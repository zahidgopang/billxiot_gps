<!-- resources/views/frontend/pages/docs.blade.php -->
@extends('frontend.layout')

@section('title', __('frontend.pages.docs.title'))
@section('description', 'Comprehensive documentation, guides, and tutorials for FalconEyeGPS platform.')

@section('content')

    <!-- Hero -->
    <section class="relative min-h-[60vh] flex items-center overflow-hidden">
        <div class="absolute inset-0 bg-gradient-to-br from-slate-50 via-emerald-50 to-teal-100 dark:from-slate-950 dark:via-emerald-950 dark:to-teal-950"></div>

        <div class="relative max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-24">
            <div class="grid lg:grid-cols-2 gap-12 items-center">
                <div>
                <span class="inline-block px-4 py-1.5 bg-gradient-to-r from-emerald-500/10 to-teal-500/10 rounded-full text-emerald-600 dark:text-emerald-400 font-semibold text-sm mb-4">
                    DOCUMENTATION
                </span>
                    <h1 class="text-5xl lg:text-6xl font-bold mb-6">
                        Complete <span class="bg-gradient-to-r from-emerald-500 to-teal-600 bg-clip-text text-transparent">Guides</span>
                    </h1>
                    <p class="text-xl text-slate-600 dark:text-slate-300">
                        Comprehensive documentation to help you get the most out of FalconEyeGPS.
                    </p>
                </div>

                <div class="relative">
                    <div class="grid grid-cols-2 gap-4">
                        <div class="p-6 rounded-2xl glass border border-slate-200 dark:border-slate-800">
                            <div class="w-12 h-12 rounded-xl bg-gradient-to-br from-emerald-500 to-teal-600 flex items-center justify-center mb-4">
                                <i class="fa-solid fa-book-open text-white text-xl"></i>
                            </div>
                            <h3 class="font-bold text-lg mb-2">User Guides</h3>
                            <p class="text-slate-600 dark:text-slate-400 text-sm">Step-by-step tutorials</p>
                        </div>

                        <div class="p-6 rounded-2xl glass border border-slate-200 dark:border-slate-800">
                            <div class="w-12 h-12 rounded-xl bg-gradient-to-br from-sky-500 to-blue-600 flex items-center justify-center mb-4">
                                <i class="fa-solid fa-code text-white text-xl"></i>
                            </div>
                            <h3 class="font-bold text-lg mb-2">API Reference</h3>
                            <p class="text-slate-600 dark:text-slate-400 text-sm">Developer documentation</p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- Documentation Categories -->
    <section class="py-20">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="text-center mb-16">
                <h2 class="text-3xl font-bold mb-4">Documentation Categories</h2>
                <p class="text-slate-600 dark:text-slate-400">Browse guides by category</p>
            </div>

            <div class="grid md:grid-cols-3 gap-8">
                @php
                    $categories = [
                        [
                            'title' => 'Getting Started',
                            'count' => '12 guides',
                            'desc' => 'Complete setup and configuration',
                            'icon' => 'fa-solid fa-rocket',
                            'color' => 'from-sky-500 to-blue-600'
                        ],
                        [
                            'title' => 'Device Management',
                            'count' => '18 guides',
                            'desc' => 'Installation and device setup',
                            'icon' => 'fa-solid fa-satellite-dish',
                            'color' => 'from-emerald-500 to-teal-600'
                        ],
                        [
                            'title' => 'Features Guide',
                            'count' => '25 guides',
                            'desc' => 'Detailed feature explanations',
                            'icon' => 'fa-solid fa-star',
                            'color' => 'from-amber-500 to-orange-600'
                        ],
                        [
                            'title' => 'Reporting & Analytics',
                            'count' => '15 guides',
                            'desc' => 'Data analysis and reporting',
                            'icon' => 'fa-solid fa-chart-bar',
                            'color' => 'from-purple-500 to-pink-600'
                        ],
                        [
                            'title' => 'API Integration',
                            'count' => '32 guides',
                            'desc' => 'Developer documentation',
                            'icon' => 'fa-solid fa-code',
                            'color' => 'from-red-500 to-rose-600'
                        ],
                        [
                            'title' => 'Troubleshooting',
                            'count' => '20 guides',
                            'desc' => 'Common issues and solutions',
                            'icon' => 'fa-solid fa-wrench',
                            'color' => 'from-indigo-500 to-violet-600'
                        ],
                    ];
                @endphp

                @foreach($categories as $category)
                    <a href="#" class="group">
                        <div class="h-full p-8 rounded-2xl glass border border-slate-200 dark:border-slate-800 hover:border-slate-300 dark:hover:border-slate-700 transition-all">
                            <div class="flex items-start gap-4 mb-6">
                                <div class="w-14 h-14 rounded-xl bg-gradient-to-br {{ $category['color'] }} flex items-center justify-center flex-shrink-0">
                                    <i class="{{ $category['icon'] }} text-white text-xl"></i>
                                </div>
                                <div>
                                    <div class="text-sm text-slate-500 dark:text-slate-400 mb-1">{{ $category['count'] }}</div>
                                    <h3 class="font-bold text-xl group-hover:text-emerald-600 dark:group-hover:text-emerald-400 transition-colors">
                                        {{ $category['title'] }}
                                    </h3>
                                </div>
                            </div>

                            <p class="text-slate-600 dark:text-slate-400 mb-6">{{ $category['desc'] }}</p>

                            <div class="flex items-center text-emerald-600 dark:text-emerald-400">
                                <span>Browse Guides</span>
                                <i class="fa-solid fa-arrow-right ml-2 group-hover:translate-x-1 transition-transform"></i>
                            </div>
                        </div>
                    </a>
                @endforeach
            </div>
        </div>
    </section>

    <!-- Popular Guides -->
    <section class="py-20 bg-slate-50 dark:bg-slate-900/50">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="text-center mb-16">
                <h2 class="text-3xl font-bold mb-4">Popular Guides</h2>
                <p class="text-slate-600 dark:text-slate-400">Most viewed documentation articles</p>
            </div>

            <div class="space-y-6 max-w-4xl mx-auto">
                @php
                    $guides = [
                        [
                            'title' => 'Initial Setup and Configuration',
                            'desc' => 'Complete guide to setting up your FalconEyeGPS account and adding devices',
                            'category' => 'Getting Started',
                            'updated' => 'Updated 2 days ago'
                        ],
                        [
                            'title' => 'Real-Time Tracking Dashboard Guide',
                            'desc' => 'Understanding all features of the live tracking dashboard',
                            'category' => 'Features',
                            'updated' => 'Updated 1 week ago'
                        ],
                        [
                            'title' => 'Geofencing Setup and Alerts',
                            'desc' => 'Create virtual boundaries and configure notification rules',
                            'category' => 'Alerts',
                            'updated' => 'Updated 3 days ago'
                        ],
                        [
                            'title' => 'API Authentication Guide',
                            'desc' => 'Setting up API keys and authentication for integration',
                            'category' => 'API',
                            'updated' => 'Updated 2 weeks ago'
                        ],
                        [
                            'title' => 'Report Generation and Export',
                            'desc' => 'Create custom reports and export data in various formats',
                            'category' => 'Reporting',
                            'updated' => 'Updated 5 days ago'
                        ],
                    ];
                @endphp

                @foreach($guides as $guide)
                    <a href="#" class="group block">
                        <div class="p-6 rounded-2xl glass border border-slate-200 dark:border-slate-800 hover:border-slate-300 dark:hover:border-slate-700 transition-colors">
                            <div class="flex flex-col md:flex-row md:items-center justify-between gap-4">
                                <div>
                                <span class="inline-block px-3 py-1 rounded-full bg-emerald-100 dark:bg-emerald-900/30 text-emerald-600 dark:text-emerald-400 text-xs font-medium mb-2">
                                    {{ $guide['category'] }}
                                </span>
                                    <h3 class="font-bold text-lg mb-2 group-hover:text-emerald-600 dark:group-hover:text-emerald-400">
                                        {{ $guide['title'] }}
                                    </h3>
                                    <p class="text-slate-600 dark:text-slate-400 text-sm mb-2">{{ $guide['desc'] }}</p>
                                    <div class="text-xs text-slate-500 dark:text-slate-400">{{ $guide['updated'] }}</div>
                                </div>
                                <div class="flex items-center gap-3">
                                <span class="text-emerald-600 dark:text-emerald-400">
                                    <i class="fa-solid fa-arrow-right group-hover:translate-x-1 transition-transform"></i>
                                </span>
                                </div>
                            </div>
                        </div>
                    </a>
                @endforeach
            </div>
        </div>
    </section>

    <!-- Download Section -->
    <section class="py-20">
        <div class="max-w-4xl mx-auto px-4 sm:px-6 lg:px-8 text-center">
            <div class="p-8 rounded-2xl glass border border-slate-200 dark:border-slate-800">
                <div class="w-16 h-16 rounded-xl bg-gradient-to-br from-emerald-500 to-teal-600 flex items-center justify-center mx-auto mb-6">
                    <i class="fa-solid fa-download text-white text-2xl"></i>
                </div>
                <h2 class="text-3xl font-bold mb-4">Download Documentation</h2>
                <p class="text-xl text-slate-600 dark:text-slate-400 mb-8">
                    Download complete documentation in PDF format for offline reference.
                </p>

                <div class="grid md:grid-cols-2 gap-6 mb-8">
                    <a href="#" class="p-6 rounded-xl bg-slate-50 dark:bg-slate-800/50 hover:bg-slate-100 dark:hover:bg-slate-800 transition-colors">
                        <div class="flex items-center gap-4">
                            <div class="w-12 h-12 rounded-lg bg-gradient-to-br from-sky-500 to-blue-600 flex items-center justify-center">
                                <i class="fa-solid fa-file-pdf text-white"></i>
                            </div>
                            <div>
                                <div class="font-medium">User Manual</div>
                                <div class="text-sm text-slate-500 dark:text-slate-400">Complete user guide (PDF)</div>
                            </div>
                        </div>
                    </a>

                    <a href="#" class="p-6 rounded-xl bg-slate-50 dark:bg-slate-800/50 hover:bg-slate-100 dark:hover:bg-slate-800 transition-colors">
                        <div class="flex items-center gap-4">
                            <div class="w-12 h-12 rounded-lg bg-gradient-to-br from-emerald-500 to-teal-600 flex items-center justify-center">
                                <i class="fa-solid fa-file-code text-white"></i>
                            </div>
                            <div>
                                <div class="font-medium">API Documentation</div>
                                <div class="text-sm text-slate-500 dark:text-slate-400">Developer guide (PDF)</div>
                            </div>
                        </div>
                    </a>
                </div>

                <p class="text-sm text-slate-500 dark:text-slate-400">
                    Last updated: December 15, 2024
                </p>
            </div>
        </div>
    </section>

@endsection
