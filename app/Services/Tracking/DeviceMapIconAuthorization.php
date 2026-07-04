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

        return $user->trackerDevicesQuery()->where('id', $device->id)->exists();
    }

    /** Preset icon, size, style, rotation toggles. */
    public function canEditAppearance(User $user, Device $device): bool
    {
        if ($this->rbac->hasPermission($user, 'devices.manage')) {
            return true;
        }

        if ($this->rbac->hasPermission($user, 'devices.manage_map_icons')
            || $this->rbac->hasPermission($user, 'web.vehicles.change_icon_size')
            || $this->rbac->hasPermission($user, 'mobile.map.custom_icon')) {
            return $user->trackerDevicesQuery()->where('id', $device->id)->exists();
        }

        return $user->trackerDevicesQuery()->where('id', $device->id)->exists();
    }

    /** Upload / replace / delete custom raster or SVG icons. */
    public function canUploadCustomIcon(User $user, Device $device): bool
    {
        if ($this->rbac->hasPermission($user, 'devices.manage')) {
            return true;
        }

        if ($this->rbac->hasPermission($user, 'devices.manage_map_icons')) {
            return $this->canEditAppearance($user, $device);
        }

        if ($this->rbac->hasPermission($user, 'web.vehicles.upload_custom_icon')
            || $this->rbac->hasPermission($user, 'mobile.map.custom_icon')) {
            return $this->canEditAppearance($user, $device);
        }

        return false;
    }
}
