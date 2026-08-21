<?php

namespace App\Services;

use App\Contracts\Geofences\GeofenceStoreInterface;
use App\Contracts\Tracking\EventReaderInterface;
use App\Models\ContactMessage;
use App\Models\Device;
use App\Services\ActivityLogService;
use App\Services\Mobile\MobileMapStatusResolver;
use App\Services\Mobile\VehicleStatusSpec;
use App\Services\Tracking\DevicePositionLoader;
use App\Services\Tracking\TrackingMetricsService;
use App\Models\Subscription;
use App\Models\User;
use App\Models\VehicleEvent;
use App\Repositories\Tracking\TraccarEventMapper;
use App\Support\Traccar\TraccarMode;
use App\Support\Traccar\TraccarSchema;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Spatie\Activitylog\Models\Activity;

class AdminDashboardService
{
    /** Historical position-window metric for trend charts (30 min; matches VehicleStatusSpec::OFFLINE_SECONDS). */
    public const ONLINE_MINUTES = 30;

    public const RECENT_ACTIVITY_LIMIT = 5;

    public function __construct(
        private DevicePositionLoader $positionLoader,
        private TrackingMetricsService $metrics,
        private EventReaderInterface $events,
        private GeofenceStoreInterface $geofences,
        private ActivityLogService $activityLog,
        private MobileMapStatusResolver $statusResolver,
    ) {}

    public function getStats(): array
    {
        $devices = Device::query()->with('user')->get();
        $this->safe(fn () => $this->positionLoader->attachLatestToMany($devices), null);

        $totalUsers = (int) $this->safe(fn () => User::query()->appCustomers()->count(), 0);
        $totalAdmins = (int) $this->safe(fn () => User::query()->appAdmins()->count(), 0);
        $totalDevices = $devices->count();
        $activeDevices = $devices->where('status', 'active')->count();
        $inactiveDevices = $devices->where('status', 'inactive')->count();
        $blockedDevices = $devices->where('status', 'blocked')->count();

        // Use the canonical status engine so dashboard counts match the live map,
        // fleet list, and mobile app exactly.
        $online = 0;
        $moving = 0;
        foreach ($devices as $d) {
            if ($d->status !== 'active') {
                continue;
            }
            $resolved = $this->statusResolver->resolve($d->latestLocation, $d);
            if (($resolved['connectivity_tier'] ?? 'offline') !== 'offline') {
                $online++;
            }
            $key = VehicleStatusSpec::normalizeKey($resolved['key'] ?? '');
            if ($key === 'running' || $key === 'moving') {
                $moving++;
            }
        }

        $onlineNow = $online;
        $movingNow = $moving;

        $activeSubscriptions = (int) $this->safe(fn () => Subscription::where('status', 'active')
            ->whereNotNull('device_id')
            ->where(function ($q) {
                $q->whereNull('ends_at')->orWhere('ends_at', '>=', now()->startOfDay());
            })
            ->count(), 0);
        $totalSubscriptions = (int) $this->safe(fn () => Subscription::count(), 0);
        $expiredSubscriptions = (int) $this->safe(fn () => Subscription::where('status', 'expired')->count(), 0);

        $alertsToday = (int) $this->safe(fn () => $this->events->countSince(now()->startOfDay()), 0);
        $alertsWeek = (int) $this->safe(fn () => $this->events->countSince(now()->subDays(7)), 0);
        $geofenceCount = (int) $this->safe(fn () => $this->geofences->countAll(), 0);
        $dataPointsToday = (int) $this->safe(fn () => $this->metrics->positionCountSince(now()->startOfDay()), 0);
        $dataPointsWeek = (int) $this->safe(fn () => $this->metrics->positionCountSince(now()->subDays(7)), 0);

        $lastGpsAt = $this->safe(fn () => $this->metrics->lastPositionAt(), null);

        $unassignedDevices = (int) $this->safe(fn () => Device::query()->withoutTraccarOwner()->count(), 0);
        $pendingContacts = (int) $this->safe(function () {
            if (! Schema::hasTable('contact_messages')) {
                return 0;
            }

            return ContactMessage::where('status', 'new')->count();
        }, 0);

        $userGrowth = $this->percentChange(
            (int) $this->safe(fn () => User::query()->appCustomers()->registeredSince(now()->startOfMonth())->count(), 0),
            (int) $this->safe(fn () => User::query()->appCustomers()->registeredBetween(
                now()->subMonth()->startOfMonth(),
                now()->subMonth()->endOfMonth()
            )->count(), 0)
        );

        $deviceGrowth = $this->percentChange(
            (int) $this->safe(fn () => Device::query()->registeredSince(now()->startOfMonth())->count(), 0),
            (int) $this->safe(fn () => Device::query()->registeredBetween(
                now()->subMonth()->startOfMonth(),
                now()->subMonth()->endOfMonth()
            )->count(), 0)
        );

        $onlineYesterday = (int) $this->safe(fn () => $this->onlineDevicesAt(now()->subDay()), 0);
        $onlineChange = $this->percentChange($onlineNow, $onlineYesterday);

        return [
            'totalUsers' => $totalUsers,
            'totalAdmins' => $totalAdmins,
            'totalDevices' => $totalDevices,
            'activeDevices' => $activeDevices,
            'inactiveDevices' => $inactiveDevices,
            'blockedDevices' => $blockedDevices,
            'onlineNow' => $onlineNow,
            'movingNow' => $movingNow,
            'offlineDevices' => max(0, $activeDevices - $onlineNow),
            'activeSubscriptions' => $activeSubscriptions,
            'totalSubscriptions' => $totalSubscriptions,
            'expiredSubscriptions' => $expiredSubscriptions,
            'alertsToday' => $alertsToday,
            'alertsWeek' => $alertsWeek,
            'geofenceCount' => $geofenceCount,
            'dataPointsToday' => $dataPointsToday,
            'dataPointsWeek' => $dataPointsWeek,
            'unassignedDevices' => $unassignedDevices,
            'pendingContacts' => $pendingContacts,
            'lastGpsAt' => $lastGpsAt,
            'gpsLive' => $lastGpsAt && $lastGpsAt >= now()->subMinutes(10),
            'userGrowth' => $userGrowth,
            'deviceGrowth' => $deviceGrowth,
            'onlineChange' => $onlineChange,
            'chart' => $this->safe(fn () => $this->getChartData(7), [
                'labels' => [],
                'gpsPings' => [],
                'activeDevices' => [],
            ]),
            'recentActivities' => $this->safe(fn () => $this->getRecentActivities(), collect()),
            'recentDevices' => $devices->sortByDesc(fn (Device $d) => $d->latestLocation?->recorded_at ?? $d->created_at)->take(10)->values(),
            'eventsByType' => $this->safe(fn () => $this->eventsByType(7), collect()),
        ];
    }

    /**
     * Canonical device status badge — identical engine to the live map, fleet
     * list, and mobile app (Running/Stopped/Parked/Moving/Delayed/Stale/Offline).
     */
    public function deviceStatusLabel(Device $device): array
    {
        $resolved = $this->statusResolver->resolve($device->latestLocation, $device);
        $key = VehicleStatusSpec::normalizeKey($resolved['key'] ?? 'offline');

        return [
            'label' => $resolved['label'] ?? (string) __('app.map.status_offline'),
            'class' => self::statusBadgeClass($key),
        ];
    }

    private static function statusBadgeClass(string $key): string
    {
        return match ($key) {
            'running', 'moving' => 'badge-running',
            'stopped', 'idle' => 'badge-stopped',
            'parked', 'ignition_off' => 'badge-parked',
            'delayed' => 'badge-delayed',
            'stale' => 'badge-stale',
            'blocked', 'alert' => 'badge-blocked',
            'offline' => 'badge-offline',
            default => 'badge-inactive',
        };
    }

    private function onlineDevicesAt(Carbon $at): int
    {
        $from = $at->copy()->subMinutes(self::ONLINE_MINUTES);
        $to = $at;

        return $this->metrics->onlineDevicesAt($at, self::ONLINE_MINUTES);
    }

    private function percentChange(int|float $current, int|float $previous): array
    {
        if ($previous <= 0) {
            return [
                'value' => $current > 0 ? 100.0 : 0.0,
                'positive' => $current >= 0,
                'label' => $current > 0 ? 'new this period' : 'no change',
            ];
        }

        $pct = round((($current - $previous) / $previous) * 100, 1);

        return [
            'value' => abs($pct),
            'positive' => $pct >= 0,
            'label' => ($pct >= 0 ? '+' : '') . $pct . '% vs last month',
        ];
    }

    private function getChartData(int $days): array
    {
        return $this->metrics->positionChartData($days);
    }

    /**
     * @template T
     * @param  callable(): T  $callback
     * @param  T  $fallback
     * @return T
     */
    private function safe(callable $callback, mixed $fallback): mixed
    {
        try {
            return $callback();
        } catch (\Throwable $e) {
            report($e);

            return $fallback;
        }
    }

    private function getRecentActivities(): Collection
    {
        $actor = auth()->user();
        if (! $actor || ! Schema::hasTable('activity_log')) {
            return collect();
        }

        $query = $this->activityLog->baseQuery();
        $this->activityLog->applyTenantScope($query, $actor, null);

        return $query
            ->limit(self::RECENT_ACTIVITY_LIMIT)
            ->get()
            ->map(fn (Activity $log) => [
                'icon' => $this->auditActivityIcon($log->event),
                'color' => $this->auditActivityColor($log->event),
                'title' => $log->description,
                'desc' => $this->activityLog->subjectLabel($log),
                'time' => $log->created_at,
            ]);
    }

    private function auditActivityIcon(?string $event): string
    {
        return match ($event) {
            'created' => 'fa-plus',
            'updated' => 'fa-pen',
            'deleted' => 'fa-trash',
            'renewed' => 'fa-sync',
            default => 'fa-clipboard-list',
        };
    }

    private function auditActivityColor(?string $event): string
    {
        return match ($event) {
            'created' => 'var(--admin-success)',
            'updated' => 'var(--admin-primary)',
            'deleted' => 'var(--admin-danger)',
            'renewed' => 'var(--admin-info)',
            default => 'var(--admin-info)',
        };
    }

    private function eventsByType(int $days): Collection
    {
        if (TraccarMode::readsTraccar() && TraccarSchema::hasEvents()) {
            $mapper = app(TraccarEventMapper::class);
            $rows = DB::table(config('traccar.tables.events', 'tc_events'))
                ->where('eventtime', '>=', now()->subDays($days)->utc())
                ->select('type', DB::raw('COUNT(*) as total'))
                ->groupBy('type')
                ->orderByDesc('total')
                ->get();

            return $rows
                ->groupBy(fn ($row) => $mapper->reverseMapType((string) $row->type))
                ->map(fn ($group, $type) => (object) [
                    'type' => $type,
                    'total' => (int) $group->sum('total'),
                ])
                ->sortByDesc('total')
                ->values();
        }

        if (! Schema::hasTable('vehicle_events')) {
            return collect();
        }

        return VehicleEvent::query()
            ->where('occurred_at', '>=', now()->subDays($days))
            ->select('type', DB::raw('COUNT(*) as total'))
            ->groupBy('type')
            ->orderByDesc('total')
            ->get();
    }

    private function eventIcon(string $type): string
    {
        return match ($type) {
            VehicleEvent::TYPE_GEOFENCE_ENTER, VehicleEvent::TYPE_GEOFENCE_EXIT => 'fa-draw-polygon',
            VehicleEvent::TYPE_OVERSPEED => 'fa-tachometer-alt',
            VehicleEvent::TYPE_STOPPED => 'fa-parking',
            VehicleEvent::TYPE_RUNNING => 'fa-car',
            VehicleEvent::TYPE_PANIC => 'fa-exclamation-circle',
            default => 'fa-bell',
        };
    }

    private function eventColor(string $severity): string
    {
        return match ($severity) {
            'error' => 'var(--admin-danger)',
            'warning' => 'var(--admin-warning)',
            default => 'var(--admin-info)',
        };
    }
}
