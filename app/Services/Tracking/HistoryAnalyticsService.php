<?php

namespace App\Services\Tracking;

use App\Models\DeviceLocation;
use App\Services\Mobile\VehicleStatusSpec;
use Carbon\Carbon;
use Illuminate\Support\Collection;

/**
 * Single source of truth for historical route analytics, timeline segments, and daily statistics.
 *
 * Uses ignition + speed (VehicleStatusSpec) — not speed alone.
 */
class HistoryAnalyticsService
{
    public const STOP_MIN_SECONDS = 120;

    /** Gap between fixes treated as offline in timeline. */
    public const OFFLINE_GAP_SECONDS = 1800;

    public const OVERSPEED_KMH = 120;

    /**
     * @param  Collection<int, DeviceLocation|object>  $points
     * @param  array{point_statuses?: bool, include_track_points?: bool, skip_timeline?: bool, minimal_stats?: bool}  $options
     * @return array<string, mixed>
     */
    public function analyze(Collection $points, array $options = []): array
    {
        $data = $this->sortedPoints($points);
        $includeTrackPoints = $options['include_track_points'] ?? true;
        $minimalStats = $options['minimal_stats'] ?? false;

        if ($data === []) {
            return $this->emptyStats();
        }

        $dist = 0.0;
        $maxSpeed = 0.0;
        $overspeedEvents = 0;
        $movingSec = 0;
        $idleSec = 0;
        $parkingSec = 0;
        $offlineSec = 0;
        $stops = [];
        $stopRun = [];
        $movingPoints = [];
        $idlePoints = [];

        $flushStop = function () use (&$stopRun, &$stops, $minimalStats): void {
            if ($minimalStats) {
                $stopRun = [];

                return;
            }

            if (count($stopRun) < 2) {
                $stopRun = [];

                return;
            }

            $t0 = $this->pointTime($stopRun[0]);
            $t1 = $this->pointTime($stopRun[count($stopRun) - 1]);
            $dur = $this->segmentDurationSeconds($t0, $t1);

            if ($dur >= self::STOP_MIN_SECONDS) {
                $mid = $stopRun[(int) floor(count($stopRun) / 2)];
                $motion = $this->motionKey($mid);
                $stops[] = [
                    'lat' => (float) $mid->lat,
                    'lng' => (float) $mid->lng,
                    'duration_seconds' => $dur,
                    'motion_key' => $motion,
                    'status_label' => $this->timelineLabel($motion),
                    'start' => app_datetime_api($t0),
                    'start_display' => app_datetime_format($t0),
                    'end' => app_datetime_api($t1),
                    'end_display' => app_datetime_format($t1),
                ];
            }

            $stopRun = [];
        };

        for ($i = 1, $n = count($data); $i < $n; $i++) {
            $a = $data[$i - 1];
            $b = $data[$i];

            $dist += $this->haversineKm(
                (float) $a->lat,
                (float) $a->lng,
                (float) $b->lat,
                (float) $b->lng
            );

            $t0 = $this->pointTime($a);
            $t1 = $this->pointTime($b);
            $dt = $this->segmentDurationSeconds($t0, $t1);

            if ($dt > self::OFFLINE_GAP_SECONDS) {
                $offlineSec += $dt;
                $flushStop();
            } else {
                $spd = (float) ($b->speed ?? 0);
                if ($spd > $maxSpeed) {
                    $maxSpeed = $spd;
                }
                if ($spd > self::OVERSPEED_KMH) {
                    $overspeedEvents++;
                }

                $motion = $this->motionKey($b);

                match ($motion) {
                    'running', 'moving' => $movingSec += $dt,
                    'stopped', 'idle' => $idleSec += $dt,
                    'parked' => $parkingSec += $dt,
                    default => $idleSec += $dt,
                };

                if (in_array($motion, ['stopped', 'idle', 'parked'], true)) {
                    $stopRun[] = $b;
                    if ($includeTrackPoints && in_array($motion, ['stopped', 'idle'], true)) {
                        $idlePoints[] = $this->pointPayload($b);
                    }
                } else {
                    if ($includeTrackPoints) {
                        $movingPoints[] = $this->pointPayload($b);
                    }
                    $flushStop();
                }
            }
        }

        $flushStop();

        $bounds = $this->routeTimeBounds($data);
        $totalSec = max(0, (int) ($bounds['total_sec'] ?? 0));

        if ($totalSec <= 0) {
            $totalSec = max(0, $this->sumSegmentDurationSeconds($data));
        }

        $stoppedSec = $idleSec + $parkingSec;
        $activeSec = $movingSec + $stoppedSec + $offlineSec;
        if ($totalSec > 0 && $activeSec > $totalSec) {
            $scale = $totalSec / max(1, $activeSec);
            $movingSec = (int) round($movingSec * $scale);
            $idleSec = (int) round($idleSec * $scale);
            $parkingSec = (int) round($parkingSec * $scale);
            $offlineSec = (int) round($offlineSec * $scale);
            $stoppedSec = $idleSec + $parkingSec;
        }

        $movingSec = max(0, $movingSec);
        $idleSec = max(0, $idleSec);
        $parkingSec = max(0, $parkingSec);
        $stoppedSec = max(0, $stoppedSec);
        $offlineSec = max(0, $offlineSec);

        $avgSpeed = $movingSec > 0
            ? round($dist / ($movingSec / 3600), 1)
            : ($totalSec > 0 ? round($dist / ($totalSec / 3600), 1) : 0);

        $timeline = ($options['skip_timeline'] ?? false)
            ? []
            : $this->buildTimelineFromSorted($data);

        $result = [
            'total_distance_km' => round($dist, 2),
            'moving_time_seconds' => $movingSec,
            'idle_time_seconds' => $idleSec,
            'parking_time_seconds' => $parkingSec,
            'stopped_time_seconds' => $stoppedSec,
            'offline_time_seconds' => $offlineSec,
            'max_speed_kmh' => round($maxSpeed, 1),
            'average_speed_kmh' => $avgSpeed,
            'overspeed_events' => $overspeedEvents,
            'total_duration_seconds' => $totalSec,
            'start_time' => $bounds['start'] ? app_datetime_api($bounds['start']) : null,
            'end_time' => $bounds['end'] ? app_datetime_api($bounds['end']) : null,
            'stops' => $stops,
            'stop_count' => count($stops),
            'moving_points' => $movingPoints,
            'idle_points' => $idlePoints,
            'timeline' => $timeline,
        ];

        if ($options['point_statuses'] ?? true) {
            $result['point_statuses'] = $this->pointStatuses($points);
        }

        return $result;
    }

    /**
     * Haversine distance (km) over ordered GPS points — lightweight helper for trip rows.
     *
     * @param  Collection<int, DeviceLocation|object>  $points
     */
    public function distanceKmForPoints(Collection $points): float
    {
        $data = $this->sortedPoints($points);
        $dist = 0.0;

        for ($i = 1, $n = count($data); $i < $n; $i++) {
            $a = $data[$i - 1];
            $b = $data[$i];
            $dist += $this->haversineKm(
                (float) $a->lat,
                (float) $a->lng,
                (float) $b->lat,
                (float) $b->lng
            );
        }

        return round($dist, 2);
    }

    /**
     * @param  Collection<int, DeviceLocation|object>  $points
     * @return list<array<string, mixed>>
     */
    public function buildTimeline(Collection $points): array
    {
        return $this->buildTimelineFromSorted($this->sortedPoints($points));
    }

    /**
     * @param  list<object>  $data  Pre-sorted GPS points from sortedPoints().
     * @return list<array<string, mixed>>
     */
    public function buildTimelineFromSorted(array $data): array
    {
        if ($data === []) {
            return [];
        }

        $raw = [];

        for ($i = 1, $n = count($data); $i < $n; $i++) {
            $a = $data[$i - 1];
            $b = $data[$i];
            $t0 = $this->pointTime($a);
            $t1 = $this->pointTime($b);
            $dt = $this->segmentDurationSeconds($t0, $t1);

            if ($dt <= 0 || ! $t0 || ! $t1) {
                continue;
            }

            if ($dt > self::OFFLINE_GAP_SECONDS) {
                $raw[] = [
                    'status_key' => 'offline',
                    'status_label' => VehicleStatusSpec::labelForKey('offline'),
                    'motion_key' => null,
                    'ignition' => null,
                    'start' => $t0,
                    'end' => $t1,
                    'duration_seconds' => $dt,
                    'start_lat' => (float) $a->lat,
                    'start_lng' => (float) $a->lng,
                    'end_lat' => (float) $b->lat,
                    'end_lng' => (float) $b->lng,
                    'speed_kmh' => 0.0,
                    'max_speed_kmh' => 0.0,
                    'heading' => null,
                ];

                continue;
            }

            $motion = $this->motionKey($b);
            $ignitionA = (bool) ($a->ignition ?? false);
            $ignitionB = (bool) ($b->ignition ?? false);

            if ($ignitionA !== $ignitionB) {
                $raw[] = $this->rawIgnitionTransition($b, $t1, $ignitionB);
            }

            $spd = (float) ($b->speed ?? 0);
            $raw[] = [
                'status_key' => $motion,
                'status_label' => $this->timelineLabel($motion),
                'motion_key' => $motion,
                'ignition' => $ignitionB,
                'start' => $t0,
                'end' => $t1,
                'duration_seconds' => $dt,
                'start_lat' => (float) $a->lat,
                'start_lng' => (float) $a->lng,
                'end_lat' => (float) $b->lat,
                'end_lng' => (float) $b->lng,
                'speed_kmh' => $spd,
                'max_speed_kmh' => $spd,
                'heading' => isset($b->heading) ? (float) $b->heading : null,
            ];
        }

        return $this->mergeTimelineSegments($raw);
    }

    /**
     * Canonical point status payloads for web history, mobile history, and playback.
     *
     * @param  Collection<int, DeviceLocation|object>  $points
     * @return list<array<string, mixed>>
     */
    public function pointStatuses(Collection $points): array
    {
        $data = $this->sortedPoints($points);

        return array_map(
            fn (int $index) => $this->pointStatusPayload($data, $index),
            array_keys($data),
        );
    }

    /**
     * Seconds the vehicle has remained in the same motion state at $index.
     *
     * @param  list<object>  $data
     */
    public function statusDurationAtIndex(array $data, int $index): int
    {
        if ($index < 0 || $index >= count($data)) {
            return 0;
        }

        $point = $data[$index];
        $target = $this->motionKey($point);
        $end = $this->pointTime($point);
        if (! $end) {
            return 0;
        }

        $since = $end->copy();

        for ($i = $index - 1; $i >= 0; $i--) {
            $prev = $data[$i];
            if ($this->motionKey($prev) !== $target) {
                break;
            }
            $prevAt = $this->pointTime($prev);
            if ($prevAt) {
                $since = $prevAt->copy();
            }
        }

        return max(0, (int) $since->diffInSeconds($end));
    }

    /**
     * @param  list<object>  $data
     * @return array<string, mixed>
     */
    private function pointStatusPayload(array $data, int $index): array
    {
        $point = $data[$index] ?? null;
        if (! $point) {
            return [];
        }

        $motion = $this->motionKey($point);
        $tripKey = VehicleStatusSpec::tripStatusKey(
            (float) ($point->speed ?? 0),
            (bool) ($point->ignition ?? false),
        );

        return [
            'status_key' => $tripKey,
            'status_label' => $this->timelineLabel($motion),
            'motion_status_key' => $motion,
            'motion_status' => VehicleStatusSpec::motionLabel($motion),
            'trip_status_key' => $tripKey,
            'trip_status_label' => $this->timelineLabel($motion),
            'status_duration_seconds' => $this->statusDurationAtIndex($data, $index),
        ];
    }

    /**
     * @param  Collection<int, DeviceLocation|object>  $points
     * @return list<object>
     */
    private function sortedPoints(Collection $points): array
    {
        return $points
            ->filter(fn ($p) => isset($p->lat, $p->lng))
            ->sortBy(fn ($p) => ($this->pointTime($p)?->getTimestamp() ?? 0).':'.($p->id ?? 0))
            ->values()
            ->all();
    }

    /**
     * @param  list<array<string, mixed>>  $raw
     * @return list<array<string, mixed>>
     */
    private function mergeTimelineSegments(array $raw): array
    {
        $merged = [];

        foreach ($raw as $segment) {
            $lastIdx = count($merged) - 1;
            $last = $lastIdx >= 0 ? $merged[$lastIdx] : null;
            $canMerge = $last
                && ! ($segment['is_transition'] ?? false)
                && ! ($last['is_transition'] ?? false)
                && ($last['status_key'] ?? '') === ($segment['status_key'] ?? '')
                && ($last['ignition'] ?? null) === ($segment['ignition'] ?? null);

            if ($canMerge) {
                $merged[$lastIdx]['end'] = $segment['end'];
                $merged[$lastIdx]['duration_seconds'] = (int) ($last['duration_seconds'] ?? 0)
                    + (int) ($segment['duration_seconds'] ?? 0);
                $merged[$lastIdx]['end_lat'] = $segment['end_lat'];
                $merged[$lastIdx]['end_lng'] = $segment['end_lng'];
                $merged[$lastIdx]['speed_kmh'] = $segment['speed_kmh'];
                $merged[$lastIdx]['max_speed_kmh'] = max(
                    (float) ($last['max_speed_kmh'] ?? 0),
                    (float) ($segment['max_speed_kmh'] ?? 0),
                );

                continue;
            }

            $merged[] = $segment;
        }

        return array_map(function (array $segment) {
            /** @var Carbon $start */
            $start = $segment['start'];
            /** @var Carbon $end */
            $end = $segment['end'];

            return [
                'status_key' => $segment['status_key'],
                'status_label' => $segment['status_label'],
                'motion_key' => $segment['motion_key'] ?? null,
                'ignition' => $segment['ignition'] ?? null,
                'start' => app_datetime_api($start),
                'start_display' => app_datetime_format($start),
                'end' => app_datetime_api($end),
                'end_display' => app_datetime_format($end),
                'duration_seconds' => max(0, (int) ($segment['duration_seconds'] ?? 0)),
                'start_lat' => $segment['start_lat'] ?? null,
                'start_lng' => $segment['start_lng'] ?? null,
                'end_lat' => $segment['end_lat'] ?? null,
                'end_lng' => $segment['end_lng'] ?? null,
                'speed_kmh' => round((float) ($segment['speed_kmh'] ?? 0), 1),
                'max_speed_kmh' => round((float) ($segment['max_speed_kmh'] ?? 0), 1),
                'heading' => $segment['heading'] ?? null,
                'is_transition' => (bool) ($segment['is_transition'] ?? false),
            ];
        }, $merged);
    }

    /**
     * @return array<string, mixed>
     */
    private function rawIgnitionTransition(object $point, Carbon $at, bool $ignition): array
    {
        $key = $ignition ? 'ignition_on' : 'ignition_off';

        return [
            'status_key' => $key,
            'status_label' => $ignition
                ? (string) __('app.map.event_ignition_on')
                : (string) __('app.map.event_ignition_off'),
            'motion_key' => $this->motionKey($point),
            'ignition' => $ignition,
            'start' => $at,
            'end' => $at,
            'duration_seconds' => 0,
            'start_lat' => (float) $point->lat,
            'start_lng' => (float) $point->lng,
            'end_lat' => (float) $point->lat,
            'end_lng' => (float) $point->lng,
            'speed_kmh' => (float) ($point->speed ?? 0),
            'max_speed_kmh' => (float) ($point->speed ?? 0),
            'heading' => isset($point->heading) ? (float) $point->heading : null,
            'is_transition' => true,
        ];
    }

    private function timelineLabel(string $motionKey): string
    {
        return match (VehicleStatusSpec::normalizeKey($motionKey)) {
            'running', 'moving' => (string) __('app.map.timeline_moving'),
            'stopped', 'idle' => (string) __('app.map.timeline_idle'),
            'parked' => (string) __('app.map.timeline_parked'),
            default => VehicleStatusSpec::motionLabel($motionKey),
        };
    }

    private function motionKey(object $point): string
    {
        return VehicleStatusSpec::motionKey(
            (float) ($point->speed ?? 0),
            (bool) ($point->ignition ?? false),
        );
    }

    private function segmentDurationSeconds(?Carbon $from, ?Carbon $to): int
    {
        if (! $from || ! $to || ! $to->greaterThan($from)) {
            return 0;
        }

        return max(0, (int) $from->diffInSeconds($to));
    }

    /**
     * @param  array<int, object>  $data
     * @return array{start: ?Carbon, end: ?Carbon, total_sec: int}
     */
    private function routeTimeBounds(array $data): array
    {
        $min = null;
        $max = null;
        $start = null;
        $end = null;

        foreach ($data as $point) {
            $at = $this->pointTime($point);
            if (! $at) {
                continue;
            }

            if ($min === null || $at->lessThan($min)) {
                $min = $at;
                $start = $at;
            }

            if ($max === null || $at->greaterThan($max)) {
                $max = $at;
                $end = $at;
            }
        }

        return [
            'start' => $start,
            'end' => $end,
            'total_sec' => $this->segmentDurationSeconds($min, $max),
        ];
    }

    /**
     * @param  array<int, object>  $data
     */
    private function sumSegmentDurationSeconds(array $data): int
    {
        $total = 0;

        for ($i = 1, $n = count($data); $i < $n; $i++) {
            $total += $this->segmentDurationSeconds(
                $this->pointTime($data[$i - 1]),
                $this->pointTime($data[$i]),
            );
        }

        return $total;
    }

    /**
     * @return array<string, mixed>
     */
    private function emptyStats(): array
    {
        return [
            'total_distance_km' => 0,
            'moving_time_seconds' => 0,
            'idle_time_seconds' => 0,
            'parking_time_seconds' => 0,
            'stopped_time_seconds' => 0,
            'offline_time_seconds' => 0,
            'max_speed_kmh' => 0,
            'average_speed_kmh' => 0,
            'overspeed_events' => 0,
            'total_duration_seconds' => 0,
            'stops' => [],
            'stop_count' => 0,
            'moving_points' => [],
            'idle_points' => [],
            'point_statuses' => [],
            'timeline' => [],
        ];
    }

    /**
     * @return array{lat: float, lng: float, speed: float, recorded_at: ?string, recorded_at_display: ?string}
     */
    private function pointPayload(object $point): array
    {
        $at = $this->pointTime($point);

        return [
            'lat' => (float) $point->lat,
            'lng' => (float) $point->lng,
            'speed' => (float) ($point->speed ?? 0),
            'recorded_at' => app_datetime_api($at),
            'recorded_at_display' => app_datetime_format($at),
        ];
    }

    private function pointTime(object $point): ?Carbon
    {
        if (! isset($point->recorded_at)) {
            return null;
        }

        return $point->recorded_at instanceof Carbon
            ? $point->recorded_at
            : Carbon::parse($point->recorded_at);
    }

    private function haversineKm(float $lat1, float $lon1, float $lat2, float $lon2): float
    {
        $r = 6371;
        $dLat = deg2rad($lat2 - $lat1);
        $dLon = deg2rad($lon2 - $lon1);
        $a = sin($dLat / 2) ** 2 + cos(deg2rad($lat1)) * cos(deg2rad($lat2)) * sin($dLon / 2) ** 2;

        return $r * 2 * asin(sqrt($a));
    }
}
