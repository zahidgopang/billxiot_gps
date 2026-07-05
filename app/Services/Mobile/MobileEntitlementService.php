<?php

namespace App\Services\Mobile;

use App\Enums\AppRole;
use App\Enums\BillingInvoiceStatus;
use App\Models\BillingInvoice;
use App\Models\Device;
use App\Models\Subscription;
use App\Models\User;
use App\Services\Authorization\RbacService;
use App\Services\Authorization\PermissionCatalogService;
use App\Services\DeviceSubscriptionService;
use App\Services\Traccar\TraccarTrackingGate;
use App\Services\Traccar\TraccarUserAccessService;
use Illuminate\Support\Collection;

/**
 * End-user mobile app access: account, subscription, and payment gates.
 */
class MobileEntitlementService
{
    public const CODE_OK = 'ok';

    public const CODE_INVALID_ROLE = 'invalid_role';

    public const CODE_ACCOUNT_INACTIVE = 'account_inactive';

    public const CODE_SUBSCRIPTION_EXPIRED = 'subscription_expired';

    public const CODE_PAYMENT_DUE = 'payment_due';

    public const CODE_NO_DEVICES = 'no_devices';

    public function __construct(
        private RbacService $rbac,
        private TraccarUserAccessService $trackerUsers,
        private TraccarTrackingGate $trackingGate,
        private DeviceSubscriptionService $subscriptions,
    ) {}

    public function isEndUser(User $user): bool
    {
        return $this->rbac->isEndUser($user);
    }

    public function canAccessMobileApp(User $user): bool
    {
        return $this->isEndUser($user) || $this->rbac->canAccessPanel($user);
    }

    /**
     * Staff (admin/client) mobile access — active account only, no subscription gate.
     *
     * @return array{allowed: bool, code: string, message: string}
     */
    public function evaluateStaff(User $user): array
    {
        if (! $this->trackingGate->userIsTrackable($user)) {
            return $this->deny(self::CODE_ACCOUNT_INACTIVE, 'Account inactive');
        }

        if (! $this->trackerUsers->hasTrackerAccount($user)) {
            return $this->deny(self::CODE_ACCOUNT_INACTIVE, 'Account inactive');
        }

        return ['allowed' => true, 'code' => self::CODE_OK, 'message' => ''];
    }

    /**
     * Login / account gate — active account only (no subscription required).
     *
     * @return array{allowed: bool, code: string, message: string}
     */
    public function evaluateAccountAccess(User $user): array
    {
        if (! $this->isEndUser($user)) {
            return $this->deny(self::CODE_INVALID_ROLE, 'This API is only available for end-user accounts.');
        }

        if (! $this->trackingGate->userIsTrackable($user)) {
            return $this->deny(self::CODE_ACCOUNT_INACTIVE, 'Account inactive');
        }

        if (! $this->trackerUsers->hasTrackerAccount($user)) {
            return $this->deny(self::CODE_ACCOUNT_INACTIVE, 'Account inactive');
        }

        return ['allowed' => true, 'code' => self::CODE_OK, 'message' => ''];
    }

    /**
     * Full entitlement including at least one subscribed device (legacy strict gate).
     *
     * @return array{allowed: bool, code: string, message: string}
     */
    public function evaluate(User $user): array
    {
        $account = $this->evaluateAccountAccess($user);

        if (! $account['allowed']) {
            return $account;
        }

        $devices = $user->trackerDevicesQuery()->with(['subscription.clientInvoice'])->get();

        if ($devices->isEmpty()) {
            return $this->deny(self::CODE_NO_DEVICES, 'No devices are linked to your account.');
        }

        $states = $devices->map(fn (Device $device) => $this->deviceEntitlementState($device));

        if ($states->contains(fn (array $s) => $s['eligible'])) {
            return ['allowed' => true, 'code' => self::CODE_OK, 'message' => ''];
        }

        if ($states->every(fn (array $s) => $s['payment_due'])) {
            return $this->deny(self::CODE_PAYMENT_DUE, 'Payment due');
        }

        if ($states->every(fn (array $s) => $s['subscription_expired'] || $s['no_subscription'])) {
            return $this->deny(self::CODE_SUBSCRIPTION_EXPIRED, 'Subscription expired');
        }

        return $this->deny(self::CODE_SUBSCRIPTION_EXPIRED, 'Subscription expired');
    }

    /**
     * @return array{eligible: bool, subscription_expired: bool, payment_due: bool, no_subscription: bool}
     */
    public function deviceEntitlementState(Device $device): array
    {
        $subscription = $this->subscriptions->subscriptionFor($device);

        if (! $subscription) {
            return [
                'eligible' => false,
                'subscription_expired' => false,
                'payment_due' => false,
                'no_subscription' => true,
            ];
        }

        $this->subscriptions->expireIfNeeded($subscription);

        $subscriptionActive = $this->subscriptions->isActive($device);
        $paymentOk = $this->isPaymentValid($subscription);

        return [
            'eligible' => $subscriptionActive && $paymentOk,
            'subscription_expired' => ! $subscriptionActive,
            'payment_due' => $subscriptionActive && ! $paymentOk,
            'no_subscription' => false,
        ];
    }

    public function isPaymentValid(Subscription $subscription): bool
    {
        $invoice = $subscription->relationLoaded('clientInvoice')
            ? $subscription->clientInvoice
            : $subscription->clientInvoice()->first();

        if (! $invoice) {
            return true;
        }

        return in_array($invoice->status, [
            BillingInvoiceStatus::Paid->value,
            BillingInvoiceStatus::Partial->value,
        ], true);
    }

    /**
     * @return array<string, mixed>|null
     */
    public function subscriptionSummaryForUser(User $user): ?array
    {
        $devices = $user->trackerDevicesQuery()->with(['subscription.clientInvoice'])->get();
        $active = $devices->first(fn (Device $d) => $this->deviceEntitlementState($d)['eligible']);

        if (! $active) {
            $device = $devices->first();
        } else {
            $device = $active;
        }

        if (! $device) {
            return null;
        }

        $subscription = $this->subscriptions->subscriptionFor($device);
        if (! $subscription) {
            return null;
        }

        $invoice = $subscription->clientInvoice;

        return [
            'plan' => $subscription->plan,
            'status' => $subscription->status,
            'starts_at' => app_datetime_api($subscription->starts_at),
            'ends_at' => app_datetime_api($subscription->ends_at),
            'starts_at_display' => app_datetime_format($subscription->starts_at, 'date'),
            'ends_at_display' => app_datetime_format($subscription->ends_at, 'date'),
            'active' => $this->subscriptions->isActive($device),
            'payment_status' => $invoice?->status ?? 'none',
            'balance_due' => $invoice ? (float) $invoice->balance_due : 0,
        ];
    }

    /**
     * @return list<string>
     */
    public function permissionsFor(User $user): array
    {
        return $this->rbac->grantedPermissionsFor($user);
    }

    /**
     * @return array<string, bool>
     */
    public function permissionMapFor(User $user): array
    {
        try {
            return app(PermissionCatalogService::class)->effectivePermissionMap($user, $this->rbac);
        } catch (\Throwable) {
            $granted = $this->permissionsFor($user);
            if (in_array('*', $granted, true)) {
                return ['*' => true];
            }

            return array_fill_keys($granted, true);
        }
    }

    /**
     * @return Collection<int, Device>
     */
    public function accessibleDevices(User $user): Collection
    {
        return $this->trackingGate->filterTrackable(
            $user,
            $user->trackerDevicesQuery()->with(['subscription.clientInvoice'])->get()
        );
    }

    /**
     * @param  array{allowed: bool, code: string, message: string}  $result
     */
    private function deny(string $code, string $message): array
    {
        return ['allowed' => false, 'code' => $code, 'message' => $message];
    }
}
