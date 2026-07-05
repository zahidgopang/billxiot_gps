<?php

namespace App\Services\Push;

use App\Contracts\Tracking\EventWriterInterface;
use App\Jobs\SendPushNotificationJob;
use App\Models\Device;
use App\Models\User;
use App\Models\VehicleEvent;
use App\Services\Authorization\TenantScopeService;
use App\Services\Notifications\AlertChannelNotifier;
use App\Services\Tracking\NotificationPreferenceService;
use App\Support\Push\PushNotificationMapper;
use App\Support\Push\PushNotificationType;
use Carbon\Carbon;

class PushNotificationDispatcher
{
    public function __construct(
        private FirebasePushService $fcm,
        private NotificationPreferenceService $notificationPrefs,
        private AlertChannelNotifier $alertChannels,
        private EventWriterInterface $eventWriter,
    ) {}

    public function forVehicleEvent(
        Device $device,
        VehicleEvent $event,
        ?string $previousMotionState = null,
    ): void {
        $pushType = PushNotificationMapper::fromVehicleEventType($event->type)
            ?? PushNotificationMapper::mapStatusPushType($previousMotionState, $event->type)
            ?? PushNotificationMapper::motionPushType($previousMotionState, $event->type);

        if ($pushType === null) {
            return;
        }

        $this->send($device, $pushType, $event->title, $event->message, [
            'event_id' => (string) $event->id,
            'event_type' => $event->type,
            'geofence_id' => $event->geofence_id ? (string) $event->geofence_id : '',
            'occurred_at' => $event->occurred_at?->toIso8601String() ?? '',
        ], pushGate: 'event');
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
        ], pushGate: 'event');
    }

    public function forSmartAlert(Device $device, VehicleEvent $event, string $pushType): void
    {
        $this->send($device, $pushType, $event->title, $event->message, [
            'event_id' => (string) $event->id,
            'event_type' => $event->type,
            'occurred_at' => $event->occurred_at?->toIso8601String() ?? '',
            'severity' => $event->severity(),
        ], pushGate: 'event');
    }

    /**
     * Maintenance reminders — not gated by PUSH_EVENT_NOTIFICATIONS_ENABLED.
     */
    public function forMaintenance(Device $device, VehicleEvent $event): void
    {
        $this->send($device, PushNotificationType::MAINTENANCE_DUE, $event->title, $event->message, [
            'event_id' => (string) $event->id,
            'event_type' => VehicleEvent::TYPE_MAINTENANCE,
            'occurred_at' => $event->occurred_at?->toIso8601String() ?? '',
            'severity' => $event->severity(),
        ], pushGate: 'always');
    }

    public function dispatchRouteTripCompleted(
        Device $device,
        \App\Models\RoutePlan $route,
        \App\Models\TripLog $trip,
        ?int $travelMinutes = null,
    ): void {
        $vehicleName = $device->mapMarkerTitle();
        $routeLabel = $route->start_city.' → '.$route->destination_city;
        $completedAt = $trip->completed_at ?? now();
        $travelText = $travelMinutes !== null
            ? sprintf('%d min', $travelMinutes)
            : __('app.routes.travel_time_unknown');

        $title = __('app.routes.trip_completed_title', ['vehicle' => $vehicleName]);
        $body = __('app.routes.trip_completed_body', [
            'route' => $routeLabel,
            'time' => app_datetime_format($completedAt),
            'duration' => $travelText,
        ]);

        $lat = (float) ($trip->last_lat ?? $device->latestLocation?->lat ?? 0);
        $lng = (float) ($trip->last_lng ?? $device->latestLocation?->lng ?? 0);
        $at = $completedAt instanceof Carbon ? $completedAt : Carbon::parse($completedAt);

        $eventId = '';
        try {
            $event = $this->eventWriter->record(
                $device,
                VehicleEvent::TYPE_TRIP_COMPLETED,
                $title,
                $body,
                null,
                $lat,
                $lng,
                $at,
                null,
                [
                    'route_id' => $route->id,
                    'route_name' => $route->name,
                    'travel_minutes' => $travelMinutes,
                ],
            );
            $eventId = (string) $event->id;
        } catch (\Throwable $e) {
            report($e);
        }

        $this->send($device, PushNotificationType::TRIP_COMPLETED, $title, $body, [
            'event_id' => $eventId,
            'event_type' => VehicleEvent::TYPE_TRIP_COMPLETED,
            'route_id' => (string) $route->id,
            'route_name' => $route->name,
            'occurred_at' => $at->toIso8601String(),
            'travel_minutes' => (string) ($travelMinutes ?? ''),
        ], pushGate: 'always');
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

        $this->send($device, $pushType, $title, $message, $extra, pushGate: 'geofence');
    }

    /**
     * @param  array<string, string>  $extra
     * @param  'event'|'geofence'|'always'  $pushGate
     */
    private function send(
        Device $device,
        string $pushType,
        string $title,
        string $body,
        array $extra = [],
        string $pushGate = 'event',
    ): void {
        try {
            $this->dispatchSend($device, $pushType, $title, $body, $extra, $pushGate);
        } catch (\Throwable $e) {
            report($e);
        }
    }

    /**
     * @param  array<string, string>  $extra
     * @param  'event'|'geofence'|'always'  $pushGate
     */
    private function dispatchSend(
        Device $device,
        string $pushType,
        string $title,
        string $body,
        array $extra = [],
        string $pushGate = 'event',
    ): void {
        $userIds = $this->resolveUserIds($device);
        if ($userIds === []) {
            return;
        }

        $eventType = (string) ($extra['event_type'] ?? $pushType);

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
            'sound' => 'default',
            'notification_sound' => 'default',
            'android_channel_id' => 'billxiot_alerts',
        ], $extra);

        $context = [
            'device_id' => (int) $device->id,
            'device_name' => (string) $device->notificationDisplayName(),
            'time_display' => $timeDisplay ?? '',
            'event_type' => $eventType,
            'push_type' => $pushType,
        ];

        foreach (['route_id', 'geofence_id', 'event_id'] as $ctxKey) {
            if (isset($extra[$ctxKey]) && $extra[$ctxKey] !== '') {
                $context[$ctxKey] = is_numeric($extra[$ctxKey])
                    ? (int) $extra[$ctxKey]
                    : $extra[$ctxKey];
            }
        }

        try {
            if (PushNotificationType::deliverViaPush($pushType)) {
                $this->alertChannels->dispatchToUsers(
                    $userIds,
                    $eventType,
                    $displayTitle,
                    $body,
                    $context,
                );
            }
        } catch (\Throwable $e) {
            report($e);
        }

        if (! $this->fcm->enabled()) {
            return;
        }

        if ($pushGate === 'event' && ! config('services.firebase.event_notifications_enabled', false)) {
            return;
        }

        if ($pushGate === 'geofence' && ! config('services.firebase.geofence_notifications_enabled', true)) {
            return;
        }

        if (! PushNotificationType::deliverViaPush($pushType)) {
            return;
        }

        $pushUserIds = array_values(array_filter(
            $userIds,
            fn (int $uid) => $this->userAllowsPush($uid, $eventType)
        ));
        if ($pushUserIds === []) {
            return;
        }

        SendPushNotificationJob::dispatch($pushUserIds, $displayTitle, $bodyWithTime, $data)
            ->afterResponse();
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

        if ($ids === []) {
            $ownerId = $device->resolveTraccarOwnerUserId();
            if ($ownerId) {
                $ids = [(int) $ownerId];
            }
        }

        $staffIds = app(TenantScopeService::class)->staffPushRecipientIds($device);

        return array_values(array_unique(array_merge($ids, $staffIds)));
    }

    private function screenForType(string $pushType): string
    {
        return match ($pushType) {
            PushNotificationType::GEOFENCE_ENTER,
            PushNotificationType::GEOFENCE_EXIT => 'map',
            default => 'device',
        };
    }

    private function userAllowsPush(int $userId, string $eventType): bool
    {
        $user = User::query()->find($userId);
        if (! $user) {
            return true;
        }

        return $this->notificationPrefs->allowsPush($user, $eventType);
    }
}
