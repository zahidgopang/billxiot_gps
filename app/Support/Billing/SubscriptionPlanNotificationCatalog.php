<?php

namespace App\Support\Billing;

use App\Models\VehicleEvent;
use App\Services\Tracking\NotificationPreferenceService;
use App\Support\Push\PushNotificationType;

/**
 * Notification types selectable on subscription plans (email / WhatsApp entitlements).
 */
class SubscriptionPlanNotificationCatalog
{
    /**
     * Standard alert types (excludes route-specific trip_completed — use route IDs).
     *
     * @return list<string>
     */
    public function standardTypeKeys(): array
    {
        return array_values(array_filter(
            NotificationPreferenceService::controllableTypes(),
            fn (string $type) => $type !== VehicleEvent::TYPE_TRIP_COMPLETED,
        ));
    }

    /**
     * Default: all standard types enabled (backward compatible).
     *
     * @return list<string>
     */
    public function defaultTypeKeys(): array
    {
        return $this->standardTypeKeys();
    }

    /**
     * @return list<array{key: string, label: string}>
     */
    public function standardTypeOptions(): array
    {
        return array_map(fn (string $key) => [
            'key' => $key,
            'label' => $this->labelForKey($key),
        ], $this->standardTypeKeys());
    }

    public function labelForKey(string $key): string
    {
        $langKey = 'app.tracking.notif_type_' . $key;
        $label = __($langKey);

        if ($label !== $langKey) {
            return (string) $label;
        }

        $planKey = 'app.billing.plan_notif_' . $key;
        $planLabel = __($planKey);

        return $planLabel !== $planKey ? (string) $planLabel : ucwords(str_replace('_', ' ', $key));
    }

    /**
     * Map push / event aliases to the plan matrix key.
     */
    public function canonicalKey(string $eventType): string
    {
        return match ($eventType) {
            'offline', 'device_offline', PushNotificationType::DEVICE_OFFLINE => VehicleEvent::TYPE_OFFLINE,
            'delayed', 'delayed_data' => VehicleEvent::TYPE_DELAYED,
            'maintenance', 'maintenance_due', PushNotificationType::MAINTENANCE_DUE => VehicleEvent::TYPE_MAINTENANCE,
            PushNotificationType::VEHICLE_STARTED,
            PushNotificationType::VEHICLE_MOVING,
            'vehicle_started',
            'vehicle_moving' => VehicleEvent::TYPE_RUNNING,
            PushNotificationType::VEHICLE_STOPPED,
            'vehicle_stopped' => VehicleEvent::TYPE_STOPPED,
            PushNotificationType::VEHICLE_PARKED,
            'vehicle_parked' => VehicleEvent::TYPE_STOPPED,
            PushNotificationType::GEOFENCE_ENTER,
            'geofence_enter' => VehicleEvent::TYPE_GEOFENCE_ENTER,
            PushNotificationType::GEOFENCE_EXIT,
            'geofence_exit' => VehicleEvent::TYPE_GEOFENCE_EXIT,
            PushNotificationType::OVERSPEED,
            'overspeed' => VehicleEvent::TYPE_OVERSPEED,
            PushNotificationType::POWER_CUT,
            'power_cut' => VehicleEvent::TYPE_POWER_CUT,
            PushNotificationType::PANIC,
            'panic', 'sos' => VehicleEvent::TYPE_PANIC,
            PushNotificationType::LOW_BATTERY,
            'low_battery' => VehicleEvent::TYPE_LOW_BATTERY,
            PushNotificationType::IGNITION_OFF_MOVING,
            'ignition_off_moving' => VehicleEvent::TYPE_IGNITION,
            PushNotificationType::TRIP_COMPLETED,
            'trip_completed' => VehicleEvent::TYPE_TRIP_COMPLETED,
            default => $eventType,
        };
    }

    /**
     * @param  list<string>|null  $enabledTypes  null = legacy plan (allow all standard types)
     */
    public function allowsStandardType(?array $enabledTypes, string $eventType): bool
    {
        if ($enabledTypes === null) {
            return true;
        }

        $canonical = $this->canonicalKey($eventType);

        if ($canonical === VehicleEvent::TYPE_TRIP_COMPLETED) {
            return true;
        }

        return in_array($canonical, $enabledTypes, true);
    }

    /**
     * @param  list<int>|null  $enabledRouteIds  null = legacy (allow all configured routes)
     */
    public function allowsRouteNotification(?array $enabledRouteIds, ?int $routeId): bool
    {
        if ($enabledRouteIds === null) {
            return true;
        }

        if ($routeId === null || $routeId <= 0) {
            return false;
        }

        return in_array($routeId, $enabledRouteIds, true);
    }
}
