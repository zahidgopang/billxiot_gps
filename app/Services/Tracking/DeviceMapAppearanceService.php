<?php

namespace App\Services\Tracking;

use App\Models\Device;
use App\Models\User;
use App\Services\Mobile\MapRenderingSpec;
use App\Support\VehicleIcons\BuiltinMapIconStorage;
use App\Support\VehicleIcons\VehicleIconLibrary;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class DeviceMapAppearanceService
{
    public function __construct(
        private DeviceVehicleIconService $iconService,
        private DeviceMapIconAuthorization $auth,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function options(?User $user = null, ?Device $device = null): array
    {
        $sizePresets = VehicleIconLibrary::sizeScales();

        return [
            'vehicle_types' => VehicleIconLibrary::defaultOptions(),
            'marker_styles' => collect(Device::MAP_MARKER_STYLES)->map(fn ($label, $key) => [
                'value' => $key,
                'label' => (string) __('app.map.marker_style_'.$key),
            ])->values()->all(),
            'marker_sizes' => collect($sizePresets)->map(fn ($scale, $key) => [
                'value' => (string) $key,
                'label' => (string) __('app.map.marker_size_'.$key),
                'scale' => $scale,
            ])->values()->all(),
            'marker_size_scales' => $sizePresets,
            'icon_sources' => collect(Device::MAP_ICON_SOURCES)->map(fn ($label, $key) => [
                'value' => $key,
                'label' => (string) __('app.map.icon_source_'.$key),
            ])->values()->all(),
            'upload' => [
                'max_kb' => (int) config('vehicle_icons.upload.max_kb', 512),
                'max_width' => (int) config('vehicle_icons.upload.max_width', 256),
                'max_height' => (int) config('vehicle_icons.upload.max_height', 256),
                'auto_resize' => (bool) config('vehicle_icons.upload.auto_resize', true),
                'allowed_extensions' => config('vehicle_icons.upload.allowed_extensions', ['png']),
            ],
            'map_rendering' => MapRenderingSpec::toArray(),
            'tips' => [
                'size_on_map' => (string) __('app.map.custom_icon_size_tip'),
                'auto_resize' => (string) __('app.map.custom_icon_auto_resize_hint'),
            ],
            'permissions' => [
                'can_edit' => $user && $device ? $this->auth->canEditAppearance($user, $device) : false,
                'can_change_icon' => $user && $device ? $this->auth->canChangeVehicleIcon($user, $device) : false,
                'can_change_size' => $user && $device ? $this->auth->canChangeIconSize($user, $device) : false,
                'can_upload_custom' => $user && $device ? $this->auth->canUploadCustomIcon($user, $device) : false,
                'can_view_details' => $user && $device ? $this->auth->canViewVehicleDetails($user, $device) : false,
            ],
        ];
    }

    /**
     * Mobile app management is upload-only: no default catalog, no default icon
     * switching. The saved appearance payload is still returned for rendering.
     *
     * @return array<string, mixed>
     */
    public function mobileUploadOptions(?User $user = null, ?Device $device = null): array
    {
        $options = $this->options($user, $device);
        unset($options['vehicle_types'], $options['marker_styles'], $options['icon_sources']);

        $options['mode'] = 'custom_upload_only';
        $options['actions'] = [
            'upload_custom_icon' => (bool) ($options['permissions']['can_upload_custom'] ?? false),
            'resize_custom_icon' => (bool) ($options['permissions']['can_edit'] ?? false),
            'choose_default_icon' => false,
            'remove_custom_icon' => false,
        ];
        $options['navigation'] = [
            'back_enabled' => true,
            'back_label' => (string) __('app.map.back_devices'),
        ];

        if ($device) {
            $options['appearance'] = $device->mapAppearancePayload();
        }

        return $options;
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    public function update(User $user, Device $device, array $data): array
    {
        if (! $this->auth->canEditAppearance($user, $device)) {
            throw ValidationException::withMessages([
                'appearance' => [__('app.map.icon_permission_denied')],
            ]);
        }

        $this->assertAllowedAppearanceFields($user, $device, $data);

        $validated = Validator::make($data, [
            'vehicle_type' => ['sometimes', 'nullable', 'string', 'max:80'],
            'map_builtin_icon_path' => ['sometimes', 'nullable', 'string', 'max:160'],
            'map_marker_style' => ['sometimes', Rule::in(array_keys(Device::MAP_MARKER_STYLES))],
            'map_marker_size' => ['sometimes', Rule::in(array_keys(VehicleIconLibrary::sizeScales()))],
            'map_icon_rotation_enabled' => ['sometimes', 'boolean'],
            'map_icon_rotation_offset' => ['sometimes', 'nullable', 'integer', 'min:-180', 'max:270'],
            'map_icon_source' => ['sometimes', Rule::in(array_keys(Device::MAP_ICON_SOURCES))],
        ])->validate();

        if (array_key_exists('vehicle_type', $validated) && $validated['vehicle_type']) {
            if (! VehicleIconLibrary::isValidDefaultType($validated['vehicle_type'])) {
                throw ValidationException::withMessages([
                    'vehicle_type' => [__('app.map.builtin_icon_invalid')],
                ]);
            }
        }

        if (array_key_exists('map_builtin_icon_path', $validated)) {
            $path = is_string($validated['map_builtin_icon_path']) ? trim($validated['map_builtin_icon_path']) : '';
            if ($path !== '' && ! VehicleIconLibrary::isValidIconPath($path)) {
                throw ValidationException::withMessages([
                    'map_builtin_icon_path' => [__('app.map.builtin_icon_invalid')],
                ]);
            }
            $device->map_builtin_icon_path = $path !== '' ? $path : null;
        }

        if (array_key_exists('vehicle_type', $validated)) {
            $device->vehicle_type = $validated['vehicle_type'] ?: null;
            // Selecting a library / shared icon clears per-device custom upload.
            if ($validated['vehicle_type']) {
                if (! array_key_exists('map_builtin_icon_path', $validated)) {
                    if (\App\Support\VehicleIcons\SharedMapIconStorage::isSharedType($validated['vehicle_type'])) {
                        $shared = \App\Support\VehicleIcons\SharedMapIconStorage::findByType($validated['vehicle_type']);
                        $device->map_builtin_icon_path = $shared?->relative_path;
                    } else {
                        $device->map_builtin_icon_path = BuiltinMapIconStorage::relativePathForType($validated['vehicle_type']);
                    }
                }
                if (! array_key_exists('map_icon_source', $validated)) {
                    $this->iconService->delete($device);
                }
                if (! array_key_exists('map_marker_style', $validated)) {
                    $device->map_marker_style = $validated['vehicle_type'] === 'pin_marker' ? 'pin' : 'body';
                }
            }
        }
        if (array_key_exists('map_marker_style', $validated)) {
            $device->map_marker_style = $validated['map_marker_style'];
        }
        if (array_key_exists('map_marker_size', $validated)) {
            $device->map_marker_size = $validated['map_marker_size'];
        }
        if (array_key_exists('map_icon_rotation_enabled', $validated)) {
            $device->map_icon_rotation_enabled = $validated['map_icon_rotation_enabled'];
        }
        if (array_key_exists('map_icon_rotation_offset', $validated)) {
            $device->map_icon_rotation_offset = Device::normalizeRotationOffsetDegrees(
                $validated['map_icon_rotation_offset']
            );
        }
        if (array_key_exists('map_icon_source', $validated) && $validated['map_icon_source'] === 'default') {
            $this->iconService->delete($device);
        }

        // Selecting a shared library icon inherits that icon's orientation offset.
        if (array_key_exists('vehicle_type', $validated) && $validated['vehicle_type']
            && ! array_key_exists('map_icon_rotation_offset', $validated)) {
            if (\App\Support\VehicleIcons\SharedMapIconStorage::isSharedType($validated['vehicle_type'])) {
                $shared = \App\Support\VehicleIcons\SharedMapIconStorage::findByType($validated['vehicle_type']);
                $device->map_icon_rotation_offset = $shared?->signedRotationOffset() ?? -90;
            } else {
                $device->map_icon_rotation_offset = 0;
            }
        }

        $device->save();

        return $device->fresh()->mapAppearancePayload();
    }

    /**
     * @return array<string, mixed>
     */
    public function uploadCustomIcon(User $user, Device $device, UploadedFile $file, int|string|null $rotationOffset = null): array
    {
        if (! $this->auth->canUploadCustomIcon($user, $device)) {
            throw ValidationException::withMessages([
                'icon' => [__('app.map.icon_upload_permission_denied')],
            ]);
        }

        $stored = $this->iconService->store($device, $file);
        $device->map_custom_icon = $stored['path'];
        $device->map_icon_source = 'custom';
        $device->map_marker_style = 'body';
        $offset = Device::normalizeRotationOffsetDegrees($rotationOffset ?? 0);
        $device->map_icon_rotation_offset = $offset;
        $device->save();

        return array_merge($device->fresh()->mapAppearancePayload(), [
            'upload_meta' => array_merge($stored['upload_meta'] ?? [], [
                'anchor_x' => 0.5,
                'anchor_y' => 0.5,
                'rotation_center_x' => 0.5,
                'rotation_center_y' => 0.5,
                'rotation_offset' => $offset,
                'thumbnail_url' => $stored['thumbnail_url'] ?? null,
            ]),
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    public function revertToDefaultIcon(User $user, Device $device): array
    {
        if (! $this->auth->canUploadCustomIcon($user, $device)) {
            throw ValidationException::withMessages([
                'appearance' => [__('app.map.icon_permission_denied')],
            ]);
        }

        $this->iconService->delete($device);
        $device->map_marker_style = 'body';
        $device->save();

        return $device->fresh()->mapAppearancePayload();
    }

    /**
     * Apply appearance settings to one or many devices the user can edit.
     *
     * @param  list<int|string>  $deviceIds
     * @param  array<string, mixed>  $data
     * @return array{updated: int, failed: list<array{id: int, message: string}>}
     */
    public function updateMany(User $user, array $deviceIds, array $data): array
    {
        $updated = 0;
        $failed = [];

        foreach ($this->resolveOwnedDevices($user, $deviceIds) as $device) {
            try {
                $this->update($user, $device, $data);
                $updated++;
            } catch (ValidationException $e) {
                $failed[] = [
                    'id' => (int) $device->id,
                    'message' => collect($e->errors())->flatten()->first() ?: __('app.map.marker_appearance_save_failed'),
                ];
            }
        }

        return compact('updated', 'failed');
    }

    /**
     * @param  list<int|string>  $deviceIds
     * @return array{updated: int, failed: list<array{id: int, message: string}>}
     */
    public function uploadCustomIconMany(User $user, array $deviceIds, UploadedFile $file): array
    {
        $updated = 0;
        $failed = [];
        $tempPath = null;

        try {
            $contents = file_get_contents($file->getRealPath());
            if ($contents === false) {
                throw ValidationException::withMessages([
                    'icon' => [__('app.map.custom_icon_invalid_image')],
                ]);
            }

            $tempPath = tempnam(sys_get_temp_dir(), 'mapicon_');
            if ($tempPath === false || file_put_contents($tempPath, $contents) === false) {
                throw ValidationException::withMessages([
                    'icon' => [__('app.map.custom_icon_invalid_image')],
                ]);
            }

            foreach ($this->resolveOwnedDevices($user, $deviceIds) as $device) {
                try {
                    $clone = new UploadedFile(
                        $tempPath,
                        $file->getClientOriginalName(),
                        $file->getClientMimeType(),
                        null,
                        true
                    );
                    $this->uploadCustomIcon($user, $device, $clone);
                    $updated++;
                } catch (ValidationException $e) {
                    $failed[] = [
                        'id' => (int) $device->id,
                        'message' => collect($e->errors())->flatten()->first() ?: __('app.map.icon_upload_permission_denied'),
                    ];
                }
            }
        } catch (ValidationException $e) {
            return [
                'updated' => 0,
                'failed' => [[
                    'id' => 0,
                    'message' => collect($e->errors())->flatten()->first() ?: __('app.map.icon_upload_permission_denied'),
                ]],
            ];
        } finally {
            if (is_string($tempPath) && is_file($tempPath)) {
                @unlink($tempPath);
            }
        }

        return compact('updated', 'failed');
    }

    /**
     * @param  list<int|string>  $deviceIds
     * @return \Illuminate\Support\Collection<int, Device>
     */
    private function resolveOwnedDevices(User $user, array $deviceIds): \Illuminate\Support\Collection
    {
        $ids = collect($deviceIds)
            ->map(fn ($id) => (int) $id)
            ->filter(fn ($id) => $id > 0)
            ->unique()
            ->values()
            ->all();

        if ($ids === []) {
            return collect();
        }

        return $user->trackerDevicesQuery()
            ->whereIn('id', $ids)
            ->get();
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function assertAllowedAppearanceFields(User $user, Device $device, array $data): void
    {
        $iconFields = ['vehicle_type', 'map_builtin_icon_path', 'map_marker_style', 'map_icon_source', 'map_icon_rotation_offset'];
        foreach ($iconFields as $field) {
            if (array_key_exists($field, $data) && ! $this->auth->canChangeVehicleIcon($user, $device)) {
                throw ValidationException::withMessages([
                    $field => [__('app.map.icon_permission_denied')],
                ]);
            }
        }

        $sizeFields = ['map_marker_size', 'map_icon_rotation_enabled'];
        foreach ($sizeFields as $field) {
            if (array_key_exists($field, $data) && ! $this->auth->canChangeIconSize($user, $device)) {
                throw ValidationException::withMessages([
                    $field => [__('app.map.icon_permission_denied')],
                ]);
            }
        }
    }
}
