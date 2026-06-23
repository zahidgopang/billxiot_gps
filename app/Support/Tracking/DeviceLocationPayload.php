<?php

namespace App\Support\Tracking;

use App\Models\Device;
use App\Models\DeviceLocation;
use App\Services\Mobile\MobileMapStatusResolver;

final class DeviceLocationPayload
{
    public static function fromDeviceLocation(DeviceLocation $location, ?Device $device = null): array
    {
        $payload = [
            'lat' => (float) $location->lat,
            'lng' => (float) $location->lng,
            'speed' => (float) ($location->speed ?? 0),
            'heading' => (float) ($location->heading ?? 0),
            'battery' => $location->battery_level,
            'battery_level' => $location->battery_level,
            'ignition' => (bool) $location->ignition,
            'acc' => (bool) ($location->acc ?? false),
            'gsm_signal' => $location->gsm_signal,
            'gps_signal' => $location->gps_signal,
            'satellites' => $location->satellites,
            'odometer' => $location->odometer,
            'power_cut' => (bool) $location->power_cut,
            'panic' => (bool) $location->panic,
            'recorded_at' => $location->recorded_at?->toIso8601String(),
            'last_update' => app_datetime_api($location->recorded_at),
            'timestamp' => $location->recorded_at?->toDateTimeString(),
            'position_id' => (int) ($location->id ?? 0),
        ];

        if ($device) {
            $resolver = app(MobileMapStatusResolver::class);
            $map = $resolver->resolve($location, $device);
            $payload['status'] = $map['label'];
            $payload['status_key'] = $map['key'];
            $payload['connectivity_tier'] = $map['connectivity_tier'];
            $payload['last_known_status'] = $map['last_known_status'];
            $payload['last_known_status_key'] = $map['last_known_status_key'];
            $payload['last_known_speed'] = $map['last_known_speed'];
            $payload['last_known_ignition'] = $map['last_known_ignition'];
            $payload['is_online'] = $resolver->isRecentlyOnline($location);
            $payload['online'] = $payload['is_online'];
        } else {
            $payload['online'] = true;
        }

        return $payload;
    }
}
