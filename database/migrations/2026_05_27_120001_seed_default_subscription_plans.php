<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('subscription_plans')) {
            return;
        }

        $now = now();
        $plans = [
            [
                'name' => 'Basic',
                'slug' => 'basic',
                'duration_months' => 1,
                'company_price' => 5.00,
                'features' => json_encode(['Live GPS tracking', '7-day history', 'Email alerts']),
            ],
            [
                'name' => 'Standard',
                'slug' => 'standard',
                'duration_months' => 1,
                'company_price' => 10.00,
                'features' => json_encode(['Everything in Basic', '30-day history', 'Geofences', 'Reports']),
            ],
            [
                'name' => 'Premium',
                'slug' => 'premium',
                'duration_months' => 1,
                'company_price' => 15.00,
                'features' => json_encode(['Everything in Standard', '1-year history', 'Priority support', 'API access']),
            ],
        ];

        foreach ($plans as $i => $plan) {
            if (DB::table('subscription_plans')->where('slug', $plan['slug'])->exists()) {
                continue;
            }

            DB::table('subscription_plans')->insert(array_merge($plan, [
                'description' => null,
                'currency' => 'USD',
                'status' => 'active',
                'is_public' => true,
                'sort_order' => $i,
                'created_at' => $now,
                'updated_at' => $now,
            ]));
        }
    }

    public function down(): void
    {
        if (! Schema::hasTable('subscription_plans')) {
            return;
        }

        DB::table('subscription_plans')->whereIn('slug', ['basic', 'standard', 'premium'])->delete();
    }
};
