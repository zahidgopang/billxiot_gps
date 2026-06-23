<?php

namespace App\Services\Tracking;

use App\Contracts\Tracking\PositionReaderInterface;
use App\Events\DeviceLocationUpdated;
use App\Models\Device;
use App\Models\TraccarEntityMap;
use App\Services\Traccar\TraccarIdMap;
use App\Services\Traccar\TraccarPositionMapper;
use App\Services\VehicleEventService;
use App\Support\RuntimeState;
use App\Support\Tracking\DeviceLocationPayload;
use App\Support\Traccar\TraccarSchema;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

/**
 * Watches tc_positions for rows written by Traccar (not the Laravel ingest API)
 * and broadcasts DeviceLocationUpdated so open map pages update in real time.
 */
class TraccarPositionBroadcastService
{
    private const CACHE_KEY = 'traccar:last_broadcast_position_id';

    public function __construct(
        private TraccarPositionMapper $mapper,
        private TraccarIdMap $idMap,
        private PositionReaderInterface $positionReader,
        private VehicleEventService $vehicleEvents,
    ) {}

    public function broadcastNewPositions(): int
    {
        if (! config('traccar.broadcast_positions', true) || ! TraccarSchema::isReady()) {
            return 0;
        }

        $table = config('traccar.tables.positions', 'tc_positions');
        $lastId = $this->lastBroadcastPositionId();

        if ($lastId === 0) {
            $currentMax = (int) (DB::table($table)->max('id') ?? 0);
            $this->saveLastBroadcastPositionId($currentMax);

            return 0;
        }

        $limit = max(1, (int) config('traccar.broadcast_positions_limit', 200));

        $rows = DB::table($table)
            ->where('id', '>', $lastId)
            ->orderBy('id')
            ->limit($limit)
            ->get();

        if ($rows->isEmpty()) {
            return 0;
        }

        $count = 0;
        $maxId = $lastId;

        foreach ($rows as $row) {
            $maxId = max($maxId, (int) $row->id);

            $laravelDeviceId = $this->idMap->laravelId(
                TraccarEntityMap::TYPE_DEVICE,
                (int) $row->deviceid
            );

            if (! $laravelDeviceId) {
                continue;
            }

            $device = Device::query()->find($laravelDeviceId);

            if (! $device) {
                continue;
            }

            $location = $this->mapper->toDeviceLocation($row, $laravelDeviceId);

            event(new DeviceLocationUpdated(
                $device->id,
                DeviceLocationPayload::fromDeviceLocation($location, $device)
            ));

            $previous = $this->positionReader->previousBefore(
                $device,
                (int) $location->id,
                (int) $location->id
            );

            $this->vehicleEvents->processLocation($device, $location, $previous);
            $count++;
        }

        $this->saveLastBroadcastPositionId($maxId);

        return $count;
    }

    private function lastBroadcastPositionId(): int
    {
        $stored = RuntimeState::getInt(self::CACHE_KEY, 0);

        if ($stored > 0) {
            return $stored;
        }

        $legacy = (int) Cache::get(self::CACHE_KEY, 0);

        if ($legacy > 0) {
            $this->saveLastBroadcastPositionId($legacy);
            Cache::forget(self::CACHE_KEY);
        }

        return $legacy;
    }

    private function saveLastBroadcastPositionId(int $id): void
    {
        RuntimeState::putInt(self::CACHE_KEY, $id);
    }
}
