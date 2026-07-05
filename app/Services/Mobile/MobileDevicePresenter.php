<?php

namespace App\Services\Mobile;

use App\Models\Device;
use App\Services\DeviceSubscriptionService;
use App\Services\Mobile\MobileMapStatusResolver;
use App\Services\Mobile\VehicleStatusSpec;
use App\Services\Tracking\StatusDurationResolver;
use App\Support\Tracking\TelemetryFormatter;

class MobileDevicePresenter
{
    public function __construct(
        private DeviceSubscriptionService $subscriptions,
        private MobileMapStatusResolver $mapStatus,
        private StatusDurationResolver $statusDuration,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function listItem(
        Device $device,
        ?\Illuminate\Support\Collection $alertDeviceIds = null,
        ?\App\Models\User $viewer = null,
    ): array {
        $latest = $device->latestLocation;
        $map = $this->mapStatus->resolve($latest, $device);
        $sub = $this->subscriptions->statusLabel($device);
        $rbac = app(\App\Services\Authorization\RbacService::class);
        $subscriptionActive = ($viewer && $rbac->bypassesSubscriptionRestrictions($viewer))
            || $sub['active'];

        return [
            'id' => $device->id,
            'name' => $device->name,
            'imei' => $device->imei,
            'device_type' => $device->device_type,
            'device_type_label' => $device->deviceTypeLabel(),
            'vehicle_name' => $device->vehicle_name ?? null,
            'vehicle_number' => $device->vehicle_number ?? null,
            'vehicle_type' => $device->defaultMapIconName(),
            'map_icon_source' => $device->usesCustomMapIcon() ? 'custom' : 'default',
            'map_custom_icon_url' => $device->usesCustomMapIcon()
                ? app(\App\Services\Tracking\DeviceVehicleIconService::class)->url($device)
                : null,
            'map_marker_style' => $device->map_marker_style,
            'map_marker_size' => $device->map_marker_size,
            'map_marker_size_scale' => $device->mapMarkerSizeScale(),
            'map_icon_rotation_enabled' => $device->map_icon_rotation_enabled,
            'display_name' => $device->mapMarkerTitle(),
            'map_marker_title' => $device->mapMarkerTitle(),
            'map_marker_plate' => $device->mapMarkerPlateLine(),
            'notification_display_name' => $device->notificationDisplayName(),
            'status' => $map['label'],
            'status_key' => $map['key'],
            'connectivity_tier' => $map['connectivity_tier'],
            'last_known_status' => $map['last_known_status'],
            'last_known_status_key' => $map['last_known_status_key'],
            'last_known_speed' => $map['last_known_speed'],
            'last_known_ignition' => $map['last_known_ignition'],
            'live_status' => $map['label'],
            'is_online' => $this->mapStatus->isRecentlyOnline($latest),
            'speed' => $latest ? (float) ($latest->speed ?? 0) : null,
            'subscription_status' => $sub['label'],
            'subscription_active' => $subscriptionActive,
            'last_update' => app_datetime_api($latest?->recorded_at),
            'last_update_display' => app_datetime_format($latest?->recorded_at),
            'battery' => $latest?->battery_level,
            'gsm_signal' => $latest?->gsm_signal,
            'gps_signal' => $latest?->gps_signal,
            'satellites' => $latest?->satellites,
            'lat' => $latest ? (float) $latest->lat : null,
            'lng' => $latest ? (float) $latest->lng : null,
            'map_rendering' => MapRenderingSpec::toArray(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function detail(
        Device $device,
        ?\Illuminate\Support\Collection $alertDeviceIds = null,
        ?\App\Models\User $viewer = null,
    ): array {
        return array_merge($this->listItem($device, $alertDeviceIds, $viewer), [
            'vehicle_name' => $device->vehicle_name ?? null,
            'vehicle_number' => $device->vehicle_number ?? null,
            'description' => $device->description,
        ]);
    }

    /**
     * @return array<string, mixed>|null
     */
    public function livePosition(Device $device, bool $withStatusDuration = true): ?array
    {
        $latest = $device->latestLocation;

        if (! $latest) {
            return null;
        }

        $map = $this->mapStatus->resolve($latest, $device);
        $motionKey = VehicleStatusSpec::motionKey(
            (float) ($latest->speed ?? 0),
            (bool) $latest->ignition,
        );
        $duration = $withStatusDuration
            ? $this->statusDuration->resolve($device, $latest, $map)
            : [
                'since' => $latest->recorded_at?->copy(),
                'seconds' => null,
            ];

        return [
            'lat' => (float) $latest->lat,
            'lng' => (float) $latest->lng,
            'speed' => (float) ($latest->speed ?? 0),
            'ignition' => (bool) $latest->ignition,
            'heading' => (float) ($latest->heading ?? 0),
            'battery' => $latest->battery_level,
            'gsm_signal' => $latest->gsm_signal,
            'gps_signal' => $latest->gps_signal,
            'satellites' => $latest->satellites,
            'odometer' => $latest->odometer,
            'odometer_km' => TelemetryFormatter::odometerKm($latest->odometer),
            'altitude' => $latest->altitude !== null ? round((float) $latest->altitude) : null,
            'gps_fix' => $latest->gps_fix,
            'address' => null,
            'last_update' => app_datetime_api($latest->recorded_at),
            'last_update_display' => app_datetime_format($latest->recorded_at),
            'status' => $map['label'],
            'status_key' => $map['key'],
            'motion_status' => VehicleStatusSpec::motionLabel($motionKey),
            'motion_status_key' => $motionKey,
            'status_since' => $duration['since']
                ? app_datetime_api($duration['since'])
                : null,
            'status_duration_seconds' => $duration['seconds'],
            'connectivity_tier' => $map['connectivity_tier'],
            'last_known_status' => $map['last_known_status'],
            'last_known_status_key' => $map['last_known_status_key'],
            'last_known_speed' => $map['last_known_speed'],
            'last_known_ignition' => $map['last_known_ignition'],
            'is_online' => $this->mapStatus->isRecentlyOnline($latest),
            'vehicle_name' => $device->vehicle_name,
            'vehicle_number' => $device->vehicle_number,
            'vehicle_type' => $device->defaultMapIconName(),
            'map_icon_source' => $device->usesCustomMapIcon() ? 'custom' : 'default',
            'map_custom_icon_url' => $device->usesCustomMapIcon()
                ? app(\App\Services\Tracking\DeviceVehicleIconService::class)->url($device)
                : null,
            'map_marker_style' => $device->map_marker_style,
            'map_marker_size' => $device->map_marker_size,
            'map_marker_size_scale' => $device->mapMarkerSizeScale(),
            'map_icon_rotation_enabled' => $device->map_icon_rotation_enabled,
            'map_marker_title' => $device->mapMarkerTitle(),
            'map_marker_plate' => $device->mapMarkerPlateLine(),
            'map_rendering' => MapRenderingSpec::toArray(),
        ];
    }
}
