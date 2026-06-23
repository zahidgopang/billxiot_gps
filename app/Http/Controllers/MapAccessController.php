<?php

namespace App\Http\Controllers;

use App\Models\Device;
use App\Services\Authorization\RbacService;
use App\Services\Authorization\TenantScopeService;
use App\Services\DeviceMapAccessService;
use App\Services\Traccar\TraccarDeviceAccessService;
use Illuminate\Http\Request;

class MapAccessController extends Controller
{
    public function __construct(
        private DeviceMapAccessService $mapAccess,
        private TenantScopeService $tenantScope,
        private RbacService $rbac,
    ) {}

    public function launchUserMap(Request $request, Device $device)
    {
        if (! app(TraccarDeviceAccessService::class)->userCanAccessDevice($request->user(), $device)) {
            abort(403);
        }

        $device->load(['subscription', 'user']);
        $check = app(\App\Services\DeviceAccessService::class)->evaluate($request->user(), $device);

        if (! $check['allowed']) {
            return redirect()
                ->route('user.devices.index')
                ->with('access_denied_title', $check['title'])
                ->with('access_denied_message', $check['message'])
                ->with('subscription_device', $device->name)
                ->with('access_denied_reason', $check['reason']);
        }

        $token = $this->mapAccess->issueGrant($device, $request->user(), false);

        return redirect()->route('user.device.map', ['token' => $token]);
    }

    public function launchAdminMap(Request $request, Device $device)
    {
        if (! $this->tenantScope->actorMayTrackMaps($request->user())) {
            abort(403, __('app.forms.map_tracking_disabled'));
        }

        $traccarDevices = app(TraccarDeviceAccessService::class);
        if ($traccarDevices->usesTraccarDeviceList() && ! Device::inTracker()->whereKey($device->id)->exists()) {
            abort(404);
        }

        if (! $traccarDevices->userCanAccessDevice($request->user(), $device)) {
            abort(403);
        }

        $token = $this->mapAccess->issueGrant($device, $request->user(), true);

        $mapRoute = $this->rbac->roleOf($request->user())->panel() === 'client'
            ? 'client.device.map'
            : 'admin.device.map';

        return redirect()->route($mapRoute, ['token' => $token]);
    }
}
