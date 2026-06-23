<?php

namespace App\Console\Commands;

use App\Support\Traccar\TraccarMode;
use App\Support\Traccar\TraccarSchema;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class TraccarLegacyStatusCommand extends Command
{
    protected $signature = 'traccar:legacy-status';

    protected $description = 'Report whether legacy tracking tables are still receiving writes (pre-drop checklist)';

    public function handle(): int
    {
        $this->info('Traccar mode: ' . TraccarMode::current());
        $this->info('Single source: ' . (TraccarMode::isSingleSource() ? 'yes' : 'no'));
        $this->info('Writes legacy tables: ' . (TraccarMode::writesLegacy() ? 'yes' : 'no'));

        if (! TraccarSchema::isReady()) {
            $this->error('tc_devices / tc_positions not found.');

            return self::FAILURE;
        }

        $checks = [
            'device_locations' => config('traccar.deprecated_tables.device_locations', 'device_locations'),
            'vehicle_events' => config('traccar.deprecated_tables.vehicle_events', 'vehicle_events'),
            'geofence_events' => config('traccar.deprecated_tables.geofence_events', 'geofence_events'),
        ];

        foreach ($checks as $label => $table) {
            if (! Schema::hasTable($table)) {
                $this->line("  [skip] {$table} — not present");
                continue;
            }

            $latest = match ($table) {
                'device_locations' => DB::table($table)->max('recorded_at'),
                'vehicle_events' => DB::table($table)->max('occurred_at'),
                default => DB::table($table)->max('created_at'),
            };

            $count = DB::table($table)->count();
            $this->line("  {$table}: {$count} rows, latest={$latest}");
        }

        $tcPos = DB::table(config('traccar.tables.positions', 'tc_positions'))->count();
        $tcEv = Schema::hasTable(config('traccar.tables.events', 'tc_events'))
            ? DB::table(config('traccar.tables.events', 'tc_events'))->count()
            : 0;

        $this->newLine();
        $this->info("tc_positions: {$tcPos} rows");
        $this->info("tc_events: {$tcEv} rows");

        if (TraccarMode::isSingleSource() && ! TraccarMode::writesLegacy()) {
            $this->info('Configuration OK for single-source (no legacy writes).');
        } else {
            $this->warn('Not in full single-source mode — legacy tables may still receive writes.');
        }

        return self::SUCCESS;
    }
}
