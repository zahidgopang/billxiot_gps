<?php

namespace App\Services\Tracking;

use App\Models\Device;
use App\Models\User;
use App\Services\Authorization\TenantScopeService;
use App\Services\Tracking\GlobalTrackingService;
use App\Support\Traccar\TraccarSchema;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class MaintenanceService
{
    public function __construct(
        private GlobalTrackingService $tracking,
        private DevicePositionLoader $positionLoader,
        private TenantScopeService $tenantScope,
    ) {}

    /**
     * @return list<array<string, mixed>>
     */
    public function listForActor(User $actor): array
    {
        if (! TraccarSchema::hasTable('tc_maintenances')) {
            return [];
        }

        $deviceIds = $this->tracking->allowedDeviceIds($actor);
        if ($deviceIds === []) {
            return [];
        }

        $hasPivot = TraccarSchema::hasTable('tc_device_maintenance');

        // Maintenances linked to at least one device the actor can see.
        if ($hasPivot) {
            $maintenanceIds = DB::table('tc_device_maintenance')
                ->whereIn('deviceid', $deviceIds)
                ->pluck('maintenanceid')
                ->map(fn ($id) => (int) $id)
                ->unique()
                ->values()
                ->all();

            if ($maintenanceIds === []) {
                return [];
            }

            $rows = DB::table('tc_maintenances')->whereIn('id', $maintenanceIds)->get();

            // Map maintenance -> linked device ids (limited to allowed ones for display).
            $links = DB::table('tc_device_maintenance')
                ->whereIn('maintenanceid', $maintenanceIds)
                ->whereIn('deviceid', $deviceIds)
                ->get()
                ->groupBy('maintenanceid');
        } else {
            $rows = DB::table('tc_maintenances')->get();
            $links = collect();
        }

        $devices = Device::query()->whereIn('id', $deviceIds)->get()->keyBy('id');
        $this->positionLoader->attachLatestToMany($devices);

        return $rows->map(function ($row) use ($links, $devices, $hasPivot) {
            $maintenanceId = (int) $row->id;
            $linkedDeviceIds = $hasPivot
                ? ($links->get($maintenanceId, collect())->pluck('deviceid')->map(fn ($id) => (int) $id)->all())
                : [];

            $objects = collect($linkedDeviceIds)->map(function (int $id) use ($devices) {
                $device = $devices->get($id);
                return ['id' => $id, 'name' => $device?->mapMarkerTitle() ?? ('#' . $id)];
            })->values()->all();

            // Use the first linked device's odometer for the status estimate.
            $primaryDevice = $devices->get($linkedDeviceIds[0] ?? 0);
            $odometerKm = $this->odometerKm($primaryDevice?->latestLocation?->odometer);

            return $this->formatRow($row, $objects, $odometerKm);
        })->values()->all();
    }

    /**
     * Create or update a maintenance "service" with multiple objects (Traccar parity).
     *
     * @param  array<string, mixed>  $data
     */
    public function save(User $actor, array $data, ?int $maintenanceId = null): ?int
    {
        if (! TraccarSchema::hasTable('tc_maintenances')) {
            return null;
        }

        $deviceIds = $this->tracking->filterAllowedIds($actor, (array) ($data['device_ids'] ?? []));
        if ($deviceIds === []) {
            return null;
        }

        // When editing, ensure the actor owns the maintenance (one of its devices is allowed).
        if ($maintenanceId !== null && ! $this->actorOwnsMaintenance($actor, $maintenanceId)) {
            return null;
        }

        $config = $this->normalizeConfig($data);
        $primary = $this->primaryTrigger($config);

        $payload = TraccarSchema::filterColumns('tc_maintenances', [
            'name' => $config['name'],
            'type' => $primary['type'],
            'start' => $primary['start'],
            'period' => $primary['period'],
            'attributes' => json_encode($config),
        ]);

        if ($maintenanceId !== null) {
            DB::table('tc_maintenances')->where('id', $maintenanceId)->update($payload);
            $id = $maintenanceId;
        } else {
            $id = (int) DB::table('tc_maintenances')->insertGetId($payload);
        }

        if (TraccarSchema::hasTable('tc_device_maintenance')) {
            DB::table('tc_device_maintenance')->where('maintenanceid', $id)->delete();
            $pivot = array_map(fn (int $deviceId) => [
                'deviceid' => $deviceId,
                'maintenanceid' => $id,
            ], $deviceIds);
            DB::table('tc_device_maintenance')->insert($pivot);
        }

        return $id;
    }

    public function delete(User $actor, int $maintenanceId): bool
    {
        if (! TraccarSchema::hasTable('tc_maintenances')) {
            return false;
        }

        if (! $this->actorOwnsMaintenance($actor, $maintenanceId)) {
            return false;
        }

        if (TraccarSchema::hasTable('tc_device_maintenance')) {
            DB::table('tc_device_maintenance')->where('maintenanceid', $maintenanceId)->delete();
        }

        return DB::table('tc_maintenances')->where('id', $maintenanceId)->delete() > 0;
    }

    private function actorOwnsMaintenance(User $actor, int $maintenanceId): bool
    {
        if (! TraccarSchema::hasTable('tc_device_maintenance')) {
            return true;
        }

        $linkedIds = DB::table('tc_device_maintenance')
            ->where('maintenanceid', $maintenanceId)
            ->pluck('deviceid')
            ->map(fn ($id) => (int) $id)
            ->all();

        if ($linkedIds === []) {
            return true;
        }

        $allowed = $this->tracking->allowedDeviceIds($actor);

        return array_intersect($linkedIds, $allowed) !== [];
    }

    /**
     * Build the normalized "Service properties" config stored in attributes.
     *
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private function normalizeConfig(array $data): array
    {
        $bool = fn ($v) => filter_var($v, FILTER_VALIDATE_BOOLEAN);
        $num = fn ($v) => ($v === null || $v === '') ? null : (float) $v;

        return [
            'name' => (string) ($data['name'] ?? 'Service'),
            'data_list' => $bool($data['data_list'] ?? true),
            'popup' => $bool($data['popup'] ?? true),
            'odometer' => [
                'enabled' => $bool($data['odometer_enabled'] ?? false),
                'interval' => $num($data['odometer_interval'] ?? null),
                'last' => $num($data['odometer_last'] ?? null),
            ],
            'hours' => [
                'enabled' => $bool($data['hours_enabled'] ?? false),
                'interval' => $num($data['hours_interval'] ?? null),
                'last' => $num($data['hours_last'] ?? null),
            ],
            'days' => [
                'enabled' => $bool($data['days_enabled'] ?? false),
                'interval' => $num($data['days_interval'] ?? null),
                'last' => $this->nullableDate($data['days_last'] ?? null),
            ],
            'trigger' => [
                'odometer_left' => $bool($data['trigger_odometer'] ?? false),
                'hours_left' => $bool($data['trigger_hours'] ?? false),
                'days_left' => $bool($data['trigger_days'] ?? false),
            ],
            'update_last_service' => $bool($data['update_last_service'] ?? false),
        ];
    }

    /**
     * Pick the Traccar type/start/period from the first enabled interval.
     *
     * @param  array<string, mixed>  $config
     * @return array{type:string, start:float, period:float}
     */
    private function primaryTrigger(array $config): array
    {
        if (! empty($config['odometer']['enabled'])) {
            return [
                'type' => 'totalDistance',
                'start' => (float) ($config['odometer']['last'] ?? 0),
                'period' => (float) ($config['odometer']['interval'] ?? 0),
            ];
        }

        if (! empty($config['hours']['enabled'])) {
            return [
                'type' => 'hours',
                'start' => (float) ($config['hours']['last'] ?? 0),
                'period' => (float) ($config['hours']['interval'] ?? 0),
            ];
        }

        return [
            'type' => 'days',
            'start' => 0.0,
            'period' => (float) ($config['days']['interval'] ?? 0),
        ];
    }

    /**
     * @param  list<array{id:int, name:string}>  $objects
     * @return array<string, mixed>
     */
    private function formatRow(object $row, array $objects, ?float $odometerKm): array
    {
        $config = json_decode((string) ($row->attributes ?? '{}'), true) ?: [];

        // Back-compat: rows created by the old simple form (type/start/period only).
        if (! isset($config['odometer']) && ! isset($config['days']) && ! isset($config['hours'])) {
            $config = $this->configFromLegacyRow($row);
        }

        $status = $this->resolveStatus($config, $odometerKm);

        return [
            'id' => (int) $row->id,
            'name' => (string) ($row->name ?? ($config['name'] ?? '')),
            'objects' => $objects,
            'object_ids' => array_map(fn ($o) => $o['id'], $objects),
            'config' => $config,
            'summary' => $this->summary($config),
            'status' => $status,
            'current_odometer' => $odometerKm,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function configFromLegacyRow(object $row): array
    {
        $type = (string) ($row->type ?? 'totalDistance');
        $attrs = json_decode((string) ($row->attributes ?? '{}'), true) ?: [];

        return [
            'name' => (string) ($row->name ?? ''),
            'data_list' => true,
            'popup' => true,
            'odometer' => [
                'enabled' => $type === 'totalDistance',
                'interval' => $type === 'totalDistance' ? (float) ($row->period ?? 0) : null,
                'last' => $type === 'totalDistance' ? (float) ($row->start ?? 0) : null,
            ],
            'hours' => [
                'enabled' => $type === 'hours',
                'interval' => $type === 'hours' ? (float) ($row->period ?? 0) : null,
                'last' => $type === 'hours' ? (float) ($row->start ?? 0) : null,
            ],
            'days' => [
                'enabled' => $type === 'days',
                'interval' => $type === 'days' ? (float) ($row->period ?? 0) : null,
                'last' => $attrs['last_service'] ?? null,
            ],
            'trigger' => ['odometer_left' => false, 'hours_left' => false, 'days_left' => false],
            'update_last_service' => false,
        ];
    }

    /**
     * @param  array<string, mixed>  $config
     */
    private function summary(array $config): string
    {
        $parts = [];
        if (! empty($config['odometer']['enabled']) && ! empty($config['odometer']['interval'])) {
            $parts[] = rtrim(rtrim(number_format((float) $config['odometer']['interval'], 0), '0'), '.') . ' km';
        }
        if (! empty($config['hours']['enabled']) && ! empty($config['hours']['interval'])) {
            $parts[] = (float) $config['hours']['interval'] . ' h';
        }
        if (! empty($config['days']['enabled']) && ! empty($config['days']['interval'])) {
            $parts[] = (int) $config['days']['interval'] . ' d';
        }

        return implode(' · ', $parts);
    }

    /**
     * @param  array<string, mixed>  $config
     */
    private function resolveStatus(array $config, ?float $odometerKm): string
    {
        $status = 'ok';

        // Odometer interval (km).
        if (! empty($config['odometer']['enabled']) && $odometerKm !== null) {
            $last = (float) ($config['odometer']['last'] ?? 0);
            $interval = (float) ($config['odometer']['interval'] ?? 0);
            if ($interval > 0) {
                $remaining = ($last + $interval) - $odometerKm;
                if ($remaining <= 0) {
                    return 'overdue';
                }
                if ($remaining <= $interval * 0.1) {
                    $status = 'soon';
                }
            }
        }

        // Days interval.
        if (! empty($config['days']['enabled']) && ! empty($config['days']['interval'])) {
            $last = ! empty($config['days']['last']) ? Carbon::parse((string) $config['days']['last']) : null;
            if ($last) {
                $due = $last->copy()->addDays((int) $config['days']['interval']);
                if ($due->isPast()) {
                    return 'overdue';
                }
                if (now()->diffInDays($due, false) <= 7) {
                    $status = 'soon';
                }
            }
        }

        return $status;
    }

    private function odometerKm(mixed $odometer): ?float
    {
        if ($odometer === null || $odometer === '') {
            return null;
        }

        // Traccar stores odometer in meters.
        return round(((float) $odometer) / 1000, 1);
    }

    private function nullableDate(mixed $value): ?string
    {
        if (empty($value)) {
            return null;
        }

        try {
            return Carbon::parse((string) $value)->format('Y-m-d');
        } catch (\Throwable) {
            return null;
        }
    }
}
