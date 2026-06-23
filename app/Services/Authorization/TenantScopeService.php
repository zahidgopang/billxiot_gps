<?php

namespace App\Services\Authorization;

use App\Enums\AppRole;
use App\Models\Client;
use App\Models\ClientDevice;
use App\Models\ClientMember;
use App\Models\Device;
use App\Models\User;
use App\Models\AdminClientScope;
use App\Support\Traccar\TraccarAppFields;
use App\Support\Traccar\TraccarSchema;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class TenantScopeService
{
    public function __construct(
        private RbacService $rbac,
    ) {}

    /**
     * Client companies visible to the actor.
     *
     * @return list<int>
     */
    public function visibleClientIds(User $actor): array
    {
        if ($this->rbac->isSuperAdmin($actor)) {
            return Client::query()->pluck('id')->map(fn ($id) => (int) $id)->all();
        }

        if ($this->rbac->roleOf($actor) === AppRole::Admin) {
            return AdminClientScope::query()
                ->where('admin_user_id', $actor->id)
                ->pluck('client_id')
                ->map(fn ($id) => (int) $id)
                ->all();
        }

        if ($this->rbac->isClientManager($actor)) {
            return $this->clientIdsForUser($actor);
        }

        return [];
    }

    /**
     * @return list<int>
     */
    public function clientIdsForUser(User $user): array
    {
        return ClientMember::query()
            ->where('user_id', $user->id)
            ->pluck('client_id')
            ->map(fn ($id) => (int) $id)
            ->unique()
            ->values()
            ->all();
    }

    public function primaryClientIdForUser(User $user): ?int
    {
        $ids = $this->clientIdsForUser($user);

        return $ids[0] ?? null;
    }

    /**
     * Client managers must belong to a company; create one when missing (legacy/orphan accounts).
     */
    public function ensureClientForManager(User $manager): int
    {
        if (! $this->rbac->isClientManager($manager)) {
            throw new \InvalidArgumentException('Only client managers can auto-provision a client company.');
        }

        $existing = $this->clientIdsForUser($manager);

        if ($existing !== []) {
            return $existing[0];
        }

        $client = Client::query()->create([
            'name' => trim($manager->name) !== '' ? $manager->name : 'My Company',
            'status' => 'active',
            'created_by' => $manager->id,
        ]);

        $this->assignUserToClient($manager, (int) $client->id, 'owner');

        return (int) $client->id;
    }

    /**
     * Device IDs owned by clients in scope (fleet inventory).
     *
     * @return list<int>|null null = unrestricted (super admin)
     */
    public function visibleDeviceIdsForPanel(User $actor): ?array
    {
        if ($this->rbac->isSuperAdmin($actor)) {
            return null;
        }

        if (! $this->rbac->canAccessPanel($actor)) {
            return [];
        }

        $clientIds = $this->visibleClientIds($actor);

        if ($clientIds === []) {
            return [];
        }

        return ClientDevice::query()
            ->whereIn('client_id', $clientIds)
            ->pluck('device_id')
            ->map(fn ($id) => (int) $id)
            ->unique()
            ->values()
            ->all();
    }

    /**
     * User IDs manageable in admin/client panels.
     *
     * @return list<int>|null null = unrestricted
     */
    public function visibleUserIdsForPanel(User $actor): ?array
    {
        if ($this->rbac->isSuperAdmin($actor)) {
            return null;
        }

        if ($this->rbac->roleOf($actor) === AppRole::Admin) {
            $clientIds = $this->visibleClientIds($actor);

            if ($clientIds === []) {
                return [];
            }

            return ClientMember::query()
                ->whereIn('client_id', $clientIds)
                ->pluck('user_id')
                ->map(fn ($id) => (int) $id)
                ->unique()
                ->push((int) $actor->id)
                ->unique()
                ->values()
                ->all();
        }

        if ($this->rbac->isClientManager($actor)) {
            $clientIds = $this->clientIdsForUser($actor);

            if ($clientIds === []) {
                return [(int) $actor->id];
            }

            return ClientMember::query()
                ->whereIn('client_id', $clientIds)
                ->pluck('user_id')
                ->map(fn ($id) => (int) $id)
                ->unique()
                ->values()
                ->all();
        }

        return [(int) $actor->id];
    }

    public function scopeUsers(Builder $query, User $actor): Builder
    {
        $ids = $this->visibleUserIdsForPanel($actor);

        if ($ids === null) {
            return $query;
        }

        return $query->whereIn($query->qualifyColumn('id'), $ids !== [] ? $ids : [0]);
    }

    public function scopeDevices(Builder $query, User $actor): Builder
    {
        $ids = $this->visibleDeviceIdsForPanel($actor);

        if ($ids === null) {
            return $query;
        }

        return $query->whereIn($query->qualifyColumn('id'), $ids !== [] ? $ids : [0]);
    }

    public function scopeClients(Builder $query, User $actor): Builder
    {
        $ids = $this->visibleClientIds($actor);

        if ($this->rbac->isSuperAdmin($actor)) {
            return $query;
        }

        return $query->whereIn($query->qualifyColumn('id'), $ids !== [] ? $ids : [0]);
    }

    public function canManageUser(User $actor, User $target): bool
    {
        if ($this->rbac->isSuperAdmin($actor)) {
            return true;
        }

        if (! $this->rbac->hasPermission($actor, 'users.manage')) {
            return false;
        }

        if ($this->rbac->isClientManager($actor)) {
            if ($actor->id === $target->id || $this->rbac->roleOf($target) !== AppRole::EndUser) {
                return false;
            }
        } elseif ($actor->id === $target->id) {
            return true;
        }

        $visible = $this->visibleUserIdsForPanel($actor);

        return $visible === null || in_array((int) $target->id, $visible, true);
    }

    public function canManageDevice(User $actor, Device $device): bool
    {
        if ($this->rbac->isSuperAdmin($actor)) {
            return true;
        }

        if (! $this->rbac->hasPermission($actor, 'devices.manage')) {
            return false;
        }

        $visible = $this->visibleDeviceIdsForPanel($actor);

        return $visible === null || in_array((int) $device->id, $visible, true);
    }

    /**
     * Whether a panel user may use fleet map tracking (location history / live maps).
     */
    public function actorMayTrackMaps(User $actor): bool
    {
        if ($this->rbac->isSuperAdmin($actor)) {
            return true;
        }

        if ($this->rbac->roleOf($actor) === AppRole::Admin) {
            return $this->rbac->hasPermission($actor, 'maps.view');
        }

        if ($this->rbac->isClientManager($actor)) {
            if (! $this->rbac->hasPermission($actor, 'maps.view')) {
                return false;
            }

            $clientIds = $this->clientIdsForUser($actor);

            if ($clientIds === []) {
                return false;
            }

            return Client::query()
                ->whereIn('id', $clientIds)
                ->where('can_track_maps', true)
                ->where('status', 'active')
                ->exists();
        }

        return false;
    }

    public function clientAllowsMapTracking(int $clientId): bool
    {
        $client = Client::query()->find($clientId);

        return $client !== null && $client->allowsMapTracking();
    }

    public function canViewDeviceOnMap(User $actor, Device $device): bool
    {
        if ($this->rbac->isEndUser($actor)) {
            return app(\App\Services\Traccar\TraccarDeviceAccessService::class)
                ->userCanAccessDevice($actor, $device);
        }

        if (! $this->actorMayTrackMaps($actor)) {
            return false;
        }

        if ($this->rbac->isClientManager($actor)) {
            $clientId = $this->clientIdForDevice($device);

            if ($clientId === null || ! $this->clientAllowsMapTracking($clientId)) {
                return false;
            }

            if (! in_array($clientId, $this->clientIdsForUser($actor), true)) {
                return false;
            }

            $visible = $this->visibleDeviceIdsForPanel($actor);

            return $visible === null || in_array((int) $device->id, $visible, true);
        }

        if (! $this->rbac->hasPermission($actor, 'maps.view')) {
            return false;
        }

        if ($this->rbac->hasPermission($actor, 'maps.view_all') || $this->rbac->isSuperAdmin($actor)) {
            $visible = $this->visibleDeviceIdsForPanel($actor);

            return $visible === null || in_array((int) $device->id, $visible, true);
        }

        return $this->canManageDevice($actor, $device);
    }

    public function assignUserToClient(User $user, int $clientId, string $membership = 'member'): void
    {
        ClientMember::query()->updateOrCreate(
            ['client_id' => $clientId, 'user_id' => $user->id],
            ['membership' => $membership]
        );
    }

    public function assignDeviceToClient(Device $device, int $clientId): void
    {
        ClientDevice::query()->updateOrCreate(
            ['device_id' => $device->id],
            ['client_id' => $clientId]
        );
    }

    public function assignAdminToClient(User $admin, int $clientId): void
    {
        AdminClientScope::query()->updateOrCreate(
            ['admin_user_id' => $admin->id, 'client_id' => $clientId]
        );
    }

    public function clientIdForDevice(Device $device): ?int
    {
        return ClientDevice::query()
            ->where('device_id', $device->id)
            ->value('client_id');
    }

    public function userBelongsToClient(User $user, int $clientId): bool
    {
        return in_array($clientId, $this->clientIdsForUser($user), true);
    }

    /**
     * Users linked to a client via client_members (for admin device assignment, etc.).
     */
    public function usersForClient(int $clientId, ?User $actor = null): Collection
    {
        return $this->queryUsersForClient($clientId, $actor, false)->get();
    }

    /**
     * End users linked to a client (device owner assignment).
     */
    public function assignableUsersForClient(int $clientId, ?User $actor = null): Collection
    {
        return $this->queryUsersForClient($clientId, $actor, true)->get();
    }

    private function queryUsersForClient(int $clientId, ?User $actor, bool $endUsersOnly): Builder
    {
        $memberIds = ClientMember::query()
            ->where('client_id', $clientId)
            ->pluck('user_id')
            ->map(fn ($id) => (int) $id)
            ->all();

        $query = User::query()
            ->whereIn('id', $memberIds !== [] ? $memberIds : [0])
            ->orderBy('name');

        if ($actor !== null) {
            $query = $this->scopeUsers($query, $actor);
        }

        if ($endUsersOnly) {
            $this->scopeEndUsersOnly($query);
        }

        return $query;
    }

    private function scopeEndUsersOnly(Builder $query): void
    {
        $table = $query->getModel()->getTable();

        if (! TraccarSchema::hasColumn($table, 'attributes')) {
            return;
        }

        $path = '$.' . TraccarAppFields::KEY_ROLE;
        $endUser = AppRole::EndUser->value;

        $query->where(function ($q) use ($path, $endUser) {
            $q->whereRaw(
                'COALESCE(JSON_UNQUOTE(JSON_EXTRACT(' . $q->qualifyColumn('attributes') . ', ?)), ?) = ?',
                [$path, $endUser, $endUser]
            )->where(function ($inner) {
                $inner->where($inner->qualifyColumn('administrator'), '!=', 1)
                    ->orWhereNull($inner->qualifyColumn('administrator'));
            });
        });
    }

    /**
     * Devices assigned to a client fleet (client_devices).
     */
    public function devicesForClient(int $clientId, ?User $actor = null): Collection
    {
        $deviceIds = ClientDevice::query()
            ->where('client_id', $clientId)
            ->pluck('device_id')
            ->map(fn ($id) => (int) $id)
            ->all();

        if ($deviceIds === []) {
            return collect();
        }

        $query = Device::query()
            ->with('user')
            ->whereIn('id', $deviceIds)
            ->orderBy('name');

        if ($actor !== null) {
            $query = $this->scopeDevices($query, $actor);
        }

        return $query->get();
    }

    public function deviceBelongsToClient(Device $device, int $clientId): bool
    {
        return ClientDevice::query()
            ->where('client_id', $clientId)
            ->where('device_id', $device->id)
            ->exists();
    }

    /**
     * Resolve device IDs for end users: pivot ∩ client fleet (when client-scoped).
     *
     * @param  list<int>  $pivotDeviceIds
     * @return list<int>
     */
    public function filterPivotDevicesForUser(User $user, array $pivotDeviceIds): array
    {
        if ($pivotDeviceIds === []) {
            return [];
        }

        if ($this->rbac->isEndUser($user)) {
            $clientIds = $this->clientIdsForUser($user);

            if ($clientIds === []) {
                return $pivotDeviceIds;
            }

            $fleetIds = ClientDevice::query()
                ->whereIn('client_id', $clientIds)
                ->whereIn('device_id', $pivotDeviceIds)
                ->pluck('device_id')
                ->map(fn ($id) => (int) $id)
                ->all();

            return $fleetIds !== [] ? $fleetIds : $pivotDeviceIds;
        }

        return $pivotDeviceIds;
    }
}
