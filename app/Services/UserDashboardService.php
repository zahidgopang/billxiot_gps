<?php

namespace App\Services;

use App\Contracts\Geofences\GeofenceStoreInterface;
use App\Contracts\Tracking\EventReaderInterface;
use App\Models\Device;
use App\Models\VehicleEvent;
use App\Models\User;
use App\Services\Authorization\RbacService;
use App\Services\Mobile\MobileMapStatusResolver;
use App\Services\Mobile\VehicleStatusSpec;
use App\Services\Tracking\DevicePositionLoader;
use App\Services\Tracking\GlobalTrackingService;
use App\Services\Tracking\TrackingMetricsService;
use App\Services\Traccar\TraccarTrackingGate;
use App\Services\Traccar\TraccarUserAccessService;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Schema;
use Spatie\Activitylog\Models\Activity;

class UserDashboardService
{
    public function __construct(
        private DevicePositionLoader $positionLoader,
        private TrackingMetricsService $metrics,
        private EventReaderInterface $events,
        private GeofenceStoreInterface $geofences,
        private TraccarUserAccessService $trackerUsers,
        private TraccarTrackingGate $trackingGate,
        private MobileMapStatusResolver $mapStatus,
        private RbacService $rbac,
        private GlobalTrackingService $tracking,
    ) {}

    public const MOVING_SPEED_KMH = VehicleStatusSpec::MOVING_SPEED_KMH;

    /** Canonical online window — matches map/mobile connectivity tiers (≤30 min; VehicleStatusSpec::OFFLINE_SECONDS). */
    public const ONLINE_MINUTES = 30;

    public function getStats(User $user): array
    {
        return array_merge(
            $this->getDashboardShell($user),
            $this->getDashboardMetrics($user),
        );
    }

    /**
     * Fast dashboard shell — live fleet KPIs without distance scans or charts.
     *
     * @return array<string, mixed>
     */
    public function getDashboardShell(User $user): array
    {
        $devices = $this->resolveDashboardDevices($user);

        if ($devices->isEmpty() && ! $this->rbac->canAccessPanel($user)) {
            return $this->emptyTrackerStats();
        }

        $this->positionLoader->attachLatestToMany($devices);
        $deviceIds = $devices->pluck('id');

        $totalDevices = $devices->count();
        $activeDevices = $devices->where('status', 'active')->count();
        $onlineNow = $this->countOnlineDevices($devices);
        $alertDeviceIds = $this->alertDeviceIds($devices);
        $vehicleStates = $this->getVehicleStateCounts($devices, $alertDeviceIds);
        $fleetCounts = $this->mapStatus->fleetCounts($devices);
        $fleetDevices = $devices->sortByDesc(fn (Device $d) => $d->latestLocation?->recorded_at)->values();
        $pageStats = $this->getDevicePageStats($devices);

        try {
            $activities = $this->getRecentActivities($deviceIds);
        } catch (\Throwable $e) {
            report($e);
            $activities = collect();
        }

        return array_merge($pageStats, [
            'devices' => $devices,
            'totalDistanceKm' => 0,
            'distanceTodayKm' => 0,
            'activeAlerts' => 0,
            'vehicleStates' => $vehicleStates,
            'fleetCounts' => $fleetCounts,
            'recentDevices' => $fleetDevices,
            'activities' => $activities,
            'alertDeviceIds' => $alertDeviceIds,
            'activePercent' => $totalDevices > 0 ? round(($activeDevices / $totalDevices) * 100) : 0,
            'onlinePercent' => $totalDevices > 0 ? round(($onlineNow / $totalDevices) * 100) : 0,
            'alertsPercent' => 0,
            'distancePercent' => 0,
            'chartData' => null,
            'mapMarkers' => $this->buildMapMarkers($devices),
        ]);
    }

    /**
     * Heavy dashboard metrics (cached) — charts, distance totals, alert counts.
     *
     * @return array<string, mixed>
     */
    public function getDashboardMetrics(User $user): array
    {
        $devices = $this->resolveDashboardDevices($user);

        if ($devices->isEmpty() && ! $this->rbac->canAccessPanel($user)) {
            $empty = $this->emptyTrackerStats();

            return [
                'totalDistanceKm' => 0,
                'distanceTodayKm' => 0,
                'activeAlerts' => 0,
                'alertsPercent' => 0,
                'distancePercent' => 0,
                'chartData' => $empty['chartData'],
            ];
        }

        $this->positionLoader->attachLatestToMany($devices);
        $deviceIds = $devices->pluck('id');
        $fleetCounts = $this->mapStatus->fleetCounts($devices);
        $heavy = $this->cachedHeavyMetrics($user, $devices, $deviceIds, $fleetCounts);

        return [
            'totalDistanceKm' => $heavy['totalDistanceKm'],
            'distanceTodayKm' => $heavy['distanceTodayKm'],
            'activeAlerts' => $heavy['activeAlerts'],
            'alertsPercent' => min(100, $heavy['activeAlerts'] * 20),
            'distancePercent' => min(100, (int) round($heavy['totalDistanceKm'] / 50)),
            'chartData' => $heavy['chartData'],
        ];
    }

    /**
     * @return array{totalDistanceKm: float|int, distanceTodayKm: float|int, activeAlerts: int, chartData: array<string, mixed>}
     */
    private function cachedHeavyMetrics(User $user, Collection $devices, Collection $deviceIds, array $fleetCounts): array
    {
        $cacheKey = 'user_dashboard_heavy_'.$user->id.'_'.md5($deviceIds->sort()->values()->implode(','));

        return Cache::remember($cacheKey, 90, function () use ($devices, $deviceIds, $fleetCounts) {
            try {
                $totalDistanceKm = round($this->metrics->calculateTotalDistanceKm($deviceIds));
            } catch (\Throwable $e) {
                report($e);
                $totalDistanceKm = 0;
            }

            try {
                $distanceTodayKm = round($this->metrics->calculateTotalDistanceKm($deviceIds, 1), 1);
            } catch (\Throwable $e) {
                report($e);
                $distanceTodayKm = 0;
            }

            try {
                $activeAlerts = $deviceIds->isEmpty()
                    ? 0
                    : $this->events->countForDevices(
                        $deviceIds,
                        now()->subDays(7),
                        VehicleEvent::dashboardAlertTypes(),
                    );
            } catch (\Throwable $e) {
                report($e);
                $activeAlerts = 0;
            }

            try {
                $chartData = $this->buildChartPayload($devices, $deviceIds, $fleetCounts);
            } catch (\Throwable $e) {
                report($e);
                $chartData = $this->emptyTrackerStats()['chartData'];
            }

            return [
                'totalDistanceKm' => $totalDistanceKm,
                'distanceTodayKm' => $distanceTodayKm,
                'activeAlerts' => $activeAlerts,
                'chartData' => $chartData,
            ];
        });
    }

    /**
     * @return array<string, mixed>
     */
    public function buildChartPayload(Collection $devices, Collection $deviceIds, array $fleetCounts): array
    {
        $from = now()->subDays(7);

        $alertCounts = [
            'Overspeed' => $deviceIds->isEmpty() ? 0 : $this->events->countForDevices($deviceIds, $from, [VehicleEvent::TYPE_OVERSPEED]),
            'Geofence' => $deviceIds->isEmpty() ? 0 : $this->events->countForDevices($deviceIds, $from, [VehicleEvent::TYPE_GEOFENCE_EXIT, VehicleEvent::TYPE_GEOFENCE_ENTER]),
            'Ignition' => $deviceIds->isEmpty() ? 0 : $this->events->countForDevices($deviceIds, $from, [VehicleEvent::TYPE_IGNITION]),
            'Power cut' => $deviceIds->isEmpty() ? 0 : $this->events->countForDevices($deviceIds, $from, [VehicleEvent::TYPE_POWER_CUT]),
            'Offline' => $deviceIds->isEmpty() ? 0 : $this->events->countForDevices($deviceIds, $from, [VehicleEvent::TYPE_OFFLINE, VehicleEvent::TYPE_COMM_LOST_MOVING]),
        ];

        $activityLabels = [];
        $activityValues = [];
        $activityWindow = self::ONLINE_MINUTES;
        for ($h = 23; $h >= 0; $h--) {
            $at = now()->subHours($h);
            $activityLabels[] = $at->format('H:i');
            try {
                $activityValues[] = $deviceIds->isEmpty()
                    ? 0
                    : $this->metrics->onlineDevicesAtForDevices($deviceIds, $at, $activityWindow);
            } catch (\Throwable) {
                $activityValues[] = 0;
            }
        }

        try {
            $performance = $this->metrics->positionChartDataForDevices($deviceIds, 30);
        } catch (\Throwable) {
            $performance = ['labels' => [], 'gpsPings' => [], 'activeDevices' => []];
        }

        $donutParked = (int) (($fleetCounts['parked'] ?? 0) + ($fleetCounts['stopped'] ?? 0));
        $donutIdle = (int) (($fleetCounts['idle'] ?? 0) + ($fleetCounts['delayed'] ?? 0) + ($fleetCounts['stale'] ?? 0) + ($fleetCounts['alert'] ?? 0));
        $donutOffline = (int) ($fleetCounts['offline'] ?? 0);
        $donutRunning = (int) ($fleetCounts['running'] ?? 0);

        return [
            'statusDonut' => [
                'running' => $donutRunning,
                'parked' => $donutParked,
                'idle' => $donutIdle,
                'offline' => $donutOffline,
            ],
            'activityArea' => [
                'labels' => $activityLabels,
                'values' => $activityValues,
            ],
            'alertsBar' => [
                'labels' => array_keys($alertCounts),
                'values' => array_values($alertCounts),
            ],
            'performanceLine' => [
                'labels' => $performance['labels'] ?? [],
                'gpsPings' => $performance['gpsPings'] ?? [],
                'activeDevices' => $performance['activeDevices'] ?? [],
            ],
            'weeklyKm' => $this->topDevicesWeeklyKm($devices, 5),
        ];
    }

    /**
     * @return array{labels: list<string>, values: list<float>}
     */
    private function topDevicesWeeklyKm(Collection $devices, int $limit): array
    {
        if ($devices->isEmpty()) {
            return ['labels' => [], 'values' => []];
        }

        $top = $devices
            ->sortByDesc(fn (Device $d) => $d->latestLocation?->recorded_at?->getTimestamp() ?? 0)
            ->take(max(1, $limit))
            ->values();

        $labels = [];
        $values = [];

        foreach ($top as $device) {
            $labels[] = $device->listPrimaryLabel();
            try {
                $values[] = round($this->metrics->calculateTotalDistanceKm(collect([$device->id]), 7), 1);
            } catch (\Throwable) {
                $values[] = 0;
            }
        }

        return compact('labels', 'values');
    }

    /**
     * @return array<string, array{good: int, warn: int, bad: int}>
     */
    public function buildVehicleHealth(Collection $devices): array
    {
        $health = [
            'gps' => ['good' => 0, 'warn' => 0, 'bad' => 0],
            'battery' => ['good' => 0, 'warn' => 0, 'bad' => 0],
            'engine' => ['good' => 0, 'warn' => 0, 'bad' => 0],
            'ignition' => ['good' => 0, 'warn' => 0, 'bad' => 0],
            'sim' => ['good' => 0, 'warn' => 0, 'bad' => 0],
            'temperature' => ['good' => 0, 'warn' => 0, 'bad' => 0],
        ];

        foreach ($devices as $device) {
            $latest = $device->latestLocation;
            $map = $this->mapStatus->resolve($latest, $device);

            if ($this->mapStatus->isRecentlyOnline($latest)) {
                $health['gps']['good']++;
            } elseif ($latest) {
                $health['gps']['warn']++;
            } else {
                $health['gps']['bad']++;
            }

            $battery = is_numeric($latest?->battery_level) ? (int) $latest->battery_level : null;
            if ($battery === null) {
                $health['battery']['warn']++;
            } elseif ($battery >= 50) {
                $health['battery']['good']++;
            } elseif ($battery >= 20) {
                $health['battery']['warn']++;
            } else {
                $health['battery']['bad']++;
            }

            if ($device->status === 'active') {
                $health['engine']['good']++;
            } elseif ($device->status === 'inactive') {
                $health['engine']['warn']++;
            } else {
                $health['engine']['bad']++;
            }

            if ($latest?->ignition) {
                $health['ignition']['good']++;
            } elseif ($this->mapStatus->isRecentlyOnline($latest)) {
                $health['ignition']['warn']++;
            } else {
                $health['ignition']['bad']++;
            }

            $gsm = is_numeric($latest?->gsm_signal ?? null) ? (int) $latest->gsm_signal : null;
            if ($gsm === null) {
                $health['sim']['warn']++;
            } elseif ($gsm >= 60) {
                $health['sim']['good']++;
            } elseif ($gsm >= 30) {
                $health['sim']['warn']++;
            } else {
                $health['sim']['bad']++;
            }

            $temp = data_get($latest?->attributes, 'temperature')
                ?? data_get($latest?->attributes, 'temp');
            if (! is_numeric($temp)) {
                $health['temperature']['warn']++;
            } elseif ($temp >= -10 && $temp <= 45) {
                $health['temperature']['good']++;
            } elseif ($temp >= -20 && $temp <= 55) {
                $health['temperature']['warn']++;
            } else {
                $health['temperature']['bad']++;
            }
        }

        return $health;
    }

    /**
     * @return list<array{id: int, name: string, lat: float, lng: float, status: string}>
     */
    public function buildMapMarkers(Collection $devices): array
    {
        $markers = [];

        foreach ($devices as $device) {
            $latest = $device->latestLocation;
            if (! $latest || $latest->lat === null || $latest->lng === null) {
                continue;
            }
            if (! $this->mapStatus->isRecentlyOnline($latest, $device)) {
                continue;
            }
            if (! $this->mapStatus->hasValidGpsFix($latest)) {
                continue;
            }

            $map = $this->mapStatus->resolve($latest, $device);
            $markers[] = [
                'id' => $device->id,
                'name' => $device->listPrimaryLabel(),
                'lat' => (float) $latest->lat,
                'lng' => (float) $latest->lng,
                'status' => $map['key'],
            ];
        }

        return $markers;
    }

    /**
     * Role-scoped device set for dashboard counts/recent list.
     * Staff (super-admin/admin/client) see their full fleet; end-users see their own devices.
     *
     * @return Collection<int, Device>
     */
    private function resolveDashboardDevices(User $user): Collection
    {
        if ($this->rbac->canAccessPanel($user)) {
            $devices = $this->tracking->devicesForActor($user);
            $devices->loadMissing('subscription');

            return $devices;
        }

        if (! $this->trackerUsers->hasTrackerAccount($user)) {
            return collect();
        }

        return $this->trackingGate->filterTrackable(
            $user,
            $user->trackerDevicesQuery()->with(['subscription'])->get(),
            requireSubscription: ! $this->rbac->bypassesSubscriptionRestrictions($user),
        );
    }

    public function calculateTotalDistanceKm(Collection $deviceIds, int $days = 30): float
    {
        return $this->metrics->calculateTotalDistanceKm($deviceIds, $days);
    }

    public function countOnlineDevices(Collection $devices): int
    {
        return $devices->filter(function (Device $device) {
            return $device->status === 'active'
                && $this->mapStatus->isRecentlyOnline($device->latestLocation);
        })->count();
    }

    public function getVehicleStateCounts(Collection $devices, ?Collection $alertDeviceIds = null): array
    {
        $fleetCounts = $this->mapStatus->fleetCounts($devices);
        $maintenance = $devices->whereIn('status', ['inactive', 'blocked'])->count();
        $alertDeviceIds ??= $this->alertDeviceIds($devices);
        $alerts = $devices->filter(fn (Device $d) => $alertDeviceIds->contains($d->id)
            && $this->mapStatus->isRecentlyOnline($d->latestLocation))->count();

        return [
            'running' => $fleetCounts['running'],
            'parked' => $fleetCounts['parked'] + $fleetCounts['stopped'] + $fleetCounts['idle'],
            'maintenance' => $maintenance,
            'alerts' => $alerts,
        ];
    }

    public function getRecentActivities(Collection $deviceIds): Collection
    {
        if ($deviceIds->isEmpty()) {
            return collect();
        }

        $vehicleItems = $this->events->recentForDevices($deviceIds, 12)
            ->map(fn (VehicleEvent $event) => [
                'type' => 'vehicle',
                'title' => $event->title,
                'description' => $event->message,
                'time' => $event->occurred_at,
                'icon' => match ($event->type) {
                    VehicleEvent::TYPE_GEOFENCE_EXIT,
                    VehicleEvent::TYPE_PANIC,
                    VehicleEvent::TYPE_OVERSPEED,
                    VehicleEvent::TYPE_POWER_CUT,
                    VehicleEvent::TYPE_COMM_LOST_MOVING,
                    VehicleEvent::TYPE_TAMPERING => 'fa-exclamation-triangle',
                    VehicleEvent::TYPE_DELAYED,
                    VehicleEvent::TYPE_GSM_WEAK,
                    VehicleEvent::TYPE_GPS_WEAK,
                    VehicleEvent::TYPE_COMM_LOST_IGNITION => 'fa-exclamation-circle',
                    VehicleEvent::TYPE_GEOFENCE_ENTER => 'fa-draw-polygon',
                    VehicleEvent::TYPE_STOPPED => 'fa-parking',
                    default => 'fa-car',
                },
                'gradient' => match ($event->severity()) {
                    'critical' => 'linear-gradient(135deg, #EF4444, #DC2626)',
                    'warning' => 'linear-gradient(135deg, #F59E0B, #D97706)',
                    default => 'linear-gradient(135deg, var(--primary-blue), var(--secondary-blue))',
                },
            ]);

        $activityItems = collect();
        if (Schema::hasTable(config('activitylog.table_name', 'activity_log'))) {
            $activityItems = Activity::where('log_name', 'device')
                ->where('subject_type', Device::class)
                ->whereIn('subject_id', $deviceIds)
                ->latest()
                ->limit(8)
                ->get()
                ->map(fn (Activity $activity) => [
                    'type' => 'activity',
                    'title' => $activity->description ?? 'Device activity',
                    'description' => data_get($activity->properties, 'device_name') ?: ($activity->description ?? ''),
                    'time' => $activity->created_at,
                    'icon' => 'fa-satellite',
                    'gradient' => 'linear-gradient(135deg, #10B981, #059669)',
                ]);
        }

        return $vehicleItems
            ->concat($activityItems)
            ->sortByDesc('time')
            ->take(8)
            ->values();
    }

    public function alertDeviceIds(Collection $devices, int $hours = 24): Collection
    {
        $ids = $devices->pluck('id');

        if ($ids->isEmpty()) {
            return collect();
        }

        $criticalTypes = [
            VehicleEvent::TYPE_GEOFENCE_EXIT,
            VehicleEvent::TYPE_PANIC,
            VehicleEvent::TYPE_POWER_CUT,
            VehicleEvent::TYPE_COMM_LOST_MOVING,
            VehicleEvent::TYPE_TAMPERING,
            VehicleEvent::TYPE_OVERSPEED,
        ];

        return $this->events
            ->recentForDevices($ids, 200)
            ->filter(fn (VehicleEvent $e) => in_array($e->type, $criticalTypes, true)
                && $e->occurred_at >= now()->subHours($hours))
            ->pluck('device_id')
            ->unique()
            ->values();
    }

    /**
     * Tracker dashboard with no tc_users row — no devices, maps, or GPS metrics.
     *
     * @return array<string, mixed>
     */
    public function emptyTrackerStats(): array
    {
        return [
            'devices' => collect(),
            'totalDevices' => 0,
            'activeDevices' => 0,
            'totalDistanceKm' => 0,
            'activeAlerts' => 0,
            'onlineNow' => 0,
            'vehicleStates' => ['running' => 0, 'parked' => 0, 'maintenance' => 0, 'alerts' => 0],
            'fleetCounts' => ['running' => 0, 'parked' => 0, 'idle' => 0, 'stopped' => 0, 'offline' => 0, 'with_gps' => 0],
            'recentDevices' => collect(),
            'activities' => collect(),
            'alertDeviceIds' => collect(),
            'activePercent' => 0,
            'onlinePercent' => 0,
            'alertsPercent' => 0,
            'distancePercent' => 0,
            'distanceTodayKm' => 0,
            'offlineNow' => 0,
            'chartData' => [
                'statusDonut' => ['running' => 0, 'parked' => 0, 'idle' => 0, 'offline' => 0],
                'activityArea' => ['labels' => [], 'values' => []],
                'alertsBar' => ['labels' => [], 'values' => []],
                'performanceLine' => ['labels' => [], 'gpsPings' => [], 'activeDevices' => []],
                'weeklyKm' => ['labels' => [], 'values' => []],
            ],
            'mapMarkers' => [],
        ];
    }

    public function getProfileStats(User $user): array
    {
        $devices = $this->resolveDashboardDevices($user);
        $this->positionLoader->attachLatestToMany($devices);
        $deviceIds = $devices->pluck('id');
        $fleetCounts = $this->mapStatus->fleetCounts($devices);
        $pageStats = $this->getDevicePageStats($devices);
        $heavy = $this->cachedHeavyMetrics($user, $devices, $deviceIds, $fleetCounts);

        return array_merge($pageStats, [
            'totalDistanceKm' => $heavy['totalDistanceKm'],
            'activeAlerts' => $heavy['activeAlerts'],
            'trackingDaysActive' => $deviceIds->isEmpty()
                ? 0
                : $this->metrics->activeTrackingDays($deviceIds),
            'geofenceCount' => $this->countGeofencesForDevices($devices),
            'memberDays' => max(1, $user->created_at?->diffInDays(now()) ?? 1),
        ]);
    }

    private function countGeofencesForDevices(Collection $devices): int
    {
        if ($devices->isEmpty()) {
            return 0;
        }

        $total = 0;

        foreach ($devices as $device) {
            $total += $this->geofences->forDevice($device)->count();
        }

        return $total;
    }

    public function getDevicePageStats(Collection $devices): array
    {
        $onlineNow = $this->countOnlineDevices($devices);
        $fleetCounts = $this->mapStatus->fleetCounts($devices);

        return [
            'totalDevices' => $devices->count(),
            'activeDevices' => $devices->where('status', 'active')->count(),
            'inactiveDevices' => $devices->where('status', 'inactive')->count(),
            'blockedDevices' => $devices->where('status', 'blocked')->count(),
            'onlineNow' => $onlineNow,
            'offlineNow' => $fleetCounts['offline'],
            'running' => $fleetCounts['running'],
            'parked' => $fleetCounts['parked'],
            'parkedIdle' => $fleetCounts['parked'] + $fleetCounts['idle'] + $fleetCounts['stopped'],
            'idle' => $fleetCounts['idle'],
            'maintenance' => $devices->whereIn('status', ['inactive', 'blocked'])->count(),
            'alerts' => $fleetCounts['alert'],
        ];
    }

    public function resolveDeviceStatus(Device $device, ?Collection $alertDeviceIds = null): array
    {
        $map = $this->mapStatus->resolve($device->latestLocation, $device);

        return $this->presentMapStatus($map['key'], $map['label']);
    }

    /**
     * Bootstrap badge/dot styling for map-aligned status keys.
     *
     * @return array{label: string, class: string, dot: string, key: string}
     */
    public function presentMapStatus(string $key, string $label): array
    {
        $presentation = match ($key) {
            'running' => ['class' => 'bg-success', 'dot' => 'bg-success'],
            'moving' => ['class' => 'bg-success', 'dot' => 'bg-success'],
            'idle' => ['class' => 'bg-warning', 'dot' => 'bg-warning'],
            'stopped' => ['class' => 'bg-warning', 'dot' => 'bg-warning'],
            'parked' => ['class' => 'bg-info', 'dot' => 'bg-info'],
            'delayed' => ['class' => 'bg-warning text-dark', 'dot' => 'bg-warning'],
            'stale' => ['class' => 'bg-warning text-dark', 'dot' => 'bg-warning'],
            'offline' => ['class' => 'bg-secondary', 'dot' => 'bg-secondary'],
            'alert' => ['class' => 'bg-danger', 'dot' => 'bg-danger'],
            'blocked' => ['class' => 'bg-dark', 'dot' => 'bg-dark'],
            default => ['class' => 'bg-secondary', 'dot' => 'bg-secondary'],
        };

        return [
            'label' => $label,
            'class' => $presentation['class'],
            'dot' => $presentation['dot'],
            'key' => $key,
        ];
    }

    private function haversineKm(float $lat1, float $lon1, float $lat2, float $lon2): float
    {
        $earthRadius = 6371;
        $dLat = deg2rad($lat2 - $lat1);
        $dLon = deg2rad($lon2 - $lon1);
        $a = sin($dLat / 2) ** 2
            + cos(deg2rad($lat1)) * cos(deg2rad($lat2)) * sin($dLon / 2) ** 2;

        return $earthRadius * 2 * atan2(sqrt($a), sqrt(1 - $a));
    }
}
