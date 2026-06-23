<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Migration mode
    |--------------------------------------------------------------------------
    | off          – Laravel tables only (legacy)
    | dual_write   – Write device_locations + tc_positions; read Laravel (deprecated)
    | read_traccar – Write both; read tc_* when available (transitional)
    | full         – Single source: tc_positions, tc_events, tc_geofences (recommended)
    */
    'mode' => env('TRACCAR_MODE', 'full'),

    /*
    | When false in full mode, Laravel device/geofence/user observers do not mirror
    | into tc_* (use direct tc_* services / Traccar protocol instead).
    */
    'sync_on_change' => env('TRACCAR_SYNC_ON_CHANGE', null),

    'sync_devices_on_change' => env('TRACCAR_SYNC_DEVICES_ON_CHANGE', true),

    'sync_users_on_change' => env('TRACCAR_SYNC_USERS_ON_CHANGE', true),

    /*
    | Write duplicate rows to device_locations / vehicle_events (off in full mode).
    */
    'write_legacy_tables' => env('TRACCAR_WRITE_LEGACY_TABLES', null),

    'enabled' => env('TRACCAR_ENABLED', false),

    /** After unify migration: Laravel model ids are native tc_* ids (no traccar_entity_map). */
    'unified_ids' => env('TRACCAR_UNIFIED_IDS', true),

    'tables' => [
        'devices' => 'tc_devices',
        'positions' => 'tc_positions',
        'events' => 'tc_events',
        'geofences' => 'tc_geofences',
        'users' => 'tc_users',
        'user_device' => 'tc_user_device',
        'user_geofence' => 'tc_user_geofence',
        'device_geofence' => 'tc_device_geofence',
        'groups' => 'tc_groups',
        'drivers' => 'tc_drivers',
        'commands' => 'tc_commands',
    ],

    'protocol' => env('TRACCAR_PROTOCOL', 'laravel'),

    /*
    | Poll tc_positions and broadcast DeviceLocationUpdated for map realtime (Pusher/Echo).
    | Requires `php artisan schedule:work` or cron + schedule:run.
    */
    'broadcast_positions' => env('TRACCAR_BROADCAST_POSITIONS', true),

    'broadcast_positions_limit' => (int) env('TRACCAR_BROADCAST_POSITIONS_LIMIT', 200),

    'sync_users' => env('TRACCAR_SYNC_USERS', true),

    /*
    | Traccar web UI password (PBKDF2 hex + salt). Independent from Laravel bcrypt.
    | Applied on new tc_users rows and when stored hash is missing/invalid (e.g. old bcrypt).
    */
    'sync_user_password' => env('TRACCAR_SYNC_USER_PASSWORD', true),

    'default_user_password' => env('TRACCAR_DEFAULT_USER_PASSWORD', '12345678'),

    /*
    | When true, every user sync resets Traccar password to default_user_password.
    */
    'reset_user_password_on_sync' => env('TRACCAR_RESET_USER_PASSWORD_ON_SYNC', false),

    'sync_geofences' => env('TRACCAR_SYNC_GEOFENCES', true),

    /*
    | When a Laravel geofence is deleted, also remove the tc_geofences row after
    | junction cleanup. Junction tables are always cleared first (scoped by device/user).
    */
    'delete_geofence_row_on_remove' => env('TRACCAR_DELETE_GEOFENCE_ROW_ON_REMOVE', true),

    /*
    | Geofence observer sync: when false, only reads traccar_entity_map and writes
    | tc_geofences + junction tables (never syncDevice/syncUser). Artisan backfill
    | passes ensure=true so missing rows are created first.
    */
    'ensure_entities_on_geofence_sync' => env('TRACCAR_ENSURE_ENTITIES_ON_GEOFENCE_SYNC', false),

    /*
    | After geofence junction changes, re-upsert tc_user_device for the owner so
    | device access is never lost (Traccar UI visibility).
    */
    'preserve_device_access_on_geofence_change' => env('TRACCAR_PRESERVE_DEVICE_ACCESS_ON_GEOFENCE_CHANGE', true),

    'sync_events' => env('TRACCAR_SYNC_EVENTS', true),

    /*
    | Log each user sync (password prefix only) — disable after debugging.
    */
    'sync_log' => env('TRACCAR_SYNC_LOG', false),

    /*
    | When to set tc_devices.disabled=1 (Traccar rejects protocol connections).
    | blocked — only Laravel "blocked" devices (recommended)
    | inactive — Laravel "inactive" or "blocked"
    | never — never disable in Traccar (Laravel still gates map/API)
    */
    'device_disable_when' => env('TRACCAR_DEVICE_DISABLE_WHEN', 'blocked'),

    /*
    | Allow POST /api/device/data for Laravel "inactive" devices (map still blocked).
    */
    'allow_inactive_ingest' => env('TRACCAR_ALLOW_INACTIVE_INGEST', true),

    /*
    | Laravel tables deprecated when TRACCAR_MODE=full (see docs/deprecated-tracking-tables.md).
    */
    'deprecated_tables' => [
        'device_locations' => 'device_locations',
        'vehicle_events' => 'vehicle_events',
        'geofence_events' => 'geofence_events',
    ],

];
