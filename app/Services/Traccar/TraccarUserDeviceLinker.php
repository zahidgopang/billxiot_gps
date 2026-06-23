<?php

namespace App\Services\Traccar;

use App\Support\Traccar\TraccarSchema;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * tc_user_device only — never touches geofence or other junction tables.
 */
class TraccarUserDeviceLinker
{
    public function upsert(int $userId, int $deviceId): void
    {
        $table = $this->table();

        if (! $table || ! $this->bothExist($userId, $deviceId)) {
            return;
        }

        $userCol = TraccarSchema::resolveColumn($table, 'userid') ?? 'userid';
        $deviceCol = TraccarSchema::resolveColumn($table, 'deviceid') ?? 'deviceid';

        DB::table($table)->updateOrInsert(
            [$userCol => $userId, $deviceCol => $deviceId],
            []
        );
    }

    public function removeForDevice(int $deviceId): void
    {
        $table = $this->table();

        if (! $table) {
            return;
        }

        $deviceCol = TraccarSchema::resolveColumn($table, 'deviceid') ?? 'deviceid';
        DB::table($table)->where($deviceCol, $deviceId)->delete();
    }

    public function removeForUser(int $userId): void
    {
        $table = $this->table();

        if (! $table) {
            return;
        }

        $userCol = TraccarSchema::resolveColumn($table, 'userid') ?? 'userid';
        DB::table($table)->where($userCol, $userId)->delete();
    }

    public function removeLink(int $userId, int $deviceId): void
    {
        $table = $this->table();

        if (! $table) {
            return;
        }

        $userCol = TraccarSchema::resolveColumn($table, 'userid') ?? 'userid';
        $deviceCol = TraccarSchema::resolveColumn($table, 'deviceid') ?? 'deviceid';

        DB::table($table)
            ->where($userCol, $userId)
            ->where($deviceCol, $deviceId)
            ->delete();
    }

    private function table(): ?string
    {
        $table = config('traccar.tables.user_device', 'tc_user_device');

        return Schema::hasTable($table) ? $table : null;
    }

    private function bothExist(int $userId, int $deviceId): bool
    {
        return DB::table(config('traccar.tables.users', 'tc_users'))->where('id', $userId)->exists()
            && DB::table(config('traccar.tables.devices', 'tc_devices'))->where('id', $deviceId)->exists();
    }
}
