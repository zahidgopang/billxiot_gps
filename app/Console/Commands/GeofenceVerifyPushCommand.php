<?php

namespace App\Console\Commands;

use App\Models\Device;
use App\Models\User;
use App\Models\UserPushToken;
use App\Services\Push\PushNotificationDispatcher;
use App\Support\Push\PushNotificationType;
use App\Support\Traccar\TraccarSchema;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Post-deploy smoke check for geofence enter/exit mobile push.
 *
 *   php artisan geofence:verify-push
 *   php artisan geofence:verify-push --send --user=8 --device=9
 */
class GeofenceVerifyPushCommand extends Command
{
    protected $signature = 'geofence:verify-push
        {--send : Send a real geofence_enter test push}
        {--user= : User id for --send}
        {--device= : Device id for --send}';

    protected $description = 'Verify geofence enter/exit push config, links, and optionally send a test FCM';

    public function handle(PushNotificationDispatcher $dispatcher): int
    {
        $ok = true;

        $this->info('=== Geofence push readiness ===');

        $checks = [
            'PUSH_NOTIFICATIONS_ENABLED' => filter_var(config('firebase.enabled', config('services.firebase.enabled')), FILTER_VALIDATE_BOOL),
            'PUSH_GEOFENCE_NOTIFICATIONS_ENABLED' => filter_var(config('firebase.geofence_notifications_enabled', true), FILTER_VALIDATE_BOOL)
                || filter_var(config('services.firebase.geofence_notifications_enabled', true), FILTER_VALIDATE_BOOL),
            'TRACKING_LARAVEL_GEOFENCE' => (bool) config('tracking.laravel_geofence_detection', true),
            'TRACCAR_FORWARD_PROCESS_EVENTS' => filter_var(config('traccar.forward.process_events', true), FILTER_VALIDATE_BOOL),
            'deliverViaPush(geofence_enter)' => PushNotificationType::deliverViaPush(PushNotificationType::GEOFENCE_ENTER),
            'deliverViaPush(geofence_exit)' => PushNotificationType::deliverViaPush(PushNotificationType::GEOFENCE_EXIT),
        ];

        foreach ($checks as $label => $pass) {
            $this->line(($pass ? '[OK]  ' : '[FAIL] ').$label.($pass ? '' : '  ← fix this'));
            $ok = $ok && $pass;
        }

        $this->line('TRACKING_PUSH_MAJOR_STATUS_ONLY='.(config('tracking.push_major_status_only') ? 'true' : 'false')
            .' (geofence bypasses this in code)');

        $geoTable = config('traccar.tables.geofences', 'tc_geofences');
        $linkTable = config('traccar.tables.device_geofence', 'tc_device_geofence');
        $geoCount = Schema::hasTable($geoTable) ? (int) DB::table($geoTable)->count() : 0;
        $linkCount = Schema::hasTable($linkTable) ? (int) DB::table($linkTable)->count() : 0;
        $this->line("Geofences in DB: {$geoCount}");
        $this->line("Device↔geofence links: {$linkCount}");

        if ($geoCount < 1 || $linkCount < 1) {
            $this->error('[FAIL] No geofence linked to a vehicle — create/save a geofence for the device first.');
            $ok = false;
        } else {
            $this->info('[OK]  At least one geofence is linked to a device');
        }

        if (! TraccarSchema::hasEvents()) {
            $this->warn('[WARN] tc_events missing — Traccar backup push path unavailable (Laravel PIP still works)');
        } else {
            $this->info('[OK]  tc_events available (backup push scheduler path)');
        }

        $tokenCount = UserPushToken::query()->count();
        $this->line("FCM tokens registered: {$tokenCount}");
        if ($tokenCount < 1) {
            $this->error('[FAIL] No FCM tokens — user must open the mobile app while logged in');
            $ok = false;
        }

        if ($this->option('send')) {
            $userId = (int) $this->option('user');
            $deviceId = (int) $this->option('device');
            if ($userId < 1 || $deviceId < 1) {
                $this->error('Provide --user=ID and --device=ID with --send');

                return self::FAILURE;
            }

            $user = User::query()->find($userId);
            $device = Device::query()->find($deviceId);
            if (! $user || ! $device) {
                $this->error('User or device not found');

                return self::FAILURE;
            }

            $tokens = UserPushToken::query()->where('user_id', $userId)->count();
            $this->line("Sending test geofence_enter to user #{$userId} ({$tokens} token(s)) for device #{$deviceId}…");

            $dispatcher->forGeofence(
                $device,
                PushNotificationType::GEOFENCE_ENTER,
                'Geofence enter',
                sprintf('%s entered geofence "TEST ZONE" (deploy verify).', $device->notificationDisplayName()),
                0,
                now(),
                null,
            );

            $this->info('Test geofence push dispatched. Check the phone + storage/logs/push-*.log');
        }

        $this->newLine();
        if ($ok) {
            $this->info('READY — drive vehicle across the geofence boundary to receive enter/exit push.');
            $this->line('Also ensure cron runs: * * * * * php artisan schedule:run');

            return self::SUCCESS;
        }

        $this->error('NOT READY — fix FAIL items above, then re-run this command.');

        return self::FAILURE;
    }
}
