<!-- resources/views/frontend/pages/company.blade.php -->
@extends('frontend.layout')

@section('title', __('frontend.pages.company.title'))
@section('description', 'Learn about FalconEyeGPS, our mission, values, and leadership team.')

@section('content')

    <!-- Hero Section -->
    <section class="relative min-h-[60vh] flex items-center overflow-hidden">
        <div class="absolute inset-0 bg-gradient-to-br from-slate-50 via-blue-50 to-sky-100 dark:from-slate-950 dark:via-slate-900 dark:to-sky-950"></div>
        <div class="absolute top-1/4 left-1/4 w-96 h-96 bg-gradient-to-r from-sky-300/20 to-blue-400/10 rounded-full blur-3xl"></div>

        <div class="relative max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-24 text-center">
        <span class="inline-block px-4 py-1.5 bg-gradient-to-r from-sky-500/10 to-blue-500/10 rounded-full text-sky-600 dark:text-sky-400 font-semibold text-sm mb-4">
            OUR COMPANY
        </span>
            <h1 class="text-5xl lg:text-6xl font-bold mb-6">
                Revolutionizing <span class="bg-gradient-to-r from-sky-500 to-blue-600 bg-clip-text text-transparent">Fleet Management</span>
            </h1>
            <p class="text-xl text-slate-600 dark:text-slate-300 max-w-3xl mx-auto">
                FalconEyeGPS is on a mission to transform fleet operations with intelligent tracking solutions.
            </p>
        </div>
    </section>

    <!-- Stats Section -->
    <section class="py-20">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="grid md:grid-cols-4 gap-8 text-center">
                <div class="p-8 rounded-2xl glass">
                    <div class="text-4xl font-bold bg-gradient-to-r from-sky-600 to-blue-700 bg-clip-text text-transparent">10K+</div>
                    <div class="text-sm text-slate-500 dark:text-slate-400 mt-2">Enterprise Clients</div>
                </div>
                <div class="p-8 rounded-2xl glass">
                    <div class="text-4xl font-bold bg-gradient-to-r from-emerald-600 to-teal-700 bg-clip-text text-transparent">150+</div>
                    <div class="text-sm text-slate-500 dark:text-slate-400 mt-2">Countries Served</div>
                </div>
                <div class="p-8 rounded-2xl glass">
                    <div class="text-4xl font-bold bg-gradient-to-r from-amber-600 to-orange-700 bg-clip-text text-transparent">99.9%</div>
                    <div class="text-sm text-slate-500 dark:text-slate-400 mt-2">Uptime SLA</div>
                </div>
                <div class="p-8 rounded-2xl glass">
                    <div class="text-4xl font-bold bg-gradient-to-r from-purple-600 to-pink-700 bg-clip-text text-transparent">24/7</div>
                    <div class="text-sm text-slate-500 dark:text-slate-400 mt-2">Global Support</div>
                </div>
            </div>
        </div>
    </section>

    <!-- Mission & Vision -->
    <section class="py-20 bg-slate-50 dark:bg-slate-900/50">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="grid lg:grid-cols-2 gap-12">
                <div class="p-8 rounded-2xl glass">
                    <div class="w-16 h-16 rounded-xl bg-gradient-to-br from-sky-500 to-blue-600 flex items-center justify-center mb-6">
                        <i class="fa-solid fa-bullseye text-white text-2xl"></i>
                    </div>
                    <h3 class="text-2xl font-bold mb-4">Our Mission</h3>
                    <p class="text-slate-600 dark:text-slate-400 mb-4">
                        To empower businesses with real-time visibility and control over their fleets through cutting-edge GPS technology.
                    </p>
                    <ul class="space-y-3">
                        <li class="flex items-start gap-2">
                            <i class="fa-solid fa-check text-emerald-500 mt-1"></i>
                            <span>Reduce operational costs by up to 30%</span>
                        </li>
                        <li class="flex items-start gap-2">
                            <i class="fa-solid fa-check text-emerald-500 mt-1"></i>
                            <span>Improve fleet safety and compliance</span>
                        </li>
                        <li class="flex items-start gap-2">
                            <i class="fa-solid fa-check text-emerald-500 mt-1"></i>
                            <span>Enable data-driven decision making</span>
                        </li>
                    </ul>
                </div>

                <div class="p-8 rounded-2xl glass">
                    <div class="w-16 h-16 rounded-xl bg-gradient-to-br from-emerald-500 to-teal-600 flex items-center justify-center mb-6">
                        <i class="fa-solid fa-eye text-white text-2xl"></i>
                    </div>
                    <h3 class="text-2xl font-bold mb-4">Our Vision</h3>
                    <p class="text-slate-600 dark:text-slate-400 mb-4">
                        To become the global standard for intelligent fleet management, connecting millions of vehicles to a smarter, safer future.
                    </p>
                    <ul class="space-y-3">
                        <li class="flex items-start gap-2">
                            <i class="fa-solid fa-rocket text-sky-500 mt-1"></i>
                            <span>Live GPS tracking and fleet alerts</span>
                        </li>
                        <li class="flex items-start gap-2">
                            <i class="fa-solid fa-rocket text-sky-500 mt-1"></i>
                            <span>Autonomous fleet integration</span>
                        </li>
                        <li class="flex items-start gap-2">
                            <i class="fa-solid fa-rocket text-sky-500 mt-1"></i>
                            <span>Sustainable fleet optimization</span>
                        </li>
                    </ul>
                </div>
            </div>
        </div>
    </section>

    <!-- Values -->
    <section class="py-20">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="text-center mb-12">
                <h2 class="text-3xl font-bold mb-4">Our Core Values</h2>
                <p class="text-slate-600 dark:text-slate-400">Guiding principles that drive our innovation</p>
            </div>

            <div class="grid md:grid-cols-3 gap-8">
                @php
                    $values = [
                        [
                            'icon' => 'fa-solid fa-shield-check',
                            'color' => 'from-sky-500 to-blue-600',
                            'title' => 'Reliability',
                            'desc' => '99.9% uptime guarantee and enterprise-grade security.'
                        ],
                        [
                            'icon' => 'fa-solid fa-lightbulb',
                            'color' => 'from-emerald-500 to-teal-600',
                            'title' => 'Innovation',
                            'desc' => 'Continuous improvement through cutting-edge technology.'
                        ],
                        [
                            'icon' => 'fa-solid fa-users',
                            'color' => 'from-amber-500 to-orange-600',
                            'title' => 'Partnership',
                            'desc' => 'Building lasting relationships with our clients.'
                        ],
                        [
                            'icon' => 'fa-solid fa-chart-line',
                            'color' => 'from-purple-500 to-pink-600',
                            'title' => 'Excellence',
                            'desc' => 'Striving for perfection in everything we do.'
                        ],
                        [
                            'icon' => 'fa-solid fa-earth-americas',
                            'color' => 'from-red-500 to-rose-600',
                            'title' => 'Sustainability',
                            'desc' => 'Reducing environmental impact through optimization.'
                        ],
                        [
                            'icon' => 'fa-solid fa-handshake',
                            'color' => 'from-indigo-500 to-violet-600',
                            'title' => 'Integrity',
                            'desc' => 'Transparent, honest, and ethical business practices.'
                        ],
                    ];
                @endphp

                @foreach($values as $value)
                    <div class="p-6 rounded-2xl glass border border-slate-200 dark:border-slate-800 hover:border-slate-300 dark:hover:border-slate-700 transition-colors">
                        <div class="w-12 h-12 rounded-xl bg-gradient-to-br {{ $value['color'] }} flex items-center justify-center mb-4">
                            <i class="{{ $value['icon'] }} text-white text-xl"></i>
                        </div>
                        <h4 class="font-bold text-lg mb-2">{{ $value['title'] }}</h4>
                        <p class="text-slate-600 dark:text-slate-400 text-sm">{{ $value['desc'] }}</p>
                    </div>
                @endforeach
            </div>
        </div>
    </section>

    <!-- CTA -->
    <section class="py-20 bg-gradient-to-r from-sky-600 to-blue-700">
        <div class="max-w-4xl mx-auto px-4 sm:px-6 lg:px-8 text-center">
            <h2 class="text-3xl lg:text-4xl font-bold text-white mb-6">Join Thousands of Successful Fleets</h2>
            <p class="text-xl text-blue-100 mb-8">Transform your fleet operations with FalconEyeGPS</p>
            <div class="flex flex-col sm:flex-row gap-4 justify-center">
                <a href="{{ route('contact') }}" class="px-8 py-3 bg-white text-blue-700 font-semibold rounded-xl hover:bg-blue-50 transition-colors">
                    Contact Sales
                </a>
                <a href="{{ route('contact') }}" class="px-8 py-3 border-2 border-white text-white font-semibold rounded-xl hover:bg-white/10 transition-colors">
                    Request Demo
                </a>
            </div>
        </div>
    </section>

@endsection
