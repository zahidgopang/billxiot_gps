<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('clients', function (Blueprint $table) {
            $table->boolean('can_track_maps')->default(false)->after('status');
        });

        // Preserve existing behaviour for companies already in the system.
        DB::table('clients')->update(['can_track_maps' => true]);
    }

    public function down(): void
    {
        Schema::table('clients', function (Blueprint $table) {
            $table->dropColumn('can_track_maps');
        });
    }
};
