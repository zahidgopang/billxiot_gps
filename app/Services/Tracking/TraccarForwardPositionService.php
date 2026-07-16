<?php

namespace App\Services\Tracking;

use App\Contracts\Tracking\PositionReaderInterface;
use App\Events\DeviceLocationUpdated;
use App\Models\Device;
use App\Models\TraccarEntityMap;
use App\Services\Traccar\TraccarIdMap;
use App\Services\Traccar\TraccarPositionMapper;
use App\Services\Tracking\CommandProtocolMapper;
use App\Services\VehicleEventService;
use App\Support\Tracking\DeviceLocationPayload;
use Illuminate\Support\Facades\Cache;

/**
 * Traccar HTTP position forward → Reverb (DeviceLocationUpdated).
 * Runs only when a GPS report arrives; no scheduled tc_positions polling.
 */
class TraccarForwardPositionService
{
    public function __construct(
        private TraccarPositionMapper $mapper,
        private TraccarIdMap $idMap,
        private PositionReaderInterface $positionReader,
        private VehicleEventService $vehicleEvents,
    ) {}

    public function isEnabled(): bool
    {
        return config('traccar.broadcast_positions', true)
            && config('traccar.broadcast_mode', 'light') === 'forward';
    }

    public function handlePayload(array $payload): bool
    {
        $position = $this->extractPosition($payload);

        if ($position === null) {
            return false;
        }

        $device = $this->resolveDevice($payload, $position);

        if (! $device) {
            return false;
        }

        $protocol = $position['protocol']
            ?? $payload['position']['protocol']
            ?? $payload['protocol']
            ?? null;
        if (is_string($protocol) && $protocol !== '') {
            app(CommandProtocolMapper::class)->rememberProtocol($device, $protocol);
        }

        $row = $this->normalizePositionRow($position);
        $location = $this->mapper->toDeviceLocation($row, $device->id);

        if (! $this->shouldBroadcastNow($device->id)) {
            $this->maybeProcessEvents($device, $location);

            return false;
        }

        event(new DeviceLocationUpdated(
            $device->id,
            DeviceLocationPayload::fromDeviceLocation($location, $device)
        ));

        $this->maybeProcessEvents($device, $location);

        return true;
    }

    /**
     * @return array<string, mixed>|null
     */
    private function extractPosition(array $payload): ?array
    {
        if (isset($payload['position']) && is_array($payload['position'])) {
            return $payload['position'];
        }

        if (isset($payload['latitude'], $payload['longitude'])) {
            return $payload;
        }

        return null;
    }

    /**
     * @param  array<string, mixed>  $position
     */
    private function resolveDevice(array $payload, array $position): ?Device
    {
        $traccarDeviceId = (int) (
            $position['deviceId']
            ?? $payload['device']['id']
            ?? $payload['deviceId']
            ?? 0
        );

        if ($traccarDeviceId > 0) {
            $laravelId = $this->idMap->laravelId(TraccarEntityMap::TYPE_DEVICE, $traccarDeviceId);

            if ($laravelId) {
                $device = Device::query()->find($laravelId);

                if ($device) {
                    return $device;
                }
            }
        }

        $uniqueId = (string) (
            $payload['device']['uniqueId']
            ?? $payload['uniqueId']
            ?? $payload['id']
            ?? ''
        );

        if ($uniqueId !== '') {
            return Device::query()->whereImei($uniqueId)->first();
        }

        return null;
    }

    /**
     * @param  array<string, mixed>  $position
     * @return array<string, mixed>
     */
    private function normalizePositionRow(array $position): array
    {
        $row = $position;

        if (isset($row['fixTime']) && ! isset($row['fixtime'])) {
            $row['fixtime'] = $row['fixTime'];
        }

        if (isset($row['deviceTime']) && ! isset($row['devicetime'])) {
            $row['devicetime'] = $row['deviceTime'];
        }

        if (isset($row['serverTime']) && ! isset($row['servertime'])) {
            $row['servertime'] = $row['serverTime'];
        }

        if (isset($row['attributes']) && is_array($row['attributes'])) {
            $row['attributes'] = json_encode($row['attributes']);
        }

        return $row;
    }

    private function shouldBroadcastNow(int $deviceId): bool
    {
        $minSeconds = max(0, (int) config('traccar.forward.broadcast_min_interval_seconds', 2));

        if ($minSeconds === 0) {
            return true;
        }

        $key = "traccar:forward:broadcast:{$deviceId}";

        if (Cache::has($key)) {
            return false;
        }

        Cache::put($key, 1, $minSeconds);

        return true;
    }

    private function maybeProcessEvents(Device $device, \App\Models\DeviceLocation $location): void
    {
        if (! filter_var(config('traccar.forward.process_events', true), FILTER_VALIDATE_BOOL)) {
            return;
        }

        $debounce = max(5, (int) config('traccar.forward.events_debounce_seconds', 30));
        $key = "traccar:forward:events:{$device->id}";

        if (Cache::has($key)) {
            return;
        }

        Cache::put($key, 1, $debounce);

        app()->terminating(function () use ($device, $location): void {
            $previous = $this->positionReader->previousBefore(
                $device,
                (int) $location->id,
                (int) $location->id
            );

            $this->vehicleEvents->processLocation($device, $location, $previous);
        });
    }
}
