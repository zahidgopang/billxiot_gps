<?php

namespace App\Services\Tracking;

use App\Models\Device;
use App\Models\User;
use App\Services\Authorization\TenantScopeService;
use App\Support\Traccar\TraccarSchema;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class MaintenanceService
{
    public function __construct(
        private GlobalTrackingService $tracking,
        private DevicePositionLoader $positionLoader,
        private TenantScopeService $tenantScope,
        private DeviceOdometerService $odometer,
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

                return ['id' => $id, 'name' => $device?->mapMarkerTitle() ?? ('#'.$id)];
            })->values()->all();

            $primaryDevice = $devices->get($linkedDeviceIds[0] ?? 0);
            $odometerKm = $this->currentOdometerKm($primaryDevice);

            return $this->formatRow($row, $objects, $odometerKm, $primaryDevice);
        })->values()->all();
    }

    /**
     * Due / overdue items for dashboard widgets.
     *
     * @return array{overdue: int, soon: int, items: list<array<string, mixed>>}
     */
    public function dueSummaryForActor(User $actor, int $limit = 8): array
    {
        $items = $this->listForActor($actor);
        $due = array_values(array_filter(
            $items,
            fn (array $row) => in_array($row['status'] ?? '', ['overdue', 'soon'], true)
        ));

        usort($due, function (array $a, array $b) {
            $rank = ['overdue' => 0, 'soon' => 1, 'ok' => 2];

            return ($rank[$a['status']] ?? 9) <=> ($rank[$b['status']] ?? 9);
        });

        return [
            'overdue' => count(array_filter($due, fn ($r) => ($r['status'] ?? '') === 'overdue')),
            'soon' => count(array_filter($due, fn ($r) => ($r['status'] ?? '') === 'soon')),
            'items' => array_slice($due, 0, $limit),
        ];
    }

    /**
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

    /**
     * Mark service completed: set last-service counters from current device values
     * and start a new reminder cycle.
     *
     * @return array<string, mixed>|null
     */
    public function complete(User $actor, int $maintenanceId, ?int $deviceId = null): ?array
    {
        if (! TraccarSchema::hasTable('tc_maintenances')) {
            return null;
        }

        if (! $this->actorOwnsMaintenance($actor, $maintenanceId)) {
            return null;
        }

        $row = DB::table('tc_maintenances')->where('id', $maintenanceId)->first();
        if (! $row) {
            return null;
        }

        $config = $this->decodeConfig($row);
        $linkedIds = $this->linkedDeviceIds($maintenanceId);
        $allowed = $this->tracking->allowedDeviceIds($actor);
        $targetId = $deviceId && in_array($deviceId, $linkedIds, true) && in_array($deviceId, $allowed, true)
            ? $deviceId
            : ($linkedIds[0] ?? null);

        if (! $targetId || ! in_array($targetId, $allowed, true)) {
            return null;
        }

        $device = Device::query()->find($targetId);
        if (! $device) {
            return null;
        }

        $this->positionLoader->attachLatest($device);
        $currentKm = $this->currentOdometerKm($device);
        $useCurrent = filter_var($config['update_last_service'] ?? true, FILTER_VALIDATE_BOOLEAN);

        if ($useCurrent) {
            if (! empty($config['odometer']['enabled']) && $currentKm !== null) {
                $config['odometer']['last'] = round($currentKm, 1);
            }

            if (! empty($config['days']['enabled'])) {
                $config['days']['last'] = now()->format('Y-m-d');
            }

            // Hours: no live telemetry in v1 — advance last by one interval when enabled.
            if (! empty($config['hours']['enabled'])) {
                $lastH = (float) ($config['hours']['last'] ?? 0);
                $intervalH = (float) ($config['hours']['interval'] ?? 0);
                if ($intervalH > 0) {
                    $config['hours']['last'] = round($lastH + $intervalH, 1);
                }
            }
        }

        $primary = $this->primaryTrigger($config);
        $payload = TraccarSchema::filterColumns('tc_maintenances', [
            'type' => $primary['type'],
            'start' => $primary['start'],
            'period' => $primary['period'],
            'attributes' => json_encode($config),
        ]);

        DB::table('tc_maintenances')->where('id', $maintenanceId)->update($payload);

        $fresh = DB::table('tc_maintenances')->where('id', $maintenanceId)->first() ?? $row;

        $objects = collect($linkedIds)->map(function (int $id) use ($allowed) {
            if (! in_array($id, $allowed, true)) {
                return null;
            }
            $d = Device::query()->find($id);

            return ['id' => $id, 'name' => $d?->mapMarkerTitle() ?? ('#'.$id)];
        })->filter()->values()->all();

        return $this->formatRow($fresh, $objects, $currentKm, $device);
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

        $linkedIds = $this->linkedDeviceIds($maintenanceId);
        if ($linkedIds === []) {
            return true;
        }

        $allowed = $this->tracking->allowedDeviceIds($actor);

        return array_intersect($linkedIds, $allowed) !== [];
    }

    /**
     * @return list<int>
     */
    private function linkedDeviceIds(int $maintenanceId): array
    {
        if (! TraccarSchema::hasTable('tc_device_maintenance')) {
            return [];
        }

        return DB::table('tc_device_maintenance')
            ->where('maintenanceid', $maintenanceId)
            ->pluck('deviceid')
            ->map(fn ($id) => (int) $id)
            ->values()
            ->all();
    }

    /**
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
            // Meaning: when marking complete, fill last-service from current counters.
            'update_last_service' => $bool($data['update_last_service'] ?? true),
        ];
    }

    /**
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
    private function formatRow(object $row, array $objects, ?float $odometerKm, ?Device $device = null): array
    {
        $config = $this->decodeConfig($row);
        $metrics = $this->computeMetrics($config, $odometerKm);
        $status = $metrics['status'];

        return [
            'id' => (int) $row->id,
            'name' => (string) ($row->name ?? ($config['name'] ?? '')),
            'objects' => $objects,
            'object_ids' => array_map(fn ($o) => $o['id'], $objects),
            'config' => $config,
            'summary' => $this->summary($config),
            'status' => $status,
            'current_odometer' => $odometerKm,
            'current_odometer_label' => $odometerKm !== null
                ? rtrim(rtrim(number_format($odometerKm, 1, '.', ''), '0'), '.').' km'
                : null,
            'odometer_left' => $metrics['odometer_left'],
            'odometer_left_label' => $metrics['odometer_left_label'],
            'odometer_exceeded_km' => $metrics['odometer_exceeded_km'],
            'days_left' => $metrics['days_left'],
            'days_left_label' => $metrics['days_left_label'],
            'engine_hours' => null,
            'engine_hours_left' => null,
            'engine_hours_label' => null,
            'engine_hours_left_label' => null,
            'can_complete' => $status === 'overdue' || $status === 'soon' || $status === 'ok',
        ];
    }

    /**
     * @param  array<string, mixed>  $config
     * @return array{
     *   status: string,
     *   odometer_left: ?float,
     *   odometer_left_label: ?string,
     *   odometer_exceeded_km: ?float,
     *   days_left: ?int,
     *   days_left_label: ?string
     * }
     */
    public function computeMetrics(array $config, ?float $odometerKm): array
    {
        $status = 'ok';
        $odoLeft = null;
        $odoLeftLabel = null;
        $odoExceeded = null;
        $daysLeft = null;
        $daysLeftLabel = null;

        if (! empty($config['odometer']['enabled']) && $odometerKm !== null) {
            $last = (float) ($config['odometer']['last'] ?? 0);
            $interval = (float) ($config['odometer']['interval'] ?? 0);
            if ($interval > 0) {
                $remaining = round(($last + $interval) - $odometerKm, 1);
                $odoLeft = $remaining;
                if ($remaining <= 0) {
                    $status = 'overdue';
                    $odoExceeded = round(abs($remaining), 1);
                    $odoLeftLabel = (string) __('app.tracking.maint_expired_km', [
                        'km' => rtrim(rtrim(number_format($odoExceeded, 1, '.', ''), '0'), '.'),
                    ]);
                } else {
                    $odoLeftLabel = rtrim(rtrim(number_format($remaining, 1, '.', ''), '0'), '.').' km';
                    if ($remaining <= $interval * 0.1) {
                        $status = 'soon';
                    }
                }
            }
        }

        if (! empty($config['days']['enabled']) && ! empty($config['days']['interval'])) {
            $last = ! empty($config['days']['last']) ? Carbon::parse((string) $config['days']['last'])->startOfDay() : null;
            if ($last) {
                $due = $last->copy()->addDays((int) $config['days']['interval']);
                $diff = (int) now()->startOfDay()->diffInDays($due, false);
                $daysLeft = $diff;
                if ($diff < 0) {
                    $status = 'overdue';
                    $daysLeftLabel = (string) __('app.tracking.maint_expired_days', ['days' => abs($diff)]);
                } else {
                    $daysLeftLabel = (string) __('app.tracking.maint_days_left_value', ['days' => $diff]);
                    if ($diff <= 7 && $status !== 'overdue') {
                        $status = 'soon';
                    }
                }
            }
        }

        return [
            'status' => $status,
            'odometer_left' => $odoLeft,
            'odometer_left_label' => $odoLeftLabel,
            'odometer_exceeded_km' => $odoExceeded,
            'days_left' => $daysLeft,
            'days_left_label' => $daysLeftLabel,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function decodeConfig(object $row): array
    {
        $config = json_decode((string) ($row->attributes ?? '{}'), true) ?: [];

        if (! isset($config['odometer']) && ! isset($config['days']) && ! isset($config['hours'])) {
            return $this->configFromLegacyRow($row);
        }

        return $config;
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
            'update_last_service' => true,
        ];
    }

    /**
     * @param  array<string, mixed>  $config
     */
    private function summary(array $config): string
    {
        $parts = [];
        if (! empty($config['odometer']['enabled']) && ! empty($config['odometer']['interval'])) {
            $parts[] = rtrim(rtrim(number_format((float) $config['odometer']['interval'], 0), '0'), '.').' km';
        }
        if (! empty($config['hours']['enabled']) && ! empty($config['hours']['interval'])) {
            $parts[] = (float) $config['hours']['interval'].' h';
        }
        if (! empty($config['days']['enabled']) && ! empty($config['days']['interval'])) {
            $parts[] = (int) $config['days']['interval'].' d';
        }

        return implode(' · ', $parts);
    }

    private function currentOdometerKm(?Device $device): ?float
    {
        if (! $device) {
            return null;
        }

        $reported = $device->latestLocation?->odometer;
        $km = $this->odometer->displayKm($device, $reported);
        if ($km !== null) {
            return round((float) $km, 1);
        }

        return $device->odometerDisplayKm($reported);
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
