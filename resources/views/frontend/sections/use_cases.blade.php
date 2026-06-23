<!-- frontend/sections/use_cases.blade.php -->
<section id="use-cases" class="py-32 relative overflow-hidden">
    <div class="absolute inset-0 bg-gradient-to-br from-slate-900 via-slate-950 to-blue-950"></div>
    <div class="absolute inset-0 opacity-10">
        <div class="absolute top-1/4 left-1/4 w-64 h-64 bg-gradient-to-r from-sky-500/20 to-blue-500/10 rounded-full blur-3xl"></div>
        <div class="absolute bottom-1/4 right-1/4 w-64 h-64 bg-gradient-to-r from-emerald-500/10 to-teal-500/5 rounded-full blur-3xl"></div>
    </div>
    <div class="absolute inset-0 opacity-5" style="background-image: linear-gradient(to right, #22d3ee 1px, transparent 1px), linear-gradient(to bottom, #22d3ee 1px, transparent 1px); background-size: 50px 50px;"></div>

    <div class="relative max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
        <div class="text-center mb-20">
            <span class="inline-block px-4 py-1.5 bg-gradient-to-r from-blue-500/10 to-purple-500/10 rounded-full text-blue-400 font-semibold text-sm mb-4">
                {{ __('frontend.use_cases.badge') }}
            </span>
            <h2 class="text-4xl lg:text-5xl font-bold text-white mb-6">
                {{ __('frontend.use_cases.title') }} <span class="bg-gradient-to-r from-sky-400 to-blue-500 bg-clip-text text-transparent">{{ __('frontend.use_cases.title_highlight') }}</span>
            </h2>
            <p class="text-xl text-slate-300 max-w-3xl mx-auto">
                {{ __('frontend.use_cases.subtitle') }}
            </p>
        </div>

        <div class="grid md:grid-cols-2 lg:grid-cols-3 gap-6 mb-20">
            @foreach(__('frontend.use_cases.items') as $case)
                <div class="group card-hover">
                    <div class="h-full p-8 rounded-2xl bg-gradient-to-b from-slate-800 to-slate-900 border border-slate-700/50 hover:border-slate-600 transition-all duration-300">
                        <div class="w-16 h-16 rounded-xl bg-gradient-to-br {{ $case['gradient'] }} flex items-center justify-center text-2xl mb-6">
                            {{ $case['icon'] }}
                        </div>
                        <h3 class="text-xl font-bold text-white mb-4">{{ $case['title'] }}</h3>
                        <ul class="space-y-3 mb-6">
                            @foreach($case['features'] as $feature)
                                <li class="flex items-start gap-2 text-sm text-slate-300">
                                    <svg class="w-4 h-4 text-sky-400 flex-shrink-0 mt-0.5" fill="currentColor" viewBox="0 0 20 20">
                                        <path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd"/>
                                    </svg>
                                    {{ $feature }}
                                </li>
                            @endforeach
                        </ul>
                        <div class="pt-6 border-t border-slate-700/50">
                            @foreach($case['stats'] as $stat)
                                <div class="flex items-center gap-2 text-sm text-slate-400 mb-2">
                                    <div class="w-1.5 h-1.5 rounded-full bg-gradient-to-br {{ $case['gradient'] }}"></div>
                                    {{ $stat }}
                                </div>
                            @endforeach
                        </div>
                    </div>
                </div>
            @endforeach
        </div>

        <div class="mb-20">
            <h3 class="text-2xl font-bold text-white text-center mb-10">{{ __('frontend.use_cases.solutions_title') }}</h3>
            <div class="grid sm:grid-cols-2 lg:grid-cols-4 gap-4">
                @foreach(__('frontend.use_cases.solutions') as $solution)
                    <div class="p-5 rounded-xl bg-slate-800/60 border border-slate-700/50">
                        <div class="text-2xl mb-3">{{ $solution['icon'] }}</div>
                        <h4 class="font-bold text-white mb-2">{{ $solution['title'] }}</h4>
                        <p class="text-sm text-slate-400">{{ $solution['desc'] }}</p>
                    </div>
                @endforeach
            </div>
        </div>

        <div class="p-8 rounded-2xl glass border border-slate-700/50">
            <div class="grid lg:grid-cols-2 gap-12 items-center">
                <div>
                    <h3 class="text-2xl font-bold text-white mb-4">{{ __('frontend.use_cases.custom_title') }}</h3>
                    <p class="text-slate-300 mb-6">{{ __('frontend.use_cases.custom_desc') }}</p>
                    <a href="{{ url('/contact') }}"
                       class="inline-flex items-center gap-2 px-6 py-3 bg-gradient-to-r from-sky-600 to-blue-700 text-white font-semibold rounded-lg hover:shadow-lg transition-all">
                        <i class="fa-solid fa-headset"></i>
                        {{ __('frontend.use_cases.custom_cta') }}
                    </a>
                </div>
                <div class="grid grid-cols-3 gap-4">
                    @foreach(__('frontend.use_cases.industries') as $industry)
                        <div class="p-4 rounded-lg bg-slate-800/50 text-center">
                            <div class="text-2xl mb-2">{{ $industry['icon'] }}</div>
                            <div class="text-sm text-slate-300">{{ $industry['label'] }}</div>
                        </div>
                    @endforeach
                </div>
            </div>
        </div>
    </div>
</section>
