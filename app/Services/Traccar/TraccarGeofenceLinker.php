<?php

namespace App\Services\Traccar;

use App\Models\Device;
use App\Models\Geofence;
use App\Support\Traccar\TraccarSchema;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Links tc_geofences to tc_devices and tc_users (required for Traccar UI visibility).
 */
class TraccarGeofenceLinker
{
    public function link(Geofence $geofence, Device $device): void
    {
        if (! $geofence->id || ! $device->id || ! TraccarSchema::hasGeofences()) {
            return;
        }

        $deviceKeys = TraccarSchema::deviceGeofencePivotKeys();

        if (Schema::hasTable($deviceKeys['table'])) {
            DB::table($deviceKeys['table'])->updateOrInsert(
                [
                    $deviceKeys['geofence'] => $geofence->id,
                    $deviceKeys['device'] => $device->id,
                ],
                []
            );
        }

        $userTable = config('traccar.tables.user_geofence', 'tc_user_geofence');

        if (! Schema::hasTable($userTable)) {
            return;
        }

        $ownerId = $device->relationLoaded('user')
            ? $device->user?->id
            : Device::query()->find($device->id)?->user_id;

        if (! $ownerId) {
            return;
        }

        $userCol = TraccarSchema::resolveColumn($userTable, 'userid') ?? 'userid';
        $geofenceCol = TraccarSchema::resolveColumn($userTable, 'geofenceid') ?? 'geofenceid';

        DB::table($userTable)->updateOrInsert(
            [
                $userCol => $ownerId,
                $geofenceCol => $geofence->id,
            ],
            []
        );
    }
}
