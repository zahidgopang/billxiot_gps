<?php

namespace App\Models;

use App\Models\ClientDevice;
use App\Models\Concerns\HasTraccarUserAssignment;
use App\Models\Concerns\UsesTcTable;
use App\Services\Traccar\TraccarDeviceAccessService;
use App\Support\Traccar\TraccarAppFields;
use App\Support\Traccar\TraccarAttributes;
use App\Support\Traccar\TraccarSchema;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

/**
 * GPS device — tc_devices (same table as Traccar).
 */
class Device extends Model
{
    use HasFactory, HasTraccarUserAssignment, UsesTcTable;

    public $timestamps = false;

    /** Common GPS hardware / tracker unit types (not vehicle body type). */
    public const DEVICE_TYPES = [
        'gps_tracker' => 'GPS device',
        'obd' => 'OBD-II Tracker',
        'hardwired' => 'Hardwired GPS',
        'portable' => 'Portable GPS',
        'asset' => 'Asset Tracker',
        'personal' => 'Personal GPS',
        'motorcycle' => 'Motorcycle GPS',
        'dashcam' => 'Dashcam GPS',
        'telematics' => 'Telematics Unit',
        'satellite' => 'Satellite Tracker',
        'other' => 'Other',
    ];

    /** @var array<string, string> Legacy vehicle-style categories → new device types */
    public const LEGACY_DEVICE_TYPE_MAP = [
        'car' => 'gps_tracker',
        'truck' => 'telematics',
        'bike' => 'motorcycle',
        'personal' => 'personal',
    ];

    /**
     * Normalize stored category / legacy values to a DEVICE_TYPES key.
     */
    public static function canonicalDeviceType(?string $type): ?string
    {
        if ($type === null || $type === '') {
            return null;
        }

        $type = strtolower(trim($type));

        if (isset(self::LEGACY_DEVICE_TYPE_MAP[$type])) {
            return self::LEGACY_DEVICE_TYPE_MAP[$type];
        }

        return $type;
    }

    /** SIM card types used in GPS trackers. */
    public const SIM_TYPES = [
        'standard' => 'Standard SIM',
        'micro' => 'Micro SIM',
        'nano' => 'Nano SIM',
        'esim' => 'eSIM',
        'other' => 'Other',
    ];

    /** License / registration plate category. */
    public const PLATE_TYPES = [
        'public_transfer' => 'Public Transfer',
        'private_transfer' => 'Private Transfer',
    ];

    /** Vehicle using the tracker (separate from GPS hardware device type). */
    public const VEHICLE_TYPES = [
        'car' => 'Car',
        'suv' => 'SUV',
        'truck' => 'Truck',
        'van' => 'Van',
        'bus' => 'Bus',
        'pickup' => 'Pickup',
        'motorcycle' => 'Motorcycle',
        'trailer' => 'Trailer',
        'other' => 'Other',
    ];

    protected $guarded = [];

    public function getTable(): string
    {
        return config('traccar.tables.devices', 'tc_devices');
    }

    public function getImeiAttribute(): ?string
    {
        return $this->attributes['uniqueid'] ?? null;
    }

    public function setImeiAttribute(?string $value): void
    {
        $this->attributes['uniqueid'] = $value;
    }

    public function getDeviceTypeAttribute(): ?string
    {
        return $this->attributes['category']
            ?? TraccarAppFields::get($this->getTraccarAttributesJson(), TraccarAppFields::KEY_DEVICE_TYPE);
    }

    public function setDeviceTypeAttribute(?string $value): void
    {
        $this->attributes['category'] = $value;
        $this->patchTraccarAppAttributes([TraccarAppFields::KEY_DEVICE_TYPE => $value]);
    }

    public function getDescriptionAttribute(): ?string
    {
        return $this->attributes['contact']
            ?? TraccarAppFields::get($this->getTraccarAttributesJson(), TraccarAppFields::KEY_DEVICE_DESC);
    }

    public function setDescriptionAttribute(?string $value): void
    {
        $this->attributes['contact'] = $value;
        $this->patchTraccarAppAttributes([TraccarAppFields::KEY_DEVICE_DESC => $value]);
    }

    public function getStatusAttribute(): string
    {
        return (string) TraccarAppFields::get(
            $this->getTraccarAttributesJson(),
            TraccarAppFields::KEY_DEVICE_STATUS,
            ((int) ($this->attributes['disabled'] ?? 0) === 1) ? 'blocked' : 'active'
        );
    }

    public function setStatusAttribute(string $value): void
    {
        $this->attributes['disabled'] = in_array($value, ['blocked', 'inactive'], true) ? 1 : 0;
        $this->patchTraccarAppAttributes([TraccarAppFields::KEY_DEVICE_STATUS => $value]);
    }

    public function getUserIdAttribute(): ?int
    {
        return $this->resolveTraccarOwnerUserId();
    }

    public function setUserIdAttribute(?int $userId): void
    {
        $this->pendingUserId = $userId;
    }

    private ?int $pendingUserId = null;

    protected static function booted(): void
    {
        static::saving(function (Device $device) {
            if (! TraccarSchema::hasColumn($device->getTable(), 'attributes')) {
                return;
            }

            if ($device->getTraccarAttributesJson() === null) {
                $device->setTraccarAttributesJson(TraccarAttributes::encode([]));
            }

            $device->setTraccarAttributesJson(
                TraccarAttributes::encode($device->traccarSyncAttributes())
            );
        });

        static::saved(function (Device $device) {
            if ($device->pendingUserId === null || ! $device->id) {
                return;
            }

            $linker = app(\App\Services\Traccar\TraccarUserDeviceLinker::class);
            $linker->removeForDevice((int) $device->id);

            if ($device->pendingUserId) {
                $linker->upsert((int) $device->pendingUserId, (int) $device->id);
            }

            $device->pendingUserId = null;
        });

        static::deleting(function (Device $device) {
            if (! $device->id) {
                return;
            }

            ClientDevice::query()->where('device_id', $device->id)->delete();

            app(\App\Services\Traccar\TraccarUserDeviceLinker::class)->removeForDevice((int) $device->id);

            $deviceGeofence = config('traccar.tables.device_geofence', 'tc_device_geofence');
            if (\Illuminate\Support\Facades\Schema::hasTable($deviceGeofence)) {
                $geofenceCol = \App\Support\Traccar\TraccarSchema::resolveColumn($deviceGeofence, 'geofenceid') ?? 'geofenceid';
                $deviceCol = \App\Support\Traccar\TraccarSchema::resolveColumn($deviceGeofence, 'deviceid') ?? 'deviceid';
                DB::table($deviceGeofence)->where($deviceCol, $device->id)->delete();
            }
        });
    }

    public function latestLocation()
    {
        return $this->hasOne(DeviceLocation::class)->latestOfMany('recorded_at');
    }

    public function subscription()
    {
        return $this->hasOne(Subscription::class, 'device_id');
    }

    public function getVehicleNameAttribute(): ?string
    {
        return TraccarAppFields::get($this->getTraccarAttributesJson(), TraccarAppFields::KEY_VEHICLE_NAME);
    }

    public function setVehicleNameAttribute(?string $value): void
    {
        $this->patchTraccarAppAttributes([TraccarAppFields::KEY_VEHICLE_NAME => $value ?: null]);
    }

    public function getVehicleNumberAttribute(): ?string
    {
        return TraccarAppFields::get($this->getTraccarAttributesJson(), TraccarAppFields::KEY_VEHICLE_NUMBER);
    }

    public function setVehicleNumberAttribute(?string $value): void
    {
        $this->patchTraccarAppAttributes([TraccarAppFields::KEY_VEHICLE_NUMBER => $value ?: null]);
    }

    public function getVehicleModelAttribute(): ?string
    {
        return TraccarAppFields::get($this->getTraccarAttributesJson(), TraccarAppFields::KEY_VEHICLE_MODEL);
    }

    public function setVehicleModelAttribute(?string $value): void
    {
        $this->patchTraccarAppAttributes([TraccarAppFields::KEY_VEHICLE_MODEL => $value ?: null]);
    }

    public function getVehicleTypeAttribute(): ?string
    {
        return TraccarAppFields::get($this->getTraccarAttributesJson(), TraccarAppFields::KEY_VEHICLE_TYPE);
    }

    public function setVehicleTypeAttribute(?string $value): void
    {
        $this->patchTraccarAppAttributes([TraccarAppFields::KEY_VEHICLE_TYPE => $value ?: null]);
    }

    public function getSimTypeAttribute(): ?string
    {
        return TraccarAppFields::get($this->getTraccarAttributesJson(), TraccarAppFields::KEY_SIM_TYPE);
    }

    public function setSimTypeAttribute(?string $value): void
    {
        $this->patchTraccarAppAttributes([TraccarAppFields::KEY_SIM_TYPE => $value ?: null]);
    }

    public function getSimNumberAttribute(): ?string
    {
        return TraccarAppFields::get($this->getTraccarAttributesJson(), TraccarAppFields::KEY_SIM_NUMBER);
    }

    public function setSimNumberAttribute(?string $value): void
    {
        $this->patchTraccarAppAttributes([TraccarAppFields::KEY_SIM_NUMBER => $value ?: null]);
    }

    public function getPlateTypeAttribute(): ?string
    {
        return TraccarAppFields::get($this->getTraccarAttributesJson(), TraccarAppFields::KEY_PLATE_TYPE);
    }

    public function setPlateTypeAttribute(?string $value): void
    {
        $this->patchTraccarAppAttributes([TraccarAppFields::KEY_PLATE_TYPE => $value ?: null]);
    }

    public function simTypeLabel(): string
    {
        return $this->typeLabelFor($this->sim_type, self::SIM_TYPES, 'sim_type');
    }

    public function plateTypeLabel(): string
    {
        return $this->typeLabelFor($this->plate_type, self::PLATE_TYPES, 'plate_type');
    }

    /**
     * Full Laravel app fields stored in tc_devices.attributes (merged on Traccar sync).
     *
     * @return array<string, mixed>
     */
    public function traccarSyncAttributes(): array
    {
        $merged = $this->traccarAppAttributes();

        $appFields = [
            TraccarAppFields::KEY_DEVICE_TYPE => $this->attributes['category'] ?? $this->device_type,
            TraccarAppFields::KEY_DEVICE_DESC => $this->attributes['contact'] ?? $this->description,
            TraccarAppFields::KEY_DEVICE_STATUS => $this->status,
            TraccarAppFields::KEY_VEHICLE_NAME => $this->vehicle_name,
            TraccarAppFields::KEY_VEHICLE_NUMBER => $this->vehicle_number,
            TraccarAppFields::KEY_VEHICLE_MODEL => $this->vehicle_model,
            TraccarAppFields::KEY_VEHICLE_TYPE => $this->vehicle_type,
            TraccarAppFields::KEY_SIM_TYPE => $this->sim_type,
            TraccarAppFields::KEY_SIM_NUMBER => $this->sim_number,
            TraccarAppFields::KEY_PLATE_TYPE => $this->plate_type,
        ];

        foreach ($appFields as $key => $value) {
            if ($value === null || $value === '') {
                unset($merged[$key]);
            } else {
                $merged[$key] = $value;
            }
        }

        return $merged;
    }

    public function deviceTypeLabel(): string
    {
        $type = $this->device_type;

        if ($type && isset(self::LEGACY_DEVICE_TYPE_MAP[$type])) {
            $type = self::LEGACY_DEVICE_TYPE_MAP[$type];
        }

        return $this->typeLabelFor($type, self::DEVICE_TYPES, 'device_type');
    }

    /**
     * Vehicle name for map and fleet UI (primary label with fallbacks).
     */
    public function vehicleDisplayName(): string
    {
        return $this->mapMarkerTitle();
    }

    public function vehiclePlateNumber(): ?string
    {
        $plate = trim((string) ($this->vehicle_number ?? ''));

        return $plate !== '' ? $plate : null;
    }

    /**
     * Primary line on map marker badge: vehicle name, else plate, else fallback.
     */
    public function mapMarkerTitle(): string
    {
        $name = trim((string) ($this->vehicle_name ?? ''));
        if ($name !== '') {
            return $name;
        }

        $plate = $this->vehiclePlateNumber();
        if ($plate !== null) {
            return $plate;
        }

        $label = trim((string) ($this->name ?? ''));

        return $label !== '' ? $label : 'Device';
    }

    /**
     * Secondary line on map marker badge: plate shown below vehicle name.
     */
    public function mapMarkerPlateLine(): ?string
    {
        $name = trim((string) ($this->vehicle_name ?? ''));
        if ($name === '') {
            return null;
        }

        return $this->vehiclePlateNumber();
    }

    /**
     * @deprecated Use mapMarkerTitle()
     */
    public function mapMarkerLabel(): string
    {
        return $this->mapMarkerTitle();
    }

    /**
     * Push notifications and alert lists: "Vehicle Name · Plate".
     */
    public function notificationDisplayName(): string
    {
        $name = trim((string) ($this->vehicle_name ?? ''));
        $plate = trim((string) ($this->vehicle_number ?? ''));

        if ($name !== '' && $plate !== '') {
            return $name.' · '.$plate;
        }

        if ($name !== '') {
            return $name;
        }

        if ($plate !== '') {
            return $plate;
        }

        return 'Vehicle';
    }

    public function deviceTypeIconClass(): string
    {
        $type = $this->device_type;
        if ($type && isset(self::LEGACY_DEVICE_TYPE_MAP[$type])) {
            $type = self::LEGACY_DEVICE_TYPE_MAP[$type];
        }

        return match ($type) {
            'obd' => 'fa-plug',
            'hardwired' => 'fa-bolt',
            'portable' => 'fa-suitcase-rolling',
            'asset' => 'fa-box',
            'personal' => 'fa-user',
            'motorcycle' => 'fa-motorcycle',
            'dashcam' => 'fa-video',
            'telematics' => 'fa-microchip',
            'satellite' => 'fa-satellite',
            'gps_tracker' => 'fa-satellite-dish',
            default => 'fa-location-crosshairs',
        };
    }

    public function vehicleTypeLabel(): string
    {
        return $this->typeLabelFor($this->vehicle_type, self::VEHICLE_TYPES, 'vehicle_type');
    }

    /**
     * Primary title on the live map (vehicle name, else plate, else device label).
     */
    public function mapDisplayTitle(): string
    {
        return $this->mapMarkerTitle();
    }

    /**
     * Secondary line under map title (plate number only).
     */
    public function mapNavSubtitle(): ?string
    {
        return $this->vehiclePlateNumber();
    }

    /**
     * Primary line for device lists: vehicle name, else plate, else device label.
     */
    public function listPrimaryLabel(): string
    {
        $name = trim((string) ($this->vehicle_name ?? ''));
        if ($name !== '') {
            return $name;
        }

        $plate = $this->vehiclePlateNumber();
        if ($plate !== null) {
            return $plate;
        }

        $label = trim((string) ($this->name ?? ''));

        return $label !== '' ? $label : 'Device';
    }

    /**
     * Secondary line for device lists: plate below vehicle name.
     */
    public function listSecondaryLabel(): ?string
    {
        $name = trim((string) ($this->vehicle_name ?? ''));
        if ($name === '') {
            return null;
        }

        return $this->vehiclePlateNumber();
    }

    /**
     * @param  array<string, string>  $types
     */
    private function typeLabelFor(?string $value, array $types, string $translationPrefix): string
    {
        if (! $value) {
            return '—';
        }

        $key = 'app.forms.'.$translationPrefix.'_'.$value;

        if (__($key) !== $key) {
            return __($key);
        }

        if (isset($types[$value])) {
            return $types[$value];
        }

        $canonical = $translationPrefix === 'device_type'
            ? self::canonicalDeviceType($value)
            : $value;

        if ($canonical !== null && isset($types[$canonical])) {
            return $types[$canonical];
        }

        return self::formatRawTypeLabel($value);
    }

    public static function formatRawTypeLabel(?string $value): string
    {
        if ($value === null || trim($value) === '') {
            return '—';
        }

        return ucwords(str_replace(['_', '-'], ' ', strtolower(trim($value))));
    }

    public function isAccountActive(): bool
    {
        return $this->status === 'active';
    }

    public function scopeForTrackerUser(Builder $query, User $user): Builder
    {
        return app(TraccarDeviceAccessService::class)->queryForUser($user);
    }

    /** Devices the user may see on maps/dashboards (tc_user_device + enabled + subscription). */
    public function scopeTrackableFor(Builder $query, User $user): Builder
    {
        return app(TraccarDeviceAccessService::class)->queryTrackableForUser($user);
    }

    public function scopeInTracker(Builder $query): Builder
    {
        $actor = auth()->user();

        return app(TraccarDeviceAccessService::class)->queryInTracker(
            $actor instanceof \App\Models\User ? $actor : null
        );
    }

    public static function normalizeImei(?string $imei): ?string
    {
        if ($imei === null) {
            return null;
        }

        $value = trim($imei);

        return $value === '' ? null : $value;
    }

    public static function normalizeVehicleNumber(?string $number): ?string
    {
        if ($number === null) {
            return null;
        }

        $value = trim($number);

        return $value === '' ? null : $value;
    }

    public static function isImeiTaken(string $imei, ?int $exceptDeviceId = null): bool
    {
        $normalized = static::normalizeImei($imei);

        if ($normalized === null) {
            return false;
        }

        $query = static::query()->whereImei($normalized);

        if ($exceptDeviceId !== null) {
            $query->where($query->getModel()->getQualifiedKeyName(), '!=', $exceptDeviceId);
        }

        return $query->exists();
    }

    public static function isVehicleNumberTaken(string $vehicleNumber, ?int $exceptDeviceId = null): bool
    {
        $normalized = static::normalizeVehicleNumber($vehicleNumber);

        if ($normalized === null) {
            return false;
        }

        $query = static::query()->whereVehicleNumber($normalized);

        if ($exceptDeviceId !== null) {
            $query->where($query->getModel()->getQualifiedKeyName(), '!=', $exceptDeviceId);
        }

        return $query->exists();
    }

    /** Match Traccar IMEI column (`uniqueid` on tc_devices). */
    public function scopeWhereImei(Builder $query, string $imei): Builder
    {
        $column = TraccarSchema::resolveColumn($query->getModel()->getTable(), 'uniqueid') ?? 'uniqueid';

        return $query->where($column, $imei);
    }

    /** Case-insensitive match on vehicle plate stored in Traccar attributes JSON. */
    public function scopeWhereVehicleNumber(Builder $query, string $vehicleNumber): Builder
    {
        $normalized = mb_strtolower(trim($vehicleNumber));
        $attrsCol = $query->getModel()->qualifyColumn('attributes');
        $path = '$.' . TraccarAppFields::KEY_VEHICLE_NUMBER;

        return $query->whereRaw(
            'LOWER(TRIM(JSON_UNQUOTE(JSON_EXTRACT(' . $attrsCol . ', ?)))) = ?',
            [$path, $normalized]
        )->whereRaw(
            'NULLIF(TRIM(JSON_UNQUOTE(JSON_EXTRACT(' . $attrsCol . ', ?))), \'\') IS NOT NULL',
            [$path]
        );
    }

    public function scopeWhereImeiLike(Builder $query, string $pattern): Builder
    {
        $column = TraccarSchema::resolveColumn($query->getModel()->getTable(), 'uniqueid') ?? 'uniqueid';

        return $query->where($column, 'like', $pattern);
    }

    /** id, name, and Traccar IMEI column for list/detail queries (use $device->imei in views). */
    public static function listSelectColumns(): array
    {
        $table = (new static)->getTable();
        $uniqueid = TraccarSchema::resolveColumn($table, 'uniqueid') ?? 'uniqueid';

        return ['id', 'name', $uniqueid];
    }

    public static function eagerListRelation(): string
    {
        return 'device:'.implode(',', static::listSelectColumns());
    }

    public function launchMapRoute(bool $fleet = false, ?string $panel = null): string
    {
        if ($fleet) {
            $panel ??= request()->routeIs('client.*') ? 'client' : 'admin';

            return route($panel . '.locations.launch-map', $this);
        }

        return route('user.devices.launch-map', $this);
    }
}
