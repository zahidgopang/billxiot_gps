<?php

namespace App\Services\Push;

use App\Models\Device;
use App\Models\VehicleEvent;
use App\Support\Push\PushNotificationMapper;
use App\Support\Push\PushNotificationType;

class PushNotificationDispatcher
{
    public function __construct(
        private FirebasePushService $fcm,
    ) {}

    public function forVehicleEvent(
        Device $device,
        VehicleEvent $event,
        ?string $previousMotionState = null,
    ): void {
        $pushType = PushNotificationMapper::fromVehicleEventType($event->type)
            ?? PushNotificationMapper::motionPushType($previousMotionState, $event->type);

        if ($pushType === null) {
            return;
        }

        $this->send($device, $pushType, $event->title, $event->message, [
            'event_id' => (string) $event->id,
            'event_type' => $event->type,
            'geofence_id' => $event->geofence_id ? (string) $event->geofence_id : '',
            'occurred_at' => $event->occurred_at?->toIso8601String() ?? '',
        ], requireEventFlag: true);
    }

    public function forConnectivity(Device $device, string $pushType, string $message): void
    {
        if (! in_array($pushType, [
            PushNotificationType::DEVICE_ONLINE,
            PushNotificationType::DEVICE_OFFLINE,
        ], true)) {
            return;
        }

        $title = PushNotificationType::title($pushType);
        $this->send($device, $pushType, $title, $message, [
            'occurred_at' => \App\Support\DateTime\AppDateTime::now()->toIso8601String(),
            'severity' => PushNotificationType::severity($pushType),
        ], requireEventFlag: true);
    }

    public function forSmartAlert(Device $device, VehicleEvent $event, string $pushType): void
    {
        $this->send($device, $pushType, $event->title, $event->message, [
            'event_id' => (string) $event->id,
            'event_type' => $event->type,
            'occurred_at' => $event->occurred_at?->toIso8601String() ?? '',
            'severity' => $event->severity(),
        ], requireEventFlag: true);
    }

    /**
     * Geofence enter/exit — always sent when push + geofence notifications are enabled.
     */
    public function forGeofence(
        Device $device,
        string $pushType,
        string $title,
        string $message,
        int $geofenceId,
        \Carbon\CarbonInterface $at,
        ?int $eventId = null,
    ): void {
        if (! in_array($pushType, [
            PushNotificationType::GEOFENCE_ENTER,
            PushNotificationType::GEOFENCE_EXIT,
        ], true)) {
            return;
        }

        $extra = [
            'event_type' => $pushType === PushNotificationType::GEOFENCE_ENTER
                ? VehicleEvent::TYPE_GEOFENCE_ENTER
                : VehicleEvent::TYPE_GEOFENCE_EXIT,
            'geofence_id' => (string) $geofenceId,
            'occurred_at' => $at->toIso8601String(),
        ];

        if ($eventId !== null && $eventId > 0) {
            $extra['event_id'] = (string) $eventId;
        }

        $this->send($device, $pushType, $title, $message, $extra, requireEventFlag: false);
    }

    /**
     * @param  array<string, string>  $extra
     */
    private function send(
        Device $device,
        string $pushType,
        string $title,
        string $body,
        array $extra = [],
        bool $requireEventFlag = true,
    ): void {
        try {
            $this->dispatchSend($device, $pushType, $title, $body, $extra, $requireEventFlag);
        } catch (\Throwable $e) {
            report($e);
        }
    }

    /**
     * @param  array<string, string>  $extra
     */
    private function dispatchSend(
        Device $device,
        string $pushType,
        string $title,
        string $body,
        array $extra = [],
        bool $requireEventFlag = true,
    ): void {
        if (! $this->fcm->enabled()) {
            return;
        }

        if ($requireEventFlag && ! config('firebase.event_notifications_enabled', false)) {
            return;
        }

        if (! $requireEventFlag && ! config('firebase.geofence_notifications_enabled', true)) {
            return;
        }

        $userIds = $this->resolveUserIds($device);
        if ($userIds === []) {
            return;
        }

        $displayTitle = PushNotificationType::title($pushType);
        if ($title !== '' && $title !== $displayTitle) {
            $displayTitle = $title;
        }

        $occurredAt = isset($extra['occurred_at'])
            ? \App\Support\DateTime\AppDateTime::parse($extra['occurred_at'])
            : \App\Support\DateTime\AppDateTime::now();
        unset($extra['occurred_at']);

        $timeDisplay = \App\Support\DateTime\AppDateTime::format($occurredAt, 'display');
        $bodyWithTime = $timeDisplay
            ? rtrim($body).' · '.$timeDisplay
            : $body;

        $data = array_merge([
            'type' => $pushType,
            'screen' => $this->screenForType($pushType),
            'device_id' => (string) $device->id,
            'device_name' => (string) $device->notificationDisplayName(),
            'time' => \App\Support\DateTime\AppDateTime::toApi($occurredAt),
            'time_display' => $timeDisplay ?? '',
            'severity' => $extra['severity'] ?? PushNotificationType::severity($pushType),
        ], $extra);

        $this->fcm->sendToUsers($userIds, $displayTitle, $bodyWithTime, $data);
    }

    /**
     * Test push (ignores PUSH_EVENT_NOTIFICATIONS_ENABLED).
     *
     * @param  array<string, string>  $data
     * @return array{sent: int, failed: int, skipped: int}
     */
    public function sendTestToUser(int $userId, string $title, string $body, array $data = []): array
    {
        return $this->fcm->sendToUser($userId, $title, $body, array_merge([
            'type' => 'test',
            'screen' => 'notifications',
        ], $data));
    }

    /**
     * @return array<int, int>
     */
    private function resolveUserIds(Device $device): array
    {
        $relation = $device->users();
        $userKey = $relation->getRelated()->getQualifiedKeyName();

        $ids = $relation
            ->pluck($userKey)
            ->map(fn ($id) => (int) $id)
            ->filter(fn ($id) => $id > 0)
            ->all();

        if ($ids !== []) {
            return array_values(array_unique($ids));
        }

        $ownerId = $device->resolveTraccarOwnerUserId();

        return $ownerId ? [(int) $ownerId] : [];
    }

    private function screenForType(string $pushType): string
    {
        return match ($pushType) {
            PushNotificationType::GEOFENCE_ENTER,
            PushNotificationType::GEOFENCE_EXIT => 'map',
            default => 'device',
        };
    }
}
