<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Minimum supported mobile app version
    |--------------------------------------------------------------------------
    |
    | Clients send X-App-Version and X-App-Build on every API request.
    | Builds below this threshold receive HTTP 426 with code app_update_required.
    |
    */

    'min_app_version' => env('MOBILE_MIN_APP_VERSION', '1.1.0'),

    'min_build_number' => (int) env('MOBILE_MIN_BUILD_NUMBER', 16),

    'update_required_message' => 'A new version of BillX GPS is available. Please update the app to continue.',

];
