<?php

namespace App\Services;

use App\Contracts\Geofences\GeofenceStoreInterface;
use App\Contracts\Tracking\EventWriterInterface;
use App\Models\Device;
use App\Models\DeviceLocation;
use App\Models\VehicleEvent;
use App\Services\Mobile\VehicleStatusSpec;
use App\Services\Push\PushNotificationDispatcher;
use App\Services\SmartFleetAlertService;
use App\Services\Tracking\TrackingSettingsService;
use App\Support\Push\PushNotificationMapper;
use App\Support\Traccar\GeofenceWkt;
use Carbon\Carbon;

class VehicleEventService
{
    public function __construct(
        private EventWriterInterface $events,
        private GeofenceStoreInterface $geofences,
        private SmartFleetAlertService $smartAlerts,
        private TrackingSettingsService $trackingSettings,
    ) {}
    public function processLocation(Device $device, DeviceLocation $location, ?DeviceLocation $previous = null): void
    {
        $speed = (float) ($location->speed ?? 0);
        $lat = (float) $location->lat;
        $lng = (float) $location->lng;
        $at = $location->recorded_at ?? now();

        $this->smartAlerts->onPositionReceived($device, $location);
        $this->processMapStatusChange($device, $speed, (bool) $location->ignition, $lat, $lng, $at);
        $this->processGeofenceFromLocation($device, $lat, $lng, $at);
        $this->processSignals($device, $location, $speed, $lat, $lng, $at, $previous);
    }

    public function processGeofenceFromLocation(Device $device, float $lat, float $lng, Carbon $at): void
    {
        if (! config('tracking.laravel_geofence_detection', true)) {
            return;
        }

        $this->processGeofence($device, $lat, $lng, $at);
    }

    /**
     * Notify on ignition-aware map status (running / idle / parked) — not raw speed flicker.
     */
    private function processMapStatusChange(
        Device $device,
        float $speed,
        bool $ignition,
        float $lat,
        float $lng,
        Carbon $at,
    ): void {
        $mapKey = VehicleStatusSpec::normalizeKey(
            VehicleStatusSpec::motionKey($speed, $ignition),
        );

        if (! in_array($mapKey, ['running', 'idle', 'parked'], true)) {
            return;
        }

        $stateKey = "device.{$device->id}.map_status_notify";
        $previousKey = cache()->get($stateKey);

        if ($previousKey === $mapKey) {
            return;
        }

        cache()->put($stateKey, $mapKey, now()->addHours(24));

        $cooldown = (int) config(
            'tracking.push_motion_cooldown_seconds',
            config('tracking.event_cooldown_seconds', 300),
        );
        $cooldownKey = "device.{$device->id}.map_status_push";
        if (cache()->has($cooldownKey)) {
            return;
        }
        cache()->put($cooldownKey, true, now()->addSeconds($cooldown));

        [$title, $message] = match ($mapKey) {
            'running' => [
                'Engine On',
                sprintf('%s engine is on / moving.', $device->notificationDisplayName()),
            ],
            'idle' => [
                'Vehicle Idle',
                sprintf('%s is idle (ignition on).', $device->notificationDisplayName()),
            ],
            'parked' => [
                'Engine Off',
                sprintf('%s is parked (engine off).', $device->notificationDisplayName()),
            ],
            default => ['Fleet Alert', "{$device->notificationDisplayName()} status changed."],
        };

        $eventType = match ($mapKey) {
            'running' => VehicleEvent::TYPE_RUNNING,
            'idle' => 'idle',
            'parked' => 'parked',
            default => VehicleEvent::TYPE_STOPPED,
        };

        $pushType = PushNotificationMapper::mapStatusPushType(
            is_string($previousKey) ? $previousKey : null,
            $mapKey,
        );

        if ($pushType === null) {
            return;
        }

        if ($this->shouldPersistEvents()) {
            $event = $this->record($device, $eventType, $title, $message, $speed, $lat, $lng, $at);
            app(PushNotificationDispatcher::class)->forVehicleEvent(
                $device,
                $event,
                is_string($previousKey) ? $previousKey : null,
            );

            return;
        }

        app(PushNotificationDispatcher::class)->forTransientPush(
            $device,
            $pushType,
            $title,
            $message,
            $eventType,
            $at,
        );
    }

    private function shouldPersistEvents(): bool
    {
        return (bool) config('tracking.persist_notification_events', false);
    }

    private function processGeofence(Device $device, float $lat, float $lng, Carbon $at): void
    {
        $cacheKey = "device.{$device->id}.inside_geofence";
        $previousId = cache()->get($cacheKey);
        $currentId = $this->insideGeofence($lat, $lng, $device->id);

        if ($previousId !== null && $previousId !== $currentId) {
            $this->handleGeofenceTransition(
                $device,
                VehicleEvent::TYPE_GEOFENCE_EXIT,
                $previousId,
                $this->geofenceName($device, $previousId),
                $lat,
                $lng,
                $at,
            );
        }

        if ($currentId !== null && $previousId !== $currentId) {
            $this->handleGeofenceTransition(
                $device,
                VehicleEvent::TYPE_GEOFENCE_ENTER,
                $currentId,
                $this->geofenceName($device, $currentId),
                $lat,
                $lng,
                $at,
            );
        }

        cache()->put($cacheKey, $currentId, now()->addDays(7));
    }

    private function handleGeofenceTransition(
        Device $device,
        string $eventType,
        int $geofenceId,
        string $zoneName,
        float $lat,
        float $lng,
        Carbon $at,
    ): void {
        $dedupeKey = "device.{$device->id}.geofence.{$eventType}.{$geofenceId}";
        if (cache()->has($dedupeKey)) {
            return;
        }

        cache()->put($dedupeKey, true, now()->addSeconds(90));

        $isEnter = $eventType === VehicleEvent::TYPE_GEOFENCE_ENTER;
        $recordTitle = $isEnter ? 'Entered geofence' : 'Left geofence';
        $message = $isEnter
            ? sprintf('%s entered geofence "%s".', $device->notificationDisplayName(), $zoneName)
            : sprintf('%s exited geofence "%s".', $device->notificationDisplayName(), $zoneName);

        $pushType = $isEnter
            ? \App\Support\Push\PushNotificationType::GEOFENCE_ENTER
            : \App\Support\Push\PushNotificationType::GEOFENCE_EXIT;

        $pushTitle = $isEnter ? 'Geofence enter' : 'Geofence exit';

        $eventId = null;
        if ($this->shouldPersistEvents()) {
            $event = $this->record(
                $device,
                $eventType,
                $recordTitle,
                $message,
                null,
                $lat,
                $lng,
                $at,
                $geofenceId,
            );
            $eventId = $event->id > 0 ? $event->id : null;
        }

        app(PushNotificationDispatcher::class)->forGeofence(
            $device,
            $pushType,
            $pushTitle,
            $message,
            $geofenceId,
            $at,
            $eventId,
        );
    }

    private function processSignals(
        Device $device,
        DeviceLocation $location,
        float $speed,
        float $lat,
        float $lng,
        Carbon $at,
        ?DeviceLocation $previous
    ): void {
        $cfg = $this->trackingSettings->forDevice($device);
        $cooldown = (int) ($cfg['event_cooldown_seconds'] ?? config('tracking.event_cooldown_seconds', 300));

        if ($location->panic) {
            $this->recordOnce(
                "device.{$device->id}.event.panic",
                $cooldown,
                function () use ($device, $speed, $lat, $lng, $at) {
                    $event = $this->record(
                        $device,
                        VehicleEvent::TYPE_PANIC,
                        'SOS / Panic',
                        sprintf('Emergency panic activated on %s.', $device->notificationDisplayName()),
                        $speed,
                        $lat,
                        $lng,
                        $at
                    );
                    $this->smartAlerts->notifySecurityEvent($device, $event);
                }
            );
        }

        if ($location->power_cut) {
            $this->recordOnce(
                "device.{$device->id}.event.power",
                $cooldown,
                function () use ($device, $speed, $lat, $lng, $at) {
                    $event = $this->record(
                        $device,
                        VehicleEvent::TYPE_POWER_CUT,
                        'Power cut',
                        sprintf('External power cut detected on %s.', $device->notificationDisplayName()),
                        $speed,
                        $lat,
                        $lng,
                        $at
                    );
                    $this->smartAlerts->notifySecurityEvent($device, $event);
                }
            );
        }

        $lowBattery = (float) ($cfg['low_battery_percent'] ?? config('tracking.low_battery_percent', 20));
        if ($location->battery_level !== null && (float) $location->battery_level <= $lowBattery) {
            $this->recordOnce(
                "device.{$device->id}.event.battery",
                $cooldown * 2,
                function () use ($device, $location, $speed, $lat, $lng, $at, $lowBattery) {
                    $event = $this->record(
                        $device,
                        VehicleEvent::TYPE_LOW_BATTERY,
                        'Low battery',
                        sprintf('%s battery at %s%%.', $device->notificationDisplayName(), $location->battery_level),
                        $speed,
                        $lat,
                        $lng,
                        $at,
                        null,
                        ['battery' => $location->battery_level]
                    );
                    $this->smartAlerts->notifySecurityEvent($device, $event);
                }
            );
        }

        if ($speed > 10 && ! $location->ignition) {
            $this->recordOnce(
                "device.{$device->id}.event.ignition",
                $cooldown,
                function () use ($device, $speed, $lat, $lng, $at) {
                    $event = $this->record(
                        $device,
                        VehicleEvent::TYPE_IGNITION,
                        'Ignition alert',
                        sprintf('%s is moving at %.0f km/h with ignition OFF.', $device->notificationDisplayName(), $speed),
                        $speed,
                        $lat,
                        $lng,
                        $at
                    );
                    $this->smartAlerts->notifySecurityEvent($device, $event);
                }
            );
        }
    }

    private function recordOnce(string $cacheKey, int $seconds, callable $callback): void
    {
        if (cache()->has($cacheKey)) {
            return;
        }

        $callback();
        cache()->put($cacheKey, true, now()->addSeconds($seconds));
    }

    private function record(
        Device $device,
        string $type,
        string $title,
        string $message,
        ?float $speed,
        float $lat,
        float $lng,
        Carbon $at,
        ?int $geofenceId = null,
        array $meta = []
    ): VehicleEvent {
        if ($this->shouldPersistEvents()) {
            return $this->events->record(
                $device,
                $type,
                $title,
                $message,
                $speed,
                $lat,
                $lng,
                $at,
                $geofenceId,
                $meta
            );
        }

        return new VehicleEvent([
            'device_id' => $device->id,
            'geofence_id' => $geofenceId,
            'type' => $type,
            'title' => $title,
            'message' => $message,
            'speed' => $speed,
            'lat' => $lat,
            'lng' => $lng,
            'meta' => $meta ?: null,
            'occurred_at' => $at,
        ]);
    }

    private function insideGeofence(float $lat, float $lng, int $deviceId): ?int
    {
        $device = Device::query()->find($deviceId);

        if (! $device) {
            return null;
        }

        foreach ($this->geofences->forDevice($device) as $g) {
            $type = (string) ($g->type ?? 'polygon');
            if (GeofenceWkt::containsPoint(
                $lat,
                $lng,
                $g->area ?? null,
                $type,
                $g->coords ?? null,
                $g->center ?? null,
                isset($g->radius) ? (int) $g->radius : null
            )) {
                return (int) $g->id;
            }
        }

        return null;
    }

    private function geofenceName(Device $device, int $geofenceId): string
    {
        $match = $this->geofences->forDevice($device)->firstWhere('id', $geofenceId);

        return $match?->name ?? 'Zone';
    }

}
