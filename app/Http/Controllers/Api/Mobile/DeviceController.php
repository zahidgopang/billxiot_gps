<?php

namespace App\Http\Controllers\Api\Mobile;

use App\Contracts\Tracking\EventReaderInterface;
use App\Contracts\Tracking\PositionReaderInterface;
use App\Http\Controllers\Controller;
use App\Http\Concerns\ResolvesHistoryDateRange;
use App\Http\Concerns\ResolvesMobileDevice;
use App\Http\Concerns\RespondsWithMobileJson;
use App\Models\VehicleEvent;
use App\Services\Mobile\MobileDevicePresenter;
use App\Services\Mobile\MobileRouteAnalyticsService;
use App\Services\Tracking\DeviceHistoryFetcher;
use App\Services\Tracking\DevicePositionLoader;
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
    ) {}

    public function index(Request $request)
    {
        $user = $request->user();
        $devices = $user->trackableDevicesQuery()->with(['subscription'])->get();
        $this->positionLoader->attachLatestToMany($devices);
        $alertIds = $this->dashboard->alertDeviceIds($devices);

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
        $device = $this->findMobileDevice($request->user(), $id);
        $this->positionLoader->attachLatest($device);
        $alertIds = $this->dashboard->alertDeviceIds(collect([$device]));

        return $this->mobileSuccess($this->presenter->detail($device, $alertIds));
    }

    public function live(Request $request, int $id)
    {
        $device = $this->findMobileDevice($request->user(), $id);
        $this->positionLoader->attachLatest($device);
        $latest = $device->latestLocation;

        if ($latest && config('tracking.laravel_geofence_detection', true)) {
            app(VehicleEventService::class)->processGeofenceFromLocation(
                $device,
                (float) $latest->lat,
                (float) $latest->lng,
                $latest->recorded_at ?? now()
            );
        }

        $payload = $this->presenter->livePosition($device);

        if (! $payload) {
            return $this->mobileError('No live position available', 404, 'no_data');
        }

        return $this->mobileSuccess($payload);
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

        $points = $locations->map(fn ($loc) => [
            'lat' => (float) $loc->lat,
            'lng' => (float) $loc->lng,
            'speed' => (float) ($loc->speed ?? 0),
            'heading' => (float) ($loc->heading ?? 0),
            'ignition' => (bool) $loc->ignition,
            'battery' => $loc->battery_level,
            'battery_level' => $loc->battery_level,
            'gsm_signal' => $loc->gsm_signal,
            'gps_signal' => $loc->gps_signal,
            'satellites' => $loc->satellites,
            'odometer' => $loc->odometer,
            'gps_fix' => $loc->gps_fix,
            'recorded_at' => app_datetime_api($loc->recorded_at),
            'timestamp' => app_datetime_format($loc->recorded_at, 'log'),
            'recorded_at_display' => app_datetime_format($loc->recorded_at),
        ])->values();

        $stats = $this->routeAnalytics->analyze($locations);

        return $this->mobileSuccess([
            'polyline' => $points,
            'stops' => $stats['stops'],
            'moving_points' => $stats['moving_points'],
            'idle_points' => $stats['idle_points'],
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
            'moving_time_seconds' => $stats['moving_time_seconds'],
            'stopped_time_seconds' => $stats['stopped_time_seconds'],
            'max_speed_kmh' => $stats['max_speed_kmh'],
            'average_speed_kmh' => $stats['average_speed_kmh'],
            'idle_time_seconds' => $stats['idle_time_seconds'],
            'total_duration_seconds' => $stats['total_duration_seconds'],
            'start_time' => $startTime,
            'end_time' => $endTime,
            'stop_count' => $stats['stop_count'] ?? count($stats['stops'] ?? []),
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
}
