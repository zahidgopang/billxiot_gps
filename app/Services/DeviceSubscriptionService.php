<?php

namespace App\Services;

use App\Models\Device;
use App\Models\Subscription;
use Carbon\Carbon;

class DeviceSubscriptionService
{
    public function subscriptionFor(Device $device): ?Subscription
    {
        return $device->relationLoaded('subscription')
            ? $device->subscription
            : $device->subscription()->first();
    }

    public function isActive(Device $device): bool
    {
        $subscription = $this->subscriptionFor($device);

        if (! $subscription) {
            return false;
        }

        $this->expireIfNeeded($subscription);

        if ($subscription->status !== 'active') {
            return false;
        }

        if ($subscription->starts_at && $subscription->starts_at->isFuture()) {
            return false;
        }

        if ($subscription->ends_at && $subscription->ends_at->endOfDay()->isPast()) {
            return false;
        }

        return true;
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

        $this->expireIfNeeded($subscription);

        if ($this->isActive($device)) {
            $ends = $subscription->ends_at
                ? ' · ' . __('app.user.devices.subscription_ends') . ' ' . $subscription->ends_at->format('M d, Y')
                : '';

            return [
                'label' => $subscription->plan . $ends,
                'class' => 'bg-success',
                'active' => true,
            ];
        }

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

        if ($subscription->starts_at && $subscription->starts_at->isFuture()) {
            return "The subscription for {$name} starts on {$subscription->starts_at->format('M d, Y')}. Map access will open on that date.";
        }

        return "The subscription for {$name} is not active. Please resubscribe to use the live map.";
    }
}
