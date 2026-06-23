<?php

namespace App\Repositories\Tracking;

use App\Contracts\Tracking\PositionReaderInterface;
use App\Models\Device;
use App\Models\DeviceLocation;
use App\Support\Tracking\DeviceLocationTelemetryMerger;
use App\Support\Traccar\TraccarMode;
use App\Support\Traccar\TraccarSchema;
use Carbon\Carbon;
use Illuminate\Support\Collection;

class DelegatingPositionReader implements PositionReaderInterface
{
    public function __construct(
        private LegacyPositionReader $legacy,
        private TraccarPositionReader $traccar,
    ) {}

    public function latestForDevice(Device $device): ?DeviceLocation
    {
        $legacyLatest = $this->legacy->latestForDevice($device);

        if ($this->shouldReadTraccar()) {
            $latest = $this->traccar->latestForDevice($device);

            if ($latest) {
                return DeviceLocationTelemetryMerger::merge($latest, $legacyLatest);
            }

            if (TraccarMode::isSingleSource()) {
                return $legacyLatest;
            }
        }

        return $legacyLatest;
    }

    public function historyForDevice(
        Device $device,
        ?Carbon $from = null,
        ?Carbon $to = null,
        string $order = 'asc'
    ): Collection {
        if ($this->shouldReadTraccar()) {
            $history = $this->traccar->historyForDevice($device, $from, $to, $order);

            if ($history->isNotEmpty()) {
                return $history;
            }
        }

        return $this->legacy->historyForDevice($device, $from, $to, $order);
    }

    public function previousBefore(Device $device, int $excludeLocationId, ?int $excludeTraccarPositionId = null): ?DeviceLocation
    {
        if ($this->shouldReadTraccar()) {
            $previous = $this->traccar->previousBefore($device, $excludeLocationId, $excludeTraccarPositionId);

            if ($previous || TraccarMode::isSingleSource()) {
                return $previous;
            }
        }

        return $this->legacy->previousBefore($device, $excludeLocationId, $excludeTraccarPositionId);
    }

    private function shouldReadTraccar(): bool
    {
        return TraccarMode::readsTraccar() && TraccarSchema::isReady();
    }
}
