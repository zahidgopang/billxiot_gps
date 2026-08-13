<?php

namespace App\Console\Commands;

use App\Models\Device;
use App\Models\TraccarEntityMap;
use App\Services\Push\PushNotificationDispatcher;
use App\Services\Traccar\TraccarIdMap;
use App\Support\DateTime\AppDateTime;
use App\Support\Push\PushNotificationType;
use App\Support\Traccar\TraccarSchema;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Backup path: Traccar-native geofenceEnter/Exit rows in tc_events → FCM.
 * Needed when Laravel point-in-polygon misses a crossing, or forward debounce skipped it.
 */
class TraccarDispatchGeofencePushesCommand extends Command
{
    protected $signature = 'traccar:dispatch-geofence-pushes {--limit=100 : Max events per run}';

    protected $description = 'Send mobile push for new Traccar geofenceEnter / geofenceExit events';

    private const CACHE_KEY = 'traccar:geofence_push:last_event_id';

    public function handle(
        PushNotificationDispatcher $dispatcher,
        TraccarIdMap $idMap,
    ): int {
        $geofenceOn = filter_var(config('firebase.geofence_notifications_enabled', true), FILTER_VALIDATE_BOOL)
            || filter_var(config('services.firebase.geofence_notifications_enabled', true), FILTER_VALIDATE_BOOL);
        if (! $geofenceOn) {
            return self::SUCCESS;
        }

        if (! TraccarSchema::hasEvents()) {
            return self::SUCCESS;
        }

        $table = config('traccar.tables.events', 'tc_events');
        $limit = max(1, min(500, (int) $this->option('limit')));
        $lastId = (int) Cache::get(self::CACHE_KEY, 0);

        if ($lastId < 1) {
            $lastId = (int) (DB::table($table)->max('id') ?? 0);
            Cache::forever(self::CACHE_KEY, $lastId);
            $this->info("Seeded geofence push cursor at event #{$lastId}");

            return self::SUCCESS;
        }

        $rows = DB::table($table)
            ->where('id', '>', $lastId)
            ->whereIn('type', ['geofenceEnter', 'geofenceExit'])
            ->orderBy('id')
            ->limit($limit)
            ->get(['id', 'deviceid', 'type', 'geofenceid', 'eventtime']);

        if ($rows->isEmpty()) {
            return self::SUCCESS;
        }

        $sent = 0;
        $maxSeen = $lastId;

        foreach ($rows as $row) {
            $maxSeen = max($maxSeen, (int) $row->id);
            $traccarDeviceId = (int) ($row->deviceid ?? 0);
            $geofenceId = (int) ($row->geofenceid ?? 0);
            if ($traccarDeviceId < 1 || $geofenceId < 1) {
                continue;
            }

            $deviceId = $idMap->laravelId(TraccarEntityMap::TYPE_DEVICE, $traccarDeviceId)
                ?? $traccarDeviceId;
            $device = Device::query()->find($deviceId);
            if (! $device) {
                continue;
            }

            $isEnter = (string) $row->type === 'geofenceEnter';
            $pushType = $isEnter
                ? PushNotificationType::GEOFENCE_ENTER
                : PushNotificationType::GEOFENCE_EXIT;
            $zoneName = $this->geofenceName($geofenceId);
            $at = AppDateTime::parse($row->eventtime ?? now());
            $message = $isEnter
                ? sprintf('%s entered geofence "%s".', $device->notificationDisplayName(), $zoneName)
                : sprintf('%s exited geofence "%s".', $device->notificationDisplayName(), $zoneName);

            $dedupeKey = "device.{$device->id}.geofence.{$pushType}.{$geofenceId}";
            if (Cache::has($dedupeKey)) {
                continue;
            }
            Cache::put($dedupeKey, true, now()->addSeconds(90));

            try {
                $dispatcher->forGeofence(
                    $device,
                    $pushType,
                    $isEnter ? 'Geofence enter' : 'Geofence exit',
                    $message,
                    $geofenceId,
                    $at,
                    (int) $row->id,
                );
                $sent++;
            } catch (\Throwable $e) {
                report($e);
                Log::channel(config('firebase.log_channel', 'stack'))
                    ->warning('Geofence push from tc_events failed', [
                        'event_id' => (int) $row->id,
                        'device_id' => $device->id,
                        'error' => $e->getMessage(),
                    ]);
            }
        }

        Cache::forever(self::CACHE_KEY, $maxSeen);

        if ($this->output->isVerbose()) {
            $this->line("Geofence push: scanned {$rows->count()} event(s), dispatched {$sent}.");
        }

        return self::SUCCESS;
    }

    private function geofenceName(int $geofenceId): string
    {
        $table = config('traccar.tables.geofences', 'tc_geofences');
        if (! TraccarSchema::hasGeofences()) {
            return 'Zone';
        }

        $name = DB::table($table)->where('id', $geofenceId)->value('name');

        return is_string($name) && $name !== '' ? $name : 'Zone';
    }
}
