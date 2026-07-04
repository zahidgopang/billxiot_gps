<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('subscriptions', function (Blueprint $table) {
            $table->boolean('notification_email_enabled')->default(false)->after('device_selling_price');
            $table->boolean('notification_whatsapp_enabled')->default(false)->after('notification_email_enabled');
            $table->decimal('notification_email_price', 12, 2)->nullable()->after('notification_whatsapp_enabled');
            $table->decimal('notification_whatsapp_price', 12, 2)->nullable()->after('notification_email_price');
        });

        // Preserve alert delivery for subscriptions that were already active before this feature.
        DB::table('subscriptions')
            ->where('status', 'active')
            ->update([
                'notification_email_enabled' => true,
                'notification_whatsapp_enabled' => true,
            ]);
    }

    public function down(): void
    {
        Schema::table('subscriptions', function (Blueprint $table) {
            $table->dropColumn([
                'notification_email_enabled',
                'notification_whatsapp_enabled',
                'notification_email_price',
                'notification_whatsapp_price',
            ]);
        });
    }
};
