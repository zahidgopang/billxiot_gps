<?php

namespace App\Http\Controllers\Api\Mobile;

use App\Contracts\Tracking\EventReaderInterface;
use App\Contracts\Tracking\PositionReaderInterface;
use App\Http\Controllers\Controller;
use App\Http\Concerns\ResolvesHistoryDateRange;
use App\Http\Concerns\ResolvesMobileDevice;
use App\Http\Concerns\RespondsWithMobileJson;
use App\Models\VehicleEvent;
use App\Models\Device;
use App\Services\Authorization\RbacService;
use App\Services\Mobile\MobileDevicePresenter;
use App\Services\Mobile\MobileRouteAnalyticsService;
use App\Services\Routes\TripManagementService;
use App\Services\Tracking\DeviceHistoryFetcher;
use App\Services\Tracking\DevicePositionLoader;
use App\Services\Tracking\DeviceMapAppearanceService;
use App\Services\Tracking\GlobalTrackingService;
use App\Services\UserDashboardService;
use App\Services\VehicleEventService;
use Illuminate\Http\Request;

class DeviceController extends Controller
{
    use ResolvesHistoryDateRange;
    use ResolvesMobileDevice;
    use RespondsWithMobileJson;

    public function __construct(
        private MobileDevicePresenter $presenter,
        private DevicePositionLoader $positionLoader,
        private PositionReaderInterface $positions,
        private MobileRouteAnalyticsService $routeAnalytics,
        private EventReaderInterface $events,
        private UserDashboardService $dashboard,
        private DeviceHistoryFetcher $historyFetcher,
        private GlobalTrackingService $tracking,
    ) {}

    public function index(Request $request)
    {
        $user = $request->user();
        $rbac = app(RbacService::class);
        $devices = $rbac->isEndUser($user)
            ? $this->tracking->linkedDevicesForActor($user)
            : $this->tracking->devicesForActor($user);
        $this->positionLoader->attachLatestToMany($devices);
        $alertIds = $this->dashboard->alertDeviceIds(
            $rbac->isEndUser($user)
                ? $this->tracking->subscribedDevicesForEndUser($user)
                : $devices
        );

        $items = $devices->map(function ($device) use ($alertIds) {
            try {
                return $this->presenter->listItem($device, $alertIds);
            } catch (\Throwable $e) {
                report($e);

                return null;
            }
        })->filter()->values();

        return $this->mobileSuccess($items);
    }

    public function show(Request $request, int $id)
    {
        $device = $this->findLinkedMobileDevice($request->user(), $id);
        $this->positionLoader->attachLatest($device);
        $alertIds = $this->dashboard->alertDeviceIds(collect([$device]));

        return $this->mobileSuccess($this->presenter->detail($device, $alertIds));
    }

    public function live(Request $request, int $id)
    {
        $device = $this->findMobileDevice($request->user(), $id);
        $this->positionLoader->attachLatest($device);
        $latest = $device->latestLocation;

        $payload = $this->presenter->livePosition($device);

        if (! $payload) {
            return $this->mobileError('No live position available', 404, 'no_data');
        }

        $payload['route_trip'] = $this->routeTripPayloadForMobile($request, $device, $latest);

        return $this->mobileSuccess($payload)
            ->header('Cache-Control', 'no-store, no-cache, must-revalidate, max-age=0');
    }

    public function completeTrip(Request $request, int $id)
    {
        $device = $this->findMobileDevice($request->user(), $id);
        $this->positionLoader->attachLatest($device);
        $latest = $device->latestLocation;

        try {
            $trip = app(\App\Services\Routes\TripManagementService::class)
                ->markCompleted($device, $request->user());
        } catch (\Throwable $e) {
            return $this->mobileError($e->getMessage(), 422);
        }

        return $this->mobileSuccess([
            'trip' => [
                'id' => $trip->id,
                'status' => $trip->status,
            ],
            'route_trip' => $this->routeTripPayloadForMobile($request, $device, $latest),
            'message' => (string) __('app.routes.trip_marked_completed'),
        ]);
    }

    public function startNewTrip(Request $request, int $id)
    {
        $device = $this->findMobileDevice($request->user(), $id);
        $this->positionLoader->attachLatest($device);
        $latest = $device->latestLocation;

        try {
            $trip = app(\App\Services\Routes\TripManagementService::class)->startNewTrip(
                $device,
                $latest ? (float) $latest->lat : null,
                $latest ? (float) $latest->lng : null,
                $latest ? (float) ($latest->speed ?? 0) : null,
                $request->user(),
            );
        } catch (\Throwable $e) {
            return $this->mobileError($e->getMessage(), 422);
        }

        $routeTrip = $this->routeTripPayloadForMobile($request, $device, $latest);

        $message = $trip->started_at
            ? (string) __('app.routes.trip_started')
            : (string) __('app.routes.trip_armed_waiting_start');

        return $this->mobileSuccess([
            'trip' => [
                'id' => $trip->id,
                'status' => $trip->status,
            ],
            'route_trip' => $routeTrip,
            'message' => $message,
        ]);
    }

    public function history(Request $request, int $id)
    {
        $device = $this->findMobileDevice($request->user(), $id);
        $range = $this->resolveHistoryRange($request);
        $explicitRange = trim((string) ($request->query('from', $request->input('from', '')))) !== '';

        $result = $this->historyFetcher->fetch(
            $device,
            $range['from'],
            $range['to'],
            $explicitRange
        );

        $locations = $result['locations'];
        $stats = $this->routeAnalytics->analyze($locations);
        $statuses = $this->routeAnalytics->pointStatuses($locations);

        $points = $locations->values()->map(fn ($loc, int $index) => array_merge([
            'lat' => (float) $loc->lat,
            'lng' => (float) $loc->lng,
            'speed' => (float) ($loc->speed ?? 0),
            'heading' => (float) ($loc->heading ?? 0),
            'ignition' => (bool) $loc->ignition,
            'acc' => (bool) ($loc->acc ?? false),
            'battery' => $loc->battery_level,
            'battery_level' => $loc->battery_level,
            'gsm_signal' => $loc->gsm_signal,
            'gps_signal' => $loc->gps_signal,
            'satellites' => $loc->satellites,
            'odometer' => $loc->odometer,
            'odometer_km' => \App\Support\Tracking\TelemetryFormatter::odometerKm($loc->odometer),
            'altitude' => $loc->altitude !== null ? round((float) $loc->altitude) : null,
            'power_cut' => (bool) $loc->power_cut,
            'panic' => (bool) $loc->panic,
            'gps_fix' => $loc->gps_fix,
            'recorded_at' => app_datetime_api($loc->recorded_at),
            'time' => app_datetime_api($loc->recorded_at),
            'timestamp' => app_datetime_format($loc->recorded_at, 'log'),
            'recorded_at_display' => app_datetime_format($loc->recorded_at),
            'position_id' => (int) ($loc->id ?? 0),
        ], $statuses[$index] ?? []))->values();

        return $this->mobileSuccess([
            'polyline' => $points,
            'stops' => $stats['stops'],
            'moving_points' => $stats['moving_points'],
            'idle_points' => $stats['idle_points'],
            'timeline' => $stats['timeline'] ?? [],
            'stats' => [
                'total_distance_km' => $stats['total_distance_km'],
                'moving_time_seconds' => max(0, (int) ($stats['moving_time_seconds'] ?? 0)),
                'idle_time_seconds' => max(0, (int) ($stats['idle_time_seconds'] ?? 0)),
                'parking_time_seconds' => max(0, (int) ($stats['parking_time_seconds'] ?? 0)),
                'stopped_time_seconds' => max(0, (int) ($stats['stopped_time_seconds'] ?? 0)),
                'offline_time_seconds' => max(0, (int) ($stats['offline_time_seconds'] ?? 0)),
                'max_speed_kmh' => $stats['max_speed_kmh'],
                'average_speed_kmh' => $stats['average_speed_kmh'],
                'total_duration_seconds' => max(0, (int) ($stats['total_duration_seconds'] ?? 0)),
                'start_time' => $stats['start_time'] ?? null,
                'end_time' => $stats['end_time'] ?? null,
                'stop_count' => $stats['stop_count'] ?? count($stats['stops'] ?? []),
            ],
            'from' => app_datetime_api($range['from']),
            'to' => app_datetime_api($range['to']),
            'history_fallback' => $result['used_fallback'] ? $result['fallback_reason'] : null,
            'used_fallback' => $result['used_fallback'],
        ]);
    }

    public function routeSummary(Request $request, int $id)
    {
        $device = $this->findMobileDevice($request->user(), $id);
        $range = $this->resolveHistoryRange($request);
        $explicitRange = trim((string) ($request->query('from', $request->input('from', '')))) !== '';

        $result = $this->historyFetcher->fetch(
            $device,
            $range['from'],
            $range['to'],
            $explicitRange
        );

        $locations = $result['locations'];
        $stats = $this->routeAnalytics->analyze($locations);

        $startTime = $stats['start_time'] ?? null;
        $endTime = $stats['end_time'] ?? null;
        if (! $startTime && $locations->isNotEmpty()) {
            $startTime = app_datetime_api($locations->first()->recorded_at);
        }
        if (! $endTime && $locations->isNotEmpty()) {
            $endTime = app_datetime_api($locations->last()->recorded_at);
        }

        return $this->mobileSuccess([
            'total_distance_km' => $stats['total_distance_km'],
            'moving_time_seconds' => max(0, (int) ($stats['moving_time_seconds'] ?? 0)),
            'stopped_time_seconds' => max(0, (int) ($stats['stopped_time_seconds'] ?? 0)),
            'idle_time_seconds' => max(0, (int) ($stats['idle_time_seconds'] ?? 0)),
            'parking_time_seconds' => max(0, (int) ($stats['parking_time_seconds'] ?? 0)),
            'offline_time_seconds' => max(0, (int) ($stats['offline_time_seconds'] ?? 0)),
            'max_speed_kmh' => $stats['max_speed_kmh'],
            'average_speed_kmh' => $stats['average_speed_kmh'],
            'total_duration_seconds' => max(0, (int) ($stats['total_duration_seconds'] ?? 0)),
            'start_time' => $startTime,
            'end_time' => $endTime,
            'stop_count' => $stats['stop_count'] ?? count($stats['stops'] ?? []),
            'timeline' => $stats['timeline'] ?? [],
            'history_fallback' => $result['used_fallback'] ? $result['fallback_reason'] : null,
            'used_fallback' => $result['used_fallback'],
        ]);
    }

    public function events(Request $request, int $id)
    {
        $device = $this->findMobileDevice($request->user(), $id);
        $range = $this->resolveHistoryRange($request);

        $types = [
            VehicleEvent::TYPE_IGNITION,
            VehicleEvent::TYPE_OVERSPEED,
            VehicleEvent::TYPE_STOPPED,
            VehicleEvent::TYPE_GEOFENCE_ENTER,
            VehicleEvent::TYPE_GEOFENCE_EXIT,
            VehicleEvent::TYPE_PANIC,
            VehicleEvent::TYPE_LOW_BATTERY,
            VehicleEvent::TYPE_POWER_CUT,
        ];

        $collection = $this->events->forDevice(
            $device,
            $range['from'],
            $range['to'],
            $types,
            min(200, max(1, (int) $request->query('limit', 100)))
        );

        return $this->mobileSuccess(
            $collection->map(fn (VehicleEvent $event) => $event->toAlertArray())->values()
        );
    }

    public function mapAppearanceOptions(Request $request)
    {
        $deviceId = $request->query('device_id');
        $device = null;
        if ($deviceId) {
            try {
                $device = $this->findMobileDevice($request->user(), (int) $deviceId);
            } catch (\Throwable) {
                $device = null;
            }
        }

        return $this->mobileSuccess(
            app(DeviceMapAppearanceService::class)->mobileUploadOptions($request->user(), $device)
        );
    }

    public function updateMapAppearance(Request $request, int $id)
    {
        $device = $this->findMobileDevice($request->user(), $id);

        try {
            $appearance = app(DeviceMapAppearanceService::class)->update(
                $request->user(),
                $device,
                $request->only([
                    'map_marker_size',
                    'map_icon_rotation_enabled',
                ])
            );
        } catch (\Illuminate\Validation\ValidationException $e) {
            return $this->mobileError(
                collect($e->errors())->flatten()->first() ?: __('app.map.marker_appearance_save_failed'),
                422
            );
        }

        return $this->mobileSuccess([
            'appearance' => $appearance,
            'message' => (string) __('app.map.marker_appearance_saved'),
        ]);
    }

    public function uploadMapCustomIcon(Request $request, int $id)
    {
        $device = $this->findMobileDevice($request->user(), $id);

        try {
            $file = $request->file('icon');
            if (! $file instanceof \Illuminate\Http\UploadedFile) {
                throw \Illuminate\Validation\ValidationException::withMessages([
                    'icon' => [__('validation.required', ['attribute' => 'icon'])],
                ]);
            }

            $appearanceService = app(DeviceMapAppearanceService::class);
            $appearance = $appearanceService->uploadCustomIcon(
                $request->user(),
                $device,
                $file
            );
            $uploadMeta = $appearance['upload_meta'] ?? null;

            if ($request->filled('map_marker_size')) {
                $appearance = $appearanceService->update(
                    $request->user(),
                    $device->fresh(),
                    $request->only(['map_marker_size'])
                );
            }
        } catch (\Illuminate\Validation\ValidationException $e) {
            return $this->mobileError(collect($e->errors())->flatten()->first(), 422);
        }

        return $this->mobileSuccess([
            'appearance' => $appearance,
            'upload_meta' => $uploadMeta ?? ($appearance['upload_meta'] ?? null),
            'message' => (string) __('app.map.custom_icon_uploaded'),
        ]);
    }

    public function deleteMapCustomIcon(Request $request, int $id)
    {
        $device = $this->findMobileDevice($request->user(), $id);

        try {
            $appearance = app(DeviceMapAppearanceService::class)->revertToDefaultIcon($request->user(), $device);
        } catch (\Illuminate\Validation\ValidationException $e) {
            return $this->mobileError(collect($e->errors())->flatten()->first(), 422);
        }

        return $this->mobileSuccess([
            'appearance' => $appearance,
            'message' => (string) __('app.map.custom_icon_removed'),
        ]);
    }

    private function routeTripPayloadForMobile(Request $request, Device $device, mixed $latest): ?array
    {
        $rbac = app(RbacService::class);
        $user = $request->user();
        $canRouteProgress = $rbac->hasPermission($user, 'mobile.map.route_progress');
        $canPolyline = $rbac->hasPermission($user, 'mobile.map.polyline');

        if (! $canRouteProgress && ! $canPolyline) {
            return null;
        }

        $payload = app(TripManagementService::class)->payloadForDevice(
            $device,
            $latest ? (float) $latest->lat : null,
            $latest ? (float) $latest->lng : null,
            $latest ? (float) ($latest->speed ?? 0) : null,
            $latest ? (float) ($latest->heading ?? 0) : null,
        );

        if (! $payload) {
            return null;
        }

        if (! $canRouteProgress) {
            unset($payload['trip'], $payload['progress']);
        }

        if (! $canPolyline && isset($payload['route']) && is_array($payload['route'])) {
            foreach ([
                'assigned_polyline',
                'polyline',
                'admin_polyline',
                'guided_polyline',
                'actual_polyline',
                'navigation_polyline',
                'join_polyline',
                'dynamic_polyline',
                'display_polyline',
            ] as $key) {
                unset($payload['route'][$key]);
            }
        }

        return $payload;
    }
}
