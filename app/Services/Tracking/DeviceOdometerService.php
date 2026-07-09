<?php

namespace App\Services\Tracking;

use App\Contracts\Tracking\PositionReaderInterface;
use App\Models\Device;
use App\Models\DeviceLocation;
use App\Support\Geo\GeoMath;
use App\Support\Tracking\TelemetryFormatter;
use App\Support\Traccar\TraccarAppFields;
use Carbon\Carbon;
use Illuminate\Support\Facades\Cache;

class DeviceOdometerService
{
    /** Ignore GPS segments shorter than this (reduces jitter). */
    private const MIN_SEGMENT_KM = 0.002;

    public function __construct(
        private PositionReaderInterface $positions,
    ) {}

    public function hasBaseline(Device $device): bool
    {
        $base = $this->baselineKm($device);

        return $base !== null && $this->baselineSetAt($device) !== null;
    }

    public function baselineKm(Device $device): ?float
    {
        $raw = TraccarAppFields::get(
            $device->getTraccarAttributesJson(),
            TraccarAppFields::KEY_ODOMETER_BASE_KM
        );

        if ($raw === null || $raw === '') {
            return null;
        }

        return max(0, round((float) $raw, 1));
    }

    public function baselineSetAt(Device $device): ?Carbon
    {
        $raw = TraccarAppFields::get(
            $device->getTraccarAttributesJson(),
            TraccarAppFields::KEY_ODOMETER_BASE_SET_AT
        );

        if (! is_string($raw) || trim($raw) === '') {
            return null;
        }

        try {
            return Carbon::parse($raw);
        } catch (\Throwable) {
            return null;
        }
    }

    /**
     * Set or reset the client-entered odometer baseline. GPS accumulation restarts from this moment.
     */
    public function setBaseline(Device $device, float $km): void
    {
        $km = max(0, round($km, 1));
        $now = now()->toDateTimeString();

        $patch = [
            TraccarAppFields::KEY_ODOMETER_BASE_KM => $km,
            TraccarAppFields::KEY_ODOMETER_BASE_SET_AT => $now,
            TraccarAppFields::KEY_ODOMETER_GPS_ACCUM_KM => 0,
            TraccarAppFields::KEY_ODOMETER_LAST_ACCUM_AT => $now,
        ];

        $latest = $this->latestPosition($device);

        if ($latest && $latest->lat !== null && $latest->lng !== null) {
            $patch[TraccarAppFields::KEY_ODOMETER_LAST_ACCUM_LAT] = (float) $latest->lat;
            $patch[TraccarAppFields::KEY_ODOMETER_LAST_ACCUM_LNG] = (float) $latest->lng;
            if ($latest->recorded_at) {
                $patch[TraccarAppFields::KEY_ODOMETER_LAST_ACCUM_AT] = $latest->recorded_at->toDateTimeString();
            }
        } else {
            $patch[TraccarAppFields::KEY_ODOMETER_LAST_ACCUM_LAT] = null;
            $patch[TraccarAppFields::KEY_ODOMETER_LAST_ACCUM_LNG] = null;
        }

        $device->patchTraccarAppAttributes($patch);
        $device->save();
        $this->forgetCache((int) $device->id);
    }

    /**
     * Resolved odometer (km) from client baseline + GPS distance, or null when no baseline is set.
     */
    public function resolveKm(Device $device): ?float
    {
        if (! $this->hasBaseline($device)) {
            return null;
        }

        return Cache::remember(
            $this->cacheKey((int) $device->id),
            45,
            function () use ($device) {
                $this->catchUpFromPositions($device);

                $base = $this->baselineKm($device) ?? 0.0;
                $accum = $this->gpsAccumKm($device);

                return round($base + $accum, 1);
            }
        );
    }

    /**
     * Display odometer: client baseline + GPS when set, otherwise device-reported reading.
     * Always resolves positions via PositionReader (Traccar tc_positions) — never Eloquent device_locations.
     */
    public function displayKm(Device $device, mixed $reportedOdometerMeters = null): ?float
    {
        $resolved = $this->resolveKm($device);
        if ($resolved !== null) {
            return $resolved;
        }

        if ($reportedOdometerMeters === null) {
            $reportedOdometerMeters = $this->latestPosition($device)?->odometer;
        }

        return TelemetryFormatter::odometerKm($reportedOdometerMeters);
    }

    /**
     * Latest GPS point for odometer work. Uses an already-attached relation when present
     * (from DevicePositionLoader), otherwise PositionReader → Traccar tc_positions.
     */
    public function latestPosition(Device $device): ?DeviceLocation
    {
        if ($device->relationLoaded('latestLocation')) {
            $loaded = $device->getRelation('latestLocation');

            return $loaded instanceof DeviceLocation ? $loaded : null;
        }

        $latest = $this->positions->latestForDevice($device);
        $device->setRelation('latestLocation', $latest);

        return $latest;
    }

    public function onPositionRecorded(Device $device, DeviceLocation $location): void
    {
        if (! $this->hasBaseline($device)) {
            return;
        }

        $baseAt = $this->baselineSetAt($device);
        if ($baseAt === null) {
            return;
        }

        $recordedAt = $location->recorded_at;
        if ($recordedAt === null || $recordedAt->lt($baseAt)) {
            return;
        }

        $lat = $location->lat;
        $lng = $location->lng;
        if ($lat === null || $lng === null) {
            return;
        }

        $this->addSegment($device, (float) $lat, (float) $lng, $recordedAt);
        $this->forgetCache((int) $device->id);
    }

    private function catchUpFromPositions(Device $device): void
    {
        $baseAt = $this->baselineSetAt($device);
        if ($baseAt === null) {
            return;
        }

        $from = $this->lastAccumAt($device) ?? $baseAt;
        if ($from->lt($baseAt)) {
            $from = $baseAt;
        }

        $positions = $this->positions->historyForDevice($device, $from->copy()->subSecond(), now(), 'asc');

        if ($positions->isEmpty()) {
            return;
        }

        $lastAt = $this->lastAccumAt($device);
        $prevLat = $this->lastAccumLat($device);
        $prevLng = $this->lastAccumLng($device);
        $added = 0.0;
        $changed = false;

        foreach ($positions as $point) {
            $at = $point->recorded_at ?? null;
            if ($at === null || $at->lte($from)) {
                continue;
            }

            $lat = $point->lat;
            $lng = $point->lng;
            if ($lat === null || $lng === null) {
                continue;
            }

            if ($prevLat !== null && $prevLng !== null) {
                $segment = GeoMath::haversineKm($prevLat, $prevLng, (float) $lat, (float) $lng);
                if ($segment >= self::MIN_SEGMENT_KM) {
                    $added += $segment;
                }
            }

            $prevLat = (float) $lat;
            $prevLng = (float) $lng;
            $lastAt = $at;
            $changed = true;
        }

        if (! $changed) {
            return;
        }

        $patch = [
            TraccarAppFields::KEY_ODOMETER_GPS_ACCUM_KM => round($this->gpsAccumKm($device) + $added, 3),
        ];

        if ($lastAt !== null) {
            $patch[TraccarAppFields::KEY_ODOMETER_LAST_ACCUM_AT] = $lastAt->toDateTimeString();
        }
        if ($prevLat !== null && $prevLng !== null) {
            $patch[TraccarAppFields::KEY_ODOMETER_LAST_ACCUM_LAT] = $prevLat;
            $patch[TraccarAppFields::KEY_ODOMETER_LAST_ACCUM_LNG] = $prevLng;
        }

        $device->patchTraccarAppAttributes($patch);
        $device->save();
    }

    private function addSegment(Device $device, float $lat, float $lng, Carbon $at): void
    {
        $prevLat = $this->lastAccumLat($device);
        $prevLng = $this->lastAccumLng($device);
        $added = 0.0;

        if ($prevLat !== null && $prevLng !== null) {
            $segment = GeoMath::haversineKm($prevLat, $prevLng, $lat, $lng);
            if ($segment >= self::MIN_SEGMENT_KM) {
                $added = $segment;
            }
        }

        $device->patchTraccarAppAttributes([
            TraccarAppFields::KEY_ODOMETER_GPS_ACCUM_KM => round($this->gpsAccumKm($device) + $added, 3),
            TraccarAppFields::KEY_ODOMETER_LAST_ACCUM_AT => $at->toDateTimeString(),
            TraccarAppFields::KEY_ODOMETER_LAST_ACCUM_LAT => $lat,
            TraccarAppFields::KEY_ODOMETER_LAST_ACCUM_LNG => $lng,
        ]);
        $device->save();
    }

    private function gpsAccumKm(Device $device): float
    {
        $raw = TraccarAppFields::get(
            $device->getTraccarAttributesJson(),
            TraccarAppFields::KEY_ODOMETER_GPS_ACCUM_KM,
            0
        );

        return max(0, (float) $raw);
    }

    private function lastAccumAt(Device $device): ?Carbon
    {
        $raw = TraccarAppFields::get(
            $device->getTraccarAttributesJson(),
            TraccarAppFields::KEY_ODOMETER_LAST_ACCUM_AT
        );

        if (! is_string($raw) || trim($raw) === '') {
            return null;
        }

        try {
            return Carbon::parse($raw);
        } catch (\Throwable) {
            return null;
        }
    }

    private function lastAccumLat(Device $device): ?float
    {
        $raw = TraccarAppFields::get(
            $device->getTraccarAttributesJson(),
            TraccarAppFields::KEY_ODOMETER_LAST_ACCUM_LAT
        );

        return is_numeric($raw) ? (float) $raw : null;
    }

    private function lastAccumLng(Device $device): ?float
    {
        $raw = TraccarAppFields::get(
            $device->getTraccarAttributesJson(),
            TraccarAppFields::KEY_ODOMETER_LAST_ACCUM_LNG
        );

        return is_numeric($raw) ? (float) $raw : null;
    }

    private function cacheKey(int $deviceId): string
    {
        return "device.odometer.km.{$deviceId}";
    }

    private function forgetCache(int $deviceId): void
    {
        Cache::forget($this->cacheKey($deviceId));
    }
}
