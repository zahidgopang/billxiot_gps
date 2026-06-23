<?php

namespace App\Repositories\Tracking;

use App\Contracts\Tracking\EventWriterInterface;
use App\Models\Device;
use App\Models\VehicleEvent;
use Carbon\Carbon;
use Illuminate\Support\Facades\Schema;

class LegacyEventWriter implements EventWriterInterface
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
    ): VehicleEvent {
        $attrs = [
            'device_id' => $device->id,
            'geofence_id' => $geofenceId,
            'type' => $type,
            'title' => $title,
            'message' => $message,
            'speed' => $speed,
            'lat' => $lat,
            'lng' => $lng,
            'meta' => $meta ?: null,
            'occurred_at' => $at,
        ];

        if (! Schema::hasTable('vehicle_events')) {
            return new VehicleEvent($attrs);
        }

        return VehicleEvent::create($attrs);
    }
}
