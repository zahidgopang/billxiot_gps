<?php

namespace App\Repositories\Geofences;

use App\Contracts\Geofences\GeofenceStoreInterface;
use App\Models\Device;
use App\Models\Geofence;
use Illuminate\Support\Collection;

class LegacyGeofenceStore implements GeofenceStoreInterface
{
    public function forDevice(Device $device): Collection
    {
        return Geofence::query()
            ->where('device_id', $device->id)
            ->get()
            ->map(fn (Geofence $g) => (object) [
                'id' => $g->id,
                'name' => $g->name,
                'type' => $g->type,
                'center' => $g->center ? json_decode($g->center, true) : null,
                'coords' => $g->coords ? json_decode($g->coords, true) : null,
                'radius' => $g->radius,
            ]);
    }

    public function countAll(): int
    {
        return Geofence::count();
    }
}
