<?php

namespace App\Repositories\Tracking;

use App\Contracts\Tracking\EventReaderInterface;
use App\Models\Device;
use App\Models\VehicleEvent;
use App\Support\Traccar\TraccarMode;
use App\Support\Traccar\TraccarSchema;
use Carbon\Carbon;
use Illuminate\Support\Collection;

class DelegatingEventReader implements EventReaderInterface
{
    public function __construct(
        private LegacyEventReader $legacy,
        private TraccarEventReader $traccar,
    ) {}

    public function latestForDevice(Device $device, int $limit = 5): Collection
    {
        if ($this->shouldReadTraccar()) {
            $events = $this->traccar->latestForDevice($device, $limit);

            if ($events->isNotEmpty() || TraccarMode::isSingleSource()) {
                return $events;
            }
        }

        return $this->legacy->latestForDevice($device, $limit);
    }

    public function afterIdForDevice(Device $device, int $afterId, int $limit = 20): Collection
    {
        if ($this->shouldReadTraccar()) {
            $events = $this->traccar->afterIdForDevice($device, $afterId, $limit);

            if ($events->isNotEmpty() || TraccarMode::isSingleSource()) {
                return $events;
            }
        }

        return $this->legacy->afterIdForDevice($device, $afterId, $limit);
    }

    public function forDevice(
        Device $device,
        ?Carbon $from = null,
        ?Carbon $to = null,
        ?array $types = null,
        int $limit = 50
    ): Collection {
        if ($this->shouldReadTraccar()) {
            $events = $this->traccar->forDevice($device, $from, $to, $types, $limit);

            if ($events->isNotEmpty() || TraccarMode::isSingleSource()) {
                return $events;
            }
        }

        return $this->legacy->forDevice($device, $from, $to, $types, $limit);
    }

    public function countForDevices(Collection $deviceIds, ?Carbon $from = null, ?array $types = null): int
    {
        if ($this->shouldReadTraccar()) {
            $count = $this->traccar->countForDevices($deviceIds, $from, $types);

            if ($count > 0 || TraccarMode::isSingleSource()) {
                return $count;
            }
        }

        return $this->legacy->countForDevices($deviceIds, $from, $types);
    }

    public function countSince(Carbon $from): int
    {
        if ($this->shouldReadTraccar()) {
            $count = $this->traccar->countSince($from);

            if ($count > 0 || TraccarMode::isSingleSource()) {
                return $count;
            }
        }

        return $this->legacy->countSince($from);
    }

    public function recentForDevices(Collection $deviceIds, int $limit = 12): Collection
    {
        if ($this->shouldReadTraccar()) {
            $events = $this->traccar->recentForDevices($deviceIds, $limit);

            if ($events->isNotEmpty() || TraccarMode::isSingleSource()) {
                return $events;
            }
        }

        return $this->legacy->recentForDevices($deviceIds, $limit);
    }

    private function shouldReadTraccar(): bool
    {
        return TraccarMode::readsTraccar() && TraccarSchema::isReady();
    }
}
