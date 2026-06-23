<?php

namespace App\Services\Traccar;

use App\Models\Device;
use App\Models\User;
use App\Services\DeviceSubscriptionService;

/**
 * Whether live tracking / maps / dashboards may show a device (tc_users + tc_devices + subscription).
 */
class TraccarTrackingGate
{
    public function __construct(
        private TraccarUserAccessService $users,
        private TraccarDeviceAccessService $devices,
        private DeviceSubscriptionService $subscriptions,
    ) {}

    public function userIsTrackable(User $user): bool
    {
        if ($user->isAdmin()) {
            return true;
        }

        return $this->users->hasTrackerAccount($user)
            && (int) ($user->getAttributes()['disabled'] ?? 0) === 0
            && ($user->status ?? 'active') === 'active';
    }

    public function deviceIsEnabledInTraccar(Device $device): bool
    {
        return (int) ($device->getAttributes()['disabled'] ?? 0) === 0
            && $device->status === 'active';
    }

    /**
     * @return array{allowed: bool, reason: string}
     */
    public function canShowTracking(
        ?User $user,
        Device $device,
        bool $requireSubscription = true,
        bool $allowInactiveDevice = false
    ): array {
        if (! $user) {
            return ['allowed' => false, 'reason' => 'no_user'];
        }

        if (! $this->userIsTrackable($user)) {
            return ['allowed' => false, 'reason' => 'user_inactive'];
        }

        if (! $this->devices->userCanAccessDevice($user, $device)) {
            return ['allowed' => false, 'reason' => 'no_tc_user_device'];
        }

        if ($device->status === 'blocked') {
            return ['allowed' => false, 'reason' => 'device_blocked'];
        }

        if (! $allowInactiveDevice && $device->status === 'inactive') {
            return ['allowed' => false, 'reason' => 'device_inactive'];
        }

        if (! $allowInactiveDevice && ! $this->deviceIsEnabledInTraccar($device)) {
            return ['allowed' => false, 'reason' => 'device_disabled'];
        }

        if ($requireSubscription && ! $this->subscriptions->isActive($device)) {
            return ['allowed' => false, 'reason' => 'subscription_inactive'];
        }

        return ['allowed' => true, 'reason' => 'ok'];
    }

    public function filterTrackable(
        User $user,
        iterable $devices,
        bool $requireSubscription = true,
        bool $allowInactiveDevice = false
    ): \Illuminate\Support\Collection {
        return collect($devices)->filter(
            fn (Device $device) => $this->canShowTracking(
                $user,
                $device,
                $requireSubscription,
                $allowInactiveDevice
            )['allowed']
        )->values();
    }
}
