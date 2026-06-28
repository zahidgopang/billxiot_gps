<?php

namespace App\Http\Controllers;

use App\Http\Concerns\ResolvesHistoryDateRange;
use App\Http\Concerns\ResolvesTrackingPanel;
use App\Services\Mobile\VehicleStatusSpec;
use App\Services\Tracking\GlobalTrackingService;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Illuminate\View\View;

class GlobalTrackingController extends Controller
{
    use ResolvesHistoryDateRange;
    use ResolvesTrackingPanel;

    public function __construct(
        private GlobalTrackingService $tracking,
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
            'deviceEditUrlTemplate' => $this->deviceEditUrlTemplate($panel),
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

        $devices = $requested === []
            ? $this->tracking->livePayloadForIds(array_slice($allowed, 0, GlobalTrackingService::MAX_LIVE_DEVICES))
            : $this->tracking->livePayloadForIds($allowed);

        return $this->noStoreJson(['devices' => $devices]);
    }

    public function devicePanel(Request $request): JsonResponse
    {
        $id = (int) ($request->query('device_id') ?? $request->input('device_id') ?? 0);
        $data = $this->tracking->devicePanelData($request->user(), $id);

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
        ]);
    }

    public function historyJson(Request $request): JsonResponse
    {
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

        return \App\Support\Tracking\HistoryRangeBounds::normalize($from, $to);
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
        return [
            'live' => "{$panel}.tracking.index",
            'liveJson' => "{$panel}.tracking.live-json",
            'history' => "{$panel}.tracking.history",
            'historyJson' => "{$panel}.tracking.history-json",
            'eventsJson' => "{$panel}.tracking.events.json",
            'geofencesJson' => "{$panel}.tracking.geofences.json",
            'devicePanel' => "{$panel}.tracking.device-panel",
            'deviceMileage' => "{$panel}.tracking.device-mileage",
            'commandsSend' => "{$panel}.tracking.commands.send",
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
}
