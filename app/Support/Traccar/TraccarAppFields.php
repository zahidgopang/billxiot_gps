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

    public const KEY_STATUS = 'laravel_status';

    public const KEY_PREFERENCES = 'laravel_preferences';

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

    public const KEY_PLATE_TYPE = 'plate_type';

    public const KEY_SIM_TYPE = 'sim_type';

    public const KEY_SIM_NUMBER = 'sim_number';

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
