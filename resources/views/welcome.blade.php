@extends('frontend.layout')

@section('title', __('seo.pages.home.title'))
@section('description', __('seo.pages.home.description'))

@section('content')

    {{-- PREMIUM HERO WITH ANIMATIONS --}}
    @include('frontend.sections.hero')

    {{-- WHY FalconEyeGPS - PREMIUM VERSION --}}
    <section class="py-32 relative overflow-hidden">
        <!-- Background Elements -->
        <div class="absolute inset-0 bg-gradient-to-b from-slate-50 to-white dark:from-slate-950 dark:to-slate-900"></div>
        <div class="absolute top-0 left-0 w-full h-full opacity-10">
            <div class="absolute top-1/4 left-1/4 w-64 h-64 bg-gradient-to-r from-sky-300 to-blue-400 rounded-full blur-3xl"></div>
            <div class="absolute bottom-1/4 right-1/4 w-96 h-96 bg-gradient-to-r from-emerald-300 to-teal-400 rounded-full blur-3xl"></div>
        </div>

        <div class="relative max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <!-- Section Header -->
            <div class="text-center mb-20">
                <span class="inline-block px-4 py-1.5 bg-gradient-to-r from-emerald-500/10 to-teal-500/10 rounded-full text-emerald-600 dark:text-emerald-400 font-semibold text-sm mb-4">
                    {{ __('frontend.home.why_badge') }}
                </span>
                <h2 class="text-4xl lg:text-5xl font-bold mb-6">
                    {{ __('frontend.home.why_title') }} <span class="bg-gradient-to-r from-sky-500 to-blue-600 bg-clip-text text-transparent">{{ __('frontend.home.why_title_highlight') }}</span>
                </h2>
                <p class="text-xl text-slate-600 dark:text-slate-300 max-w-3xl mx-auto">
                    {{ __('frontend.home.why_subtitle') }}
                </p>
            </div>

            <!-- Features Grid -->
            <div class="grid md:grid-cols-3 gap-8 lg:gap-12">
                <!-- Feature 1 -->
                <div class="group relative">
                    <div class="h-full p-8 rounded-2xl glass border border-slate-200 dark:border-slate-800 hover:border-sky-300 dark:hover:border-sky-700 transition-all duration-300">
                        <!-- Animated Icon -->
                        <div class="relative mb-6">
                            <div class="w-16 h-16 rounded-xl bg-gradient-to-br from-sky-500 to-blue-600 flex items-center justify-center shadow-lg">
                                <i class="fa-solid fa-bolt text-white text-2xl"></i>
                            </div>
                            <div class="absolute -top-2 -right-2 w-8 h-8 bg-white dark:bg-slate-900 rounded-full flex items-center justify-center shadow-md">
                                <div class="w-2 h-2 rounded-full bg-gradient-to-br from-sky-500 to-blue-600 animate-pulse"></div>
                            </div>
                        </div>

                        <h3 class="text-xl font-bold mb-4 text-slate-900 dark:text-white">{{ __('frontend.home.precision_title') }}</h3>
                        <p class="text-slate-600 dark:text-slate-400 mb-6">
                            {{ __('frontend.home.precision_desc') }}
                        </p>

                        <!-- Stats -->
                        <div class="pt-6 border-t border-slate-200 dark:border-slate-800">
                            <div class="flex items-center gap-2 text-sm text-slate-500 dark:text-slate-400 mb-2">
                                <div class="w-2 h-2 rounded-full bg-emerald-500"></div>
                                <span>{{ __('frontend.home.precision_stat1') }}</span>
                            </div>
                            <div class="flex items-center gap-2 text-sm text-slate-500 dark:text-slate-400">
                                <div class="w-2 h-2 rounded-full bg-emerald-500"></div>
                                <span>{{ __('frontend.home.precision_stat2') }}</span>
                            </div>
                        </div>
                    </div>

                    <!-- Hover Effect -->
                    <div class="absolute inset-0 rounded-2xl bg-gradient-to-br from-sky-500 to-blue-600 opacity-0 group-hover:opacity-5 blur-xl transition-opacity duration-300 -z-10"></div>
                </div>

                <!-- Feature 2 -->
                <div class="group relative">
                    <div class="h-full p-8 rounded-2xl glass border border-slate-200 dark:border-slate-800 hover:border-emerald-300 dark:hover:border-emerald-700 transition-all duration-300">
                        <!-- Animated Icon -->
                        <div class="relative mb-6">
                            <div class="w-16 h-16 rounded-xl bg-gradient-to-br from-emerald-500 to-teal-600 flex items-center justify-center shadow-lg">
                                <i class="fa-solid fa-shield text-white text-2xl"></i>
                            </div>
                            <div class="absolute -top-2 -right-2 w-8 h-8 bg-white dark:bg-slate-900 rounded-full flex items-center justify-center shadow-md">
                                <div class="w-2 h-2 rounded-full bg-gradient-to-br from-emerald-500 to-teal-600 animate-pulse" style="animation-delay: 0.5s"></div>
                            </div>
                        </div>

                        <h3 class="text-xl font-bold mb-4 text-slate-900 dark:text-white">{{ __('frontend.home.security_title') }}</h3>
                        <p class="text-slate-600 dark:text-slate-400 mb-6">
                            {{ __('frontend.home.security_desc') }}
                        </p>

                        <!-- Stats -->
                        <div class="pt-6 border-t border-slate-200 dark:border-slate-800">
                            <div class="flex items-center gap-2 text-sm text-slate-500 dark:text-slate-400 mb-2">
                                <div class="w-2 h-2 rounded-full bg-emerald-500"></div>
                                <span>{{ __('frontend.home.security_stat1') }}</span>
                            </div>
                            <div class="flex items-center gap-2 text-sm text-slate-500 dark:text-slate-400">
                                <div class="w-2 h-2 rounded-full bg-emerald-500"></div>
                                <span>{{ __('frontend.home.security_stat2') }}</span>
                            </div>
                        </div>
                    </div>

                    <!-- Hover Effect -->
                    <div class="absolute inset-0 rounded-2xl bg-gradient-to-br from-emerald-500 to-teal-600 opacity-0 group-hover:opacity-5 blur-xl transition-opacity duration-300 -z-10"></div>
                </div>

                <!-- Feature 3 -->
                <div class="group relative">
                    <div class="h-full p-8 rounded-2xl glass border border-slate-200 dark:border-slate-800 hover:border-purple-300 dark:hover:border-purple-700 transition-all duration-300">
                        <!-- Animated Icon -->
                        <div class="relative mb-6">
                            <div class="w-16 h-16 rounded-xl bg-gradient-to-br from-purple-500 to-pink-600 flex items-center justify-center shadow-lg">
                                <i class="fa-solid fa-brain text-white text-2xl"></i>
                            </div>
                            <div class="absolute -top-2 -right-2 w-8 h-8 bg-white dark:bg-slate-900 rounded-full flex items-center justify-center shadow-md">
                                <div class="w-2 h-2 rounded-full bg-gradient-to-br from-purple-500 to-pink-600 animate-pulse" style="animation-delay: 1s"></div>
                            </div>
                        </div>

                        <h3 class="text-xl font-bold mb-4 text-slate-900 dark:text-white">{{ __('frontend.home.ai_title') }}</h3>
                        <p class="text-slate-600 dark:text-slate-400 mb-6">
                            {{ __('frontend.home.ai_desc') }}
                        </p>

                        <!-- Stats -->
                        <div class="pt-6 border-t border-slate-200 dark:border-slate-800">
                            <div class="flex items-center gap-2 text-sm text-slate-500 dark:text-slate-400 mb-2">
                                <div class="w-2 h-2 rounded-full bg-emerald-500"></div>
                                <span>{{ __('frontend.home.ai_stat1') }}</span>
                            </div>
                            <div class="flex items-center gap-2 text-sm text-slate-500 dark:text-slate-400">
                                <div class="w-2 h-2 rounded-full bg-emerald-500"></div>
                                <span>{{ __('frontend.home.ai_stat2') }}</span>
                            </div>
                        </div>
                    </div>

                    <!-- Hover Effect -->
                    <div class="absolute inset-0 rounded-2xl bg-gradient-to-br from-purple-500 to-pink-600 opacity-0 group-hover:opacity-5 blur-xl transition-opacity duration-300 -z-10"></div>
                </div>
            </div>

            <!-- Trust Badges -->
            <div class="mt-20 grid grid-cols-2 md:grid-cols-4 gap-8 text-center">
                <div class="p-6 rounded-2xl glass">
                    <div class="text-3xl font-bold bg-gradient-to-r from-sky-600 to-blue-700 bg-clip-text text-transparent">24/7</div>
                    <div class="text-sm text-slate-500 dark:text-slate-400">{{ __('frontend.home.stat_support') }}</div>
                </div>
                <div class="p-6 rounded-2xl glass">
                    <div class="text-3xl font-bold bg-gradient-to-r from-emerald-600 to-teal-700 bg-clip-text text-transparent">99.9%</div>
                    <div class="text-sm text-slate-500 dark:text-slate-400">{{ __('frontend.home.stat_uptime') }}</div>
                </div>
                <div class="p-6 rounded-2xl glass">
                    <div class="text-3xl font-bold bg-gradient-to-r from-amber-600 to-orange-700 bg-clip-text text-transparent">10K+</div>
                    <div class="text-sm text-slate-500 dark:text-slate-400">{{ __('frontend.home.stat_fleets') }}</div>
                </div>
                <div class="p-6 rounded-2xl glass">
                    <div class="text-3xl font-bold bg-gradient-to-r from-purple-600 to-pink-700 bg-clip-text text-transparent">150+</div>
                    <div class="text-sm text-slate-500 dark:text-slate-400">{{ __('frontend.home.stat_countries') }}</div>
                </div>
            </div>
        </div>
    </section>

    {{-- FEATURES SECTION --}}
    @include('frontend.sections.features')

    {{-- TESTIMONIALS SECTION --}}
    @include('frontend.sections.testimonials')

    {{-- USE CASES SECTION --}}
    @include('frontend.sections.use_cases')

    {{-- ANDROID APP --}}
    @include('frontend.sections.mobile_screenshots')
    @include('frontend.sections.android_app')

    {{-- PREMIUM CTA SECTION --}}
    <section class="py-32 relative overflow-hidden">
        <!-- Animated Gradient Background -->
        <div class="absolute inset-0 bg-gradient-to-r from-sky-600 via-blue-600 to-indigo-700 animate-gradient">
            <div class="absolute top-0 left-0 w-full h-full">
                <div class="absolute top-1/4 left-1/4 w-96 h-96 bg-white/10 rounded-full blur-3xl animate-pulse"></div>
                <div class="absolute bottom-1/4 right-1/4 w-96 h-96 bg-white/10 rounded-full blur-3xl animate-pulse" style="animation-delay: 1s"></div>
            </div>
        </div>

        <!-- Animated Satellite Orbits -->
        <div class="absolute inset-0 overflow-hidden">
            <div class="absolute top-1/3 left-1/4">
                <div class="w-64 h-64 border-2 border-white/10 rounded-full animate-spin-slow"></div>
            </div>
            <div class="absolute bottom-1/3 right-1/4">
                <div class="w-96 h-96 border-2 border-white/10 rounded-full animate-spin-slow" style="animation-direction: reverse; animation-duration: 40s;"></div>
            </div>
        </div>

        <div class="relative max-w-4xl mx-auto px-4 sm:px-6 lg:px-8 text-center">
            <!-- Trust Badge -->
            <div class="inline-flex items-center gap-2 px-4 py-2 bg-white/10 backdrop-blur-sm rounded-full border border-white/20 mb-8">
                <div class="relative flex h-2 w-2">
                    <span class="animate-ping absolute inline-flex h-full w-full rounded-full bg-emerald-400 opacity-75"></span>
                    <span class="relative inline-flex rounded-full h-2 w-2 bg-emerald-500"></span>
                </div>
                <span class="text-sm text-white/90">{{ __('frontend.home.cta_badge') }}</span>
            </div>

            <!-- Main Heading -->
            <h2 class="text-4xl lg:text-6xl font-bold text-white mb-8">
                {{ __('frontend.home.cta_title') }}
                <span class="block bg-gradient-to-r from-white to-blue-100 bg-clip-text text-transparent">
                    {{ __('frontend.home.cta_title_highlight') }}
                </span>
            </h2>

            <!-- Subheading -->
            <p class="text-xl text-blue-100 mb-12 max-w-2xl mx-auto">
                {{ __('frontend.home.cta_subtitle') }}
            </p>

            <!-- CTA Buttons -->
            <div class="flex flex-col sm:flex-row gap-6 justify-center mb-12">
                <a href="{{ url('/contact') }}"
                   class="group relative px-10 py-5 bg-white text-blue-700 rounded-2xl font-bold text-lg overflow-hidden shadow-2xl hover:shadow-3xl transition-all duration-300">
                    <span class="relative z-10 flex items-center justify-center gap-3">
                        {{ __('frontend.home.cta_request_demo') }}
                        <svg class="w-5 h-5 transform group-hover:translate-x-2 transition-transform" fill="currentColor" viewBox="0 0 20 20">
                            <path fill-rule="evenodd" d="M10.293 5.293a1 1 0 011.414 0l4 4a1 1 0 010 1.414l-4 4a1 1 0 01-1.414-1.414L12.586 11H5a1 1 0 110-2h7.586l-2.293-2.293a1 1 0 010-1.414z" clip-rule="evenodd"/>
                        </svg>
                    </span>
                    <div class="absolute inset-0 bg-gradient-to-r from-white to-blue-100 transform -translate-x-full group-hover:translate-x-0 transition-transform duration-500"></div>
                </a>

                <a href="{{ url('/contact') }}?subject=sales"
                   class="group px-10 py-5 bg-white/20 backdrop-blur-sm text-white rounded-2xl font-bold text-lg border-2 border-white/30 hover:bg-white/30 transition-all">
                    <span class="flex items-center justify-center gap-3">
                        {{ __('frontend.home.cta_contact_sales') }}
                    </span>
                </a>
                <a href="{{ url('demo/login') }}"
                   class="group px-10 py-5 bg-white/10 backdrop-blur-sm text-white rounded-2xl font-bold text-lg border-2 border-white/20 hover:bg-white/20 transition-all">
                    <span class="flex items-center justify-center gap-3">
                        {{ __('frontend.home.cta_schedule') }}
                    </span>
                </a>
            </div>

            <!-- Benefits -->
            <div class="grid grid-cols-1 sm:grid-cols-3 gap-6 text-blue-100/80 text-sm">
                <div class="flex items-center justify-center gap-2">
                    <svg class="w-4 h-4" fill="currentColor" viewBox="0 0 20 20">
                        <path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd"/>
                    </svg>
                    <span>{{ __('frontend.hero.benefit_setup') }}</span>
                </div>
                <div class="flex items-center justify-center gap-2">
                    <svg class="w-4 h-4" fill="currentColor" viewBox="0 0 20 20">
                        <path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd"/>
                    </svg>
                    <span>{{ __('frontend.hero.benefit_devices') }}</span>
                </div>
                <div class="flex items-center justify-center gap-2">
                    <svg class="w-4 h-4" fill="currentColor" viewBox="0 0 20 20">
                        <path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd"/>
                    </svg>
                    <span>{{ __('frontend.hero.benefit_support') }}</span>
                </div>
            </div>

            <!-- Trust Logos -->
            <div class="mt-16">
                <p class="text-blue-100/60 text-sm mb-6">{{ __('frontend.home.cta_trusted') }}</p>
                <div class="flex flex-wrap justify-center gap-x-6 gap-y-3 text-sm md:text-base opacity-80">
                    @foreach(__('frontend.trusted_partners') as $partner)
                        <span class="font-semibold text-white/90">{{ $partner }}</span>
                    @endforeach
                </div>
            </div>
        </div>
    </section>

@endsection

@push('styles')
    <style>
        .glass {
            background: rgba(255, 255, 255, 0.05);
            backdrop-filter: blur(10px);
            border: 1px solid rgba(255, 255, 255, 0.1);
        }

        .dark .glass {
            background: rgba(15, 23, 42, 0.7);
            border: 1px solid rgba(255, 255, 255, 0.05);
        }

        .animate-gradient {
            background-size: 200% 200%;
            animation: gradient-x 15s ease infinite;
        }

        @keyframes gradient-x {
            0%, 100% { background-position: 0% 50%; }
            50% { background-position: 100% 50%; }
        }

        .animate-spin-slow {
            animation: spin 30s linear infinite;
        }

        @keyframes spin {
            from { transform: rotate(0deg); }
            to { transform: rotate(360deg); }
        }

        .card-hover {
            transition: all 0.4s cubic-bezier(0.4, 0, 0.2, 1);
        }

        .card-hover:hover {
            transform: translateY(-8px);
            box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.25);
        }
    </style>
@endpush

@push('scripts')
    <script>
        // Initialize animations on scroll
        document.addEventListener('DOMContentLoaded', function() {
            // Add intersection observer for fade-in animations
            const observerOptions = {
                threshold: 0.1,
                rootMargin: '0px 0px -50px 0px'
            };

            const observer = new IntersectionObserver((entries) => {
                entries.forEach(entry => {
                    if (entry.isIntersecting) {
                        entry.target.classList.add('animate-slide-in');
                    }
                });
            }, observerOptions);

            // Observe all feature cards
            document.querySelectorAll('.group').forEach((card) => {
                observer.observe(card);
            });

            // Parallax effect for CTA section
            const ctaSection = document.querySelector('section:last-of-type');
            if (ctaSection) {
                document.addEventListener('mousemove', (e) => {
                    const x = (e.clientX / window.innerWidth - 0.5) * 10;
                    const y = (e.clientY / window.innerHeight - 0.5) * 10;
                    ctaSection.style.transform = `translateX(${x}px) translateY(${y}px)`;
                });
            }

            // Smooth scroll for demo link
            document.querySelector('a[href="#demo"]')?.addEventListener('click', function(e) {
                e.preventDefault();
                // In a real implementation, this would open a demo modal
                alert('Demo scheduling modal would open here');
            });
        });
    </script>
@endpush
