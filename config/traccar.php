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
        'commands_queue' => 'tc_commands_queue',
    ],

    'protocol' => env('TRACCAR_PROTOCOL', 'laravel'),

    /*
    | Poll tc_positions and broadcast DeviceLocationUpdated for map realtime (Pusher/Echo).
    | Requires `php artisan schedule:work` or cron + schedule:run.
    |
    | Modes (TRACCAR_BROADCAST_MODE):
    |   off    — disabled (maps use HTTP polling only; lowest CPU)
    |   forward — Traccar HTTP POST → Reverb instantly (realtime, no DB poll scheduler) ★ recommended
    |   light  — WebSocket push every ~30s via scheduler (fallback if forward not configured)
    |   full   — legacy scheduler: every row + inline alerts (high CPU)
    */
    'broadcast_positions' => env('TRACCAR_BROADCAST_POSITIONS', true),

    'broadcast_mode' => env('TRACCAR_BROADCAST_MODE', 'light'),

    /** Traccar position forward (traccar.xml forward.url → POST /api/traccar/forward). */
    'forward' => [
        'secret' => env('TRACCAR_FORWARD_SECRET'),
        /** Min seconds between Reverb broadcasts per device (burst GPS reports). */
        'broadcast_min_interval_seconds' => (int) env('TRACCAR_FORWARD_BROADCAST_MIN_INTERVAL_SECONDS', 2),
        /** Geofence/push logic debounced per device (runs after HTTP response). */
        'process_events' => filter_var(env('TRACCAR_FORWARD_PROCESS_EVENTS', true), FILTER_VALIDATE_BOOL),
        'events_debounce_seconds' => (int) env('TRACCAR_FORWARD_EVENTS_DEBOUNCE_SECONDS', 30),
    ],

    /** How often schedule runs traccar:broadcast-positions (seconds). Light default: 30. */
    'broadcast_interval_seconds' => (int) env('TRACCAR_BROADCAST_INTERVAL_SECONDS', 30),

    /** In full mode, run VehicleEventService on each new row. Ignored in light mode. */
    'broadcast_process_events' => filter_var(
        env('TRACCAR_BROADCAST_PROCESS_EVENTS', false),
        FILTER_VALIDATE_BOOL,
    ),

    /** Only push the newest tc_positions row per device per batch (large CPU saver). */
    'broadcast_latest_per_device' => filter_var(
        env('TRACCAR_BROADCAST_LATEST_PER_DEVICE', true),
        FILTER_VALIDATE_BOOL,
    ),

    'broadcast_positions_limit' => (int) env('TRACCAR_BROADCAST_POSITIONS_LIMIT', 80),

    /** Light mode: how often traccar:process-position-events runs (minutes). */
    'broadcast_events_interval_minutes' => (int) env('TRACCAR_BROADCAST_EVENTS_INTERVAL_MINUTES', 2),

    /*
    | When broadcast is off (poll-only maps), still run geofence/status logic on a slow
    | schedule — much lighter CPU than traccar:broadcast-positions.
    */
    'schedule_position_events' => filter_var(
        env('TRACCAR_SCHEDULE_POSITION_EVENTS', true),
        FILTER_VALIDATE_BOOL,
    ),

    'position_events_interval_minutes' => (int) env('TRACCAR_POSITION_EVENTS_INTERVAL_MINUTES', 5),

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
    | Auto-create Device rows when unknown IMEIs POST to /api/device/data.
    | Disabled by default in production — register devices in admin first.
    */
    'ingest_auto_create_devices' => env(
        'TRACKING_INGEST_AUTO_CREATE_DEVICES',
        env('APP_ENV', 'production') !== 'production'
    ),

    /*
    | Laravel tables deprecated when TRACCAR_MODE=full (see docs/deprecated-tracking-tables.md).
    */
    'deprecated_tables' => [
        'device_locations' => 'device_locations',
        'vehicle_events' => 'vehicle_events',
        'geofence_events' => 'geofence_events',
    ],

];
