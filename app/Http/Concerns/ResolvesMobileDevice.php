<?php

namespace App\Http\Concerns;

use App\Models\Device;
use App\Models\User;
use App\Services\DeviceAccessService;
use App\Services\DeviceSubscriptionService;
use App\Services\Mobile\MobileEntitlementService;
use App\Services\Tracking\GlobalTrackingService;
use Illuminate\Http\Exceptions\HttpResponseException;

trait ResolvesMobileDevice
{
    protected function findLinkedMobileDevice(User $user, int|string $id): Device
    {
        $deviceId = (int) $id;
        $tracking = app(GlobalTrackingService::class);

        if (! in_array($deviceId, $tracking->filterLinkedIds($user, [$deviceId]), true)) {
            throw new HttpResponseException(response()->json([
                'success' => false,
                'message' => 'Device not found or access denied.',
                'code' => 'not_found',
            ], 404));
        }

        return Device::query()
            ->with(['subscription.clientInvoice'])
            ->findOrFail($deviceId);
    }

    protected function findMobileDevice(User $user, int|string $id): Device
    {
        $deviceId = (int) $id;
        $tracking = app(GlobalTrackingService::class);

        if (! in_array($deviceId, $tracking->filterAllowedIds($user, [$deviceId]), true)) {
            $linked = in_array($deviceId, $tracking->filterLinkedIds($user, [$deviceId]), true);

            if ($linked) {
                throw new HttpResponseException(response()->json([
                    'success' => false,
                    'message' => app(DeviceSubscriptionService::class)->resubscribeMessage(
                        Device::query()->findOrFail($deviceId)
                    ),
                    'code' => 'subscription_inactive',
                ], 403));
            }

            throw new HttpResponseException(response()->json([
                'success' => false,
                'message' => 'Device not found or access denied.',
                'code' => 'not_found',
            ], 404));
        }

        $device = Device::query()
            ->with(['subscription.clientInvoice'])
            ->findOrFail($deviceId);

        $entitlement = app(MobileEntitlementService::class);
        $rbac = app(\App\Services\Authorization\RbacService::class);
        $check = app(DeviceAccessService::class)->evaluate(
            $user,
            $device,
            requireSubscription: $entitlement->isEndUser($user)
                && ! $rbac->bypassesSubscriptionRestrictions($user),
        );

        if (! $check['allowed']) {
            throw new HttpResponseException(response()->json([
                'success' => false,
                'message' => $check['message'] ?: $check['title'],
                'code' => $check['reason'],
            ], 403));
        }

        return $device;
    }
}
