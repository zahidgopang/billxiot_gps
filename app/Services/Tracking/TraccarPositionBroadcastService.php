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
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

/**
 * Watches tc_positions for rows written by Traccar (not the Laravel ingest API)
 * and broadcasts DeviceLocationUpdated so open map clients update in real time.
 *
 * Use broadcast_mode=light on small servers (AWS Lightsail): WebSocket only,
 * latest GPS per device, alerts via traccar:process-position-events.
 */
class TraccarPositionBroadcastService
{
    private const CACHE_KEY = 'traccar:last_broadcast_position_id';

    public function __construct(
        private TraccarPositionMapper $mapper,
        private TraccarIdMap $idMap,
        private PositionReaderInterface $positionReader,
        private VehicleEventService $vehicleEvents,
        private DevicePositionLoader $positionLoader,
    ) {}

    public function broadcastNewPositions(): int
    {
        if (! config('traccar.broadcast_positions', true) || ! TraccarSchema::isReady()) {
            return 0;
        }

        if (config('traccar.broadcast_mode', 'light') === 'off') {
            return 0;
        }

        $table = config('traccar.tables.positions', 'tc_positions');
        $lastId = $this->lastBroadcastPositionId();

        if ($lastId === 0) {
            $currentMax = (int) (DB::table($table)->max('id') ?? 0);
            $this->saveLastBroadcastPositionId($currentMax);

            return 0;
        }

        $limit = max(1, (int) config('traccar.broadcast_positions_limit', 80));

        $rows = DB::table($table)
            ->where('id', '>', $lastId)
            ->orderBy('id')
            ->limit($limit)
            ->get();

        if ($rows->isEmpty()) {
            return 0;
        }

        $maxId = (int) $rows->max('id');

        if ($this->shouldKeepLatestPerDevice()) {
            $rows = $this->latestRowPerTraccarDevice($rows);
        }

        $processEvents = $this->shouldProcessEventsInline();

        $laravelIds = [];
        $mappedRows = [];

        foreach ($rows as $row) {
            $laravelDeviceId = $this->idMap->laravelId(
                TraccarEntityMap::TYPE_DEVICE,
                (int) $row->deviceid
            );

            if (! $laravelDeviceId) {
                continue;
            }

            $laravelIds[] = $laravelDeviceId;
            $mappedRows[] = [$laravelDeviceId, $row];
        }

        if ($mappedRows === []) {
            $this->saveLastBroadcastPositionId($maxId);

            return 0;
        }

        $devices = Device::query()
            ->whereIn('id', array_values(array_unique($laravelIds)))
            ->get()
            ->keyBy('id');

        $count = 0;

        foreach ($mappedRows as [$laravelDeviceId, $row]) {
            $device = $devices->get($laravelDeviceId);

            if (! $device) {
                continue;
            }

            $location = $this->mapper->toDeviceLocation($row, $laravelDeviceId);

            event(new DeviceLocationUpdated(
                $device->id,
                DeviceLocationPayload::fromDeviceLocation($location, $device)
            ));

            if ($processEvents) {
                $previous = $this->positionReader->previousBefore(
                    $device,
                    (int) $location->id,
                    (int) $location->id
                );
                $this->vehicleEvents->processLocation($device, $location, $previous);
            }

            $count++;
        }

        $this->saveLastBroadcastPositionId($maxId);

        return $count;
    }

    /**
     * Light mode: run status/geofence/push logic once per device from latest GPS (not every tc_positions row).
     */
    public function processLatestPositionEvents(): int
    {
        if (! TraccarSchema::isReady()) {
            return 0;
        }

        $devices = Device::query()
            ->where('status', 'active')
            ->get();

        if ($devices->isEmpty()) {
            return 0;
        }

        $this->positionLoader->attachLatestToMany($devices);

        $count = 0;

        foreach ($devices as $device) {
            $location = $device->latestLocation;

            if ($location === null || $location->lat === null || $location->lng === null) {
                continue;
            }

            $previous = $this->positionReader->previousBefore(
                $device,
                (int) $location->id,
                (int) $location->id
            );

            $this->vehicleEvents->processLocation($device, $location, $previous);
            $count++;
        }

        return $count;
    }

    private function shouldKeepLatestPerDevice(): bool
    {
        return filter_var(
            config('traccar.broadcast_latest_per_device', true),
            FILTER_VALIDATE_BOOL,
        );
    }

    private function shouldProcessEventsInline(): bool
    {
        if (config('traccar.broadcast_mode', 'light') === 'light') {
            return false;
        }

        return filter_var(
            config('traccar.broadcast_process_events', false),
            FILTER_VALIDATE_BOOL,
        ) || config('traccar.broadcast_mode') === 'full';
    }

    /**
     * @param  Collection<int, object>  $rows  ordered by id asc
     * @return Collection<int, object>
     */
    private function latestRowPerTraccarDevice(Collection $rows): Collection
    {
        $latest = [];

        foreach ($rows as $row) {
            $latest[(int) $row->deviceid] = $row;
        }

        return collect(array_values($latest));
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
