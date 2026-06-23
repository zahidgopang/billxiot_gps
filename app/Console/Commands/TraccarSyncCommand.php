<?php

namespace App\Console\Commands;

use App\Models\Device;
use App\Models\Geofence;
use App\Models\User;
use App\Services\Traccar\TraccarSyncService;
use App\Support\Traccar\TraccarMode;
use App\Support\Traccar\TraccarSchema;
use Illuminate\Console\Command;

class TraccarSyncCommand extends Command
{
    protected $signature = 'traccar:sync
                            {--users : Sync users to tc_users}
                            {--devices : Sync devices to tc_devices}
                            {--geofences : Sync geofences to tc_geofences}';

    protected $description = 'Backfill Laravel tracking entities into Traccar tables';

    public function handle(TraccarSyncService $sync): int
    {
        if (! TraccarMode::isActive()) {
            $this->error('Set TRACCAR_ENABLED=true (and TRACCAR_MODE) in .env before running sync.');

            return self::FAILURE;
        }

        if (! TraccarSchema::isReady()) {
            $this->error('Traccar tables (tc_devices, tc_positions) were not found. Start Traccar once against this database.');

            return self::FAILURE;
        }

        if (config('traccar.sync_log')) {
            $this->warn('TRACCAR_SYNC_LOG=true — check storage/logs/laravel.log for per-user sync details.');
        }

        $syncUsers = $this->option('users') || ! $this->option('devices') && ! $this->option('geofences');
        $syncDevices = $this->option('devices') || ! $this->option('users') && ! $this->option('geofences');
        $syncGeofences = $this->option('geofences');

        $pruned = $sync->pruneStaleEntityMaps();
        if ($pruned > 0) {
            $this->warn("Pruned {$pruned} stale traccar_entity_map row(s) (tc_* row missing).");
        }

        if ($syncDevices) {
            $bar = $this->output->createProgressBar(Device::count());
            $bar->start();
            Device::query()->orderBy('id')->chunkById(100, function ($devices) use ($sync, $bar) {
                foreach ($devices as $device) {
                    $sync->syncDevice($device);
                    $bar->advance();
                }
            });
            $bar->finish();
            $this->newLine();
            $this->info('Devices synced.');
        }

        if ($syncUsers && TraccarSchema::hasUsers()) {
            $bar = $this->output->createProgressBar(User::count());
            $bar->start();
            User::query()->orderBy('id')->chunkById(100, function ($users) use ($sync, $bar) {
                foreach ($users as $user) {
                    $sync->syncUser($user);
                    $bar->advance();
                }
            });
            $bar->finish();
            $this->newLine();
            $this->info('Users synced.');
            $this->line('Traccar UI login uses TRACCAR_DEFAULT_USER_PASSWORD (not the Laravel password).');
        }

        if ($syncGeofences && TraccarSchema::hasGeofences()) {
            $bar = $this->output->createProgressBar(Geofence::count());
            $bar->start();
            Geofence::query()->orderBy('id')->chunkById(100, function ($geofences) use ($sync, $bar) {
                foreach ($geofences as $geofence) {
                    $sync->syncGeofence($geofence, ensureEntities: true);
                    $bar->advance();
                }
            });
            $bar->finish();
            $this->newLine();
            $this->info('Geofences synced.');
        }

        if ($syncUsers || $syncDevices) {
            $repaired = $sync->repairUserDeviceLinks();
            $this->line("Repaired {$repaired} tc_user_device link(s) from Laravel ownership.");
        }

        $this->info('Traccar sync complete.');
        $this->line('If devices vanished in Traccar after geofence edits, run: php artisan traccar:repair-links');

        return self::SUCCESS;
    }
}
