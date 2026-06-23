<?php

namespace App\Repositories\Tracking;

use App\Contracts\Tracking\EventWriterInterface;
use App\Models\Device;
use App\Models\TraccarEntityMap;
use App\Models\VehicleEvent;
use App\Services\Traccar\TraccarIdMap;
use App\Support\Traccar\TraccarAttributes;
use App\Support\Traccar\TraccarSchema;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class TraccarEventWriter implements EventWriterInterface
{
    public function __construct(
        private TraccarIdMap $idMap,
        private TraccarEventMapper $mapper,
    ) {}

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
        $traccarDeviceId = $this->idMap->get(TraccarEntityMap::TYPE_DEVICE, $device->id);

        if (! $traccarDeviceId || ! TraccarSchema::hasEvents()) {
            throw new \RuntimeException('Traccar device mapping missing; cannot write tc_events.');
        }

        $traccarGeofenceId = $geofenceId
            ? $this->idMap->get(TraccarEntityMap::TYPE_GEOFENCE, $geofenceId)
            : null;

        $eventsTable = config('traccar.tables.events', 'tc_events');

        $payload = TraccarSchema::filterColumns($eventsTable, [
            'type' => $this->mapper->mapType($type),
            'eventtime' => $at,
            'deviceid' => $traccarDeviceId,
            'positionid' => $traccarPositionId,
            'geofenceid' => $traccarGeofenceId,
            'attributes' => TraccarAttributes::encode([
                'laravel_type' => $type,
                'laravel_geofence_id' => $geofenceId,
                'title' => $title,
                'message' => $message,
                'speed' => $speed,
                'latitude' => $lat,
                'longitude' => $lng,
                'meta' => $meta,
            ]),
        ]);

        $id = (int) DB::table($eventsTable)->insertGetId($payload);

        $row = (object) array_merge($payload, ['id' => $id]);

        return $this->mapper->toVehicleEvent($row, $device->id);
    }
}
