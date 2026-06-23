<?php

namespace App\Repositories\Tracking;

use App\Contracts\Tracking\PositionWriterInterface;
use App\Data\Tracking\IncomingPosition;
use App\Models\DeviceLocation;
use App\Services\Traccar\TraccarIdMap;
use App\Services\Traccar\TraccarSyncService;
use App\Support\Traccar\TraccarAttributes;
use App\Support\Traccar\TraccarSchema;
use App\Support\Traccar\TraccarSpeed;
use Illuminate\Support\Facades\DB;

class TraccarPositionWriter implements PositionWriterInterface
{
    public function __construct(
        private TraccarSyncService $sync,
        private TraccarIdMap $idMap,
    ) {}

    public function store(IncomingPosition $position): DeviceLocation
    {
        if (! TraccarSchema::isReady()) {
            throw new \RuntimeException('Traccar tables are not available.');
        }

        $traccarDeviceId = $this->sync->syncDevice($position->device);
        $now = now();
        $fixtime = $position->recordedAt;

        $attributes = TraccarAttributes::encode(
            TraccarAttributes::fromPositionPayload(array_merge(
                $position->toLegacyAttributes(),
                ['battery' => $position->battery]
            ))
        );

        $positionsTable = config('traccar.tables.positions', 'tc_positions');
        $devicesTable = config('traccar.tables.devices', 'tc_devices');

        $validValue = TraccarSchema::hasColumn($positionsTable, 'valid') ? true : null;

        $positionId = DB::table($positionsTable)->insertGetId(
            TraccarSchema::filterColumns($positionsTable, array_filter([
                'protocol' => config('traccar.protocol', 'laravel'),
                'deviceid' => $traccarDeviceId,
                'servertime' => $now,
                'devicetime' => $fixtime,
                'fixtime' => $fixtime,
                'valid' => $validValue,
                'latitude' => $position->lat,
                'longitude' => $position->lng,
                'altitude' => 0,
                'speed' => TraccarSpeed::kmhToKnots($position->speedKmh),
                'course' => $position->heading,
                'address' => null,
                'attributes' => $attributes,
                'accuracy' => 0,
                'network' => null,
            ], fn ($v) => $v !== null))
        );

        DB::table($devicesTable)
            ->where('id', $traccarDeviceId)
            ->update(
                TraccarSchema::filterColumns($devicesTable, [
                    'positionid' => $positionId,
                    'lastupdate' => $now,
                ])
            );

        $mapped = new DeviceLocation($position->toLegacyAttributes());
        $mapped->id = $positionId;
        $mapped->exists = true;

        return $mapped;
    }
}
