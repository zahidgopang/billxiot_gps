<?php

return [
    'stopped_speed_kmh' => (float) env('TRACKING_STOPPED_SPEED', 5),
    'slow_speed_max_kmh' => (float) env('TRACKING_SLOW_SPEED_MAX', 30),
    'overspeed_kmh' => (float) env('TRACKING_OVERSPEED', 80),
    'low_battery_percent' => (int) env('TRACKING_LOW_BATTERY', 20),
    'event_cooldown_seconds' => (int) env('TRACKING_EVENT_COOLDOWN', 300),

    /** Speed (km/h) above which a vehicle is considered moving/running. */
    'moving_speed_kmh' => (float) env('TRACKING_MOVING_SPEED_KMH', 5),

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
];
