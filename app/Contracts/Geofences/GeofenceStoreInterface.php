<?php

namespace App\Contracts\Geofences;

use App\Models\Device;
use Illuminate\Support\Collection;

interface GeofenceStoreInterface
{
    /**
     * Geofence shapes for point-in-polygon checks and map display.
     *
     * @return Collection<int, object{
     *   id: int,
     *   name: string,
     *   type: string,
     *   center: ?array,
     *   coords: ?array,
     *   radius: ?int
     * }>
     */
    public function forDevice(Device $device): Collection;

    public function countAll(): int;
}
