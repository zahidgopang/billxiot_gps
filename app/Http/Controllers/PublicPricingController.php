<?php

namespace App\Http\Controllers;

use App\Models\SubscriptionPlan;
use Illuminate\Support\Facades\Schema;

class PublicPricingController extends Controller
{
    public function __invoke()
    {
        $plans = collect();

        if (Schema::hasTable('subscription_plans')) {
            $plans = SubscriptionPlan::query()
                ->publicActive()
                ->orderBy('billing_cycle')
                ->orderBy('sort_order')
                ->orderBy('name')
                ->get();
        }

        return view('pricing', compact('plans'));
    }
}
