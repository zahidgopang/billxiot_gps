<?php

namespace App\Services\Tracking;

use App\Models\SharedMapIcon;
use App\Models\User;
use App\Support\VehicleIcons\SharedMapIconStorage;
use App\Support\VehicleIcons\VehicleIconLibrary;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class SharedMapIconService
{
    public function __construct(
        private DeviceVehicleIconService $deviceIcons,
    ) {}

    /**
     * Categories Super Admin can upload into (excludes the old "shared" bucket).
     *
     * @return list<array{id: string, label: string, order: int}>
     */
    public function uploadCategories(): array
    {
        return collect(VehicleIconLibrary::categories())
            ->reject(fn (array $cat) => ($cat['id'] ?? '') === 'shared')
            ->values()
            ->all();
    }

    /**
     * @return list<string>
     */
    public function allowedCategoryIds(): array
    {
        return collect($this->uploadCategories())->pluck('id')->map(fn ($id) => (string) $id)->all();
    }

    /**
     * @return Collection<int, SharedMapIcon>
     */
    public function all(?string $category = null): Collection
    {
        return SharedMapIcon::query()
            ->when($category, fn ($q) => $q->where('category', $category))
            ->orderBy('category')
            ->orderBy('label')
            ->orderBy('id')
            ->get();
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function registryEntries(): array
    {
        return $this->all()->map(function (SharedMapIcon $icon) {
            $category = $this->normalizeCategory($icon->category);
            $absolute = SharedMapIconStorage::absolutePath($icon->relative_path);
            if (! is_file($absolute)) {
                return null;
            }

            return [
                'id' => $icon->registryId(),
                'category' => $category,
                'path' => $icon->relative_path,
                'url' => SharedMapIconStorage::urlForRelativePath($icon->relative_path),
                'label' => $icon->label,
                'tags' => array_values($icon->tags ?? ['custom', $category, $icon->slug]),
                'shared' => true,
                'rotation_offset' => $icon->signedRotationOffset(),
                'anchor_x' => 0.5,
                'anchor_y' => 0.5,
            ];
        })->filter()->values()->all();
    }

    /**
     * @return array{icon: SharedMapIcon}
     */
    public function store(
        User $uploader,
        UploadedFile $file,
        ?string $label = null,
        ?string $category = null,
        int|string|null $rotationOffset = null,
    ): array {
        $this->deviceIcons->validateUpload($file);
        $category = $this->normalizeCategory($category);
        $offset = $this->normalizeRotationOffset($rotationOffset);

        $original = pathinfo((string) $file->getClientOriginalName(), PATHINFO_FILENAME);
        $baseLabel = trim((string) ($label ?: $original));
        if ($baseLabel === '') {
            $baseLabel = 'Custom icon';
        }

        $slug = $this->uniqueSlug($baseLabel);
        $mime = strtolower((string) $file->getMimeType());
        $extension = strtolower($file->getClientOriginalExtension() ?: 'png');
        if (! in_array($extension, ['png', 'svg'], true)) {
            $extension = $mime === 'image/svg+xml' ? 'svg' : 'png';
        }

        $contents = file_get_contents($file->getRealPath());
        if ($contents === false) {
            throw ValidationException::withMessages([
                'icon' => [__('app.map.custom_icon_invalid_image')],
            ]);
        }

        if ($mime !== 'image/svg+xml' && config('vehicle_icons.upload.auto_resize', true)) {
            $normalized = $this->deviceIcons->normalizeRasterUpload($file->getRealPath(), $mime);
            if (is_array($normalized) && ! empty($normalized['contents'])) {
                $contents = $normalized['contents'];
                if (! empty($normalized['extension'])) {
                    $extension = $normalized['extension'];
                }
            }
        }

        File::ensureDirectoryExists(SharedMapIconStorage::diskRoot().DIRECTORY_SEPARATOR.SharedMapIconStorage::FOLDER);

        $relativePath = SharedMapIconStorage::relativePathForSlug($slug, $extension);
        $absolute = SharedMapIconStorage::absolutePath($relativePath);
        if (file_put_contents($absolute, $contents) === false) {
            throw ValidationException::withMessages([
                'icon' => [__('app.map.shared_icon_store_failed')],
            ]);
        }

        $icon = SharedMapIcon::query()->create([
            'slug' => $slug,
            'label' => Str::limit($baseLabel, 120, ''),
            'category' => $category,
            'relative_path' => $relativePath,
            'mime' => $mime,
            'rotation_offset' => $offset,
            'tags' => ['custom', $category, $slug],
            'uploaded_by' => $uploader->id,
        ]);

        return ['icon' => $icon];
    }

    public function updateRotationOffset(SharedMapIcon $icon, int|string|null $rotationOffset): SharedMapIcon
    {
        $icon->rotation_offset = $this->normalizeRotationOffset($rotationOffset);
        $icon->save();

        return $icon->fresh();
    }

    public function delete(SharedMapIcon $icon): void
    {
        $absolute = SharedMapIconStorage::absolutePath($icon->relative_path);
        if (is_file($absolute)) {
            @unlink($absolute);
        }
        $icon->delete();
    }

    public function normalizeCategory(?string $category): string
    {
        $category = strtolower(trim((string) $category));
        $allowed = $this->allowedCategoryIds();

        if ($category === '' || $category === 'shared' || ! in_array($category, $allowed, true)) {
            return 'vehicles';
        }

        return $category;
    }

    /**
     * Normalize to one of: 0, 90, -90, 180 (or nearest quarter-turn).
     */
    public function normalizeRotationOffset(int|string|null $value): int
    {
        if ($value === null || $value === '') {
            // Prefer top-down nose-up (North). Existing side-view icons are migrated to -90.
            return 0;
        }

        $raw = (int) $value;
        $raw = (($raw % 360) + 360) % 360;
        $quarters = [0, 90, 180, 270];
        $nearest = 0;
        $best = 360;
        foreach ($quarters as $q) {
            $dist = min(abs($raw - $q), 360 - abs($raw - $q));
            if ($dist < $best) {
                $best = $dist;
                $nearest = $q;
            }
        }

        return $nearest === 270 ? -90 : ($nearest === 180 ? 180 : ($nearest === 90 ? 90 : 0));
    }

    private function uniqueSlug(string $label): string
    {
        $base = Str::slug($label, '_');
        $base = preg_replace('/[^a-z0-9_]+/', '_', strtolower((string) $base)) ?: 'custom_icon';
        $base = trim($base, '_');
        if ($base === '') {
            $base = 'custom_icon';
        }
        $base = Str::limit($base, 60, '');

        $slug = $base;
        $i = 2;
        while (SharedMapIcon::query()->where('slug', $slug)->exists()) {
            $slug = Str::limit($base, 50, '').'_'.$i;
            $i++;
        }

        return $slug;
    }
}
