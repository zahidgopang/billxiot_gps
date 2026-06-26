<?php

namespace App\Services\Traccar;

use App\Models\DeviceLocation;
use App\Support\Traccar\TraccarAttributes;
use App\Support\Traccar\TraccarSpeed;
use Carbon\Carbon;
use stdClass;

class TraccarPositionMapper
{
    public function toDeviceLocation(stdClass|array $row, int $laravelDeviceId): DeviceLocation
    {
        $data = is_array($row) ? $row : (array) $row;
        $attrs = TraccarAttributes::decode($data['attributes'] ?? null);
        $fields = TraccarAttributes::toPositionFields($attrs);
        // Traccar stores tc_positions timestamps in UTC. Parse them as UTC and
        // shift to the app timezone so the DeviceLocation datetime cast (which
        // re-parses in the app timezone) preserves the correct instant — otherwise
        // a UTC fix is read as local time and the device looks hours stale/offline.
        $appTz = (string) config('app.timezone', 'UTC');
        $rawFixtime = $data['fixtime'] ?? $data['devicetime'] ?? $data['servertime'] ?? null;
        $recordedAt = $rawFixtime !== null
            ? ($rawFixtime instanceof \DateTimeInterface
                ? Carbon::instance($rawFixtime)->setTimezone($appTz)
                : Carbon::parse($rawFixtime, 'UTC')->setTimezone($appTz))
            : now();

        $satellites = $fields['satellites'];
        if ($satellites === null && isset($data['satellites'])) {
            $satellites = is_numeric($data['satellites']) ? (int) $data['satellites'] : null;
        }

        $gsmSignal = $fields['gsm_signal'];
        if ($gsmSignal === null && isset($data['gsm_signal'])) {
            $gsmSignal = is_numeric($data['gsm_signal']) ? (int) $data['gsm_signal'] : null;
        }

        $gpsSignal = $fields['gps_signal'];
        if ($gpsSignal === null && isset($data['gps_signal'])) {
            $gpsSignal = is_numeric($data['gps_signal']) ? (int) $data['gps_signal'] : null;
        }

        $location = new DeviceLocation([
            'device_id' => $laravelDeviceId,
            'lat' => (float) ($data['latitude'] ?? 0),
            'lng' => (float) ($data['longitude'] ?? 0),
            'speed' => TraccarSpeed::knotsToKmh((float) ($data['speed'] ?? 0)),
            'heading' => (float) ($data['course'] ?? 0),
            'battery_level' => $fields['battery_level'],
            'gps_fix' => $fields['gps_fix'],
            'recorded_at' => $recordedAt,
            'ignition' => $fields['ignition'],
            'acc' => $fields['acc'],
            'gsm_signal' => $gsmSignal,
            'gps_signal' => $gpsSignal,
            'satellites' => $satellites,
            'odometer' => $fields['odometer'],
            'power_cut' => $fields['power_cut'],
            'panic' => $fields['panic'],
        ]);

        $location->id = (int) ($data['id'] ?? 0);
        $location->exists = true;

        return $location;
    }
}
