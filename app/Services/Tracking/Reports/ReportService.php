<?php

namespace App\Services\Tracking\Reports;

use App\Contracts\Tracking\EventReaderInterface;
use App\Models\Device;
use App\Models\DeviceLocation;
use App\Models\User;
use App\Models\VehicleEvent;
use App\Services\Mobile\VehicleStatusSpec;
use App\Services\Tracking\DeviceHistoryFetcher;
use App\Services\Tracking\GlobalTrackingService;
use App\Services\Tracking\HistoryAnalyticsService;
use App\Support\DateTime\AppDateTime;
use Carbon\Carbon;
use Illuminate\Support\Collection;

class ReportService
{
    public const MAX_DEVICES = 50;

    /** Max GPS rows returned per device in web JSON (table paginates client-side). */
    public const MAX_POSITIONS_WEB_PER_DEVICE = 2500;

    /** Max GPS rows per device in CSV/Excel/PDF exports. */
    public const MAX_POSITIONS_EXPORT_PER_DEVICE = 50000;

    /** Max events per device in web JSON. */
    public const MAX_EVENTS_WEB_PER_DEVICE = 1000;

    /** Max events per device in exports. */
    public const MAX_EVENTS_EXPORT_PER_DEVICE = 10000;

    /** Max route polyline points for the map widget. */
    public const MAX_ROUTE_MAP_POINTS = 3000;

    /** Downsample GPS rows above this count for trips/stops analytics. */
    public const MAX_ANALYTICS_POINTS = 15000;

    /** Summary reports use full GPS data up to this count for accurate totals. */
    public const MAX_SUMMARY_FULL_POINTS = 50000;

    /** @var array<int, Collection<int, DeviceLocation>> */
    private array $locationCache = [];

    /** @var array<string, array<string, mixed>> */
    private array $statsCache = [];

    /** @var array<int, list<DeviceLocation>> */
    private array $sortedPointsCache = [];

    private bool $forExport = false;

    public function __construct(
        private DeviceHistoryFetcher $historyFetcher,
        private HistoryAnalyticsService $historyAnalytics,
        private EventReaderInterface $events,
        private GlobalTrackingService $tracking,
        private \App\Contracts\Tracking\PositionReaderInterface $positions,
    ) {}

    /**
     * @param  list<int>  $deviceIds
     * @return array<string, mixed>
     */
    public function generate(User $actor, string $type, array $deviceIds, Carbon $from, ?Carbon $to, bool $forExport = false): array
    {
        $this->locationCache = [];
        $this->statsCache = [];
        $this->sortedPointsCache = [];
        $this->forExport = $forExport;

        $requestedCount = count($deviceIds);
        $ids = array_slice(
            $this->tracking->filterAllowedIds($actor, $deviceIds),
            0,
            self::MAX_DEVICES
        );

        if ($ids === []) {
            return [
                'type' => $type,
                'devices' => [],
                'from' => $from->toIso8601String(),
                'to' => $to?->toIso8601String(),
                'meta' => ['devices_requested' => $requestedCount, 'devices_in_report' => 0],
            ];
        }

        $devices = Device::query()->whereIn('id', $ids)->get()->keyBy('id');
        $this->prefetchLocations($devices->values()->all(), $from, $to);

        $results = [];
        $positionsTruncated = false;
        $analyticsDownsampled = false;

        foreach ($ids as $id) {
            $device = $devices->get($id);
            if (! $device) {
                continue;
            }

            $row = match ($type) {
                'route' => $this->routeReport($device, $from, $to, $type),
                'positions' => $this->positionsReport($device, $from, $to),
                'trips' => $this->tripsReport($device, $from, $to, $type),
                'stops' => $this->stopsReport($device, $from, $to, $type),
                'summary' => $this->summaryReport($device, $from, $to, $type),
                'events' => $this->eventsReport($device, $from, $to),
                default => $this->summaryReport($device, $from, $to, 'summary'),
            };

            if (($row['positions_truncated'] ?? false) === true) {
                $positionsTruncated = true;
            }

            if (($row['analytics_downsampled'] ?? false) === true) {
                $analyticsDownsampled = true;
            }

            unset($row['analytics_downsampled']);

            $results[] = $row;

            if (count($ids) > 3 && ! $this->forExport) {
                $rangeKey = $from->toIso8601String().'|'.($to?->toIso8601String() ?? '');
                unset($this->locationCache[$this->locationCacheKey($id, $rangeKey)]);
                unset($this->statsCache[$this->statsCacheKey($id, $from, $to, $type)]);
                unset($this->sortedPointsCache[$id]);
            }
        }

        return [
            'type' => $type,
            'from' => $from->toIso8601String(),
            'to' => $to?->toIso8601String(),
            'devices' => $results,
            'totals' => $this->aggregateTotals($type, $results),
            'meta' => [
                'devices_requested' => $requestedCount,
                'devices_in_report' => count($results),
                'devices_capped' => $requestedCount > self::MAX_DEVICES,
                'positions_truncated' => $positionsTruncated,
                'analytics_downsampled' => $analyticsDownsampled,
            ],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function routeReport(Device $device, Carbon $from, ?Carbon $to, string $reportType): array
    {
        $collection = $this->loadLocationCollection($device, $from, $to);
        $stats = $this->statsFor($device, $collection, $reportType, $from, $to);
        $tripCount = $this->countTripsFromTimeline($stats['timeline'] ?? []);
        $pointCount = $collection->count();

        return array_merge($this->deviceMeta($device), [
            'point_count' => $pointCount,
            'total_distance_km' => $stats['total_distance_km'] ?? 0,
            'moving_time_seconds' => $stats['moving_time_seconds'] ?? 0,
            'stopped_time_seconds' => $stats['stopped_time_seconds'] ?? 0,
            'idle_time_seconds' => $stats['idle_time_seconds'] ?? 0,
            'parking_time_seconds' => $stats['parking_time_seconds'] ?? 0,
            'offline_time_seconds' => $stats['offline_time_seconds'] ?? 0,
            'max_speed_kmh' => $stats['max_speed_kmh'] ?? 0,
            'average_speed_kmh' => $stats['average_speed_kmh'] ?? 0,
            'total_duration_seconds' => $stats['total_duration_seconds'] ?? 0,
            'start_time' => $stats['start_time'] ?? null,
            'end_time' => $stats['end_time'] ?? null,
            'trip_count' => $tripCount,
            'stop_count' => $stats['stop_count'] ?? 0,
            'points' => $this->buildRoutePoints($collection),
            'analytics_downsampled' => $stats['analytics_downsampled'] ?? false,
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function positionsReport(Device $device, Carbon $from, ?Carbon $to): array
    {
        $collection = $this->loadLocationCollection($device, $from, $to);
        $total = $collection->count();
        $cap = $this->forExport
            ? self::MAX_POSITIONS_EXPORT_PER_DEVICE
            : self::MAX_POSITIONS_WEB_PER_DEVICE;
        $useDetailedStatus = $total <= 800 && ! $this->forExport;
        $statuses = $useDetailedStatus
            ? $this->historyAnalytics->pointStatuses($collection)
            : [];
        $positions = [];

        foreach ($collection->values() as $index => $loc) {
            if (count($positions) >= $cap) {
                break;
            }

            if ($useDetailedStatus) {
                $status = $statuses[$index] ?? [];
                $statusLabel = (string) ($status['motion_status'] ?? $status['status_label'] ?? '');
                $statusKey = (string) ($status['motion_status_key'] ?? $status['status_key'] ?? '');
            } else {
                $statusKey = VehicleStatusSpec::motionKey(
                    (float) ($loc->speed ?? 0),
                    (bool) $loc->ignition,
                );
                $statusLabel = VehicleStatusSpec::motionLabel($statusKey);
            }

            $positions[] = [
                'time' => AppDateTime::toApi($loc->recorded_at),
                'time_display' => app_datetime_format($loc->recorded_at),
                'lat' => (float) $loc->lat,
                'lng' => (float) $loc->lng,
                'speed' => round((float) ($loc->speed ?? 0), 1),
                'heading' => isset($loc->heading) ? (float) $loc->heading : null,
                'ignition' => (bool) $loc->ignition,
                'status' => $statusLabel,
                'status_key' => $statusKey,
            ];
        }

        return array_merge($this->deviceMeta($device), [
            'positions' => $positions,
            'position_count' => $total,
            'positions_returned' => count($positions),
            'positions_truncated' => $total > count($positions),
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function tripsReport(Device $device, Carbon $from, ?Carbon $to, string $reportType): array
    {
        $collection = $this->loadLocationCollection($device, $from, $to);
        $stats = $this->statsFor($device, $collection, $reportType, $from, $to);
        $sorted = $this->sortedPointsForDevice($device, $collection);
        $trips = $this->tripsFromTimeline($sorted, $stats['timeline'] ?? []);

        return array_merge($this->deviceMeta($device), [
            'trips' => $trips,
            'trip_count' => count($trips),
            'analytics_downsampled' => $stats['analytics_downsampled'] ?? false,
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function stopsReport(Device $device, Carbon $from, ?Carbon $to, string $reportType): array
    {
        $collection = $this->loadLocationCollection($device, $from, $to);
        $stats = $this->statsFor($device, $collection, $reportType, $from, $to);

        return array_merge($this->deviceMeta($device), [
            'stops' => $stats['stops'] ?? [],
            'stop_count' => $stats['stop_count'] ?? 0,
            'analytics_downsampled' => $stats['analytics_downsampled'] ?? false,
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function summaryReport(Device $device, Carbon $from, ?Carbon $to, string $reportType): array
    {
        $collection = $this->loadLocationCollection($device, $from, $to);
        $stats = $this->statsFor($device, $collection, $reportType, $from, $to);
        $tripCount = $this->countTripsFromTimeline($stats['timeline'] ?? []);
        $pointCount = $collection->count();

        return array_merge($this->deviceMeta($device), [
            'point_count' => $pointCount,
            'total_distance_km' => $stats['total_distance_km'] ?? 0,
            'moving_time_seconds' => $stats['moving_time_seconds'] ?? 0,
            'stopped_time_seconds' => $stats['stopped_time_seconds'] ?? 0,
            'idle_time_seconds' => $stats['idle_time_seconds'] ?? 0,
            'parking_time_seconds' => $stats['parking_time_seconds'] ?? 0,
            'offline_time_seconds' => $stats['offline_time_seconds'] ?? 0,
            'max_speed_kmh' => $stats['max_speed_kmh'] ?? 0,
            'average_speed_kmh' => $stats['average_speed_kmh'] ?? 0,
            'overspeed_events' => $stats['overspeed_events'] ?? 0,
            'stop_count' => $stats['stop_count'] ?? 0,
            'trip_count' => $tripCount,
            'start_time' => $stats['start_time'] ?? null,
            'end_time' => $stats['end_time'] ?? null,
            'total_duration_seconds' => $stats['total_duration_seconds'] ?? 0,
            'analytics_downsampled' => $stats['analytics_downsampled'] ?? false,
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function eventsReport(Device $device, Carbon $from, ?Carbon $to): array
    {
        $events = $this->events
            ->forDevice(
                $device,
                $from,
                $to,
                limit: $this->forExport
                    ? self::MAX_EVENTS_EXPORT_PER_DEVICE
                    : self::MAX_EVENTS_WEB_PER_DEVICE,
            )
            ->map(fn (VehicleEvent $event) => array_merge($event->toAlertArray(), [
                'lat' => $event->lat !== null ? (float) $event->lat : null,
                'lng' => $event->lng !== null ? (float) $event->lng : null,
            ]))
            ->values()
            ->all();

        return array_merge($this->deviceMeta($device), [
            'events' => $events,
            'event_count' => count($events),
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function deviceMeta(Device $device): array
    {
        return [
            'device_id' => $device->id,
            'device_name' => $device->mapMarkerTitle(),
            'plate' => $device->vehiclePlateNumber() ?? '',
            'driver' => $device->driverDisplayName() ?? '',
        ];
    }

    /**
     * @param  list<array<string, mixed>>  $devices
     * @return array<string, mixed>
     */
    private function aggregateTotals(string $type, array $devices): array
    {
        if ($devices === []) {
            return [];
        }

        if ($type === 'summary' || $type === 'route') {
            return [
                'device_count' => count($devices),
                'total_distance_km' => round(array_sum(array_map(
                    fn (array $d) => (float) ($d['total_distance_km'] ?? 0),
                    $devices
                )), 2),
                'moving_time_seconds' => array_sum(array_map(
                    fn (array $d) => (int) ($d['moving_time_seconds'] ?? 0),
                    $devices
                )),
                'stopped_time_seconds' => array_sum(array_map(
                    fn (array $d) => (int) ($d['stopped_time_seconds'] ?? 0),
                    $devices
                )),
                'idle_time_seconds' => array_sum(array_map(
                    fn (array $d) => (int) ($d['idle_time_seconds'] ?? 0),
                    $devices
                )),
                'parking_time_seconds' => array_sum(array_map(
                    fn (array $d) => (int) ($d['parking_time_seconds'] ?? 0),
                    $devices
                )),
                'offline_time_seconds' => array_sum(array_map(
                    fn (array $d) => (int) ($d['offline_time_seconds'] ?? 0),
                    $devices
                )),
                'max_speed_kmh' => max(array_map(
                    fn (array $d) => (float) ($d['max_speed_kmh'] ?? 0),
                    $devices
                )),
                'trip_count' => array_sum(array_map(
                    fn (array $d) => (int) ($d['trip_count'] ?? 0),
                    $devices
                )),
                'stop_count' => array_sum(array_map(
                    fn (array $d) => (int) ($d['stop_count'] ?? 0),
                    $devices
                )),
                'overspeed_events' => array_sum(array_map(
                    fn (array $d) => (int) ($d['overspeed_events'] ?? 0),
                    $devices
                )),
                'point_count' => array_sum(array_map(
                    fn (array $d) => (int) ($d['point_count'] ?? 0),
                    $devices
                )),
            ];
        }

        if ($type === 'trips') {
            return [
                'device_count' => count($devices),
                'trip_count' => array_sum(array_map(
                    fn (array $d) => (int) ($d['trip_count'] ?? 0),
                    $devices
                )),
            ];
        }

        if ($type === 'stops') {
            return [
                'device_count' => count($devices),
                'stop_count' => array_sum(array_map(
                    fn (array $d) => (int) ($d['stop_count'] ?? 0),
                    $devices
                )),
            ];
        }

        if ($type === 'events') {
            return [
                'device_count' => count($devices),
                'event_count' => array_sum(array_map(
                    fn (array $d) => (int) ($d['event_count'] ?? 0),
                    $devices
                )),
            ];
        }

        if ($type === 'positions') {
            return [
                'device_count' => count($devices),
                'position_count' => array_sum(array_map(
                    fn (array $d) => (int) ($d['position_count'] ?? 0),
                    $devices
                )),
            ];
        }

        return ['device_count' => count($devices)];
    }

    /**
     * @param  list<Device>  $devices
     */
    private function prefetchLocations(array $devices, Carbon $from, ?Carbon $to): void
    {
        if ($devices === []) {
            return;
        }

        $rangeKey = $from->toIso8601String().'|'.($to?->toIso8601String() ?? '');
        $missing = array_values(array_filter(
            $devices,
            fn (Device $device) => ! array_key_exists($this->locationCacheKey($device->id, $rangeKey), $this->locationCache)
        ));

        if ($missing === []) {
            return;
        }

        $batch = $this->positions->historyForDevices($missing, $from, $to, 'asc');

        foreach ($missing as $device) {
            $this->locationCache[$this->locationCacheKey($device->id, $rangeKey)] = $batch[$device->id] ?? collect();
        }
    }

    private function locationCacheKey(int $deviceId, string $rangeKey): string
    {
        return $deviceId.'|'.$rangeKey;
    }

    /**
     * @return Collection<int, DeviceLocation>
     */
    private function loadLocationCollection(Device $device, Carbon $from, ?Carbon $to): Collection
    {
        $rangeKey = $from->toIso8601String().'|'.($to?->toIso8601String() ?? '');
        $cacheKey = $this->locationCacheKey($device->id, $rangeKey);

        if (array_key_exists($cacheKey, $this->locationCache)) {
            return $this->locationCache[$cacheKey];
        }

        $locations = $this->historyFetcher->fetch($device, $from, $to, true, false)['locations'];
        $this->locationCache[$cacheKey] = $locations;

        return $locations;
    }

    /**
     * @param  Collection<int, DeviceLocation>  $collection
     * @return array<string, mixed>
     */
    private function statsFor(
        Device $device,
        Collection $collection,
        string $reportType,
        Carbon $from,
        ?Carbon $to,
    ): array {
        $cacheKey = $this->statsCacheKey($device->id, $from, $to, $reportType);
        if (array_key_exists($cacheKey, $this->statsCache)) {
            return $this->statsCache[$cacheKey];
        }

        $analyticsPoints = $this->collectionForAnalytics($collection, $reportType);
        $wasDownsampled = $analyticsPoints->count() < $collection->count();

        $stats = $this->historyAnalytics->analyze($analyticsPoints, [
            'point_statuses' => false,
            'include_track_points' => false,
            'minimal_stats' => false,
        ]);

        if ($wasDownsampled) {
            $stats['total_distance_km'] = $this->historyAnalytics->distanceKmForPoints($collection);
            $stats['max_speed_kmh'] = $this->maxSpeedKmhFromCollection($collection);
            $movingSec = (int) ($stats['moving_time_seconds'] ?? 0);
            $dist = (float) ($stats['total_distance_km'] ?? 0);
            if ($movingSec > 0) {
                $stats['average_speed_kmh'] = round($dist / ($movingSec / 3600), 1);
            }
        }

        $stats['analytics_downsampled'] = $wasDownsampled;
        $this->statsCache[$cacheKey] = $stats;

        return $stats;
    }

    /**
     * @param  Collection<int, DeviceLocation>  $collection
     */
    private function maxSpeedKmhFromCollection(Collection $collection): float
    {
        $max = 0.0;

        foreach ($collection as $loc) {
            $spd = (float) ($loc->speed ?? 0);
            if ($spd > $max) {
                $max = $spd;
            }
        }

        return round($max, 1);
    }

    private function statsCacheKey(int $deviceId, Carbon $from, ?Carbon $to, string $reportType): string
    {
        return implode('|', [
            (string) $deviceId,
            $reportType,
            $from->toIso8601String(),
            $to?->toIso8601String() ?? '',
        ]);
    }

    /**
     * @return Collection<int, DeviceLocation>
     */
    private function collectionForAnalytics(Collection $collection, string $reportType): Collection
    {
        $count = $collection->count();
        $max = $reportType === 'summary' || $reportType === 'route'
            ? self::MAX_SUMMARY_FULL_POINTS
            : self::MAX_ANALYTICS_POINTS;

        if ($count <= $max) {
            return $collection;
        }

        return $this->downsamplePreservingMotion($collection, $max);
    }

    /**
     * Keep motion/ignition changes plus uniform samples so totals stay representative.
     *
     * @return Collection<int, DeviceLocation>
     */
    private function downsamplePreservingMotion(Collection $collection, int $max): Collection
    {
        $values = $collection->values();
        $count = $values->count();
        if ($count <= $max) {
            return $collection;
        }

        $keep = [];
        $prevKey = null;

        foreach ($values as $index => $point) {
            $motionKey = VehicleStatusSpec::motionKey(
                (float) ($point->speed ?? 0),
                (bool) ($point->ignition ?? false),
            );
            $isEdge = $index === 0 || $index === ($count - 1);
            $changed = $motionKey !== $prevKey;
            $prevKey = $motionKey;

            if ($isEdge || $changed) {
                $keep[$index] = $point;
            }
        }

        $step = max(1, (int) ceil($count / $max));
        for ($i = 0; $i < $count; $i += $step) {
            $keep[$i] = $values[$i];
        }

        ksort($keep);

        return collect(array_values($keep));
    }

    /**
     * @return list<DeviceLocation>
     */
    private function sortedPointsForDevice(Device $device, Collection $collection): array
    {
        if (array_key_exists($device->id, $this->sortedPointsCache)) {
            return $this->sortedPointsCache[$device->id];
        }

        $sorted = $collection
            ->filter(fn ($p) => isset($p->lat, $p->lng, $p->recorded_at))
            ->sortBy(fn ($p) => ($p->recorded_at instanceof Carbon
                ? $p->recorded_at->getTimestamp()
                : Carbon::parse($p->recorded_at)->getTimestamp()).':'.($p->id ?? 0))
            ->values()
            ->all();

        $this->sortedPointsCache[$device->id] = $sorted;

        return $sorted;
    }

    /**
     * @param  Collection<int, DeviceLocation>  $collection
     * @return list<array<string, mixed>>
     */
    private function buildRoutePoints(Collection $collection): array
    {
        if ($collection->isEmpty()) {
            return [];
        }

        $values = $collection->values();
        $total = $values->count();
        $step = max(1, (int) ceil($total / self::MAX_ROUTE_MAP_POINTS));
        $points = [];

        for ($i = 0; $i < $total; $i += $step) {
            /** @var DeviceLocation $loc */
            $loc = $values[$i];
            $points[] = [
                'lat' => (float) $loc->lat,
                'lng' => (float) $loc->lng,
                'speed' => (float) ($loc->speed ?? 0),
                'heading' => (float) ($loc->heading ?? 0),
                'ignition' => (bool) $loc->ignition,
                'recorded_at' => AppDateTime::toApi($loc->recorded_at),
            ];
        }

        $last = $values[$total - 1];
        if ($points !== [] && ($points[count($points) - 1]['recorded_at'] ?? null) !== AppDateTime::toApi($last->recorded_at)) {
            $points[] = [
                'lat' => (float) $last->lat,
                'lng' => (float) $last->lng,
                'speed' => (float) ($last->speed ?? 0),
                'heading' => (float) ($last->heading ?? 0),
                'ignition' => (bool) $last->ignition,
                'recorded_at' => AppDateTime::toApi($last->recorded_at),
            ];
        }

        return $points;
    }

    /**
     * Count trips from timeline without building trip rows (fast path for summary/route).
     *
     * @param  list<array<string, mixed>>  $timeline
     */
    private function countTripsFromTimeline(array $timeline): int
    {
        if ($timeline === []) {
            return 0;
        }

        $movingKeys = ['running', 'moving'];
        $breakKeys = ['parked', 'stopped', 'idle', 'offline'];
        $stopMin = HistoryAnalyticsService::STOP_MIN_SECONDS;
        $count = 0;
        $hasMoving = false;
        $breakSec = 0;

        $flush = function () use (&$count, &$hasMoving, &$breakSec): void {
            if ($hasMoving) {
                $count++;
            }
            $hasMoving = false;
            $breakSec = 0;
        };

        foreach ($timeline as $segment) {
            if (($segment['is_transition'] ?? false) === true) {
                continue;
            }

            $key = (string) ($segment['status_key'] ?? '');
            $dur = (int) ($segment['duration_seconds'] ?? 0);

            if (in_array($key, $movingKeys, true)) {
                if ($breakSec >= $stopMin) {
                    $flush();
                }
                $breakSec = 0;
                $hasMoving = true;
            } elseif (in_array($key, $breakKeys, true)) {
                $breakSec += $dur;
                if ($breakSec >= $stopMin && $hasMoving) {
                    $flush();
                }
            }
        }

        if ($hasMoving) {
            $count++;
        }

        return $count;
    }

    /**
     * Build trips from ignition-aware timeline segments (Traccar-style).
     *
     * @param  list<DeviceLocation>  $sortedPoints
     * @param  list<array<string, mixed>>  $timeline
     * @return list<array<string, mixed>>
     */
    private function tripsFromTimeline(array $sortedPoints, array $timeline): array
    {
        if ($timeline === []) {
            return [];
        }

        $movingKeys = ['running', 'moving'];
        $breakKeys = ['parked', 'stopped', 'idle', 'offline'];
        $stopMin = HistoryAnalyticsService::STOP_MIN_SECONDS;
        $trips = [];
        $bucket = [];
        $breakSec = 0;

        $flush = function () use (&$trips, &$bucket, &$breakSec, $sortedPoints): void {
            if ($bucket === []) {
                $breakSec = 0;

                return;
            }

            $trip = $this->buildTripFromSegments($sortedPoints, $bucket);
            if ($trip !== null) {
                $trips[] = $trip;
            }

            $bucket = [];
            $breakSec = 0;
        };

        foreach ($timeline as $segment) {
            if (($segment['is_transition'] ?? false) === true) {
                continue;
            }

            $key = (string) ($segment['status_key'] ?? '');
            $dur = (int) ($segment['duration_seconds'] ?? 0);

            if (in_array($key, $movingKeys, true)) {
                if ($breakSec >= $stopMin) {
                    $flush();
                }
                $breakSec = 0;
                $bucket[] = $segment;
            } elseif (in_array($key, $breakKeys, true)) {
                $breakSec += $dur;
                if ($breakSec >= $stopMin && $bucket !== []) {
                    $flush();
                }
            }
        }

        $flush();

        return $trips;
    }

    /**
     * @param  list<DeviceLocation>  $sortedPoints
     * @param  list<array<string, mixed>>  $segments
     * @return array<string, mixed>|null
     */
    private function buildTripFromSegments(array $sortedPoints, array $segments): ?array
    {
        if ($segments === []) {
            return null;
        }

        $first = $segments[0];
        $last = $segments[count($segments) - 1];
        $startAt = Carbon::parse((string) ($first['start'] ?? ''));
        $endAt = Carbon::parse((string) ($last['end'] ?? ''));

        $startIdx = $this->pointIndexAtOrAfter($sortedPoints, $startAt);
        $endIdx = $this->pointIndexAtOrBefore($sortedPoints, $endAt);
        $slice = ($startIdx !== null && $endIdx !== null && $endIdx >= $startIdx)
            ? array_slice($sortedPoints, $startIdx, $endIdx - $startIdx + 1)
            : [];

        $movingSec = array_sum(array_map(
            fn (array $segment) => (int) ($segment['duration_seconds'] ?? 0),
            $segments
        ));
        $maxSpeed = max(array_merge(
            [0.0],
            array_map(
                fn (array $segment) => (float) ($segment['max_speed_kmh'] ?? $segment['speed_kmh'] ?? 0),
                $segments
            )
        ));
        $duration = max(0, (int) $startAt->diffInSeconds($endAt));
        $distance = count($slice) >= 2
            ? $this->historyAnalytics->distanceKmForPoints(collect($slice))
            : $this->segmentChainDistanceKm($segments);

        $avgSpeed = $movingSec > 0
            ? round($distance / ($movingSec / 3600), 1)
            : ($duration > 0 ? round($distance / ($duration / 3600), 1) : 0);

        return [
            'start_time' => (string) ($first['start_display'] ?? $first['start'] ?? ''),
            'end_time' => (string) ($last['end_display'] ?? $last['end'] ?? ''),
            'start_lat' => $first['start_lat'] ?? null,
            'start_lng' => $first['start_lng'] ?? null,
            'end_lat' => $last['end_lat'] ?? null,
            'end_lng' => $last['end_lng'] ?? null,
            'distance_km' => $distance,
            'duration_seconds' => $duration > 0 ? $duration : $movingSec,
            'moving_time_seconds' => $movingSec,
            'max_speed_kmh' => round($maxSpeed, 1),
            'average_speed_kmh' => $avgSpeed,
        ];
    }

    /**
     * @param  list<DeviceLocation>  $points
     */
    private function pointIndexAtOrAfter(array $points, Carbon $at): ?int
    {
        $lo = 0;
        $hi = count($points) - 1;
        $found = null;

        while ($lo <= $hi) {
            $mid = intdiv($lo + $hi, 2);
            $pointAt = $this->pointRecordedAt($points[$mid]);
            if (! $pointAt) {
                $lo = $mid + 1;

                continue;
            }

            if ($pointAt->greaterThanOrEqualTo($at)) {
                $found = $mid;
                $hi = $mid - 1;
            } else {
                $lo = $mid + 1;
            }
        }

        return $found;
    }

    /**
     * @param  list<DeviceLocation>  $points
     */
    private function pointIndexAtOrBefore(array $points, Carbon $at): ?int
    {
        $lo = 0;
        $hi = count($points) - 1;
        $found = null;

        while ($lo <= $hi) {
            $mid = intdiv($lo + $hi, 2);
            $pointAt = $this->pointRecordedAt($points[$mid]);
            if (! $pointAt) {
                $hi = $mid - 1;

                continue;
            }

            if ($pointAt->lessThanOrEqualTo($at)) {
                $found = $mid;
                $lo = $mid + 1;
            } else {
                $hi = $mid - 1;
            }
        }

        return $found;
    }

    private function pointRecordedAt(DeviceLocation $point): ?Carbon
    {
        if (! isset($point->recorded_at)) {
            return null;
        }

        return $point->recorded_at instanceof Carbon
            ? $point->recorded_at
            : Carbon::parse($point->recorded_at);
    }

    /**
     * @param  list<array<string, mixed>>  $segments
     */
    private function segmentChainDistanceKm(array $segments): float
    {
        $distance = 0.0;
        $prevLat = null;
        $prevLng = null;

        foreach ($segments as $segment) {
            $lat = (float) ($segment['start_lat'] ?? $segment['end_lat'] ?? 0);
            $lng = (float) ($segment['start_lng'] ?? $segment['end_lng'] ?? 0);
            if ($prevLat !== null && $prevLng !== null) {
                $distance += $this->historyAnalytics->distanceKmForPoints(collect([
                    (object) ['lat' => $prevLat, 'lng' => $prevLng],
                    (object) ['lat' => $lat, 'lng' => $lng],
                ]));
            }
            $prevLat = (float) ($segment['end_lat'] ?? $lat);
            $prevLng = (float) ($segment['end_lng'] ?? $lng);
        }

        return round($distance, 2);
    }
}
