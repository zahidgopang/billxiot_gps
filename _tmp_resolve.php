<?php

use App\Services\Mobile\MobileMapStatusResolver;
use App\Services\SmartFleetAlertService;
use App\Services\VehicleEventService;
use App\Services\Tracking\GlobalTrackingService;

foreach ([
    MobileMapStatusResolver::class,
    SmartFleetAlertService::class,
    VehicleEventService::class,
    GlobalTrackingService::class,
] as $cls) {
    app($cls);
    echo $cls . ': OK' . PHP_EOL;
}

echo 'all resolved' . PHP_EOL;
