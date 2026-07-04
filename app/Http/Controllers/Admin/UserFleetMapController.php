<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Concerns\InteractsWithTenantAuthorization;
use App\Http\Controllers\Controller;
use App\Models\Device;
use App\Models\User;
use App\Services\FleetMapDeviceService;
use App\Services\Mobile\MapRenderingSpec;
use App\Services\Tracking\DevicePositionLoader;
use App\Services\UserDashboardService;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;

class UserFleetMapController extends Controller
{
    use InteractsWithTenantAuthorization;

    public function show(Request $request, User $user, UserDashboardService $dashboard)
    {
        $this->authorizeManageUser($user);

        if (! $user->isEndUserRole()) {
            return redirect()
                ->route($this->panelPrefix().'.users.index')
                ->with('error', __('app.admin.users.fleet_map_end_user_only'));
        }

        $panel = $this->panelPrefix();
        $devices = $this->loadVisibleDevices($request, $user);
        app(DevicePositionLoader::class)->attachLatestToMany($devices);

        return view('admin.users.fleet-map', [
            'targetUser' => $user,
            'devices' => $devices,
            'panel' => $panel,
            'mapSpec' => MapRenderingSpec::toArray(),
            'initialPayload' => $this->buildDevicesPayload($devices, $dashboard, $panel),
            'stats' => $dashboard->getDevicePageStats($devices),
        ]);
    }

    public function liveJson(Request $request, User $user, UserDashboardService $dashboard)
    {
        $this->authorizeManageUser($user);

        if (! $user->isEndUserRole()) {
            return response()->json(['devices' => [], 'stats' => null]);
        }

        $panel = $this->panelPrefix();
        $devices = $this->loadVisibleDevices($request, $user);
        app(DevicePositionLoader::class)->attachLatestToMany($devices);

        return response()->json([
            'devices' => $this->buildDevicesPayload($devices, $dashboard, $panel),
            'stats' => $dashboard->getDevicePageStats($devices),
        ]);
    }

    /**
     * @return Collection<int, Device>
     */
    private function loadVisibleDevices(Request $request, User $user): Collection
    {
        $devices = $user->trackerDevicesQuery()
            ->orderBy('name')
            ->get();

        $allowedIds = $this->tenantScope()->visibleDeviceIdsForPanel($request->user());
        if ($allowedIds !== null) {
            $devices = $devices->filter(
                fn (Device $device) => in_array((int) $device->id, $allowedIds, true)
            )->values();
        }

        return $devices
            ->sortBy(fn (Device $device) => mb_strtolower($device->mapMarkerTitle()))
            ->values();
    }

    /**
     * @param  Collection<int, Device>  $devices
     * @return list<array<string, mixed>>
     */
    private function buildDevicesPayload(Collection $devices, UserDashboardService $dashboard, string $panel): array
    {
        return app(FleetMapDeviceService::class)->buildPayload(
            $devices,
            $dashboard,
            fn (Device $device) => route($panel.'.locations.launch-map', $device),
        );
    }
}
