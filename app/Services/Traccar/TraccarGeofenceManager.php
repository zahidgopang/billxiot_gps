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
        $coords = isset($payload['coords']) ? $this->limitPolygonPoints($payload['coords']) : null;
        $radius = isset($payload['radius']) ? (int) round((float) $payload['radius']) : null;
        $name = mb_substr(trim((string) ($payload['name'] ?? 'Geofence')), 0, 128);

        $geofence = new Geofence([
            'name' => $name !== '' ? $name : 'Geofence',
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
            $geofence->name = mb_substr(trim((string) $payload['name']), 0, 128);
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
            $geofence->coords = $this->limitPolygonPoints($payload['coords']);
        }

        $coords = $payload['coords'] ?? TraccarAppFields::get(
            $geofence->getTraccarAttributesJson(),
            TraccarAppFields::KEY_GEOFENCE_COORDS
        );
        if (is_array($coords)) {
            $coords = $this->limitPolygonPoints($coords);
        }
        $center = $payload['center'] ?? TraccarAppFields::get(
            $geofence->getTraccarAttributesJson(),
            TraccarAppFields::KEY_GEOFENCE_CENTER
        );

        $radius = $geofence->radius;
        if (array_key_exists('radius', $payload) && $payload['radius'] !== null) {
            $radius = (int) round((float) $payload['radius']);
            $geofence->radius = $radius;
        }

        $geofence->area = GeofenceWkt::fromLaravel(
            $type,
            $coords,
            $center,
            $radius
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

    /**
     * Keep polygons under DB-friendly point counts (also shrinks attributes JSON).
     *
     * @param  array<int, mixed>|null  $coords
     * @return array<int, mixed>|null
     */
    private function limitPolygonPoints(?array $coords, int $maxPoints = 80): ?array
    {
        if ($coords === null) {
            return null;
        }

        $count = count($coords);
        if ($count <= $maxPoints) {
            return array_values($coords);
        }

        $limited = [];
        $lastIndex = $count - 1;
        for ($i = 0; $i < $maxPoints - 1; $i++) {
            $idx = (int) round(($i / ($maxPoints - 2)) * ($lastIndex - 1));
            $limited[] = $coords[$idx];
        }
        $limited[] = $coords[$lastIndex];

        return array_values($limited);
    }
}
