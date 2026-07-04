<?php

namespace App\Services\Billing;

use App\Models\Device;
use App\Models\Subscription;
use App\Models\VehicleEvent;
use App\Support\Billing\SubscriptionNotificationConfig;
use App\Support\Billing\SubscriptionPlanNotificationCatalog;
use App\Services\DeviceSubscriptionService;

class SubscriptionNotificationEntitlementService
{
    public function __construct(
        private DeviceSubscriptionService $subscriptions,
        private SubscriptionPlanNotificationCatalog $catalog,
        private SubscriptionNotificationConfig $subscriptionConfig,
    ) {}

    /**
     * @param  array<string, mixed>  $context
     */
    public function allowsEmailForDevice(?int $deviceId, ?string $eventType = null, array $context = []): bool
    {
        return $this->channelAllows($deviceId, 'email', $eventType, $context);
    }

    /**
     * @param  array<string, mixed>  $context
     */
    public function allowsWhatsAppForDevice(?int $deviceId, ?string $eventType = null, array $context = []): bool
    {
        return $this->channelAllows($deviceId, 'whatsapp', $eventType, $context);
    }

    /**
     * @param  array<string, mixed>  $context
     */
    private function channelAllows(?int $deviceId, string $channel, ?string $eventType, array $context): bool
    {
        if (! $deviceId) {
            return false;
        }

        $device = Device::query()->find($deviceId);
        if (! $device) {
            return false;
        }

        if (! $this->subscriptions->isActive($device)) {
            return false;
        }

        $subscription = $this->subscriptions->subscriptionFor($device);
        if (! $subscription) {
            return false;
        }

        $subscription->loadMissing('subscriptionPlan');

        if (! $this->subscriptionChannelEnabled($subscription, $channel)) {
            return false;
        }

        if ($eventType === null || $eventType === '') {
            return true;
        }

        if (! $this->planAllowsEvent($subscription, $channel, $eventType, $context)) {
            return false;
        }

        return $this->subscriptionAllowsEvent($subscription, $channel, $eventType, $context);
    }

    /**
     * @param  array<string, mixed>  $context
     */
    private function subscriptionAllowsEvent(Subscription $subscription, string $channel, string $eventType, array $context): bool
    {
        $config = $channel === 'email'
            ? $subscription->notification_email_config
            : $subscription->notification_whatsapp_config;

        if ($this->subscriptionConfig->usesLegacyEntitlements($config)) {
            return true;
        }

        $canonical = $this->catalog->canonicalKey($eventType);

        if ($canonical === VehicleEvent::TYPE_TRIP_COMPLETED) {
            $routeId = isset($context['route_id']) ? (int) $context['route_id'] : null;

            return $this->subscriptionConfig->routeEnabled($config, $routeId);
        }

        return $this->subscriptionConfig->typeEnabled($config, $eventType);
    }

    private function subscriptionChannelEnabled(Subscription $subscription, string $channel): bool
    {
        return match ($channel) {
            'email' => (bool) $subscription->notification_email_enabled,
            'whatsapp' => (bool) $subscription->notification_whatsapp_enabled,
            default => false,
        };
    }

    /**
     * @param  array<string, mixed>  $context
     */
    private function planAllowsEvent(Subscription $subscription, string $channel, string $eventType, array $context): bool
    {
        $plan = $subscription->subscriptionPlan;
        if (! $plan) {
            return true;
        }

        $canonical = $this->catalog->canonicalKey($eventType);

        if ($canonical === VehicleEvent::TYPE_TRIP_COMPLETED) {
            $routeId = isset($context['route_id']) ? (int) $context['route_id'] : null;

            return $this->catalog->allowsRouteNotification(
                $plan->notification_route_ids,
                $routeId,
            );
        }

        $enabledTypes = $channel === 'email'
            ? $plan->notification_email_types
            : $plan->notification_whatsapp_types;

        return $this->catalog->allowsStandardType($enabledTypes, $eventType);
    }
}
