<?php

namespace App\Services\Tracking;

use App\Models\DeviceLocation;
use App\Models\VehicleEvent;
use Carbon\Carbon;
use Illuminate\Support\Collection;

/**
 * Chronological trip/parking/event timeline for History UI (web + mobile).
 *
 * Built from HistoryAnalyticsService timeline + stops + optional alert events.
 */
class TripTimelineBuilder
{
    private const MOVING_KEYS = ['running', 'moving'];

    private const PARK_KEYS = ['parked', 'stopped', 'idle', 'offline'];

    private const EVENT_TYPES = [
        VehicleEvent::TYPE_OVERSPEED => 'overspeed',
        VehicleEvent::TYPE_GEOFENCE_ENTER => 'geofence_enter',
        VehicleEvent::TYPE_GEOFENCE_EXIT => 'geofence_exit',
        VehicleEvent::TYPE_RUNNING => 'ignition_on',
        VehicleEvent::TYPE_STOPPED => 'ignition_off',
        'parked' => 'ignition_off',
        VehicleEvent::TYPE_LOW_BATTERY => 'low_battery',
        VehicleEvent::TYPE_POWER_CUT => 'power_cut',
        VehicleEvent::TYPE_PANIC => 'panic',
    ];

    public function __construct(
        private HistoryAnalyticsService $analytics,
    ) {}

    /**
     * @param  Collection<int, DeviceLocation|object>|list<object>  $points
     * @param  list<array<string, mixed>>  $timeline
     * @param  list<array<string, mixed>>  $stops
     * @param  list<array<string, mixed>>  $events  Compiled history / alert rows
     * @return list<array<string, mixed>>
     */
    public function build(
        Collection|array $points,
        array $timeline,
        array $stops = [],
        array $events = [],
    ): array {
        $sorted = $points instanceof Collection
            ? $points->values()->all()
            : array_values($points);

        $core = $this->buildCoreSegments($sorted, $timeline, $stops);
        $overlays = $this->buildEventOverlays($events, $timeline, $sorted);

        $merged = array_merge($core, $overlays);
        usort($merged, function (array $a, array $b): int {
            $cmp = strcmp(
                (string) ($a['sort_time'] ?? $a['start'] ?? ''),
                (string) ($b['sort_time'] ?? $b['start'] ?? '')
            );
            if ($cmp !== 0) {
                return $cmp;
            }

            return ($a['sort_order'] ?? 50) <=> ($b['sort_order'] ?? 50);
        });

        foreach ($merged as &$row) {
            unset($row['sort_time'], $row['sort_order']);
        }
        unset($row);

        return array_values($merged);
    }

    /**
     * @param  list<object>  $sorted
     * @param  list<array<string, mixed>>  $timeline
     * @param  list<array<string, mixed>>  $stops
     * @return list<array<string, mixed>>
     */
    private function buildCoreSegments(array $sorted, array $timeline, array $stops): array
    {
        $segments = [];
        $driveBucket = [];
        $prevDriveMeta = null;
        $emittedStart = false;
        $lastEnd = null;

        $flushDrive = function () use (&$segments, &$driveBucket, &$prevDriveMeta, &$emittedStart, $sorted): void {
            if ($driveBucket === []) {
                return;
            }

            $drive = $this->driveFromBucket($sorted, $driveBucket);
            if ($drive === null) {
                $driveBucket = [];

                return;
            }

            if (! $emittedStart) {
                $segments[] = $this->markerSegment(
                    'trip_start',
                    'Start Trip',
                    $drive['start'],
                    $drive['start_display'],
                    $drive['start_lat'],
                    $drive['start_lng'],
                    sortOrder: 10
                );
                $emittedStart = true;
            }

            $segments[] = $drive;
            $prevDriveMeta = $drive;
            $driveBucket = [];
        };

        foreach ($timeline as $segment) {
            if (($segment['is_transition'] ?? false) === true) {
                continue;
            }

            $key = (string) ($segment['status_key'] ?? '');
            $dur = (int) ($segment['duration_seconds'] ?? 0);

            if (in_array($key, self::MOVING_KEYS, true)) {
                $driveBucket[] = $segment;
                $lastEnd = (string) ($segment['end'] ?? $lastEnd);

                continue;
            }

            if (in_array($key, self::PARK_KEYS, true)) {
                if ($dur < HistoryAnalyticsService::STOP_MIN_SECONDS && $driveBucket === []) {
                    continue;
                }

                $flushDrive();

                if ($dur >= HistoryAnalyticsService::STOP_MIN_SECONDS) {
                    $parking = $this->parkingFromSegment($segment, $stops, $prevDriveMeta);
                    if ($parking !== null) {
                        $segments[] = $parking;
                        $lastEnd = (string) ($parking['end'] ?? $lastEnd);
                    }
                }
            }
        }

        $flushDrive();

        if ($emittedStart) {
            $endSeg = $prevDriveMeta;
            $lat = $endSeg['end_lat'] ?? null;
            $lng = $endSeg['end_lng'] ?? null;
            $time = $endSeg['end'] ?? $lastEnd;
            $display = $endSeg['end_display'] ?? $time;
            $segments[] = $this->markerSegment(
                'trip_end',
                'End Trip',
                $time,
                $display,
                $lat,
                $lng,
                sortOrder: 90
            );
        }

        return $segments;
    }

    /**
     * @param  list<object>  $sorted
     * @param  list<array<string, mixed>>  $bucket
     * @return array<string, mixed>|null
     */
    private function driveFromBucket(array $sorted, array $bucket): ?array
    {
        if ($bucket === []) {
            return null;
        }

        $first = $bucket[0];
        $last = $bucket[count($bucket) - 1];
        $startAt = $this->parseTime((string) ($first['start'] ?? ''));
        $endAt = $this->parseTime((string) ($last['end'] ?? ''));
        if (! $startAt || ! $endAt) {
            return null;
        }

        $movingSec = array_sum(array_map(
            fn (array $s) => (int) ($s['duration_seconds'] ?? 0),
            $bucket
        ));
        $maxSpeed = max(array_merge(
            [0.0],
            array_map(
                fn (array $s) => (float) ($s['max_speed_kmh'] ?? $s['speed_kmh'] ?? 0),
                $bucket
            )
        ));

        $distance = $this->distanceBetweenTimes($sorted, $startAt, $endAt);
        if ($distance <= 0) {
            $distance = $this->segmentChainDistanceKm($bucket);
        }

        $duration = max(0, (int) $startAt->diffInSeconds($endAt));
        $avg = $movingSec > 0
            ? round($distance / ($movingSec / 3600), 1)
            : ($duration > 0 ? round($distance / ($duration / 3600), 1) : 0.0);

        $startLat = isset($first['start_lat']) ? (float) $first['start_lat'] : null;
        $startLng = isset($first['start_lng']) ? (float) $first['start_lng'] : null;
        $endLat = isset($last['end_lat']) ? (float) $last['end_lat'] : null;
        $endLng = isset($last['end_lng']) ? (float) $last['end_lng'] : null;

        return [
            'kind' => 'drive',
            'kind_label' => 'Driving',
            'start' => (string) ($first['start'] ?? ''),
            'end' => (string) ($last['end'] ?? ''),
            'start_display' => (string) ($first['start_display'] ?? $first['start'] ?? ''),
            'end_display' => (string) ($last['end_display'] ?? $last['end'] ?? ''),
            'duration_seconds' => $duration > 0 ? $duration : $movingSec,
            'moving_time_seconds' => $movingSec,
            'distance_km' => $distance,
            'max_speed_kmh' => round($maxSpeed, 1),
            'average_speed_kmh' => $avg,
            'start_lat' => $startLat,
            'start_lng' => $startLng,
            'end_lat' => $endLat,
            'end_lng' => $endLng,
            'lat' => $startLat,
            'lng' => $startLng,
            'maps_url' => $this->mapsUrl($startLat, $startLng),
            'address' => null,
            'sort_time' => (string) ($first['start'] ?? ''),
            'sort_order' => 40,
        ];
    }

    /**
     * @param  array<string, mixed>  $segment
     * @param  list<array<string, mixed>>  $stops
     * @param  array<string, mixed>|null  $prevDrive
     * @return array<string, mixed>|null
     */
    private function parkingFromSegment(array $segment, array $stops, ?array $prevDrive): ?array
    {
        $matched = $this->matchStop($segment, $stops) ?? $segment;
        $lat = isset($matched['lat'])
            ? (float) $matched['lat']
            : (isset($matched['start_lat']) ? (float) $matched['start_lat'] : null);
        $lng = isset($matched['lng'])
            ? (float) $matched['lng']
            : (isset($matched['start_lng']) ? (float) $matched['start_lng'] : null);

        $start = (string) ($matched['start'] ?? $matched['start_display'] ?? '');
        $end = (string) ($matched['end'] ?? $matched['end_display'] ?? '');
        $startDisplay = (string) ($matched['start_display'] ?? $matched['start'] ?? '');
        $endDisplay = (string) ($matched['end_display'] ?? $matched['end'] ?? '');
        $duration = (int) ($matched['duration_seconds'] ?? $segment['duration_seconds'] ?? 0);

        return [
            'kind' => 'parking',
            'kind_label' => (string) ($matched['status_label'] ?? 'Parking'),
            'start' => $start,
            'end' => $end,
            'start_display' => $startDisplay,
            'end_display' => $endDisplay,
            'arrival_time' => $startDisplay,
            'departure_time' => $endDisplay,
            'duration_seconds' => $duration,
            'lat' => $lat,
            'lng' => $lng,
            'start_lat' => $lat,
            'start_lng' => $lng,
            'end_lat' => $lat,
            'end_lng' => $lng,
            'maps_url' => $matched['maps_url'] ?? $this->mapsUrl($lat, $lng),
            'address' => $matched['address'] ?? null,
            'distance_before_km' => $prevDrive['distance_km'] ?? null,
            'max_speed_before_kmh' => $prevDrive['max_speed_kmh'] ?? null,
            'motion_key' => $matched['motion_key'] ?? $segment['motion_key'] ?? $segment['status_key'] ?? null,
            'sort_time' => $start !== '' ? $start : $startDisplay,
            'sort_order' => 50,
        ];
    }

    /**
     * @param  list<array<string, mixed>>  $events
     * @param  list<array<string, mixed>>  $timeline
     * @param  list<object>  $sorted
     * @return list<array<string, mixed>>
     */
    private function buildEventOverlays(array $events, array $timeline, array $sorted): array
    {
        $out = [];

        foreach ($timeline as $segment) {
            if (($segment['is_transition'] ?? false) !== true) {
                continue;
            }
            $key = (string) ($segment['status_key'] ?? '');
            $kind = match ($key) {
                'ignition_on', 'running' => 'ignition_on',
                'ignition_off', 'parked', 'stopped' => 'ignition_off',
                default => null,
            };
            if ($kind === null) {
                continue;
            }
            $lat = isset($segment['start_lat']) ? (float) $segment['start_lat'] : null;
            $lng = isset($segment['start_lng']) ? (float) $segment['start_lng'] : null;
            $time = (string) ($segment['start'] ?? '');
            $out[] = [
                'kind' => $kind,
                'kind_label' => $kind === 'ignition_on' ? 'Ignition ON' : 'Ignition OFF',
                'start' => $time,
                'end' => $time,
                'start_display' => (string) ($segment['start_display'] ?? $time),
                'end_display' => (string) ($segment['start_display'] ?? $time),
                'duration_seconds' => 0,
                'lat' => $lat,
                'lng' => $lng,
                'maps_url' => $this->mapsUrl($lat, $lng),
                'title' => $kind === 'ignition_on' ? 'Ignition ON' : 'Ignition OFF',
                'message' => (string) ($segment['status_label'] ?? ''),
                'sort_time' => $time,
                'sort_order' => 30,
            ];
        }

        $overspeedSeen = [];
        foreach ($events as $event) {
            $eventType = (string) ($event['event_type'] ?? '');
            $kind = self::EVENT_TYPES[$eventType] ?? null;
            if ($kind === null) {
                // Raw titles / mapped push-like names
                $kind = match (strtolower($eventType)) {
                    'overspeed', 'deviceoverspeed' => 'overspeed',
                    'geofence_enter', 'geofenceenter' => 'geofence_enter',
                    'geofence_exit', 'geofenceexit' => 'geofence_exit',
                    'ignition_on', 'running' => 'ignition_on',
                    'ignition_off', 'stopped', 'parked' => 'ignition_off',
                    'fuel', 'fuel_drop' => 'fuel',
                    default => null,
                };
            }
            if ($kind === null) {
                continue;
            }

            $time = (string) ($event['time'] ?? $event['recorded_at'] ?? $event['start'] ?? '');
            $lat = isset($event['lat']) ? (float) $event['lat'] : null;
            $lng = isset($event['lng']) ? (float) $event['lng'] : null;

            if ($kind === 'overspeed') {
                $bucket = substr($time, 0, 16).'|'.round((float) ($lat ?? 0), 3);
                if (isset($overspeedSeen[$bucket])) {
                    continue;
                }
                $overspeedSeen[$bucket] = true;
                if (count($overspeedSeen) > 40) {
                    continue;
                }
            }

            $out[] = [
                'kind' => $kind,
                'kind_label' => $this->labelForKind($kind),
                'start' => $time,
                'end' => $time,
                'start_display' => (string) ($event['clock'] ?? $event['time_display'] ?? $time),
                'end_display' => (string) ($event['clock'] ?? $event['time_display'] ?? $time),
                'duration_seconds' => (int) ($event['duration_seconds'] ?? 0),
                'lat' => $lat,
                'lng' => $lng,
                'speed_kmh' => isset($event['speed']) ? (float) $event['speed'] : null,
                'maps_url' => $this->mapsUrl($lat, $lng),
                'title' => (string) ($event['title'] ?? $this->labelForKind($kind)),
                'message' => (string) ($event['message'] ?? ''),
                'geofence' => $event['geofence'] ?? null,
                'address' => null,
                'sort_time' => $time,
                'sort_order' => 35,
            ];
        }

        foreach ($this->fuelDropEvents($sorted) as $fuelEvent) {
            $out[] = $fuelEvent;
        }

        return $out;
    }

    /**
     * @param  list<object>  $sorted
     * @return list<array<string, mixed>>
     */
    private function fuelDropEvents(array $sorted): array
    {
        $out = [];
        $prev = null;
        $prevTime = null;

        foreach ($sorted as $point) {
            $fuel = $point->fuel ?? null;
            if ($fuel === null || ! is_numeric($fuel)) {
                continue;
            }
            $fuel = (float) $fuel;
            $time = $point->recorded_at ?? null;
            $timeStr = $time ? (string) app_datetime_api($time) : '';

            if ($prev !== null && ($prev - $fuel) >= 2.0) {
                $lat = (float) ($point->lat ?? 0);
                $lng = (float) ($point->lng ?? 0);
                $out[] = [
                    'kind' => 'fuel',
                    'kind_label' => 'Fuel Drop',
                    'start' => $timeStr,
                    'end' => $timeStr,
                    'start_display' => $time ? (string) app_datetime_format($time) : $timeStr,
                    'end_display' => $time ? (string) app_datetime_format($time) : $timeStr,
                    'duration_seconds' => 0,
                    'lat' => $lat,
                    'lng' => $lng,
                    'maps_url' => $this->mapsUrl($lat, $lng),
                    'title' => 'Fuel Drop',
                    'message' => sprintf('Fuel dropped from %.1f to %.1f L.', $prev, $fuel),
                    'fuel_before' => $prev,
                    'fuel_after' => $fuel,
                    'fuel_delta' => round($prev - $fuel, 1),
                    'sort_time' => $timeStr,
                    'sort_order' => 36,
                ];
                if (count($out) >= 25) {
                    break;
                }
            }

            $prev = $fuel;
            $prevTime = $timeStr;
        }

        return $out;
    }

    /**
     * @param  array<string, mixed>  $segment
     * @param  list<array<string, mixed>>  $stops
     * @return array<string, mixed>|null
     */
    private function matchStop(array $segment, array $stops): ?array
    {
        $segStart = $this->parseTime((string) ($segment['start'] ?? ''));
        $segLat = isset($segment['start_lat']) ? (float) $segment['start_lat'] : null;
        $segLng = isset($segment['start_lng']) ? (float) $segment['start_lng'] : null;

        foreach ($stops as $stop) {
            $stopStart = $this->parseTime((string) ($stop['start'] ?? $stop['start_display'] ?? ''));
            if ($segStart && $stopStart && abs($segStart->diffInSeconds($stopStart)) <= 120) {
                return $stop;
            }
            $stopLat = isset($stop['lat']) ? (float) $stop['lat'] : null;
            $stopLng = isset($stop['lng']) ? (float) $stop['lng'] : null;
            if ($segLat !== null && $segLng !== null && $stopLat !== null && $stopLng !== null) {
                if (abs($segLat - $stopLat) < 0.0008 && abs($segLng - $stopLng) < 0.0008) {
                    return $stop;
                }
            }
        }

        return null;
    }

    /**
     * @param  list<object>  $sorted
     */
    private function distanceBetweenTimes(array $sorted, Carbon $from, Carbon $to): float
    {
        if ($sorted === []) {
            return 0.0;
        }

        $slice = [];
        foreach ($sorted as $point) {
            $t = $point->recorded_at ?? null;
            if (! $t) {
                continue;
            }
            try {
                $at = $t instanceof Carbon ? $t : Carbon::parse((string) $t);
            } catch (\Throwable) {
                continue;
            }
            if ($at->greaterThanOrEqualTo($from) && $at->lessThanOrEqualTo($to)) {
                $slice[] = $point;
            }
        }

        if (count($slice) < 2) {
            return 0.0;
        }

        return $this->analytics->distanceKmForPoints(collect($slice));
    }

    /**
     * @param  list<array<string, mixed>>  $segments
     */
    private function segmentChainDistanceKm(array $segments): float
    {
        $dist = 0.0;
        foreach ($segments as $segment) {
            $lat1 = isset($segment['start_lat']) ? (float) $segment['start_lat'] : null;
            $lng1 = isset($segment['start_lng']) ? (float) $segment['start_lng'] : null;
            $lat2 = isset($segment['end_lat']) ? (float) $segment['end_lat'] : null;
            $lng2 = isset($segment['end_lng']) ? (float) $segment['end_lng'] : null;
            if ($lat1 === null || $lng1 === null || $lat2 === null || $lng2 === null) {
                continue;
            }
            $dist += $this->haversineKm($lat1, $lng1, $lat2, $lng2);
        }

        return round($dist, 2);
    }

    private function haversineKm(float $lat1, float $lng1, float $lat2, float $lng2): float
    {
        $earth = 6371.0;
        $dLat = deg2rad($lat2 - $lat1);
        $dLng = deg2rad($lng2 - $lng1);
        $a = sin($dLat / 2) ** 2
            + cos(deg2rad($lat1)) * cos(deg2rad($lat2)) * sin($dLng / 2) ** 2;

        return $earth * 2 * atan2(sqrt($a), sqrt(1 - $a));
    }

    /**
     * @return array<string, mixed>
     */
    private function markerSegment(
        string $kind,
        string $label,
        ?string $time,
        ?string $display,
        mixed $lat,
        mixed $lng,
        int $sortOrder,
    ): array {
        $latF = is_numeric($lat) ? (float) $lat : null;
        $lngF = is_numeric($lng) ? (float) $lng : null;

        return [
            'kind' => $kind,
            'kind_label' => $label,
            'start' => (string) ($time ?? ''),
            'end' => (string) ($time ?? ''),
            'start_display' => (string) ($display ?? $time ?? ''),
            'end_display' => (string) ($display ?? $time ?? ''),
            'duration_seconds' => 0,
            'lat' => $latF,
            'lng' => $lngF,
            'start_lat' => $latF,
            'start_lng' => $lngF,
            'maps_url' => $this->mapsUrl($latF, $lngF),
            'address' => null,
            'sort_time' => (string) ($time ?? ''),
            'sort_order' => $sortOrder,
        ];
    }

    private function mapsUrl(?float $lat, ?float $lng): ?string
    {
        if ($lat === null || $lng === null || ! is_finite($lat) || ! is_finite($lng)) {
            return null;
        }
        if (abs($lat) > 90 || abs($lng) > 180 || ($lat == 0.0 && $lng == 0.0)) {
            return null;
        }

        return 'https://www.google.com/maps/search/?api=1&query='
            .rawurlencode(number_format($lat, 6, '.', '').','.number_format($lng, 6, '.', ''));
    }

    private function parseTime(string $value): ?Carbon
    {
        $value = trim($value);
        if ($value === '') {
            return null;
        }
        try {
            return Carbon::parse($value);
        } catch (\Throwable) {
            return null;
        }
    }

    private function labelForKind(string $kind): string
    {
        return match ($kind) {
            'overspeed' => 'Overspeed',
            'geofence_enter' => 'Geofence Entry',
            'geofence_exit' => 'Geofence Exit',
            'ignition_on' => 'Ignition ON',
            'ignition_off' => 'Ignition OFF',
            'fuel' => 'Fuel Drop',
            'low_battery' => 'Low Battery',
            'power_cut' => 'Power Cut',
            'panic' => 'SOS / Panic',
            default => ucwords(str_replace('_', ' ', $kind)),
        };
    }
}
