<?php

return [
    'stopped_speed_kmh' => (float) env('TRACKING_STOPPED_SPEED', 1),
    'slow_speed_max_kmh' => (float) env('TRACKING_SLOW_SPEED_MAX', 30),
    'overspeed_kmh' => (float) env('TRACKING_OVERSPEED', 80),
    'low_battery_percent' => (int) env('TRACKING_LOW_BATTERY', 20),
    'event_cooldown_seconds' => (int) env('TRACKING_EVENT_COOLDOWN', 300),

    /** Speed (km/h) above which a vehicle is considered moving/running. */
    'moving_speed_kmh' => (float) env('TRACKING_MOVING_SPEED_KMH', 1),

    /** Seconds before delayed tier (2 min). Fresh motion uses fixes newer than this. */
    'delayed_min_seconds' => (int) env('TRACKING_DELAYED_MIN_SECONDS', 120),

    /** Seconds before weak signal / stale tier (10 min). */
    'stale_min_seconds' => (int) env('TRACKING_STALE_MIN_SECONDS', 600),

    /** Seconds without GPS before offline (30 min). Offline is communication timeout only. */
    'offline_seconds' => (int) env('TRACKING_OFFLINE_SECONDS', 1800),

    /** @deprecated use delayed_min_seconds */
    'recent_seconds' => (int) env('TRACKING_RECENT_SECONDS', 120),

    /** @deprecated use recent_seconds */
    'recent_minutes' => (int) env('TRACKING_RECENT_MINUTES', 1),

    /** @deprecated use offline_seconds */
    'offline_minutes' => (int) env('TRACKING_OFFLINE_MINUTES', 2),

    /** Minutes without GPS before a device is treated as offline for push alerts. */
    'online_minutes' => (int) env('TRACKING_ONLINE_MINUTES', 30),

    /** When false, rely on Traccar tc_events (geofenceEnter/Exit) only; when true, Laravel also detects zones on position updates. */
    'laravel_geofence_detection' => filter_var(env('TRACKING_LARAVEL_GEOFENCE', true), FILTER_VALIDATE_BOOL),

    /** GSM signal below this percentage triggers a weak-signal warning. */
    'gsm_weak_percent' => (int) env('TRACKING_GSM_WEAK_PERCENT', 25),

    /** GPS signal below this percentage triggers a weak-signal warning. */
    'gps_weak_percent' => (int) env('TRACKING_GPS_WEAK_PERCENT', 30),

    /** Satellite count below this triggers a GPS weak warning. */
    'gps_min_satellites' => (int) env('TRACKING_GPS_MIN_SATELLITES', 4),

    /** Account owner: also notify linked sub-accounts when maintenance is due (default off). */
    'maintenance_notify_sub_accounts' => filter_var(env('TRACKING_MAINT_NOTIFY_SUBS', false), FILTER_VALIDATE_BOOL),

    /** Only push running / idle / parked / stopped — blocks GSM weak, online, delayed, etc.
     *  Geofence enter/exit and panic/power_cut still bypass this gate in PushNotificationType. */
    'push_major_status_only' => filter_var(env('TRACKING_PUSH_MAJOR_STATUS_ONLY', true), FILTER_VALIDATE_BOOL),

    /** When true, Laravel also writes geofence/security events to the events store (in addition to push). */
    'persist_notification_events' => filter_var(env('TRACKING_PERSIST_NOTIFICATION_EVENTS', false), FILTER_VALIDATE_BOOL),

    /** Minimum seconds between map-status push notifications per device. */
    'push_motion_cooldown_seconds' => (int) env('TRACKING_PUSH_MOTION_COOLDOWN', 300),

    /** GSM/GPS weak alerts on every position (very noisy — keep off in production). */
    'push_smart_signal_alerts' => filter_var(env('TRACKING_PUSH_SMART_SIGNAL_ALERTS', false), FILTER_VALIDATE_BOOL),

    /** Geofence enter/exit on live-map polling (causes 504s — only run on GPS ingest). */
    'geofence_on_live_poll' => filter_var(env('TRACKING_GEOFENCE_ON_LIVE_POLL', false), FILTER_VALIDATE_BOOL),

    /** Seconds to cache fleet live-json responses (reduces poll load with many devices). */
    'live_json_cache_seconds' => (int) env('TRACKING_LIVE_JSON_CACHE_SECONDS', 8),

    /** Include route_trip in live-json only when a single device is polled. */
    'live_include_route_trip_single' => filter_var(env('TRACKING_LIVE_INCLUDE_ROUTE_TRIP_SINGLE', true), FILTER_VALIDATE_BOOL),

    /** HTTP poll interval (ms) when Reverb/Echo is down — positions via live-json. */
    'live_poll_interval_ms' => (int) env('TRACKING_LIVE_POLL_INTERVAL_MS', 10000),

    /** Slow heartbeat poll (ms) when Reverb/Echo is connected — WS carries live fixes. */
    'live_poll_interval_connected_ms' => (int) env('TRACKING_LIVE_POLL_INTERVAL_CONNECTED_MS', 30000),

    /** Alert bell poll (ms) when realtime is unavailable. */
    'alert_poll_interval_ms' => (int) env('TRACKING_ALERT_POLL_INTERVAL_MS', 30000),

    /** Alert bell poll (ms) when Reverb/Echo is connected. */
    'alert_poll_interval_connected_ms' => (int) env('TRACKING_ALERT_POLL_INTERVAL_CONNECTED_MS', 60000),

    /**
     * Persist notification rows to vehicle_events / tc_events.
     * When false, status/geofence/alert pushes are sent via FCM only (no DB insert).
     */
    /** When true, snap live fixes to the nearest road via Google Roads API (same key as Maps). */
    'roads_snap_enabled' => filter_var(env('TRACKING_ROADS_SNAP_ENABLED', false), FILTER_VALIDATE_BOOL),
];
