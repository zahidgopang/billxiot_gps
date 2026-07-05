<?php

namespace App\Http\Concerns;

use App\Models\Device;
use App\Services\DeviceMapAccessService;
use App\Services\Authorization\RbacService;
use App\Services\Traccar\TraccarDeviceAccessService;
use Illuminate\Http\Request;

trait ResolvesMapDevice
{
    protected function isAdminMapRequest(?Request $request = null): bool
    {
        return app(DeviceMapAccessService::class)->isAdminMapRequest($request);
    }

    protected function findMapDevice(string $token): Device
    {
        return app(DeviceMapAccessService::class)->assertMapApiAccess($token, auth()->user());
    }

    /**
     * @return array<string, string>
     */
    protected function mapApiRoutes(Device $device, string $mapToken): array
    {
        $token = ['token' => $mapToken];

        if ($this->isAdminMapRequest()) {
            $panel = request()->routeIs('client.*') ? 'client' : 'admin';

            return [
                'live' => route($panel . '.device.live.json', $token),
                'history' => route($panel . '.device.history.json', $token),
                'summary' => route($panel . '.device.summary.json', $token),
                'alerts' => route($panel . '.device.alerts.json', $token),
                'completeTrip' => route($panel . '.device.complete.trip', $token),
                'startNewTrip' => route($panel . '.device.start.new.trip', $token),
                'reverseGeocode' => route($panel . '.device.reverse.geocode', $token),
                'geofences' => route($panel . '.device.geofences.json', $token),
                'geofencesSave' => route($panel . '.device.geofences.save', $token),
                'geofenceDestroy' => rtrim(route($panel . '.geofence.destroy', ['id' => 0]), '/0'),
                'geofenceUpdate' => rtrim(route($panel . '.geofence.update', ['id' => 0]), '/0'),
                'accessDeniedRedirect' => route($panel . '.locations.index'),
            ];
        }

        return [
            'live' => route('user.device.live.json', $token),
            'history' => route('user.device.history.json', $token),
            'summary' => route('user.device.summary.json', $token),
            'alerts' => route('user.device.alerts.json', $token),
            'completeTrip' => route('user.device.complete.trip', $token),
            'startNewTrip' => route('user.device.start.new.trip', $token),
            'reverseGeocode' => route('user.device.reverse.geocode', $token),
            'geofences' => route('user.device.geofences.json', $token),
            'geofencesSave' => route('user.device.geofences.save', $token),
            'geofenceDestroy' => rtrim(route('user.geofence.destroy', ['id' => 0]), '/0'),
            'geofenceUpdate' => rtrim(route('user.geofence.update', ['id' => 0]), '/0'),
            'accessDeniedRedirect' => route('user.devices.index'),
        ];
    }

    protected function canManageGeofencesOnMap(Device $device): bool
    {
        $user = auth()->user();
        if (! $user) {
            return false;
        }

        if ($this->isAdminMapRequest()) {
            $rbac = app(RbacService::class);
            if ($rbac->isSuperAdmin($user) || $rbac->isVendorAdmin($user) || $rbac->isClientManager($user)) {
                return app(TraccarDeviceAccessService::class)->userCanAccessDevice($user, $device);
            }

            return false;
        }

        return true;
    }

    protected function assertFleetGeofenceAccess(Device $device): void
    {
        if (! $this->canManageGeofencesOnMap($device)) {
            abort(403, 'Not authorized to manage geofences for this device.');
        }
    }
}
