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

    /**
     * @param  list<int>  $deviceIds
     * @return array<int, DeviceLocation>
     */
    public function latestForDevices(array $deviceIds): array
    {
        $deviceIds = array_values(array_unique(array_filter(array_map('intval', $deviceIds))));
        if ($deviceIds === []) {
            return [];
        }

        $legacyMap = $this->legacy->latestForDevices($deviceIds);

        if (! $this->shouldReadTraccar()) {
            return $legacyMap;
        }

        $traccarMap = $this->traccar->latestForDevices($deviceIds);
        $out = [];

        foreach ($deviceIds as $id) {
            $traccarLatest = $traccarMap[$id] ?? null;
            $legacyLatest = $legacyMap[$id] ?? null;

            if ($traccarLatest) {
                $out[$id] = DeviceLocationTelemetryMerger::merge($traccarLatest, $legacyLatest);
            } elseif ($legacyLatest) {
                $out[$id] = $legacyLatest;
            }
        }

        return $out;
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

    /**
     * @param  list<Device>  $devices
     * @return array<int, Collection<int, DeviceLocation>>
     */
    public function historyForDevices(
        array $devices,
        ?Carbon $from = null,
        ?Carbon $to = null,
        string $order = 'asc'
    ): array {
        if ($devices === []) {
            return [];
        }

        if ($this->shouldReadTraccar()) {
            $history = $this->traccar->historyForDevices($devices, $from, $to, $order);
            $missing = array_values(array_filter(
                $devices,
                fn (Device $device) => ($history[$device->id] ?? collect())->isEmpty()
            ));

            if ($missing === []) {
                return $history;
            }

            $legacyBatch = $this->legacy->historyForDevices($missing, $from, $to, $order);

            foreach ($legacyBatch as $id => $collection) {
                if ($collection->isNotEmpty()) {
                    $history[$id] = $collection;
                }
            }

            return $history;
        }

        return $this->legacy->historyForDevices($devices, $from, $to, $order);
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
