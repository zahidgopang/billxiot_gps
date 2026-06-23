<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Firebase / FCM (HTTP v1)
    |--------------------------------------------------------------------------
    |
    | Credentials path is configured in .env (never commit the JSON file).
    | See config/services.php → firebase and .env.example.
    |
    */

    'enabled' => filter_var(env('PUSH_NOTIFICATIONS_ENABLED', false), FILTER_VALIDATE_BOOL),

    /** Auto-send pushes on GPS / geofence events (disable until test push succeeds). */
    'event_notifications_enabled' => filter_var(env('PUSH_EVENT_NOTIFICATIONS_ENABLED', false), FILTER_VALIDATE_BOOL),

    /** Geofence enter/exit pushes (on by default when FCM is enabled). */
    'geofence_notifications_enabled' => filter_var(env('PUSH_GEOFENCE_NOTIFICATIONS_ENABLED', true), FILTER_VALIDATE_BOOL),

    'credentials' => \App\Support\Firebase\FirebaseCredentials::resolvePath(
        env('FIREBASE_CREDENTIALS')
    ),

    'project_id' => env('FIREBASE_PROJECT_ID'),

    'log_channel' => env('PUSH_LOG_CHANNEL', 'push'),

];
