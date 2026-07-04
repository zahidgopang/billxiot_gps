<?php

namespace App\Services\Tracking;

use App\Models\Device;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

class DeviceVehicleIconService
{
    public function url(Device $device): ?string
    {
        $path = $device->map_custom_icon;
        if (! is_string($path) || $path === '') {
            return null;
        }

        if (! $device->usesCustomMapIcon()) {
            return null;
        }

        $disk = $this->disk();
        if (! Storage::disk($disk)->exists($path)) {
            return null;
        }

        $version = Storage::disk($disk)->lastModified($path) ?: time();

        return route('device-map-icons.show', ['device' => $device->id]).'?v='.$version;
    }

    /**
     * @return array{path: string, upload_meta: array<string, mixed>}
     */
    public function store(Device $device, UploadedFile $file): array
    {
        $this->validateUpload($file);
        $this->deleteStoredFile($device->map_custom_icon);

        $mime = strtolower((string) $file->getMimeType());
        $extension = strtolower($file->getClientOriginalExtension() ?: 'png');
        $allowed = config('vehicle_icons.upload.allowed_extensions', ['png']);
        if (! in_array($extension, $allowed, true)) {
            $extension = 'png';
        }

        $uploadMeta = [
            'resized' => false,
            'original_width' => null,
            'original_height' => null,
            'width' => null,
            'height' => null,
        ];

        $contents = file_get_contents($file->getRealPath());
        if ($contents === false) {
            throw ValidationException::withMessages([
                'icon' => [__('app.map.custom_icon_invalid_image')],
            ]);
        }

        if ($mime !== 'image/svg+xml' && config('vehicle_icons.upload.auto_resize', true)) {
            $normalized = $this->normalizeRasterContents($file->getRealPath(), $mime);
            if ($normalized !== null) {
                $contents = $normalized['contents'];
                $uploadMeta = $normalized['meta'];
                if ($normalized['extension'] !== null) {
                    $extension = $normalized['extension'];
                }
            }
        } elseif ($mime !== 'image/svg+xml') {
            $dims = $this->readRasterDimensions($file->getRealPath());
            if ($dims !== null) {
                $uploadMeta['width'] = $dims['width'];
                $uploadMeta['height'] = $dims['height'];
            }
        }

        $relativePath = $this->directory().'/'.$device->id.'/icon.'.$extension;
        Storage::disk($this->disk())->put($relativePath, $contents);

        return [
            'path' => $relativePath,
            'upload_meta' => $uploadMeta,
        ];
    }

    public function delete(Device $device): void
    {
        $this->deleteStoredFile($device->map_custom_icon);
        $device->map_custom_icon = null;
        $device->map_icon_source = 'default';
    }

    /**
     * @throws ValidationException
     */
    public function validateUpload(UploadedFile $file): void
    {
        $maxKb = (int) config('vehicle_icons.upload.max_kb', 512);

        Validator::make(
            ['icon' => $file],
            [
                'icon' => [
                    'required',
                    'file',
                    'max:'.$maxKb,
                    'mimes:'.implode(',', config('vehicle_icons.upload.allowed_extensions', ['png'])),
                ],
            ]
        )->validate();

        $mime = strtolower((string) $file->getMimeType());
        $allowedMimes = config('vehicle_icons.upload.allowed_mimes', []);
        if ($allowedMimes !== [] && ! in_array($mime, $allowedMimes, true)) {
            throw ValidationException::withMessages([
                'icon' => [__('app.map.custom_icon_invalid_type')],
            ]);
        }

        if ($mime !== 'image/svg+xml') {
            $dims = $this->readRasterDimensions($file->getRealPath());
            if ($dims === null) {
                throw ValidationException::withMessages([
                    'icon' => [__('app.map.custom_icon_invalid_image')],
                ]);
            }
        }
    }

    /**
     * @return array{width: int, height: int}|null
     */
    private function readRasterDimensions(string $path): ?array
    {
        $size = @getimagesize($path);
        if (! is_array($size)) {
            return null;
        }

        return [
            'width' => (int) $size[0],
            'height' => (int) $size[1],
        ];
    }

    /**
     * @return array{contents: string, extension: ?string, meta: array<string, mixed>}|null
     */
    private function normalizeRasterContents(string $path, string $mime): ?array
    {
        if (! extension_loaded('gd')) {
            return null;
        }

        $dims = $this->readRasterDimensions($path);
        if ($dims === null) {
            return null;
        }

        $maxW = (int) config('vehicle_icons.upload.max_width', 256);
        $maxH = (int) config('vehicle_icons.upload.max_height', 256);
        $width = $dims['width'];
        $height = $dims['height'];

        $meta = [
            'resized' => false,
            'original_width' => $width,
            'original_height' => $height,
            'width' => $width,
            'height' => $height,
        ];

        if ($width <= $maxW && $height <= $maxH) {
            return null;
        }

        $src = $this->createGdImage($path, $mime);
        if ($src === null) {
            return null;
        }

        $ratio = min($maxW / $width, $maxH / $height);
        $newW = max(1, (int) round($width * $ratio));
        $newH = max(1, (int) round($height * $ratio));

        $dst = imagecreatetruecolor($newW, $newH);
        if ($dst === false) {
            imagedestroy($src);

            return null;
        }

        imagealphablending($dst, false);
        imagesavealpha($dst, true);
        $transparent = imagecolorallocatealpha($dst, 0, 0, 0, 127);
        imagefill($dst, 0, 0, $transparent);
        imagecopyresampled($dst, $src, 0, 0, 0, 0, $newW, $newH, $width, $height);
        imagedestroy($src);

        $encoded = $this->encodeGdImage($dst, $mime);
        imagedestroy($dst);

        if ($encoded === null) {
            return null;
        }

        $meta['resized'] = true;
        $meta['width'] = $newW;
        $meta['height'] = $newH;

        return [
            'contents' => $encoded['contents'],
            'extension' => $encoded['extension'],
            'meta' => $meta,
        ];
    }

    /**
     * @return \GdImage|null
     */
    private function createGdImage(string $path, string $mime): mixed
    {
        return match ($mime) {
            'image/png' => @imagecreatefrompng($path) ?: null,
            'image/jpeg', 'image/jpg' => @imagecreatefromjpeg($path) ?: null,
            'image/webp' => function_exists('imagecreatefromwebp') ? (@imagecreatefromwebp($path) ?: null) : null,
            default => null,
        };
    }

    /**
     * @return array{contents: string, extension: string}|null
     */
    private function encodeGdImage(\GdImage $image, string $mime): ?array
    {
        ob_start();
        $ok = match ($mime) {
            'image/png' => imagepng($image, null, 6),
            'image/jpeg', 'image/jpg' => imagejpeg($image, null, 90),
            'image/webp' => function_exists('imagewebp') ? imagewebp($image, null, 90) : false,
            default => false,
        };
        $contents = ob_get_clean();

        if (! $ok || $contents === false || $contents === '') {
            return null;
        }

        $extension = match ($mime) {
            'image/jpeg', 'image/jpg' => 'jpg',
            'image/webp' => 'webp',
            default => 'png',
        };

        return [
            'contents' => $contents,
            'extension' => $extension,
        ];
    }

    private function deleteStoredFile(?string $path): void
    {
        if (! is_string($path) || $path === '') {
            return;
        }

        $disk = $this->disk();
        if (Storage::disk($disk)->exists($path)) {
            Storage::disk($disk)->delete($path);
        }
    }

    private function disk(): string
    {
        return (string) config('vehicle_icons.upload.disk', 'public');
    }

    private function directory(): string
    {
        return (string) config('vehicle_icons.upload.directory', 'device-icons');
    }
}
