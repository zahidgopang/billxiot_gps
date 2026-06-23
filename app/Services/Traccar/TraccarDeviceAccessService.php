<?php

namespace App\Services\Traccar;

use App\Models\Device;
use App\Models\User;
use App\Support\Traccar\TraccarMode;
use App\Support\Traccar\TraccarSchema;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Tracker UI device lists: source of truth is tc_user_device + tc_devices (not devices.user_id alone).
 */
class TraccarDeviceAccessService
{
    public function __construct(
        private TraccarIdMap $idMap,
        private TraccarSyncService $sync,
        private TraccarUserAccessService $trackerUsers,
        private ?\App\Services\Authorization\RbacService $rbac = null,
        private ?\App\Services\Authorization\TenantScopeService $tenantScope = null,
    ) {}

    private function rbac(): \App\Services\Authorization\RbacService
    {
        return $this->rbac ??= app(\App\Services\Authorization\RbacService::class);
    }

    private function tenantScope(): \App\Services\Authorization\TenantScopeService
    {
        return $this->tenantScope ??= app(\App\Services\Authorization\TenantScopeService::class);
    }

    public function usesTraccarDeviceList(): bool
    {
        return TraccarMode::readsTraccar() && TraccarSchema::isReady();
    }

    /**
     * Laravel device IDs the user may see in maps, device lists, and alerts.
     *
     * @return array<int, int>
     */
    public function laravelDeviceIdsForUser(User $user): array
    {
        if (! $this->usesTraccarDeviceList()) {
            return $user->devices()->pluck('id')->map(fn ($id) => (int) $id)->all();
        }

        if (! $this->trackerUsers->hasTrackerAccount($user)) {
            return [];
        }

        $traccarUserId = (int) $user->id;

        $userDeviceTable = config('traccar.tables.user_device', 'tc_user_device');
        $devicesTable = config('traccar.tables.devices', 'tc_devices');

        if (! Schema::hasTable($userDeviceTable) || ! Schema::hasTable($devicesTable)) {
            return [];
        }

        $userCol = TraccarSchema::resolveColumn($userDeviceTable, 'userid') ?? 'userid';
        $deviceCol = TraccarSchema::resolveColumn($userDeviceTable, 'deviceid') ?? 'deviceid';

        $ids = DB::table($userDeviceTable)
            ->where($userCol, $traccarUserId)
            ->pluck($deviceCol)
            ->map(fn ($id) => (int) $id)
            ->unique()
            ->filter(fn (int $id) => DB::table($devicesTable)->where('id', $id)->exists())
            ->values()
            ->all();

        if ($this->rbac()->canAccessPanel($user)) {
            $scoped = $this->tenantScope()->visibleDeviceIdsForPanel($user);

            if ($scoped === null) {
                return $ids;
            }

            return array_values(array_intersect($ids, $scoped));
        }

        return $this->tenantScope()->filterPivotDevicesForUser($user, $ids);
    }

    public function queryForUser(User $user): Builder
    {
        $ids = $this->laravelDeviceIdsForUser($user);

        return Device::query()->whereIn('id', $ids !== [] ? $ids : [0]);
    }

    /**
     * Devices linked in tc_user_device that are enabled in tc_devices (maps/dashboards still gate subscription).
     */
    public function queryTrackableForUser(User $user): Builder
    {
        return $this->queryForUser($user)->where('disabled', 0);
    }

    /**
     * Admin / fleet views: only Laravel devices that still exist in tc_devices.
     */
    public function queryInTracker(?User $actor = null): Builder
    {
        $query = Device::query();

        if ($actor) {
            $this->tenantScope()->scopeDevices($query, $actor);
        }

        return $query;
    }

    public function userCanAccessDevice(User $user, Device $device): bool
    {
        if ($this->rbac()->canAccessPanel($user)) {
            return $this->tenantScope()->canViewDeviceOnMap($user, $device);
        }

        if (! $this->usesTraccarDeviceList()) {
            return (int) $device->user_id === (int) $user->id;
        }

        return in_array((int) $device->id, $this->laravelDeviceIdsForUser($user), true);
    }

    public function devicesForUser(User $user, array $with = []): Collection
    {
        $query = $this->queryForUser($user);

        if ($with !== []) {
            $query->with($with);
        }

        return $query->get();
    }
}
