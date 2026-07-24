<?php

namespace App\Support\Traccar;

/**
 * Laravel app fields stored inside Traccar tc_* attributes JSON.
 */
final class TraccarAppFields
{
    public const KEY_ROLE = 'laravel_role';

    public const KEY_PERMISSIONS = 'laravel_permissions';

    public const KEY_CREATED_BY = 'laravel_created_by';

    public const KEY_PARENT_USER_ID = 'laravel_parent_user_id';

    public const KEY_IS_SUB_ACCOUNT = 'laravel_is_sub_account';

    public const KEY_STATUS = 'laravel_status';

    public const KEY_PREFERENCES = 'laravel_preferences';

    /** Per-user web/push toggles for alert types (not shared across users). */
    public const KEY_NOTIFICATION_PREFERENCES = 'laravel_notification_preferences';

    public const KEY_PASSWORD = 'laravel_password';

    public const KEY_EMAIL_VERIFIED = 'laravel_email_verified_at';

    public const KEY_REMEMBER = 'laravel_remember_token';

    public const KEY_PHONE = 'laravel_phone';

    public const KEY_COUNTRY = 'laravel_country_code';

    public const KEY_AVATAR = 'laravel_avatar';

    public const KEY_CREATED_AT = 'laravel_created_at';

    public const KEY_UPDATED_AT = 'laravel_updated_at';

    public const KEY_DEVICE_TYPE = 'device_type';

    public const KEY_VEHICLE_NAME = 'vehicle_name';

    public const KEY_VEHICLE_NUMBER = 'vehicle_number';

    public const KEY_VEHICLE_MODEL = 'vehicle_model';

    public const KEY_VEHICLE_TYPE = 'vehicle_type';

    public const KEY_MAP_MARKER_STYLE = 'map_marker_style';

    public const KEY_MAP_MARKER_SIZE = 'map_marker_size';

    public const KEY_MAP_ICON_SOURCE = 'map_icon_source';

    public const KEY_MAP_CUSTOM_ICON = 'map_custom_icon';

    public const KEY_MAP_BUILTIN_ICON = 'map_builtin_icon_path';

    public const KEY_MAP_ICON_ROTATION = 'map_icon_rotation_enabled';

    public const KEY_MAP_ICON_ROTATION_OFFSET = 'map_icon_rotation_offset';

    public const KEY_DRIVER_NAME = 'driver_name';

    public const KEY_DRIVER_CONTACT = 'driver_contact';

    public const KEY_PLATE_TYPE = 'plate_type';

    public const KEY_ODOMETER_BASE_KM = 'odometer_base_km';

    public const KEY_ODOMETER_BASE_SET_AT = 'odometer_base_set_at';

    public const KEY_ODOMETER_GPS_ACCUM_KM = 'odometer_gps_accum_km';

    public const KEY_ODOMETER_LAST_ACCUM_AT = 'odometer_last_accum_at';

    public const KEY_ODOMETER_LAST_ACCUM_LAT = 'odometer_last_accum_lat';

    public const KEY_ODOMETER_LAST_ACCUM_LNG = 'odometer_last_accum_lng';

    /** Bump when GPS accumulation filters change so stale inflated totals are rebuilt. */
    public const KEY_ODOMETER_ACCUM_VERSION = 'odometer_accum_version';

    /** Average diesel use in liters per 100 km (estimated consumption). */
    public const KEY_FUEL_CONSUMPTION_L_PER_100KM = 'fuel_consumption_l_per_100km';

    /** Display preference: l_per_100km | km_per_l */
    public const KEY_FUEL_EFFICIENCY_UNIT = 'fuel_efficiency_unit';

    /** Optional tank capacity in liters (helps interpret % fuel sensors). */
    public const KEY_FUEL_TANK_CAPACITY_L = 'fuel_tank_capacity_l';

    /** Fuel sensor reading unit: liters | percent */
    public const KEY_FUEL_SENSOR_UNIT = 'fuel_sensor_unit';

    public const KEY_SIM_TYPE = 'sim_type';

    public const KEY_SIM_NUMBER = 'sim_number';

    /**
     * Last observed Traccar wire protocol (e.g. teltonika, gt06).
     * Cached from tc_positions.protocol so command profile detection works
     * even when tc_devices.model / name do not mention the GPS brand.
     */
    public const KEY_TRACCAR_PROTOCOL = 'traccar_protocol';

    public const KEY_DEVICE_STATUS = 'laravel_device_status';

    public const KEY_DEVICE_DESC = 'description';

    public const KEY_GEOFENCE_TYPE = 'type';

    public const KEY_GEOFENCE_CENTER = 'center';

    public const KEY_GEOFENCE_COORDS = 'coords';

    public const KEY_GEOFENCE_RADIUS = 'radius';

    public const KEY_GEOFENCE_DEVICE_ID = 'laravel_device_id';

    public static function mergeInto(?string $attributesJson, array $patch): string
    {
        $attrs = TraccarAttributes::decode($attributesJson);
        foreach ($patch as $key => $value) {
            if ($value === null) {
                unset($attrs[$key]);
            } else {
                $attrs[$key] = $value;
            }
        }

        return TraccarAttributes::encode($attrs);
    }

    public static function get(?string $attributesJson, string $key, mixed $default = null): mixed
    {
        return TraccarAttributes::decode($attributesJson)[$key] ?? $default;
    }
}
