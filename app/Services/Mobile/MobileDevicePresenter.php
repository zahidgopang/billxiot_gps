<?php

namespace App\Services\Mobile;

use App\Models\Device;
use App\Services\DeviceSubscriptionService;

class MobileDevicePresenter
{
    public function __construct(
        private DeviceSubscriptionService $subscriptions,
        private MobileMapStatusResolver $mapStatus,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function listItem(Device $device, ?\Illuminate\Support\Collection $alertDeviceIds = null): array
    {
        $latest = $device->latestLocation;
        $map = $this->mapStatus->resolve($latest, $device);
        $sub = $this->subscriptions->statusLabel($device);

        return [
            'id' => $device->id,
            'name' => $device->name,
            'imei' => $device->imei,
            'device_type' => $device->device_type,
            'device_type_label' => $device->deviceTypeLabel(),
            'vehicle_name' => $device->vehicle_name ?? null,
            'vehicle_number' => $device->vehicle_number ?? null,
            'vehicle_type' => $device->vehicle_type ?? null,
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
            'subscription_active' => $sub['active'],
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
    public function detail(Device $device, ?\Illuminate\Support\Collection $alertDeviceIds = null): array
    {
        return array_merge($this->listItem($device, $alertDeviceIds), [
            'vehicle_name' => $device->vehicle_name ?? null,
            'vehicle_number' => $device->vehicle_number ?? null,
            'description' => $device->description,
        ]);
    }

    /**
     * @return array<string, mixed>|null
     */
    public function livePosition(Device $device): ?array
    {
        $latest = $device->latestLocation;

        if (! $latest) {
            return null;
        }

        $map = $this->mapStatus->resolve($latest, $device);

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
            'gps_fix' => $latest->gps_fix,
            'address' => null,
            'last_update' => app_datetime_api($latest->recorded_at),
            'last_update_display' => app_datetime_format($latest->recorded_at),
            'status' => $map['label'],
            'status_key' => $map['key'],
            'connectivity_tier' => $map['connectivity_tier'],
            'last_known_status' => $map['last_known_status'],
            'last_known_status_key' => $map['last_known_status_key'],
            'last_known_speed' => $map['last_known_speed'],
            'last_known_ignition' => $map['last_known_ignition'],
            'is_online' => $this->mapStatus->isRecentlyOnline($latest),
            'vehicle_name' => $device->vehicle_name,
            'vehicle_number' => $device->vehicle_number,
            'map_marker_title' => $device->mapMarkerTitle(),
            'map_marker_plate' => $device->mapMarkerPlateLine(),
            'map_rendering' => MapRenderingSpec::toArray(),
        ];
    }
}
