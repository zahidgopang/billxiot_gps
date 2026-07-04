<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('trip_logs', function (Blueprint $table) {
            $table->decimal('start_lat', 10, 7)->nullable()->after('started_at');
            $table->decimal('start_lng', 10, 7)->nullable()->after('start_lat');
            $table->decimal('expected_distance_km', 10, 3)->nullable()->after('distance_travelled_km');
            $table->decimal('actual_distance_km', 10, 3)->default(0)->after('expected_distance_km');
            $table->decimal('extra_distance_km', 10, 3)->default(0)->after('actual_distance_km');
            $table->decimal('corridor_progress_km', 10, 3)->default(0)->after('extra_distance_km');
            $table->text('actual_route_polyline')->nullable()->after('navigation_context');
            $table->text('navigation_route_polyline')->nullable()->after('actual_route_polyline');
            $table->json('trip_statistics')->nullable()->after('navigation_route_polyline');
            $table->json('trip_summary')->nullable()->after('trip_statistics');
        });
    }

    public function down(): void
    {
        Schema::table('trip_logs', function (Blueprint $table) {
            $table->dropColumn([
                'start_lat',
                'start_lng',
                'expected_distance_km',
                'actual_distance_km',
                'extra_distance_km',
                'corridor_progress_km',
                'actual_route_polyline',
                'navigation_route_polyline',
                'trip_statistics',
                'trip_summary',
            ]);
        });
    }
};
