<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tracking_tasks', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('device_id')->index();
            $table->unsignedBigInteger('created_by')->nullable();
            $table->string('name');
            $table->string('start')->nullable();
            $table->string('destination')->nullable();
            $table->enum('priority', ['low', 'normal', 'high'])->default('normal');
            $table->enum('status', ['new', 'in_progress', 'done', 'cancelled'])->default('new')->index();
            $table->dateTime('time_from')->nullable();
            $table->dateTime('time_to')->nullable();
            $table->timestamps();

            // device_id references tc_devices.id and created_by references tc_users.id
            // (no FK — those tables are owned by Traccar).
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tracking_tasks');
    }
};
