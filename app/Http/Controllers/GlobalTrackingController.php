<?php

namespace App\Http\Controllers;

use App\Http\Concerns\ResolvesHistoryDateRange;
use App\Http\Concerns\ResolvesTrackingPanel;
use App\Services\Authorization\RbacService;
use App\Services\Mobile\VehicleStatusSpec;
use App\Services\Tracking\GlobalTrackingService;
use App\Services\Tracking\TrackingUiPermissions;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Route;
use Illuminate\View\View;

class GlobalTrackingController extends Controller
{
    use ResolvesHistoryDateRange;
    use ResolvesTrackingPanel;

    public function __construct(
        private GlobalTrackingService $tracking,
        private RbacService $rbac,
    ) {}

    public function index(Request $request): View
    {
        $panel = $this->resolvePanel($request);
        $vehicles = $this->tracking->listItemsForActor($request->user());

        return view('tracking.traccar', [
            'panel' => $panel,
            'layout' => $this->layoutForPanel($panel),
            'vehicles' => $vehicles,
            'stateColors' => VehicleStatusSpec::STATE_COLORS,
            'hubRoutes' => $this->trackingHubRoutes($panel),
            'routes' => $this->liveRouteNames($panel),
            'deviceEditUrlTemplate' => $this->canOpenVehicleDetails($request->user())
                ? $this->deviceEditUrlTemplate($panel)
                : null,
            'manageRoutesUrl' => Route::has("{$panel}.routes.index")
                ? route("{$panel}.routes.index")
                : null,
            'trackingUi' => app(TrackingUiPermissions::class)->forUser($request->user()),
            'companyMapCard' => app(\App\Services\Tracking\CompanyMapCardService::class)->mapPayload(),
        ]);
    }

    public function liveJson(Request $request): JsonResponse
    {
        $user = $request->user();
        $requested = $this->parseTrackingIdList($request);
        $allowed = $requested === []
            ? $this->tracking->allowedDeviceIds($user)
            : $this->tracking->filterAllowedIds($user, $requested);

        if ($requested !== [] && $allowed === []) {
            return $this->noStoreJson(['devices' => []]);
        }

        $targetIds = $requested === []
            ? array_slice($allowed, 0, GlobalTrackingService::MAX_LIVE_DEVICES)
            : array_slice($allowed, 0, GlobalTrackingService::MAX_LIVE_DEVICES);

        $sortedIds = $targetIds;
        sort($sortedIds);

        $cacheSeconds = (int) config('tracking.live_json_cache_seconds', 3);
        $cacheKey = 'tracking.live_json.'
            . $user->id
            . '.'
            . md5(implode(',', $sortedIds));

        if ($cacheSeconds > 0) {
            $payload = Cache::remember($cacheKey, $cacheSeconds, fn () => $this->tracking->livePayloadForIds($targetIds, $user));

            return $this->noStoreJson(['devices' => $payload]);
        }

        $devices = $this->tracking->livePayloadForIds($targetIds, $user);

        return $this->noStoreJson(['devices' => $devices]);
    }

    public function devicePanel(Request $request): JsonResponse
    {
        $id = (int) ($request->query('device_id') ?? $request->input('device_id') ?? 0);
        $sectionsParam = $request->query('sections');
        $sections = null;
        if (is_string($sectionsParam) && trim($sectionsParam) !== '') {
            $sections = array_values(array_filter(array_map(
                static fn (string $part) => strtolower(trim($part)),
                explode(',', $sectionsParam),
            )));
        }

        try {
            $freshStats = $request->boolean('fresh');
            $data = $this->tracking->devicePanelData($request->user(), $id, $sections, $freshStats);
        } catch (\Throwable $e) {
            report($e);

            return $this->noStoreJson([
                'success' => false,
                'message' => __('app.tracking.panel_load_failed'),
            ], 503);
        }

        if ($data === null) {
            return $this->noStoreJson(['success' => false], 404);
        }

        return $this->noStoreJson(['success' => true, 'panel' => $data]);
    }

    public function deviceMileage(Request $request): JsonResponse
    {
        $id = (int) ($request->query('device_id') ?? $request->input('device_id') ?? 0);
        $mileage = $this->tracking->deviceMileage($request->user(), $id);

        if ($mileage === null) {
            return $this->noStoreJson(['success' => false], 404);
        }

        return $this->noStoreJson(['success' => true, 'mileage' => $mileage]);
    }

    public function completeTrip(Request $request): JsonResponse
    {
        $id = (int) ($request->input('device_id') ?? $request->query('device_id') ?? 0);
        $allowed = $this->tracking->filterAllowedIds($request->user(), [$id]);
        if ($allowed === []) {
            return $this->noStoreJson(['success' => false, 'message' => __('app.tracking.select_vehicle')], 404);
        }

        $device = \App\Models\Device::query()->find($id);
        if (! $device) {
            return $this->noStoreJson(['success' => false], 404);
        }

        try {
            $trip = app(\App\Services\Routes\TripManagementService::class)
                ->markCompleted($device, $request->user());
        } catch (\Throwable $e) {
            return $this->noStoreJson(['success' => false, 'message' => $e->getMessage()], 422);
        }

        $device->refresh();
        $latest = $device->latestLocation;

        return $this->noStoreJson([
            'success' => true,
            'message' => __('app.routes.trip_marked_completed'),
            'trip' => [
                'id' => $trip->id,
                'status' => $trip->status,
            ],
            'route_trip' => $this->tracking->routeTripPayloadForActor($request->user(), $device, $latest),
        ]);
    }

    public function startNewTrip(Request $request): JsonResponse
    {
        $id = (int) ($request->input('device_id') ?? $request->query('device_id') ?? 0);
        $allowed = $this->tracking->filterAllowedIds($request->user(), [$id]);
        if ($allowed === []) {
            return $this->noStoreJson(['success' => false, 'message' => __('app.tracking.select_vehicle')], 404);
        }

        $device = \App\Models\Device::query()->find($id);
        if (! $device) {
            return $this->noStoreJson(['success' => false], 404);
        }

        app(\App\Services\Tracking\DevicePositionLoader::class)->attachLatest($device);
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
            return $this->noStoreJson(['success' => false, 'message' => $e->getMessage()], 422);
        }

        $routeTrip = $this->tracking->routeTripPayloadForActor($request->user(), $device, $latest);
        $message = $trip->started_at
            ? __('app.routes.trip_started')
            : __('app.routes.trip_armed_waiting_start');

        return $this->noStoreJson([
            'success' => true,
            'message' => $message,
            'trip' => [
                'id' => $trip->id,
                'status' => $trip->status,
            ],
            'route_trip' => $routeTrip,
        ]);
    }

    public function restartTrip(Request $request): JsonResponse
    {
        $id = (int) ($request->input('device_id') ?? $request->query('device_id') ?? 0);
        $allowed = $this->tracking->filterAllowedIds($request->user(), [$id]);
        if ($allowed === []) {
            return $this->noStoreJson(['success' => false, 'message' => __('app.tracking.select_vehicle')], 404);
        }

        $device = \App\Models\Device::query()->find($id);
        if (! $device) {
            return $this->noStoreJson(['success' => false], 404);
        }

        app(\App\Services\Tracking\DevicePositionLoader::class)->attachLatest($device);
        $latest = $device->latestLocation;

        try {
            $trip = app(\App\Services\Routes\TripManagementService::class)->restartTrip(
                $device,
                $latest ? (float) $latest->lat : null,
                $latest ? (float) $latest->lng : null,
                $latest ? (float) ($latest->speed ?? 0) : null,
                $request->user(),
            );
        } catch (\Throwable $e) {
            return $this->noStoreJson(['success' => false, 'message' => $e->getMessage()], 422);
        }

        $message = $trip->started_at
            ? __('app.routes.trip_restarted')
            : __('app.routes.trip_armed_waiting_start');

        return $this->noStoreJson([
            'success' => true,
            'message' => $message,
            'trip' => [
                'id' => $trip->id,
                'status' => $trip->status,
            ],
            'route_trip' => $this->tracking->routeTripPayloadForActor($request->user(), $device, $latest),
        ]);
    }

    public function routeGuidance(Request $request): JsonResponse
    {
        $actor = $request->user();
        $ui = app(TrackingUiPermissions::class)->forUser($actor);
        if (! ($ui['polyline'] ?? true)) {
            return $this->noStoreJson(['success' => false, 'message' => __('app.tracking.polyline_not_allowed')], 403);
        }

        $id = (int) ($request->query('device_id') ?? $request->input('device_id') ?? 0);
        $allowed = $this->tracking->filterAllowedIds($request->user(), [$id]);
        if ($allowed === []) {
            return $this->noStoreJson(['success' => false], 404);
        }

        $device = \App\Models\Device::query()->find($id);
        if (! $device) {
            return $this->noStoreJson(['success' => false], 404);
        }

        app(\App\Services\Tracking\DevicePositionLoader::class)->attachLatest($device);
        $latest = $device->latestLocation;
        $lat = $latest ? (float) $latest->lat : null;
        $lng = $latest ? (float) $latest->lng : null;
        $speed = $latest ? (float) ($latest->speed ?? 0) : null;

        $tripService = app(\App\Services\Routes\TripManagementService::class);
        $payload = $tripService->payloadForDevice($device, $lat, $lng, $speed);
        $route = $payload['route'] ?? null;
        if (! $route) {
            return $this->noStoreJson(['success' => false, 'message' => 'No route polyline'], 404);
        }

        if (! ($route['show_polyline'] ?? false)) {
            return $this->noStoreJson(['success' => false, 'message' => 'Route polyline disabled'], 403);
        }

        if ($payload['trip_mode_active'] ?? false) {
            $vertices = $route['navigation_polyline'] ?? [];
            if (count($vertices) < 2) {
                return $this->noStoreJson(['success' => false, 'message' => 'Navigation route unavailable'], 422);
            }

            return $this->noStoreJson([
                'success' => true,
                'mode' => 'navigation',
                'vertices' => $vertices,
                'distance_km' => $route['navigation_distance_km'] ?? null,
                'duration_minutes' => $route['navigation_duration_minutes'] ?? null,
                'is_road_polyline' => true,
            ]);
        }

        $vertices = $route['guided_polyline'] ?? [];
        $isRoad = (bool) ($route['is_road_polyline'] ?? false);

        if (! $isRoad || count($vertices) < 10) {
            $assignment = $tripService->assignmentForDevice($device->id);
            $routePlan = $assignment?->route;
            if ($routePlan) {
                $guidance = app(\App\Services\Routes\RouteGuidanceService::class);
                $guidance->forgetCache($routePlan);
                $fresh = $guidance->fetchFromGoogle($routePlan);
                if ($fresh && count($fresh['vertices'] ?? []) >= 2) {
                    $vertices = $fresh['vertices'];
                    $isRoad = true;
                }
            }
        }

        if (count($vertices) < 2) {
            return $this->noStoreJson(['success' => false, 'message' => 'Guidance unavailable'], 422);
        }

        return $this->noStoreJson([
            'success' => true,
            'mode' => 'assigned',
            'vertices' => $vertices,
            'is_road_polyline' => $isRoad,
        ]);
    }

    public function history(Request $request): View
    {
        $panel = $this->resolvePanel($request);
        $vehicles = $this->tracking->listItemsForActor($request->user());

        return view('tracking.history', [
            'panel' => $panel,
            'layout' => $this->layoutForPanel($panel),
            'vehicles' => $vehicles,
            'multiColors' => GlobalTrackingService::MULTI_VEHICLE_COLORS,
            'hubRoutes' => $this->trackingHubRoutes($panel),
            'routes' => $this->liveRouteNames($panel),
            'trackingUi' => $this->trackingUiFor($request->user()),
        ]);
    }

    public function historyJson(Request $request): JsonResponse
    {
        $this->prepareHeavyHistoryRequest();

        try {
            $user = $request->user();
            $ids = $this->tracking->filterAllowedIds($user, $this->parseTrackingIdList($request));

            if ($ids === []) {
                return $this->noStoreJson(['vehicles' => [], 'message' => 'No devices selected']);
            }

            $range = $this->resolveGlobalHistoryRange($request);

            $vehicles = $this->tracking->historyForDevices(
                $user,
                $ids,
                $range['from'],
                $range['to'],
            );

            return $this->noStoreJson([
                'vehicles' => $vehicles,
                'from' => $range['from']->toIso8601String(),
                'to' => $range['to']?->toIso8601String(),
            ]);
        } catch (\Throwable $e) {
            return $this->historyFailureJson($e);
        }
    }

    public function historyPointsJson(Request $request): JsonResponse
    {
        $this->prepareHeavyHistoryRequest();

        try {
            $user = $request->user();
            $ids = $this->tracking->filterAllowedIds($user, $this->parseTrackingIdList($request));

            if ($ids === []) {
                return $this->noStoreJson(['vehicles' => [], 'message' => 'No devices selected']);
            }

            $range = $this->resolveGlobalHistoryRange($request);

            return $this->noStoreJson([
                'vehicles' => $this->collectHistoryVehicles($ids, $range, 'points'),
                'from' => $range['from']->toIso8601String(),
                'to' => $range['to']?->toIso8601String(),
            ]);
        } catch (\Throwable $e) {
            return $this->historyFailureJson($e);
        }
    }

    public function historyAnalyticsJson(Request $request): JsonResponse
    {
        $this->prepareHeavyHistoryRequest();

        try {
            $user = $request->user();
            $ids = $this->tracking->filterAllowedIds($user, $this->parseTrackingIdList($request));

            if ($ids === []) {
                return $this->noStoreJson(['vehicles' => [], 'message' => 'No devices selected']);
            }

            $range = $this->resolveGlobalHistoryRange($request);

            return $this->noStoreJson([
                'vehicles' => $this->collectHistoryVehicles($ids, $range, 'analytics'),
                'from' => $range['from']->toIso8601String(),
                'to' => $range['to']?->toIso8601String(),
            ]);
        } catch (\Throwable $e) {
            return $this->historyFailureJson($e);
        }
    }

    /**
     * Export history day as trips+stops (xlsx/csv/pdf) via ReportService.
     */
    public function historyExport(Request $request): \Symfony\Component\HttpFoundation\StreamedResponse|\Illuminate\Http\Response
    {
        $this->prepareHeavyHistoryRequest();

        $user = $request->user();
        $ids = $this->tracking->filterAllowedIds($user, $this->parseTrackingIdList($request));
        if ($ids === []) {
            abort(422, (string) __('app.tracking.report_select_vehicle'));
        }

        $range = $this->resolveGlobalHistoryRange($request);
        $format = strtolower((string) $request->input('format', $request->query('format', 'xlsx')));
        if (! in_array($format, ['csv', 'xlsx', 'xls', 'pdf'], true)) {
            $format = 'xlsx';
        }

        try {
            $reports = app(\App\Services\Tracking\Reports\ReportService::class);
            $export = app(\App\Services\Tracking\Reports\ReportExportService::class);
            \App\Services\Tracking\Reports\ReportService::applyTimeLimit(
                count($ids),
                $range['from'],
                $range['to'] ?? $range['from']->copy()->endOfDay(),
                forExport: true,
            );
            $request->session()->save();

            $report = $reports->generate(
                $user,
                'trips_stops',
                $ids,
                $range['from'],
                $range['to'] ?? $range['from']->copy()->endOfDay(),
                forExport: true,
            );

            return $export->export($report, $format);
        } catch (\Throwable $e) {
            report($e);
            abort(500, (string) __('app.tracking.report_export_failed'));
        }
    }

    /**
     * Lazy reverse-geocode for history stop addresses (cached).
     */
    public function historyGeocode(Request $request): JsonResponse
    {
        $lat = (float) $request->query('lat', $request->input('lat', 0));
        $lng = (float) $request->query('lng', $request->input('lng', 0));
        if ($lat == 0.0 && $lng == 0.0) {
            return $this->noStoreJson(['success' => false, 'address' => null], 422);
        }

        $cacheKey = 'history.geocode.'.round($lat, 5).'.'.round($lng, 5);
        $address = Cache::remember($cacheKey, now()->addDays(7), function () use ($lat, $lng) {
            return app(\App\Support\Geo\GeoLocalityResolver::class)->reverseGeocode($lat, $lng);
        });

        return $this->noStoreJson([
            'success' => $address !== null && $address !== '',
            'address' => $address,
            'lat' => $lat,
            'lng' => $lng,
        ]);
    }

    /**
     * @param  list<int>  $ids
     * @param  array{from: Carbon, to: Carbon|null}  $range
     * @return list<array<string, mixed>>
     */
    private function collectHistoryVehicles(array $ids, array $range, string $mode): array
    {
        $history = app(\App\Services\Tracking\GlobalTrackingHistoryService::class);
        $multi = count($ids) > 1;
        $vehicles = [];
        $colorIndex = 0;

        foreach ($ids as $id) {
            $device = \App\Models\Device::query()->find($id);
            if (! $device) {
                continue;
            }

            $fetch = $history->fetchLocations($device, $range['from'], $range['to']);
            $locations = $fetch['locations'];
            if ($locations->isEmpty()) {
                continue;
            }

            $color = $multi
                ? GlobalTrackingService::MULTI_VEHICLE_COLORS[$colorIndex++ % count(GlobalTrackingService::MULTI_VEHICLE_COLORS)]
                : null;

            $vehicles[] = $mode === 'analytics'
                ? $history->vehicleAnalyticsPayload(
                    $device,
                    $locations,
                    $range['from'],
                    $range['to'],
                    (bool) $fetch['used_fallback'],
                    $fetch['fallback_reason'],
                    $color,
                )
                : $history->vehiclePointsPayload(
                    $device,
                    $locations,
                    (bool) $fetch['used_fallback'],
                    $fetch['fallback_reason'],
                    $color,
                );
        }

        return $vehicles;
    }

    private function prepareHeavyHistoryRequest(): void
    {
        @ini_set('memory_limit', '512M');
        @set_time_limit(180);
    }

    private function historyFailureJson(\Throwable $e): JsonResponse
    {
        report($e);

        return $this->noStoreJson([
            'success' => false,
            'message' => __('app.map.load_failed'),
            'vehicles' => [],
        ], 503);
    }

    /**
     * @return array{from: Carbon, to: Carbon|null}
     */
    private function resolveGlobalHistoryRange(Request $request): array
    {
        $fromInput = trim((string) ($request->query('from', $request->input('from', ''))));
        $toInput = trim((string) ($request->query('to', $request->input('to', ''))));
        $tz = config('app.timezone');

        if ($fromInput === '') {
            return [
                'from' => now()->subHours(24),
                'to' => null,
            ];
        }

        $from = $this->parseHistoryDateTime($fromInput, $tz, true);
        $to = $toInput !== ''
            ? $this->parseHistoryDateTime($toInput, $tz, false)
            : $from->copy()->endOfDay();

        if ($this->isCalendarDayRangeInput($fromInput, $toInput)) {
            return \App\Support\Tracking\HistoryRangeBounds::normalize($from, $to);
        }

        if ($to->lessThan($from)) {
            [$from, $to] = [$to->copy(), $from->copy()];
        }

        return ['from' => $from, 'to' => $to];
    }

    private function isDateOnlyHistoryInput(string $value): bool
    {
        $value = str_replace('T', ' ', trim($value));

        return (bool) preg_match('/^\d{4}-\d{2}-\d{2}$/', $value);
    }

    private function isCalendarDayRangeInput(string $fromInput, string $toInput): bool
    {
        if ($this->isDateOnlyHistoryInput($fromInput)
            && ($toInput === '' || $this->isDateOnlyHistoryInput($toInput))) {
            return true;
        }

        $from = str_replace('T', ' ', trim($fromInput));
        $to = str_replace('T', ' ', trim($toInput));

        return (bool) preg_match('/^\d{4}-\d{2}-\d{2} 00:00/', $from)
            && ($toInput === '' || preg_match('/^\d{4}-\d{2}-\d{2} 23:59/', $to));
    }

    private function parseHistoryDateTime(string $value, string $tz, bool $start): Carbon
    {
        if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $value)) {
            $date = Carbon::createFromFormat('Y-m-d', $value, $tz)->startOfDay();

            return $start ? $date : $date->copy()->endOfDay();
        }

        if (preg_match('/^\d{4}-\d{2}-\d{2} \d{2}:\d{2}/', $value)) {
            return Carbon::parse($value, $tz);
        }

        if (str_contains($value, 'T')) {
            return Carbon::parse($value)->setTimezone($tz);
        }

        return $start
            ? Carbon::parse($value, $tz)->startOfDay()
            : Carbon::parse($value, $tz)->endOfDay();
    }

    /**
     * @return array<string, string>
     */
    private function liveRouteNames(string $panel): array
    {
        unset($panel);

        return [
            'live' => 'tracking.index',
            'liveJson' => 'tracking.live-json',
            'history' => 'tracking.history',
            'historyJson' => 'tracking.history-json',
            'historyPoints' => 'tracking.history-points-json',
            'historyAnalytics' => 'tracking.history-analytics-json',
            'historyExport' => 'tracking.history-export',
            'historyGeocode' => 'tracking.history-geocode',
            'eventsJson' => 'tracking.events.json',
            'geofencesJson' => 'tracking.geofences.json',
            'devicePanel' => 'tracking.device-panel',
            'deviceMileage' => 'tracking.device-mileage',
            'commandsSend' => 'tracking.commands.send',
            'completeTrip' => 'tracking.complete-trip',
            'startNewTrip' => 'tracking.start-new-trip',
            'restartTrip' => 'tracking.restart-trip',
            'routeGuidance' => 'tracking.route-guidance',
        ];
    }

    private function deviceEditUrlTemplate(string $panel): ?string
    {
        $routeName = "{$panel}.devices.edit";

        if (! Route::has($routeName)) {
            return null;
        }

        return str_replace(
            '/0/',
            '/__DEVICE_ID__/',
            route($routeName, ['device' => 0]),
        );
    }

    private function canOpenVehicleDetails(?\App\Models\User $user): bool
    {
        if (! $user) {
            return false;
        }

        return $this->rbac->hasPermission($user, 'devices.manage')
            || $this->rbac->hasPermission($user, 'web.vehicles.view_details');
    }
}
