<?php

namespace App\Services\Tracking;

use App\Models\Device;
use App\Models\User;
use App\Services\Authorization\RbacService;

class DeviceMapIconAuthorization
{
    public function __construct(private RbacService $rbac) {}

    public function canView(User $user, Device $device): bool
    {
        if ($this->rbac->hasPermission($user, 'devices.manage')) {
            return true;
        }

        return $this->ownsDevice($user, $device);
    }

    /** Vehicle name, plate, and odometer on the user devices page. */
    public function canViewVehicleDetails(User $user, Device $device): bool
    {
        if ($this->bypassesDevicePolicy($user)) {
            return $this->canView($user, $device);
        }

        if (! $this->rbac->hasPermission($user, 'web.vehicles.view_details')) {
            return false;
        }

        return $this->ownsDevice($user, $device);
    }

    public function canEditVehicleLabel(User $user, Device $device): bool
    {
        return $this->canViewVehicleDetails($user, $device);
    }

    /** Account-level permission (before device scope). */
    public function hasChangeVehicleIconPermission(User $user): bool
    {
        return $this->bypassesDevicePolicy($user)
            || $this->rbac->hasPermission($user, 'devices.manage_map_icons');
    }

    public function hasChangeIconSizePermission(User $user): bool
    {
        return $this->bypassesDevicePolicy($user)
            || $this->rbac->hasPermission($user, 'devices.manage_map_icons')
            || $this->rbac->hasPermission($user, 'web.vehicles.change_icon_size')
            || $this->rbac->hasPermission($user, 'web.vehicles.upload_custom_icon')
            || $this->rbac->hasPermission($user, 'mobile.map.custom_icon');
    }

    public function hasUploadCustomIconPermission(User $user): bool
    {
        return $this->bypassesDevicePolicy($user)
            || $this->rbac->hasPermission($user, 'web.vehicles.upload_custom_icon')
            || $this->rbac->hasPermission($user, 'mobile.map.custom_icon');
    }

    public function hasAnyAppearancePermission(User $user): bool
    {
        return $this->hasChangeVehicleIconPermission($user)
            || $this->hasChangeIconSizePermission($user)
            || $this->hasUploadCustomIconPermission($user);
    }

    /** Preset vehicle type and marker style. */
    public function canChangeVehicleIcon(User $user, Device $device): bool
    {
        if (! $this->hasChangeVehicleIconPermission($user)) {
            return false;
        }

        return $this->bypassesDevicePolicy($user)
            ? $this->canView($user, $device)
            : $this->ownsDevice($user, $device);
    }

    /** Marker size slider and rotation toggle. */
    public function canChangeIconSize(User $user, Device $device): bool
    {
        if (! $this->hasChangeIconSizePermission($user)) {
            return false;
        }

        return $this->bypassesDevicePolicy($user)
            ? $this->canView($user, $device)
            : $this->ownsDevice($user, $device);
    }

    /** Show map marker appearance UI (any icon-related permission). */
    public function canEditAppearance(User $user, Device $device): bool
    {
        return $this->canChangeVehicleIcon($user, $device)
            || $this->canChangeIconSize($user, $device)
            || $this->canUploadCustomIcon($user, $device);
    }

    /** Upload / replace / delete custom raster or SVG icons. */
    public function canUploadCustomIcon(User $user, Device $device): bool
    {
        if (! $this->hasUploadCustomIconPermission($user)) {
            return false;
        }

        return $this->bypassesDevicePolicy($user)
            ? $this->canView($user, $device)
            : $this->ownsDevice($user, $device);
    }

    private function bypassesDevicePolicy(User $user): bool
    {
        return $this->rbac->hasPermission($user, 'devices.manage');
    }

    private function ownsDevice(User $user, Device $device): bool
    {
        return $user->trackerDevicesQuery()->where('id', $device->id)->exists();
    }
}
