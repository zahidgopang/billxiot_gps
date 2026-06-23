<?php

namespace App\Services;

use App\Contracts\Tracking\EventWriterInterface;
use App\Contracts\Tracking\PositionReaderInterface;
use App\Models\Device;
use App\Models\DeviceLocation;
use App\Models\VehicleEvent;
use App\Services\Mobile\MobileMapStatusResolver;
use App\Services\Push\PushNotificationDispatcher;
use App\Support\Push\PushNotificationType;
use Carbon\Carbon;

/**
 * Intelligent fleet alerting — connectivity, signal quality, and security events.
 */
class SmartFleetAlertService
{
    public function __construct(
        private EventWriterInterface $events,
        private PositionReaderInterface $positions,
        private MobileMapStatusResolver $mapStatus,
        private PushNotificationDispatcher $push,
    ) {}

    public function onPositionReceived(Device $device, DeviceLocation $location): void
    {
        if ($device->status !== 'active') {
            return;
        }

        $this->clearConnectivityEpisode($device->id);

        $speed = (float) ($location->speed ?? 0);
        $lat = (float) $location->lat;
        $lng = (float) $location->lng;
        $at = $location->recorded_at ?? now();

        $this->processSignalQuality($device, $location, $speed, $lat, $lng, $at);
    }

    public function checkConnectivity(Device $device): void
    {
        if ($device->status !== 'active') {
            return;
        }

        $latest = $this->positions->latestForDevice($device);
        $map = $this->mapStatus->resolve($latest, $device);
        $tier = $map['connectivity_tier'];
        $name = $device->notificationDisplayName();

        if ($tier === 'live') {
            $this->clearConnectivityEpisode($device->id);

            return;
        }

        $lat = (float) ($latest->lat ?? 0);
        $lng = (float) ($latest->lng ?? 0);
        $at = $latest?->recorded_at ?? now();
        $lastKnownKey = (string) ($map['last_known_status_key'] ?? '');
        $lastKnownSpeed = (float) ($map['last_known_speed'] ?? 0);
        $lastKnownIgnition = (bool) ($map['last_known_ignition'] ?? false);
        $minutesSince = $latest?->recorded_at
            ? (int) $latest->recorded_at->diffInMinutes(now())
            : null;

        if ($tier === 'delayed' || $tier === 'stale') {
            $this->emitOnce(
                "device.{$device->id}.alert.delayed",
                now()->addHours(6),
                function () use ($device, $name, $minutesSince, $lat, $lng, $at) {
                    $mins = $minutesSince ?? (int) ceil(MobileMapStatusResolver::DELAYED_MIN_SECONDS / 60);
                    $event = $this->record(
                        $device,
                        VehicleEvent::TYPE_DELAYED,
                        (string) __('app.alerts.delayed_title'),
                        (string) __('app.alerts.delayed_message', ['device' => $name, 'minutes' => $mins]),
                        null,
                        $lat,
                        $lng,
                        $at,
                    );
                    $this->push->forSmartAlert($device, $event, PushNotificationType::DELAYED_DATA);
                }
            );

            if (in_array($lastKnownKey, ['running', 'moving'], true)
                || $lastKnownSpeed > MobileMapStatusResolver::MOVING_SPEED_KMH) {
                $this->emitOnce(
                    "device.{$device->id}.alert.tampering",
                    now()->addHours(6),
                    function () use ($device, $name, $lastKnownSpeed, $lat, $lng, $at) {
                        $event = $this->record(
                            $device,
                            VehicleEvent::TYPE_TAMPERING,
                            (string) __('app.alerts.tampering_title'),
                            (string) __('app.alerts.tampering_message', [
                                'device' => $name,
                                'speed' => number_format($lastKnownSpeed, 0),
                            ]),
                            $lastKnownSpeed,
                            $lat,
                            $lng,
                            $at,
                        );
                        $this->push->forSmartAlert($device, $event, PushNotificationType::TAMPERING_SUSPECTED);
                    }
                );
            }

            return;
        }

        if ($tier !== 'offline') {
            return;
        }

        $commLostMoving = in_array($lastKnownKey, ['running', 'moving'], true)
            || $lastKnownSpeed > MobileMapStatusResolver::MOVING_SPEED_KMH;

        if ($commLostMoving) {
            $this->emitOnce(
                "device.{$device->id}.alert.comm_moving",
                now()->addHours(12),
                function () use ($device, $name, $lastKnownSpeed, $minutesSince, $lat, $lng, $at) {
                    $event = $this->record(
                        $device,
                        VehicleEvent::TYPE_COMM_LOST_MOVING,
                        (string) __('app.alerts.comm_lost_moving_title'),
                        (string) __('app.alerts.comm_lost_moving_message', [
                            'device' => $name,
                            'speed' => number_format($lastKnownSpeed, 0),
                            'minutes' => $minutesSince ?? (int) ceil(MobileMapStatusResolver::OFFLINE_SECONDS / 60),
                        ]),
                        $lastKnownSpeed,
                        $lat,
                        $lng,
                        $at,
                    );
                    $this->push->forSmartAlert($device, $event, PushNotificationType::COMM_LOST_MOVING);

                    return;
                }
            );

            cache()->put("device.{$device->id}.push_connectivity", false, now()->addHours(24));

            return;
        }

        if ($lastKnownIgnition) {
            $this->emitOnce(
                "device.{$device->id}.alert.comm_ignition",
                now()->addHours(12),
                function () use ($device, $name, $minutesSince, $lat, $lng, $at) {
                    $event = $this->record(
                        $device,
                        VehicleEvent::TYPE_COMM_LOST_IGNITION,
                        (string) __('app.alerts.comm_lost_ignition_title'),
                        (string) __('app.alerts.comm_lost_ignition_message', [
                            'device' => $name,
                            'minutes' => $minutesSince ?? (int) ceil(MobileMapStatusResolver::OFFLINE_SECONDS / 60),
                        ]),
                        null,
                        $lat,
                        $lng,
                        $at,
                    );
                    $this->push->forSmartAlert($device, $event, PushNotificationType::COMM_LOST_IGNITION);
                }
            );
        }

        $this->emitOnce(
            "device.{$device->id}.alert.offline",
            now()->addHours(12),
            function () use ($device, $name, $minutesSince, $lat, $lng, $at) {
                $event = $this->record(
                    $device,
                    VehicleEvent::TYPE_OFFLINE,
                    (string) __('app.alerts.offline_title'),
                    (string) __('app.alerts.offline_message', [
                        'device' => $name,
                        'minutes' => $minutesSince ?? (int) ceil(MobileMapStatusResolver::OFFLINE_SECONDS / 60),
                    ]),
                    null,
                    $lat,
                    $lng,
                    $at,
                );
                $this->push->forSmartAlert($device, $event, PushNotificationType::DEVICE_OFFLINE);
            }
        );

        cache()->put("device.{$device->id}.push_connectivity", false, now()->addHours(24));
    }

    public function notifySecurityEvent(Device $device, VehicleEvent $event): void
    {
        $pushType = match ($event->type) {
            VehicleEvent::TYPE_PANIC => PushNotificationType::PANIC,
            VehicleEvent::TYPE_POWER_CUT => PushNotificationType::POWER_CUT,
            VehicleEvent::TYPE_LOW_BATTERY => PushNotificationType::LOW_BATTERY,
            VehicleEvent::TYPE_IGNITION => PushNotificationType::IGNITION_OFF_MOVING,
            VehicleEvent::TYPE_GSM_WEAK => PushNotificationType::GSM_WEAK,
            VehicleEvent::TYPE_GPS_WEAK => PushNotificationType::GPS_WEAK,
            default => null,
        };

        if ($pushType !== null) {
            $this->push->forSmartAlert($device, $event, $pushType);
        }
    }

    private function processSignalQuality(
        Device $device,
        DeviceLocation $location,
        float $speed,
        float $lat,
        float $lng,
        Carbon $at,
    ): void {
        $cooldown = (int) config('tracking.event_cooldown_seconds', 300);
        $name = $device->notificationDisplayName();
        $gsmThreshold = (int) config('tracking.gsm_weak_percent', 25);
        $gpsThreshold = (int) config('tracking.gps_weak_percent', 30);
        $minSatellites = (int) config('tracking.gps_min_satellites', 4);

        $gsm = $location->gsm_signal;
        if ($gsm !== null && (int) $gsm > 0 && (int) $gsm < $gsmThreshold) {
            $this->emitOnce(
                "device.{$device->id}.event.gsm_weak",
                now()->addSeconds($cooldown * 2),
                function () use ($device, $name, $gsm, $speed, $lat, $lng, $at) {
                    $event = $this->record(
                        $device,
                        VehicleEvent::TYPE_GSM_WEAK,
                        (string) __('app.alerts.gsm_weak_title'),
                        (string) __('app.alerts.gsm_weak_message', ['device' => $name, 'signal' => $gsm]),
                        $speed,
                        $lat,
                        $lng,
                        $at,
                        null,
                        ['gsm_signal' => $gsm],
                    );
                    $this->notifySecurityEvent($device, $event);
                }
            );
        }

        $gps = $location->gps_signal;
        $satellites = $location->satellites;
        $gpsWeak = ($gps !== null && (int) $gps > 0 && (int) $gps < $gpsThreshold)
            || ($satellites !== null && (int) $satellites >= 0 && (int) $satellites < $minSatellites);

        if ($gpsWeak) {
            $this->emitOnce(
                "device.{$device->id}.event.gps_weak",
                now()->addSeconds($cooldown * 2),
                function () use ($device, $name, $gps, $satellites, $speed, $lat, $lng, $at) {
                    $detail = $satellites !== null
                        ? (string) __('app.alerts.gps_weak_satellites', ['count' => $satellites])
                        : (string) __('app.alerts.gps_weak_signal', ['signal' => $gps ?? 0]);
                    $event = $this->record(
                        $device,
                        VehicleEvent::TYPE_GPS_WEAK,
                        (string) __('app.alerts.gps_weak_title'),
                        (string) __('app.alerts.gps_weak_message', ['device' => $name, 'detail' => $detail]),
                        $speed,
                        $lat,
                        $lng,
                        $at,
                        null,
                        ['gps_signal' => $gps, 'satellites' => $satellites],
                    );
                    $this->notifySecurityEvent($device, $event);
                }
            );
        }
    }

    private function clearConnectivityEpisode(int $deviceId): void
    {
        foreach (['delayed', 'offline', 'tampering', 'comm_moving', 'comm_ignition'] as $suffix) {
            cache()->forget("device.{$deviceId}.alert.{$suffix}");
        }
    }

    private function emitOnce(string $cacheKey, Carbon $ttl, callable $callback): void
    {
        if (cache()->has($cacheKey)) {
            return;
        }

        $callback();
        cache()->put($cacheKey, true, $ttl);
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
        array $meta = [],
    ): VehicleEvent {
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
}
