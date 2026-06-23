<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Mobile API token lifetimes
    |--------------------------------------------------------------------------
    |
    | When the mobile app logs in without "remember" (Keep me signed in),
    | tokens expire after session_hours. Persistent logins use no expiration
    | until the user logs out or the token is revoked.
    |
    */

    'session_hours' => (int) env('MOBILE_SESSION_HOURS', 12),

    'remember_days' => (int) env('MOBILE_REMEMBER_DAYS', 90),

];
