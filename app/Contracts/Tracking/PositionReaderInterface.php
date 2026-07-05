<?php

namespace App\Contracts\Tracking;

use App\Models\Device;
use App\Models\DeviceLocation;
use Carbon\Carbon;
use Illuminate\Support\Collection;

interface PositionReaderInterface
{
    public function latestForDevice(Device $device): ?DeviceLocation;

    /**
     * Latest fix per device in one round-trip (fleet live / map poll).
     *
     * @param  list<int>  $deviceIds
     * @return array<int, DeviceLocation> keyed by Laravel device id
     */
    public function latestForDevices(array $deviceIds): array;

    /**
     * @return Collection<int, DeviceLocation>
     */
    public function historyForDevice(
        Device $device,
        ?Carbon $from = null,
        ?Carbon $to = null,
        string $order = 'asc'
    ): Collection;

    public function previousBefore(Device $device, int $excludeLocationId, ?int $excludeTraccarPositionId = null): ?DeviceLocation;
}
