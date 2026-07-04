<?php

return [

    'default' => env('BROADCAST_DRIVER', 'reverb'),

    /*
    |--------------------------------------------------------------------------
    | Browser Echo (Laravel Reverb / Pusher protocol)
    |--------------------------------------------------------------------------
    |
    | Set REVERB_CLIENT_ENABLED=false when Reverb is not running locally;
    | maps still update via HTTP polling.
    |
    */
    'echo_enabled' => env('REVERB_CLIENT_ENABLED', env('BROADCAST_DRIVER') === 'reverb'),

    'connections' => [

        'reverb' => [
            'driver'  => 'reverb',
            'key'     => env('REVERB_APP_KEY'),
            'secret'  => env('REVERB_APP_SECRET'),
            'app_id'  => env('REVERB_APP_ID'),
            'options' => [
                'host'   => env('REVERB_HOST'),
                'port'   => env('REVERB_PORT', 443),
                'scheme' => env('REVERB_SCHEME', 'https'),
                'useTLS' => env('REVERB_SCHEME', 'https') === 'https',
            ],
            'client_options' => [
                // Guzzle client options for server-to-Reverb event publishing.
            ],
        ],

    ],
];
