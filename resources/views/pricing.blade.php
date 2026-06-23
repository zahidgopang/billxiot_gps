@extends('frontend.layout')

@section('title', __('seo.pages.pricing.title'))
@section('description', __('seo.pages.pricing.description'))

@section('content')
    <!-- Pricing Hero -->
    <section class="pt-32 pb-24 relative">
        <div class="absolute inset-0 bg-gradient-to-br from-slate-50 via-blue-50 to-sky-100 dark:from-slate-950 dark:via-slate-900 dark:to-sky-950"></div>

        <div class="relative max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 text-center">
            <div class="text-left max-w-3xl mx-auto mb-8">
                @include('frontend.partials.page-breadcrumbs')
            </div>
            <span class="inline-block px-4 py-1.5 bg-gradient-to-r from-sky-500/10 to-blue-500/10 rounded-full text-sky-600 dark:text-sky-400 font-semibold text-sm mb-4">
                {{ __('frontend.pricing.badge') }}
            </span>
            <h1 class="text-5xl lg:text-6xl font-bold mb-6">
                {{ __('frontend.pricing.heading') }} <span class="bg-gradient-to-r from-sky-500 to-blue-600 bg-clip-text text-transparent">{{ __('frontend.pricing.heading_highlight') }}</span>
            </h1>
            <p class="text-xl text-slate-600 dark:text-slate-300 max-w-3xl mx-auto mb-12">
                {{ __('frontend.pricing.subtitle') }}
            </p>
        </div>
    </section>

    <!-- Pricing Cards -->
    <section class="pb-32 relative">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            @if(isset($plans) && $plans->isNotEmpty())
                @include('partials.pricing-plans-dynamic', ['plans' => $plans])
            @else
            <div class="grid md:grid-cols-3 gap-8 lg:gap-12">
                <!-- Essential Plan -->
                <div class="group relative">
                    <div class="h-full p-8 rounded-2xl glass border border-slate-200 dark:border-slate-800 hover:border-sky-300 dark:hover:border-sky-700 transition-all duration-300">
                        <!-- Plan Badge -->
                        <div class="inline-flex items-center gap-2 px-3 py-1 rounded-full bg-slate-100 dark:bg-slate-800 text-slate-600 dark:text-slate-400 text-sm font-medium mb-6">
                            <svg class="w-4 h-4" fill="currentColor" viewBox="0 0 20 20">
                                <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zM9.555 7.168A1 1 0 008 8v4a1 1 0 001.555.832l3-2a1 1 0 000-1.664l-3-2z" clip-rule="evenodd"/>
                            </svg>
                            Perfect for Startups
                        </div>

                        <!-- Plan Title -->
                        <h3 class="text-2xl font-bold text-slate-900 dark:text-white mb-4">Essential</h3>

                        <!-- Price -->
                        <div class="mb-6">
                            <div class="flex items-baseline">
                                <span class="text-5xl font-bold text-slate-900 dark:text-white">$5</span>
                                <span class="text-slate-500 dark:text-slate-400 ml-2">/device/month</span>
                            </div>
                            <p class="text-sm text-slate-500 dark:text-slate-400 mt-2">Billed annually or $6 monthly</p>
                        </div>

                        <!-- Features -->
                        <ul class="space-y-4 mb-8">
                            <li class="flex items-center gap-3">
                                <svg class="w-5 h-5 text-emerald-500 flex-shrink-0" fill="currentColor" viewBox="0 0 20 20">
                                    <path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd"/>
                                </svg>
                                <span>Real-time GPS tracking</span>
                            </li>
                            <li class="flex items-center gap-3">
                                <svg class="w-5 h-5 text-emerald-500 flex-shrink-0" fill="currentColor" viewBox="0 0 20 20">
                                    <path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd"/>
                                </svg>
                                <span>Basic trip history</span>
                            </li>
                            <li class="flex items-center gap-3">
                                <svg class="w-5 h-5 text-emerald-500 flex-shrink-0" fill="currentColor" viewBox="0 0 20 20">
                                    <path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd"/>
                                </svg>
                                <span>Speed & ignition alerts</span>
                            </li>
                            <li class="flex items-center gap-3">
                                <svg class="w-5 h-5 text-emerald-500 flex-shrink-0" fill="currentColor" viewBox="0 0 20 20">
                                    <path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd"/>
                                </svg>
                                <span>Web dashboard access</span>
                            </li>
                            <li class="flex items-center gap-3">
                                <svg class="w-5 h-5 text-emerald-500 flex-shrink-0" fill="currentColor" viewBox="0 0 20 20">
                                    <path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd"/>
                                </svg>
                                <span>Email support</span>
                            </li>
                        </ul>

                        <!-- CTA -->
                        <a href="{{ url('register') }}"
                           class="block w-full py-3 text-center rounded-xl bg-slate-800 dark:bg-slate-700 text-white font-semibold hover:bg-slate-900 dark:hover:bg-slate-600 transition-colors">
                            {{ __('frontend.pricing.get_started') }}
                        </a>
                    </div>
                </div>

                <!-- Professional Plan (Popular) -->
                <div class="group relative scale-105">
                    <!-- Popular Badge -->
                    <div class="absolute -top-4 left-1/2 transform -translate-x-1/2">
                        <div class="px-4 py-1.5 bg-gradient-to-r from-emerald-500 to-teal-600 text-white text-sm font-semibold rounded-full shadow-lg">
                            {{ __('frontend.pricing.most_popular') }}
                        </div>
                    </div>

                    <!-- Main Card -->
                    <div class="h-full p-8 rounded-2xl bg-gradient-to-b from-sky-600 to-blue-700 text-white">
                        <!-- Plan Badge -->
                        <div class="inline-flex items-center gap-2 px-3 py-1 rounded-full bg-white/20 text-white/90 text-sm font-medium mb-6">
                            <svg class="w-4 h-4" fill="currentColor" viewBox="0 0 20 20">
                                <path fill-rule="evenodd" d="M12.395 2.553a1 1 0 00-1.45-.385c-.345.23-.614.558-.822.88-.214.33-.403.713-.57 1.116-.334.804-.614 1.768-.84 2.734a31.365 31.365 0 00-.613 3.58 2.64 2.64 0 01-.945-1.067c-.328-.68-.398-1.534-.398-2.654A1 1 0 005.05 6.05 6.981 6.981 0 003 11a7 7 0 1011.95-4.95c-.592-.591-.98-.985-1.348-1.467-.363-.476-.724-1.063-1.207-2.03zM12.12 15.12A3 3 0 017 13s.879.5 2.5.5c0-1 .5-4 1.25-4.5.5 1 .786 1.293 1.371 1.879A2.99 2.99 0 0113 13a2.99 2.99 0 01-.879 2.121z" clip-rule="evenodd"/>
                            </svg>
                            Recommended for Growth
                        </div>

                        <!-- Plan Title -->
                        <h3 class="text-2xl font-bold mb-4">Professional</h3>

                        <!-- Price -->
                        <div class="mb-6">
                            <div class="flex items-baseline">
                                <span class="text-5xl font-bold">$10</span>
                                <span class="text-blue-200 ml-2">/device/month</span>
                            </div>
                            <p class="text-sm text-blue-200 mt-2">Billed annually or $12 monthly</p>
                        </div>

                        <!-- Features -->
                        <ul class="space-y-4 mb-8">
                            <li class="flex items-center gap-3">
                                <svg class="w-5 h-5 text-white flex-shrink-0" fill="currentColor" viewBox="0 0 20 20">
                                    <path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd"/>
                                </svg>
                                <span>Everything in Essential</span>
                            </li>
                            <li class="flex items-center gap-3">
                                <svg class="w-5 h-5 text-white flex-shrink-0" fill="currentColor" viewBox="0 0 20 20">
                                    <path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd"/>
                                </svg>
                                <span>Advanced geofencing</span>
                            </li>
                            <li class="flex items-center gap-3">
                                <svg class="w-5 h-5 text-white flex-shrink-0" fill="currentColor" viewBox="0 0 20 20">
                                    <path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd"/>
                                </svg>
                                <span>Route replay & analytics</span>
                            </li>
                            <li class="flex items-center gap-3">
                                <svg class="w-5 h-5 text-white flex-shrink-0" fill="currentColor" viewBox="0 0 20 20">
                                    <path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd"/>
                                </svg>
                                <span>Driver behavior monitoring</span>
                            </li>
                            <li class="flex items-center gap-3">
                                <svg class="w-5 h-5 text-white flex-shrink-0" fill="currentColor" viewBox="0 0 20 20">
                                    <path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd"/>
                                </svg>
                                <span>Priority email & chat support</span>
                            </li>
                            <li class="flex items-center gap-3">
                                <svg class="w-5 h-5 text-white flex-shrink-0" fill="currentColor" viewBox="0 0 20 20">
                                    <path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd"/>
                                </svg>
                                <span>Custom report generation</span>
                            </li>
                        </ul>

                        <!-- CTA -->
                        <a href="{{ url('/contact') }}"
                           class="block w-full py-3 text-center rounded-xl bg-white text-sky-700 font-semibold hover:bg-slate-100 transition-colors shadow-lg">
                            {{ __('frontend.pricing.request_demo') }}
                        </a>
                    </div>
                </div>

                <!-- Enterprise Plan -->
                <div class="group relative">
                    <div class="h-full p-8 rounded-2xl glass border border-slate-200 dark:border-slate-800 hover:border-purple-300 dark:hover:border-purple-700 transition-all duration-300">
                        <!-- Plan Badge -->
                        <div class="inline-flex items-center gap-2 px-3 py-1 rounded-full bg-purple-100 dark:bg-purple-900/30 text-purple-600 dark:text-purple-400 text-sm font-medium mb-6">
                            <svg class="w-4 h-4" fill="currentColor" viewBox="0 0 20 20">
                                <path fill-rule="evenodd" d="M12.395 2.553a1 1 0 00-1.45-.385c-.345.23-.614.558-.822.88-.214.33-.403.713-.57 1.116-.334.804-.614 1.768-.84 2.734a31.365 31.365 0 00-.613 3.58 2.64 2.64 0 01-.945-1.067c-.328-.68-.398-1.534-.398-2.654A1 1 0 005.05 6.05 6.981 6.981 0 003 11a7 7 0 1011.95-4.95c-.592-.591-.98-.985-1.348-1.467-.363-.476-.724-1.063-1.207-2.03zM12.12 15.12A3 3 0 017 13s.879.5 2.5.5c0-1 .5-4 1.25-4.5.5 1 .786 1.293 1.371 1.879A2.99 2.99 0 0113 13a2.99 2.99 0 01-.879 2.121z" clip-rule="evenodd"/>
                            </svg>
                            For Large Organizations
                        </div>

                        <!-- Plan Title -->
                        <h3 class="text-2xl font-bold text-slate-900 dark:text-white mb-4">Enterprise</h3>

                        <!-- Price -->
                        <div class="mb-6">
                            <div class="flex items-baseline">
                                <span class="text-5xl font-bold text-slate-900 dark:text-white">Custom</span>
                            </div>
                            <p class="text-sm text-slate-500 dark:text-slate-400 mt-2">Volume discounts available</p>
                        </div>

                        <!-- Features -->
                        <ul class="space-y-4 mb-8">
                            <li class="flex items-center gap-3">
                                <svg class="w-5 h-5 text-purple-500 flex-shrink-0" fill="currentColor" viewBox="0 0 20 20">
                                    <path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd"/>
                                </svg>
                                <span>Everything in Professional</span>
                            </li>
                            <li class="flex items-center gap-3">
                                <svg class="w-5 h-5 text-purple-500 flex-shrink-0" fill="currentColor" viewBox="0 0 20 20">
                                    <path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd"/>
                                </svg>
                                <span>Unlimited devices</span>
                            </li>
                            <li class="flex items-center gap-3">
                                <svg class="w-5 h-5 text-purple-500 flex-shrink-0" fill="currentColor" viewBox="0 0 20 20">
                                    <path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd"/>
                                </svg>
                                <span>Custom API & integrations</span>
                            </li>
                            <li class="flex items-center gap-3">
                                <svg class="w-5 h-5 text-purple-500 flex-shrink-0" fill="currentColor" viewBox="0 0 20 20">
                                    <path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd"/>
                                </svg>
                                <span>SLA with 99.9% uptime</span>
                            </li>
                            <li class="flex items-center gap-3">
                                <svg class="w-5 h-5 text-purple-500 flex-shrink-0" fill="currentColor" viewBox="0 0 20 20">
                                    <path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd"/>
                                </svg>
                                <span>Dedicated account manager</span>
                            </li>
                            <li class="flex items-center gap-3">
                                <svg class="w-5 h-5 text-purple-500 flex-shrink-0" fill="currentColor" viewBox="0 0 20 20">
                                    <path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd"/>
                                </svg>
                                <span>On-premise deployment option</span>
                            </li>
                        </ul>

                        <!-- CTA -->
                        <a href="{{ url('contact') }}"
                           class="block w-full py-3 text-center rounded-xl border-2 border-purple-500 text-purple-600 dark:text-purple-400 font-semibold hover:bg-purple-50 dark:hover:bg-purple-900/20 transition-colors">
                            {{ __('frontend.pricing.contact_sales') }}
                        </a>
                    </div>
                </div>
            </div>
            @endif

            <!-- All Plans Include -->
            <div class="mt-20 p-8 rounded-2xl glass border border-slate-200 dark:border-slate-800">
                <h3 class="text-2xl font-bold text-center mb-8">{{ __('frontend.pricing.all_plans_include') }}</h3>
                <div class="grid md:grid-cols-3 gap-8">
                    <div class="text-center">
                        <div class="w-12 h-12 rounded-xl bg-gradient-to-br from-sky-500/10 to-blue-500/10 flex items-center justify-center mx-auto mb-4">
                            <i class="fa-solid fa-shield-check text-sky-500 text-xl"></i>
                        </div>
                        <h4 class="font-semibold mb-2">{{ __('frontend.pricing.enterprise_security') }}</h4>
                        <p class="text-sm text-slate-600 dark:text-slate-400">{{ __('frontend.pricing.enterprise_security_desc') }}</p>
                    </div>
                    <div class="text-center">
                        <div class="w-12 h-12 rounded-xl bg-gradient-to-br from-emerald-500/10 to-teal-500/10 flex items-center justify-center mx-auto mb-4">
                            <i class="fa-solid fa-mobile-screen text-emerald-500 text-xl"></i>
                        </div>
                        <h4 class="font-semibold mb-2">{{ __('frontend.pricing.mobile_apps') }}</h4>
                        <p class="text-sm text-slate-600 dark:text-slate-400">{{ __('frontend.pricing.mobile_apps_desc') }}</p>
                    </div>
                    <div class="text-center">
                        <div class="w-12 h-12 rounded-xl bg-gradient-to-br from-amber-500/10 to-orange-500/10 flex items-center justify-center mx-auto mb-4">
                            <i class="fa-solid fa-sync text-amber-500 text-xl"></i>
                        </div>
                        <h4 class="font-semibold mb-2">{{ __('frontend.pricing.regular_updates') }}</h4>
                        <p class="text-sm text-slate-600 dark:text-slate-400">{{ __('frontend.pricing.regular_updates_desc') }}</p>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- FAQ Section -->
    <section class="pb-32 relative">
        <div class="max-w-4xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="text-center mb-16">
                <h2 class="text-4xl font-bold mb-6">{{ __('frontend.pricing.faq_title') }}</h2>
                <p class="text-xl text-slate-600 dark:text-slate-300">{{ __('frontend.pricing.faq_subtitle') }}</p>
            </div>

            <div class="space-y-6">
                @include('frontend.partials.faq-accordion', ['faqs' => __('frontend.pricing.faqs')])
            </div>

            <!-- Still have questions -->
            <div class="mt-12 text-center">
                <p class="text-slate-600 dark:text-slate-400 mb-6">{{ __('frontend.pricing.still_have_questions') }}</p>
                <a href="{{ url('/contact') }}"
                   class="inline-flex items-center gap-2 px-6 py-3 bg-gradient-to-r from-sky-600 to-blue-700 text-white font-semibold rounded-lg hover:shadow-lg transition-all">
                    <i class="fa-solid fa-envelope"></i>
                    {{ __('frontend.pricing.contact_support') }}
                </a>
            </div>
        </div>
    </section>

    <!-- Final CTA -->
    <section class="py-32 relative overflow-hidden">
        <div class="absolute inset-0 bg-gradient-to-r from-sky-600 via-blue-600 to-indigo-700 animate-gradient"></div>

        <!-- Animated Elements -->
        <div class="absolute top-0 left-0 w-full h-full">
            <div class="absolute top-1/4 left-1/4 w-96 h-96 bg-white/10 rounded-full blur-3xl animate-pulse"></div>
            <div class="absolute bottom-1/4 right-1/4 w-96 h-96 bg-white/10 rounded-full blur-3xl animate-pulse" style="animation-delay: 1s"></div>
        </div>

        <div class="relative max-w-4xl mx-auto px-4 sm:px-6 lg:px-8 text-center">
            <h2 class="text-4xl lg:text-5xl font-bold text-white mb-8">{{ __('frontend.pricing.cta_title') }}</h2>
            <p class="text-xl text-blue-100 mb-12">{{ __('frontend.pricing.cta_subtitle') }}</p>

            <div class="flex flex-col sm:flex-row gap-6 justify-center">
                <a href="{{ url('register') }}"
                   class="group relative px-10 py-5 bg-white text-blue-700 rounded-2xl font-bold text-lg overflow-hidden shadow-2xl hover:shadow-3xl transition-all duration-300">
                    <span class="relative z-10 flex items-center justify-center gap-3">
                        {{ __('frontend.pricing.request_demo') }}
                        <svg class="w-5 h-5 transform group-hover:translate-x-2 transition-transform" fill="currentColor" viewBox="0 0 20 20">
                            <path fill-rule="evenodd" d="M10.293 5.293a1 1 0 011.414 0l4 4a1 1 0 010 1.414l-4 4a1 1 0 01-1.414-1.414L12.586 11H5a1 1 0 110-2h7.586l-2.293-2.293a1 1 0 010-1.414z" clip-rule="evenodd"/>
                        </svg>
                    </span>
                    <div class="absolute inset-0 bg-gradient-to-r from-white to-blue-100 transform -translate-x-full group-hover:translate-x-0 transition-transform duration-500"></div>
                </a>

                <a href="{{'demo/login'}}"
                   class="group px-10 py-5 bg-white/20 backdrop-blur-sm text-white rounded-2xl font-bold text-lg border-2 border-white/30 hover:bg-white/30 transition-all">
                    <span class="flex items-center justify-center gap-3">
                        <i class="fa-solid fa-calendar"></i>
                        {{ __('frontend.pricing.schedule_demo') }}
                    </span>
                </a>
            </div>

            <div class="mt-12 text-blue-100/70 text-sm">
                <p>{{ __('frontend.pricing.cta_footer') }}</p>
            </div>
        </div>
    </section>
@endsection

@push('scripts')
    @if(isset($plans) && $plans->isNotEmpty())
        <script src="{{ asset('js/pricing-billing-toggle.js') }}?v={{ filemtime(public_path('js/pricing-billing-toggle.js')) }}"></script>
    @endif
@endpush
