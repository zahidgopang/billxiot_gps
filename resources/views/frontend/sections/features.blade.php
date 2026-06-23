<!-- frontend/sections/features.blade.php -->
<section id="features" class="py-32 relative">
    <!-- Background Pattern -->
    <div class="absolute inset-0 bg-gradient-to-b from-transparent via-slate-50/50 to-blue-50/50 dark:from-transparent dark:via-slate-900/30 dark:to-slate-900/50"></div>
    <div class="absolute inset-0 opacity-5 bg-grid-pattern"></div>

    <div class="relative max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
        <!-- Section Header -->
        <div class="text-center mb-20">
            <span class="inline-block px-4 py-1.5 bg-gradient-to-r from-sky-500/10 to-blue-500/10 rounded-full text-sky-600 dark:text-sky-400 font-semibold text-sm mb-4">
                {{ __('frontend.features.badge') }}
            </span>
            <h2 class="text-4xl lg:text-5xl font-bold mb-6">
                {{ __('frontend.features.title') }} <span class="bg-gradient-to-r from-sky-500 to-blue-600 bg-clip-text text-transparent">{{ __('frontend.features.title_highlight') }}</span>
            </h2>
            <p class="text-xl text-slate-600 dark:text-slate-300 max-w-3xl mx-auto">
                {{ __('frontend.features.subtitle') }}
            </p>
        </div>

        <!-- Features Grid -->
        <div class="grid md:grid-cols-2 lg:grid-cols-3 gap-8">
            @php
                $features = __('frontend.features.items');
            @endphp

            @foreach($features as $feature)
                <div class="group card-hover">
                    <div class="h-full p-8 rounded-2xl glass border border-slate-200/50 dark:border-slate-800/50">
                        <!-- Icon -->
                        <div class="relative mb-6">
                            <div class="w-16 h-16 rounded-xl bg-gradient-to-br {{ $feature['gradient'] }} flex items-center justify-center shadow-lg">
                                <i class="fa-solid fa-{{ $feature['icon'] }} text-white text-2xl"></i>
                            </div>
                            <div class="absolute -top-2 -right-2 w-8 h-8 bg-white dark:bg-slate-900 rounded-full flex items-center justify-center shadow-md">
                                <div class="w-2 h-2 rounded-full bg-gradient-to-br {{ $feature['gradient'] }}"></div>
                            </div>
                        </div>

                        <!-- Content -->
                        <h3 class="text-xl font-bold mb-4 text-slate-900 dark:text-white group-hover:text-sky-600 dark:group-hover:text-sky-400 transition-colors">
                            {{ $feature['title'] }}
                        </h3>

                        <p class="text-slate-600 dark:text-slate-400 mb-6">
                            {{ $feature['desc'] }}
                        </p>

                        <!-- Feature Points -->
                        <ul class="space-y-2">
                            @foreach($feature['points'] as $point)
                                <li class="flex items-center gap-2 text-sm text-slate-500 dark:text-slate-400">
                                    <svg class="w-4 h-4 text-sky-500 flex-shrink-0" fill="currentColor" viewBox="0 0 20 20">
                                        <path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd"/>
                                    </svg>
                                    {{ $point }}
                                </li>
                            @endforeach
                        </ul>

                        <!-- Hover Indicator -->
                        <div class="mt-6 pt-6 border-t border-slate-200 dark:border-slate-800 opacity-0 group-hover:opacity-100 transition-opacity">
                            <a href="#" class="inline-flex items-center gap-2 text-sm font-medium text-sky-600 dark:text-sky-400">
                                {{ __('frontend.features.learn_more') }}
                                <svg class="w-4 h-4 group-hover:translate-x-1 transition-transform" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/>
                                </svg>
                            </a>
                        </div>
                    </div>
                </div>
            @endforeach
        </div>

        <!-- Interactive Demo Preview -->
        <div class="mt-32 relative">
            <div class="gradient-border">
                <div class="bg-slate-900 rounded-xl p-8">
                    <div class="grid lg:grid-cols-2 gap-12 items-center">
                        <div>
                            <h3 class="text-3xl font-bold text-white mb-6">
                                {{ __('frontend.features.demo_title') }}
                            </h3>
                            <p class="text-slate-400 mb-8">
                                {{ __('frontend.features.demo_desc') }}
                            </p>
                            <a href="{{ url('/demo/login') }}"
                               class="inline-flex items-center gap-2 px-6 py-3 bg-gradient-to-r from-sky-600 to-blue-700 text-white font-semibold rounded-lg hover:shadow-lg transition-all">
                                <i class="fa-solid fa-play"></i>
                                {{ __('frontend.features.demo_btn') }}
                            </a>
                        </div>

                        <div class="relative">
                            <!-- Animated Stats -->
                            <div class="grid grid-cols-2 gap-4">
                                <div class="p-4 rounded-lg bg-slate-800/50">
                                    <div class="text-3xl font-bold text-white shimmer-text">247</div>
                                    <div class="text-sm text-slate-400">{{ __('frontend.features.active_vehicles') }}</div>
                                </div>
                                <div class="p-4 rounded-lg bg-slate-800/50">
                                    <div class="text-3xl font-bold text-white shimmer-text">98%</div>
                                    <div class="text-sm text-slate-400">{{ __('frontend.features.uptime_month') }}</div>
                                </div>
                                <div class="p-4 rounded-lg bg-slate-800/50">
                                    <div class="text-3xl font-bold text-white shimmer-text">23K</div>
                                    <div class="text-sm text-slate-400">{{ __('frontend.features.alerts_processed') }}</div>
                                </div>
                                <div class="p-4 rounded-lg bg-slate-800/50">
                                    <div class="text-3xl font-bold text-white shimmer-text">15%</div>
                                    <div class="text-sm text-slate-400">{{ __('frontend.features.fuel_saved') }}</div>
                                </div>
                            </div>

                            <!-- Animated Progress -->
                            <div class="mt-6">
                                <div class="flex justify-between text-sm text-slate-400 mb-2">
                                    <span>{{ __('frontend.features.system_performance') }}</span>
                                    <span>99.9%</span>
                                </div>
                                <div class="h-2 bg-slate-800 rounded-full overflow-hidden">
                                    <div class="h-full bg-gradient-to-r from-emerald-500 to-teal-600 rounded-full animate-[width_2s_ease-in-out]" style="width: 99.9%"></div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</section>
