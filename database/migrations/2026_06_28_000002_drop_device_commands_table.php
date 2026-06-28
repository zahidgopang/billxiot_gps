<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Remove the temporary device_commands table.
 *
 * Commands now use Traccar's native tc_commands_queue table. Environments that
 * already ran 2026_06_28_000000_create_device_commands_table need this migration
 * to drop the obsolete table; fresh installs never create it.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::dropIfExists('device_commands');
    }

    public function down(): void
    {
        if (Schema::hasTable('device_commands')) {
            return;
        }

        Schema::create('device_commands', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('device_id')->index();
            $table->string('type', 64);
            $table->text('data')->nullable();
            $table->json('attributes')->nullable();
            $table->string('status', 32)->default('pending');
            $table->unsignedBigInteger('requested_by')->nullable()->index();
            $table->text('result')->nullable();
            $table->timestamp('queued_at')->nullable();
            $table->timestamp('sent_at')->nullable();
            $table->timestamps();
        });
    }
};
