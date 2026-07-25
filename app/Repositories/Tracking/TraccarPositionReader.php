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

        $devicesTable = config('traccar.tables.devices', 'tc_devices');
        $positionsTable = config('traccar.tables.positions', 'tc_positions');

        // Prefer tc_devices.positionid (O(1)) — MAX(id) over tc_positions is too slow on large fleets.
        $positionId = DB::table($devicesTable)
            ->where('id', $traccarDeviceId)
            ->value('positionid');

        $row = null;
        if ($positionId) {
            $row = DB::table($positionsTable)->where('id', (int) $positionId)->first();
        }

        if (! $row) {
            $row = DB::table($positionsTable)
                ->where('deviceid', $traccarDeviceId)
                ->orderByDesc('fixtime')
                ->orderByDesc('id')
                ->first();
        }

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

        $devicesTable = config('traccar.tables.devices', 'tc_devices');
        $positionsTable = config('traccar.tables.positions', 'tc_positions');
        $traccarIds = array_keys($traccarToLaravel);

        $deviceRows = DB::table($devicesTable)
            ->whereIn('id', $traccarIds)
            ->get(['id', 'positionid']);

        $positionIds = [];
        $missingTraccarIds = [];
        foreach ($deviceRows as $deviceRow) {
            $tcId = (int) $deviceRow->id;
            $posId = (int) ($deviceRow->positionid ?? 0);
            if ($posId > 0) {
                $positionIds[$posId] = $tcId;
            } else {
                $missingTraccarIds[] = $tcId;
            }
        }

        $out = [];

        if ($positionIds !== []) {
            $rows = DB::table($positionsTable)->whereIn('id', array_keys($positionIds))->get();
            foreach ($rows as $row) {
                $tcId = $positionIds[(int) $row->id] ?? (int) $row->deviceid;
                $laravelId = $traccarToLaravel[$tcId] ?? null;
                if ($laravelId) {
                    $out[$laravelId] = $this->mapper->toDeviceLocation($row, $laravelId);
                }
            }
        }

        // Fallback only for devices without positionid (rare) — avoid scanning all positions for the fleet.
        foreach ($missingTraccarIds as $tcId) {
            $laravelId = $traccarToLaravel[$tcId] ?? null;
            if (! $laravelId || isset($out[$laravelId])) {
                continue;
            }
            $row = DB::table($positionsTable)
                ->where('deviceid', $tcId)
                ->orderByDesc('fixtime')
                ->orderByDesc('id')
                ->first();
            if ($row) {
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
