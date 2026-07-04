<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Smart route navigation
    |--------------------------------------------------------------------------
    |
    | Tolerance and timing thresholds for Google Maps–style route matching.
    | Values are in meters unless noted.
    |
    */

    'tolerance_meters' => (int) env('ROUTE_TOLERANCE_METERS', 200),

    'slight_deviation_meters' => (int) env('ROUTE_SLIGHT_DEVIATION_METERS', 500),

    'join_route_meters' => (int) env('ROUTE_JOIN_METERS', 1500),

    'heading_tolerance_degrees' => (int) env('ROUTE_HEADING_TOLERANCE', 65),

    'off_route_confirm_minutes' => (int) env('ROUTE_OFF_ROUTE_CONFIRM_MINUTES', 3),

    'off_route_confirm_km' => (float) env('ROUTE_OFF_ROUTE_CONFIRM_KM', 2),

    'dynamic_route_cache_minutes' => (int) env('ROUTE_DYNAMIC_CACHE_MINUTES', 5),

    'navigation_recalc_km' => (float) env('ROUTE_NAVIGATION_RECALC_KM', 0.8),

    'trip_start_join_tolerance_km' => (float) env('ROUTE_TRIP_START_JOIN_KM', 2),

    'trip_start_radius_meters' => (int) env('ROUTE_TRIP_START_RADIUS_METERS', 500),

    /** When vehicle is farther than this from the assigned corridor, draw origin→vehicle via Google. */
    'join_route_corridor_km' => (float) env('ROUTE_JOIN_CORRIDOR_KM', 5),

    'checkpoint_skip_buffer_km' => (float) env('ROUTE_CHECKPOINT_SKIP_KM', 8),

];
