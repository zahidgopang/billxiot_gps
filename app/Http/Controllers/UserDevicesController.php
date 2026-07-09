<?php

namespace App\Http\Controllers;

use App\Models\Device;
use App\Services\DeviceSubscriptionService;
use App\Services\FleetMapDeviceService;
use App\Services\Mobile\MapRenderingSpec;
use App\Services\Tracking\DeviceMapAppearanceService;
use App\Services\Tracking\UserDeviceLabelService;
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
            $rbac = app(\App\Services\Authorization\RbacService::class);
            $trackingGate = app(TraccarTrackingGate::class);
            $allDevices = $user
                ->trackerDevicesQuery()
                ->with(['subscription'])
                ->orderByDesc('id')
                ->get();

            $isEndUser = $rbac->isEndUser($user);
            $requireSubscriptionForList = $isEndUser && ! $rbac->bypassesSubscriptionRestrictions($user);

            $trackableForFleet = $trackingGate->filterTrackable(
                $user,
                $allDevices,
                requireSubscription: true,
            );
            $fleetMapEligibleCount = $trackableForFleet->count();

            $devices = $requireSubscriptionForList
                ? $trackableForFleet
                : $trackingGate->filterTrackable(
                    $user,
                    $allDevices,
                    requireSubscription: false,
                );

            app(DevicePositionLoader::class)->attachLatestToMany($devices);

            $alertDeviceIds = $dashboard->alertDeviceIds($devices);
            $deviceAccessMap = app(\App\Services\DeviceAccessService::class)
                ->evaluateMany($user, $devices);

            return view('user.devices', array_merge(
                $dashboard->getDevicePageStats($devices),
                [
                    'devices' => $devices,
                    'alertDeviceIds' => $alertDeviceIds,
                    'dashboardService' => $dashboard,
                    'subscriptionService' => app(DeviceSubscriptionService::class),
                    'fleetMapEligibleCount' => $fleetMapEligibleCount,
                    'deviceAccessMap' => $deviceAccessMap,
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
                'deviceAccessMap' => [],
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
        ])->header('Cache-Control', 'no-store, no-cache, must-revalidate, max-age=0');
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
        ])->header('Cache-Control', 'no-store, no-cache, must-revalidate, max-age=0');
    }

    public function updateVehicleLabel(Request $request, Device $device): JsonResponse
    {
        $user = Auth::user();

        try {
            $labels = app(UserDeviceLabelService::class)->update(
                $user,
                $device,
                $request->only(['vehicle_name', 'vehicle_number'])
            );
        } catch (\Illuminate\Validation\ValidationException $e) {
            $status = collect($e->errors())->has('device') ? 404 : 422;

            return response()->json([
                'success' => false,
                'message' => collect($e->errors())->flatten()->first() ?: __('app.user.devices.vehicle_label_save_failed'),
                'errors' => $e->errors(),
            ], $status);
        }

        return response()->json([
            'success' => true,
            'labels' => $labels,
            'message' => __('app.user.devices.vehicle_label_saved'),
        ]);
    }

    public function updateMapAppearance(Request $request, Device $device): JsonResponse
    {
        $user = Auth::user();
        if (! app(\App\Services\Tracking\DeviceMapIconAuthorization::class)->canEditAppearance($user, $device)) {
            return response()->json(['success' => false, 'message' => __('app.common.not_found')], 404);
        }

        try {
            $appearance = app(DeviceMapAppearanceService::class)->update(
                $user,
                $device,
                $request->only([
                    'vehicle_type',
                    'map_builtin_icon_path',
                    'map_marker_style',
                    'map_marker_size',
                    'map_icon_rotation_enabled',
                    'map_icon_source',
                ])
            );
        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => collect($e->errors())->flatten()->first() ?: __('app.map.marker_appearance_save_failed'),
                'errors' => $e->errors(),
            ], 422);
        }

        return response()->json([
            'success' => true,
            'appearance' => $appearance,
            'message' => __('app.map.marker_appearance_saved'),
        ]);
    }

    public function updateMapAppearanceBulk(Request $request): JsonResponse
    {
        $user = Auth::user();
        $auth = app(\App\Services\Tracking\DeviceMapIconAuthorization::class);

        if (! $auth->hasAnyAppearancePermission($user)) {
            return response()->json(['success' => false, 'message' => __('app.map.icon_permission_denied')], 403);
        }

        $deviceIds = (array) $request->input('device_ids', []);
        if ($deviceIds === []) {
            return response()->json([
                'success' => false,
                'message' => __('app.user.devices.icon_select_vehicles'),
            ], 422);
        }

        $result = app(DeviceMapAppearanceService::class)->updateMany(
            $user,
            $deviceIds,
            $request->only([
                'vehicle_type',
                'map_builtin_icon_path',
                'map_marker_style',
                'map_marker_size',
                'map_icon_rotation_enabled',
                'map_icon_source',
            ])
        );

        if ($result['updated'] === 0) {
            return response()->json([
                'success' => false,
                'message' => $result['failed'][0]['message'] ?? __('app.map.marker_appearance_save_failed'),
                'failed' => $result['failed'],
            ], 422);
        }

        return response()->json([
            'success' => true,
            'updated' => $result['updated'],
            'failed' => $result['failed'],
            'message' => __('app.user.devices.icon_bulk_saved', ['count' => $result['updated']]),
        ]);
    }

    public function uploadMapCustomIconBulk(Request $request): JsonResponse
    {
        $user = Auth::user();
        $auth = app(\App\Services\Tracking\DeviceMapIconAuthorization::class);

        if (! $auth->hasUploadCustomIconPermission($user)) {
            return response()->json(['success' => false, 'message' => __('app.map.icon_upload_permission_denied')], 403);
        }

        $deviceIds = (array) $request->input('device_ids', []);
        $file = $request->file('icon');

        if ($deviceIds === [] || ! $file) {
            return response()->json([
                'success' => false,
                'message' => __('app.user.devices.icon_select_vehicles'),
            ], 422);
        }

        $result = app(DeviceMapAppearanceService::class)->uploadCustomIconMany($user, $deviceIds, $file);

        if ($result['updated'] === 0) {
            return response()->json([
                'success' => false,
                'message' => $result['failed'][0]['message'] ?? __('app.map.icon_upload_permission_denied'),
                'failed' => $result['failed'],
            ], 422);
        }

        return response()->json([
            'success' => true,
            'updated' => $result['updated'],
            'failed' => $result['failed'],
            'message' => __('app.user.devices.icon_bulk_uploaded', ['count' => $result['updated']]),
        ]);
    }

    public function uploadMapCustomIcon(Request $request, Device $device): JsonResponse
    {
        $user = Auth::user();

        try {
            $appearance = app(DeviceMapAppearanceService::class)->uploadCustomIcon(
                $user,
                $device,
                $request->file('icon'),
                $request->input('rotation_offset', $request->input('map_icon_rotation_offset', 0))
            );
        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => collect($e->errors())->flatten()->first(),
                'errors' => $e->errors(),
            ], 422);
        }

        return response()->json([
            'success' => true,
            'appearance' => $appearance,
            'upload_meta' => $appearance['upload_meta'] ?? null,
            'message' => __('app.map.custom_icon_uploaded'),
        ]);
    }

    public function deleteMapCustomIcon(Request $request, Device $device): JsonResponse
    {
        $user = Auth::user();

        try {
            $appearance = app(DeviceMapAppearanceService::class)->revertToDefaultIcon($user, $device);
        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => collect($e->errors())->flatten()->first(),
            ], 422);
        }

        return response()->json([
            'success' => true,
            'appearance' => $appearance,
            'message' => __('app.map.custom_icon_removed'),
        ]);
    }
}
