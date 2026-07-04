<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('subscriptions', function (Blueprint $table) {
            $table->json('notification_email_config')->nullable()->after('notification_whatsapp_price');
            $table->json('notification_whatsapp_config')->nullable()->after('notification_email_config');
        });
    }

    public function down(): void
    {
        Schema::table('subscriptions', function (Blueprint $table) {
            $table->dropColumn([
                'notification_email_config',
                'notification_whatsapp_config',
            ]);
        });
    }
};
