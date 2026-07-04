<?php

namespace App\Services\Tracking;

use App\Models\Device;
use App\Models\User;
use App\Support\Traccar\TraccarSchema;
use Illuminate\Support\Facades\DB;

class DriverService
{
    public function __construct(
        private GlobalTrackingService $tracking,
    ) {}

    /**
     * @return list<array<string, mixed>>
     */
    public function listForActor(User $actor): array
    {
        if (! TraccarSchema::hasTable(config('traccar.tables.drivers', 'tc_drivers'))) {
            return $this->legacyDriversFromDevices($actor);
        }

        $rows = DB::table(config('traccar.tables.drivers', 'tc_drivers'))->orderBy('name')->get();
        $assignments = $this->deviceAssignments();

        return $rows->map(function ($row) use ($assignments) {
            $attrs = json_decode((string) ($row->attributes ?? '{}'), true) ?: [];

            return [
                'id' => (int) $row->id,
                'name' => (string) ($row->name ?? ''),
                'unique_id' => (string) ($row->uniqueid ?? ''),
                'phone' => (string) ($attrs['phone'] ?? $attrs['contact'] ?? ''),
                'device_id' => $assignments[(int) $row->id] ?? null,
            ];
        })->values()->all();
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function create(User $actor, array $data): ?int
    {
        $table = config('traccar.tables.drivers', 'tc_drivers');
        if (! TraccarSchema::hasTable($table)) {
            return null;
        }

        $payload = TraccarSchema::filterColumns($table, [
            'name' => (string) ($data['name'] ?? ''),
            'uniqueid' => (string) ($data['unique_id'] ?? uniqid('drv_', true)),
            'attributes' => json_encode(['phone' => $data['phone'] ?? '']),
        ]);

        return (int) DB::table($table)->insertGetId($payload);
    }

    public function assign(User $actor, int $driverId, int $deviceId): bool
    {
        if (! in_array($deviceId, $this->tracking->filterAllowedIds($actor, [$deviceId]), true)) {
            return false;
        }

        $device = Device::query()->find($deviceId);
        if (! $device) {
            return false;
        }

        if (TraccarSchema::hasTable('tc_device_driver')) {
            DB::table('tc_device_driver')->where('deviceid', $deviceId)->delete();
            DB::table('tc_device_driver')->insert(['deviceid' => $deviceId, 'driverid' => $driverId]);
        }

        $driverRow = DB::table(config('traccar.tables.drivers', 'tc_drivers'))
            ->where('id', $driverId)
            ->first(['name', 'attributes']);

        if (! $driverRow) {
            return false;
        }

        $attrs = json_decode((string) ($driverRow->attributes ?? '{}'), true) ?: [];
        $phone = trim((string) ($attrs['phone'] ?? $attrs['contact'] ?? ''));

        $device->driver_name = (string) ($driverRow->name ?? '');
        $device->driver_contact = $phone !== '' ? $phone : null;
        $device->save();

        return true;
    }

    public function delete(User $actor, int $driverId): bool
    {
        $table = config('traccar.tables.drivers', 'tc_drivers');
        if (! TraccarSchema::hasTable($table)) {
            return false;
        }

        if (TraccarSchema::hasTable('tc_device_driver')) {
            DB::table('tc_device_driver')->where('driverid', $driverId)->delete();
        }

        return DB::table($table)->where('id', $driverId)->delete() > 0;
    }

    /**
     * @return array<int, int>
     */
    private function deviceAssignments(): array
    {
        if (! TraccarSchema::hasTable('tc_device_driver')) {
            return [];
        }

        return DB::table('tc_device_driver')
            ->pluck('deviceid', 'driverid')
            ->map(fn ($v) => (int) $v)
            ->all();
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function legacyDriversFromDevices(User $actor): array
    {
        return $this->tracking->devicesForActor($actor)
            ->filter(fn (Device $d) => $d->driverDisplayName())
            ->map(fn (Device $d) => [
                'id' => 0,
                'name' => $d->driverDisplayName(),
                'unique_id' => '',
                'phone' => $d->driverContactNumber(),
                'device_id' => $d->id,
            ])
            ->values()
            ->all();
    }
}
