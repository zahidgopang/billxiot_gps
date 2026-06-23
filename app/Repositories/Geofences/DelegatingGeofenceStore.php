<?php

namespace App\Repositories\Geofences;

use App\Contracts\Geofences\GeofenceStoreInterface;
use App\Models\Device;
use App\Support\Traccar\TraccarMode;
use App\Support\Traccar\TraccarSchema;
use Illuminate\Support\Collection;

class DelegatingGeofenceStore implements GeofenceStoreInterface
{
    public function __construct(
        private LegacyGeofenceStore $legacy,
        private TraccarGeofenceStore $traccar,
    ) {}

    public function forDevice(Device $device): Collection
    {
        if (TraccarMode::readsTraccar() && TraccarSchema::hasGeofences()) {
            $zones = $this->traccar->forDevice($device);

            if ($zones->isNotEmpty() || TraccarMode::isSingleSource()) {
                return $zones;
            }
        }

        return $this->legacy->forDevice($device);
    }

    public function countAll(): int
    {
        if (TraccarMode::readsTraccar() && TraccarSchema::hasGeofences()) {
            $count = $this->traccar->countAll();

            if ($count > 0 || TraccarMode::isSingleSource()) {
                return $count;
            }
        }

        return $this->legacy->countAll();
    }
}
