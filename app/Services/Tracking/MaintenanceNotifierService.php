<?php

namespace App\Services\Tracking;

use App\Contracts\Tracking\EventWriterInterface;
use App\Models\Device;
use App\Models\User;
use App\Models\VehicleEvent;
use App\Services\Push\PushNotificationDispatcher;
use App\Support\Traccar\TraccarSchema;
use Carbon\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

/**
 * Evaluates Traccar maintenances against live device telemetry and raises a
 * maintenance-due alert (web event + push) once per service cycle.
 *
 * Cycles advance only when the account owner marks the service completed —
 * overdue services keep reminding until then.
 */
class MaintenanceNotifierService
{
    public function __construct(
        private EventWriterInterface $events,
        private PushNotificationDispatcher $push,
        private DevicePositionLoader $positionLoader,
        private DeviceOdometerService $odometer,
        private TrackingSettingsService $trackingSettings,
    ) {}

    /**
     * @return int Number of maintenance-due alerts emitted this run.
     */
    public function run(): int
    {
        if (! TraccarSchema::hasTable('tc_maintenances') || ! TraccarSchema::hasTable('tc_device_maintenance')) {
            return 0;
        }

        $rows = DB::table('tc_maintenances')->get();
        if ($rows->isEmpty()) {
            return 0;
        }

        $links = DB::table('tc_device_maintenance')
            ->get()
            ->groupBy('maintenanceid');

        $deviceIds = $links->flatten(1)
            ->pluck('deviceid')
            ->map(fn ($id) => (int) $id)
            ->unique()
            ->values()
            ->all();

        if ($deviceIds === []) {
            return 0;
        }

        $devices = Device::query()->whereIn('id', $deviceIds)->get()->keyBy('id');
        $this->positionLoader->attachLatestToMany($devices);

        $emitted = 0;

        foreach ($rows as $row) {
            $maintenanceId = (int) $row->id;
            $config = $this->decodeConfig($row);
            $name = (string) ($config['name'] ?? $row->name ?? 'Service');

            $linked = $links->get($maintenanceId, collect())
                ->pluck('deviceid')
                ->map(fn ($id) => (int) $id);

            foreach ($linked as $deviceId) {
                $device = $devices->get($deviceId);
                if (! $device || $device->status !== 'active') {
                    continue;
                }

                $emitted += $this->evaluateDevice($maintenanceId, $name, $config, $device);
            }
        }

        return $emitted;
    }

    /**
     * @param  array<string, mixed>  $config
     */
    private function evaluateDevice(int $maintenanceId, string $name, array $config, Device $device): int
    {
        $emitted = 0;
        $location = $device->latestLocation;
        $lat = (float) ($location->lat ?? 0);
        $lng = (float) ($location->lng ?? 0);

        $odo = $config['odometer'] ?? [];
        if (! empty($odo['enabled']) && (float) ($odo['interval'] ?? 0) > 0) {
            $odometerKm = $this->odometer->displayKm($device, $location->odometer ?? null);
            if ($odometerKm === null) {
                $odometerKm = $device->odometerDisplayKm($location->odometer ?? null);
            }
            if ($odometerKm !== null) {
                $last = (float) ($odo['last'] ?? 0);
                $interval = (float) $odo['interval'];
                $cycleInfo = $this->dueOdometerCycle($last, $interval, $odometerKm);

                if ($cycleInfo !== null) {
                    $threshold = $cycleInfo['threshold'];
                    // Stable cycle key until last-service is manually updated.
                    $cycle = 'odo.last.'.(int) round($last).'.iv.'.(int) round($interval);
                    $exceeded = max(0, round($odometerKm - $threshold, 1));
                    $message = (string) __('app.alerts.maintenance_odometer_message', [
                        'device' => $device->notificationDisplayName(),
                        'service' => $name,
                        'odometer' => number_format($odometerKm, 0),
                        'threshold' => number_format($threshold, 0),
                    ]);
                    if ($exceeded > 0) {
                        $message .= ' '.(string) __('app.alerts.maintenance_expired_suffix', [
                            'km' => number_format($exceeded, 0),
                        ]);
                    }
                    $emitted += $this->emit($maintenanceId, $device, $cycle, $name, $message, $lat, $lng, [
                        'trigger' => 'odometer',
                        'odometer_km' => $odometerKm,
                        'threshold_km' => $threshold,
                        'exceeded_km' => $exceeded,
                    ]);
                }
            }
        }

        $days = $config['days'] ?? [];
        if (! empty($days['enabled']) && (int) ($days['interval'] ?? 0) > 0 && ! empty($days['last'])) {
            try {
                $due = Carbon::parse((string) $days['last'])->startOfDay()->addDays((int) $days['interval']);
            } catch (\Throwable) {
                $due = null;
            }

            if ($due && $due->isPast()) {
                // Stable until last service date is manually completed.
                $cycle = 'days.last.'.Carbon::parse((string) $days['last'])->format('Ymd')
                    .'.iv.'.(int) $days['interval'];
                $message = (string) __('app.alerts.maintenance_days_message', [
                    'device' => $device->notificationDisplayName(),
                    'service' => $name,
                    'date' => $due->format('Y-m-d'),
                ]);
                $emitted += $this->emit($maintenanceId, $device, $cycle, $name, $message, $lat, $lng, [
                    'trigger' => 'days',
                    'due_date' => $due->format('Y-m-d'),
                ]);
            }
        }

        return $emitted;
    }

    /**
     * @return array{threshold: float}|null
     */
    public function dueOdometerCycle(float $last, float $interval, float $odometerKm): ?array
    {
        if ($interval <= 0 || $odometerKm < ($last + $interval)) {
            return null;
        }

        return [
            'threshold' => round($last + $interval, 1),
        ];
    }

    public function effectiveDueDate(string $last, int $intervalDays, Carbon $now): ?Carbon
    {
        if ($intervalDays <= 0) {
            return null;
        }

        try {
            $lastDate = Carbon::parse($last)->startOfDay();
        } catch (\Throwable) {
            return null;
        }

        $firstDue = $lastDate->copy()->addDays($intervalDays);
        if ($firstDue->greaterThan($now)) {
            return null;
        }

        return $firstDue;
    }

    /**
     * @param  array<string, mixed>  $meta
     */
    private function emit(
        int $maintenanceId,
        Device $device,
        string $cycle,
        string $name,
        string $message,
        float $lat,
        float $lng,
        array $meta,
    ): int {
        $cacheKey = "maint.{$maintenanceId}.dev.{$device->id}.{$cycle}";
        if (Cache::has($cacheKey)) {
            return 0;
        }

        $title = (string) __('app.alerts.maintenance_title', ['service' => $name]);

        $event = $this->events->record(
            $device,
            VehicleEvent::TYPE_MAINTENANCE,
            $title,
            $message,
            null,
            $lat,
            $lng,
            now(),
            null,
            array_merge(['maintenance_id' => $maintenanceId, 'service' => $name], $meta),
        );

        $recipientIds = $this->resolveMaintenanceRecipientIds($device);
        $this->push->forMaintenance($device, $event, $recipientIds);

        // Remind periodically while still overdue (same cycle) — every 24h.
        Cache::put($cacheKey, true, now()->addDay());

        return 1;
    }

    /**
     * Owner always; sub-accounts only when the account owner enables the setting.
     *
     * @return list<int>
     */
    public function resolveMaintenanceRecipientIds(Device $device): array
    {
        $ownerId = $device->resolveTraccarOwnerUserId();
        $ids = [];

        if ($ownerId) {
            $ids[] = (int) $ownerId;
        }

        $owner = $ownerId ? User::query()->find((int) $ownerId) : null;
        $notifySubs = false;
        if ($owner) {
            $settings = $this->trackingSettings->forActor($owner);
            $notifySubs = filter_var($settings['maintenance_notify_sub_accounts'] ?? false, FILTER_VALIDATE_BOOLEAN);
        }

        if ($notifySubs) {
            $relation = $device->users();
            $userKey = $relation->getRelated()->getQualifiedKeyName();
            $linked = $relation
                ->pluck($userKey)
                ->map(fn ($id) => (int) $id)
                ->filter(fn ($id) => $id > 0)
                ->all();

            foreach ($linked as $userId) {
                if ($ownerId && (int) $userId === (int) $ownerId) {
                    continue;
                }
                $user = User::query()->find($userId);
                if ($user && $user->isSubAccount()) {
                    $parentId = $user->parentUserId();
                    if ($ownerId && $parentId && (int) $parentId === (int) $ownerId) {
                        $ids[] = (int) $userId;
                    }
                }
            }
        }

        // Staff/admin map viewers still receive operational alerts.
        $staffIds = app(\App\Services\Authorization\TenantScopeService::class)->staffPushRecipientIds($device);

        return array_values(array_unique(array_merge($ids, $staffIds)));
    }

    /**
     * @return array<string, mixed>
     */
    private function decodeConfig(object $row): array
    {
        $config = json_decode((string) ($row->attributes ?? '{}'), true) ?: [];

        if (! isset($config['odometer']) && ! isset($config['days']) && ! isset($config['hours'])) {
            $type = (string) ($row->type ?? '');

            return [
                'name' => (string) ($row->name ?? ''),
                'odometer' => [
                    'enabled' => $type === 'totalDistance',
                    'interval' => $type === 'totalDistance' ? (float) ($row->period ?? 0) : null,
                    'last' => $type === 'totalDistance' ? (float) ($row->start ?? 0) : null,
                ],
                'days' => [
                    'enabled' => $type === 'days',
                    'interval' => $type === 'days' ? (float) ($row->period ?? 0) : null,
                    'last' => $config['last_service'] ?? null,
                ],
            ];
        }

        return $config;
    }
}
