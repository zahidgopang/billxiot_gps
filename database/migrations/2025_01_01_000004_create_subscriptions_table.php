<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::create('subscriptions', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('user_id')->index();
            $table->string('plan')->nullable();
            $table->date('starts_at')->nullable();
            $table->date('ends_at')->nullable();
            $table->enum('status',['active','expired','cancelled'])->default('active')->index();
            $table->timestamps();

            // References tc_users.id (no FK — table owned by Traccar)
        });
    }

    public function down()
    {
        Schema::dropIfExists('subscriptions');
    }
};
