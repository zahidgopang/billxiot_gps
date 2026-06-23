<?php

namespace App\Services\Traccar;

use App\Models\Device;
use App\Models\Geofence;
use App\Models\TraccarEntityMap;
use App\Models\User;
use App\Models\VehicleEvent;
use App\Support\Traccar\GeofenceWkt;
use App\Support\Traccar\TraccarAttributes;
use App\Support\Traccar\TraccarPassword;
use App\Support\Traccar\TraccarSchema;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

class TraccarSyncService
{
    public function __construct(
        private TraccarIdMap $idMap,
        private TraccarUserDeviceLinker $userDeviceLinker,
    ) {}

    public function syncDevice(Device $device): int
    {
        if (! TraccarSchema::isReady()) {
            throw new \RuntimeException('Traccar schema is not ready.');
        }

        $devicesTable = config('traccar.tables.devices', 'tc_devices');
        $existing = $this->validatedTraccarId($devicesTable, TraccarEntityMap::TYPE_DEVICE, $device->id);

        $existingAttrs = [];
        if ($existing) {
            $existingAttrs = TraccarAttributes::decode(
                (string) DB::table($devicesTable)->where('id', $existing)->value('attributes')
            );
        }

        $attributes = TraccarAttributes::encode(
            array_merge($existingAttrs, $device->traccarSyncAttributes())
        );

        $payload = [
            'name' => $device->name ?: $device->imei,
            'uniqueid' => $device->imei,
            'model' => $device->model,
            'category' => $device->device_type,
            'contact' => $device->description,
            'disabled' => $this->traccarDeviceDisabled($device) ? 1 : 0,
            'attributes' => $attributes,
        ];

        $payload = TraccarSchema::filterColumns($devicesTable, $payload);

        if ($existing) {
            DB::table($devicesTable)->where('id', $existing)->update($payload);
            $traccarId = $existing;
        } else {
            $byUnique = DB::table($devicesTable)->where('uniqueid', $device->imei)->value('id');

            if ($byUnique) {
                DB::table($devicesTable)->where('id', $byUnique)->update($payload);
                $traccarId = (int) $byUnique;
            } else {
                $traccarId = (int) DB::table($devicesTable)->insertGetId($payload);
            }

            $this->idMap->put(TraccarEntityMap::TYPE_DEVICE, $device->id, $traccarId);
        }

        $this->upsertUserDeviceLinkForDevice($device, $traccarId);

        return $traccarId;
    }

    /**
     * Remove Traccar user↔device permission only (tc_user_device).
     * Called when a Laravel device is deleted — never from geofence flows.
     * Does not delete tc_devices, tc_positions, or Laravel devices.
     */
    public function unlinkDevice(Device $device): void
    {
        $traccarId = $this->idMap->get(TraccarEntityMap::TYPE_DEVICE, $device->id);

        if (! $traccarId) {
            return;
        }

        $userDeviceTable = $this->userDeviceTable();

        if (Schema::hasTable($userDeviceTable)) {
            DB::table($userDeviceTable)->where('deviceid', $traccarId)->delete();
        }

        $this->idMap->forget(TraccarEntityMap::TYPE_DEVICE, $device->id);
    }

    /**
     * Remove Laravel user from Traccar: junctions + tc_users row + entity map.
     * Does not delete Laravel users or devices rows.
     */
    public function unlinkUser(User $user): void
    {
        if (! TraccarSchema::hasUsers()) {
            return;
        }

        $traccarUserId = $this->validatedTraccarId(
            config('traccar.tables.users', 'tc_users'),
            TraccarEntityMap::TYPE_USER,
            $user->id
        );

        if (! $traccarUserId) {
            $this->idMap->forget(TraccarEntityMap::TYPE_USER, $user->id);

            return;
        }

        $userDeviceTable = $this->userDeviceTable();
        if (Schema::hasTable($userDeviceTable)) {
            $userCol = TraccarSchema::resolveColumn($userDeviceTable, 'userid') ?? 'userid';
            DB::table($userDeviceTable)->where($userCol, $traccarUserId)->delete();
        }

        if (TraccarSchema::hasGeofences()) {
            $userGeofenceTable = config('traccar.tables.user_geofence', 'tc_user_geofence');
            if (Schema::hasTable($userGeofenceTable)) {
                $userCol = TraccarSchema::resolveColumn($userGeofenceTable, 'userid') ?? 'userid';
                DB::table($userGeofenceTable)->where($userCol, $traccarUserId)->delete();
            }
        }

        $usersTable = config('traccar.tables.users', 'tc_users');
        DB::table($usersTable)->where('id', $traccarUserId)->delete();

        $this->idMap->forget(TraccarEntityMap::TYPE_USER, $user->id);
    }

    /**
     * Rebuild tc_user_device from Laravel ownership (upsert only, never deletes geofence links).
     */
    public function repairUserDeviceLinks(?User $user = null): int
    {
        $userDeviceTable = $this->userDeviceTable();

        if (! Schema::hasTable($userDeviceTable) || ! TraccarSchema::isReady()) {
            return 0;
        }

        $count = 0;
        $query = $user ? User::query()->whereKey($user->id) : User::query();

        $query->orderBy('id')->each(function (User $owner) use (&$count) {
            $traccarUserId = $this->resolveTraccarUserIdForLink($owner, ensure: true);

            if (! $traccarUserId) {
                return;
            }

            $owner->loadMissing('devices');

            foreach ($owner->devices as $device) {
                $traccarDeviceId = $this->resolveTraccarDeviceIdForLink($device, ensure: true);

                if (! $traccarDeviceId) {
                    continue;
                }

                if ($this->upsertUserDeviceLink((int) $traccarUserId, (int) $traccarDeviceId)) {
                    $count++;
                }
            }
        });

        return $count;
    }

    /**
     * Remove traccar_entity_map rows whose tc_* row no longer exists (e.g. after Traccar UI delete).
     */
    public function pruneStaleEntityMaps(): int
    {
        if (! Schema::hasTable('traccar_entity_map')) {
            return 0;
        }

        $pruned = 0;
        $checks = [
            [TraccarEntityMap::TYPE_DEVICE, config('traccar.tables.devices', 'tc_devices')],
            [TraccarEntityMap::TYPE_USER, config('traccar.tables.users', 'tc_users')],
            [TraccarEntityMap::TYPE_GEOFENCE, config('traccar.tables.geofences', 'tc_geofences')],
        ];

        foreach ($checks as [$type, $table]) {
            if (! Schema::hasTable($table)) {
                continue;
            }

            TraccarEntityMap::query()
                ->where('entity_type', $type)
                ->orderBy('id')
                ->each(function (TraccarEntityMap $map) use ($table, $type, &$pruned) {
                    if (! $this->traccarRowExists($table, (int) $map->traccar_id)) {
                        $this->idMap->forget($type, (int) $map->laravel_id);
                        $pruned++;
                    }
                });
        }

        return $pruned;
    }

    /**
     * Sync Laravel user metadata to tc_users (not Laravel bcrypt).
     *
     * Traccar login uses PBKDF2 hex via {@see TraccarPassword} and default_user_password.
     *
     * @param  string|null  $plainPassword  Optional override (e.g. artisan command); never Laravel bcrypt.
     */
    public function syncUser(User $user, ?string $plainPassword = null): ?int
    {
        if (! config('traccar.sync_users', true) || ! TraccarSchema::hasUsers()) {
            return null;
        }

        $usersTable = config('traccar.tables.users', 'tc_users');
        $hashColumn = TraccarSchema::resolveColumn($usersTable, 'hashedPassword');
        $existing = $this->validatedTraccarId($usersTable, TraccarEntityMap::TYPE_USER, $user->id);
        $byEmail = DB::table($usersTable)->where('email', $user->email)->value('id');
        $traccarIdBeforeWrite = $existing ?: ($byEmail ? (int) $byEmail : null);
        $isNewTraccarUser = $traccarIdBeforeWrite === null;

        $existingHash = null;
        if ($traccarIdBeforeWrite && $hashColumn) {
            $existingHash = DB::table($usersTable)->where('id', $traccarIdBeforeWrite)->value($hashColumn);
        }

        $login = Str::lower($user->email);
        $passwordSynced = false;

        $payload = array_merge([
            'name' => $user->name,
            'email' => $user->email,
            'login' => $login,
        ], \App\Support\Traccar\TraccarUserPermissions::syncPayload($user));

        $traccarPlainPassword = $this->resolveTraccarPlainPassword(
            $existingHash,
            $isNewTraccarUser,
            $plainPassword
        );

        if ($traccarPlainPassword !== null) {
            $passwordSynced = $this->applyTraccarPassword($payload, $usersTable, $traccarPlainPassword);
        }

        $payload = TraccarSchema::filterColumns($usersTable, $payload);

        if ($existing) {
            $affected = DB::table($usersTable)->where('id', $existing)->update($payload);
            $this->logUserSync($user, $usersTable, $existing, 'update_mapped', $payload, $passwordSynced, $affected);

            return $existing;
        }

        if ($byEmail) {
            $traccarId = (int) $byEmail;
            $affected = DB::table($usersTable)->where('id', $traccarId)->update($payload);
            $this->logUserSync($user, $usersTable, $traccarId, 'update_by_email', $payload, $passwordSynced, $affected);
        } else {
            $traccarId = (int) DB::table($usersTable)->insertGetId($payload);
            $this->logUserSync($user, $usersTable, $traccarId, 'insert', $payload, $passwordSynced, 1);
        }

        $this->idMap->put(TraccarEntityMap::TYPE_USER, $user->id, $traccarId);

        $this->relinkUserDevices($user, $traccarId, ensureDevices: true);
        $this->relinkUserGeofences($user, ensureEntities: true);

        return $traccarId;
    }

    /**
     * Ensure tc_user_geofence / tc_device_geofence exist for all of the user's Laravel geofences.
     */
    public function relinkUserGeofences(User $user, bool $ensureEntities = false): void
    {
        if (! config('traccar.sync_geofences', true) || ! TraccarSchema::hasGeofences()) {
            return;
        }

        $user->loadMissing('devices');

        foreach ($user->devices as $device) {
            Geofence::query()
                ->where('device_id', $device->id)
                ->orderBy('id')
                ->each(function (Geofence $geofence) use ($ensureEntities) {
                    try {
                        $this->syncGeofence($geofence, $ensureEntities);
                    } catch (\Throwable $e) {
                        report($e);
                    }
                });
        }
    }

    /**
     * Sync geofence geometry + junction tables only.
     *
     * @param  bool|null  $ensureEntities  When true, creates/updates tc_devices & tc_users first (artisan backfill).
     *                                     Observers default false — only map + tc_geofences + junctions.
     */
    public function syncGeofence(Geofence $geofence, ?bool $ensureEntities = null): ?int
    {
        if (! config('traccar.sync_geofences', true) || ! TraccarSchema::hasGeofences()) {
            return null;
        }

        $geofence->loadMissing('device.user');

        if (! $geofence->device) {
            return null;
        }

        $device = $geofence->device;
        $ensureEntities ??= (bool) config('traccar.ensure_entities_on_geofence_sync', false);

        $traccarDeviceId = $ensureEntities
            ? $this->syncDevice($device)
            : $this->resolveTraccarDeviceId($device);

        if (! $traccarDeviceId) {
            logger()->warning('traccar.geofence.sync.skipped', [
                'laravel_geofence_id' => $geofence->id,
                'laravel_device_id' => $device->id,
                'reason' => 'missing_traccar_device_map',
            ]);

            return null;
        }

        $geofencesTable = config('traccar.tables.geofences', 'tc_geofences');
        $existing = $this->idMap->get(TraccarEntityMap::TYPE_GEOFENCE, $geofence->id);

        $area = GeofenceWkt::fromLaravel(
            $geofence->type,
            $geofence->coords,
            $geofence->center,
            $geofence->radius
        );

        $payload = [
            'name' => $geofence->name,
            'description' => $geofence->type,
            'area' => $area,
            'attributes' => TraccarAttributes::encode([
                'laravel_geofence_id' => $geofence->id,
                'type' => $geofence->type,
                'center' => $geofence->center ? json_decode($geofence->center, true) : null,
                'coords' => $geofence->coords ? json_decode($geofence->coords, true) : null,
                'radius' => $geofence->radius,
            ]),
        ];

        $payload = TraccarSchema::filterColumns($geofencesTable, $payload);

        if ($existing) {
            DB::table($geofencesTable)->where('id', $existing)->update($payload);
            $traccarGeofenceId = $existing;
        } else {
            $traccarGeofenceId = (int) DB::table($geofencesTable)->insertGetId($payload);
            $this->idMap->put(TraccarEntityMap::TYPE_GEOFENCE, $geofence->id, $traccarGeofenceId);
        }

        $this->linkGeofenceJunctions($device, $traccarGeofenceId, $traccarDeviceId);
        $this->preserveDeviceAccessAfterGeofenceChange($device, $traccarDeviceId);

        return $traccarGeofenceId;
    }

    /**
     * Remove a Laravel geofence from Traccar: junction rows first (scoped), then tc_geofences.
     * Never touches tc_devices, tc_user_device, Laravel devices, or positions.
     */
    public function removeGeofence(Geofence $geofence): void
    {
        if (! TraccarSchema::hasGeofences()) {
            return;
        }

        $ids = $this->resolveTraccarIdsForGeofence($geofence);

        if (! $ids['traccarGeofenceId']) {
            return;
        }

        if ($ids['traccarDeviceId'] !== null && $ids['traccarUserId'] !== null) {
            $this->detachGeofenceRelations(
                (int) $ids['traccarGeofenceId'],
                (int) $ids['traccarDeviceId'],
                (int) $ids['traccarUserId']
            );
        } else {
            logger()->warning('traccar.geofence.remove.partial_map', [
                'laravel_geofence_id' => $geofence->id,
                'traccar_geofence_id' => $ids['traccarGeofenceId'],
                'traccar_device_id' => $ids['traccarDeviceId'],
                'traccar_user_id' => $ids['traccarUserId'],
                'message' => 'Skipped junction delete without both device and user Traccar IDs',
            ]);
        }

        if (config('traccar.delete_geofence_row_on_remove', true)) {
            $this->deleteTraccarGeofenceRow((int) $ids['traccarGeofenceId']);
        }

        $this->idMap->forget(TraccarEntityMap::TYPE_GEOFENCE, $geofence->id);

        if ($ids['traccarDeviceId'] !== null && $ids['traccarUserId'] !== null) {
            $this->upsertUserDeviceLink((int) $ids['traccarUserId'], (int) $ids['traccarDeviceId']);
        }
    }

    /**
     * Drop only tc_device_geofence for a geofence that moved off a Laravel device.
     */
    public function detachGeofenceFromLaravelDevice(Geofence $geofence, int $laravelDeviceId): void
    {
        if (! TraccarSchema::hasGeofences() || ! TraccarSchema::hasDeviceGeofence()) {
            return;
        }

        $traccarGeofenceId = $this->idMap->get(TraccarEntityMap::TYPE_GEOFENCE, $geofence->id);
        $traccarDeviceId = $this->idMap->get(TraccarEntityMap::TYPE_DEVICE, $laravelDeviceId);

        if (! $traccarGeofenceId || ! $traccarDeviceId) {
            return;
        }

        $this->deleteGeofenceJunctionRows('device_geofence', [
            'geofenceid' => (int) $traccarGeofenceId,
            'deviceid' => (int) $traccarDeviceId,
        ]);

        $geofence->loadMissing('device');

        if ($geofence->device) {
            $this->preserveDeviceAccessAfterGeofenceChange($geofence->device, (int) $traccarDeviceId);
        }
    }

    /**
     * Attach geofence junction rows only (tc_device_geofence, tc_user_geofence).
     * Never calls syncUser/syncDevice or modifies tc_user_device / tc_devices.
     */
    private function linkGeofenceJunctions(Device $device, int $traccarGeofenceId, int $traccarDeviceId): void
    {
        $this->unlinkStaleDeviceGeofenceLinks($traccarGeofenceId, $traccarDeviceId);

        if (TraccarSchema::hasDeviceGeofence()) {
            $this->upsertGeofenceJunction(
                config('traccar.tables.device_geofence', 'tc_device_geofence'),
                'deviceid',
                $traccarDeviceId,
                'geofenceid',
                $traccarGeofenceId
            );
        }

        $traccarUserId = $this->resolveTraccarUserIdForDevice($device);

        if ($traccarUserId && TraccarSchema::hasUserGeofence()) {
            $this->upsertGeofenceJunction(
                config('traccar.tables.user_geofence', 'tc_user_geofence'),
                'userid',
                $traccarUserId,
                'geofenceid',
                $traccarGeofenceId
            );
        }
    }

    /**
     * DELETE scoped junction rows only (both keys required).
     */
    private function detachGeofenceRelations(
        int $traccarGeofenceId,
        int $traccarDeviceId,
        int $traccarUserId
    ): void {
        if ($traccarDeviceId <= 0 || $traccarUserId <= 0 || $traccarGeofenceId <= 0) {
            throw new \InvalidArgumentException('Geofence detach requires positive Traccar device, user, and geofence IDs.');
        }

        if (TraccarSchema::hasDeviceGeofence()) {
            $this->deleteGeofenceJunctionRows('device_geofence', [
                'geofenceid' => $traccarGeofenceId,
                'deviceid' => $traccarDeviceId,
            ]);
        }

        if (TraccarSchema::hasUserGeofence()) {
            $this->deleteGeofenceJunctionRows('user_geofence', [
                'geofenceid' => $traccarGeofenceId,
                'userid' => $traccarUserId,
            ]);
        }
    }

    /**
     * When a Laravel geofence moves to another device, drop the old device↔geofence link only.
     */
    private function unlinkStaleDeviceGeofenceLinks(int $traccarGeofenceId, int $traccarDeviceId): void
    {
        if (! TraccarSchema::hasDeviceGeofence() || $traccarDeviceId <= 0) {
            return;
        }

        $table = config('traccar.tables.device_geofence', 'tc_device_geofence');
        $geofenceCol = TraccarSchema::resolveColumn($table, 'geofenceid') ?? 'geofenceid';
        $deviceCol = TraccarSchema::resolveColumn($table, 'deviceid') ?? 'deviceid';

        $staleDeviceIds = DB::table($table)
            ->where($geofenceCol, $traccarGeofenceId)
            ->where($deviceCol, '!=', $traccarDeviceId)
            ->pluck($deviceCol);

        foreach ($staleDeviceIds as $staleDeviceId) {
            $this->deleteGeofenceJunctionRows('device_geofence', [
                'geofenceid' => $traccarGeofenceId,
                'deviceid' => (int) $staleDeviceId,
            ]);
        }
    }

    /**
     * @return array{traccarGeofenceId: ?int, traccarDeviceId: ?int, traccarUserId: ?int}
     */
    private function resolveTraccarIdsForGeofence(Geofence $geofence): array
    {
        $geofence->loadMissing('device.user');

        $traccarGeofenceId = $this->idMap->get(TraccarEntityMap::TYPE_GEOFENCE, $geofence->id);
        $traccarDeviceId = null;
        $traccarUserId = null;

        if ($geofence->device_id) {
            $traccarDeviceId = $this->idMap->get(TraccarEntityMap::TYPE_DEVICE, (int) $geofence->device_id);
        }

        if ($geofence->device?->user_id) {
            $traccarUserId = $this->idMap->get(TraccarEntityMap::TYPE_USER, (int) $geofence->device->user_id);
        }

        return [
            'traccarGeofenceId' => $traccarGeofenceId,
            'traccarDeviceId' => $traccarDeviceId,
            'traccarUserId' => $traccarUserId,
        ];
    }

    private function deleteTraccarGeofenceRow(int $traccarGeofenceId): void
    {
        $table = config('traccar.tables.geofences', 'tc_geofences');

        if ($table !== 'tc_geofences' && ! str_ends_with($table, '_geofences')) {
            throw new \RuntimeException("Refusing to delete unexpected geofence table: {$table}");
        }

        DB::table($table)->where('id', $traccarGeofenceId)->delete();
    }

    /**
     * @param  array<string, int>  $logicalWhere  e.g. geofenceid + deviceid or geofenceid + userid
     */
    private function deleteGeofenceJunctionRows(string $junctionKey, array $logicalWhere): void
    {
        $table = match ($junctionKey) {
            'device_geofence' => config('traccar.tables.device_geofence', 'tc_device_geofence'),
            'user_geofence' => config('traccar.tables.user_geofence', 'tc_user_geofence'),
            default => throw new \InvalidArgumentException("Unknown geofence junction: {$junctionKey}"),
        };

        $this->assertGeofenceJunctionTable($table);

        $query = DB::table($table);

        foreach ($logicalWhere as $column => $value) {
            $resolved = TraccarSchema::resolveColumn($table, $column) ?? $column;
            $query->where($resolved, $value);
        }

        $query->delete();
    }

    private function assertGeofenceJunctionTable(string $table): void
    {
        $allowed = [
            config('traccar.tables.device_geofence', 'tc_device_geofence'),
            config('traccar.tables.user_geofence', 'tc_user_geofence'),
        ];

        if (! in_array($table, $allowed, true)) {
            throw new \RuntimeException("Geofence detach must not delete from table: {$table}");
        }
    }

    private function upsertGeofenceJunction(
        string $table,
        string $firstKey,
        int $firstId,
        string $secondKey,
        int $secondId
    ): void {
        $this->assertGeofenceJunctionTable($table);

        $firstCol = TraccarSchema::resolveColumn($table, $firstKey) ?? $firstKey;
        $secondCol = TraccarSchema::resolveColumn($table, $secondKey) ?? $secondKey;

        DB::table($table)->updateOrInsert(
            [$firstCol => $firstId, $secondCol => $secondId],
            []
        );
    }

    public function syncVehicleEvent(VehicleEvent $event, ?int $traccarPositionId = null): void
    {
        if (! config('traccar.sync_events', true) || ! TraccarSchema::hasEvents()) {
            return;
        }

        $event->loadMissing('device', 'geofence');

        if (! $event->device) {
            return;
        }

        $traccarDeviceId = $this->syncDevice($event->device);
        $traccarGeofenceId = $event->geofence_id
            ? $this->idMap->get(TraccarEntityMap::TYPE_GEOFENCE, $event->geofence_id)
            : null;

        $eventsTable = config('traccar.tables.events', 'tc_events');

        DB::table($eventsTable)->insert(
            TraccarSchema::filterColumns($eventsTable, [
                'type' => $this->mapEventType($event->type),
                'eventtime' => $event->occurred_at,
                'deviceid' => $traccarDeviceId,
                'positionid' => $traccarPositionId,
                'geofenceid' => $traccarGeofenceId,
                'attributes' => TraccarAttributes::encode([
                    'laravel_event_id' => $event->id,
                    'laravel_type' => $event->type,
                    'title' => $event->title,
                    'message' => $event->message,
                    'speed' => $event->speed,
                    'latitude' => $event->lat,
                    'longitude' => $event->lng,
                    'meta' => $event->meta,
                ]),
            ])
        );
    }

    public function relinkUserDevices(User $user, ?int $traccarUserId = null, bool $ensureDevices = true): void
    {
        if (! Schema::hasTable($this->userDeviceTable()) || ! TraccarSchema::isReady()) {
            return;
        }

        $user->loadMissing('devices');

        $traccarUserId = $traccarUserId ?? $this->resolveTraccarUserIdForLink($user, ensure: true);

        if (! $traccarUserId) {
            return;
        }

        foreach ($user->devices as $device) {
            try {
                $traccarDeviceId = $this->resolveTraccarDeviceIdForLink($device, ensure: $ensureDevices);

                if (! $traccarDeviceId) {
                    continue;
                }

                $this->upsertUserDeviceLink((int) $traccarUserId, (int) $traccarDeviceId);
            } catch (\Throwable $e) {
                report($e);
            }
        }
    }

    private function traccarDeviceDisabled(Device $device): bool
    {
        return match (config('traccar.device_disable_when', 'blocked')) {
            'never' => false,
            'inactive' => in_array($device->status, ['inactive', 'blocked'], true),
            default => $device->status === 'blocked',
        };
    }

    private function linkDeviceToOwner(Device $device, int $traccarDeviceId): void
    {
        $this->upsertUserDeviceLinkForDevice($device, $traccarDeviceId);
    }

    private function resolveTraccarDeviceId(Device $device): ?int
    {
        return $this->resolveTraccarDeviceIdForLink($device, ensure: false);
    }

    /**
     * Resolve tc_devices.id for a Laravel device, healing stale traccar_entity_map rows.
     */
    private function resolveTraccarDeviceIdForLink(Device $device, bool $ensure = false): ?int
    {
        $devicesTable = config('traccar.tables.devices', 'tc_devices');
        $mapped = $this->validatedTraccarId($devicesTable, TraccarEntityMap::TYPE_DEVICE, $device->id);

        if ($mapped) {
            return $mapped;
        }

        if ($device->imei) {
            $byImei = DB::table($devicesTable)->where('uniqueid', $device->imei)->value('id');

            if ($byImei) {
                $this->idMap->put(TraccarEntityMap::TYPE_DEVICE, $device->id, (int) $byImei);

                return (int) $byImei;
            }
        }

        return $ensure ? $this->syncDevice($device) : null;
    }

    public function resolveTraccarUserIdForLink(User $user, bool $ensure = false): ?int
    {
        $usersTable = config('traccar.tables.users', 'tc_users');
        $mapped = $this->validatedTraccarId($usersTable, TraccarEntityMap::TYPE_USER, $user->id);

        if ($mapped) {
            return $mapped;
        }

        if (! $user->email) {
            return null;
        }

        $byEmail = DB::table($usersTable)->where('email', $user->email)->value('id');

        if ($byEmail) {
            $this->idMap->put(TraccarEntityMap::TYPE_USER, $user->id, (int) $byEmail);

            return (int) $byEmail;
        }

        return $ensure ? $this->syncUser($user) : null;
    }

    private function validatedTraccarId(string $table, string $entityType, int $laravelId): ?int
    {
        $mapped = $this->idMap->get($entityType, $laravelId);

        if (! $mapped) {
            return null;
        }

        if ($this->traccarRowExists($table, $mapped)) {
            return $mapped;
        }

        $this->idMap->forget($entityType, $laravelId);

        return null;
    }

    private function traccarRowExists(string $table, int $id): bool
    {
        return DB::table($table)->where('id', $id)->exists();
    }

    private function resolveTraccarUserIdForDevice(Device $device): ?int
    {
        return $device->user_id ? (int) $device->user_id : null;
    }

    /**
     * Re-assert tc_user_device after geofence work (upsert only — never delete).
     */
    private function preserveDeviceAccessAfterGeofenceChange(?Device $device, int $traccarDeviceId): void
    {
        if (! config('traccar.preserve_device_access_on_geofence_change', true) || ! $device) {
            return;
        }

        $traccarUserId = $this->resolveTraccarUserIdForDevice($device);

        if ($traccarUserId) {
            $this->upsertUserDeviceLink($traccarUserId, $traccarDeviceId);
        }
    }

    private function upsertUserDeviceLinkForDevice(Device $device, int $traccarDeviceId): void
    {
        if (! $device->user_id) {
            return;
        }

        $this->upsertUserDeviceLink((int) $device->user_id, $traccarDeviceId);
    }

    private function upsertUserDeviceLink(int $traccarUserId, int $traccarDeviceId): bool
    {
        if (! Schema::hasTable($this->userDeviceTable())) {
            return false;
        }

        $this->userDeviceLinker->upsert($traccarUserId, $traccarDeviceId);

        return true;
    }

    private function userDeviceTable(): string
    {
        return config('traccar.tables.user_device', 'tc_user_device');
    }

    private function assertUserDeviceTable(string $table): void
    {
        if ($table !== 'tc_user_device' && ! str_ends_with($table, '_user_device')) {
            throw new \RuntimeException("Refusing to write device access on non user_device table: {$table}");
        }
    }

    private function mapEventType(string $laravelType): string
    {
        return match ($laravelType) {
            VehicleEvent::TYPE_GEOFENCE_ENTER => 'geofenceEnter',
            VehicleEvent::TYPE_GEOFENCE_EXIT => 'geofenceExit',
            VehicleEvent::TYPE_OVERSPEED => 'deviceOverspeed',
            VehicleEvent::TYPE_PANIC => 'alarm',
            VehicleEvent::TYPE_POWER_CUT => 'alarm',
            default => $laravelType,
        };
    }

    private function resolveTraccarPlainPassword(
        ?string $existingHashHex,
        bool $isNewTraccarUser,
        ?string $overridePlain = null
    ): ?string {
        if (! config('traccar.sync_user_password', true)) {
            return null;
        }

        if ($overridePlain !== null && $overridePlain !== '') {
            return $overridePlain;
        }

        $default = (string) config('traccar.default_user_password', '12345678');

        if ($default === '') {
            return null;
        }

        if ($isNewTraccarUser) {
            return $default;
        }

        if (config('traccar.reset_user_password_on_sync', false)) {
            return $default;
        }

        if ($existingHashHex === null || $existingHashHex === '') {
            return $default;
        }

        if (TraccarPassword::looksLikeLaravelBcrypt($existingHashHex)) {
            return $default;
        }

        if (! TraccarPassword::looksLikeTraccarHex($existingHashHex)) {
            return $default;
        }

        return null;
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function applyTraccarPassword(array &$payload, string $usersTable, string $plainPassword): bool
    {
        $hashColumn = TraccarSchema::resolveColumn($usersTable, 'hashedPassword');
        $saltColumn = TraccarSchema::resolveColumn($usersTable, 'salt');

        if (! $hashColumn || ! $saltColumn) {
            return false;
        }

        $derived = TraccarPassword::createHash($plainPassword);
        $payload['hashedPassword'] = $derived['hash'];
        $payload['salt'] = $derived['salt'];

        return true;
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function logUserSync(
        User $user,
        string $usersTable,
        int $traccarId,
        string $action,
        array $payload,
        bool $passwordSynced,
        int $rowsAffected
    ): void {
        if (! config('traccar.sync_log', false)) {
            return;
        }

        $passwordColumn = TraccarSchema::resolveColumn($usersTable, 'hashedPassword');
        $storedHash = $passwordColumn
            ? DB::table($usersTable)->where('id', $traccarId)->value($passwordColumn)
            : null;

        logger()->info('traccar.user.sync', [
            'action' => $action,
            'laravel_user_id' => $user->id,
            'traccar_user_id' => $traccarId,
            'email' => $user->email,
            'traccar_password_synced' => $passwordSynced,
            'stored_hash_looks_like_bcrypt' => TraccarPassword::looksLikeLaravelBcrypt(
                is_string($storedHash) ? $storedHash : null
            ),
            'stored_hash_looks_like_traccar_hex' => TraccarPassword::looksLikeTraccarHex(
                is_string($storedHash) ? $storedHash : null
            ),
            'payload_keys' => array_keys($payload),
            'rows_affected' => $rowsAffected,
        ]);
    }
}
