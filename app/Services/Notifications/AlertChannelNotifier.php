<?php

namespace App\Services\Notifications;

use App\Models\User;
use App\Services\Billing\SubscriptionNotificationEntitlementService;
use App\Services\Tracking\NotificationPreferenceService;

class AlertChannelNotifier
{
    public function __construct(
        private NotificationPreferenceService $prefs,
        private SubscriptionNotificationEntitlementService $subscriptionNotifications,
        private AlertEmailNotifier $email,
        private AlertWhatsAppNotifier $whatsapp,
    ) {}

    /**
     * @param  array<string, mixed>  $context
     */
    public function dispatchToUsers(
        array $userIds,
        string $eventType,
        string $title,
        string $body,
        array $context = [],
    ): void {
        foreach (array_unique($userIds) as $userId) {
            $user = User::query()->find((int) $userId);
            if (! $user) {
                continue;
            }

            $this->dispatchToUser($user, $eventType, $title, $body, $context);
        }
    }

    /**
     * @param  array<string, mixed>  $context
     */
    public function dispatchToUser(
        User $user,
        string $eventType,
        string $title,
        string $body,
        array $context = [],
    ): void {
        $deviceId = isset($context['device_id']) ? (int) $context['device_id'] : null;

        if (
            $this->subscriptionNotifications->allowsEmailForDevice($deviceId, $eventType, $context)
            && $this->prefs->allowsEmail($user, $eventType)
        ) {
            try {
                $this->email->send($user, $title, $body, $context);
            } catch (\Throwable $e) {
                report($e);
            }
        }

        if (
            $this->subscriptionNotifications->allowsWhatsAppForDevice($deviceId, $eventType, $context)
            && $this->prefs->allowsWhatsApp($user, $eventType)
        ) {
            try {
                $this->whatsapp->send($user, $title, $body, $context);
            } catch (\Throwable $e) {
                report($e);
            }
        }
    }
}
