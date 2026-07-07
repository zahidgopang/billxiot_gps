<?php

namespace App\Services\Tracking;

use App\Models\Device;
use App\Models\DeviceLocation;
use App\Services\Mobile\MobileRouteAnalyticsService;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;

/**
 * Shared device-map history fetch + formatting for parallel map API endpoints.
 */
class DeviceMapHistoryService
{
    private const CACHE_SECONDS = 45;

    private const MAP_POINT_CAP = 2800;

    public function __construct(
        private DeviceHistoryFetcher $historyFetcher,
        private MobileRouteAnalyticsService $routeAnalytics,
    ) {}

    /**
     * @return array{locations: Collection<int, DeviceLocation>, used_fallback: bool, fallback_reason: ?string, range: array{from: Carbon, to: ?Carbon}}
     */
    public function fetchLocations(Device $device, Carbon $from, ?Carbon $to, bool $explicitRange): array
    {
        $cacheKey = $this->cacheKey($device, $from, $to, $explicitRange);

        if (Cache::has($cacheKey)) {
            /** @var array{locations: Collection<int, DeviceLocation>, used_fallback: bool, fallback_reason: ?string} $cached */
            $cached = Cache::get($cacheKey);

            return [
                'locations' => $cached['locations'],
                'used_fallback' => $cached['used_fallback'],
                'fallback_reason' => $cached['fallback_reason'],
                'range' => ['from' => $from, 'to' => $to],
            ];
        }

        $fetch = $this->historyFetcher->fetch($device, $from, $to, $explicitRange);

        if ($fetch['locations']->count() <= 4000) {
            Cache::put($cacheKey, [
                'locations' => $fetch['locations'],
                'used_fallback' => $fetch['used_fallback'],
                'fallback_reason' => $fetch['fallback_reason'],
            ], self::CACHE_SECONDS);
        }

        return [
            'locations' => $fetch['locations'],
            'used_fallback' => $fetch['used_fallback'],
            'fallback_reason' => $fetch['fallback_reason'],
            'range' => ['from' => $from, 'to' => $to],
        ];
    }

    /**
     * @param  Collection<int, DeviceLocation>  $locations
     * @return Collection<int, DeviceLocation>
     */
    public function downsampleForMap(Collection $locations, int $max = self::MAP_POINT_CAP): Collection
    {
        $count = $locations->count();
        if ($count <= $max) {
            return $locations->values();
        }

        $step = (int) ceil($count / $max);
        $sampled = collect();

        foreach ($locations->values() as $index => $location) {
            if ($index === 0 || $index === $count - 1 || $index % $step === 0) {
                $sampled->push($location);
            }
        }

        return $sampled->values();
    }

    /**
     * Lightweight map points — no per-point status merge (faster JSON + client parse).
     *
     * @param  Collection<int, DeviceLocation>  $locations
     * @return array<int, array<string, mixed>>
     */
    public function formatMapPoints(Collection $locations): array
    {
        return $locations
            ->values()
            ->map(fn (DeviceLocation $loc) => $this->formatMapPoint($loc))
            ->all();
    }

    /**
     * @param  Collection<int, DeviceLocation>  $locations
     * @param  list<array<string, mixed>>  $statuses
     * @return array<int, array<string, mixed>>
     */
    public function formatMapPointsWithStatuses(Collection $locations, array $statuses): array
    {
        return $locations
            ->values()
            ->map(fn (DeviceLocation $loc, int $index) => array_merge(
                $this->formatMapPoint($loc),
                $statuses[$index] ?? [],
            ))
            ->all();
    }

    /**
     * @param  Collection<int, DeviceLocation>  $locations
     * @return array{stats: array<string, mixed>, timeline: list<array<string, mixed>>}
     */
    public function analyzeForMap(Collection $locations): array
    {
        $stats = $this->routeAnalytics->analyze($locations, [
            'point_statuses' => false,
            'include_track_points' => false,
        ]);

        return [
            'stats' => $this->publicStatsPayload($stats),
            'timeline' => $stats['timeline'] ?? [],
        ];
    }

    /**
     * Full legacy bundle (single request).
     *
     * @param  Collection<int, DeviceLocation>  $locations
     * @return array{points: array<int, array<string, mixed>>, stats: array<string, mixed>, timeline: list<array<string, mixed>>}
     */
    public function buildFullPayload(Collection $locations): array
    {
        $stats = $this->routeAnalytics->analyze($locations, ['point_statuses' => true]);
        $statuses = $stats['point_statuses'] ?? [];
        unset($stats['point_statuses'], $stats['moving_points'], $stats['idle_points']);

        return [
            'points' => $this->formatMapPointsWithStatuses($locations, $statuses),
            'stats' => $this->publicStatsPayload($stats),
            'timeline' => $stats['timeline'] ?? [],
        ];
    }

    /**
     * @param  array<string, mixed>  $stats
     * @return array<string, mixed>
     */
    public function publicStatsPayload(array $stats): array
    {
        $stops = array_slice($stats['stops'] ?? [], 0, 250);

        return [
            'total_distance_km' => $stats['total_distance_km'] ?? 0,
            'moving_time_seconds' => max(0, (int) ($stats['moving_time_seconds'] ?? 0)),
            'idle_time_seconds' => max(0, (int) ($stats['idle_time_seconds'] ?? 0)),
            'parking_time_seconds' => max(0, (int) ($stats['parking_time_seconds'] ?? 0)),
            'stopped_time_seconds' => max(0, (int) ($stats['stopped_time_seconds'] ?? 0)),
            'offline_time_seconds' => max(0, (int) ($stats['offline_time_seconds'] ?? 0)),
            'max_speed_kmh' => $stats['max_speed_kmh'] ?? 0,
            'average_speed_kmh' => $stats['average_speed_kmh'] ?? 0,
            'overspeed_events' => (int) ($stats['overspeed_events'] ?? 0),
            'total_duration_seconds' => max(0, (int) ($stats['total_duration_seconds'] ?? 0)),
            'start_time' => $stats['start_time'] ?? null,
            'end_time' => $stats['end_time'] ?? null,
            'stop_count' => (int) ($stats['stop_count'] ?? 0),
            'stops' => array_map(fn (array $stop) => [
                'lat' => (float) ($stop['lat'] ?? 0),
                'lng' => (float) ($stop['lng'] ?? 0),
                'duration_seconds' => (int) ($stop['duration_seconds'] ?? $stop['duration'] ?? 0),
                'duration' => (int) ($stop['duration_seconds'] ?? $stop['duration'] ?? 0),
                'start' => $stop['start'] ?? null,
                'end' => $stop['end'] ?? null,
                'start_display' => $stop['start_display'] ?? null,
                'end_display' => $stop['end_display'] ?? null,
                'status_label' => $stop['status_label'] ?? null,
                'motion_key' => $stop['motion_key'] ?? null,
            ], $stops),
        ];
    }

    private function cacheKey(Device $device, Carbon $from, ?Carbon $to, bool $explicitRange): string
    {
        $toKey = $to?->timestamp ?? 'open';

        return sprintf(
            'device_map_hist:%d:%s:%s:%d',
            $device->id,
            $from->timestamp,
            $toKey,
            $explicitRange ? 1 : 0,
        );
    }

    /**
     * @return array<string, mixed>
     */
    private function formatMapPoint(DeviceLocation $location): array
    {
        return [
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
            'gps_fix' => $location->gps_fix,
            'recorded_at' => $location->recorded_at
                ? \App\Support\DateTime\AppDateTime::toApi($location->recorded_at)
                : null,
            'time' => $location->recorded_at
                ? \App\Support\DateTime\AppDateTime::toApi($location->recorded_at)
                : null,
        ];
    }
}
