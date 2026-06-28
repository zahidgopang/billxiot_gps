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
use App\Support\DateTime\AppDateTime;
use App\Support\Tracking\DeviceLocationPayload;
use Carbon\Carbon;
use Illuminate\Support\Collection;

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
        private TenantScopeService $tenantScope,
        private RbacService $rbac,
        private \App\Services\Mobile\MobileRouteAnalyticsService $analytics,
    ) {}

    /**
     * Traccar-style bottom info panel for one device:
     * latest telemetry + today's statistics + recent events + recent positions (for the graph).
     *
     * @return array<string, mixed>|null
     */
    public function devicePanelData(User $actor, int $deviceId): ?array
    {
        if (! in_array($deviceId, $this->filterAllowedIds($actor, [$deviceId]), true)) {
            return null;
        }

        $device = Device::query()->find($deviceId);
        if (! $device) {
            return null;
        }

        $this->positionLoader->attachLatestToMany(collect([$device]));
        $latest = $device->latestLocation;
        $map = $this->mapStatus->resolve($latest, $device);

        $from = now()->startOfDay();
        $to = now();
        $collection = $this->historyFetcher->fetch($device, $from, $to, true)['locations'];
        $stats = $this->analytics->analyze($collection);

        $events = $this->events
            ->forDevice($device, now()->subDays(7), $to, null, limit: 12)
            ->map(fn (VehicleEvent $event) => array_merge($event->toAlertArray(), [
                'lat' => $event->lat !== null ? (float) $event->lat : null,
                'lng' => $event->lng !== null ? (float) $event->lng : null,
            ]))
            ->values()
            ->all();
        usort($events, fn ($a, $b) => strcmp((string) ($b['time'] ?? ''), (string) ($a['time'] ?? '')));
        $events = array_slice($events, 0, 12);

        $positions = $collection
            ->map(fn (DeviceLocation $loc) => [
                'speed' => round((float) ($loc->speed ?? 0)),
                'lat' => (float) $loc->lat,
                'lng' => (float) $loc->lng,
                'time' => $loc->recorded_at ? AppDateTime::format($loc->recorded_at, 'log') : null,
            ])
            ->values()
            ->all();

        // Recent tasks for this object (Traccar "Recent tasks" widget).
        $tasks = [];
        if (\Illuminate\Support\Facades\Schema::hasTable('tracking_tasks')) {
            $tasks = \App\Models\TrackingTask::query()
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

        $notes = is_string($device->description ?? null) && $device->description !== ''
            ? $device->description
            : null;

        return [
            'id' => $device->id,
            'name' => $device->mapMarkerTitle(),
            'plate' => $device->mapMarkerPlateLine() ?? $device->vehiclePlateNumber(),
            'status' => $map['label'],
            'status_key' => $map['key'],
            'icon' => $device->deviceTypeIconClass(),
            'color' => VehicleStatusSpec::colorForKey($map['key']),
            'speed' => $latest !== null ? round((float) ($latest->speed ?? 0)) : null,
            'angle' => $latest !== null ? round((float) ($latest->heading ?? 0)) : null,
            'altitude' => ($latest?->altitude !== null) ? round((float) $latest->altitude) : null,
            'odometer' => $latest?->odometer,
            'lat' => $latest ? (float) $latest->lat : null,
            'lng' => $latest ? (float) $latest->lng : null,
            'ignition' => $latest !== null ? (bool) $latest->ignition : null,
            'time_position' => $latest?->recorded_at ? app_datetime_format($latest->recorded_at) : null,
            'time_server' => app_datetime_format(now()),
            'stats' => [
                'distance_km' => round((float) ($stats['total_distance_km'] ?? 0), 2),
                'move_seconds' => (int) ($stats['moving_time_seconds'] ?? 0),
                'stop_seconds' => (int) ($stats['stopped_time_seconds'] ?? 0),
                'top_speed' => round((float) ($stats['max_speed_kmh'] ?? 0)),
                'avg_speed' => round((float) ($stats['average_speed_kmh'] ?? 0)),
            ],
            'events' => $events,
            'positions' => $positions,
            'tasks' => $tasks,
            'mileage' => null, // lazy-loaded via deviceMileage() to keep the panel fast
            'fuel' => $latest?->fuel ?? null,
            'battery' => $latest?->battery_level ?? null,
            'notes' => $notes,
            'photo' => null,
            'speed_max' => 160,
        ];
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

        return $actor->trackerDevicesQuery()
            ->orderBy('name')
            ->get();
    }

    /**
     * @return list<int>
     */
    public function allowedDeviceIds(User $actor): array
    {
        return $this->devicesForActor($actor)
            ->pluck('id')
            ->map(fn ($id) => (int) $id)
            ->values()
            ->all();
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
     * Sidebar vehicle list with latest telemetry for all visible devices.
     *
     * @return list<array<string, mixed>>
     */
    public function listItemsForActor(User $actor): array
    {
        $devices = $this->devicesForActor($actor);
        $this->positionLoader->attachLatestToMany($devices);

        return $devices->map(function (Device $device) {
            $latest = $device->latestLocation;
            $map = $this->mapStatus->resolve($latest, $device);

            return [
                'id' => $device->id,
                'title' => $device->mapMarkerTitle(),
                'plate' => $device->mapMarkerPlateLine(),
                'imei' => $device->imei,
                'status_key' => $map['key'],
                'status_label' => $map['label'],
                'connectivity_tier' => $map['connectivity_tier'],
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
            ];
        })->values()->all();
    }

    /**
     * Live positions for selected device ids (already filtered to allowed set).
     *
     * @param  list<int>  $deviceIds
     * @return list<array<string, mixed>>
     */
    public function livePayloadForIds(array $deviceIds): array
    {
        if ($deviceIds === []) {
            return [];
        }

        $deviceIds = array_slice($deviceIds, 0, self::MAX_LIVE_DEVICES);
        $devices = Device::query()->whereIn('id', $deviceIds)->get()->keyBy('id');
        $this->positionLoader->attachLatestToMany($devices);

        $out = [];

        foreach ($deviceIds as $id) {
            $device = $devices->get($id);
            if (! $device) {
                continue;
            }

            $latest = $device->latestLocation;
            if (! $latest) {
                continue;
            }

            $payload = DeviceLocationPayload::fromDeviceLocation($latest, $device);
            $payload['id'] = $device->id;
            $payload['title'] = $device->mapMarkerTitle();
            $payload['plate'] = $device->mapMarkerPlateLine();
            $payload['icon'] = $device->deviceTypeIconClass();
            $payload['color'] = VehicleStatusSpec::colorForKey((string) ($payload['status_key'] ?? 'offline'));
            $payload['recorded_at_human'] = app_datetime_format($latest->recorded_at);
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

            $result = $this->historyFetcher->fetch($device, $from, $to, true);
            $points = $result['locations']
                ->map(fn (DeviceLocation $loc) => $this->formatHistoryPoint($loc))
                ->values()
                ->all();

            $events = $this->events
                ->forDevice($device, $from, $to, limit: 500)
                ->map(fn (VehicleEvent $event) => array_merge($event->toAlertArray(), [
                    'lat' => $event->lat !== null ? (float) $event->lat : null,
                    'lng' => $event->lng !== null ? (float) $event->lng : null,
                ]))
                ->values()
                ->all();

            $vehicles[] = [
                'id' => $device->id,
                'name' => $device->mapMarkerTitle(),
                'color' => $multi
                    ? self::MULTI_VEHICLE_COLORS[$colorIndex++ % count(self::MULTI_VEHICLE_COLORS)]
                    : null,
                'points' => $points,
                'used_fallback' => $result['used_fallback'],
                'fallback_reason' => $result['fallback_reason'],
                'events' => $events,
            ];
        }

        return $vehicles;
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
            'recorded_at' => AppDateTime::toApi($location->recorded_at),
            'timestamp' => $location->recorded_at
                ? AppDateTime::format($location->recorded_at, 'log')
                : null,
        ];
    }
}
