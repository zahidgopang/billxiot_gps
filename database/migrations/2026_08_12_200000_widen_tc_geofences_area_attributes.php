<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Traccar default tc_geofences.area/attributes are varchar(4096/4000).
 * Detailed polygons + app shape JSON exceed that and INSERT fails — geofence looks "not saved".
 */
return new class extends Migration
{
    public function up(): void
    {
        $table = config('traccar.tables.geofences', 'tc_geofences');
        if (! Schema::hasTable($table)) {
            return;
        }

        // Avoid doctrine/dbal requirement for ->change().
        if (Schema::hasColumn($table, 'area')) {
            DB::statement("ALTER TABLE `{$table}` MODIFY `area` MEDIUMTEXT NOT NULL");
        }
        if (Schema::hasColumn($table, 'attributes')) {
            DB::statement("ALTER TABLE `{$table}` MODIFY `attributes` MEDIUMTEXT NULL");
        }
    }

    public function down(): void
    {
        $table = config('traccar.tables.geofences', 'tc_geofences');
        if (! Schema::hasTable($table)) {
            return;
        }

        if (Schema::hasColumn($table, 'area')) {
            DB::statement("ALTER TABLE `{$table}` MODIFY `area` VARCHAR(4096) NOT NULL");
        }
        if (Schema::hasColumn($table, 'attributes')) {
            DB::statement("ALTER TABLE `{$table}` MODIFY `attributes` VARCHAR(4000) NULL");
        }
    }
};
