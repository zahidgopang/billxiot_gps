<?php

namespace App\Http\Controllers\Api\Mobile;

use App\Http\Controllers\Controller;
use App\Http\Concerns\RespondsWithMobileJson;
use App\Models\User;
use App\Models\VehicleEvent;
use App\Services\Mobile\MobileDevicePresenter;
use App\Services\Mobile\MobileMapStatusResolver;
use App\Services\UserDashboardService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;

class DashboardController extends Controller
{
    use RespondsWithMobileJson;

    private const HOME_CACHE_SECONDS = 45;

    public function __construct(
        private UserDashboardService $dashboard,
        private MobileDevicePresenter $presenter,
        private MobileMapStatusResolver $mapStatus,
    ) {}

    public function home(Request $request)
    {
        $user = $request->user();
        $payload = Cache::remember(
            $this->homeCacheKey($user),
            self::HOME_CACHE_SECONDS,
            fn () => $this->buildHomePayload($user),
        );

        return $this->mobileSuccess($payload);
    }

    public function summary(Request $request)
    {
        $user = $request->user();
        $stats = $this->dashboard->getStats($user);
        $devices = $stats['devices'];
        $fleet = $this->mapStatus->fleetCounts($devices);

        return $this->mobileSuccess($this->summaryPayload($stats, $devices, $fleet));
    }

    /**
     * @return array<string, mixed>
     */
    private function buildHomePayload(User $user): array
    {
        $stats = $this->dashboard->getStats($user);
        $devices = $stats['devices'];
        $fleet = $this->mapStatus->fleetCounts($devices);
        $alertIds = $stats['alertDeviceIds'] ?? $this->dashboard->alertDeviceIds($devices);

        return [
            'summary' => $this->summaryPayload($stats, $devices, $fleet),
            'activity' => $this->activityPayload($stats['activities'])->values(),
            'recent_vehicles' => $this->recentVehiclePayload($stats['recentDevices'], $alertIds)->values(),
        ];
    }

    private function homeCacheKey(User $user): string
    {
        return 'mobile.dashboard.home.' . $user->id;
    }

    private function summaryPayload(array $stats, $devices, array $fleet): array
    {

        $geofenceAlerts = 0;
        if ($devices->isNotEmpty()) {
            $deviceIds = $devices->pluck('id');
            try {
                $geofenceAlerts = app(\App\Contracts\Tracking\EventReaderInterface::class)->countForDevices(
                    $deviceIds,
                    now()->subDays(7),
                    [VehicleEvent::TYPE_GEOFENCE_ENTER, VehicleEvent::TYPE_GEOFENCE_EXIT]
                );
            } catch (\Throwable $e) {
                report($e);
                $geofenceAlerts = 0;
            }
        }

        $parkedTotal = $fleet['parked'] + $fleet['stopped'] + $fleet['idle'];

        return [
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
        ];
    }

    public function activity(Request $request)
    {
        $user = $request->user();
        $stats = $this->dashboard->getStats($user);

        return $this->mobileSuccess($this->activityPayload($stats['activities'])->values());
    }

    public function recentVehicles(Request $request)
    {
        $user = $request->user();
        $stats = $this->dashboard->getStats($user);
        $alertIds = $stats['alertDeviceIds'] ?? $this->dashboard->alertDeviceIds($stats['devices']);

        return $this->mobileSuccess($this->recentVehiclePayload($stats['recentDevices'], $alertIds)->values());
    }

    private function activityPayload($activities)
    {
        return $activities
            ->map(fn (array $item) => array_merge([
                'type' => $item['type'],
                'title' => $item['title'],
                'description' => $item['description'],
                'icon' => $item['icon'],
            ], \App\Support\DateTime\AppDateTime::apiFields($item['time'] ?? null)));
    }

    private function recentVehiclePayload($devices, $alertIds)
    {
        return $devices->map(function ($device) use ($alertIds) {
            try {
                return $this->presenter->listItem($device, $alertIds);
            } catch (\Throwable $e) {
                report($e);

                return null;
            }
        })->filter();
    }
}
