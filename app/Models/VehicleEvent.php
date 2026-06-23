<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class VehicleEvent extends Model
{
    public const TYPE_STOPPED = 'stopped';

    public const TYPE_RUNNING = 'running';

    public const TYPE_SLOW_SPEED = 'slow_speed';

    public const TYPE_OVERSPEED = 'overspeed';

    public const TYPE_GEOFENCE_ENTER = 'geofence_enter';

    public const TYPE_GEOFENCE_EXIT = 'geofence_exit';

    public const TYPE_LOW_BATTERY = 'low_battery';

    public const TYPE_POWER_CUT = 'power_cut';

    public const TYPE_PANIC = 'panic';

    public const TYPE_IGNITION = 'ignition_off_moving';

    public const TYPE_DELAYED = 'delayed_data';

    public const TYPE_OFFLINE = 'device_offline';

    public const TYPE_COMM_LOST_MOVING = 'comm_lost_moving';

    public const TYPE_COMM_LOST_IGNITION = 'comm_lost_ignition';

    public const TYPE_TAMPERING = 'tampering_suspected';

    public const TYPE_GSM_WEAK = 'gsm_weak';

    public const TYPE_GPS_WEAK = 'gps_weak';

    /** @return list<string> */
    public static function criticalTypes(): array
    {
        return [
            self::TYPE_PANIC,
            self::TYPE_POWER_CUT,
            self::TYPE_COMM_LOST_MOVING,
            self::TYPE_TAMPERING,
            self::TYPE_OVERSPEED,
            self::TYPE_GEOFENCE_EXIT,
        ];
    }

    /** @return list<string> */
    public static function warningTypes(): array
    {
        return [
            self::TYPE_DELAYED,
            self::TYPE_COMM_LOST_IGNITION,
            self::TYPE_GSM_WEAK,
            self::TYPE_GPS_WEAK,
            self::TYPE_LOW_BATTERY,
            self::TYPE_IGNITION,
            self::TYPE_OFFLINE,
        ];
    }

    /** @return list<string> */
    public static function dashboardAlertTypes(): array
    {
        return array_values(array_unique(array_merge(
            self::criticalTypes(),
            self::warningTypes(),
        )));
    }

    protected $fillable = [
        'device_id',
        'geofence_id',
        'type',
        'title',
        'message',
        'speed',
        'lat',
        'lng',
        'meta',
        'occurred_at',
    ];

    protected $casts = [
        'speed' => 'float',
        'lat' => 'float',
        'lng' => 'float',
        'meta' => 'array',
        'occurred_at' => 'datetime',
    ];

    public function device(): BelongsTo
    {
        return $this->belongsTo(Device::class);
    }

    public function geofence(): BelongsTo
    {
        return $this->belongsTo(Geofence::class);
    }

    public function severity(): string
    {
        return match ($this->type) {
            self::TYPE_PANIC,
            self::TYPE_POWER_CUT,
            self::TYPE_COMM_LOST_MOVING,
            self::TYPE_TAMPERING => 'critical',
            self::TYPE_OVERSPEED,
            self::TYPE_GEOFENCE_EXIT,
            self::TYPE_LOW_BATTERY,
            self::TYPE_IGNITION,
            self::TYPE_DELAYED,
            self::TYPE_COMM_LOST_IGNITION,
            self::TYPE_GSM_WEAK,
            self::TYPE_GPS_WEAK,
            self::TYPE_OFFLINE => 'warning',
            default => 'info',
        };
    }

    public function typeLabel(): string
    {
        return match ($this->type) {
            self::TYPE_STOPPED => 'Stopped',
            self::TYPE_RUNNING => 'Running',
            self::TYPE_SLOW_SPEED => 'Slow speed',
            self::TYPE_OVERSPEED => 'Overspeed',
            self::TYPE_GEOFENCE_ENTER => 'Geofence enter',
            self::TYPE_GEOFENCE_EXIT => 'Geofence exit',
            self::TYPE_LOW_BATTERY => 'Low battery',
            self::TYPE_POWER_CUT => 'Power cut',
            self::TYPE_PANIC => 'SOS / Panic',
            self::TYPE_IGNITION => 'Ignition alert',
            self::TYPE_DELAYED => 'Delayed data',
            self::TYPE_OFFLINE => 'Device offline',
            self::TYPE_COMM_LOST_MOVING => 'Communication lost while moving',
            self::TYPE_COMM_LOST_IGNITION => 'Communication lost (ignition ON)',
            self::TYPE_TAMPERING => 'Tampering suspected',
            self::TYPE_GSM_WEAK => 'GSM signal weak',
            self::TYPE_GPS_WEAK => 'GPS signal weak',
            default => ucfirst(str_replace('_', ' ', $this->type)),
        };
    }

    public function toAlertArray(): array
    {
        $geofenceName = $this->geofence?->name;
        if (! $geofenceName && $this->geofence_id) {
            $geofenceName = Geofence::query()->where('id', $this->geofence_id)->value('name');
        }
        if (! $geofenceName && $this->message && preg_match('/geofence\s+"([^"]+)"/i', $this->message, $match)) {
            $geofenceName = $match[1];
        }

        $title = $this->title ?: $this->typeLabel();
        $message = $this->message;

        if ($message === '' && in_array($this->type, [self::TYPE_GEOFENCE_ENTER, self::TYPE_GEOFENCE_EXIT], true)) {
            $zone = $geofenceName ?: 'geofence zone';
            $message = match ($this->type) {
                self::TYPE_GEOFENCE_ENTER => sprintf('Entered geofence "%s".', $zone),
                self::TYPE_GEOFENCE_EXIT => sprintf('Exited geofence "%s".', $zone),
                default => $message,
            };
        }

        if ($geofenceName && ! str_contains($message, $geofenceName)) {
            $message = trim($message.' ('.$geofenceName.')');
        }

        return [
            'id' => $this->id,
            'type' => $this->severity(),
            'event_type' => $this->type,
            'title' => $title,
            'message' => $message,
            'geofence' => $geofenceName,
            'speed' => $this->speed,
            'lat' => $this->lat,
            'lng' => $this->lng,
            ...\App\Support\DateTime\AppDateTime::apiFields($this->occurred_at),
            'date' => \App\Support\DateTime\AppDateTime::format($this->occurred_at, 'date'),
            'clock' => \App\Support\DateTime\AppDateTime::format($this->occurred_at, 'time'),
        ];
    }
}
