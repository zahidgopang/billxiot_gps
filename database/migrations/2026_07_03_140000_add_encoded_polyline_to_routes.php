<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('routes', function (Blueprint $table) {
            $table->longText('encoded_polyline')->nullable()->after('show_polyline');
            $table->decimal('guided_distance_km', 10, 3)->nullable()->after('encoded_polyline');
            $table->unsignedInteger('guided_duration_minutes')->nullable()->after('guided_distance_km');
            $table->timestamp('polyline_generated_at')->nullable()->after('guided_duration_minutes');
        });
    }

    public function down(): void
    {
        Schema::table('routes', function (Blueprint $table) {
            $table->dropColumn([
                'encoded_polyline',
                'guided_distance_km',
                'guided_duration_minutes',
                'polyline_generated_at',
            ]);
        });
    }
};
