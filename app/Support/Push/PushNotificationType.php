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
        ];
    }

    public static function title(string $type): string
    {
        return match ($type) {
            self::VEHICLE_STARTED => 'Vehicle started',
            self::VEHICLE_STOPPED => 'Vehicle stopped',
            self::VEHICLE_MOVING => 'Vehicle moving',
            self::VEHICLE_PARKED => 'Vehicle parked',
            self::GEOFENCE_ENTER => 'Geofence enter',
            self::GEOFENCE_EXIT => 'Geofence exit',
            self::OVERSPEED => 'Overspeed',
            self::DEVICE_OFFLINE => 'Device offline',
            self::DEVICE_ONLINE => 'Device online',
            self::DELAYED_DATA => 'Delayed data',
            self::COMM_LOST_MOVING => 'Communication lost while moving',
            self::COMM_LOST_IGNITION => 'Communication lost (ignition ON)',
            self::TAMPERING_SUSPECTED => 'Tampering suspected',
            self::POWER_CUT => 'Power cut',
            self::PANIC => 'SOS / Panic',
            self::LOW_BATTERY => 'Low battery',
            self::IGNITION_OFF_MOVING => 'Ignition off while moving',
            self::GSM_WEAK => 'GSM signal weak',
            self::GPS_WEAK => 'GPS signal weak',
            default => 'Fleet alert',
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
            self::GEOFENCE_EXIT => 'warning',
            default => 'info',
        };
    }
}
