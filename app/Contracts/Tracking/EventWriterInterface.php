<?php

namespace App\Contracts\Tracking;

use App\Models\Device;
use App\Models\VehicleEvent;
use Carbon\Carbon;

interface EventWriterInterface
{
    public function record(
        Device $device,
        string $type,
        string $title,
        string $message,
        ?float $speed,
        float $lat,
        float $lng,
        Carbon $at,
        ?int $geofenceId = null,
        array $meta = [],
        ?int $traccarPositionId = null
    ): VehicleEvent;
}
