<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('push_notification_logs', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('user_id')->nullable();
            $table->string('fcm_token_hash', 64)->nullable();
            $table->string('push_type', 64)->nullable();
            $table->string('title')->nullable();
            $table->text('body')->nullable();
            $table->string('status', 16);
            $table->unsignedSmallInteger('http_status')->nullable();
            $table->text('error_message')->nullable();
            $table->json('response')->nullable();
            $table->json('data')->nullable();
            $table->timestamp('created_at')->useCurrent();

            $table->index(['user_id', 'created_at']);
            $table->index(['status', 'created_at']);
            $table->index('push_type');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('push_notification_logs');
    }
};
