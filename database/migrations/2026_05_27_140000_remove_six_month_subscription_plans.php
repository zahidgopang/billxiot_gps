<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

return new class extends Migration
{
    public function up(): void
    {
        if (! \Illuminate\Support\Facades\Schema::hasTable('subscription_plans')) {
            return;
        }

        $plans = DB::table('subscription_plans')
            ->where('duration_months', 6)
            ->get();

        foreach ($plans as $plan) {
            $base = Str::slug((string) $plan->name) . '-monthly';
            $slug = $base;
            $n = 2;
            while (
                DB::table('subscription_plans')
                    ->where('slug', $slug)
                    ->where('id', '!=', $plan->id)
                    ->exists()
            ) {
                $slug = $base . '-' . $n;
                $n++;
            }

            DB::table('subscription_plans')->where('id', $plan->id)->update([
                'billing_cycle' => 'monthly',
                'duration_months' => 1,
                'slug' => $slug,
            ]);
        }
    }

    public function down(): void
    {
        // Irreversible normalization.
    }
};
