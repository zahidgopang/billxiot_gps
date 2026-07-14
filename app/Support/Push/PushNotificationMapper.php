<?php

namespace App\Support\Push;

use App\Models\VehicleEvent;

final class PushNotificationMapper
{
    /**
     * Map ignition-aware map status (running / idle / parked) to a mobile push type.
     */
    public static function mapStatusPushType(?string $previousKey, string $newKey): ?string
    {
        $previousKey = $previousKey !== null && $previousKey !== ''
            ? \App\Services\Mobile\VehicleStatusSpec::normalizeKey($previousKey)
            : null;
        $newKey = \App\Services\Mobile\VehicleStatusSpec::normalizeKey($newKey);

        if (! in_array($newKey, ['running', 'idle', 'parked'], true)) {
            return null;
        }

        if ($previousKey === $newKey) {
            return null;
        }

        return match ($newKey) {
            'running' => PushNotificationType::VEHICLE_STARTED,
            'idle' => PushNotificationType::VEHICLE_STOPPED,
            'parked' => PushNotificationType::VEHICLE_PARKED,
            default => null,
        };
    }

    /**
     * Map motion-state transition to a mobile push type.
     */
    public static function motionPushType(?string $previousState, string $newState): ?string
    {
        return match ($newState) {
            VehicleEvent::TYPE_RUNNING => $previousState === VehicleEvent::TYPE_STOPPED
                ? PushNotificationType::VEHICLE_STARTED
                : PushNotificationType::VEHICLE_MOVING,
            VehicleEvent::TYPE_SLOW_SPEED => PushNotificationType::VEHICLE_MOVING,
            VehicleEvent::TYPE_STOPPED => in_array($previousState, [
                VehicleEvent::TYPE_RUNNING,
                VehicleEvent::TYPE_SLOW_SPEED,
                VehicleEvent::TYPE_OVERSPEED,
            ], true)
                ? PushNotificationType::VEHICLE_PARKED
                : PushNotificationType::VEHICLE_STOPPED,
            VehicleEvent::TYPE_OVERSPEED => PushNotificationType::OVERSPEED,
            default => null,
        };
    }

    public static function fromVehicleEventType(string $eventType): ?string
    {
        return match ($eventType) {
            VehicleEvent::TYPE_GEOFENCE_ENTER,
            'geofenceEnter' => PushNotificationType::GEOFENCE_ENTER,
            VehicleEvent::TYPE_GEOFENCE_EXIT,
            'geofenceExit' => PushNotificationType::GEOFENCE_EXIT,
            VehicleEvent::TYPE_OVERSPEED,
            'deviceOverspeed',
            'overspeed' => PushNotificationType::OVERSPEED,
            VehicleEvent::TYPE_MAINTENANCE,
            'maintenance' => PushNotificationType::MAINTENANCE_DUE,
            VehicleEvent::TYPE_RUNNING,
            'running',
            'ignitionOn',
            'deviceIgnitionOn',
            'engineOn',
            'deviceMoving' => PushNotificationType::VEHICLE_STARTED,
            VehicleEvent::TYPE_STOPPED,
            'stopped',
            'idle',
            'ignitionOff',
            'deviceIgnitionOff',
            'engineOff',
            'deviceStopped' => PushNotificationType::VEHICLE_STOPPED,
            'parked' => PushNotificationType::VEHICLE_PARKED,
            VehicleEvent::TYPE_OFFLINE,
            'deviceOffline',
            'offline',
            'deviceUnknown' => PushNotificationType::DEVICE_OFFLINE,
            'device_online',
            'deviceOnline',
            'online' => PushNotificationType::DEVICE_ONLINE,
            VehicleEvent::TYPE_LOW_BATTERY,
            'lowBattery',
            'deviceLowBattery' => PushNotificationType::LOW_BATTERY,
            VehicleEvent::TYPE_POWER_CUT,
            'powerCut',
            'devicePowerCut' => PushNotificationType::POWER_CUT,
            VehicleEvent::TYPE_PANIC,
            'alarm',
            'sos' => PushNotificationType::PANIC,
            VehicleEvent::TYPE_DELAYED => PushNotificationType::DELAYED_DATA,
            VehicleEvent::TYPE_COMM_LOST_MOVING => PushNotificationType::COMM_LOST_MOVING,
            VehicleEvent::TYPE_COMM_LOST_IGNITION => PushNotificationType::COMM_LOST_IGNITION,
            VehicleEvent::TYPE_TAMPERING => PushNotificationType::TAMPERING_SUSPECTED,
            VehicleEvent::TYPE_IGNITION => PushNotificationType::IGNITION_OFF_MOVING,
            VehicleEvent::TYPE_GSM_WEAK => PushNotificationType::GSM_WEAK,
            VehicleEvent::TYPE_GPS_WEAK => PushNotificationType::GPS_WEAK,
            VehicleEvent::TYPE_TRIP_COMPLETED => PushNotificationType::TRIP_COMPLETED,
            default => null,
        };
    }
}
