@php
    $monthlyPlans = $plans->where('billing_cycle', 'monthly')->values();
    $yearlyPlans = $plans->where('billing_cycle', 'yearly')->values();
    $defaultCycle = $monthlyPlans->isNotEmpty() ? 'monthly' : 'yearly';
    $gridCols = max(1, min(3, max($monthlyPlans->count(), $yearlyPlans->count(), 1)));
@endphp

<div id="pricing-plans-root" data-default-cycle="{{ $defaultCycle }}">
    <div class="flex justify-center mb-10">
        <div class="inline-flex items-center bg-slate-200 dark:bg-slate-800 rounded-xl p-1" role="tablist" aria-label="{{ __('frontend.pricing.billing_toggle_label') }}">
            <button type="button"
                    role="tab"
                    data-billing-toggle="monthly"
                    class="px-6 py-2 rounded-lg font-semibold transition-colors disabled:opacity-40 disabled:cursor-not-allowed"
                    @if($monthlyPlans->isEmpty()) disabled aria-disabled="true" @endif>
                {{ __('frontend.pricing.monthly') }}
            </button>
            <button type="button"
                    role="tab"
                    data-billing-toggle="yearly"
                    class="px-6 py-2 rounded-lg font-semibold transition-colors disabled:opacity-40 disabled:cursor-not-allowed"
                    @if($yearlyPlans->isEmpty()) disabled aria-disabled="true" @endif>
                {{ __('frontend.pricing.yearly') }}
                <span class="text-emerald-500 ms-1">{{ __('frontend.pricing.yearly_save') }}</span>
            </button>
        </div>
    </div>

    <p data-pricing-empty="monthly" class="hidden text-center text-slate-600 dark:text-slate-400 py-12">
        {{ __('frontend.pricing.no_monthly_plans') }}
    </p>
    <p data-pricing-empty="yearly" class="hidden text-center text-slate-600 dark:text-slate-400 py-12">
        {{ __('frontend.pricing.no_yearly_plans') }}
    </p>

    <div data-pricing-grid class="grid md:grid-cols-{{ $gridCols }} gap-8 lg:gap-12">
        @foreach($plans as $plan)
            @php
                $cyclePlans = $plan->billing_cycle === 'yearly' ? $yearlyPlans : $monthlyPlans;
                $highlight = $cyclePlans->count() >= 2 && $cyclePlans->search(fn ($p) => $p->id === $plan->id) === 1;
            @endphp
            <div data-plan-cycle="{{ $plan->billing_cycle }}"
                 class="group relative {{ $highlight ? 'md:scale-105' : '' }}">
                <div class="h-full p-8 rounded-2xl glass border border-slate-200 dark:border-slate-800 hover:border-sky-300 dark:hover:border-sky-700 transition-all duration-300 {{ $highlight ? 'ring-2 ring-sky-500/30' : '' }}">
                    <div class="flex items-center justify-between gap-2 mb-2">
                        <h3 class="text-2xl font-bold text-slate-900 dark:text-white">{{ $plan->name }}</h3>
                        <span class="text-xs font-semibold uppercase tracking-wide px-2 py-1 rounded-full bg-sky-100 text-sky-700 dark:bg-sky-900/40 dark:text-sky-300">
                            {{ $plan->billingCycleLabel() }}
                        </span>
                    </div>
                    @if($plan->description)
                        <p class="text-sm text-slate-500 dark:text-slate-400 mb-4">{{ $plan->description }}</p>
                    @endif
                    <div class="mb-6">
                        <div class="flex items-baseline flex-wrap gap-x-2">
                            <span class="text-5xl font-bold text-slate-900 dark:text-white">{{ number_format((float) $plan->company_price, 0) }}</span>
                            <span class="text-slate-500 dark:text-slate-400">
                                {{ $plan->currency }}
                                @if($plan->billing_cycle === 'yearly')
                                    {{ __('frontend.pricing.per_year_suffix') }}
                                @else
                                    {{ __('frontend.pricing.per_month_suffix') }}
                                @endif
                            </span>
                        </div>
                    </div>
                    @if(is_array($plan->features) && count($plan->features))
                        <ul class="space-y-3 mb-8">
                            @foreach($plan->features as $feature)
                                <li class="flex items-center gap-3 text-slate-700 dark:text-slate-300">
                                    <svg class="w-5 h-5 text-emerald-500 flex-shrink-0" fill="currentColor" viewBox="0 0 20 20">
                                        <path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd"/>
                                    </svg>
                                    <span>{{ $feature }}</span>
                                </li>
                            @endforeach
                        </ul>
                    @endif
                    <a href="{{ url('register') }}" class="block w-full py-3 text-center rounded-xl bg-sky-600 text-white font-semibold hover:bg-sky-700 transition-colors">
                        {{ __('frontend.pricing.get_started') }}
                    </a>
                </div>
            </div>
        @endforeach
    </div>
</div>
