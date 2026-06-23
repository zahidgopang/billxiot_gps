<?php

namespace App\Services;

use App\Contracts\Geofences\GeofenceStoreInterface;
use App\Contracts\Tracking\EventReaderInterface;
use App\Models\ContactMessage;
use App\Models\Device;
use App\Services\ActivityLogService;
use App\Services\Tracking\DevicePositionLoader;
use App\Services\Tracking\TrackingMetricsService;
use App\Models\Subscription;
use App\Models\User;
use App\Models\VehicleEvent;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Spatie\Activitylog\Models\Activity;

class AdminDashboardService
{
    public const ONLINE_MINUTES = 5;

    public const RECENT_ACTIVITY_LIMIT = 5;

    public function __construct(
        private DevicePositionLoader $positionLoader,
        private TrackingMetricsService $metrics,
        private EventReaderInterface $events,
        private GeofenceStoreInterface $geofences,
        private ActivityLogService $activityLog,
    ) {}

    public function getStats(): array
    {
        $cutoff = now()->subMinutes(self::ONLINE_MINUTES);
        $devices = Device::with(['user:id,name,email'])->get();
        $this->positionLoader->attachLatestToMany($devices);

        $totalUsers = User::query()->appCustomers()->count();
        $totalAdmins = User::query()->appAdmins()->count();
        $totalDevices = $devices->count();
        $activeDevices = $devices->where('status', 'active')->count();
        $inactiveDevices = $devices->where('status', 'inactive')->count();
        $blockedDevices = $devices->where('status', 'blocked')->count();

        $onlineNow = $devices->filter(function (Device $d) use ($cutoff) {
            return $d->status === 'active'
                && $d->latestLocation
                && $d->latestLocation->recorded_at >= $cutoff;
        })->count();

        $movingNow = $devices->filter(function (Device $d) use ($cutoff) {
            return $d->latestLocation
                && $d->latestLocation->recorded_at >= $cutoff
                && (float) ($d->latestLocation->speed ?? 0) > UserDashboardService::MOVING_SPEED_KMH;
        })->count();

        $activeSubscriptions = Subscription::where('status', 'active')
            ->whereNotNull('device_id')
            ->where(function ($q) {
                $q->whereNull('ends_at')->orWhere('ends_at', '>=', now()->startOfDay());
            })
            ->count();
        $totalSubscriptions = Subscription::count();
        $expiredSubscriptions = Subscription::where('status', 'expired')->count();

        $alertsToday = $this->events->countSince(now()->startOfDay());
        $alertsWeek = $this->events->countSince(now()->subDays(7));
        $geofenceCount = $this->geofences->countAll();
        $dataPointsToday = $this->metrics->positionCountSince(now()->startOfDay());
        $dataPointsWeek = $this->metrics->positionCountSince(now()->subDays(7));

        $lastGpsAt = $this->metrics->lastPositionAt();

        $unassignedDevices = Device::query()->withoutTraccarOwner()->count();
        $pendingContacts = ContactMessage::where('status', 'new')->count();

        $userGrowth = $this->percentChange(
            User::query()->appCustomers()->registeredSince(now()->startOfMonth())->count(),
            User::query()->appCustomers()->registeredBetween(
                now()->subMonth()->startOfMonth(),
                now()->subMonth()->endOfMonth()
            )->count()
        );

        $deviceGrowth = $this->percentChange(
            Device::query()->registeredSince(now()->startOfMonth())->count(),
            Device::query()->registeredBetween(
                now()->subMonth()->startOfMonth(),
                now()->subMonth()->endOfMonth()
            )->count()
        );

        $onlineYesterday = $this->onlineDevicesAt(now()->subDay());
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
            'chart' => $this->getChartData(30),
            'recentActivities' => $this->getRecentActivities(),
            'recentDevices' => $devices->sortByDesc(fn (Device $d) => $d->latestLocation?->recorded_at ?? $d->created_at)->take(10)->values(),
            'eventsByType' => $this->eventsByType(7),
        ];
    }

    public function deviceStatusLabel(Device $device): array
    {
        $cutoff = now()->subMinutes(self::ONLINE_MINUTES);
        $latest = $device->latestLocation;

        if ($device->status === 'blocked') {
            return ['label' => 'Blocked', 'class' => 'badge-blocked'];
        }
        if ($device->status === 'inactive') {
            return ['label' => 'Inactive', 'class' => 'badge-inactive'];
        }
        if (! $latest || $latest->recorded_at < $cutoff) {
            return ['label' => 'Offline', 'class' => 'badge-offline'];
        }
        if ((float) ($latest->speed ?? 0) > UserDashboardService::MOVING_SPEED_KMH) {
            return ['label' => 'Moving', 'class' => 'badge-active'];
        }

        return ['label' => 'Online', 'class' => 'badge-active'];
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

    private function getRecentActivities(): Collection
    {
        $actor = auth()->user();
        if (! $actor) {
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
        if (\App\Support\Traccar\TraccarMode::readsTraccar() && \App\Support\Traccar\TraccarSchema::hasEvents()) {
            return DB::table(config('traccar.tables.events', 'tc_events'))
                ->where('eventtime', '>=', now()->subDays($days))
                ->get()
                ->groupBy(fn ($row) => app(\App\Repositories\Tracking\TraccarEventMapper::class)
                    ->reverseMapType((string) $row->type))
                ->map(fn ($group, $type) => (object) ['type' => $type, 'total' => $group->count()])
                ->values();
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
