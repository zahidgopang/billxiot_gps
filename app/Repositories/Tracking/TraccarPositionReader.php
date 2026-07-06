<?php

namespace App\Repositories\Tracking;

use App\Contracts\Tracking\PositionReaderInterface;
use App\Models\Device;
use App\Models\DeviceLocation;
use App\Models\TraccarEntityMap;
use App\Services\Traccar\TraccarIdMap;
use App\Services\Traccar\TraccarPositionMapper;
use App\Services\Traccar\TraccarSyncService;
use App\Support\Tracking\HistoryRangeBounds;
use App\Support\Traccar\TraccarSchema;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class TraccarPositionReader implements PositionReaderInterface
{
    public function __construct(
        private TraccarSyncService $sync,
        private TraccarIdMap $idMap,
        private TraccarPositionMapper $mapper,
    ) {}

    public function latestForDevice(Device $device): ?DeviceLocation
    {
        if (! TraccarSchema::isReady()) {
            return null;
        }

        $traccarDeviceId = $this->resolveTraccarDeviceId($device);

        if (! $traccarDeviceId) {
            return null;
        }

        $row = DB::table(config('traccar.tables.positions', 'tc_positions'))
            ->where('deviceid', $traccarDeviceId)
            ->orderByDesc('fixtime')
            ->orderByDesc('id')
            ->first();

        return $row ? $this->mapper->toDeviceLocation($row, $device->id) : null;
    }

    /**
     * @param  list<int>  $deviceIds
     * @return array<int, DeviceLocation>
     */
    public function latestForDevices(array $deviceIds): array
    {
        $deviceIds = array_values(array_unique(array_filter(array_map('intval', $deviceIds))));
        if ($deviceIds === [] || ! TraccarSchema::isReady()) {
            return [];
        }

        $traccarToLaravel = [];
        foreach ($deviceIds as $laravelId) {
            $traccarId = $this->idMap->get(TraccarEntityMap::TYPE_DEVICE, $laravelId);
            if ($traccarId) {
                $traccarToLaravel[(int) $traccarId] = (int) $laravelId;
            }
        }

        if ($traccarToLaravel === []) {
            return [];
        }

        $table = config('traccar.tables.positions', 'tc_positions');
        $traccarIds = array_keys($traccarToLaravel);

        $rows = DB::table("{$table} as p")
            ->joinSub(
                DB::table($table)
                    ->select('deviceid', DB::raw('MAX(id) as max_id'))
                    ->whereIn('deviceid', $traccarIds)
                    ->groupBy('deviceid'),
                'latest',
                fn ($join) => $join
                    ->on('p.deviceid', '=', 'latest.deviceid')
                    ->on('p.id', '=', 'latest.max_id')
            )
            ->get();

        $out = [];
        foreach ($rows as $row) {
            $laravelId = $traccarToLaravel[(int) $row->deviceid] ?? null;
            if ($laravelId) {
                $out[$laravelId] = $this->mapper->toDeviceLocation($row, $laravelId);
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
        if (! TraccarSchema::isReady()) {
            return collect();
        }

        $traccarDeviceId = $this->resolveTraccarDeviceId($device);

        if (! $traccarDeviceId) {
            return collect();
        }

        return $this->historyQuery([$traccarDeviceId], $from, $to, $order)
            ->map(fn ($row) => $this->mapper->toDeviceLocation($row, $device->id));
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
        if (! TraccarSchema::isReady() || $devices === []) {
            return [];
        }

        $traccarToLaravel = [];
        foreach ($devices as $device) {
            $traccarId = $this->resolveTraccarDeviceId($device);
            if ($traccarId) {
                $traccarToLaravel[(int) $traccarId] = (int) $device->id;
            }
        }

        if ($traccarToLaravel === []) {
            return [];
        }

        $rows = $this->historyQuery(array_keys($traccarToLaravel), $from, $to, $order);
        $grouped = [];
        foreach ($traccarToLaravel as $laravelId) {
            $grouped[$laravelId] = collect();
        }

        foreach ($rows as $row) {
            $laravelId = $traccarToLaravel[(int) $row->deviceid] ?? null;
            if ($laravelId) {
                $grouped[$laravelId]->push($this->mapper->toDeviceLocation($row, $laravelId));
            }
        }

        return $grouped;
    }

    /**
     * @param  list<int>  $traccarDeviceIds
     */
    private function historyQuery(array $traccarDeviceIds, ?Carbon $from, ?Carbon $to, string $order)
    {
        $query = DB::table(config('traccar.tables.positions', 'tc_positions'))
            ->select([
                'id',
                'deviceid',
                'fixtime',
                'latitude',
                'longitude',
                'speed',
                'course',
                'attributes',
            ])
            ->whereIn('deviceid', $traccarDeviceIds);

        if ($from) {
            $fromBound = HistoryRangeBounds::isCalendarDayStart($from)
                ? HistoryRangeBounds::traccarFromUtc($from)
                : HistoryRangeBounds::traccarInstantUtc($from);
            $query->where('fixtime', '>=', $fromBound);
        }

        if ($to) {
            if (HistoryRangeBounds::isCalendarDayEnd($to)) {
                $query->where('fixtime', '<', HistoryRangeBounds::traccarToExclusiveUtc($to));
            } else {
                $query->where('fixtime', '<=', HistoryRangeBounds::traccarInstantUtc($to));
            }
        }

        $direction = strtolower($order) === 'desc' ? 'desc' : 'asc';

        return $query
            ->orderBy('fixtime', $direction)
            ->orderBy('id', $direction)
            ->get();
    }

    public function previousBefore(Device $device, int $excludeLocationId, ?int $excludeTraccarPositionId = null): ?DeviceLocation
    {
        if (! TraccarSchema::isReady()) {
            return null;
        }

        $traccarDeviceId = $this->resolveTraccarDeviceId($device);

        if (! $traccarDeviceId) {
            return null;
        }

        $query = DB::table(config('traccar.tables.positions', 'tc_positions'))
            ->where('deviceid', $traccarDeviceId);

        if ($excludeTraccarPositionId) {
            $query->where('id', '!=', $excludeTraccarPositionId);
        } elseif ($excludeLocationId) {
            $query->where('id', '!=', $excludeLocationId);
        }

        $row = $query->orderByDesc('fixtime')->orderByDesc('id')->first();

        return $row ? $this->mapper->toDeviceLocation($row, $device->id) : null;
    }

    private function resolveTraccarDeviceId(Device $device): ?int
    {
        $mapped = $this->idMap->get(TraccarEntityMap::TYPE_DEVICE, $device->id);

        if ($mapped) {
            return $mapped;
        }

        try {
            return $this->sync->syncDevice($device);
        } catch (\Throwable $e) {
            report($e);

            return null;
        }
    }
}
