<?php

namespace App\Http\Controllers\Api\Mobile;

use App\Http\Controllers\Controller;
use App\Http\Concerns\RespondsWithMobileJson;
use App\Models\VehicleEvent;
use App\Services\Mobile\MobileDevicePresenter;
use App\Services\Mobile\MobileMapStatusResolver;
use App\Services\UserDashboardService;
use Illuminate\Http\Request;

class DashboardController extends Controller
{
    use RespondsWithMobileJson;

    public function __construct(
        private UserDashboardService $dashboard,
        private MobileDevicePresenter $presenter,
        private MobileMapStatusResolver $mapStatus,
    ) {}

    public function summary(Request $request)
    {
        $user = $request->user();
        $stats = $this->dashboard->getStats($user);
        $devices = $stats['devices'];
        $fleet = $this->mapStatus->fleetCounts($devices);

        $geofenceAlerts = 0;
        if ($devices->isNotEmpty()) {
            $deviceIds = $devices->pluck('id');
            $geofenceAlerts = app(\App\Contracts\Tracking\EventReaderInterface::class)->countForDevices(
                $deviceIds,
                now()->subDays(7),
                [VehicleEvent::TYPE_GEOFENCE_ENTER, VehicleEvent::TYPE_GEOFENCE_EXIT]
            );
        }

        $parkedTotal = $fleet['parked'] + $fleet['stopped'] + $fleet['idle'];

        return $this->mobileSuccess([
            'total_devices' => $stats['totalDevices'],
            // Recent GPS ping (last 5 min) — "connected now"
            'online_devices' => $stats['onlineNow'],
            // Map-style status (matches web device map HUD)
            'offline_devices' => $fleet['offline'],
            'moving_devices' => $fleet['running'],
            'running_devices' => $fleet['running'],
            'parked_devices' => $parkedTotal,
            'idle_devices' => $fleet['idle'],
            'with_gps_devices' => $fleet['with_gps'],
            'alerts_count' => $stats['activeAlerts'],
            'total_distance_km' => $stats['totalDistanceKm'],
            'geofence_alerts' => $geofenceAlerts,
        ]);
    }

    public function activity(Request $request)
    {
        $user = $request->user();
        $stats = $this->dashboard->getStats($user);
        $deviceIds = $stats['devices']->pluck('id');

        $activities = $this->dashboard->getRecentActivities($deviceIds)
            ->map(fn (array $item) => array_merge([
                'type' => $item['type'],
                'title' => $item['title'],
                'description' => $item['description'],
                'icon' => $item['icon'],
            ], \App\Support\DateTime\AppDateTime::apiFields($item['time'] ?? null)))
            ->values();

        return $this->mobileSuccess($activities);
    }

    public function recentVehicles(Request $request)
    {
        $user = $request->user();
        $stats = $this->dashboard->getStats($user);
        $alertIds = $this->dashboard->alertDeviceIds($stats['devices']);

        $items = $stats['recentDevices']->map(function ($device) use ($alertIds) {
            try {
                return $this->presenter->listItem($device, $alertIds);
            } catch (\Throwable $e) {
                report($e);

                return null;
            }
        })->filter()->values();

        return $this->mobileSuccess($items);
    }
}
