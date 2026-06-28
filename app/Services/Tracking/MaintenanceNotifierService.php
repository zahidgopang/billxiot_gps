<?php

namespace App\Services\Tracking;

use App\Contracts\Tracking\EventWriterInterface;
use App\Models\Device;
use App\Models\VehicleEvent;
use App\Services\Push\PushNotificationDispatcher;
use App\Support\Push\PushNotificationType;
use App\Support\Traccar\TraccarSchema;
use Carbon\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

/**
 * Evaluates Traccar maintenances ("services") against live device telemetry and
 * raises a maintenance-due alert (web event + push) once per service cycle.
 *
 * Distance (odometer) and calendar-day triggers are evaluated, mirroring the
 * status shown on the Maintenance screen. Engine-hour triggers are stored but
 * cannot be auto-evaluated until engine-hour telemetry is captured.
 */
class MaintenanceNotifierService
{
    public function __construct(
        private EventWriterInterface $events,
        private PushNotificationDispatcher $push,
        private DevicePositionLoader $positionLoader,
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

        // Distance / odometer trigger.
        $odo = $config['odometer'] ?? [];
        if (! empty($odo['enabled']) && (float) ($odo['interval'] ?? 0) > 0) {
            $odometerKm = $this->odometerKm($location->odometer ?? null);
            if ($odometerKm !== null) {
                $last = (float) ($odo['last'] ?? 0);
                $interval = (float) $odo['interval'];
                $threshold = $last + $interval;

                if ($odometerKm >= $threshold) {
                    $cycle = 'odo.' . (int) round($threshold);
                    $message = (string) __('app.alerts.maintenance_odometer_message', [
                        'device' => $device->notificationDisplayName(),
                        'service' => $name,
                        'odometer' => number_format($odometerKm, 0),
                        'threshold' => number_format($threshold, 0),
                    ]);
                    $emitted += $this->emit($maintenanceId, $device, $cycle, $name, $message, $lat, $lng, [
                        'trigger' => 'odometer',
                        'odometer_km' => $odometerKm,
                        'threshold_km' => $threshold,
                    ]);
                }
            }
        }

        // Calendar-day trigger.
        $days = $config['days'] ?? [];
        if (! empty($days['enabled']) && (int) ($days['interval'] ?? 0) > 0 && ! empty($days['last'])) {
            try {
                $due = Carbon::parse((string) $days['last'])->startOfDay()->addDays((int) $days['interval']);
            } catch (\Throwable) {
                $due = null;
            }

            if ($due && $due->isPast()) {
                $cycle = 'days.' . $due->format('Ymd');
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
     * Record the event + push exactly once per service cycle.
     *
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

        $this->push->forSmartAlert($device, $event, PushNotificationType::MAINTENANCE_DUE);

        // Keep the marker long enough to span the active cycle; a new cycle uses a
        // different key, so updating "last service" re-arms the alert immediately.
        Cache::put($cacheKey, true, now()->addDays(180));

        return 1;
    }

    /**
     * @return array<string, mixed>
     */
    private function decodeConfig(object $row): array
    {
        $config = json_decode((string) ($row->attributes ?? '{}'), true) ?: [];

        // Back-compat: legacy rows stored only type/start/period.
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

    private function odometerKm(mixed $odometer): ?float
    {
        if ($odometer === null || $odometer === '') {
            return null;
        }

        // Traccar stores odometer in meters.
        return round(((float) $odometer) / 1000, 1);
    }
}
