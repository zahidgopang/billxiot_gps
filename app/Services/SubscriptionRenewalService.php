<?php

namespace App\Services;

use App\Models\Subscription;
use App\Models\SubscriptionHistory;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class SubscriptionRenewalService
{
    public function __construct(
        private DeviceSubscriptionService $subscriptions
    ) {}

    public function syncExpiredStatus(Subscription $subscription): Subscription
    {
        $this->subscriptions->expireIfNeeded($subscription);

        // Preserve any eager-loaded relations (e.g. invoices) for list screens.
        $relations = array_keys($subscription->getRelations());

        return $relations !== []
            ? $subscription->fresh($relations)
            : $subscription->refresh();
    }

    public function canRenew(Subscription $subscription): bool
    {
        $this->syncExpiredStatus($subscription);

        return $subscription->status === 'expired';
    }

    public function renew(
        Subscription $subscription,
        Carbon $startsAt,
        Carbon $endsAt,
        User $admin
    ): Subscription {
        if (! $this->canRenew($subscription)) {
            throw new InvalidArgumentException('Only expired subscriptions can be renewed.');
        }

        return DB::transaction(function () use ($subscription, $startsAt, $endsAt, $admin) {
            SubscriptionHistory::create([
                'subscription_id' => $subscription->id,
                'user_id' => $subscription->user_id,
                'device_id' => $subscription->device_id,
                'plan' => $subscription->plan,
                'starts_at' => $subscription->starts_at,
                'ends_at' => $subscription->ends_at,
                'status' => $subscription->status,
                'archived_at' => now(),
                'archived_by' => $admin->id,
            ]);

            $subscription->update([
                'starts_at' => $startsAt->copy()->startOfDay(),
                'ends_at' => $endsAt->copy()->startOfDay(),
                'status' => 'active',
            ]);

            return $subscription->fresh(['user', 'device', 'histories']);
        });
    }
}
