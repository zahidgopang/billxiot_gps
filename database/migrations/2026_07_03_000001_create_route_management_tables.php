<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('routes', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('start_city');
            $table->string('destination_city');
            $table->decimal('start_lat', 10, 7);
            $table->decimal('start_lng', 10, 7);
            $table->decimal('destination_lat', 10, 7);
            $table->decimal('destination_lng', 10, 7);
            $table->decimal('expected_distance_km', 10, 2)->default(0);
            $table->unsignedInteger('expected_duration_minutes')->default(0);
            $table->unsignedInteger('arrival_radius_meters')->default(500);
            $table->boolean('show_polyline')->default(true);
            $table->boolean('show_progress_bar')->default(true);
            $table->boolean('auto_complete_on_arrival')->default(false);
            $table->string('status', 20)->default('active');
            $table->timestamps();

            $table->index('status');
        });

        Schema::create('route_checkpoints', function (Blueprint $table) {
            $table->id();
            $table->foreignId('route_id')->constrained('routes')->cascadeOnDelete();
            $table->unsignedSmallInteger('sequence')->default(1);
            $table->string('from_location');
            $table->string('to_location');
            $table->decimal('from_lat', 10, 7);
            $table->decimal('from_lng', 10, 7);
            $table->decimal('to_lat', 10, 7);
            $table->decimal('to_lng', 10, 7);
            $table->decimal('distance_km', 10, 2)->default(0);
            $table->unsignedInteger('expected_duration_minutes')->default(0);
            $table->unsignedSmallInteger('speed_limit_kmh')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->unique(['route_id', 'sequence']);
            $table->index(['route_id', 'sequence']);
        });

        Schema::create('device_route_assignments', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('device_id')->unique();
            $table->foreignId('route_id')->constrained('routes')->cascadeOnDelete();
            $table->timestamps();
        });

        Schema::create('trip_logs', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('device_id')->index();
            $table->foreignId('route_id')->constrained('routes')->cascadeOnDelete();
            $table->unsignedSmallInteger('current_checkpoint_sequence')->nullable();
            $table->string('current_segment_label')->nullable();
            $table->decimal('percentage_completed', 5, 2)->default(0);
            $table->decimal('distance_travelled_km', 10, 3)->default(0);
            $table->decimal('remaining_distance_km', 10, 3)->default(0);
            $table->timestamp('eta_at')->nullable();
            $table->string('status', 32)->default('not_started');
            $table->timestamp('started_at')->nullable();
            $table->timestamp('reached_destination_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->decimal('last_lat', 10, 7)->nullable();
            $table->decimal('last_lng', 10, 7)->nullable();
            $table->timestamps();

            $table->index(['device_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('trip_logs');
        Schema::dropIfExists('device_route_assignments');
        Schema::dropIfExists('route_checkpoints');
        Schema::dropIfExists('routes');
    }
};
