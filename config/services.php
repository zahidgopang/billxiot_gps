<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Third Party Services
    |--------------------------------------------------------------------------
    |
    | This file is for storing the credentials for third party services such
    | as Mailgun, Postmark, AWS and more. This file provides the de facto
    | location for this type of information, allowing packages to have
    | a conventional file to locate the various service credentials.
    |
    */

    'postmark' => [
        'key' => env('POSTMARK_API_KEY'),
    ],

    'resend' => [
        'key' => env('RESEND_API_KEY'),
    ],

    'ses' => [
        'key' => env('AWS_ACCESS_KEY_ID'),
        'secret' => env('AWS_SECRET_ACCESS_KEY'),
        'region' => env('AWS_DEFAULT_REGION', 'us-east-1'),
    ],

    'slack' => [
        'notifications' => [
            'bot_user_oauth_token' => env('SLACK_BOT_USER_OAUTH_TOKEN'),
            'channel' => env('SLACK_BOT_USER_DEFAULT_CHANNEL'),
        ],
    ],

    'firebase' => [
        'enabled' => env('PUSH_NOTIFICATIONS_ENABLED', false),
        'event_notifications_enabled' => env('PUSH_EVENT_NOTIFICATIONS_ENABLED', false),
        'geofence_notifications_enabled' => env('PUSH_GEOFENCE_NOTIFICATIONS_ENABLED', true),
        /** Relative to project root or absolute path — see storage/app/firebase/.gitignore */
        'credentials' => env('FIREBASE_CREDENTIALS'),
        'project_id' => env('FIREBASE_PROJECT_ID'),
        /** Write every FCM attempt to push_notification_logs — off by default (file log only). */
        'log_to_database' => filter_var(env('PUSH_LOG_TO_DATABASE', false), FILTER_VALIDATE_BOOL),
    ],

    'google' => [
        'maps_key' => env('GOOGLE_MAPS_API_KEY'),
        /** Map ID for Advanced Markers — Google Cloud Console → Map Management */
        'maps_map_id' => env('GOOGLE_MAPS_MAP_ID'),
        /**
         * Server-side Geocoding key (IP restriction / unrestricted).
         * Browser keys with HTTP referer restrictions cannot call Geocoding from PHP.
         */
        'geocoding_key' => env('GOOGLE_GEOCODING_API_KEY', env('GOOGLE_MAPS_SERVER_KEY')),
    ],

];
