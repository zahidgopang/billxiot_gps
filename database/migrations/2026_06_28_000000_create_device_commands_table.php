<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('device_commands', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('device_id')->index();
            $table->string('type', 64);
            $table->text('data')->nullable();
            $table->json('attributes')->nullable();
            $table->string('status', 16)->default('pending');
            $table->unsignedBigInteger('requested_by')->nullable();
            $table->text('result')->nullable();
            $table->timestamp('queued_at')->nullable();
            $table->timestamp('sent_at')->nullable();
            $table->timestamps();

            // No FK: `devices` is a view over tc_devices under unified Traccar ids.
            $table->index(['device_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('device_commands');
    }
};
