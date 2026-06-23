<!-- resources/views/frontend/pages/about.blade.php -->
@extends('frontend.layout')

@section('title', __('seo.pages.about.title'))
@section('description', __('seo.pages.about.description'))

@section('content')

    <!-- Hero -->
    <section class="relative min-h-[60vh] flex items-center overflow-hidden">
        <div class="absolute inset-0 bg-gradient-to-br from-slate-50 via-emerald-50 to-teal-100 dark:from-slate-950 dark:via-emerald-950 dark:to-teal-950"></div>

        <div class="relative max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-24">
            @include('frontend.partials.page-breadcrumbs')
            <div class="grid lg:grid-cols-2 gap-12 items-center">
                <div>
                <span class="inline-block px-4 py-1.5 bg-gradient-to-r from-emerald-500/10 to-teal-500/10 rounded-full text-emerald-600 dark:text-emerald-400 font-semibold text-sm mb-4">
                    {{ __('frontend.pages.about.badge') }}
                </span>
                    <h1 class="text-5xl lg:text-6xl font-bold mb-6">
                        {{ __('frontend.pages.about.heading') }} <span class="bg-gradient-to-r from-emerald-500 to-teal-600 bg-clip-text text-transparent">{{ __('frontend.pages.about.heading_highlight') }}</span>
                    </h1>
                    <p class="text-xl text-slate-600 dark:text-slate-300">
                        {{ __('frontend.pages.about.intro') }}
                    </p>
                </div>
                <div class="relative">
                    <div class="rounded-2xl overflow-hidden shadow-2xl">
                        <div class="aspect-video bg-gradient-to-br from-emerald-500 to-teal-600"></div>
                    </div>
                    <div class="absolute -bottom-6 -left-6 bg-white dark:bg-slate-900 p-6 rounded-2xl shadow-xl">
                        <div class="text-3xl font-bold text-emerald-600 dark:text-emerald-400">9+</div>
                        <div class="text-sm text-slate-600 dark:text-slate-400">{{ __('frontend.pages.about.years') }}</div>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- Timeline -->
    <section class="py-20">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="text-center mb-16">
                <h2 class="text-3xl font-bold mb-4">Our Journey</h2>
                <p class="text-slate-600 dark:text-slate-400">Milestones in our growth and innovation</p>
            </div>

            <div class="relative">
                <!-- Timeline line -->
                <div class="absolute left-1/2 transform -translate-x-1/2 h-full w-1 bg-gradient-to-b from-sky-500 to-blue-600"></div>

                @php
                    $timeline = [
                        [
                            'year' => '2015',
                            'title' => 'Company Founded',
                            'desc' => 'FalconEyeGPS established with focus on real-time GPS tracking',
                            'align' => 'left'
                        ],
                        [
                            'year' => '2017',
                            'title' => 'First Enterprise Client',
                            'desc' => 'Onboarded first regional logistics fleet in the Middle East',
                            'align' => 'right'
                        ],
                        [
                            'year' => '2019',
                            'title' => 'Mobile App Launch',
                            'desc' => 'Released Android app for live tracking and fleet alerts',
                            'align' => 'left'
                        ],
                        [
                            'year' => '2021',
                            'title' => 'Global Expansion',
                            'desc' => 'Opened offices in Europe and APAC regions',
                            'align' => 'right'
                        ],
                        [
                            'year' => '2023',
                            'title' => 'Regional Growth',
                            'desc' => 'Expanded fleet operations support across Saudi Arabia and Pakistan',
                            'align' => 'left'
                        ],
                        [
                            'year' => '2024',
                            'title' => 'Next Generation Platform',
                            'desc' => 'Launched AI-driven autonomous fleet management system',
                            'align' => 'right'
                        ],
                    ];
                @endphp

                @foreach($timeline as $item)
                    <div class="relative mb-12 {{ $item['align'] === 'left' ? 'pr-1/2' : 'pl-1/2' }}">
                        <div class="p-6 rounded-2xl glass border border-slate-200 dark:border-slate-800 max-w-lg {{ $item['align'] === 'left' ? 'ml-auto' : 'mr-auto' }}">
                            <div class="flex items-center gap-4 mb-4">
                                <div class="w-12 h-12 rounded-xl bg-gradient-to-br from-sky-500 to-blue-600 flex items-center justify-center">
                                    <span class="text-white font-bold">{{ $item['year'] }}</span>
                                </div>
                                <h3 class="text-xl font-bold">{{ $item['title'] }}</h3>
                            </div>
                            <p class="text-slate-600 dark:text-slate-400">{{ $item['desc'] }}</p>
                        </div>
                        <div class="absolute top-6 {{ $item['align'] === 'left' ? 'right-0 translate-x-1/2' : 'left-0 -translate-x-1/2' }}">
                            <div class="w-6 h-6 rounded-full bg-gradient-to-br from-sky-500 to-blue-600 border-4 border-white dark:border-slate-900"></div>
                        </div>
                    </div>
                @endforeach
            </div>
        </div>
    </section>

    <!-- Leadership Team -->
    <section class="py-20 bg-slate-50 dark:bg-slate-900/50">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="text-center mb-16">
                <h2 class="text-3xl font-bold mb-4">Leadership Team</h2>
                <p class="text-slate-600 dark:text-slate-400">Experienced professionals driving innovation</p>
            </div>

            <div class="grid md:grid-cols-2 lg:grid-cols-4 gap-8">
                @php
                    $team = [
                        [
                            'name' => 'Alex Johnson',
                            'role' => 'CEO & Founder',
                            'bio' => 'Former Google Maps engineer with 15+ years in GPS technology',
                            'color' => 'from-sky-500 to-blue-600'
                        ],
                        [
                            'name' => 'Maria Chen',
                            'role' => 'CTO',
                            'bio' => 'AI/ML expert with PhD in Computer Science from MIT',
                            'color' => 'from-emerald-500 to-teal-600'
                        ],
                        [
                            'name' => 'David Wilson',
                            'role' => 'COO',
                            'bio' => 'Operations veteran with 20+ years in logistics industry',
                            'color' => 'from-amber-500 to-orange-600'
                        ],
                        [
                            'name' => 'Sarah Miller',
                            'role' => 'Head of Product',
                            'bio' => 'Product leader with experience at Uber and Amazon',
                            'color' => 'from-purple-500 to-pink-600'
                        ],
                    ];
                @endphp

                @foreach($team as $member)
                    <div class="group">
                        <div class="p-6 rounded-2xl glass border border-slate-200 dark:border-slate-800 hover:border-slate-300 dark:hover:border-slate-700 transition-all">
                            <div class="w-20 h-20 rounded-xl bg-gradient-to-br {{ $member['color'] }} flex items-center justify-center mb-6 text-white text-2xl font-bold">
                                {{ substr($member['name'], 0, 1) }}
                            </div>
                            <h3 class="font-bold text-xl mb-2">{{ $member['name'] }}</h3>
                            <div class="text-sky-600 dark:text-sky-400 font-medium mb-3">{{ $member['role'] }}</div>
                            <p class="text-slate-600 dark:text-slate-400 text-sm">{{ $member['bio'] }}</p>

                            <div class="flex gap-3 mt-6">
                                <a href="#" class="w-8 h-8 rounded-lg bg-slate-100 dark:bg-slate-800 flex items-center justify-center text-slate-600 dark:text-slate-400 hover:bg-slate-200 dark:hover:bg-slate-700">
                                    <i class="fab fa-linkedin"></i>
                                </a>
                                <a href="#" class="w-8 h-8 rounded-lg bg-slate-100 dark:bg-slate-800 flex items-center justify-center text-slate-600 dark:text-slate-400 hover:bg-slate-200 dark:hover:bg-slate-700">
                                    <i class="fab fa-twitter"></i>
                                </a>
                            </div>
                        </div>
                    </div>
                @endforeach
            </div>
        </div>
    </section>

    <!-- Awards & Recognition -->
    <section class="py-20">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="text-center mb-16">
                <h2 class="text-3xl font-bold mb-4">Awards & Recognition</h2>
                <p class="text-slate-600 dark:text-slate-400">Industry recognition for innovation and excellence</p>
            </div>

            <div class="grid md:grid-cols-3 gap-8">
                <div class="p-8 rounded-2xl glass text-center">
                    <div class="w-16 h-16 rounded-xl bg-gradient-to-br from-amber-500 to-orange-600 flex items-center justify-center mx-auto mb-6">
                        <i class="fa-solid fa-trophy text-white text-2xl"></i>
                    </div>
                    <h3 class="font-bold text-xl mb-3">Tech Innovation Award 2023</h3>
                    <p class="text-slate-600 dark:text-slate-400">For groundbreaking AI in fleet management</p>
                </div>

                <div class="p-8 rounded-2xl glass text-center">
                    <div class="w-16 h-16 rounded-xl bg-gradient-to-br from-sky-500 to-blue-600 flex items-center justify-center mx-auto mb-6">
                        <i class="fa-solid fa-star text-white text-2xl"></i>
                    </div>
                    <h3 class="font-bold text-xl mb-3">Forbes Tech 100</h3>
                    <p class="text-slate-600 dark:text-slate-400">Listed among top 100 tech companies to watch</p>
                </div>

                <div class="p-8 rounded-2xl glass text-center">
                    <div class="w-16 h-16 rounded-xl bg-gradient-to-br from-emerald-500 to-teal-600 flex items-center justify-center mx-auto mb-6">
                        <i class="fa-solid fa-award text-white text-2xl"></i>
                    </div>
                    <h3 class="font-bold text-xl mb-3">Sustainability Excellence</h3>
                    <p class="text-slate-600 dark:text-slate-400">Reducing carbon footprint through optimization</p>
                </div>
            </div>
        </div>
    </section>

@endsection
