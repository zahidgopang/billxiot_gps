<?php

namespace App\Services\Tracking;

use App\Models\Device;
use App\Models\User;
use App\Services\Mobile\MapRenderingSpec;
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
                'can_upload_custom' => $user && $device ? $this->auth->canUploadCustomIcon($user, $device) : false,
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

        $validated = Validator::make($data, [
            'vehicle_type' => ['sometimes', 'nullable', Rule::in(array_keys(VehicleIconLibrary::defaultTypes()))],
            'map_marker_style' => ['sometimes', Rule::in(array_keys(Device::MAP_MARKER_STYLES))],
            'map_marker_size' => ['sometimes', Rule::in(array_keys(VehicleIconLibrary::sizeScales()))],
            'map_icon_rotation_enabled' => ['sometimes', 'boolean'],
            'map_icon_source' => ['sometimes', Rule::in(array_keys(Device::MAP_ICON_SOURCES))],
        ])->validate();

        if (array_key_exists('vehicle_type', $validated)) {
            $device->vehicle_type = $validated['vehicle_type'] ?: null;
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
        if (array_key_exists('map_icon_source', $validated) && $validated['map_icon_source'] === 'default') {
            $this->iconService->delete($device);
        }

        $device->save();

        return $device->fresh()->mapAppearancePayload();
    }

    /**
     * @return array<string, mixed>
     */
    public function uploadCustomIcon(User $user, Device $device, UploadedFile $file): array
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
        $device->save();

        return array_merge($device->fresh()->mapAppearancePayload(), [
            'upload_meta' => $stored['upload_meta'],
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    public function revertToDefaultIcon(User $user, Device $device): array
    {
        if (! $this->auth->canUploadCustomIcon($user, $device) && ! $this->auth->canEditAppearance($user, $device)) {
            throw ValidationException::withMessages([
                'appearance' => [__('app.map.icon_permission_denied')],
            ]);
        }

        $this->iconService->delete($device);
        $device->map_marker_style = 'pin';
        $device->save();

        return $device->fresh()->mapAppearancePayload();
    }
}
