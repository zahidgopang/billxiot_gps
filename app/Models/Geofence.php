<?php

namespace App\Models;

use App\Models\Concerns\UsesTcTable;
use App\Models\Traccar\TcDeviceGeofence;
use App\Support\Traccar\TraccarAppFields;
use App\Support\Traccar\TraccarAttributes;
use App\Support\Traccar\TraccarSchema;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasOneThrough;
use Illuminate\Support\Facades\DB;

/**
 * Geofence — tc_geofences (same table as Traccar). Geometry in area WKT; shape meta in attributes.
 */
class Geofence extends Model
{
    use UsesTcTable;

    public $timestamps = false;

    protected $guarded = [];

    public function getTable(): string
    {
        return config('traccar.tables.geofences', 'tc_geofences');
    }

    public function getTypeAttribute(): string
    {
        return (string) TraccarAppFields::get(
            $this->getTraccarAttributesJson(),
            TraccarAppFields::KEY_GEOFENCE_TYPE,
            'polygon'
        );
    }

    public function setTypeAttribute(string $value): void
    {
        $this->patchTraccarAppAttributes([TraccarAppFields::KEY_GEOFENCE_TYPE => $value]);
    }

    public function getCenterAttribute(): ?string
    {
        $center = TraccarAppFields::get($this->getTraccarAttributesJson(), TraccarAppFields::KEY_GEOFENCE_CENTER);

        return is_array($center) ? json_encode($center) : (is_string($center) ? $center : null);
    }

    public function setCenterAttribute($value): void
    {
        $decoded = is_string($value) ? json_decode($value, true) : $value;
        $this->patchTraccarAppAttributes([TraccarAppFields::KEY_GEOFENCE_CENTER => $decoded]);
    }

    public function getCoordsAttribute(): ?string
    {
        $coords = TraccarAppFields::get($this->getTraccarAttributesJson(), TraccarAppFields::KEY_GEOFENCE_COORDS);

        return is_array($coords) ? json_encode($coords) : (is_string($coords) ? $coords : null);
    }

    public function setCoordsAttribute($value): void
    {
        $decoded = is_string($value) ? json_decode($value, true) : $value;
        $this->patchTraccarAppAttributes([TraccarAppFields::KEY_GEOFENCE_COORDS => $decoded]);
    }

    public function getRadiusAttribute(): ?int
    {
        $r = TraccarAppFields::get($this->getTraccarAttributesJson(), TraccarAppFields::KEY_GEOFENCE_RADIUS);

        return $r !== null ? (int) $r : null;
    }

    public function setRadiusAttribute($value): void
    {
        $this->patchTraccarAppAttributes([TraccarAppFields::KEY_GEOFENCE_RADIUS => $value]);
    }

    public function device(): HasOneThrough
    {
        $keys = TraccarSchema::deviceGeofencePivotKeys();

        return $this->hasOneThrough(
            Device::class,
            TcDeviceGeofence::class,
            $keys['geofence'],
            'id',
            'id',
            $keys['device'],
        );
    }

    public function getDeviceIdAttribute(): ?int
    {
        if ($this->relationLoaded('device') && $this->device) {
            return (int) $this->device->id;
        }

        if (! isset($this->attributes['id'])) {
            return TraccarAppFields::get($this->getTraccarAttributesJson(), TraccarAppFields::KEY_GEOFENCE_DEVICE_ID);
        }

        $keys = TraccarSchema::deviceGeofencePivotKeys();

        return DB::table($keys['table'])->where($keys['geofence'], $this->attributes['id'])->value($keys['device']);
    }

    public function setDeviceIdAttribute(?int $deviceId): void
    {
        $this->pendingDeviceId = $deviceId;
        $this->patchTraccarAppAttributes([TraccarAppFields::KEY_GEOFENCE_DEVICE_ID => $deviceId]);
    }

    private ?int $pendingDeviceId = null;

    protected static function booted(): void
    {
        static::saving(function (Geofence $geofence) {
            if (TraccarSchema::hasColumn($geofence->getTable(), 'attributes')
                && $geofence->getTraccarAttributesJson() === null) {
                $geofence->setTraccarAttributesJson(TraccarAttributes::encode([]));
            }
        });

        static::saved(function (Geofence $geofence) {
            if ($geofence->pendingDeviceId === null) {
                return;
            }

            $deviceKeys = TraccarSchema::deviceGeofencePivotKeys();
            $userTable = config('traccar.tables.user_geofence', 'tc_user_geofence');

            DB::table($deviceKeys['table'])->where($deviceKeys['geofence'], $geofence->id)->delete();

            if ($geofence->pendingDeviceId) {
                DB::table($deviceKeys['table'])->insert([
                    $deviceKeys['device'] => $geofence->pendingDeviceId,
                    $deviceKeys['geofence'] => $geofence->id,
                ]);

                $device = Device::query()->with('user')->find($geofence->pendingDeviceId);
                $ownerId = $device?->user_id;

                if ($ownerId && \Illuminate\Support\Facades\Schema::hasTable($userTable)) {
                    $userCol = TraccarSchema::resolveColumn($userTable, 'userid') ?? 'userid';
                    DB::table($userTable)->updateOrInsert(
                        [$userCol => $ownerId, $deviceKeys['geofence'] => $geofence->id],
                        []
                    );
                }
            }

            $geofence->pendingDeviceId = null;
        });
    }
}
