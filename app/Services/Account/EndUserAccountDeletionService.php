<?php

namespace App\Services\Account;

use App\Models\ClientMember;
use App\Models\Device;
use App\Models\SubAccount;
use App\Models\Subscription;
use App\Models\User;
use App\Services\AdminAuditService;
use App\Services\Authorization\RbacService;
use App\Services\Authorization\SubAccountService;
use App\Services\Tracking\DeviceVehicleIconService;
use App\Services\Traccar\TraccarUserDeviceLinker;
use App\Services\UserAvatarService;
use App\Support\Traccar\TraccarSchema;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Throwable;

/**
 * Permanently deletes an end-user web account and owned data
 * (vehicles exclusive to them, travel history, subscriptions, sub-accounts).
 *
 * Sub-accounts cannot self-delete; only the parent end user can.
 */
class EndUserAccountDeletionService
{
    private const HISTORY_DELETE_CHUNK = 5000;

    public function __construct(
        private RbacService $rbac,
        private SubAccountService $subAccounts,
        private TraccarUserDeviceLinker $deviceLinker,
        private UserAvatarService $avatars,
        private DeviceVehicleIconService $vehicleIcons,
        private AdminAuditService $audit,
    ) {}

    public function canDeleteAccount(User $user): bool
    {
        if (! $this->rbac->isEndUser($user)) {
            return false;
        }

        // Sub-accounts must never see or use self-delete (parent owns the fleet).
        if ($user->isSubAccount() || $user->parentUserId()) {
            return false;
        }

        if ($this->rbac->canAccessPanel($user)) {
            return false;
        }

        return true;
    }

    /**
     * Preview counts shown on the confirmation screen.
     *
     * @return array{vehicles: int, exclusive_vehicles: int, shared_vehicles: int, sub_accounts: int, subscriptions: int}
     */
    public function deletionSummary(User $user): array
    {
        $deviceIds = $this->linkedDeviceIds($user);
        $exclusiveIds = $this->exclusiveDeviceIds($user, $deviceIds);
        $subCount = SubAccount::query()->where('parent_user_id', $user->id)->count();

        $subscriptionCount = 0;
        if ($deviceIds->isNotEmpty() && Schema::hasTable('subscriptions')) {
            $subscriptionCount = Subscription::query()
                ->where(function ($q) use ($user, $deviceIds) {
                    $q->where('user_id', $user->id)
                        ->orWhereIn('device_id', $deviceIds->all());
                })
                ->count();
        }

        return [
            'vehicles' => $deviceIds->count(),
            'exclusive_vehicles' => $exclusiveIds->count(),
            'shared_vehicles' => max(0, $deviceIds->count() - $exclusiveIds->count()),
            'sub_accounts' => $subCount,
            'subscriptions' => $subscriptionCount,
        ];
    }

    /**
     * @throws \RuntimeException
     */
    public function delete(User $user): void
    {
        if (! $this->canDeleteAccount($user)) {
            throw new \RuntimeException(__('app.user.account_delete.not_allowed'));
        }

        $email = (string) $user->email;
        $userId = (int) $user->id;
        $summary = $this->deletionSummary($user);

        $deviceIds = $this->linkedDeviceIds($user);
        $exclusiveIds = $this->exclusiveDeviceIds($user, $deviceIds);

        // Travel history can be large — purge before the account transaction.
        foreach ($exclusiveIds as $deviceId) {
            $this->purgeDeviceTravelData((int) $deviceId);
        }

        DB::transaction(function () use ($user, $userId, $exclusiveIds) {
            $householdIds = $this->householdUserIds($user);

            // 1) Remove sub-accounts (child users + links).
            $children = User::query()
                ->whereIn('id', SubAccount::query()
                    ->where('parent_user_id', $userId)
                    ->pluck('user_id'))
                ->get();

            foreach ($children as $child) {
                $this->subAccounts->delete($user, $child);
            }

            // 2) Subscriptions for this user / their exclusive vehicles.
            if (Schema::hasTable('subscriptions')) {
                Subscription::query()
                    ->where(function ($q) use ($userId, $exclusiveIds) {
                        $q->where('user_id', $userId);
                        if ($exclusiveIds->isNotEmpty()) {
                            $q->orWhereIn('device_id', $exclusiveIds->all());
                        }
                    })
                    ->delete();
            }

            // 3) Delete vehicles that only this household used (history already purged).
            foreach ($exclusiveIds as $deviceId) {
                $device = Device::query()->find($deviceId);
                if (! $device) {
                    continue;
                }

                try {
                    $this->vehicleIcons->delete($device);
                } catch (Throwable $e) {
                    report($e);
                }

                $this->purgeDeviceAppRows((int) $deviceId);
                $device->delete();
            }

            // 4) Unlink any remaining shared vehicles from this user.
            $this->deviceLinker->removeForUser($userId);

            // 5) Tenant membership + API tokens + avatar.
            ClientMember::query()->where('user_id', $userId)->delete();

            if (method_exists($user, 'tokens')) {
                $user->tokens()->delete();
            }

            $this->avatars->delete($user);

            // 6) Clear leftover geofence user links for the household.
            $this->purgeUserGeofenceLinks($householdIds);

            // 7) Delete the account (User::deleting also cleans pivots).
            $user->delete();
        });

        try {
            $this->audit->log('deleted', "End user self-deleted account {$email}", null, [
                'email' => $email,
                'user_id' => $userId,
                'vehicles_deleted' => $summary['exclusive_vehicles'],
                'vehicles_unlinked' => $summary['shared_vehicles'],
                'sub_accounts' => $summary['sub_accounts'],
                'subscriptions' => $summary['subscriptions'],
                'travel_history_purged' => true,
            ]);
        } catch (Throwable $e) {
            report($e);
        }
    }

    /**
     * Remove GPS travel history and related Traccar rows for one device.
     */
    private function purgeDeviceTravelData(int $deviceId): void
    {
        $devicesTable = config('traccar.tables.devices', 'tc_devices');
        $positionsTable = config('traccar.tables.positions', 'tc_positions');
        $eventsTable = config('traccar.tables.events', 'tc_events');
        $commandsQueue = config('traccar.tables.commands_queue', 'tc_commands_queue');

        // Clear FK from device → current position before deleting history.
        if (Schema::hasTable($devicesTable) && TraccarSchema::hasColumn($devicesTable, 'positionid')) {
            $positionCol = TraccarSchema::resolveColumn($devicesTable, 'positionid') ?? 'positionid';
            DB::table($devicesTable)->where('id', $deviceId)->update([$positionCol => null]);
        }

        if (Schema::hasTable($positionsTable)) {
            $deviceCol = TraccarSchema::resolveColumn($positionsTable, 'deviceid') ?? 'deviceid';
            $this->deleteInChunks($positionsTable, $deviceCol, $deviceId);
        }

        if (Schema::hasTable($eventsTable)) {
            $deviceCol = TraccarSchema::resolveColumn($eventsTable, 'deviceid') ?? 'deviceid';
            $this->deleteInChunks($eventsTable, $deviceCol, $deviceId);
        }

        if (Schema::hasTable($commandsQueue) && TraccarSchema::hasColumn($commandsQueue, 'deviceid')) {
            $deviceCol = TraccarSchema::resolveColumn($commandsQueue, 'deviceid') ?? 'deviceid';
            $this->deleteInChunks($commandsQueue, $deviceCol, $deviceId);
        }

        // Legacy Laravel history tables (if still present).
        if (Schema::hasTable('device_locations')) {
            $this->deleteInChunks('device_locations', 'device_id', $deviceId);
        }

        if (Schema::hasTable('vehicle_events')) {
            $eventIds = DB::table('vehicle_events')->where('device_id', $deviceId)->pluck('id');
            if ($eventIds->isNotEmpty() && Schema::hasTable('vehicle_event_reads')) {
                foreach ($eventIds->chunk(1000) as $chunk) {
                    DB::table('vehicle_event_reads')->whereIn('vehicle_event_id', $chunk->all())->delete();
                }
            }
            $this->deleteInChunks('vehicle_events', 'device_id', $deviceId);
        }
    }

    /**
     * App-side rows tied to a device (not Traccar GPS history).
     */
    private function purgeDeviceAppRows(int $deviceId): void
    {
        if (Schema::hasTable('device_route_assignments')) {
            DB::table('device_route_assignments')->where('device_id', $deviceId)->delete();
        }

        if (Schema::hasTable('device_command_logs')) {
            $this->deleteInChunks('device_command_logs', 'device_id', $deviceId);
        }
    }

    private function deleteInChunks(string $table, string $column, int $deviceId): void
    {
        do {
            $deleted = DB::table($table)
                ->where($column, $deviceId)
                ->limit(self::HISTORY_DELETE_CHUNK)
                ->delete();
        } while ($deleted > 0);
    }

    /**
     * @return Collection<int, int>
     */
    private function linkedDeviceIds(User $user): Collection
    {
        $keys = TraccarSchema::userDevicePivotKeys();
        if (! Schema::hasTable($keys['table'])) {
            return collect();
        }

        return DB::table($keys['table'])
            ->where($keys['user'], $user->id)
            ->pluck($keys['device'])
            ->map(fn ($id) => (int) $id)
            ->unique()
            ->values();
    }

    /**
     * Devices linked only to this user and/or their sub-accounts.
     *
     * @param  Collection<int, int>  $deviceIds
     * @return Collection<int, int>
     */
    private function exclusiveDeviceIds(User $user, Collection $deviceIds): Collection
    {
        if ($deviceIds->isEmpty()) {
            return collect();
        }

        $keys = TraccarSchema::userDevicePivotKeys();
        $household = $this->householdUserIds($user)->all();

        $exclusive = collect();
        foreach ($deviceIds as $deviceId) {
            $linkedUserIds = DB::table($keys['table'])
                ->where($keys['device'], $deviceId)
                ->pluck($keys['user'])
                ->map(fn ($id) => (int) $id)
                ->unique()
                ->values();

            $outside = $linkedUserIds->diff($household);
            if ($outside->isEmpty()) {
                $exclusive->push((int) $deviceId);
            }
        }

        return $exclusive->unique()->values();
    }

    /**
     * Parent + sub-account user ids.
     *
     * @return Collection<int, int>
     */
    private function householdUserIds(User $user): Collection
    {
        $childIds = SubAccount::query()
            ->where('parent_user_id', $user->id)
            ->pluck('user_id')
            ->map(fn ($id) => (int) $id);

        return collect([(int) $user->id])->merge($childIds)->unique()->values();
    }

    /**
     * @param  Collection<int, int>  $userIds
     */
    private function purgeUserGeofenceLinks(Collection $userIds): void
    {
        if ($userIds->isEmpty()) {
            return;
        }

        $userGeofence = config('traccar.tables.user_geofence', 'tc_user_geofence');
        if (! Schema::hasTable($userGeofence)) {
            return;
        }

        $userCol = TraccarSchema::resolveColumn($userGeofence, 'userid') ?? 'userid';
        DB::table($userGeofence)->whereIn($userCol, $userIds->all())->delete();
    }
}
