<?php

namespace App\Console\Commands;

use App\Models\Device;
use App\Services\Traccar\TraccarSyncService;
use App\Support\Traccar\TraccarSchema;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class TraccarEnableDevicesCommand extends Command
{
    protected $signature = 'traccar:enable-devices
                            {--sync : Re-sync each device from Laravel (updates links + disabled flag)}';

    protected $description = 'Enable all devices in tc_devices (disabled=0) so trackers can connect via Traccar protocol';

    public function handle(TraccarSyncService $sync): int
    {
        if (! TraccarSchema::isReady()) {
            $this->error('Traccar tables not found or TRACCAR_ENABLED is false.');

            return self::FAILURE;
        }

        $table = config('traccar.tables.devices', 'tc_devices');
        $disabledColumn = TraccarSchema::resolveColumn($table, 'disabled');

        if (! $disabledColumn) {
            $this->warn('No disabled column on tc_devices — nothing to update.');

            return self::SUCCESS;
        }

        if ($this->option('sync')) {
            $bar = $this->output->createProgressBar(Device::count());
            $bar->start();
            Device::query()->orderBy('id')->chunkById(50, function ($devices) use ($sync, $bar) {
                foreach ($devices as $device) {
                    $sync->syncDevice($device);
                    $bar->advance();
                }
            });
            $bar->finish();
            $this->newLine();
            $this->info('Devices re-synced from Laravel.');

            return self::SUCCESS;
        }

        $count = DB::table($table)->update([$disabledColumn => 0]);
        $this->info("Set {$disabledColumn}=0 on {$count} row(s) in {$table}.");
        $this->line('Tip: run with --sync to apply Laravel status + tc_user_device links.');

        return self::SUCCESS;
    }
}
