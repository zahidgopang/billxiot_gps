<?php

namespace App\Contracts\Tracking;

use App\Models\Device;
use App\Models\VehicleEvent;
use Carbon\Carbon;
use Illuminate\Support\Collection;

interface EventReaderInterface
{
    public function latestForDevice(Device $device, int $limit = 5): Collection;

    /**
     * Events with id greater than $afterId (chronological), for map bell polling.
     *
     * @return Collection<int, VehicleEvent>
     */
    public function afterIdForDevice(Device $device, int $afterId, int $limit = 20): Collection;

    /**
     * @return Collection<int, VehicleEvent>
     */
    public function forDevice(
        Device $device,
        ?Carbon $from = null,
        ?Carbon $to = null,
        ?array $types = null,
        int $limit = 50
    ): Collection;

    public function countForDevices(Collection $deviceIds, ?Carbon $from = null, ?array $types = null): int;

    public function countSince(Carbon $from): int;

    /**
     * @return Collection<int, VehicleEvent>
     */
    public function recentForDevices(Collection $deviceIds, int $limit = 12): Collection;
}
