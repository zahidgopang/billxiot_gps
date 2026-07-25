<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Device;
use App\Services\Authorization\TenantScopeService;
use App\Services\DeviceSubscriptionService;
use App\Services\Tracking\DevicePositionLoader;
use App\Services\UserDashboardService;
use Illuminate\Http\Request;

class LocationHistoryController extends Controller
{
    public function __construct(
        private TenantScopeService $tenantScope,
    ) {}

    public function index(Request $request, UserDashboardService $dashboard, DeviceSubscriptionService $subscriptions)
    {
        $q = Device::inTracker()
            ->with(['user', 'subscription'])
            ->orderByDesc('id');

        $this->tenantScope->scopeDevices($q, $request->user());

        if ($search = $request->query('q')) {
            $q->where(function ($w) use ($search) {
                $w->whereImeiLike("%{$search}%")
                    ->orWhere('name', 'like', "%{$search}%")
                    ->orWhereHas('user', fn ($u) => $u->where('name', 'like', "%{$search}%")->orWhere('email', 'like', "%{$search}%"));
            });
        }

        if ($request->filled('status')) {
            $q->whereAppStatus($request->status);
        }

        // KPIs must cover the full filtered fleet (paginator total), not only the current page.
        $fleetForStats = (clone $q)->get();
        app(DevicePositionLoader::class)->attachLatestToMany($fleetForStats);
        $stats = $dashboard->getDevicePageStats($fleetForStats);

        $devices = $q->paginate(20)->withQueryString();
        app(DevicePositionLoader::class)->attachLatestToMany($devices->getCollection());
        $alertDeviceIds = $dashboard->alertDeviceIds($devices->getCollection());

        return view('admin.locations.index', [
            'devices' => $devices,
            'dashboardService' => $dashboard,
            'subscriptionService' => $subscriptions,
            'alertDeviceIds' => $alertDeviceIds,
            'stats' => $stats,
            'panel' => $request->routeIs('client.*') ? 'client' : 'admin',
        ]);
    }

    public function liveJson(Request $request, UserDashboardService $dashboard)
    {
        $ids = collect(explode(',', (string) $request->query('ids', '')))
            ->map(fn ($id) => (int) $id)
            ->filter(fn ($id) => $id > 0)
            ->unique()
            ->values()
            ->take(50);

        if ($ids->isEmpty()) {
            return response()->json([
                'devices' => [],
                'stats' => null,
            ]);
        }

        $devices = Device::inTracker()
            ->whereIn('id', $ids)
            ->get();

        $allowedIds = $this->tenantScope->visibleDeviceIdsForPanel($request->user());
        if ($allowedIds !== null) {
            $devices = $devices->filter(fn (Device $d) => in_array((int) $d->id, $allowedIds, true))->values();
        }

        $devices = $devices->sortBy(fn (Device $d) => $ids->search($d->id))->values();

        app(DevicePositionLoader::class)->attachLatestToMany($devices);
        $alertDeviceIds = $dashboard->alertDeviceIds($devices);

        $devicesPayload = $devices->map(function (Device $device) use ($dashboard, $alertDeviceIds) {
            $liveStatus = $dashboard->resolveDeviceStatus($device, $alertDeviceIds);
            $latest = $device->latestLocation;

            return [
                'id' => $device->id,
                'live_status' => $liveStatus,
                'status_key' => $liveStatus['key'] ?? null,
                'lat' => $latest ? (float) $latest->lat : null,
                'lng' => $latest ? (float) $latest->lng : null,
                'speed' => $latest ? round((float) ($latest->speed ?? 0), 0) : null,
                'position_id' => $latest?->id,
                'recorded_at' => $latest?->recorded_at?->toIso8601String(),
                'recorded_at_human' => $latest?->recorded_at
                    ? app_datetime_format($latest->recorded_at)
                    : __('app.common.no_data'),
            ];
        })->values();

        // Row payloads only — page-scoped stats would overwrite fleet KPIs (e.g. 20 vs 87).
        return response()->json([
            'devices' => $devicesPayload,
            'stats' => null,
        ]);
    }
}
