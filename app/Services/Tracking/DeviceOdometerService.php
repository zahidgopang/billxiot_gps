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
    /**
     * Ignore shorter GPS hops (parking / multipath jitter).
     * ~15 m — previous 2 m threshold let parked vehicles inflate odometer.
     */
    private const MIN_SEGMENT_KM = 0.015;

    /** Reject teleport / bad fixes (straight-line hop larger than this). */
    private const MAX_SEGMENT_KM = 5.0;

    /** Reject hops whose implied speed exceeds this (km/h). */
    private const MAX_IMPLIED_SPEED_KMH = 200.0;

    /**
     * Only count distance when the vehicle is actually moving.
     * Uses reported speed when present, otherwise implied speed from the hop.
     */
    private const MIN_MOVING_SPEED_KMH = 1.5;

    /** Do not bridge long offline gaps with a single straight-line hop. */
    private const MAX_GAP_SECONDS = 600;

    /** Filter / rebuild version stored on the device attributes. */
    public const ACCUM_VERSION = 2;

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
        $now = now();
        $nowStr = $now->toDateTimeString();

        // Always anchor accumulation at the moment the client sets the dash reading.
        // Never backdate last_accum_at to an older GPS fix — that re-adds distance already
        // included in the dash reading and causes large over-reads.
        $patch = [
            TraccarAppFields::KEY_ODOMETER_BASE_KM => $km,
            TraccarAppFields::KEY_ODOMETER_BASE_SET_AT => $nowStr,
            TraccarAppFields::KEY_ODOMETER_GPS_ACCUM_KM => 0,
            TraccarAppFields::KEY_ODOMETER_LAST_ACCUM_AT => $nowStr,
            TraccarAppFields::KEY_ODOMETER_ACCUM_VERSION => self::ACCUM_VERSION,
        ];

        $latest = $this->latestPosition($device);

        if ($latest && $latest->lat !== null && $latest->lng !== null) {
            $patch[TraccarAppFields::KEY_ODOMETER_LAST_ACCUM_LAT] = (float) $latest->lat;
            $patch[TraccarAppFields::KEY_ODOMETER_LAST_ACCUM_LNG] = (float) $latest->lng;
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
                if ($this->accumVersion($device) < self::ACCUM_VERSION) {
                    $this->rebuildGpsAccum($device);
                } else {
                    $this->catchUpFromPositions($device);
                }

                $base = $this->baselineKm($device) ?? 0.0;
                $accum = $this->gpsAccumKm($device);

                return round($base + $accum, 1);
            }
        );
    }

    /**
     * Display odometer (km) as of a historical timestamp.
     * With a client baseline: baseline + GPS distance from baselineSetAt through $at.
     * Without a baseline: null (caller should use device-reported telemetry).
     */
    public function displayKmAt(Device $device, Carbon $at): ?float
    {
        $pair = $this->displayKmBetween($device, $at, $at);

        return $pair['end'] ?? $pair['start'] ?? null;
    }

    /**
     * Start/end display odometer for a window using a single history scan from baseline.
     *
     * @return array{start: ?float, end: ?float}
     */
    public function displayKmBetween(Device $device, Carbon $startAt, Carbon $endAt): array
    {
        if (! $this->hasBaseline($device)) {
            return ['start' => null, 'end' => null];
        }

        $base = $this->baselineKm($device) ?? 0.0;
        $baseAt = $this->baselineSetAt($device);
        if ($baseAt === null) {
            return ['start' => null, 'end' => null];
        }

        if ($endAt->lt($startAt)) {
            [$startAt, $endAt] = [$endAt->copy(), $startAt->copy()];
        }

        if ($endAt->lte($baseAt)) {
            $v = round($base, 1);

            return ['start' => $v, 'end' => $v];
        }

        $fromScan = $baseAt->copy()->subSecond();
        $toScan = $endAt->copy()->addSecond();
        $positions = $this->positions->historyForDevice($device, $fromScan, $toScan, 'asc');

        $added = 0.0;
        $prevLat = null;
        $prevLng = null;
        $prevAt = null;
        $startKm = $startAt->lte($baseAt) ? round($base, 1) : null;
        $endKm = null;

        foreach ($positions as $point) {
            $recorded = $point->recorded_at ?? null;
            if ($recorded === null || $recorded->lt($baseAt) || $recorded->gt($endAt)) {
                continue;
            }

            $lat = $point->lat ?? null;
            $lng = $point->lng ?? null;
            if ($lat === null || $lng === null) {
                continue;
            }

            $lat = (float) $lat;
            $lng = (float) $lng;

            if ($prevLat !== null && $prevLng !== null && $prevAt !== null) {
                $segment = GeoMath::haversineKm($prevLat, $prevLng, $lat, $lng);
                if ($this->shouldCountSegment(
                    $segment,
                    $prevAt,
                    $recorded,
                    $this->pointSpeedKmh($point),
                    $this->pointIgnitionOn($point),
                )) {
                    $added += $segment;
                }
            }

            $prevLat = $lat;
            $prevLng = $lng;
            $prevAt = $recorded;

            $reading = round($base + $added, 1);
            if ($startKm === null && $recorded->gte($startAt)) {
                $startKm = $reading;
            }
            if ($recorded->lte($endAt)) {
                $endKm = $reading;
            }
        }

        if ($startKm === null) {
            $startKm = round($base, 1);
        }
        if ($endKm === null) {
            $endKm = $startKm;
        }

        return ['start' => $startKm, 'end' => $endKm];
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

        if ($this->accumVersion($device) < self::ACCUM_VERSION) {
            $this->rebuildGpsAccum($device);
            $this->forgetCache((int) $device->id);

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

        $lastAt = $this->lastAccumAt($device);
        // Skip duplicates / out-of-order packets (they reverse the cursor and inflate distance).
        if ($lastAt !== null && $recordedAt->lte($lastAt)) {
            return;
        }

        $lat = $location->lat;
        $lng = $location->lng;
        if ($lat === null || $lng === null) {
            return;
        }

        $this->addSegment(
            $device,
            (float) $lat,
            (float) $lng,
            $recordedAt,
            $this->pointSpeedKmh($location),
            $this->pointIgnitionOn($location),
        );
        $this->forgetCache((int) $device->id);
    }

    /**
     * Full rebuild of GPS accumulation from the baseline timestamp using current filters.
     * Corrects inflated totals caused by older bugs (backdated cursor, parking jitter, teleports).
     */
    public function rebuildGpsAccum(Device $device): void
    {
        $baseAt = $this->baselineSetAt($device);
        if ($baseAt === null) {
            return;
        }

        $positions = $this->positions->historyForDevice(
            $device,
            $baseAt->copy()->subSecond(),
            now()->copy()->addSecond(),
            'asc'
        );

        $added = 0.0;
        $prevLat = null;
        $prevLng = null;
        $prevAt = null;
        $lastAt = $baseAt;
        $lastLat = $this->lastAccumLat($device);
        $lastLng = $this->lastAccumLng($device);

        foreach ($positions as $point) {
            $at = $point->recorded_at ?? null;
            if ($at === null || $at->lt($baseAt)) {
                continue;
            }

            $lat = $point->lat;
            $lng = $point->lng;
            if ($lat === null || $lng === null) {
                continue;
            }

            $lat = (float) $lat;
            $lng = (float) $lng;

            if ($prevLat !== null && $prevLng !== null && $prevAt !== null) {
                $segment = GeoMath::haversineKm($prevLat, $prevLng, $lat, $lng);
                if ($this->shouldCountSegment(
                    $segment,
                    $prevAt,
                    $at,
                    $this->pointSpeedKmh($point),
                    $this->pointIgnitionOn($point),
                )) {
                    $added += $segment;
                }
            }

            $prevLat = $lat;
            $prevLng = $lng;
            $prevAt = $at;
            $lastAt = $at;
            $lastLat = $lat;
            $lastLng = $lng;
        }

        $device->patchTraccarAppAttributes([
            TraccarAppFields::KEY_ODOMETER_GPS_ACCUM_KM => round($added, 3),
            TraccarAppFields::KEY_ODOMETER_LAST_ACCUM_AT => $lastAt->toDateTimeString(),
            TraccarAppFields::KEY_ODOMETER_LAST_ACCUM_LAT => $lastLat,
            TraccarAppFields::KEY_ODOMETER_LAST_ACCUM_LNG => $lastLng,
            TraccarAppFields::KEY_ODOMETER_ACCUM_VERSION => self::ACCUM_VERSION,
        ]);
        $device->save();
        $this->forgetCache((int) $device->id);
    }

    /**
     * @param  iterable<int, DeviceLocation|object>  $positions
     */
    private function distanceKmAlongPositions(iterable $positions, Carbon $from, Carbon $to): float
    {
        $added = 0.0;
        $prevLat = null;
        $prevLng = null;
        $prevAt = null;

        foreach ($positions as $point) {
            $recorded = $point->recorded_at ?? null;
            if ($recorded === null || $recorded->lt($from) || $recorded->gt($to)) {
                continue;
            }

            $lat = $point->lat ?? null;
            $lng = $point->lng ?? null;
            if ($lat === null || $lng === null) {
                continue;
            }

            $lat = (float) $lat;
            $lng = (float) $lng;

            if ($prevLat !== null && $prevLng !== null && $prevAt !== null) {
                $segment = GeoMath::haversineKm($prevLat, $prevLng, $lat, $lng);
                if ($this->shouldCountSegment(
                    $segment,
                    $prevAt,
                    $recorded,
                    $this->pointSpeedKmh($point),
                    $this->pointIgnitionOn($point),
                )) {
                    $added += $segment;
                }
            }

            $prevLat = $lat;
            $prevLng = $lng;
            $prevAt = $recorded;
        }

        return $added;
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
        $prevAt = $lastAt;
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

            $lat = (float) $lat;
            $lng = (float) $lng;

            if ($prevLat !== null && $prevLng !== null && $prevAt !== null) {
                $segment = GeoMath::haversineKm($prevLat, $prevLng, $lat, $lng);
                if ($this->shouldCountSegment(
                    $segment,
                    $prevAt,
                    $at,
                    $this->pointSpeedKmh($point),
                    $this->pointIgnitionOn($point),
                )) {
                    $added += $segment;
                }
            }

            $prevLat = $lat;
            $prevLng = $lng;
            $prevAt = $at;
            $lastAt = $at;
            $changed = true;
        }

        if (! $changed) {
            return;
        }

        $patch = [
            TraccarAppFields::KEY_ODOMETER_GPS_ACCUM_KM => round($this->gpsAccumKm($device) + $added, 3),
            TraccarAppFields::KEY_ODOMETER_ACCUM_VERSION => self::ACCUM_VERSION,
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

    private function addSegment(
        Device $device,
        float $lat,
        float $lng,
        Carbon $at,
        ?float $speedKmh,
        ?bool $ignitionOn,
    ): void {
        $prevLat = $this->lastAccumLat($device);
        $prevLng = $this->lastAccumLng($device);
        $prevAt = $this->lastAccumAt($device);
        $added = 0.0;

        if ($prevLat !== null && $prevLng !== null && $prevAt !== null) {
            $segment = GeoMath::haversineKm($prevLat, $prevLng, $lat, $lng);
            if ($this->shouldCountSegment($segment, $prevAt, $at, $speedKmh, $ignitionOn)) {
                $added = $segment;
            }
        }

        $device->patchTraccarAppAttributes([
            TraccarAppFields::KEY_ODOMETER_GPS_ACCUM_KM => round($this->gpsAccumKm($device) + $added, 3),
            TraccarAppFields::KEY_ODOMETER_LAST_ACCUM_AT => $at->toDateTimeString(),
            TraccarAppFields::KEY_ODOMETER_LAST_ACCUM_LAT => $lat,
            TraccarAppFields::KEY_ODOMETER_LAST_ACCUM_LNG => $lng,
            TraccarAppFields::KEY_ODOMETER_ACCUM_VERSION => self::ACCUM_VERSION,
        ]);
        $device->save();
    }

    /**
     * Decide whether a GPS hop should increase the odometer.
     * Always advance the cursor even when this returns false (caller responsibility).
     */
    private function shouldCountSegment(
        float $segmentKm,
        Carbon $from,
        Carbon $to,
        ?float $speedKmh,
        ?bool $ignitionOn,
    ): bool {
        if ($segmentKm < self::MIN_SEGMENT_KM) {
            return false;
        }

        if ($segmentKm > self::MAX_SEGMENT_KM) {
            return false;
        }

        $dt = max(0, $from->diffInRealSeconds($to));
        if ($dt <= 0) {
            return false;
        }

        // Do not invent road distance across long offline gaps.
        if ($dt > self::MAX_GAP_SECONDS) {
            return false;
        }

        $impliedSpeed = ($segmentKm / $dt) * 3600.0;
        if ($impliedSpeed > self::MAX_IMPLIED_SPEED_KMH) {
            return false;
        }

        // Prefer device-reported speed; fall back to implied hop speed.
        $movingSpeed = $speedKmh !== null && $speedKmh >= 0
            ? $speedKmh
            : $impliedSpeed;

        if ($movingSpeed < self::MIN_MOVING_SPEED_KMH) {
            return false;
        }

        // When ignition is explicitly OFF, ignore residual GPS drift.
        if ($ignitionOn === false && $movingSpeed < 5.0) {
            return false;
        }

        return true;
    }

    private function pointSpeedKmh(object $point): ?float
    {
        $raw = $point->speed ?? null;
        if ($raw === null || $raw === '') {
            return null;
        }

        $value = (float) $raw;
        // Traccar often stores knots; values under ~100 treated as km/h already are fine for a gate.
        // If speed looks like m/s (typical 0–50), convert to km/h.
        if ($value > 0 && $value < 80) {
            // Ambiguous unit — treat as km/h (device payloads in this app use km/h).
            return $value;
        }

        return $value;
    }

    private function pointIgnitionOn(object $point): ?bool
    {
        if (isset($point->ignition) && $point->ignition !== null && $point->ignition !== '') {
            return filter_var($point->ignition, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE) ?? (bool) $point->ignition;
        }

        if (isset($point->acc) && $point->acc !== null && $point->acc !== '') {
            return filter_var($point->acc, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE) ?? (bool) $point->acc;
        }

        return null;
    }

    private function accumVersion(Device $device): int
    {
        $raw = TraccarAppFields::get(
            $device->getTraccarAttributesJson(),
            TraccarAppFields::KEY_ODOMETER_ACCUM_VERSION,
            0
        );

        return max(0, (int) $raw);
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
