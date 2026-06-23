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

        $query = DB::table(config('traccar.tables.positions', 'tc_positions'))
            ->where('deviceid', $traccarDeviceId);

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
            ->get()
            ->map(fn ($row) => $this->mapper->toDeviceLocation($row, $device->id));
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
