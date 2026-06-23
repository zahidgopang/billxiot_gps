<?php

namespace App\Support\Push;

use App\Models\VehicleEvent;

final class PushNotificationMapper
{
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

  /**
     * Map a stored vehicle event type to a push type (geofence, overspeed, etc.).
     */
    public static function fromVehicleEventType(string $eventType): ?string
    {
        return match ($eventType) {
            VehicleEvent::TYPE_GEOFENCE_ENTER => PushNotificationType::GEOFENCE_ENTER,
            VehicleEvent::TYPE_GEOFENCE_EXIT => PushNotificationType::GEOFENCE_EXIT,
            VehicleEvent::TYPE_OVERSPEED => PushNotificationType::OVERSPEED,
            default => null,
        };
    }
}
