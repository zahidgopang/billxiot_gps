<?php

namespace App\Services\Traccar;

use App\Models\Device;
use App\Models\Geofence;
use App\Support\Traccar\GeofenceWkt;
use App\Support\Traccar\TraccarAppFields;
use App\Support\Traccar\TraccarAttributes;
use App\Support\Traccar\TraccarSchema;

/**
 * Geofence CRUD on tc_geofences + junction tables only.
 */
class TraccarGeofenceManager
{
    public function __construct(
        private TraccarGeofenceLinker $linker,
    ) {}

    /**
     * @param  array{name: string, type: string, center?: array|null, coords?: array|null, radius?: int|null}  $payload
     */
    public function create(Device $device, array $payload): Geofence
    {
        $type = (string) ($payload['type'] ?? 'polygon');
        $center = $payload['center'] ?? null;
        $coords = $payload['coords'] ?? null;
        $radius = isset($payload['radius']) ? (int) $payload['radius'] : null;

        $geofence = new Geofence([
            'name' => $payload['name'],
            'area' => GeofenceWkt::fromLaravel($type, $coords, $center, $radius),
        ]);

        if (TraccarSchema::hasColumn($geofence->getTable(), 'description')) {
            $geofence->setAttribute('description', $type);
        }

        $geofence->type = $type;
        $geofence->device_id = $device->id;

        if ($type === 'circle') {
            $geofence->center = $center;
            $geofence->radius = $radius;
        } else {
            $geofence->coords = $coords;
        }

        $geofence->save();

        $device->loadMissing('user');
        $this->linker->link($geofence, $device);

        return $geofence->fresh(['device']);
    }

    public function update(Geofence $geofence, array $payload): Geofence
    {
        if (isset($payload['name']) && $payload['name'] !== '') {
            $geofence->name = $payload['name'];
        }

        $type = $geofence->type;

        if ($type === 'circle') {
            if (array_key_exists('center', $payload)) {
                $geofence->center = $payload['center'];
            }
            if (array_key_exists('radius', $payload)) {
                $geofence->radius = $payload['radius'];
            }
        }

        if ($type === 'polygon' && array_key_exists('coords', $payload)) {
            $geofence->coords = $payload['coords'];
        }

        $coords = $payload['coords'] ?? TraccarAppFields::get(
            $geofence->getTraccarAttributesJson(),
            TraccarAppFields::KEY_GEOFENCE_COORDS
        );
        $center = $payload['center'] ?? TraccarAppFields::get(
            $geofence->getTraccarAttributesJson(),
            TraccarAppFields::KEY_GEOFENCE_CENTER
        );

        $geofence->area = GeofenceWkt::fromLaravel(
            $type,
            $coords,
            $center,
            $geofence->radius
        );

        $geofence->save();

        if ($geofence->device_id) {
            $device = Device::query()->with('user')->find($geofence->device_id);
            if ($device) {
                $this->linker->link($geofence, $device);
            }
        }

        return $geofence->fresh(['device']);
    }

    public function delete(Geofence $geofence): void
    {
        $table = config('traccar.tables.device_geofence', 'tc_device_geofence');
        $userTable = config('traccar.tables.user_geofence', 'tc_user_geofence');
        $geofenceCol = TraccarSchema::resolveColumn($table, 'geofenceid') ?? 'geofenceid';

        \Illuminate\Support\Facades\DB::table($table)->where($geofenceCol, $geofence->id)->delete();

        if (\Illuminate\Support\Facades\Schema::hasTable($userTable)) {
            $userCol = TraccarSchema::resolveColumn($userTable, 'userid') ?? 'userid';
            \Illuminate\Support\Facades\DB::table($userTable)->where($geofenceCol, $geofence->id)->delete();
        }

        $geofence->delete();
    }
}
