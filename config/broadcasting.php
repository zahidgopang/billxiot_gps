<?php

use App\Support\ReverbHost;
use App\Support\ReverbTls;

$reverbHost = ReverbHost::normalize(env('REVERB_HOST'));
$reverbScheme = env('REVERB_SCHEME', 'https');
$reverbPort = (int) env('REVERB_PORT', 443);

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
                'host'   => $reverbHost,
                'port'   => $reverbPort,
                'scheme' => $reverbScheme,
                'useTLS' => $reverbScheme === 'https',
            ],
            'client_options' => [
                'verify' => ! ReverbTls::enabled(),
            ],
        ],

    ],
];
