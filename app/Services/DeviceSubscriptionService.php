<?php

namespace App\Services;

use App\Models\Device;
use App\Models\Subscription;
use Carbon\Carbon;

class DeviceSubscriptionService
{
    /**
     * Most recent subscription row for this device (renewals / re-subscribes create new rows).
     */
    public function subscriptionFor(Device $device): ?Subscription
    {
        if ($device->relationLoaded('subscription')) {
            return $device->subscription;
        }

        return $this->latestSubscriptionQuery($device->id)->first();
    }

    public function isActive(Device $device): bool
    {
        $subscription = $this->subscriptionFor($device);

        if (! $subscription) {
            return false;
        }

        return $this->subscriptionIsCurrentlyActive($subscription);
    }

    /**
     * True when the device's latest subscription row is active and within its date window.
     */
    public function subscriptionIsCurrentlyActive(Subscription $subscription): bool
    {
        $this->expireIfNeeded($subscription);

        if ($subscription->status !== 'active') {
            return false;
        }

        if ($subscription->starts_at && $this->startsOnFutureCalendarDay($subscription)) {
            return false;
        }

        if ($subscription->ends_at && $subscription->ends_at->copy()->endOfDay()->isPast()) {
            return false;
        }

        return true;
    }

    /**
     * Expire active subscription row(s) for a device when creating a replacement.
     * Previous period ends on the new subscription start date (calendar day).
     *
     * @return list<int> Superseded subscription IDs
     */
    public function supersedeActiveSubscriptionsForDevice(int $deviceId, Carbon $newStartsAt): array
    {
        $endsOn = $newStartsAt->copy()->startOfDay()->toDateString();
        $superseded = [];

        Subscription::query()
            ->where('device_id', $deviceId)
            ->where('status', 'active')
            ->orderByDesc('id')
            ->get()
            ->each(function (Subscription $subscription) use ($endsOn, &$superseded) {
                $subscription->update([
                    'status' => 'expired',
                    'ends_at' => $endsOn,
                ]);

                $superseded[] = (int) $subscription->id;
            });

        return $superseded;
    }

    /**
     * Block new subscription create when the latest row for the device is still active.
     * When editing, pass $exceptSubscriptionId to ignore the row being updated.
     */
    public function deviceHasActiveSubscription(int $deviceId, ?int $exceptSubscriptionId = null): bool
    {
        if ($exceptSubscriptionId === null) {
            $latest = $this->latestSubscriptionQuery($deviceId)->first();

            return $latest !== null && $this->subscriptionIsCurrentlyActive($latest);
        }

        return Subscription::query()
            ->where('device_id', $deviceId)
            ->where('id', '!=', $exceptSubscriptionId)
            ->orderByDesc('id')
            ->get()
            ->contains(fn (Subscription $subscription) => $this->subscriptionIsCurrentlyActive($subscription));
    }

    /**
     * Subscription start dates are calendar days — active from 00:00 on starts_at.
     */
    public function startsOnFutureCalendarDay(Subscription $subscription): bool
    {
        if (! $subscription->starts_at) {
            return false;
        }

        return now()->startOfDay()->lt($subscription->starts_at->copy()->startOfDay());
    }

    public function expireIfNeeded(Subscription $subscription): void
    {
        if ($subscription->status !== 'active') {
            return;
        }

        if ($subscription->ends_at && $subscription->ends_at->endOfDay()->isPast()) {
            $subscription->update(['status' => 'expired']);
            $subscription->refresh();
        }
    }

    public function statusLabel(Device $device): array
    {
        $subscription = $this->subscriptionFor($device);

        if (! $subscription) {
            return ['label' => __('app.user.devices.subscription_no_plan'), 'class' => 'bg-secondary', 'active' => false];
        }

        if ($this->subscriptionIsCurrentlyActive($subscription)) {
            $ends = $subscription->ends_at
                ? ' · ' . __('app.user.devices.subscription_ends') . ' ' . $subscription->ends_at->format('M d, Y')
                : '';

            return [
                'label' => $subscription->plan . $ends,
                'class' => 'bg-success',
                'active' => true,
            ];
        }

        $this->expireIfNeeded($subscription);

        return match ($subscription->status) {
            'cancelled' => ['label' => __('app.forms.cancelled'), 'class' => 'bg-warning text-dark', 'active' => false],
            default => ['label' => __('app.forms.expired'), 'class' => 'bg-danger', 'active' => false],
        };
    }

    public function inactiveMessage(Device $device): string
    {
        return $this->resubscribeMessage($device);
    }

    public function resubscribeMessage(Device $device): string
    {
        $subscription = $this->subscriptionFor($device);
        $name = $device->name ?: 'this device';

        if (! $subscription) {
            return "No active subscription for {$name}. Please resubscribe to use the live map. Contact your administrator or support team.";
        }

        $this->expireIfNeeded($subscription);

        if ($subscription->status === 'cancelled') {
            return "The subscription for {$name} was cancelled. Please resubscribe to restore map tracking and live GPS features.";
        }

        if ($subscription->ends_at && $subscription->ends_at->endOfDay()->isPast()) {
            return "The subscription for {$name} ended on {$subscription->ends_at->format('M d, Y')}. Please resubscribe to continue using the map.";
        }

        if ($subscription->starts_at && $this->startsOnFutureCalendarDay($subscription)) {
            return "The subscription for {$name} starts on {$subscription->starts_at->format('M d, Y')}. Map access will open on that date.";
        }

        return "The subscription for {$name} is not active. Please resubscribe to use the live map.";
    }

    private function latestSubscriptionQuery(int $deviceId)
    {
        return Subscription::query()
            ->where('device_id', $deviceId)
            ->orderByDesc('id');
    }
}
