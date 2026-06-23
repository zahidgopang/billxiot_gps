<!-- frontend/sections/testimonials.blade.php -->
<section id="testimonials" class="py-32 relative overflow-hidden">
    <!-- Background Elements -->
    <div class="absolute inset-0 bg-gradient-to-b from-slate-50 to-white dark:from-slate-950 dark:to-slate-900"></div>
    <div class="absolute top-0 left-0 w-full h-32 bg-gradient-to-b from-white to-transparent dark:from-slate-950"></div>

    <!-- Floating Quotes -->
    <div class="absolute top-1/4 left-10 text-9xl opacity-5 text-sky-500">"</div>
    <div class="absolute bottom-1/4 right-10 text-9xl opacity-5 text-blue-500">"</div>

    <div class="relative max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
        <!-- Section Header -->
        <div class="text-center mb-20">
            <span class="inline-block px-4 py-1.5 bg-gradient-to-r from-emerald-500/10 to-teal-500/10 rounded-full text-emerald-600 dark:text-emerald-400 font-semibold text-sm mb-4">
                {{ __('frontend.testimonials.badge') }}
            </span>
            <h2 class="text-4xl lg:text-5xl font-bold mb-6">
                {{ __('frontend.testimonials.title') }} <span class="bg-gradient-to-r from-emerald-500 to-teal-600 bg-clip-text text-transparent">{{ __('frontend.testimonials.title_highlight') }}</span>
            </h2>
            <p class="text-xl text-slate-600 dark:text-slate-300 max-w-3xl mx-auto">
                {{ __('frontend.testimonials.subtitle') }}
            </p>
        </div>

        <!-- Testimonials Grid -->
        <div class="grid md:grid-cols-2 lg:grid-cols-3 gap-8 mb-20 max-w-7xl mx-auto">
            @php
                $testimonials = __('frontend.testimonials.items');
            @endphp

            @foreach($testimonials as $testimonial)
                <div class="group card-hover">
                    <div class="h-full p-8 rounded-2xl glass border border-slate-200 dark:border-slate-800">
                        <!-- Quote Icon -->
                        <div class="text-4xl text-sky-500/30 mb-6">"</div>

                        <!-- Content -->
                        <p class="text-slate-700 dark:text-slate-300 mb-8 italic">
                            {{ $testimonial['content'] }}
                        </p>

                        <!-- Rating -->
                        <div class="flex items-center gap-1 mb-6">
                            @for($i = 0; $i < 5; $i++)
                                <svg class="w-5 h-5 text-amber-500" fill="currentColor" viewBox="0 0 20 20">
                                    <path d="M9.049 2.927c.3-.921 1.603-.921 1.902 0l1.07 3.292a1 1 0 00.95.69h3.462c.969 0 1.371 1.24.588 1.81l-2.8 2.034a1 1 0 00-.364 1.118l1.07 3.292c.3.921-.755 1.688-1.54 1.118l-2.8-2.034a1 1 0 00-1.175 0l-2.8 2.034c-.784.57-1.838-.197-1.539-1.118l1.07-3.292a1 1 0 00-.364-1.118L2.98 8.72c-.783-.57-.38-1.81.588-1.81h3.461a1 1 0 00.951-.69l1.07-3.292z"/>
                                </svg>
                            @endfor
                        </div>

                        <!-- Metrics -->
                        <div class="space-y-2 mb-8">
                            @foreach($testimonial['metrics'] as $metric)
                                <div class="flex items-center justify-between text-sm">
                                    <span class="text-slate-500 dark:text-slate-400">{{ explode(':', $metric)[0] }}:</span>
                                    <span class="font-semibold text-slate-700 dark:text-slate-300">{{ explode(':', $metric)[1] }}</span>
                                </div>
                            @endforeach
                        </div>

                        <!-- Author -->
                        <div class="flex items-center gap-4">
                            <div class="w-12 h-12 rounded-full bg-gradient-to-br from-sky-500 to-blue-600 flex items-center justify-center text-white text-xl">
                                {{ $testimonial['logo'] }}
                            </div>
                            <div>
                                <div class="font-bold text-slate-900 dark:text-white">{{ $testimonial['name'] }}</div>
                                <div class="text-sm text-slate-500 dark:text-slate-400">{{ $testimonial['role'] }}</div>
                                <div class="text-sm font-medium text-sky-600 dark:text-sky-400">{{ $testimonial['company'] }}</div>
                            </div>
                        </div>
                    </div>
                </div>
            @endforeach
        </div>

        <!-- Stats Bar -->
        <div class="grid grid-cols-2 md:grid-cols-4 gap-8">
            <div class="text-center">
                <div class="text-4xl font-bold bg-gradient-to-r from-sky-600 to-blue-700 bg-clip-text text-transparent">120+</div>
                <div class="text-sm text-slate-500 dark:text-slate-400">{{ __('frontend.testimonials.stat_fleets') }}</div>
            </div>
            <div class="text-center">
                <div class="text-4xl font-bold bg-gradient-to-r from-emerald-600 to-teal-700 bg-clip-text text-transparent">92%</div>
                <div class="text-sm text-slate-500 dark:text-slate-400">{{ __('frontend.testimonials.stat_satisfaction') }}</div>
            </div>
            <div class="text-center">
                <div class="text-4xl font-bold bg-gradient-to-r from-amber-600 to-orange-700 bg-clip-text text-transparent">4.7/5</div>
                <div class="text-sm text-slate-500 dark:text-slate-400">{{ __('frontend.testimonials.stat_rating') }}</div>
            </div>
            <div class="text-center">
                <div class="text-4xl font-bold bg-gradient-to-r from-purple-600 to-pink-700 bg-clip-text text-transparent">2</div>
                <div class="text-sm text-slate-500 dark:text-slate-400">{{ __('frontend.testimonials.stat_regions') }}</div>
            </div>
        </div>
    </div>
</section>
