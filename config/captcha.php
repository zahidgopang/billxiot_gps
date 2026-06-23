<?php

$secret = env('NOCAPTCHA_SECRET');
$sitekey = env('NOCAPTCHA_SITEKEY');

return [
    'secret' => $secret,
    'sitekey' => $sitekey,

    'enabled' => filter_var(env('NOCAPTCHA_ENABLED', true), FILTER_VALIDATE_BOOLEAN)
        && filled($secret)
        && filled($sitekey),

    'options' => [
        'timeout' => (int) env('NOCAPTCHA_TIMEOUT', 30),
        'score_threshold' => (float) env('NOCAPTCHA_SCORE_THRESHOLD', 0.3),
    ],

    'v3' => [
        'action' => env('NOCAPTCHA_ACTION', 'register'),
        'sitekey' => $sitekey,
    ],
];
