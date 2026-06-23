<?php

namespace App\Http\Controllers;

use App\Models\Device;
use App\Services\DeviceSubscriptionService;
use App\Services\FleetMapDeviceService;
use App\Services\Mobile\MapRenderingSpec;
use App\Services\Traccar\TraccarTrackingGate;
use App\Services\Tracking\DevicePositionLoader;
use App\Services\UserDashboardService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;

class UserDevicesController extends Controller
{
    /**
     * Display a listing of user devices.
     */
    public function index(UserDashboardService $dashboard)
    {
        try {
            $user = Auth::user();
            $allDevices = $user
                ->trackerDevicesQuery()
                ->with(['subscription'])
                ->orderByDesc('id')
                ->get();

            $trackingGate = app(TraccarTrackingGate::class);
            $fleetMapEligibleCount = $trackingGate
                ->filterTrackable($user, $allDevices, requireSubscription: true)
                ->count();
            $devices = $trackingGate->filterTrackable($user, $allDevices, requireSubscription: false);

            app(DevicePositionLoader::class)->attachLatestToMany($devices);

            $alertDeviceIds = $dashboard->alertDeviceIds($devices);

            return view('user.devices', array_merge(
                $dashboard->getDevicePageStats($devices),
                [
                    'devices' => $devices,
                    'alertDeviceIds' => $alertDeviceIds,
                    'dashboardService' => $dashboard,
                    'subscriptionService' => app(DeviceSubscriptionService::class),
                    'fleetMapEligibleCount' => $fleetMapEligibleCount,
                ]
            ));
        } catch (\Exception $e) {
            Log::error('Error loading devices: ' . $e->getMessage());

            return view('user.devices', [
                'devices' => collect(),
                'totalDevices' => 0,
                'activeDevices' => 0,
                'inactiveDevices' => 0,
                'blockedDevices' => 0,
                'onlineNow' => 0,
                'running' => 0,
                'parked' => 0,
                'alerts' => 0,
                'alertDeviceIds' => collect(),
                'dashboardService' => $dashboard,
                'fleetMapEligibleCount' => 0,
            ]);
        }
    }

    /**
     * Cluster fleet map — active devices with active subscription only.
     */
    public function fleetMap(UserDashboardService $dashboard, FleetMapDeviceService $fleetMap)
    {
        $user = Auth::user();
        $devices = $fleetMap->attachPositions($fleetMap->loadForEndUser($user));

        if ($devices->isEmpty()) {
            return redirect()
                ->route('user.devices.index')
                ->with('access_denied_title', __('app.user.devices.fleet_map_unavailable_title'))
                ->with('access_denied_message', __('app.user.devices.fleet_map_unavailable_message'));
        }

        $payload = $fleetMap->buildPayload(
            $devices,
            $dashboard,
            fn (Device $device) => route('user.devices.launch-map', $device),
        );

        return view('user.fleet-map', [
            'devices' => $devices,
            'mapSpec' => MapRenderingSpec::toArray(),
            'initialPayload' => $payload,
            'stats' => $dashboard->getDevicePageStats($devices),
        ]);
    }

    public function fleetMapLiveJson(UserDashboardService $dashboard, FleetMapDeviceService $fleetMap): JsonResponse
    {
        $user = Auth::user();
        $devices = $fleetMap->attachPositions($fleetMap->loadForEndUser($user));

        $payload = $fleetMap->buildPayload(
            $devices,
            $dashboard,
            fn (Device $device) => route('user.devices.launch-map', $device),
        );

        return response()->json([
            'devices' => $payload,
            'stats' => $dashboard->getDevicePageStats($devices),
        ]);
    }

    /**
     * Live telemetry for devices table (polled from the browser).
     */
    public function liveJson(Request $request, UserDashboardService $dashboard): JsonResponse
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

        $user = Auth::user();
        $devices = $user
            ->trackerDevicesQuery()
            ->whereIn('id', $ids)
            ->get()
            ->sortBy(fn (Device $d) => $ids->search($d->id))
            ->values();

        $devices = app(TraccarTrackingGate::class)->filterTrackable($user, $devices, requireSubscription: false);

        app(DevicePositionLoader::class)->attachLatestToMany($devices);
        $alertDeviceIds = $dashboard->alertDeviceIds($devices);

        $devicesPayload = $devices->map(function (Device $device) use ($dashboard, $alertDeviceIds) {
            $liveStatus = $dashboard->resolveDeviceStatus($device, $alertDeviceIds);
            $latest = $device->latestLocation;

            return [
                'id' => $device->id,
                'live_status' => $liveStatus,
                'speed' => $latest ? round((float) ($latest->speed ?? 0), 0) : null,
                'recorded_at' => $latest?->recorded_at?->toIso8601String(),
                'recorded_at_human' => $latest?->recorded_at
                    ? app_datetime_format($latest->recorded_at)
                    : __('app.user.devices.no_data_yet'),
            ];
        })->values();

        return response()->json([
            'devices' => $devicesPayload,
            'stats' => $dashboard->getDevicePageStats($devices),
        ]);
    }
}
