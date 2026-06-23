<?php

namespace App\Services;

use App\Models\Device;
use App\Models\User;
use App\Services\Traccar\TraccarDeviceAccessService;
use App\Services\Traccar\TraccarTrackingGate;

class DeviceAccessService
{
    public function __construct(
        private DeviceSubscriptionService $subscriptions,
        private TraccarDeviceAccessService $traccarDevices,
        private TraccarTrackingGate $trackingGate,
    ) {}

    /**
     * Gate for GPS ingest API (POST /api/device/data).
     * Inactive devices may still report when TRACCAR_ALLOW_INACTIVE_INGEST=true.
     */
    public function evaluateForIngest(?User $user, Device $device): array
    {
        $allowInactive = config('traccar.allow_inactive_ingest', true);

        return $this->evaluate($user, $device, allowInactiveDevice: $allowInactive, requireSubscription: false);
    }

    public function evaluate(
        ?User $user,
        Device $device,
        bool $allowInactiveDevice = false,
        bool $requireSubscription = true
    ): array {
        if (! $user) {
            return $this->deny('no_owner', 'Device not assigned', 'This device is not linked to an active account.');
        }

        $gate = $this->trackingGate->canShowTracking(
            $user,
            $device,
            requireSubscription: $requireSubscription,
            allowInactiveDevice: $allowInactiveDevice
        );

        if ($gate['allowed']) {
            return [
                'allowed' => true,
                'reason' => 'ok',
                'title' => '',
                'message' => '',
                'action' => 'allow',
            ];
        }

        return match ($gate['reason']) {
            'user_inactive' => $this->deny(
                'user_inactive',
                'Account inactive',
                'Your account is inactive. Map tracking is disabled. Please contact support to reactivate your account.'
            ),
            'no_tc_user_device', 'unauthorized' => $this->deny(
                'unauthorized',
                'Access denied',
                'You do not have permission to access this device.'
            ),
            'device_disabled', 'device_inactive' => $this->deny(
                'device_inactive',
                'Device inactive',
                'This device is marked inactive. Contact support or your administrator to enable tracking.'
            ),
            'device_blocked' => $this->deny(
                'device_blocked',
                'Device blocked',
                'This device has been blocked by an administrator. Map access is not available.'
            ),
            'subscription_inactive' => $this->deny(
                'subscription_inactive',
                'Subscription required',
                $this->subscriptions->resubscribeMessage($device)
            ),
            default => $this->deny(
                'unauthorized',
                'Access denied',
                'You do not have permission to access this device.'
            ),
        };
    }

    public function canUseMap(?User $user, Device $device): bool
    {
        return $this->evaluate($user, $device)['allowed'];
    }

    public function isUserActive(User $user): bool
    {
        return $this->trackingGate->userIsTrackable($user);
    }

    private function deny(string $reason, string $title, string $message): array
    {
        return [
            'allowed' => false,
            'reason' => $reason,
            'title' => $title,
            'message' => $message,
            'action' => 'resubscribe',
        ];
    }
}
