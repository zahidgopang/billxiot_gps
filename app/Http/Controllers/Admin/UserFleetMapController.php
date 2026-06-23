<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Concerns\InteractsWithTenantAuthorization;
use App\Http\Controllers\Controller;
use App\Models\Device;
use App\Models\User;
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
        $alertDeviceIds = $dashboard->alertDeviceIds($devices);

        return $devices->map(function (Device $device) use ($dashboard, $alertDeviceIds, $panel) {
            $latest = $device->latestLocation;
            $status = $dashboard->resolveDeviceStatus($device, $alertDeviceIds);

            return [
                'id' => $device->id,
                'title' => $device->mapMarkerTitle(),
                'plate' => $device->mapMarkerPlateLine(),
                'vehicle_type' => $device->vehicle_type ?: 'car',
                'status_key' => $status['key'],
                'status_label' => $status['label'],
                'lat' => $latest ? (float) $latest->lat : null,
                'lng' => $latest ? (float) $latest->lng : null,
                'heading' => $latest ? (float) ($latest->heading ?? 0) : 0,
                'speed' => $latest ? round((float) ($latest->speed ?? 0)) : null,
                'recorded_at_human' => $latest?->recorded_at
                    ? app_datetime_format($latest->recorded_at)
                    : __('app.common.no_data'),
                'launch_map_url' => route($panel.'.locations.launch-map', $device),
            ];
        })->values()->all();
    }
}
