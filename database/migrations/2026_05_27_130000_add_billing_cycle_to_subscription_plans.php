<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('subscription_plans')) {
            return;
        }

        if (! Schema::hasColumn('subscription_plans', 'billing_cycle')) {
            Schema::table('subscription_plans', function (Blueprint $table) {
                $table->string('billing_cycle', 20)->default('monthly')->after('duration_months');
            });
        }

        DB::table('subscription_plans')->orderBy('id')->get()->each(function ($plan) {
            $cycle = ((int) $plan->duration_months) >= 12 ? 'yearly' : 'monthly';
            $months = $cycle === 'yearly' ? 12 : 1;

            $base = Str::slug((string) $plan->name) . '-' . $cycle;
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
                'billing_cycle' => $cycle,
                'duration_months' => $months,
                'slug' => $slug,
            ]);
        });
    }

    public function down(): void
    {
        if (Schema::hasColumn('subscription_plans', 'billing_cycle')) {
            Schema::table('subscription_plans', function (Blueprint $table) {
                $table->dropColumn('billing_cycle');
            });
        }
    }
};
