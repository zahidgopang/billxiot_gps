<?php

namespace App\Repositories\Geofences;

use App\Contracts\Geofences\GeofenceStoreInterface;
use App\Models\Device;
use App\Models\TraccarEntityMap;
use App\Services\Traccar\TraccarIdMap;
use App\Support\Traccar\TraccarAppFields;
use App\Support\Traccar\TraccarAttributes;
use App\Support\Traccar\TraccarSchema;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class TraccarGeofenceStore implements GeofenceStoreInterface
{
    public function __construct(
        private TraccarIdMap $idMap,
    ) {}

    public function forDevice(Device $device): Collection
    {
        $traccarDeviceId = $this->idMap->get(TraccarEntityMap::TYPE_DEVICE, (int) $device->id)
            ?? (int) $device->id;

        if (! $traccarDeviceId || ! TraccarSchema::hasDeviceGeofence()) {
            return collect();
        }

        $junction = config('traccar.tables.device_geofence', 'tc_device_geofence');
        $geofencesTable = config('traccar.tables.geofences', 'tc_geofences');
        $geofenceCol = TraccarSchema::resolveColumn($junction, 'geofenceid') ?? 'geofenceid';
        $deviceCol = TraccarSchema::resolveColumn($junction, 'deviceid') ?? 'deviceid';

        $geofenceIds = DB::table($junction)
            ->where($deviceCol, $traccarDeviceId)
            ->pluck($geofenceCol);

        if ($geofenceIds->isEmpty()) {
            return collect();
        }

        return DB::table($geofencesTable)
            ->whereIn('id', $geofenceIds)
            ->get()
            ->map(function ($row) {
                $attrs = TraccarAttributes::decode($row->attributes ?? null);
                $type = (string) TraccarAppFields::get(
                    is_string($row->attributes ?? null) ? $row->attributes : null,
                    TraccarAppFields::KEY_GEOFENCE_TYPE,
                    $attrs['type'] ?? $row->description ?? 'polygon'
                );

                return (object) [
                    // Always expose the tc_geofences primary key — destroy/update use Geofence::find().
                    'id' => (int) $row->id,
                    'name' => (string) $row->name,
                    'type' => $type,
                    'area' => isset($row->area) ? (string) $row->area : null,
                    'center' => $attrs[TraccarAppFields::KEY_GEOFENCE_CENTER] ?? $attrs['center'] ?? null,
                    'coords' => $attrs[TraccarAppFields::KEY_GEOFENCE_COORDS] ?? $attrs['coords'] ?? null,
                    'radius' => isset($attrs[TraccarAppFields::KEY_GEOFENCE_RADIUS])
                        ? (int) $attrs[TraccarAppFields::KEY_GEOFENCE_RADIUS]
                        : (isset($attrs['radius']) ? (int) $attrs['radius'] : null),
                ];
            });
    }

    public function countAll(): int
    {
        if (! TraccarSchema::hasGeofences()) {
            return 0;
        }

        return (int) DB::table(config('traccar.tables.geofences', 'tc_geofences'))->count();
    }
}
