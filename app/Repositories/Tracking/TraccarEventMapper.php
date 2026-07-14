<?php

namespace App\Repositories\Tracking;

use App\Models\Device;
use App\Models\Geofence;
use App\Models\TraccarEntityMap;
use App\Models\VehicleEvent;
use App\Services\Traccar\TraccarIdMap;
use App\Support\Push\PushNotificationMapper;
use App\Support\Push\PushNotificationType;
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
            'lat' => (float) ($attrs['latitude'] ?? $data['position_latitude'] ?? $data['latitude'] ?? 0),
            'lng' => (float) ($attrs['longitude'] ?? $data['position_longitude'] ?? $data['longitude'] ?? 0),
            'meta' => $attrs['meta'] ?? null,
            // tc_events.eventtime is stored in UTC — parse as UTC then convert to
            // the app timezone so alert times display correctly (not offset).
            'occurred_at' => isset($data['eventtime'])
                ? Carbon::parse($data['eventtime'], 'UTC')->setTimezone(config('app.timezone'))
                : now(),
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
            'deviceOverspeed', 'overspeed' => VehicleEvent::TYPE_OVERSPEED,
            'maintenance' => VehicleEvent::TYPE_MAINTENANCE,
            'alarm', 'sos' => VehicleEvent::TYPE_PANIC,
            'ignitionOn', 'deviceIgnitionOn', 'engineOn' => VehicleEvent::TYPE_RUNNING,
            'ignitionOff', 'deviceIgnitionOff', 'engineOff' => 'parked',
            'deviceOnline', 'online' => 'device_online',
            'deviceOffline', 'offline', 'deviceUnknown' => VehicleEvent::TYPE_OFFLINE,
            'deviceMoving', 'deviceMovingStart' => VehicleEvent::TYPE_RUNNING,
            'deviceStopped', 'deviceMovingStop' => VehicleEvent::TYPE_STOPPED,
            'lowBattery', 'deviceLowBattery' => VehicleEvent::TYPE_LOW_BATTERY,
            'powerCut', 'devicePowerCut' => VehicleEvent::TYPE_POWER_CUT,
            default => $traccarType,
        };
    }

    public function mapType(string $laravelType): string
    {
        return match ($laravelType) {
            VehicleEvent::TYPE_GEOFENCE_ENTER => 'geofenceEnter',
            VehicleEvent::TYPE_GEOFENCE_EXIT => 'geofenceExit',
            VehicleEvent::TYPE_OVERSPEED => 'deviceOverspeed',
            VehicleEvent::TYPE_MAINTENANCE => 'maintenance',
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
                'Geofence Entry',
                sprintf('%s entered "%s"%s.', $deviceName, $zone, $coords),
            ],
            VehicleEvent::TYPE_GEOFENCE_EXIT => [
                'Geofence Exit',
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
            VehicleEvent::TYPE_POWER_CUT => ['Power Cut', sprintf('Power cut on %s.', $deviceName)],
            VehicleEvent::TYPE_LOW_BATTERY => ['Low Battery', sprintf('Low battery on %s.', $deviceName)],
            VehicleEvent::TYPE_STOPPED => ['Engine Off', sprintf('%s has stopped.', $deviceName)],
            VehicleEvent::TYPE_RUNNING => ['Engine On', sprintf('%s engine is on / moving.', $deviceName)],
            'idle' => ['Vehicle Idle', sprintf('%s is idle (ignition on).', $deviceName)],
            'parked' => ['Engine Off', sprintf('%s is parked (engine off).', $deviceName)],
            'device_online' => ['Device Connected', sprintf('%s is online again.', $deviceName)],
            VehicleEvent::TYPE_OFFLINE => ['Device Disconnected', sprintf('%s disconnected.', $deviceName)],
            VehicleEvent::TYPE_MAINTENANCE => ['Maintenance Due', sprintf('%s is due for scheduled maintenance.', $deviceName)],
            VehicleEvent::TYPE_TRIP_COMPLETED => ['Trip Completed', sprintf('%s completed a planned route.', $deviceName)],
            VehicleEvent::TYPE_DELAYED => ['Delayed Data', sprintf('%s has delayed GPS updates.', $deviceName)],
            VehicleEvent::TYPE_GSM_WEAK => ['Weak GSM Signal', sprintf('%s has a weak GSM signal.', $deviceName)],
            VehicleEvent::TYPE_GPS_WEAK => ['Weak GPS Signal', sprintf('%s has a weak GPS signal.', $deviceName)],
            VehicleEvent::TYPE_IGNITION => ['Ignition Off While Moving', sprintf('%s ignition is off while moving.', $deviceName)],
            default => $this->fallbackCopy($laravelType, $deviceName),
        };
    }

    /**
     * @return array{0: string, 1: string}
     */
    private function fallbackCopy(string $laravelType, string $deviceName): array
    {
        $pushType = PushNotificationMapper::fromVehicleEventType($laravelType);
        $label = $pushType
            ? PushNotificationType::title($pushType)
            : trim(ucwords(str_replace(['_', '-', '.'], ' ', $laravelType)));

        if ($label === '' || strcasecmp($label, 'Event') === 0 || strcasecmp($label, 'Unknown') === 0) {
            $label = 'Fleet Alert';
        }

        return [
            $label,
            sprintf('%s — %s.', $deviceName, $label),
        ];
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
