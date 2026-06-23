<?php

namespace App\Support\Traccar;

use Illuminate\Support\Facades\Schema;

final class TraccarSchema
{
    public static function isReady(): bool
    {
        if (! TraccarMode::isActive()) {
            return false;
        }

        return Schema::hasTable(config('traccar.tables.devices', 'tc_devices'))
            && Schema::hasTable(config('traccar.tables.positions', 'tc_positions'));
    }

    public static function hasEvents(): bool
    {
        return TraccarMode::isActive()
            && Schema::hasTable(config('traccar.tables.events', 'tc_events'));
    }

    public static function hasGeofences(): bool
    {
        return TraccarMode::isActive()
            && Schema::hasTable(config('traccar.tables.geofences', 'tc_geofences'));
    }

    public static function hasUserGeofence(): bool
    {
        return self::hasGeofences()
            && Schema::hasTable(config('traccar.tables.user_geofence', 'tc_user_geofence'));
    }

    public static function hasDeviceGeofence(): bool
    {
        return self::hasGeofences()
            && Schema::hasTable(config('traccar.tables.device_geofence', 'tc_device_geofence'));
    }

    public static function hasUsers(): bool
    {
        return TraccarMode::isActive()
            && Schema::hasTable(config('traccar.tables.users', 'tc_users'));
    }

    public static function hasTable(string $table): bool
    {
        return Schema::hasTable($table);
    }

    public static function hasPositions(): bool
    {
        return TraccarMode::isActive()
            && Schema::hasTable(config('traccar.tables.positions', 'tc_positions'));
    }

    /**
     * Map payload keys to real table column names (case-insensitive).
     * Fixes hashedPassword being dropped when MySQL lists hashedpassword.
     *
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    public static function filterColumns(string $table, array $payload): array
    {
        $columnMap = self::columnNameMap($table);

        if ($columnMap === []) {
            return $payload;
        }

        $filtered = [];

        foreach ($payload as $key => $value) {
            $resolved = $columnMap[strtolower((string) $key)] ?? null;

            if ($resolved !== null) {
                $filtered[$resolved] = $value;
            }
        }

        return $filtered;
    }

    public static function resolveColumn(string $table, string $logicalName): ?string
    {
        return self::columnNameMap($table)[strtolower($logicalName)] ?? null;
    }

    public static function hasColumn(string $table, string $logicalName): bool
    {
        return self::resolveColumn($table, $logicalName) !== null;
    }

    /**
     * tc_user_device pivot column names (resolved for MySQL casing).
     *
     * @return array{table: string, user: string, device: string}
     */
    public static function userDevicePivotKeys(): array
    {
        $table = config('traccar.tables.user_device', 'tc_user_device');

        return [
            'table' => $table,
            'user' => self::resolveColumn($table, 'userid') ?? 'userid',
            'device' => self::resolveColumn($table, 'deviceid') ?? 'deviceid',
        ];
    }

    /**
     * tc_device_geofence pivot column names.
     *
     * @return array{table: string, geofence: string, device: string}
     */
    public static function deviceGeofencePivotKeys(): array
    {
        $table = config('traccar.tables.device_geofence', 'tc_device_geofence');

        return [
            'table' => $table,
            'geofence' => self::resolveColumn($table, 'geofenceid') ?? 'geofenceid',
            'device' => self::resolveColumn($table, 'deviceid') ?? 'deviceid',
        ];
    }

    /**
     * @return array<string, string> lowercase name => actual column name
     */
    private static function columnNameMap(string $table): array
    {
        static $cache = [];

        if (! isset($cache[$table])) {
            $columns = Schema::hasTable($table)
                ? Schema::getColumnListing($table)
                : [];

            $map = [];
            foreach ($columns as $column) {
                $map[strtolower($column)] = $column;
            }
            $cache[$table] = $map;
        }

        return $cache[$table];
    }
}
