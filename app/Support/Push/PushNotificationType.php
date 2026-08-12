<?php

namespace App\Support\Push;

use App\Models\VehicleEvent;

/**
 * FCM data.type values consumed by the Flutter app.
 */
final class PushNotificationType
{
    public const VEHICLE_STARTED = 'vehicle_started';

    public const VEHICLE_STOPPED = 'vehicle_stopped';

    public const VEHICLE_MOVING = 'vehicle_moving';

    public const VEHICLE_PARKED = 'vehicle_parked';

    public const GEOFENCE_ENTER = 'geofence_enter';

    public const GEOFENCE_EXIT = 'geofence_exit';

    public const OVERSPEED = 'overspeed';

    public const DEVICE_OFFLINE = 'device_offline';

    public const DEVICE_ONLINE = 'device_online';

    public const DELAYED_DATA = 'delayed_data';

    public const COMM_LOST_MOVING = 'comm_lost_moving';

    public const COMM_LOST_IGNITION = 'comm_lost_ignition';

    public const TAMPERING_SUSPECTED = 'tampering_suspected';

    public const POWER_CUT = 'power_cut';

    public const PANIC = 'panic';

    public const LOW_BATTERY = 'low_battery';

    public const IGNITION_OFF_MOVING = 'ignition_off_moving';

    public const GSM_WEAK = 'gsm_weak';

    public const GPS_WEAK = 'gps_weak';

    public const MAINTENANCE_DUE = 'maintenance_due';

    public const TRIP_COMPLETED = 'trip_completed';

    /** Push types allowed when TRACKING_PUSH_MAJOR_STATUS_ONLY=true (running/stopped/parked/idle). */
    public static function majorMotionPushTypes(): array
    {
        return [
            self::VEHICLE_STARTED,
            self::VEHICLE_STOPPED,
            self::VEHICLE_MOVING,
            self::VEHICLE_PARKED,
        ];
    }

    /** Security alerts that bypass the major-status-only gate. */
    public static function criticalBypassTypes(): array
    {
        return [
            self::PANIC,
            self::POWER_CUT,
        ];
    }

    /** Geofence enter/exit always deliver even when major-status-only is on. */
    public static function geofencePushTypes(): array
    {
        return [
            self::GEOFENCE_ENTER,
            self::GEOFENCE_EXIT,
        ];
    }

    public static function deliverViaPush(string $type): bool
    {
        if (! config('tracking.push_major_status_only', true)) {
            return true;
        }

        if (in_array($type, self::criticalBypassTypes(), true)) {
            return true;
        }

        if (in_array($type, self::geofencePushTypes(), true)) {
            return true;
        }

        return in_array($type, self::majorMotionPushTypes(), true);
    }

    /** @return array<int, string> */
    public static function all(): array
    {
        return [
            self::VEHICLE_STARTED,
            self::VEHICLE_STOPPED,
            self::VEHICLE_MOVING,
            self::VEHICLE_PARKED,
            self::GEOFENCE_ENTER,
            self::GEOFENCE_EXIT,
            self::OVERSPEED,
            self::DEVICE_OFFLINE,
            self::DEVICE_ONLINE,
            self::DELAYED_DATA,
            self::COMM_LOST_MOVING,
            self::COMM_LOST_IGNITION,
            self::TAMPERING_SUSPECTED,
            self::POWER_CUT,
            self::PANIC,
            self::LOW_BATTERY,
            self::IGNITION_OFF_MOVING,
            self::GSM_WEAK,
            self::GPS_WEAK,
            self::MAINTENANCE_DUE,
            self::TRIP_COMPLETED,
        ];
    }

    public static function title(string $type): string
    {
        return match ($type) {
            self::VEHICLE_STARTED => 'Engine On',
            self::VEHICLE_STOPPED => 'Engine Off',
            self::VEHICLE_MOVING => 'Vehicle Moving',
            self::VEHICLE_PARKED => 'Vehicle Parked',
            self::GEOFENCE_ENTER => 'Geofence Entry',
            self::GEOFENCE_EXIT => 'Geofence Exit',
            self::OVERSPEED => 'Overspeed',
            self::DEVICE_OFFLINE => 'Device Disconnected',
            self::DEVICE_ONLINE => 'Device Connected',
            self::DELAYED_DATA => 'Delayed Data',
            self::COMM_LOST_MOVING => 'Communication Lost While Moving',
            self::COMM_LOST_IGNITION => 'Communication Lost (Ignition ON)',
            self::TAMPERING_SUSPECTED => 'Tampering Suspected',
            self::POWER_CUT => 'Power Cut',
            self::PANIC => 'SOS / Panic',
            self::LOW_BATTERY => 'Low Battery',
            self::IGNITION_OFF_MOVING => 'Ignition Off While Moving',
            self::GSM_WEAK => 'Weak GSM Signal',
            self::GPS_WEAK => 'Weak GPS Signal',
            self::MAINTENANCE_DUE => 'Maintenance Due',
            self::TRIP_COMPLETED => 'Trip Completed',
            default => 'Fleet Alert',
        };
    }

    public static function severity(string $type): string
    {
        return match ($type) {
            self::PANIC,
            self::POWER_CUT,
            self::COMM_LOST_MOVING,
            self::TAMPERING_SUSPECTED,
            self::OVERSPEED => 'critical',
            self::DELAYED_DATA,
            self::COMM_LOST_IGNITION,
            self::GSM_WEAK,
            self::GPS_WEAK,
            self::LOW_BATTERY,
            self::IGNITION_OFF_MOVING,
            self::DEVICE_OFFLINE,
            self::MAINTENANCE_DUE,
            self::GEOFENCE_EXIT => 'warning',
            default => 'info',
        };
    }
}
