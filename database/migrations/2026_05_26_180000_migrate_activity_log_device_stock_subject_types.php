<?php

use App\Models\DeviceStockOrder;
use App\Models\DeviceStockUnit;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('activity_log')) {
            return;
        }

        DB::table('activity_log')
            ->where('subject_type', DeviceStockUnit::class)
            ->update(['subject_type' => DeviceStockOrder::class]);
    }

    public function down(): void
    {
        // Irreversible without storing previous values.
    }
};
