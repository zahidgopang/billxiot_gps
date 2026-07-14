<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Laravel-owned audit trail for device commands.
 *
 * Delivery goes through Traccar POST /api/commands/send. We do not leave pending
 * rows in tc_commands_queue after a successful API send (avoids double delivery).
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('device_command_logs')) {
            return;
        }

        Schema::create('device_command_logs', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('device_id')->index();
            $table->unsignedBigInteger('traccar_device_id')->nullable()->index();
            $table->string('type', 64);
            $table->text('data')->nullable();
            $table->string('status', 32)->default('pending')->index();
            $table->unsignedBigInteger('requested_by')->nullable()->index();
            $table->string('requested_by_name')->nullable();
            $table->text('result')->nullable();
            $table->string('delivery', 32)->default('queue'); // traccar_api | queue
            $table->unsignedBigInteger('queue_id')->nullable()->index();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('device_command_logs');
    }
};
