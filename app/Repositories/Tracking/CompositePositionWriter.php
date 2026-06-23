<?php

namespace App\Repositories\Tracking;

use App\Contracts\Tracking\PositionWriterInterface;
use App\Data\Tracking\IncomingPosition;
use App\Models\DeviceLocation;
use App\Support\Traccar\TraccarMode;
use App\Support\Traccar\TraccarSchema;

class CompositePositionWriter implements PositionWriterInterface
{
    public function __construct(
        private LegacyPositionWriter $legacy,
        private TraccarPositionWriter $traccar,
    ) {}

    public function store(IncomingPosition $position): DeviceLocation
    {
        $mode = TraccarMode::current();

        if ($mode === TraccarMode::FULL && TraccarSchema::isReady()) {
            return $this->traccar->store($position);
        }

        $legacyLocation = null;

        if (TraccarMode::writesLegacy()) {
            $legacyLocation = $this->legacy->store($position);
        }

        if (TraccarMode::writesTraccar() && TraccarSchema::isReady()) {
            try {
                $traccarLocation = $this->traccar->store($position);

                if ($legacyLocation) {
                    $legacyLocation->setAttribute('traccar_position_id', $traccarLocation->id);

                    return $legacyLocation;
                }

                return $traccarLocation;
            } catch (\Throwable $e) {
                report($e);

                if ($legacyLocation) {
                    return $legacyLocation;
                }

                throw $e;
            }
        }

        if ($legacyLocation) {
            return $legacyLocation;
        }

        return $this->legacy->store($position);
    }
}
