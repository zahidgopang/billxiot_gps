<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('trip_logs', function (Blueprint $table) {
            $table->json('navigation_context')->nullable()->after('checkpoint_timings');
        });
    }

    public function down(): void
    {
        Schema::table('trip_logs', function (Blueprint $table) {
            $table->dropColumn('navigation_context');
        });
    }
};
