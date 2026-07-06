<?php

namespace App\Services\Tracking;

use App\Contracts\Tracking\EventReaderInterface;
use App\Models\Device;
use App\Models\DeviceLocation;
use App\Models\User;
use App\Models\VehicleEvent;
use App\Services\Authorization\RbacService;
use App\Services\Authorization\TenantScopeService;
use App\Services\Mobile\MobileMapStatusResolver;
use App\Services\Mobile\VehicleStatusSpec;
use App\Services\Traccar\TraccarTrackingGate;
use App\Support\DateTime\AppDateTime;
use App\Support\Tracking\DeviceLocationPayload;
use App\Support\Tracking\TelemetryFormatter;
use App\Services\Tracking\StatusDurationResolver;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;

/**
 * Role-scoped vehicle lists and live/history payloads for the Global Tracking module.
 * Reads only from existing Traccar-backed models — no custom tracking tables.
 */
class GlobalTrackingService
{
    public const MAX_LIVE_DEVICES = 100;

    /** Distinct route colors when multiple vehicles are shown on the history map. */
    public const MULTI_VEHICLE_COLORS = [
        '#2563eb',
        '#dc2626',
        '#7c3aed',
        '#059669',
        '#d97706',
        '#db2777',
        '#0891b2',
        '#4f46e5',
        '#65a30d',
        '#ea580c',
    ];

    public function __construct(
        private DevicePositionLoader $positionLoader,
        private DeviceHistoryFetcher $historyFetcher,
        private EventReaderInterface $events,
        private MobileMapStatusResolver $mapStatus,
        private StatusDurationResolver $statusDuration,
        private TenantScopeService $tenantScope,
        private RbacService $rbac,
        private \App\Services\Mobile\MobileRouteAnalyticsService $analytics,
        private HistoryEventsCompiler $historyEvents,
        private \App\Services\Routes\TripManagementService $tripManagement,
        private DriverMapInfoService $driverMapInfo,
        private TrackingUiPermissions $trackingUi,
        private TraccarTrackingGate $trackingGate,
    ) {}

    /**
     * Traccar-style bottom info panel for one device.
     *
     * @param  list<string>|null  $sections  core, stats, events, graph, route_trip — default excludes route_trip (load via sections=route_trip).
     * @return array<string, mixed>|null
     */
    public function devicePanelData(User $actor, int $deviceId, ?array $sections = null, bool $freshStats = false): ?array
    {
        if (! in_array($deviceId, $this->filterAllowedIds($actor, [$deviceId]), true)) {
            return null;
        }

        $device = Device::query()->find($deviceId);
        if (! $device) {
            return null;
        }

        $sections = $sections ?? ['core', 'stats', 'events', 'graph'];
        $sections = array_values(array_unique(array_map('strtolower', $sections)));

        if ($sections === ['route_trip']) {
            $this->positionLoader->attachLatestToMany(collect([$device]));
            $latest = $device->latestLocation;

            return [
                'id' => $device->id,
                'route_trip' => $this->routeTripPayloadForActor($actor, $device, $latest),
            ];
        }

        $panel = [];

        if (in_array('core', $sections, true)) {
            $panel = array_merge($panel, $this->devicePanelCore($actor, $device));
        }

        if (in_array('stats', $sections, true)) {
            $panel['stats'] = $this->devicePanelTodayStats($device, $freshStats);
        }

        if (in_array('events', $sections, true)) {
            $panel['events'] = $this->devicePanelRecentEvents($device);
        }

        if (in_array('graph', $sections, true)) {
            $panel['positions'] = $this->devicePanelGraphPositions($device);
        }

        if (in_array('route_trip', $sections, true)) {
            $latest = $device->latestLocation;
            if (! isset($latest)) {
                $this->positionLoader->attachLatestToMany(collect([$device]));
                $latest = $device->latestLocation;
            }
            $panel['route_trip'] = $this->routeTripPayloadForActor($actor, $device, $latest);
        }

        return $panel;
    }

    /**
     * @return array<string, mixed>
     */
    private function devicePanelCore(User $actor, Device $device): array
    {
        $this->positionLoader->attachLatestToMany(collect([$device]));
        $latest = $device->latestLocation;
        $map = $this->mapStatus->resolve($latest, $device);
        $motionKey = $latest
            ? VehicleStatusSpec::motionKey((float) ($latest->speed ?? 0), (bool) $latest->ignition)
            : null;
        $duration = $this->statusDuration->resolve($device, $latest, $map);

        $notes = is_string($device->description ?? null) && $device->description !== ''
            ? $device->description
            : null;

        return [
            'id' => $device->id,
            'name' => $device->mapMarkerTitle(),
            'plate' => $device->mapMarkerPlateLine() ?? $device->vehiclePlateNumber(),
            'status' => $map['label'],
            'status_key' => $map['key'],
            'connectivity_tier' => $map['connectivity_tier'],
            'last_known_status' => $map['last_known_status'],
            'last_known_status_key' => $map['last_known_status_key'],
            'motion_status' => $motionKey ? VehicleStatusSpec::motionLabel($motionKey) : null,
            'motion_status_key' => $motionKey,
            'status_since' => $duration['since']
                ? app_datetime_api($duration['since'])
                : null,
            'status_duration_seconds' => $duration['seconds'],
            'icon' => $device->deviceTypeIconClass(),
            'color' => VehicleStatusSpec::colorForKey($map['key']),
            'speed' => $latest !== null ? round((float) ($latest->speed ?? 0)) : null,
            'angle' => $latest !== null ? round((float) ($latest->heading ?? 0)) : null,
            'altitude' => ($latest?->altitude !== null) ? round((float) $latest->altitude) : null,
            'odometer' => TelemetryFormatter::odometerKm($latest?->odometer),
            'lat' => $latest ? (float) $latest->lat : null,
            'lng' => $latest ? (float) $latest->lng : null,
            'ignition' => $latest !== null ? (bool) $latest->ignition : null,
            'time_position' => $latest?->recorded_at ? app_datetime_format($latest->recorded_at) : null,
            'time_server' => app_datetime_format(now()),
            'tasks' => $this->devicePanelTasks($device->id),
            'mileage' => null,
            'fuel' => $latest?->fuel ?? null,
            'battery' => $latest?->battery_level ?? null,
            'notes' => $notes,
            'photo' => null,
            'driver' => $this->driverPayloadForActor($actor, $device),
            'speed_max' => 160,
        ];
    }

    /**
     * @return array<string, int|float>
     */
    private function devicePanelTodayStats(Device $device, bool $fresh = false): array
    {
        $dayKey = now()->format('Y-m-d');
        $cacheKey = "tracking:panel:stats:{$device->id}:{$dayKey}";

        if ($fresh) {
            Cache::forget($cacheKey);
        }

        $stats = Cache::remember(
            $cacheKey,
            now()->addSeconds($fresh ? 45 : 90),
            function () use ($device) {
                $from = now()->startOfDay();
                $to = now();

                try {
                    $result = $this->historyFetcher->fetch($device, $from, $to, true, allowFallback: false);
                    $analyzed = $this->analytics->analyze($result['locations'], [
                        'include_track_points' => false,
                        'point_statuses' => false,
                        'skip_timeline' => true,
                        'minimal_stats' => false,
                    ]);
                } catch (\Throwable) {
                    $analyzed = $this->analytics->analyze(collect(), [
                        'minimal_stats' => true,
                    ]);
                }

                return $analyzed;
            }
        );

        return [
            'distance_km' => round((float) ($stats['total_distance_km'] ?? 0), 2),
            'move_seconds' => max(0, (int) ($stats['moving_time_seconds'] ?? 0)),
            'stop_seconds' => max(0, (int) ($stats['stopped_time_seconds'] ?? 0)),
            'idle_seconds' => max(0, (int) ($stats['idle_time_seconds'] ?? 0)),
            'parking_seconds' => max(0, (int) ($stats['parking_time_seconds'] ?? 0)),
            'top_speed' => round((float) ($stats['max_speed_kmh'] ?? 0)),
            'avg_speed' => round((float) ($stats['average_speed_kmh'] ?? 0)),
        ];
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function devicePanelRecentEvents(Device $device): array
    {
        $to = now();

        return $this->events
            ->forDevice($device, now()->startOfDay(), $to, null, limit: 12)
            ->map(fn (VehicleEvent $event) => array_merge($event->toAlertArray(), [
                'lat' => $event->lat !== null ? (float) $event->lat : null,
                'lng' => $event->lng !== null ? (float) $event->lng : null,
            ]))
            ->sortByDesc(fn ($event) => (string) ($event['time'] ?? ''))
            ->take(12)
            ->values()
            ->all();
    }

    /**
     * Speed graph points — recent window only, downsampled for a fast response.
     *
     * @return list<array{speed: float|int, lat: float, lng: float, time: ?string}>
     */
    private function devicePanelGraphPositions(Device $device): array
    {
        $from = now()->subHours(2);
        $to = now();

        try {
            $result = $this->historyFetcher->fetch($device, $from, $to, true, allowFallback: false);
            $collection = $result['locations'];
        } catch (\Throwable) {
            $collection = collect();
        }

        return $this->downsampleLocations($collection, 120)
            ->map(fn (DeviceLocation $loc) => [
                'speed' => round((float) ($loc->speed ?? 0)),
                'lat' => (float) $loc->lat,
                'lng' => (float) $loc->lng,
                'time' => $loc->recorded_at ? AppDateTime::format($loc->recorded_at, 'log') : null,
            ])
            ->values()
            ->all();
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function devicePanelTasks(int $deviceId): array
    {
        static $tasksTableExists = null;

        if ($tasksTableExists === null) {
            $tasksTableExists = \Illuminate\Support\Facades\Schema::hasTable('tracking_tasks');
        }

        if (! $tasksTableExists) {
            return [];
        }

        return \App\Models\TrackingTask::query()
            ->where('device_id', $deviceId)
            ->orderByDesc('id')
            ->limit(8)
            ->get()
            ->map(fn ($t) => [
                'name' => (string) $t->name,
                'status' => (string) $t->status,
                'start' => (string) ($t->start ?? ''),
                'destination' => (string) ($t->destination ?? ''),
                'time' => optional($t->time_from)->format('Y-m-d H:i'),
            ])
            ->all();
    }

    /**
     * @param  Collection<int, DeviceLocation>  $locations
     * @return Collection<int, DeviceLocation>
     */
    private function downsampleLocations(Collection $locations, int $maxPoints): Collection
    {
        $count = $locations->count();
        if ($count <= $maxPoints || $maxPoints < 1) {
            return $locations->values();
        }

        $step = (int) ceil($count / $maxPoints);
        $out = collect();

        foreach ($locations->values() as $index => $location) {
            if ($index % $step === 0 || $index === $count - 1) {
                $out->push($location);
            }
        }

        return $out->values();
    }

    /**
     * @return array<string, mixed>|null
     */
    public function routeTripPayload(Device $device, ?DeviceLocation $latest): ?array
    {
        $map = $this->mapStatus->resolve($latest, $device);
        $isLive = ($map['connectivity_tier'] ?? 'offline') === 'live';

        return $this->tripManagement->payloadForDevice(
            $device,
            $latest ? (float) $latest->lat : null,
            $latest ? (float) $latest->lng : null,
            $latest ? (float) ($latest->speed ?? 0) : null,
            $latest ? (float) ($latest->heading ?? 0) : null,
            $isLive,
        );
    }

    public function routeTripPayloadForActor(User $actor, Device $device, ?DeviceLocation $latest): ?array
    {
        $payload = $this->routeTripPayload($device, $latest);
        if ($payload === null) {
            return null;
        }

        $ui = app(TrackingUiPermissions::class)->forUser($actor);
        if (! ($ui['polyline'] ?? true)) {
            return $this->stripRouteTripPolylines($payload);
        }

        return $payload;
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    public function stripRouteTripPolylines(array $payload): array
    {
        if (! isset($payload['route']) || ! is_array($payload['route'])) {
            return $payload;
        }

        $polylineKeys = [
            'assigned_polyline',
            'guided_polyline',
            'polyline',
            'admin_polyline',
            'actual_polyline',
            'navigation_polyline',
            'join_polyline',
            'dynamic_polyline',
            'display_polyline',
            'encoded_polyline',
        ];

        foreach ($polylineKeys as $key) {
            unset($payload['route'][$key]);
        }

        $payload['route']['has_stored_polyline'] = false;
        $payload['route']['is_road_polyline'] = false;

        return $payload;
    }

    /**
     * Daily mileage (km) for the last N days — lazy-loaded + cached (heavy history scan),
     * so opening the device panel stays instant.
     *
     * @return list<array{label:string, km:float}>|null
     */
    public function deviceMileage(User $actor, int $deviceId, int $days = 5): ?array
    {
        if (! in_array($deviceId, $this->filterAllowedIds($actor, [$deviceId]), true)) {
            return null;
        }

        $days = max(1, min(14, $days));

        return \Illuminate\Support\Facades\Cache::remember(
            "tracking:mileage:{$deviceId}:{$days}",
            now()->addMinutes(10),
            function () use ($deviceId, $days) {
                $device = Device::query()->find($deviceId);
                if (! $device) {
                    return [];
                }

                try {
                    $collection = $this->historyFetcher
                        ->fetch($device, now()->subDays($days - 1)->startOfDay(), now(), true)['locations'];
                } catch (\Throwable) {
                    $collection = collect();
                }

                $perDay = $this->dailyDistanceKm($collection);

                $out = [];
                for ($d = $days - 1; $d >= 0; $d--) {
                    $day = now()->subDays($d);
                    $out[] = [
                        'label' => $day->format('d'),
                        'km' => round((float) ($perDay[$day->format('Y-m-d')] ?? 0), 1),
                    ];
                }

                return $out;
            }
        );
    }

    /**
     * Single-pass haversine distance per calendar day (km), keyed by Y-m-d.
     *
     * @param  Collection<int, DeviceLocation>  $locations
     * @return array<string, float>
     */
    private function dailyDistanceKm(Collection $locations): array
    {
        $perDay = [];
        $prev = null;

        foreach ($locations as $loc) {
            if ($loc->lat === null || $loc->lng === null || ! $loc->recorded_at) {
                continue;
            }

            $day = $loc->recorded_at->format('Y-m-d');
            $perDay[$day] = $perDay[$day] ?? 0.0;

            if ($prev !== null && $prev['day'] === $day) {
                $perDay[$day] += $this->haversineKm(
                    (float) $prev['lat'],
                    (float) $prev['lng'],
                    (float) $loc->lat,
                    (float) $loc->lng
                );
            }

            $prev = ['day' => $day, 'lat' => $loc->lat, 'lng' => $loc->lng];
        }

        return $perDay;
    }

    private function haversineKm(float $lat1, float $lng1, float $lat2, float $lng2): float
    {
        $earth = 6371.0;
        $dLat = deg2rad($lat2 - $lat1);
        $dLng = deg2rad($lng2 - $lng1);
        $a = sin($dLat / 2) ** 2 + cos(deg2rad($lat1)) * cos(deg2rad($lat2)) * sin($dLng / 2) ** 2;

        return $earth * 2 * asin(min(1.0, sqrt($a)));
    }

    /**
     * @return Collection<int, Device>
     */
    public function devicesForActor(User $actor): Collection
    {
        if ($this->rbac->canAccessPanel($actor)) {
            return Device::inTracker()
                ->orderBy('name')
                ->when(true, fn ($q) => $this->tenantScope->scopeDevices($q, $actor))
                ->get();
        }

        return $this->subscribedDevicesForEndUser($actor);
    }

    /**
     * All tc-linked devices for end-user listing (includes vehicles without active subscription).
     *
     * @return Collection<int, Device>
     */
    public function linkedDevicesForActor(User $actor): Collection
    {
        if ($this->rbac->canAccessPanel($actor)) {
            return $this->devicesForActor($actor);
        }

        return $this->trackingGate->filterTrackable(
            $actor,
            $actor->trackerDevicesQuery()
                ->with(['subscription.clientInvoice'])
                ->orderBy('name')
                ->get(),
            requireSubscription: false,
        );
    }

    /**
     * @return Collection<int, Device>
     */
    public function subscribedDevicesForEndUser(User $actor): Collection
    {
        return $this->trackingGate->filterTrackable(
            $actor,
            $actor->trackerDevicesQuery()
                ->with(['subscription.clientInvoice'])
                ->orderBy('name')
                ->get(),
            requireSubscription: true,
        );
    }

    /**
     * @return list<int>
     */
    public function allowedDeviceIds(User $actor): array
    {
        return Cache::remember(
            'tracking.allowed_ids.'.$actor->id,
            45,
            fn () => $this->devicesForActor($actor)
                ->pluck('id')
                ->map(fn ($id) => (int) $id)
                ->values()
                ->all()
        );
    }

    /**
     * @param  list<int|string>  $requestedIds
     * @return list<int>
     */
    public function filterAllowedIds(User $actor, array $requestedIds): array
    {
        if ($requestedIds === []) {
            return [];
        }

        $allowed = array_flip($this->allowedDeviceIds($actor));

        return array_values(array_filter(
            array_map('intval', $requestedIds),
            fn (int $id) => isset($allowed[$id])
        ));
    }

    /**
     * @param  list<int|string>  $requestedIds
     * @return list<int>
     */
    public function filterLinkedIds(User $actor, array $requestedIds): array
    {
        if ($requestedIds === []) {
            return [];
        }

        $allowed = array_flip(
            $this->linkedDevicesForActor($actor)
                ->pluck('id')
                ->map(fn ($id) => (int) $id)
                ->all()
        );

        return array_values(array_filter(
            array_map('intval', $requestedIds),
            fn (int $id) => isset($allowed[$id])
        ));
    }

    /**
     * Sidebar vehicle list with latest telemetry for all visible devices.
     *
     * @return list<array<string, mixed>>
     */
    public function listItemsForActor(User $actor): array
    {
        $devices = $this->devicesForActor($actor);
        $this->positionLoader->attachLatestToMany($devices);
        $driverPayloads = $this->driverMapInfo->payloadsForDevices($devices);
        $includeDriver = (bool) ($this->trackingUi->forUser($actor)['driver'] ?? false);

        return $devices->map(function (Device $device) use ($actor, $driverPayloads, $includeDriver) {
            $latest = $device->latestLocation;
            $map = $this->mapStatus->resolve($latest, $device);
            $duration = $this->statusDuration->resolve($device, $latest, $map);

            return array_merge([
                'id' => $device->id,
                'title' => $device->mapMarkerTitle(),
                'plate' => $device->mapMarkerPlateLine(),
                'imei' => $device->imei,
                'status_key' => $map['key'],
                'status_label' => $map['label'],
                'connectivity_tier' => $map['connectivity_tier'],
                'status_since' => $duration['since']
                    ? app_datetime_api($duration['since'])
                    : null,
                'status_duration_seconds' => $duration['seconds'],
                'icon' => $device->deviceTypeIconClass(),
                'color' => VehicleStatusSpec::colorForKey($map['key']),
                'lat' => $latest ? (float) $latest->lat : null,
                'lng' => $latest ? (float) $latest->lng : null,
                'speed' => $latest !== null ? round((float) ($latest->speed ?? 0)) : null,
                'heading' => $latest ? (float) ($latest->heading ?? 0) : 0,
                'ignition' => $latest !== null ? (bool) $latest->ignition : null,
                'gsm_signal' => $latest?->gsm_signal,
                'satellites' => $latest?->satellites,
                'battery_level' => $latest?->battery_level,
                'recorded_at_human' => $latest?->recorded_at
                    ? app_datetime_format($latest->recorded_at)
                    : (string) __('app.user.devices.no_data_yet'),
                'driver' => $includeDriver ? ($driverPayloads[$device->id] ?? null) : null,
                'route_trip' => $this->routeTripPayloadForActor($actor, $device, $latest),
            ], $device->mapAppearancePayload());
        })->values()->all();
    }

    /**
     * Live positions for selected device ids (already filtered to allowed set).
     *
     * @param  list<int>  $deviceIds
     * @return list<array<string, mixed>>
     */
    public function livePayloadForIds(array $deviceIds, ?User $actor = null): array
    {
        if ($deviceIds === []) {
            return [];
        }

        $deviceIds = array_slice($deviceIds, 0, self::MAX_LIVE_DEVICES);
        $devices = Device::query()->whereIn('id', $deviceIds)->get()->keyBy('id');
        $this->positionLoader->attachLatestToMany($devices);
        $driverPayloads = $this->driverMapInfo->payloadsForDevices($devices);
        $includeDriver = $actor && ($this->trackingUi->forUser($actor)['driver'] ?? false);
        $includeRouteTrip = count($deviceIds) === 1
            && (bool) config('tracking.live_include_route_trip_single', true);

        $out = [];

        foreach ($deviceIds as $id) {
            $device = $devices->get($id);
            if (! $device) {
                continue;
            }

            $latest = $device->latestLocation;

            $payload = $latest
                ? DeviceLocationPayload::fromDeviceLocation($latest, $device)
                : [
                    'id' => $device->id,
                    'lat' => null,
                    'lng' => null,
                    'speed' => null,
                    'heading' => 0,
                    'status_key' => 'offline',
                    'status' => (string) __('app.tracking.offline'),
                ];
            $payload = array_merge($payload, $device->mapAppearancePayload());
            $payload['id'] = $device->id;
            $payload['title'] = $device->mapMarkerTitle();
            $payload['plate'] = $device->mapMarkerPlateLine();
            $payload['icon'] = $device->deviceTypeIconClass();
            $payload['color'] = VehicleStatusSpec::colorForKey((string) ($payload['status_key'] ?? 'offline'));
            $payload['recorded_at_human'] = $latest
                ? app_datetime_format($latest->recorded_at)
                : null;
            if ($includeRouteTrip && $latest) {
                try {
                    $payload['route_trip'] = $actor
                        ? $this->routeTripPayloadForActor($actor, $device, $latest)
                        : $this->routeTripPayload($device, $latest);
                } catch (\Throwable $e) {
                    report($e);
                }
            }
            if ($includeDriver) {
                $payload['driver'] = $driverPayloads[$device->id] ?? null;
            }
            $out[] = $payload;
        }

        return $out;
    }

    /**
     * History routes for one or more devices within a datetime range.
     *
     * @param  list<int>  $deviceIds
     * @return list<array<string, mixed>>
     */
    public function historyForDevices(User $actor, array $deviceIds, Carbon $from, ?Carbon $to): array
    {
        $ids = $this->filterAllowedIds($actor, $deviceIds);
        $multi = count($ids) > 1;
        $vehicles = [];
        $colorIndex = 0;

        foreach ($ids as $id) {
            $device = Device::query()->find($id);
            if (! $device) {
                continue;
            }

            $result = $this->historyFetcher->fetch($device, $from, $to, true, allowFallback: false);
            $locations = $result['locations'];

            $stats = $this->analytics->analyze($locations, [
                'point_statuses' => false,
                'include_track_points' => false,
                'skip_timeline' => false,
            ]);

            $displayLocations = $this->downsampleHistoryPoints($locations);

            $points = $displayLocations
                ->values()
                ->map(fn (DeviceLocation $loc) => $this->formatHistoryPoint($loc))
                ->values()
                ->all();

            $dbEvents = $this->events
                ->forDevice($device, $from, $to, limit: 300);

            $historyEvents = $this->historyEvents->compile(
                $dbEvents,
                $stats['timeline'] ?? [],
                $stats['stops'] ?? [],
            );

            $events = $historyEvents;

            $vehicles[] = array_merge([
                'id' => $device->id,
                'name' => $device->mapMarkerTitle(),
                'title' => $device->mapMarkerTitle(),
                'plate' => $device->mapMarkerPlateLine(),
                'color' => $multi
                    ? self::MULTI_VEHICLE_COLORS[$colorIndex++ % count(self::MULTI_VEHICLE_COLORS)]
                    : null,
                'points' => $points,
                'stats' => [
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
                    'stop_count' => (int) ($stats['stop_count'] ?? 0),
                ],
                'timeline' => $stats['timeline'] ?? [],
                'used_fallback' => $result['used_fallback'],
                'fallback_reason' => $result['fallback_reason'],
                'events' => $events,
                'history_events' => $historyEvents,
                'point_count' => $locations->count(),
                'display_point_count' => count($points),
            ], $device->mapAppearancePayload());
        }

        return $vehicles;
    }

    /**
     * @return array<string, mixed>|null
     */
    private function driverPayloadForActor(User $actor, Device $device): ?array
    {
        if (! ($this->trackingUi->forUser($actor)['driver'] ?? false)) {
            return null;
        }

        return $this->driverMapInfo->payloadForDevice($device);
    }

    /**
     * Cap map polyline size while analytics run on the full track.
     *
     * @param  Collection<int, DeviceLocation>  $locations
     * @return Collection<int, DeviceLocation>
     */
    private function downsampleHistoryPoints(Collection $locations, int $max = 2800): Collection
    {
        $count = $locations->count();
        if ($count <= $max) {
            return $locations;
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
    private function formatHistoryPoint(DeviceLocation $location): array
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
}
