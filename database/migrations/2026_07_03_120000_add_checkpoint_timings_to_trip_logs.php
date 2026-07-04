<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('trip_logs', function (Blueprint $table) {
            $table->json('checkpoint_timings')->nullable()->after('last_lng');
        });
    }

    public function down(): void
    {
        Schema::table('trip_logs', function (Blueprint $table) {
            $table->dropColumn('checkpoint_timings');
        });
    }
};
