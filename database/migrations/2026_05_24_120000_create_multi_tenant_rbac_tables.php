<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('clients', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('slug')->unique();
            $table->string('status', 20)->default('active');
            $table->unsignedBigInteger('created_by')->nullable()->index();
            $table->json('settings')->nullable();
            $table->timestamps();

            $table->index('status');
        });

        Schema::create('client_members', function (Blueprint $table) {
            $table->id();
            $table->foreignId('client_id')->constrained('clients')->cascadeOnDelete();
            $table->unsignedBigInteger('user_id')->index();
            $table->string('membership', 32)->default('member');
            $table->timestamps();

            $table->unique(['client_id', 'user_id']);
        });

        Schema::create('client_devices', function (Blueprint $table) {
            $table->id();
            $table->foreignId('client_id')->constrained('clients')->cascadeOnDelete();
            $table->unsignedBigInteger('device_id')->index();
            $table->timestamps();

            $table->unique(['client_id', 'device_id']);
            $table->unique('device_id');
        });

        Schema::create('admin_client_scopes', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('admin_user_id')->index();
            $table->foreignId('client_id')->constrained('clients')->cascadeOnDelete();
            $table->timestamps();

            $table->unique(['admin_user_id', 'client_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('admin_client_scopes');
        Schema::dropIfExists('client_devices');
        Schema::dropIfExists('client_members');
        Schema::dropIfExists('clients');
    }
};
