<?php

namespace App\Models;

use App\Models\ClientDevice;
use App\Models\Concerns\HasTraccarUserAssignment;
use App\Models\Concerns\UsesTcTable;
use App\Services\Traccar\TraccarDeviceAccessService;
use App\Services\Tracking\DeviceFuelService;
use App\Services\Tracking\DeviceOdometerService;
use App\Support\Traccar\TraccarAppFields;
use App\Support\Traccar\TraccarAttributes;
use App\Support\Traccar\TraccarSchema;
use App\Support\VehicleIcons\VehicleIconLibrary;
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

    /** Vehicle using the tracker (separate from GPS hardware device type). @deprecated use VehicleIconLibrary::defaultTypes() */
    public const VEHICLE_TYPES = [
        'car' => 'Car',
        'suv' => 'SUV',
        'truck' => 'Truck',
        'van' => 'Van',
        'bus' => 'Bus',
        'motorcycle' => 'Motorcycle',
        'taxi' => 'Taxi',
        'ambulance' => 'Ambulance',
        'police' => 'Police',
        'fire_truck' => 'Fire Truck',
        'tractor' => 'Tractor',
        'crane' => 'Crane',
        'boat' => 'Boat',
        'bicycle' => 'Bicycle',
        'pickup' => 'Pickup',
        'trailer' => 'Trailer',
        'other' => 'Other',
    ];

    public const MAP_ICON_SOURCES = [
        'default' => 'Default library icon',
        'custom' => 'Custom uploaded icon',
    ];

    public const MAP_MARKER_STYLES = [
        'labeled' => 'Labeled vehicle',
        'body' => 'Vehicle body',
        'pin' => 'Status pin',
    ];

    /** @deprecated Prefer VehicleIconLibrary::sizeScales() keys (percentage presets). */
    public const MAP_MARKER_SIZES = [
        '50' => '50%',
        '75' => '75%',
        '100' => '100%',
        '125' => '125%',
        '150' => '150%',
        '200' => '200%',
    ];

    public const DEFAULT_MAP_MARKER_SIZE = '100';

    public const DEFAULT_MAP_MARKER_STYLE = 'pin';

    public const DEFAULT_MAP_ICON_SOURCE = 'default';

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

    /**
     * All users linked to this device (Traccar many-to-many).
     *
     * @return list<int>
     */
    public function getUserIdsAttribute(): array
    {
        return $this->resolveTraccarOwnerUserIds();
    }

    /**
     * Assign one vehicle to multiple users (synced to tc_user_device on save).
     *
     * @param  array<int|string>|null  $userIds
     */
    public function setUserIdsAttribute($userIds): void
    {
        if ($userIds === null) {
            $this->pendingUserIds = null;

            return;
        }

        $this->pendingUserIds = collect((array) $userIds)
            ->map(fn ($id) => (int) $id)
            ->filter(fn (int $id) => $id > 0)
            ->unique()
            ->values()
            ->all();
    }

    private ?int $pendingUserId = null;

    /** @var list<int>|null */
    private ?array $pendingUserIds = null;

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
            if (! $device->id) {
                return;
            }

            // Multi-user assignment (Traccar parity): sync the full set of linked users.
            if ($device->pendingUserIds !== null) {
                $linker = app(\App\Services\Traccar\TraccarUserDeviceLinker::class);
                $linker->removeForDevice((int) $device->id);

                foreach ($device->pendingUserIds as $userId) {
                    $linker->upsert((int) $userId, (int) $device->id);
                }

                $device->pendingUserIds = null;
                $device->pendingUserId = null;

                return;
            }

            // Legacy single-owner path (kept for existing callers).
            if ($device->pendingUserId === null) {
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
        return $this->hasOne(Subscription::class, 'device_id')->latestOfMany();
    }

    public function subscriptions()
    {
        return $this->hasMany(Subscription::class, 'device_id')->orderByDesc('id');
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
        $normalized = is_string($value) ? trim($value) : null;
        if ($normalized === '') {
            $normalized = null;
        }

        // Map-icon picker ids (shared_*) must never overwrite install-time body type.
        if ($normalized !== null && ! isset(self::VEHICLE_TYPES[$normalized])) {
            $normalized = self::guessBodyTypeFromMapIconKey($normalized);
        }

        $this->patchTraccarAppAttributes([
            TraccarAppFields::KEY_VEHICLE_TYPE => $normalized,
        ]);
    }

    public function odometerBaselineKm(): ?float
    {
        return app(DeviceOdometerService::class)->baselineKm($this);
    }

    public function odometerDisplayKm($reportedOdometerMeters = null): ?float
    {
        // DeviceOdometerService resolves positions via Traccar PositionReader (tc_positions).
        return app(DeviceOdometerService::class)->displayKm($this, $reportedOdometerMeters);
    }

    public function fuelConsumptionLPer100km(): ?float
    {
        return app(DeviceFuelService::class)->consumptionLPer100km($this);
    }

    public function fuelEfficiencyUnit(): string
    {
        return app(DeviceFuelService::class)->efficiencyUnit($this);
    }

    public function fuelTankCapacityL(): ?float
    {
        return app(DeviceFuelService::class)->tankCapacityL($this);
    }

    public function fuelSensorUnit(): string
    {
        return app(DeviceFuelService::class)->sensorUnit($this);
    }

    public function getMapMarkerStyleAttribute(): string
    {
        $value = TraccarAppFields::get(
            $this->getTraccarAttributesJson(),
            TraccarAppFields::KEY_MAP_MARKER_STYLE
        );

        return is_string($value) && isset(self::MAP_MARKER_STYLES[$value])
            ? $value
            : self::DEFAULT_MAP_MARKER_STYLE;
    }

    public function setMapMarkerStyleAttribute(?string $value): void
    {
        $this->patchTraccarAppAttributes([
            TraccarAppFields::KEY_MAP_MARKER_STYLE => isset(self::MAP_MARKER_STYLES[(string) $value])
                ? $value
                : null,
        ]);
    }

    public function getMapMarkerSizeAttribute(): string
    {
        $value = TraccarAppFields::get(
            $this->getTraccarAttributesJson(),
            TraccarAppFields::KEY_MAP_MARKER_SIZE
        );

        return VehicleIconLibrary::normalizeSizeKey(is_string($value) ? $value : null);
    }

    public function setMapMarkerSizeAttribute(?string $value): void
    {
        $normalized = VehicleIconLibrary::normalizeSizeKey($value);
        $this->patchTraccarAppAttributes([
            TraccarAppFields::KEY_MAP_MARKER_SIZE => $normalized,
        ]);
    }

    public function getMapIconSourceAttribute(): string
    {
        $value = TraccarAppFields::get(
            $this->getTraccarAttributesJson(),
            TraccarAppFields::KEY_MAP_ICON_SOURCE
        );

        return is_string($value) && isset(self::MAP_ICON_SOURCES[$value])
            ? $value
            : self::DEFAULT_MAP_ICON_SOURCE;
    }

    public function setMapIconSourceAttribute(?string $value): void
    {
        $this->patchTraccarAppAttributes([
            TraccarAppFields::KEY_MAP_ICON_SOURCE => isset(self::MAP_ICON_SOURCES[(string) $value])
                ? $value
                : null,
        ]);
    }

    public function getMapCustomIconAttribute(): ?string
    {
        $path = TraccarAppFields::get(
            $this->getTraccarAttributesJson(),
            TraccarAppFields::KEY_MAP_CUSTOM_ICON
        );

        return is_string($path) && $path !== '' ? $path : null;
    }

    public function setMapCustomIconAttribute(?string $value): void
    {
        $this->patchTraccarAppAttributes([
            TraccarAppFields::KEY_MAP_CUSTOM_ICON => is_string($value) && $value !== '' ? $value : null,
        ]);
    }

    public function getMapBuiltinIconPathAttribute(): ?string
    {
        $path = TraccarAppFields::get(
            $this->getTraccarAttributesJson(),
            TraccarAppFields::KEY_MAP_BUILTIN_ICON
        );

        return is_string($path) && $path !== '' ? $path : null;
    }

    public function setMapBuiltinIconPathAttribute(?string $value): void
    {
        $this->patchTraccarAppAttributes([
            TraccarAppFields::KEY_MAP_BUILTIN_ICON => is_string($value) && $value !== '' ? $value : null,
        ]);
    }

    public function resolvedBuiltinIconPath(): string
    {
        $fallback = \App\Support\VehicleIcons\BuiltinMapIconStorage::relativePathForType('car');
        $stored = $this->map_builtin_icon_path;

        if (\App\Support\VehicleIcons\SharedMapIconStorage::isValidRelativePath($stored)) {
            return (string) $stored;
        }

        if (\App\Support\VehicleIcons\SharedMapIconStorage::isSharedType($this->vehicle_type)) {
            $shared = \App\Support\VehicleIcons\SharedMapIconStorage::findByType($this->vehicle_type);
            if ($shared && \App\Support\VehicleIcons\SharedMapIconStorage::isValidRelativePath($shared->relative_path)) {
                return $shared->relative_path;
            }

            // Shared icon deleted / file missing — never leave the map blank.
            return $fallback;
        }

        $resolved = \App\Support\VehicleIcons\BuiltinMapIconStorage::resolveRelativePath(
            $stored,
            $this->vehicle_type
        );

        if (\App\Support\VehicleIcons\BuiltinMapIconStorage::isValidRelativePath($resolved)) {
            return $resolved;
        }

        return $fallback;
    }

    public function resolvedBuiltinIconUrl(): string
    {
        $path = $this->resolvedBuiltinIconPath();
        if (\App\Support\VehicleIcons\VehicleIconLibrary::isValidIconPath($path)) {
            return \App\Support\VehicleIcons\VehicleIconLibrary::urlForIconPath($path);
        }

        return \App\Support\VehicleIcons\VehicleIconLibrary::builtinUrlFor('car');
    }

    /** Guaranteed map icon URL used when custom/shared assets are missing. */
    public function fallbackMapIconUrl(): string
    {
        return \App\Support\VehicleIcons\VehicleIconLibrary::builtinUrlFor('car');
    }

    /** List/table thumb: uploaded custom icon when present, otherwise the real default map icon. */
    public function listMapIconUrl(): string
    {
        if ($this->usesCustomMapIcon()) {
            $url = app(\App\Services\Tracking\DeviceVehicleIconService::class)->url($this);
            if (is_string($url) && $url !== '') {
                return $url;
            }
        }

        // Prefer root-relative builtin assets so list thumbs match the live map
        // and never depend on a generated/placeholder image.
        $path = $this->resolvedBuiltinIconPath();
        if (\App\Support\VehicleIcons\BuiltinMapIconStorage::isValidRelativePath($path)) {
            $relative = \App\Support\VehicleIcons\BuiltinMapIconStorage::publicRoot().'/'.ltrim($path, '/');
            $url = '/'.trim(str_replace('\\', '/', $relative), '/');
            $absolute = \App\Support\VehicleIcons\BuiltinMapIconStorage::absolutePath($path);
            if (is_file($absolute)) {
                $url .= '?v='.filemtime($absolute);
            }

            return $url;
        }

        if (\App\Support\VehicleIcons\SharedMapIconStorage::isValidRelativePath($path)) {
            return \App\Support\VehicleIcons\SharedMapIconStorage::urlForRelativePath($path);
        }

        return '/icons/builtin/Vehicles/car.svg';
    }

    public function getMapIconRotationEnabledAttribute(): bool
    {
        $value = TraccarAppFields::get(
            $this->getTraccarAttributesJson(),
            TraccarAppFields::KEY_MAP_ICON_ROTATION
        );

        if ($value === null || $value === '') {
            return true;
        }

        return filter_var($value, FILTER_VALIDATE_BOOLEAN);
    }

    public function setMapIconRotationEnabledAttribute(mixed $value): void
    {
        $this->patchTraccarAppAttributes([
            TraccarAppFields::KEY_MAP_ICON_ROTATION => filter_var($value, FILTER_VALIDATE_BOOLEAN) ? '1' : '0',
        ]);
    }

    public function getMapIconRotationOffsetAttribute(): int
    {
        $value = TraccarAppFields::get(
            $this->getTraccarAttributesJson(),
            TraccarAppFields::KEY_MAP_ICON_ROTATION_OFFSET
        );

        if ($value === null || $value === '') {
            return $this->resolvedIconRotationOffset();
        }

        return self::normalizeRotationOffsetDegrees($value);
    }

    public function setMapIconRotationOffsetAttribute(mixed $value): void
    {
        $this->patchTraccarAppAttributes([
            TraccarAppFields::KEY_MAP_ICON_ROTATION_OFFSET => (string) self::normalizeRotationOffsetDegrees($value),
        ]);
    }

    /**
     * Degrees added to GPS heading so the selected icon nose points forward.
     * Prefer the device-saved offset (Change Icon “nose direction”), then the
     * shared library catalog offset, then 0 for built-in nose-up artwork.
     */
    public function resolvedIconRotationOffset(): int
    {
        $stored = TraccarAppFields::get(
            $this->getTraccarAttributesJson(),
            TraccarAppFields::KEY_MAP_ICON_ROTATION_OFFSET
        );
        if ($stored !== null && $stored !== '') {
            return self::normalizeRotationOffsetDegrees($stored);
        }

        if (\App\Support\VehicleIcons\SharedMapIconStorage::isSharedType($this->vehicle_type)) {
            $shared = \App\Support\VehicleIcons\SharedMapIconStorage::findByType($this->vehicle_type);
            if ($shared) {
                return $shared->signedRotationOffset();
            }

            // SVG Repo–style shared side views face East when catalog row is missing.
            return -90;
        }

        $path = $this->map_builtin_icon_path;
        if (\App\Support\VehicleIcons\SharedMapIconStorage::isValidRelativePath($path)) {
            $shared = \App\Support\VehicleIcons\SharedMapIconStorage::findByRelativePath($path);
            if ($shared) {
                return $shared->signedRotationOffset();
            }

            return -90;
        }

        // Built-in top-down icons face North (nose up).
        return 0;
    }

    public static function normalizeRotationOffsetDegrees(mixed $value): int
    {
        $raw = (int) $value;
        $raw = (($raw % 360) + 360) % 360;
        if ($raw > 180) {
            $raw -= 360;
        }

        return $raw;
    }

    public function mapMarkerSizeScale(): float
    {
        return VehicleIconLibrary::scaleForSize($this->map_marker_size);
    }

    public function defaultMapIconName(): string
    {
        $path = $this->map_builtin_icon_path;
        if (\App\Support\VehicleIcons\SharedMapIconStorage::isValidRelativePath($path)) {
            $shared = \App\Support\VehicleIcons\SharedMapIconStorage::findByRelativePath($path);
            if ($shared) {
                return $shared->registryId();
            }
        }

        $type = $this->vehicle_type;

        return VehicleIconLibrary::isValidDefaultType($type) ? $type : 'car';
    }

    public function usesCustomMapIcon(): bool
    {
        if ($this->map_icon_source !== 'custom'
            || ! is_string($this->map_custom_icon)
            || $this->map_custom_icon === '') {
            return false;
        }

        // Missing/deleted upload files must not hide the selected library icon.
        $url = app(\App\Services\Tracking\DeviceVehicleIconService::class)->url($this);

        return is_string($url) && $url !== '';
    }

    /**
     * @return array<string, mixed>
     */
    public function mapAppearancePayload(): array
    {
        $iconService = app(\App\Services\Tracking\DeviceVehicleIconService::class);

        $usesCustom = $this->usesCustomMapIcon();
        $fallbackUrl = $this->fallbackMapIconUrl();
        $builtinPath = $this->resolvedBuiltinIconPath();
        $builtinUrl = $this->resolvedBuiltinIconUrl() ?: $fallbackUrl;

        // Prefer a real vehicle type the renderer understands; missing shared types → car.
        $vehicleType = $this->defaultMapIconName();
        if (\App\Support\VehicleIcons\SharedMapIconStorage::isSharedType($vehicleType)
            && ! \App\Support\VehicleIcons\SharedMapIconStorage::isValidRelativePath($builtinPath)) {
            $vehicleType = 'car';
        }

        return [
            'vehicle_type' => $vehicleType,
            // Always expose a resolvable library URL so maps never render blank markers.
            'map_builtin_icon_path' => $usesCustom ? 'Vehicles/car.svg' : $builtinPath,
            'map_builtin_icon_url' => $usesCustom ? $fallbackUrl : $builtinUrl,
            'map_fallback_icon_url' => $fallbackUrl,
            'map_icon_source' => $usesCustom ? 'custom' : 'default',
            'map_custom_icon_url' => $usesCustom ? $iconService->url($this) : null,
            // Built-in / custom library icons use body markers (center-anchored).
            // Status pin only for the explicit pin_marker type.
            'map_marker_style' => $usesCustom
                ? 'body'
                : ($vehicleType === 'pin_marker'
                    ? 'pin'
                    : ($this->map_marker_style === 'labeled' ? 'labeled' : 'body')),
            'map_marker_size' => $this->map_marker_size,
            'map_marker_size_scale' => $this->mapMarkerSizeScale(),
            'map_icon_rotation_enabled' => $this->map_icon_rotation_enabled,
            'map_icon_rotation_offset' => $this->resolvedIconRotationOffset(),
            'map_icon_anchor_x' => 0.5,
            'map_icon_anchor_y' => 0.5,
        ];
    }

    public function getDriverNameAttribute(): ?string
    {
        return TraccarAppFields::get($this->getTraccarAttributesJson(), TraccarAppFields::KEY_DRIVER_NAME);
    }

    public function setDriverNameAttribute(?string $value): void
    {
        $this->patchTraccarAppAttributes([TraccarAppFields::KEY_DRIVER_NAME => $value ?: null]);
    }

    public function getDriverContactAttribute(): ?string
    {
        $raw = TraccarAppFields::get($this->getTraccarAttributesJson(), TraccarAppFields::KEY_DRIVER_CONTACT);

        return self::normalizeDriverContact($raw);
    }

    public function setDriverContactAttribute(?string $value): void
    {
        $this->patchTraccarAppAttributes([TraccarAppFields::KEY_DRIVER_CONTACT => $value ?: null]);
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
            TraccarAppFields::KEY_MAP_MARKER_STYLE => $this->map_marker_style,
            TraccarAppFields::KEY_MAP_MARKER_SIZE => $this->map_marker_size,
            TraccarAppFields::KEY_MAP_ICON_SOURCE => $this->map_icon_source,
            TraccarAppFields::KEY_MAP_CUSTOM_ICON => $this->map_custom_icon,
            TraccarAppFields::KEY_MAP_BUILTIN_ICON => $this->map_builtin_icon_path,
            TraccarAppFields::KEY_MAP_ICON_ROTATION => $this->map_icon_rotation_enabled ? '1' : '0',
            TraccarAppFields::KEY_DRIVER_NAME => $this->driver_name,
            TraccarAppFields::KEY_DRIVER_CONTACT => $this->driver_contact,
            TraccarAppFields::KEY_SIM_TYPE => $this->sim_type,
            TraccarAppFields::KEY_SIM_NUMBER => $this->sim_number,
            TraccarAppFields::KEY_PLATE_TYPE => $this->plate_type,
            TraccarAppFields::KEY_ODOMETER_BASE_KM => $this->odometerBaselineKm(),
            TraccarAppFields::KEY_ODOMETER_BASE_SET_AT => TraccarAppFields::get(
                $this->getTraccarAttributesJson(),
                TraccarAppFields::KEY_ODOMETER_BASE_SET_AT
            ),
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

    /** Driver name for map/fleet UI, or null when unset. */
    public function driverDisplayName(): ?string
    {
        $name = trim((string) ($this->driver_name ?? ''));

        return $name !== '' ? $name : null;
    }

    /** Driver contact number for display, or null when unset. */
    public function driverContactNumber(): ?string
    {
        $contact = $this->driver_contact;

        return $contact !== null && $contact !== '' ? $contact : null;
    }

    /**
     * Normalize driver contact from plain text or Traccar driver attributes JSON.
     */
    public static function normalizeDriverContact(mixed $value): ?string
    {
        if ($value === null) {
            return null;
        }

        $contact = trim((string) $value);
        if ($contact === '') {
            return null;
        }

        if (str_starts_with($contact, '{') || str_starts_with($contact, '[')) {
            $decoded = json_decode($contact, true);
            if (is_array($decoded)) {
                $phone = trim((string) ($decoded['phone'] ?? $decoded['contact'] ?? $decoded['mobile'] ?? ''));

                return $phone !== '' ? $phone : null;
            }
        }

        return $contact;
    }

    /** Sanitized phone for tel: links (keeps leading + and digits only). */
    public function driverContactTel(): ?string
    {
        $contact = $this->driverContactNumber();
        if ($contact === null) {
            return null;
        }

        $tel = preg_replace('/[^\d+]/', '', $contact);
        $tel = preg_replace('/(?!^)\+/', '', (string) $tel);

        return $tel === '' ? null : $tel;
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
        $type = $this->vehicle_type;

        // Map-icon ids (shared_*) must not appear as the install-time vehicle body type.
        if (\App\Support\VehicleIcons\SharedMapIconStorage::isSharedType($type)
            || ($type && ! isset(self::VEHICLE_TYPES[$type]))) {
            $guess = self::guessBodyTypeFromMapIconKey($type);
            if ($guess !== null) {
                return $this->typeLabelFor($guess, self::VEHICLE_TYPES, 'vehicle_type');
            }
        }

        return $this->typeLabelFor($type, self::VEHICLE_TYPES, 'vehicle_type');
    }

    /**
     * Infer a predefined body type (car/truck/…) from a map-icon key or shared slug.
     */
    public static function guessBodyTypeFromMapIconKey(?string $key): ?string
    {
        $raw = strtolower(trim((string) $key));
        if ($raw === '') {
            return null;
        }

        $raw = preg_replace('/^shared_/', '', $raw) ?? $raw;
        $raw = str_replace(['-', 'svgrepo', 'com'], ['_', '', ''], $raw);
        $raw = preg_replace('/_+/', '_', $raw) ?? $raw;

        foreach (array_keys(self::VEHICLE_TYPES) as $type) {
            if ($type === 'other') {
                continue;
            }
            if (str_contains($raw, $type)) {
                return $type;
            }
        }

        if (str_contains($raw, 'bike') || str_contains($raw, 'bicycle')) {
            return 'bicycle';
        }
        if (str_contains($raw, 'moto')) {
            return 'motorcycle';
        }
        if (str_contains($raw, 'bus')) {
            return 'bus';
        }

        return null;
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
