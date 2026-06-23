<?php

namespace App\Repositories\Tracking;

use App\Contracts\Tracking\EventReaderInterface;
use App\Models\Device;
use App\Models\TraccarEntityMap;
use App\Models\VehicleEvent;
use App\Services\Traccar\TraccarIdMap;
use App\Support\Traccar\TraccarSchema;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class TraccarEventReader implements EventReaderInterface
{
    public function __construct(
        private TraccarIdMap $idMap,
        private TraccarEventMapper $mapper,
    ) {}

    public function latestForDevice(Device $device, int $limit = 5): Collection
    {
        return $this->forDevice($device, limit: $limit);
    }

    public function afterIdForDevice(Device $device, int $afterId, int $limit = 20): Collection
    {
        $traccarDeviceId = $this->idMap->get(TraccarEntityMap::TYPE_DEVICE, $device->id);

        if (! $traccarDeviceId || ! TraccarSchema::hasEvents() || $afterId < 1) {
            return collect();
        }

        $rows = DB::table(config('traccar.tables.events', 'tc_events'))
            ->where('deviceid', $traccarDeviceId)
            ->where('id', '>', $afterId)
            ->orderBy('id')
            ->limit($limit)
            ->get();

        return $rows->map(fn ($row) => $this->mapper->toVehicleEvent($row, $device->id))->values();
    }

    public function forDevice(
        Device $device,
        ?Carbon $from = null,
        ?Carbon $to = null,
        ?array $types = null,
        int $limit = 50
    ): Collection {
        $traccarDeviceId = $this->idMap->get(TraccarEntityMap::TYPE_DEVICE, $device->id);

        if (! $traccarDeviceId || ! TraccarSchema::hasEvents()) {
            return collect();
        }

        $query = DB::table(config('traccar.tables.events', 'tc_events'))
            ->where('deviceid', $traccarDeviceId);

        if ($from) {
            $query->where('eventtime', '>=', $from);
        }

        if ($to) {
            $query->where('eventtime', '<=', $to);
        }

        if ($types) {
            $traccarTypes = array_values(array_unique(array_map(
                fn (string $type) => $this->mapper->mapType($type),
                $types
            )));
            $query->whereIn('type', $traccarTypes);
        }

        $rows = $query
            ->orderByDesc('eventtime')
            ->orderByDesc('id')
            ->limit($limit)
            ->get();

        return $rows
            ->map(fn ($row) => $this->mapper->toVehicleEvent($row, $device->id))
            ->values();
    }

    public function countForDevices(Collection $deviceIds, ?Carbon $from = null, ?array $types = null): int
    {
        if ($deviceIds->isEmpty() || ! TraccarSchema::hasEvents()) {
            return 0;
        }

        $traccarDeviceIds = $deviceIds
            ->map(fn ($id) => $this->idMap->get(TraccarEntityMap::TYPE_DEVICE, (int) $id))
            ->filter()
            ->values()
            ->all();

        if ($traccarDeviceIds === []) {
            return 0;
        }

        $query = DB::table(config('traccar.tables.events', 'tc_events'))
            ->whereIn('deviceid', $traccarDeviceIds);

        if ($from) {
            $query->where('eventtime', '>=', $from);
        }

        if (! $types) {
            return $query->count();
        }

        return $query->get()->filter(function ($row) use ($types) {
            $event = $this->mapper->toVehicleEvent($row, 0);

            return in_array($event->type, $types, true);
        })->count();
    }

    public function countSince(Carbon $from): int
    {
        if (! TraccarSchema::hasEvents()) {
            return 0;
        }

        return DB::table(config('traccar.tables.events', 'tc_events'))
            ->where('eventtime', '>=', $from)
            ->count();
    }

    public function recentForDevices(Collection $deviceIds, int $limit = 12): Collection
    {
        if ($deviceIds->isEmpty() || ! TraccarSchema::hasEvents()) {
            return collect();
        }

        $traccarDeviceIds = $deviceIds
            ->map(fn ($id) => $this->idMap->get(TraccarEntityMap::TYPE_DEVICE, (int) $id))
            ->filter()
            ->values()
            ->all();

        if ($traccarDeviceIds === []) {
            return collect();
        }

        $rows = DB::table(config('traccar.tables.events', 'tc_events'))
            ->whereIn('deviceid', $traccarDeviceIds)
            ->orderByDesc('eventtime')
            ->orderByDesc('id')
            ->limit($limit)
            ->get();

        $deviceIdByTraccar = [];
        foreach ($deviceIds as $laravelId) {
            $tid = $this->idMap->get(TraccarEntityMap::TYPE_DEVICE, (int) $laravelId);
            if ($tid) {
                $deviceIdByTraccar[$tid] = (int) $laravelId;
            }
        }

        return $rows->map(function ($row) use ($deviceIdByTraccar) {
            $laravelDeviceId = $deviceIdByTraccar[(int) $row->deviceid] ?? 0;

            return $this->mapper->toVehicleEvent($row, $laravelDeviceId);
        });
    }
}
