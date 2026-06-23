<?php

namespace App\Repositories\Tracking;

use App\Models\Device;
use App\Models\Geofence;
use App\Models\TraccarEntityMap;
use App\Models\VehicleEvent;
use App\Services\Traccar\TraccarIdMap;
use App\Support\Traccar\TraccarAttributes;
use Carbon\Carbon;
use stdClass;

class TraccarEventMapper
{
    public function __construct(
        private TraccarIdMap $idMap,
    ) {}

    public function toVehicleEvent(stdClass|array $row, int $laravelDeviceId): VehicleEvent
    {
        $data = is_array($row) ? $row : (array) $row;
        $attrs = TraccarAttributes::decode($data['attributes'] ?? null);
        $traccarType = (string) ($data['type'] ?? '');
        $laravelType = (string) ($attrs['laravel_type'] ?? $this->reverseMapType($traccarType));
        $geofenceId = $this->resolveLaravelGeofenceId($data, $attrs);
        $device = $laravelDeviceId > 0 ? Device::query()->find($laravelDeviceId) : null;
        $geofence = $this->resolveGeofence($geofenceId);

        $title = trim((string) ($attrs['title'] ?? ''));
        $message = trim((string) ($attrs['message'] ?? ''));

        if ($title === '' || $message === '') {
            [$defaultTitle, $defaultMessage] = $this->defaultCopy(
                $laravelType,
                $device?->notificationDisplayName() ?? 'Vehicle',
                $geofence?->name,
                isset($attrs['speed']) ? (float) $attrs['speed'] : null,
                (float) ($attrs['latitude'] ?? $data['latitude'] ?? 0),
                (float) ($attrs['longitude'] ?? $data['longitude'] ?? 0),
            );
            if ($title === '') {
                $title = $defaultTitle;
            }
            if ($message === '') {
                $message = $defaultMessage;
            }
        }

        $event = new VehicleEvent([
            'device_id' => $laravelDeviceId,
            'geofence_id' => $geofenceId,
            'type' => $laravelType,
            'title' => $title,
            'message' => $message,
            'speed' => isset($attrs['speed']) ? (float) $attrs['speed'] : null,
            'lat' => (float) ($attrs['latitude'] ?? 0),
            'lng' => (float) ($attrs['longitude'] ?? 0),
            'meta' => $attrs['meta'] ?? null,
            'occurred_at' => Carbon::parse($data['eventtime'] ?? now()),
        ]);

        $event->id = (int) ($data['id'] ?? 0);
        $event->exists = true;

        if ($geofence) {
            $event->setRelation('geofence', $geofence);
        }

        return $event;
    }

    public function reverseMapType(string $traccarType): string
    {
        return match ($traccarType) {
            'geofenceEnter' => VehicleEvent::TYPE_GEOFENCE_ENTER,
            'geofenceExit' => VehicleEvent::TYPE_GEOFENCE_EXIT,
            'deviceOverspeed' => VehicleEvent::TYPE_OVERSPEED,
            'alarm' => VehicleEvent::TYPE_PANIC,
            default => $traccarType,
        };
    }

    public function mapType(string $laravelType): string
    {
        return match ($laravelType) {
            VehicleEvent::TYPE_GEOFENCE_ENTER => 'geofenceEnter',
            VehicleEvent::TYPE_GEOFENCE_EXIT => 'geofenceExit',
            VehicleEvent::TYPE_OVERSPEED => 'deviceOverspeed',
            VehicleEvent::TYPE_PANIC, VehicleEvent::TYPE_POWER_CUT => 'alarm',
            default => $laravelType,
        };
    }

    /**
     * @return array{0: string, 1: string}
     */
    public function defaultCopy(
        string $laravelType,
        string $deviceName,
        ?string $geofenceName,
        ?float $speed,
        float $lat,
        float $lng,
    ): array {
        $zone = $geofenceName ?: 'geofence zone';
        $coords = ($lat || $lng)
            ? sprintf(' at %.5f, %.5f', $lat, $lng)
            : '';

        return match ($laravelType) {
            VehicleEvent::TYPE_GEOFENCE_ENTER => [
                'Entered geofence',
                sprintf('%s entered "%s"%s.', $deviceName, $zone, $coords),
            ],
            VehicleEvent::TYPE_GEOFENCE_EXIT => [
                'Left geofence',
                sprintf('%s exited "%s"%s.', $deviceName, $zone, $coords),
            ],
            VehicleEvent::TYPE_OVERSPEED => [
                'Overspeed',
                sprintf(
                    '%s exceeded speed limit%s.',
                    $deviceName,
                    $speed !== null ? sprintf(' (%.0f km/h)', $speed) : ''
                ),
            ],
            VehicleEvent::TYPE_PANIC => ['SOS / Panic', sprintf('Emergency alert on %s.', $deviceName)],
            VehicleEvent::TYPE_POWER_CUT => ['Power cut', sprintf('Power cut on %s.', $deviceName)],
            VehicleEvent::TYPE_LOW_BATTERY => ['Low battery', sprintf('Low battery on %s.', $deviceName)],
            VehicleEvent::TYPE_STOPPED => ['Vehicle stopped', sprintf('%s has stopped.', $deviceName)],
            VehicleEvent::TYPE_RUNNING => ['Vehicle running', sprintf('%s is moving.', $deviceName)],
            default => [
                ucfirst(str_replace('_', ' ', $laravelType)),
                sprintf('%s event recorded.', $deviceName),
            ],
        };
    }

    private function resolveLaravelGeofenceId(array $data, array $attrs): ?int
    {
        if (isset($attrs['laravel_geofence_id'])) {
            return (int) $attrs['laravel_geofence_id'];
        }

        if (! isset($data['geofenceid']) || $data['geofenceid'] === null || $data['geofenceid'] === '') {
            return null;
        }

        $traccarGeofenceId = (int) $data['geofenceid'];

        return $this->idMap->laravelId(TraccarEntityMap::TYPE_GEOFENCE, $traccarGeofenceId) ?? $traccarGeofenceId;
    }

    private function resolveGeofence(?int $geofenceId): ?Geofence
    {
        if (! $geofenceId) {
            return null;
        }

        return Geofence::query()->find($geofenceId);
    }
}
