<?php

namespace App\Services\Tracking;

use App\Contracts\Tracking\EventReaderInterface;
use App\Models\Device;
use App\Models\DeviceLocation;
use App\Services\Mobile\MobileRouteAnalyticsService;
use App\Support\DateTime\AppDateTime;
use App\Support\Tracking\TelemetryFormatter;
use Carbon\Carbon;
use Illuminate\Support\Collection;

/**
 * Cached history fetch + split payloads for global tracking (/tracking).
 */
class GlobalTrackingHistoryService
{
    private const MAP_POINT_CAP = 2800;

    /** Downsample before analytics when tracks are very large. */
    private const ANALYTICS_POINT_CAP = 6000;

    /** Skip building the heavy timeline graph above this (events still compile from stops). */
    private const SKIP_TIMELINE_POINT_CAP = 5000;

    private const TIMELINE_OUTPUT_CAP = 400;

    public function __construct(
        private DeviceHistoryFetcher $historyFetcher,
        private MobileRouteAnalyticsService $analytics,
        private HistoryEventsCompiler $historyEvents,
        private EventReaderInterface $events,
        private HistoryTrackCache $trackCache,
    ) {}

    /**
     * @return array{locations: Collection<int, DeviceLocation>, used_fallback: bool, fallback_reason: ?string}
     */
    public function fetchLocations(Device $device, Carbon $from, ?Carbon $to): array
    {
        return $this->trackCache->remember($device, $from, $to, function () use ($device, $from, $to) {
            $fetch = $this->historyFetcher->fetch($device, $from, $to, true, allowFallback: false);

            return [
                'locations' => $fetch['locations'],
                'used_fallback' => $fetch['used_fallback'],
                'fallback_reason' => $fetch['fallback_reason'],
            ];
        });
    }

    /**
     * @param  Collection<int, DeviceLocation>  $locations
     * @return array<int, array<string, mixed>>
     */
    public function formatMapPoints(Collection $locations, bool $lite = true): array
    {
        return $this->downsampleForMap($locations)
            ->values()
            ->map(fn (DeviceLocation $loc) => $lite
                ? $this->formatMapPointLite($loc)
                : $this->formatHistoryPoint($loc))
            ->all();
    }

    /**
     * Single analyze pass — stats, timeline, and compiled events.
     *
     * @return array{stats: array<string, mixed>, timeline: list<array<string, mixed>>, events: list<array<string, mixed>>}
     */
    public function analyticsBundle(Device $device, Collection $locations, Carbon $from, ?Carbon $to): array
    {
        return $this->buildAnalyticsBundle($device, $locations, $from, $to);
    }

    /**
     * @return array{stats: array<string, mixed>, timeline: list<array<string, mixed>>, events: list<array<string, mixed>>}
     */
    private function buildAnalyticsBundle(Device $device, Collection $locations, Carbon $from, ?Carbon $to): array
    {
        $count = $locations->count();
        $analysisPoints = $count > self::ANALYTICS_POINT_CAP
            ? $this->downsampleForMap($locations, self::ANALYTICS_POINT_CAP)
            : $locations;

        $rawStats = $this->analytics->analyze($analysisPoints, [
            'point_statuses' => false,
            'include_track_points' => false,
            'skip_timeline' => $count > self::SKIP_TIMELINE_POINT_CAP,
        ]);

        return [
            'stats' => $this->publicStatsPayload($rawStats),
            'timeline' => $this->capTimeline($rawStats['timeline'] ?? []),
            'events' => $this->compileHistoryEventsFromStats($device, $rawStats, $from, $to),
        ];
    }

    /**
     * @param  list<array<string, mixed>>  $timeline
     * @return list<array<string, mixed>>
     */
    private function capTimeline(array $timeline, int $max = self::TIMELINE_OUTPUT_CAP): array
    {
        $count = count($timeline);
        if ($count <= $max) {
            return $timeline;
        }

        $step = (int) ceil($count / $max);
        $sampled = [];

        foreach ($timeline as $index => $segment) {
            if ($index === 0 || $index === $count - 1 || $index % $step === 0) {
                $sampled[] = $segment;
            }
        }

        return $sampled;
    }

    /**
     * @param  Collection<int, DeviceLocation>  $locations
     * @return list<array<string, mixed>>
     */
    public function compileHistoryEvents(Device $device, Collection $locations, Carbon $from, ?Carbon $to): array
    {
        $stats = $this->analytics->analyze($locations, [
            'point_statuses' => false,
            'include_track_points' => false,
            'skip_timeline' => false,
        ]);

        return $this->compileHistoryEventsFromStats($device, $stats, $from, $to);
    }

    /**
     * @param  array<string, mixed>  $stats  Raw analyze() output
     * @return list<array<string, mixed>>
     */
    public function compileHistoryEventsFromStats(Device $device, array $stats, Carbon $from, ?Carbon $to): array
    {
        $dbEvents = $this->events->forDevice($device, $from, $to, limit: 300);

        return $this->historyEvents->compile(
            $dbEvents,
            $stats['timeline'] ?? [],
            $stats['stops'] ?? [],
        );
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
     * @return array<string, mixed>
     */
    public function formatMapPointLite(DeviceLocation $location): array
    {
        return [
            'lat' => (float) $location->lat,
            'lng' => (float) $location->lng,
            'speed' => (float) ($location->speed ?? 0),
            'heading' => (float) ($location->heading ?? 0),
            'ignition' => (bool) $location->ignition,
            'recorded_at' => AppDateTime::toApi($location->recorded_at),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function formatHistoryPoint(DeviceLocation $location): array
    {
        return [
            'lat' => (float) $location->lat,
            'lng' => (float) $location->lng,
            'speed' => (float) ($location->speed ?? 0),
            'heading' => (float) ($location->heading ?? 0),
            'ignition' => (bool) $location->ignition,
            'acc' => (bool) ($location->acc ?? false),
            'battery' => $location->battery_level,
            'battery_level' => $location->battery_level,
            'gsm_signal' => $location->gsm_signal,
            'gps_signal' => $location->gps_signal,
            'satellites' => $location->satellites,
            'odometer' => $location->odometer,
            'odometer_km' => TelemetryFormatter::odometerKm($location->odometer),
            'altitude' => $location->altitude !== null ? round((float) $location->altitude) : null,
            'power_cut' => (bool) $location->power_cut,
            'panic' => (bool) $location->panic,
            'gps_fix' => $location->gps_fix,
            'recorded_at' => AppDateTime::toApi($location->recorded_at),
            'time' => AppDateTime::toApi($location->recorded_at),
            'timestamp' => $location->recorded_at
                ? AppDateTime::format($location->recorded_at, 'log')
                : null,
            'position_id' => (int) ($location->id ?? 0),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function vehiclePointsPayload(
        Device $device,
        Collection $locations,
        bool $usedFallback,
        ?string $fallbackReason,
        ?string $color = null,
    ): array {
        $points = $this->formatMapPoints($locations);

        return array_merge([
            'id' => $device->id,
            'name' => $device->mapMarkerTitle(),
            'title' => $device->mapMarkerTitle(),
            'plate' => $device->mapMarkerPlateLine() ?? $device->vehiclePlateNumber(),
            'color' => $color,
            'points' => $points,
            'point_count' => $locations->count(),
            'display_point_count' => count($points),
            'used_fallback' => $usedFallback,
            'fallback_reason' => $fallbackReason,
        ], $device->mapAppearancePayload());
    }

    /**
     * @return array<string, mixed>
     */
    public function vehicleAnalyticsPayload(
        Device $device,
        Collection $locations,
        Carbon $from,
        ?Carbon $to,
        bool $usedFallback,
        ?string $fallbackReason,
        ?string $color = null,
    ): array {
        $bundle = $this->analyticsBundle($device, $locations, $from, $to);

        return array_merge([
            'id' => $device->id,
            'name' => $device->mapMarkerTitle(),
            'title' => $device->mapMarkerTitle(),
            'plate' => $device->mapMarkerPlateLine() ?? $device->vehiclePlateNumber(),
            'color' => $color,
            'stats' => $bundle['stats'],
            'timeline' => $bundle['timeline'],
            'events' => $bundle['events'],
            'history_events' => $bundle['events'],
            'point_count' => $locations->count(),
            'used_fallback' => $usedFallback,
            'fallback_reason' => $fallbackReason,
        ], $device->mapAppearancePayload());
    }

    /**
     * Build one vehicle history bundle (legacy single endpoint).
     *
     * @return array<string, mixed>|null
     */
    public function vehicleHistoryBundle(
        Device $device,
        Collection $locations,
        Carbon $from,
        ?Carbon $to,
        ?string $color = null,
    ): ?array {
        if ($locations->isEmpty()) {
            return null;
        }

        $bundle = $this->analyticsBundle($device, $locations, $from, $to);
        $points = $this->formatMapPoints($locations, lite: $locations->count() > self::SKIP_TIMELINE_POINT_CAP);

        $payload = [
            'id' => $device->id,
            'name' => $device->mapMarkerTitle(),
            'title' => $device->mapMarkerTitle(),
            'plate' => $device->mapMarkerPlateLine() ?? $device->vehiclePlateNumber(),
            'color' => $color,
            'points' => $points,
            'stats' => $bundle['stats'],
            'events' => $bundle['events'],
            'history_events' => $bundle['events'],
            'point_count' => $locations->count(),
            'display_point_count' => count($points),
        ];

        if ($locations->count() <= self::SKIP_TIMELINE_POINT_CAP) {
            $payload['timeline'] = $bundle['timeline'];
        } else {
            $payload['timeline'] = [];
        }

        return array_merge($payload, $device->mapAppearancePayload());
    }
}
