<?php

namespace App\Services\Tracking;

use App\Contracts\Tracking\PositionReaderInterface;
use App\Models\Device;
use Illuminate\Support\Collection;

class DevicePositionLoader
{
    public function __construct(
        private PositionReaderInterface $positions,
    ) {}

    public function attachLatest(Device $device): Device
    {
        $device->setRelation('latestLocation', $this->positions->latestForDevice($device));

        return $device;
    }

    /**
     * @param  Collection<int, Device>|array<int, Device>  $devices
     */
    public function attachLatestToMany(Collection|array $devices): Collection
    {
        $collection = $devices instanceof Collection ? $devices : collect($devices);

        if ($collection->isEmpty()) {
            return $collection;
        }

        $ids = $collection->pluck('id')->map(fn ($id) => (int) $id)->all();
        $latestMap = $this->positions->latestForDevices($ids);

        $collection->each(function (Device $device) use ($latestMap) {
            $device->setRelation('latestLocation', $latestMap[(int) $device->id] ?? null);
        });

        return $collection;
    }
}
