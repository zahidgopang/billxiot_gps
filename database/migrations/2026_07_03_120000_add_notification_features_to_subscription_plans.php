<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('subscription_plans', function (Blueprint $table) {
            $table->json('notification_email_types')->nullable()->after('features');
            $table->json('notification_whatsapp_types')->nullable()->after('notification_email_types');
            $table->json('notification_route_ids')->nullable()->after('notification_whatsapp_types');
        });

        $defaults = array_values(array_filter(
            \App\Services\Tracking\NotificationPreferenceService::controllableTypes(),
            fn (string $type) => $type !== \App\Models\VehicleEvent::TYPE_TRIP_COMPLETED,
        ));

        DB::table('subscription_plans')->update([
            'notification_email_types' => json_encode($defaults),
            'notification_whatsapp_types' => json_encode($defaults),
        ]);

        if (Schema::hasTable('routes')) {
            $routeIds = DB::table('routes')->where('status', 'active')->pluck('id')->all();
            if ($routeIds !== []) {
                DB::table('subscription_plans')->update([
                    'notification_route_ids' => json_encode(array_map('intval', $routeIds)),
                ]);
            }
        }
    }

    public function down(): void
    {
        Schema::table('subscription_plans', function (Blueprint $table) {
            $table->dropColumn([
                'notification_email_types',
                'notification_whatsapp_types',
                'notification_route_ids',
            ]);
        });
    }
};
