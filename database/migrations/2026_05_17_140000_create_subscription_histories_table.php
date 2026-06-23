<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('subscription_histories', function (Blueprint $table) {
            $table->id();
            $table->foreignId('subscription_id')->constrained('subscriptions')->cascadeOnDelete();
            $table->unsignedBigInteger('user_id')->nullable()->index();
            $table->unsignedBigInteger('device_id')->nullable()->index();
            $table->string('plan')->nullable();
            $table->date('starts_at')->nullable();
            $table->date('ends_at')->nullable();
            $table->string('status', 20);
            $table->timestamp('archived_at');
            $table->unsignedBigInteger('archived_by')->nullable()->index();
            $table->timestamps();

            $table->index(['subscription_id', 'archived_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('subscription_histories');
    }
};
