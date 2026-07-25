<?php

namespace App\Services\Tracking\Reports;

use App\Contracts\Tracking\EventReaderInterface;
use App\Models\Device;
use App\Models\DeviceLocation;
use App\Models\User;
use App\Models\VehicleEvent;
use App\Services\Mobile\VehicleStatusSpec;
use App\Services\Tracking\DeviceFuelService;
use App\Services\Tracking\DeviceHistoryFetcher;
use App\Services\Tracking\DeviceOdometerService;
use App\Services\Tracking\DevicePositionLoader;
use App\Services\Tracking\GlobalTrackingService;
use App\Services\Tracking\HistoryAnalyticsService;
use App\Services\Tracking\MaintenanceService;
use App\Services\Tracking\TaskService;
use App\Services\Tracking\TrackingSettingsService;
use App\Support\DateTime\AppDateTime;
use App\Support\Tracking\TelemetryFormatter;
use Carbon\Carbon;
use Illuminate\Support\Collection;

class ReportService
{
    public const MAX_DEVICES = 200;

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

    /** Downsample GPS rows above this count for trips/stops analytics (exports). */
    public const MAX_ANALYTICS_POINTS = 25000;

    /** Interactive web/mobile trips/stops analytics budget. */
    public const MAX_ANALYTICS_POINTS_WEB = 16000;

    /** Summary reports use full GPS data up to this count for accurate export totals. */
    public const MAX_SUMMARY_FULL_POINTS = 80000;

    /** Interactive summary/mileage/route point budget (week-long accuracy). */
    public const MAX_SUMMARY_WEB_POINTS = 30000;

    /** Prefetch tc_positions in day windows when the range is longer than this. */
    public const PREFETCH_DAY_CHUNK_THRESHOLD = 2;

    /** @var array<int, Collection<int, DeviceLocation>> */
    private array $locationCache = [];

    /** @var array<string, array<string, mixed>> */
    private array $statsCache = [];

    /** @var array<int, list<DeviceLocation>> */
    private array $sortedPointsCache = [];

    private bool $forExport = false;

    private ReportFilters $filters;

    /** Report types that do not need GPS history prefetch. */
    private const LIGHTWEIGHT_TYPES = [
        'current_position',
        'object_info',
        'service',
        'tasks',
    ];

    /** @var list<array<string, mixed>>|null */
    private ?array $maintenanceCache = null;

    public function __construct(
        private DeviceHistoryFetcher $historyFetcher,
        private HistoryAnalyticsService $historyAnalytics,
        private EventReaderInterface $events,
        private GlobalTrackingService $tracking,
        private \App\Contracts\Tracking\PositionReaderInterface $positions,
        private DeviceFuelService $fuel,
        private MaintenanceService $maintenance,
        private TaskService $tasks,
        private DevicePositionLoader $positionLoader,
        private TrackingSettingsService $trackingSettings,
        private ReportLocationLabelResolver $locationLabels,
    ) {
        $this->filters = ReportFilters::defaults();
    }

    /**
     * PHP execution budget for report generate/export requests.
     * Sized for week-long single-vehicle analytics under reverse-proxy soft limits
     * when the client also windows the range.
     */
    public static function recommendedTimeLimitSeconds(
        int $deviceCount,
        Carbon $from,
        ?Carbon $to,
        bool $forExport = false,
    ): int {
        $deviceCount = max(1, $deviceCount);
        $end = $to ?? now();
        $days = max(1, (int) ceil(max(1, $from->diffInRealSeconds($end)) / 86400));
        $perDevice = max(60, $days * 35);
        $seconds = 180 + ($deviceCount * $perDevice);

        if ($forExport) {
            $seconds = (int) round($seconds * 1.6);
        }

        return min(1200, max(240, $seconds));
    }

    public static function applyTimeLimit(
        int $deviceCount,
        Carbon $from,
        ?Carbon $to,
        bool $forExport = false,
    ): void {
        $seconds = self::recommendedTimeLimitSeconds($deviceCount, $from, $to, $forExport);
        set_time_limit($seconds);
        @ini_set('max_execution_time', (string) $seconds);
    }

    /**
     * @param  list<int>  $deviceIds
     * @return array<string, mixed>
     */
    public function generate(
        User $actor,
        string $type,
        array $deviceIds,
        Carbon $from,
        ?Carbon $to,
        bool $forExport = false,
        ?ReportFilters $filters = null,
    ): array {
        $this->locationCache = [];
        $this->statsCache = [];
        $this->sortedPointsCache = [];
        $this->maintenanceCache = null;
        $this->forExport = $forExport;
        $this->filters = $filters ?? ReportFilters::defaults();
        $this->locationLabels->clearCache();

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
                'meta' => [
                    'devices_requested' => $requestedCount,
                    'devices_in_report' => 0,
                    'filters' => $this->filters->toArray(),
                ],
            ];
        }

        $devices = Device::query()->whereIn('id', $ids)->get()->keyBy('id');
        $needsHistory = ! in_array($type, self::LIGHTWEIGHT_TYPES, true);
        if ($needsHistory) {
            $this->prefetchLocations($devices->values()->all(), $from, $to);
        }
        if (in_array($type, ['current_position', 'object_info'], true)) {
            // Positions live in Traccar (tc_positions) — never eager-load Eloquent latestLocation.
            $this->positionLoader->attachLatestToMany($devices);
        }

        $results = [];
        $positionsTruncated = false;
        $analyticsDownsampled = false;
        $skippedEmpty = 0;

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
                'trips_stops' => $this->tripsStopsReport($device, $from, $to, $type),
                'mileage' => $this->mileageReport($device, $from, $to, $type),
                'odometer' => $this->odometerReport($device, $from, $to),
                'diesel' => $this->dieselReport($device, $from, $to, $type),
                'summary' => $this->summaryReport($device, $from, $to, $type),
                'events' => $this->eventsReport($device, $from, $to),
                'overspeeds' => $this->overspeedsReport($device, $from, $to),
                'zone_inout' => $this->zoneInOutReport($device, $from, $to),
                'fuel_fillings' => $this->fuelFillingsReport($device, $from, $to),
                'current_position' => $this->currentPositionReport($device),
                'object_info' => $this->objectInfoReport($device),
                'service' => $this->serviceReport($device, $actor),
                'tasks' => $this->tasksReport($device, $actor, $from, $to),
                'speed' => $this->telemetrySeriesReport($device, $from, $to, 'speed'),
                'altitude' => $this->telemetrySeriesReport($device, $from, $to, 'altitude'),
                'ignition' => $this->ignitionChangesReport($device, $from, $to),
                default => $this->summaryReport($device, $from, $to, 'summary'),
            };

            $row = $this->enrichDeviceLocations($device, $type, $row);

            if (($row['positions_truncated'] ?? false) === true) {
                $positionsTruncated = true;
            }

            if (($row['analytics_downsampled'] ?? false) === true) {
                $analyticsDownsampled = true;
            }

            unset($row['analytics_downsampled']);

            if ($this->filters->ignoreEmpty && $this->isDeviceReportEmpty($type, $row)) {
                $skippedEmpty++;
            } else {
                $results[] = $row;
            }

            if (count($ids) > 1 && ! $this->forExport) {
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
                'devices_skipped_empty' => $skippedEmpty,
                'filters' => $this->filters->toArray(),
                'geocode_available' => $this->locationLabels->geocodeConfigured(),
                'location_labels_requested' => $this->filters->needsLocationLabels(),
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

            $lat = (float) $loc->lat;
            $lng = (float) $loc->lng;
            $positions[] = [
                'time' => AppDateTime::toApi($loc->recorded_at),
                'time_display' => app_datetime_format($loc->recorded_at),
                'lat' => $lat,
                'lng' => $lng,
                'maps_url' => $this->googleMapsUrl($lat, $lng),
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
        $stops = $this->decorateStopsWithMaps($stats['stops'] ?? []);
        $trips = array_map(
            fn (array $trip) => $this->enrichTrip($trip, $sorted, $stops),
            $this->tripsFromTimeline($sorted, $stats['timeline'] ?? [])
        );

        return array_merge($this->deviceMeta($device), [
            'trips' => $trips,
            'trip_count' => count($trips),
            'stop_count' => count($stops),
            'total_distance_km' => $stats['total_distance_km'] ?? 0,
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
        $stops = $this->decorateStopsWithMaps($stats['stops'] ?? []);

        return array_merge($this->deviceMeta($device), [
            'stops' => $stops,
            'stop_count' => count($stops),
            'analytics_downsampled' => $stats['analytics_downsampled'] ?? false,
        ]);
    }

    /**
     * Combined moving trips + stops timeline (with Google Maps links and stop durations).
     *
     * @return array<string, mixed>
     */
    private function tripsStopsReport(Device $device, Carbon $from, ?Carbon $to, string $reportType): array
    {
        return $this->composeTripsStops(
            $device,
            $this->loadLocationCollection($device, $from, $to),
            $from,
            $to,
        );
    }

    /**
     * Build trips/stops from an already-loaded GPS collection (History PDF with fallback routes).
     *
     * @param  Collection<int, DeviceLocation>  $locations
     * @param  array{attach_route_points?: bool, enrich_addresses?: bool}  $options
     * @return array<string, mixed>
     */
    public function tripsStopsFromLocations(
        Device $device,
        Collection $locations,
        ?ReportFilters $filters = null,
        array $options = [],
    ): array {
        $previousFilters = $this->filters;
        $previousExport = $this->forExport;
        $attachRoutePoints = (bool) ($options['attach_route_points'] ?? false);
        $enrichAddresses = (bool) ($options['enrich_addresses'] ?? false);

        $this->filters = $filters ?? new ReportFilters(
            showCoordinates: true,
            showAddresses: $enrichAddresses,
        );
        // Route polylines on every trip are heavy; PDF only needs aggregates + table rows.
        $this->forExport = $attachRoutePoints;
        $this->locationLabels->clearCache();
        unset($this->sortedPointsCache[$device->id]);
        // Avoid colliding with generate() stats cache keys for the same device.
        $this->statsCache = [];

        try {
            $from = now()->subSecond();
            $to = now();
            $row = $this->composeTripsStops($device, $locations, $from, $to);

            if ($enrichAddresses && $this->filters->needsLocationLabels()) {
                return $this->enrichDeviceLocations($device, 'trips_stops', $row);
            }

            return $row;
        } finally {
            $this->filters = $previousFilters;
            $this->forExport = $previousExport;
            unset($this->sortedPointsCache[$device->id]);
            $this->statsCache = [];
        }
    }

    /**
     * @param  Collection<int, DeviceLocation>  $collection
     * @return array<string, mixed>
     */
    private function composeTripsStops(
        Device $device,
        Collection $collection,
        Carbon $from,
        ?Carbon $to,
    ): array {
        $stats = $this->statsFor($device, $collection, 'trips', $from, $to);
        $sorted = $this->sortedPointsForDevice($device, $collection);
        $stops = $this->decorateStopsWithMaps($stats['stops'] ?? []);
        $trips = array_map(
            fn (array $trip) => $this->enrichTrip($trip, $sorted, $stops),
            $this->tripsFromTimeline($sorted, $stats['timeline'] ?? [])
        );

        $segments = [];
        foreach ($trips as $trip) {
            $segments[] = array_merge(['kind' => 'trip', 'kind_label' => (string) __('app.tracking.report_seg_trip')], $trip);
        }
        foreach ($stops as $stop) {
            $segments[] = array_merge(['kind' => 'stop', 'kind_label' => (string) __('app.tracking.report_seg_stop')], $stop);
        }

        // Sort by real timestamps — never by 12-hour display strings (12:04 AM vs 12:20 PM).
        usort($segments, function (array $a, array $b): int {
            return $this->reportSortTimestamp($a) <=> $this->reportSortTimestamp($b);
        });

        return array_merge($this->deviceMeta($device), [
            'trips' => $trips,
            'stops' => $stops,
            'segments' => $segments,
            'trip_count' => count($trips),
            'stop_count' => count($stops),
            'total_distance_km' => $stats['total_distance_km'] ?? 0,
            'moving_time_seconds' => $stats['moving_time_seconds'] ?? 0,
            'stopped_time_seconds' => $stats['stopped_time_seconds'] ?? 0,
            'analytics_downsampled' => $stats['analytics_downsampled'] ?? false,
        ]);
    }

    /**
     * Daily mileage breakdown for the selected range.
     *
     * @return array<string, mixed>
     */
    private function mileageReport(Device $device, Carbon $from, ?Carbon $to, string $reportType): array
    {
        $collection = $this->loadLocationCollection($device, $from, $to);
        $stats = $this->statsFor($device, $collection, 'summary', $from, $to);
        $sorted = $this->sortedPointsForDevice($device, $collection);
        $days = $this->dailyMileageFromPoints($sorted);

        return array_merge($this->deviceMeta($device), [
            'total_distance_km' => $stats['total_distance_km'] ?? 0,
            'moving_time_seconds' => $stats['moving_time_seconds'] ?? 0,
            'stopped_time_seconds' => $stats['stopped_time_seconds'] ?? 0,
            'trip_count' => $this->countTripsFromTimeline($stats['timeline'] ?? []),
            'stop_count' => $stats['stop_count'] ?? 0,
            'max_speed_kmh' => $stats['max_speed_kmh'] ?? 0,
            'average_speed_kmh' => $stats['average_speed_kmh'] ?? 0,
            'start_time' => $stats['start_time'] ?? null,
            'end_time' => $stats['end_time'] ?? null,
            'days' => $days,
            'day_count' => count($days),
            'analytics_downsampled' => $stats['analytics_downsampled'] ?? false,
        ]);
    }

    /**
     * Distance traveled in the selected range (odometer / trip distance report).
     *
     * @return array<string, mixed>
     */
    private function odometerReport(Device $device, Carbon $from, ?Carbon $to): array
    {
        $collection = $this->loadLocationCollection($device, $from, $to);
        $stats = $this->statsFor($device, $collection, 'summary', $from, $to);
        $sorted = $this->sortedPointsForDevice($device, $collection);

        $startOdo = null;
        $endOdo = null;
        $firstAt = null;
        $lastAt = null;

        foreach ($sorted as $point) {
            $at = $this->pointRecordedAt($point);
            if ($at) {
                if ($firstAt === null) {
                    $firstAt = $at;
                }
                $lastAt = $at;
            }

            $km = TelemetryFormatter::odometerKm($point->odometer ?? null);
            if ($km === null) {
                continue;
            }
            if ($startOdo === null) {
                $startOdo = $km;
            }
            $endOdo = $km;
        }

        // Prefer client baseline + GPS accumulation when set (matches live panel).
        // Single history scan for start+end (avoids loading baseline→end twice).
        $odometer = app(DeviceOdometerService::class);
        if ($odometer->hasBaseline($device)) {
            $startAt = $firstAt ?? $from;
            $endAt = $lastAt ?? ($to ?? now());
            $pair = $odometer->displayKmBetween($device, $startAt, $endAt);
            if ($pair['start'] !== null) {
                $startOdo = $pair['start'];
            }
            if ($pair['end'] !== null) {
                $endOdo = $pair['end'];
            }
        }

        // Full GPS path distance (corrected for downsampling in statsFor).
        $gpsDistanceKm = round((float) ($stats['total_distance_km'] ?? 0), 2);
        $odometerDelta = ($startOdo !== null && $endOdo !== null)
            ? round(max(0, $endOdo - $startOdo), 2)
            : null;

        // Prefer device odometer delta when present — matches dashboard odometer units
        // and avoids GPS jitter on dense tracks. Fall back to GPS path distance.
        $distanceKm = $odometerDelta !== null && $odometerDelta > 0
            ? $odometerDelta
            : $gpsDistanceKm;

        return array_merge($this->deviceMeta($device), [
            'total_distance_km' => $distanceKm,
            'gps_distance_km' => $gpsDistanceKm,
            'odometer_delta_km' => $odometerDelta,
            'start_odometer_km' => $startOdo,
            'end_odometer_km' => $endOdo,
            'moving_time_seconds' => (int) ($stats['moving_time_seconds'] ?? 0),
            // Keep ISO for window merging; UI formats for display.
            'start_time' => $firstAt
                ? app_datetime_api($firstAt)
                : ($stats['start_time'] ?? null),
            'end_time' => $lastAt
                ? app_datetime_api($lastAt)
                : ($stats['end_time'] ?? null),
            'start_time_display' => $firstAt ? app_datetime_format($firstAt) : null,
            'end_time_display' => $lastAt ? app_datetime_format($lastAt) : null,
            'point_count' => $collection->count(),
            'analytics_downsampled' => $stats['analytics_downsampled'] ?? false,
        ]);
    }

    /**
     * Diesel consumption: period totals, per-trip fuel, and daily breakdown.
     * Uses fuel-sensor drops when available; otherwise rate × distance.
     *
     * @return array<string, mixed>
     */
    private function dieselReport(Device $device, Carbon $from, ?Carbon $to, string $reportType): array
    {
        $collection = $this->loadLocationCollection($device, $from, $to);
        $stats = $this->statsFor($device, $collection, 'summary', $from, $to);
        $sorted = $this->sortedPointsForDevice($device, $collection);
        $settings = $this->fuel->settings($device);
        $distanceKm = (float) ($stats['total_distance_km'] ?? 0);
        $resolved = $this->fuel->resolveConsumption($device, $distanceKm, $sorted);

        $trips = array_map(
            function (array $trip) use ($device, $sorted): array {
                $tripDistance = (float) ($trip['distance_km'] ?? 0);
                $tripStart = $this->parseReportTime((string) ($trip['start_time'] ?? ''));
                $tripEnd = $this->parseReportTime((string) ($trip['end_time'] ?? ''));
                $slice = [];
                if ($tripStart && $tripEnd && $sorted !== []) {
                    $startIdx = $this->pointIndexAtOrAfter($sorted, $tripStart);
                    $endIdx = $this->pointIndexAtOrBefore($sorted, $tripEnd);
                    if ($startIdx !== null && $endIdx !== null && $endIdx >= $startIdx) {
                        $slice = array_slice($sorted, $startIdx, $endIdx - $startIdx + 1);
                    }
                }
                $fuel = $this->fuel->resolveConsumption($device, $tripDistance, $slice);

                return array_merge($trip, [
                    'distance_km' => round($tripDistance, 2),
                    'fuel_liters' => $fuel['fuel_liters'],
                    'fuel_method' => $fuel['method'],
                    'efficiency' => $fuel['efficiency'],
                    'efficiency_unit' => $fuel['efficiency_unit'],
                    'maps_url' => $trip['maps_url'] ?? $trip['start_maps_url'] ?? null,
                ]);
            },
            $this->tripsFromTimeline($sorted, $stats['timeline'] ?? [])
        );

        $days = [];
        foreach ($this->dailyMileageFromPoints($sorted) as $day) {
            $dayDistance = (float) ($day['distance_km'] ?? 0);
            $dayStart = $this->parseReportTime((string) ($day['start_time'] ?? ''));
            $dayEnd = $this->parseReportTime((string) ($day['end_time'] ?? ''));
            $slice = [];
            if ($dayStart && $dayEnd && $sorted !== []) {
                $startIdx = $this->pointIndexAtOrAfter($sorted, $dayStart);
                $endIdx = $this->pointIndexAtOrBefore($sorted, $dayEnd);
                if ($startIdx !== null && $endIdx !== null && $endIdx >= $startIdx) {
                    $slice = array_slice($sorted, $startIdx, $endIdx - $startIdx + 1);
                }
            }
            $fuel = $this->fuel->resolveConsumption($device, $dayDistance, $slice);
            $days[] = array_merge($day, [
                'fuel_liters' => $fuel['fuel_liters'],
                'fuel_method' => $fuel['method'],
                'efficiency' => $fuel['efficiency'],
                'efficiency_unit' => $fuel['efficiency_unit'],
            ]);
        }

        return array_merge($this->deviceMeta($device), [
            'total_distance_km' => round($distanceKm, 2),
            'fuel_liters' => $resolved['fuel_liters'],
            'fuel_method' => $resolved['method'],
            'fuel_method_label' => $this->fuelMethodLabel($resolved['method']),
            'efficiency' => $resolved['efficiency'],
            'efficiency_unit' => $resolved['efficiency_unit'],
            'efficiency_label' => $this->efficiencyUnitLabel($resolved['efficiency_unit']),
            'estimated_liters' => $resolved['estimated_liters'],
            'sensor_liters' => $resolved['sensor_liters'],
            'sensor_samples' => $resolved['sensor_samples'],
            'refill_count' => $resolved['refill_count'],
            'rate_l_per_100km' => $resolved['rate_l_per_100km'],
            'tank_capacity_l' => $settings['tank_capacity_l'],
            'moving_time_seconds' => $stats['moving_time_seconds'] ?? 0,
            'trip_count' => count($trips),
            'day_count' => count($days),
            'start_time' => $stats['start_time'] ?? null,
            'end_time' => $stats['end_time'] ?? null,
            'trips' => $trips,
            'days' => $days,
            'analytics_downsampled' => $stats['analytics_downsampled'] ?? false,
        ]);
    }

    private function fuelMethodLabel(string $method): string
    {
        return match ($method) {
            'sensor' => (string) __('app.tracking.report_fuel_method_sensor'),
            'estimated' => (string) __('app.tracking.report_fuel_method_estimated'),
            default => (string) __('app.tracking.report_fuel_method_unconfigured'),
        };
    }

    private function efficiencyUnitLabel(string $unit): string
    {
        return $unit === DeviceFuelService::UNIT_KM_PER_L
            ? (string) __('app.tracking.report_col_efficiency_km_l')
            : (string) __('app.tracking.report_col_efficiency_l_100');
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
            ->map(function (VehicleEvent $event) {
                $lat = $event->lat !== null ? (float) $event->lat : null;
                $lng = $event->lng !== null ? (float) $event->lng : null;

                return array_merge($event->toAlertArray(), [
                    'lat' => $lat,
                    'lng' => $lng,
                    'maps_url' => $this->googleMapsUrl($lat, $lng),
                ]);
            })
            ->values()
            ->all();

        return array_merge($this->deviceMeta($device), [
            'events' => $events,
            'event_count' => count($events),
        ]);
    }

    /**
     * Overspeed segments from GPS (Traccar “Overspeeds”).
     *
     * @return array<string, mixed>
     */
    private function overspeedsReport(Device $device, Carbon $from, ?Carbon $to): array
    {
        $collection = $this->loadLocationCollection($device, $from, $to);
        $sorted = $this->sortedPointsForDevice($device, $collection);
        $limit = $this->resolvedOverspeedLimitKmh($device);

        $segments = [];
        $open = null;

        foreach ($sorted as $point) {
            $spd = (float) ($point->speed ?? 0);
            $at = $this->pointRecordedAt($point);
            $lat = $point->lat !== null ? (float) $point->lat : null;
            $lng = $point->lng !== null ? (float) $point->lng : null;

            if ($spd > $limit) {
                if ($open === null) {
                    $open = [
                        'start_at' => $at,
                        'end_at' => $at,
                        'max_speed_kmh' => $spd,
                        'start_lat' => $lat,
                        'start_lng' => $lng,
                        'end_lat' => $lat,
                        'end_lng' => $lng,
                        'point_count' => 1,
                    ];
                } else {
                    $open['end_at'] = $at;
                    $open['max_speed_kmh'] = max((float) $open['max_speed_kmh'], $spd);
                    $open['end_lat'] = $lat;
                    $open['end_lng'] = $lng;
                    $open['point_count']++;
                }
                continue;
            }

            if ($open !== null) {
                $segments[] = $this->formatOverspeedSegment($open, $limit);
                $open = null;
            }
        }
        if ($open !== null) {
            $segments[] = $this->formatOverspeedSegment($open, $limit);
        }

        return array_merge($this->deviceMeta($device), [
            'overspeed_limit_kmh' => $limit,
            'overspeeds' => $segments,
            'overspeed_count' => count($segments),
        ]);
    }

    /**
     * @param  array<string, mixed>  $open
     * @return array<string, mixed>
     */
    private function formatOverspeedSegment(array $open, float $limit): array
    {
        /** @var Carbon|null $start */
        $start = $open['start_at'] instanceof Carbon ? $open['start_at'] : null;
        /** @var Carbon|null $end */
        $end = $open['end_at'] instanceof Carbon ? $open['end_at'] : null;
        $duration = ($start && $end) ? max(0, (int) $end->diffInSeconds($start)) : 0;

        return [
            'start_time' => $start ? app_datetime_format($start) : null,
            'end_time' => $end ? app_datetime_format($end) : null,
            'duration_seconds' => $duration,
            'max_speed_kmh' => round((float) $open['max_speed_kmh'], 1),
            'limit_kmh' => $limit,
            'start_lat' => $open['start_lat'],
            'start_lng' => $open['start_lng'],
            'end_lat' => $open['end_lat'],
            'end_lng' => $open['end_lng'],
            'maps_url' => $this->googleMapsUrl(
                $open['start_lat'] !== null ? (float) $open['start_lat'] : null,
                $open['start_lng'] !== null ? (float) $open['start_lng'] : null,
            ),
            'point_count' => (int) $open['point_count'],
        ];
    }

    /**
     * Geofence enter/exit (Traccar “Zone in/out”).
     *
     * @return array<string, mixed>
     */
    private function zoneInOutReport(Device $device, Carbon $from, ?Carbon $to): array
    {
        $full = $this->eventsReport($device, $from, $to);
        $events = array_values(array_filter(
            $full['events'] ?? [],
            function (array $event): bool {
                $type = strtolower((string) ($event['event_type'] ?? $event['type'] ?? ''));

                return in_array($type, [
                    VehicleEvent::TYPE_GEOFENCE_ENTER,
                    VehicleEvent::TYPE_GEOFENCE_EXIT,
                    'geofenceenter',
                    'geofenceexit',
                ], true);
            }
        ));

        return array_merge($this->deviceMeta($device), [
            'events' => $events,
            'event_count' => count($events),
            'zone_events' => $events,
            'zone_event_count' => count($events),
        ]);
    }

    /**
     * Fuel fillings detected from sensor rises (Traccar “Fuel fillings”).
     *
     * @return array<string, mixed>
     */
    private function fuelFillingsReport(Device $device, Carbon $from, ?Carbon $to): array
    {
        $collection = $this->loadLocationCollection($device, $from, $to);
        $sorted = $this->sortedPointsForDevice($device, $collection);
        $fillings = [];
        $prevLiters = null;

        foreach ($sorted as $point) {
            $liters = $this->fuel->readingToLiters($point->fuel ?? null, $device);
            if ($liters === null) {
                continue;
            }
            if ($prevLiters === null) {
                $prevLiters = $liters;
                continue;
            }

            $rise = $liters - $prevLiters;
            if ($rise >= DeviceFuelService::MIN_REFILL_L) {
                $lat = $point->lat !== null ? (float) $point->lat : null;
                $lng = $point->lng !== null ? (float) $point->lng : null;
                $fillings[] = [
                    'time' => app_datetime_format($point->recorded_at),
                    'liters' => round($rise, 2),
                    'level_before' => round($prevLiters, 2),
                    'level_after' => round($liters, 2),
                    'lat' => $lat,
                    'lng' => $lng,
                    'maps_url' => $this->googleMapsUrl($lat, $lng),
                ];
            }
            $prevLiters = $liters;
        }

        return array_merge($this->deviceMeta($device), [
            'fillings' => $fillings,
            'filling_count' => count($fillings),
            'total_filled_liters' => round(array_sum(array_map(
                fn (array $f) => (float) ($f['liters'] ?? 0),
                $fillings
            )), 2),
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function currentPositionReport(Device $device): array
    {
        $loc = $device->latestLocation;
        if (! $loc) {
            return array_merge($this->deviceMeta($device), [
                'has_position' => false,
                'time' => null,
                'lat' => null,
                'lng' => null,
                'maps_url' => null,
                'speed' => null,
                'heading' => null,
                'altitude' => null,
                'ignition' => null,
                'status' => (string) __('app.map.status_offline'),
                'status_key' => 'offline',
            ]);
        }

        $lat = $loc->lat !== null ? (float) $loc->lat : null;
        $lng = $loc->lng !== null ? (float) $loc->lng : null;
        $age = $loc->recorded_at ? (int) $loc->recorded_at->diffInSeconds(now()) : null;
        $resolved = VehicleStatusSpec::resolve(
            $age,
            (float) ($loc->speed ?? 0),
            (bool) ($loc->ignition ?? false),
        );

        return array_merge($this->deviceMeta($device), [
            'has_position' => $lat !== null && $lng !== null,
            'time' => app_datetime_format($loc->recorded_at),
            'lat' => $lat,
            'lng' => $lng,
            'maps_url' => $this->googleMapsUrl($lat, $lng),
            'speed' => $loc->speed !== null ? round((float) $loc->speed, 1) : null,
            'heading' => $loc->heading !== null ? round((float) $loc->heading, 1) : null,
            'altitude' => $loc->altitude !== null ? round((float) $loc->altitude, 1) : null,
            'ignition' => $loc->ignition,
            'status' => $resolved['label'],
            'status_key' => $resolved['key'],
            'connectivity_tier' => $resolved['tier'],
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function objectInfoReport(Device $device): array
    {
        $current = $this->currentPositionReport($device);

        $odometer = $device->latestLocation?->odometer;

        return array_merge($this->deviceMeta($device), [
            'imei' => (string) ($device->imei ?? ''),
            'model' => (string) ($device->vehicle_model ?? $device->model ?? ''),
            'phone' => (string) ($device->driverContactNumber() ?? ''),
            'status' => $current['status'] ?? null,
            'status_key' => $current['status_key'] ?? null,
            'connectivity_tier' => $current['connectivity_tier'] ?? null,
            'last_update' => $current['time'] ?? null,
            'lat' => $current['lat'] ?? null,
            'lng' => $current['lng'] ?? null,
            'maps_url' => $current['maps_url'] ?? null,
            'speed' => $current['speed'] ?? null,
            'ignition' => $current['ignition'] ?? null,
            'odometer_km' => $odometer !== null ? round(((float) $odometer) / 1000, 1) : null,
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function serviceReport(Device $device, User $actor): array
    {
        $this->maintenanceCache ??= $this->maintenance->listForActor($actor);
        $items = [];
        foreach ($this->maintenanceCache as $row) {
            $objectIds = $row['object_ids'] ?? [];
            if (! in_array((int) $device->id, array_map('intval', $objectIds), true)) {
                continue;
            }
            $items[] = [
                'name' => (string) ($row['name'] ?? ''),
                'summary' => (string) ($row['summary'] ?? ''),
                'status' => (string) ($row['status'] ?? ''),
                'current_odometer_label' => $row['current_odometer_label'] ?? null,
                'odometer_left_label' => $row['odometer_left_label'] ?? null,
                'days_left_label' => $row['days_left_label'] ?? null,
            ];
        }

        return array_merge($this->deviceMeta($device), [
            'services' => $items,
            'service_count' => count($items),
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function tasksReport(Device $device, User $actor, Carbon $from, ?Carbon $to): array
    {
        $rows = $this->tasks->listForActor($actor, [
            'device_id' => $device->id,
            'from' => $from->toDateTimeString(),
            'to' => ($to ?? now())->toDateTimeString(),
        ]);

        $tasks = array_map(static function (array $task): array {
            return [
                'name' => (string) ($task['name'] ?? ''),
                'start' => (string) ($task['start'] ?? ''),
                'destination' => (string) ($task['destination'] ?? ''),
                'priority' => (string) ($task['priority'] ?? ''),
                'status' => (string) ($task['status'] ?? ''),
                'time_from' => (string) ($task['time_from'] ?? ''),
                'time_to' => (string) ($task['time_to'] ?? ''),
            ];
        }, $rows);

        return array_merge($this->deviceMeta($device), [
            'tasks' => $tasks,
            'task_count' => count($tasks),
        ]);
    }

    /**
     * Tabular series for Speed / Altitude graphical-style reports.
     *
     * @return array<string, mixed>
     */
    private function telemetrySeriesReport(Device $device, Carbon $from, ?Carbon $to, string $metric): array
    {
        $collection = $this->loadLocationCollection($device, $from, $to);
        $sorted = $this->sortedPointsForDevice($device, $collection);
        $cap = $this->forExport
            ? self::MAX_POSITIONS_EXPORT_PER_DEVICE
            : self::MAX_POSITIONS_WEB_PER_DEVICE;
        $step = count($sorted) <= $cap ? 1 : (int) ceil(count($sorted) / $cap);
        $series = [];

        for ($i = 0; $i < count($sorted); $i += $step) {
            $point = $sorted[$i];
            $lat = $point->lat !== null ? (float) $point->lat : null;
            $lng = $point->lng !== null ? (float) $point->lng : null;
            $value = $metric === 'altitude'
                ? ($point->altitude !== null ? round((float) $point->altitude, 1) : null)
                : ($point->speed !== null ? round((float) $point->speed, 1) : null);

            $series[] = [
                'time' => app_datetime_format($point->recorded_at),
                'value' => $value,
                'lat' => $lat,
                'lng' => $lng,
                'maps_url' => $this->googleMapsUrl($lat, $lng),
            ];
        }

        return array_merge($this->deviceMeta($device), [
            'metric' => $metric,
            'series' => $series,
            'point_count' => count($series),
            'positions_truncated' => count($sorted) > count($series),
        ]);
    }

    /**
     * Ignition on/off change log (Traccar “Ignition” graphical counterpart as table).
     *
     * @return array<string, mixed>
     */
    private function ignitionChangesReport(Device $device, Carbon $from, ?Carbon $to): array
    {
        $collection = $this->loadLocationCollection($device, $from, $to);
        $sorted = $this->sortedPointsForDevice($device, $collection);
        $changes = [];
        $prev = null;

        foreach ($sorted as $point) {
            if ($point->ignition === null) {
                continue;
            }
            $on = (bool) $point->ignition;
            if ($prev !== null && $prev === $on) {
                continue;
            }
            $lat = $point->lat !== null ? (float) $point->lat : null;
            $lng = $point->lng !== null ? (float) $point->lng : null;
            $changes[] = [
                'time' => app_datetime_format($point->recorded_at),
                'ignition' => $on,
                'lat' => $lat,
                'lng' => $lng,
                'maps_url' => $this->googleMapsUrl($lat, $lng),
            ];
            $prev = $on;
        }

        return array_merge($this->deviceMeta($device), [
            'changes' => $changes,
            'change_count' => count($changes),
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

        if ($type === 'trips' || $type === 'trips_stops') {
            return [
                'device_count' => count($devices),
                'trip_count' => array_sum(array_map(
                    fn (array $d) => (int) ($d['trip_count'] ?? 0),
                    $devices
                )),
                'stop_count' => array_sum(array_map(
                    fn (array $d) => (int) ($d['stop_count'] ?? 0),
                    $devices
                )),
                'total_distance_km' => round(array_sum(array_map(
                    fn (array $d) => (float) ($d['total_distance_km'] ?? 0),
                    $devices
                )), 2),
            ];
        }

        if ($type === 'mileage') {
            return [
                'device_count' => count($devices),
                'total_distance_km' => round(array_sum(array_map(
                    fn (array $d) => (float) ($d['total_distance_km'] ?? 0),
                    $devices
                )), 2),
                'day_count' => array_sum(array_map(
                    fn (array $d) => (int) ($d['day_count'] ?? 0),
                    $devices
                )),
                'trip_count' => array_sum(array_map(
                    fn (array $d) => (int) ($d['trip_count'] ?? 0),
                    $devices
                )),
            ];
        }

        if ($type === 'odometer') {
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
            ];
        }

        if ($type === 'diesel') {
            $totalDistance = round(array_sum(array_map(
                fn (array $d) => (float) ($d['total_distance_km'] ?? 0),
                $devices
            )), 2);
            $totalFuel = round(array_sum(array_map(
                fn (array $d) => (float) ($d['fuel_liters'] ?? 0),
                $devices
            )), 2);

            return [
                'device_count' => count($devices),
                'total_distance_km' => $totalDistance,
                'fuel_liters' => $totalFuel,
                'trip_count' => array_sum(array_map(
                    fn (array $d) => (int) ($d['trip_count'] ?? 0),
                    $devices
                )),
                'day_count' => array_sum(array_map(
                    fn (array $d) => (int) ($d['day_count'] ?? 0),
                    $devices
                )),
                'efficiency' => $totalDistance > 0 && $totalFuel > 0
                    ? round(($totalFuel / $totalDistance) * 100, 2)
                    : 0,
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

        if ($type === 'overspeeds') {
            return [
                'device_count' => count($devices),
                'overspeed_count' => array_sum(array_map(
                    fn (array $d) => (int) ($d['overspeed_count'] ?? 0),
                    $devices
                )),
            ];
        }

        if ($type === 'zone_inout') {
            return [
                'device_count' => count($devices),
                'event_count' => array_sum(array_map(
                    fn (array $d) => (int) ($d['zone_event_count'] ?? $d['event_count'] ?? 0),
                    $devices
                )),
            ];
        }

        if ($type === 'fuel_fillings') {
            return [
                'device_count' => count($devices),
                'filling_count' => array_sum(array_map(
                    fn (array $d) => (int) ($d['filling_count'] ?? 0),
                    $devices
                )),
                'total_filled_liters' => round(array_sum(array_map(
                    fn (array $d) => (float) ($d['total_filled_liters'] ?? 0),
                    $devices
                )), 2),
            ];
        }

        if (in_array($type, ['speed', 'altitude'], true)) {
            return [
                'device_count' => count($devices),
                'point_count' => array_sum(array_map(
                    fn (array $d) => (int) ($d['point_count'] ?? 0),
                    $devices
                )),
            ];
        }

        if ($type === 'ignition') {
            return [
                'device_count' => count($devices),
                'change_count' => array_sum(array_map(
                    fn (array $d) => (int) ($d['change_count'] ?? 0),
                    $devices
                )),
            ];
        }

        if ($type === 'service') {
            return [
                'device_count' => count($devices),
                'service_count' => array_sum(array_map(
                    fn (array $d) => (int) ($d['service_count'] ?? 0),
                    $devices
                )),
            ];
        }

        if ($type === 'tasks') {
            return [
                'device_count' => count($devices),
                'task_count' => array_sum(array_map(
                    fn (array $d) => (int) ($d['task_count'] ?? 0),
                    $devices
                )),
            ];
        }

        if (in_array($type, ['current_position', 'object_info'], true)) {
            return ['device_count' => count($devices)];
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

        $end = $to ?? now();
        $spanDays = max(1, (int) ceil(max(1, $from->diffInRealSeconds($end)) / 86400));

        // Long ranges: load calendar-day slices so MySQL/Traccar stays within
        // query budgets and peak memory stays lower than one giant week pull.
        if ($spanDays > self::PREFETCH_DAY_CHUNK_THRESHOLD) {
            $tz = (string) config('app.timezone', 'Asia/Riyadh');
            $cursor = $from->copy()->timezone($tz)->startOfDay();
            $last = $end->copy()->timezone($tz)->endOfDay();
            $merged = [];
            foreach ($missing as $device) {
                $merged[$device->id] = collect();
            }

            while ($cursor->lte($last)) {
                $dayEnd = $cursor->copy()->endOfDay();
                if ($dayEnd->greaterThan($last)) {
                    $dayEnd = $last->copy();
                }

                $batch = $this->positions->historyForDevices($missing, $cursor, $dayEnd, 'asc');
                foreach ($missing as $device) {
                    $slice = $batch[$device->id] ?? collect();
                    if ($slice->isNotEmpty()) {
                        $merged[$device->id] = $merged[$device->id]->concat($slice);
                    }
                }

                // Keep the request alive on long multi-day pulls.
                if (function_exists('set_time_limit')) {
                    @set_time_limit(120);
                }

                $cursor = $cursor->copy()->addDay()->startOfDay();
            }

            foreach ($missing as $device) {
                $this->locationCache[$this->locationCacheKey($device->id, $rangeKey)] =
                    ($merged[$device->id] ?? collect())->values();
            }

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
            'stop_min_seconds' => $this->filters->stopMinSeconds,
            'overspeed_kmh' => $this->resolvedOverspeedLimitKmh($device),
        ]);

        // Always correct distance + max speed from the full GPS collection when
        // analytics ran on a downsampled set — keeps week reports accurate.
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
            (string) $this->filters->stopMinSeconds,
            (string) ($this->filters->speedLimitKmh ?? 'default'),
        ]);
    }

    /**
     * @return Collection<int, DeviceLocation>
     */
    private function collectionForAnalytics(Collection $collection, string $reportType): Collection
    {
        $count = $collection->count();
        $isSummaryLike = $reportType === 'summary'
            || $reportType === 'route'
            || $reportType === 'mileage'
            || $reportType === 'diesel';
        $max = $this->forExport
            ? ($isSummaryLike ? self::MAX_SUMMARY_FULL_POINTS : self::MAX_ANALYTICS_POINTS)
            : ($isSummaryLike ? self::MAX_SUMMARY_WEB_POINTS : self::MAX_ANALYTICS_POINTS_WEB);

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
        $stopMin = $this->filters->stopMinSeconds;
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
        $stopMin = $this->filters->stopMinSeconds;
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

        $startIso = (string) ($first['start'] ?? '');
        $endIso = (string) ($last['end'] ?? '');
        $startDisplay = (string) ($first['start_display'] ?? '');
        $endDisplay = (string) ($last['end_display'] ?? '');

        return [
            // ISO for sorting / parsing; *_display / legacy start_time for UI.
            'start' => $startIso,
            'end' => $endIso,
            'start_time' => $startDisplay !== '' ? $startDisplay : $startIso,
            'end_time' => $endDisplay !== '' ? $endDisplay : $endIso,
            'start_display' => $startDisplay !== '' ? $startDisplay : $startIso,
            'end_display' => $endDisplay !== '' ? $endDisplay : $endIso,
            'start_time_display' => $startDisplay !== '' ? $startDisplay : $startIso,
            'end_time_display' => $endDisplay !== '' ? $endDisplay : $endIso,
            'start_lat' => $first['start_lat'] ?? null,
            'start_lng' => $first['start_lng'] ?? null,
            'end_lat' => $last['end_lat'] ?? null,
            'end_lng' => $last['end_lng'] ?? null,
            'start_maps_url' => $this->googleMapsUrl(
                isset($first['start_lat']) ? (float) $first['start_lat'] : null,
                isset($first['start_lng']) ? (float) $first['start_lng'] : null,
            ),
            'end_maps_url' => $this->googleMapsUrl(
                isset($last['end_lat']) ? (float) $last['end_lat'] : null,
                isset($last['end_lng']) ? (float) $last['end_lng'] : null,
            ),
            'maps_url' => $this->googleMapsUrl(
                isset($first['start_lat']) ? (float) $first['start_lat'] : null,
                isset($first['start_lng']) ? (float) $first['start_lng'] : null,
            ),
            'distance_km' => $distance,
            'duration_seconds' => $duration > 0 ? $duration : $movingSec,
            'moving_time_seconds' => $movingSec,
            'max_speed_kmh' => round($maxSpeed, 1),
            'average_speed_kmh' => $avgSpeed,
        ];
    }

    /**
     * Attach Google Maps links, in-trip stops, and a downsampled route polyline to a trip row.
     *
     * @param  array<string, mixed>  $trip
     * @param  list<DeviceLocation>  $sortedPoints
     * @param  list<array<string, mixed>>  $stops
     * @return array<string, mixed>
     */
    private function enrichTrip(array $trip, array $sortedPoints, array $stops): array
    {
        $startLat = isset($trip['start_lat']) ? (float) $trip['start_lat'] : null;
        $startLng = isset($trip['start_lng']) ? (float) $trip['start_lng'] : null;
        $endLat = isset($trip['end_lat']) ? (float) $trip['end_lat'] : null;
        $endLng = isset($trip['end_lng']) ? (float) $trip['end_lng'] : null;

        $trip['start_maps_url'] = $trip['start_maps_url'] ?? $this->googleMapsUrl($startLat, $startLng);
        $trip['end_maps_url'] = $trip['end_maps_url'] ?? $this->googleMapsUrl($endLat, $endLng);
        $trip['maps_url'] = $trip['maps_url'] ?? $trip['start_maps_url'];

        $tripStart = $this->parseReportTime((string) ($trip['start'] ?? $trip['start_time'] ?? ''));
        $tripEnd = $this->parseReportTime((string) ($trip['end'] ?? $trip['end_time'] ?? ''));

        $tripStops = [];
        if ($tripStart && $tripEnd) {
            foreach ($stops as $stop) {
                $stopStart = $this->parseReportTime((string) ($stop['start'] ?? $stop['start_display'] ?? ''));
                if (! $stopStart) {
                    continue;
                }
                if ($stopStart->greaterThanOrEqualTo($tripStart) && $stopStart->lessThanOrEqualTo($tripEnd)) {
                    $tripStops[] = $stop;
                }
            }
        }
        $trip['stops'] = $tripStops;
        $trip['stop_count'] = count($tripStops);

        // Polyline payloads are heavy for multi-vehicle week reports; keep for exports only.
        if ($this->forExport && $tripStart && $tripEnd && $sortedPoints !== []) {
            $startIdx = $this->pointIndexAtOrAfter($sortedPoints, $tripStart);
            $endIdx = $this->pointIndexAtOrBefore($sortedPoints, $tripEnd);
            if ($startIdx !== null && $endIdx !== null && $endIdx >= $startIdx) {
                $slice = array_slice($sortedPoints, $startIdx, $endIdx - $startIdx + 1);
                $routePoints = $this->downsampleRoutePoints($slice, 200);
                $trip['route_points'] = $routePoints;
                $trip['route_point_count'] = count($routePoints);
            } else {
                $trip['route_points'] = [];
                $trip['route_point_count'] = 0;
            }
        } else {
            $trip['route_points'] = [];
            $trip['route_point_count'] = 0;
        }

        return $trip;
    }

    /**
     * @param  list<array<string, mixed>>  $stops
     * @return list<array<string, mixed>>
     */
    private function decorateStopsWithMaps(array $stops): array
    {
        return array_map(function (array $stop): array {
            $lat = isset($stop['lat']) ? (float) $stop['lat'] : null;
            $lng = isset($stop['lng']) ? (float) $stop['lng'] : null;
            $stop['maps_url'] = $this->googleMapsUrl($lat, $lng);

            return $stop;
        }, $stops);
    }

    private function resolvedOverspeedLimitKmh(Device $device): float
    {
        if ($this->filters->speedLimitKmh !== null && $this->filters->speedLimitKmh > 0) {
            return (float) $this->filters->speedLimitKmh;
        }

        $settings = $this->trackingSettings->forDevice($device);
        $limit = (float) ($settings['overspeed_kmh'] ?? config('tracking.overspeed_kmh', HistoryAnalyticsService::OVERSPEED_KMH));

        return $limit > 0 ? $limit : (float) HistoryAnalyticsService::OVERSPEED_KMH;
    }

    /**
     * @param  array<string, mixed>  $row
     * @return array<string, mixed>
     */
    private function enrichDeviceLocations(Device $device, string $type, array $row): array
    {
        if (! $this->filters->needsLocationLabels()) {
            return $row;
        }

        if (isset($row['stops']) && is_array($row['stops'])) {
            $row['stops'] = array_map(
                fn (array $stop) => $this->locationLabels->enrichPointRow($stop, $device, $this->filters),
                $row['stops']
            );
        }

        if (isset($row['trips']) && is_array($row['trips'])) {
            $row['trips'] = array_map(function (array $trip) use ($device): array {
                $start = $this->locationLabels->resolve(
                    isset($trip['start_lat']) ? (float) $trip['start_lat'] : null,
                    isset($trip['start_lng']) ? (float) $trip['start_lng'] : null,
                    $device,
                    $this->filters,
                );
                $end = $this->locationLabels->resolve(
                    isset($trip['end_lat']) ? (float) $trip['end_lat'] : null,
                    isset($trip['end_lng']) ? (float) $trip['end_lng'] : null,
                    $device,
                    $this->filters,
                );
                $trip['start_address'] = $start['location_label'];
                $trip['end_address'] = $end['location_label'];
                $trip['address'] = $start['location_label'];
                if (isset($trip['stops']) && is_array($trip['stops'])) {
                    $trip['stops'] = array_map(
                        fn (array $stop) => $this->locationLabels->enrichPointRow($stop, $device, $this->filters),
                        $trip['stops']
                    );
                }

                return $trip;
            }, $row['trips']);
        }

        if (isset($row['segments']) && is_array($row['segments'])) {
            $row['segments'] = array_map(function (array $seg) use ($device): array {
                if (($seg['kind'] ?? '') === 'trip') {
                    $start = $this->locationLabels->resolve(
                        isset($seg['start_lat']) ? (float) $seg['start_lat'] : null,
                        isset($seg['start_lng']) ? (float) $seg['start_lng'] : null,
                        $device,
                        $this->filters,
                    );
                    $seg['address'] = $start['location_label'];
                    $seg['start_address'] = $start['location_label'];

                    return $seg;
                }

                return $this->locationLabels->enrichPointRow($seg, $device, $this->filters);
            }, $row['segments']);
        }

        foreach (['events', 'zone_events', 'fillings', 'overspeeds', 'positions'] as $listKey) {
            if (! isset($row[$listKey]) || ! is_array($row[$listKey])) {
                continue;
            }

            $max = $listKey === 'positions'
                ? ($this->forExport ? 500 : 120)
                : 5000;
            $count = 0;
            $row[$listKey] = array_map(function (array $item) use ($device, $listKey, &$count, $max): array {
                if ($count >= $max) {
                    return $item;
                }
                $count++;

                if ($listKey === 'overspeeds') {
                    $resolved = $this->locationLabels->resolve(
                        isset($item['start_lat']) ? (float) $item['start_lat'] : null,
                        isset($item['start_lng']) ? (float) $item['start_lng'] : null,
                        $device,
                        $this->filters,
                    );
                    $item['address'] = $resolved['location_label'];
                    $item['location_label'] = $resolved['location_label'];

                    return $item;
                }

                return $this->locationLabels->enrichPointRow($item, $device, $this->filters);
            }, $row[$listKey]);
        }

        if ($type === 'current_position') {
            $row = $this->locationLabels->enrichPointRow($row, $device, $this->filters);
        }

        return $row;
    }

    /**
     * @param  array<string, mixed>  $row
     */
    private function isDeviceReportEmpty(string $type, array $row): bool
    {
        return match ($type) {
            'trips' => ((int) ($row['trip_count'] ?? count($row['trips'] ?? []))) === 0,
            'stops' => ((int) ($row['stop_count'] ?? count($row['stops'] ?? []))) === 0,
            'trips_stops' => ((int) ($row['trip_count'] ?? 0)) === 0 && ((int) ($row['stop_count'] ?? 0)) === 0,
            'events' => ((int) ($row['event_count'] ?? count($row['events'] ?? []))) === 0,
            'overspeeds' => ((int) ($row['overspeed_count'] ?? count($row['overspeeds'] ?? []))) === 0,
            'zone_inout' => count($row['zone_events'] ?? $row['events'] ?? []) === 0,
            'fuel_fillings' => count($row['fillings'] ?? []) === 0,
            'positions' => ((int) ($row['point_count'] ?? count($row['positions'] ?? []))) === 0,
            'route' => ((int) ($row['point_count'] ?? 0)) === 0,
            'mileage' => count($row['days'] ?? []) === 0 || (float) ($row['total_distance_km'] ?? 0) <= 0,
            'summary' => (float) ($row['total_distance_km'] ?? 0) <= 0
                && ((int) ($row['trip_count'] ?? 0)) === 0
                && ((int) ($row['stop_count'] ?? 0)) === 0,
            'odometer' => (float) ($row['total_distance_km'] ?? 0) <= 0
                && (float) ($row['odometer_delta_km'] ?? 0) <= 0,
            'diesel' => (float) ($row['total_distance_km'] ?? 0) <= 0,
            'speed', 'altitude' => count($row['series'] ?? []) === 0,
            'ignition' => count($row['changes'] ?? []) === 0,
            'service' => count($row['services'] ?? []) === 0,
            'tasks' => count($row['tasks'] ?? []) === 0,
            'current_position' => ($row['lat'] ?? null) === null || ($row['lng'] ?? null) === null,
            default => false,
        };
    }

    private function googleMapsUrl(?float $lat, ?float $lng): ?string
    {
        if ($lat === null || $lng === null) {
            return null;
        }
        if (! is_finite($lat) || ! is_finite($lng)) {
            return null;
        }
        if (abs($lat) > 90 || abs($lng) > 180 || ($lat == 0.0 && $lng == 0.0)) {
            return null;
        }

        return 'https://www.google.com/maps/search/?api=1&query='
            .rawurlencode(number_format($lat, 6, '.', '').','.number_format($lng, 6, '.', ''));
    }

    private function parseReportTime(string $value): ?Carbon
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

    /**
     * Epoch seconds for chronological report ordering.
     * Prefers ISO/API `start` so 12-hour AM/PM display strings never drive sort order.
     *
     * @param  array<string, mixed>  $row
     */
    private function reportSortTimestamp(array $row): int
    {
        foreach (['start', 'start_time', 'start_display', 'start_time_display'] as $key) {
            $raw = trim((string) ($row[$key] ?? ''));
            if ($raw === '') {
                continue;
            }
            $dt = $this->parseReportTime($raw);
            if ($dt !== null) {
                return $dt->getTimestamp();
            }
        }

        return 0;
    }

    /**
     * @param  list<DeviceLocation>  $points
     * @return list<array{lat: float, lng: float, speed: float|null, recorded_at: string|null}>
     */
    private function downsampleRoutePoints(array $points, int $max): array
    {
        $count = count($points);
        if ($count === 0) {
            return [];
        }

        $step = $count <= $max ? 1 : (int) ceil($count / $max);
        $out = [];
        for ($i = 0; $i < $count; $i += $step) {
            $p = $points[$i];
            $out[] = [
                'lat' => (float) $p->lat,
                'lng' => (float) $p->lng,
                'speed' => $p->speed !== null ? (float) $p->speed : null,
                'recorded_at' => $p->recorded_at ? app_datetime_api($p->recorded_at) : null,
            ];
        }

        $last = $points[$count - 1];
        $lastRow = [
            'lat' => (float) $last->lat,
            'lng' => (float) $last->lng,
            'speed' => $last->speed !== null ? (float) $last->speed : null,
            'recorded_at' => $last->recorded_at ? app_datetime_api($last->recorded_at) : null,
        ];
        if ($out === [] || $out[count($out) - 1]['recorded_at'] !== $lastRow['recorded_at']) {
            $out[] = $lastRow;
        }

        return $out;
    }

    /**
     * @param  list<DeviceLocation>  $sortedPoints
     * @return list<array<string, mixed>>
     */
    private function dailyMileageFromPoints(array $sortedPoints): array
    {
        if ($sortedPoints === []) {
            return [];
        }

        $tz = (string) config('app.timezone', 'UTC');
        /** @var array<string, list<DeviceLocation>> $byDay */
        $byDay = [];

        foreach ($sortedPoints as $point) {
            $at = $this->pointRecordedAt($point);
            if (! $at) {
                continue;
            }
            $day = $at->copy()->timezone($tz)->toDateString();
            $byDay[$day][] = $point;
        }

        ksort($byDay);
        $rows = [];
        foreach ($byDay as $date => $points) {
            $dist = $this->historyAnalytics->distanceKmForPoints(collect($points));
            $first = $points[0] ?? null;
            $last = $points[count($points) - 1] ?? null;
            $firstAt = $first ? $this->pointRecordedAt($first) : null;
            $lastAt = $last ? $this->pointRecordedAt($last) : null;
            $duration = ($firstAt && $lastAt) ? max(0, (int) $firstAt->diffInSeconds($lastAt)) : 0;
            $startLat = $first ? (float) $first->lat : null;
            $startLng = $first ? (float) $first->lng : null;
            $endLat = $last ? (float) $last->lat : null;
            $endLng = $last ? (float) $last->lng : null;

            $rows[] = [
                'date' => $date,
                'distance_km' => round($dist, 2),
                'point_count' => count($points),
                'duration_seconds' => $duration,
                'start_time' => $firstAt ? app_datetime_api($firstAt) : null,
                'end_time' => $lastAt ? app_datetime_api($lastAt) : null,
                'start_lat' => $startLat,
                'start_lng' => $startLng,
                'end_lat' => $endLat,
                'end_lng' => $endLng,
                'start_maps_url' => $this->googleMapsUrl($startLat, $startLng),
                'end_maps_url' => $this->googleMapsUrl($endLat, $endLng),
                'maps_url' => $this->googleMapsUrl($startLat, $startLng),
            ];
        }

        return $rows;
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
