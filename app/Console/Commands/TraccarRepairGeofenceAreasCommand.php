<?php

namespace App\Console\Commands;

use App\Models\Geofence;
use App\Support\Traccar\GeofenceWkt;
use App\Support\Traccar\TraccarAppFields;
use App\Support\Traccar\TraccarSchema;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class TraccarRepairGeofenceAreasCommand extends Command
{
    protected $signature = 'traccar:repair-geofence-areas {--link : Re-apply tc_device_geofence and tc_user_geofence links}';

    protected $description = 'Rebuild tc_geofences.area WKT (lat/lng order for Traccar UI) from stored attributes';

    public function handle(\App\Services\Traccar\TraccarGeofenceLinker $linker): int
    {
        if (! TraccarSchema::hasGeofences()) {
            $this->error('tc_geofences not available.');

            return self::FAILURE;
        }

        $table = config('traccar.tables.geofences', 'tc_geofences');
        $fixed = 0;

        foreach (Geofence::query()->orderBy('id')->cursor() as $geofence) {
            $type = $geofence->type;
            $coords = TraccarAppFields::get($geofence->getTraccarAttributesJson(), TraccarAppFields::KEY_GEOFENCE_COORDS);
            $center = TraccarAppFields::get($geofence->getTraccarAttributesJson(), TraccarAppFields::KEY_GEOFENCE_CENTER);
            $radius = TraccarAppFields::get($geofence->getTraccarAttributesJson(), TraccarAppFields::KEY_GEOFENCE_RADIUS);

            $area = GeofenceWkt::fromLaravel($type, $coords, $center, $radius !== null ? (int) $radius : null);

            DB::table($table)->where('id', $geofence->id)->update([
                'area' => $area,
            ]);

            if ($this->option('link') && $geofence->device_id) {
                $device = \App\Models\Device::query()->with('user')->find($geofence->device_id);
                if ($device) {
                    $linker->link($geofence, $device);
                }
            }

            $fixed++;
        }

        $this->info("Rebuilt area WKT for {$fixed} geofence(s). Refresh Traccar web UI (Ctrl+F5).");

        return self::SUCCESS;
    }
}
