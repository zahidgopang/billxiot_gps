<!-- resources/views/frontend/pages/status.blade.php -->
@extends('frontend.layout')

@section('title', __('frontend.pages.status.title'))
@section('description', 'Real-time status of FalconEyeGPS services, API, and platform components.')

@section('content')

    <!-- Hero -->
    <section class="relative min-h-[60vh] flex items-center overflow-hidden">
        <div class="absolute inset-0 bg-gradient-to-br from-slate-50 via-emerald-50 to-teal-100 dark:from-slate-950 dark:via-emerald-950 dark:to-teal-950"></div>

        <div class="relative max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-24 text-center">
            <div class="inline-flex items-center gap-2 px-4 py-2 bg-white/20 backdrop-blur-sm rounded-full border border-white/30 mb-6">
                <div class="w-2 h-2 rounded-full bg-emerald-400 animate-pulse"></div>
                <span class="text-sm text-emerald-900 dark:text-emerald-400 font-medium">All Systems Operational</span>
            </div>

            <h1 class="text-5xl lg:text-6xl font-bold mb-6">
                System <span class="bg-gradient-to-r from-emerald-500 to-teal-600 bg-clip-text text-transparent">Status</span>
            </h1>
            <p class="text-xl text-slate-600 dark:text-slate-300 max-w-3xl mx-auto">
                Real-time status of FalconEyeGPS services and platform components.
            </p>
        </div>
    </section>

    <!-- Overall Status -->
    <section class="py-20">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="text-center mb-16">
                <h2 class="text-3xl font-bold mb-4">Current Status</h2>
                <p class="text-slate-600 dark:text-slate-400">Updated every minute</p>
            </div>

            <div class="grid md:grid-cols-4 gap-8">
                <div class="p-8 rounded-2xl glass border border-emerald-200 dark:border-emerald-800">
                    <div class="text-center">
                        <div class="text-4xl font-bold text-emerald-600 dark:text-emerald-400 mb-2">99.9%</div>
                        <div class="text-sm text-slate-600 dark:text-slate-400">Uptime Last 30 Days</div>
                    </div>
                </div>

                <div class="p-8 rounded-2xl glass border border-emerald-200 dark:border-emerald-800">
                    <div class="text-center">
                        <div class="text-4xl font-bold text-emerald-600 dark:text-emerald-400 mb-2">24/7</div>
                        <div class="text-sm text-slate-600 dark:text-slate-400">Monitoring</div>
                    </div>
                </div>

                <div class="p-8 rounded-2xl glass border border-emerald-200 dark:border-emerald-800">
                    <div class="text-center">
                        <div class="text-4xl font-bold text-emerald-600 dark:text-emerald-400 mb-2">&lt;100ms</div>
                        <div class="text-sm text-slate-600 dark:text-slate-400">API Response Time</div>
                    </div>
                </div>

                <div class="p-8 rounded-2xl glass border border-emerald-200 dark:border-emerald-800">
                    <div class="text-center">
                        <div class="text-4xl font-bold text-emerald-600 dark:text-emerald-400 mb-2">0</div>
                        <div class="text-sm text-slate-600 dark:text-slate-400">Active Incidents</div>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- Service Status -->
    <section class="py-20 bg-slate-50 dark:bg-slate-900/50">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="text-center mb-16">
                <h2 class="text-3xl font-bold mb-4">Service Status</h2>
                <p class="text-slate-600 dark:text-slate-400">Individual component status</p>
            </div>

            <div class="space-y-6 max-w-4xl mx-auto">
                @php
                    $services = [
                        [
                            'name' => 'Real-Time GPS Tracking',
                            'status' => 'operational',
                            'status_color' => 'bg-emerald-500',
                            'description' => 'Live vehicle tracking and location updates'
                        ],
                        [
                            'name' => 'Web Dashboard',
                            'status' => 'operational',
                            'status_color' => 'bg-emerald-500',
                            'description' => 'Primary web application interface'
                        ],
                        [
                            'name' => 'Mobile Apps',
                            'status' => 'operational',
                            'status_color' => 'bg-emerald-500',
                            'description' => 'iOS and Android applications'
                        ],
                        [
                            'name' => 'REST API',
                            'status' => 'operational',
                            'status_color' => 'bg-emerald-500',
                            'description' => 'Developer API and integrations'
                        ],
                        [
                            'name' => 'Data Processing',
                            'status' => 'operational',
                            'status_color' => 'bg-emerald-500',
                            'description' => 'Analytics and report generation'
                        ],
                        [
                            'name' => 'Email Notifications',
                            'status' => 'operational',
                            'status_color' => 'bg-emerald-500',
                            'description' => 'Alert and notification system'
                        ],
                        [
                            'name' => 'Historical Data',
                            'status' => 'operational',
                            'status_color' => 'bg-emerald-500',
                            'description' => 'Trip history and archived data'
                        ],
                    ];
                @endphp

                @foreach($services as $service)
                    <div class="p-6 rounded-2xl glass border border-slate-200 dark:border-slate-800">
                        <div class="flex items-center justify-between">
                            <div class="flex items-center gap-4">
                                <div class="w-3 h-3 rounded-full {{ $service['status_color'] }}"></div>
                                <div>
                                    <h3 class="font-bold text-lg">{{ $service['name'] }}</h3>
                                    <p class="text-slate-600 dark:text-slate-400 text-sm">{{ $service['description'] }}</p>
                                </div>
                            </div>
                            <span class="px-3 py-1 rounded-full bg-emerald-100 dark:bg-emerald-900/30 text-emerald-600 dark:text-emerald-400 text-xs font-medium">
                            {{ ucfirst($service['status']) }}
                        </span>
                        </div>
                    </div>
                @endforeach
            </div>
        </div>
    </section>

    <!-- Incident History -->
    <section class="py-20">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="text-center mb-16">
                <h2 class="text-3xl font-bold mb-4">Incident History</h2>
                <p class="text-slate-600 dark:text-slate-400">Past incidents and maintenance</p>
            </div>

            <div class="space-y-8 max-w-4xl mx-auto">
                @php
                    $incidents = [
                        [
                            'date' => 'Nov 15, 2024',
                            'title' => 'Scheduled Database Maintenance',
                            'status' => 'completed',
                            'description' => 'Database optimization and performance improvements',
                            'duration' => '2 hours',
                            'impact' => 'minor'
                        ],
                        [
                            'date' => 'Oct 28, 2024',
                            'title' => 'API Rate Limiting Update',
                            'status' => 'completed',
                            'description' => 'Updated rate limiting policies for better performance',
                            'duration' => '1 hour',
                            'impact' => 'minor'
                        ],
                        [
                            'date' => 'Sep 10, 2024',
                            'title' => 'GPS Data Processing Upgrade',
                            'status' => 'completed',
                            'description' => 'Upgraded data processing infrastructure',
                            'duration' => '4 hours',
                            'impact' => 'minor'
                        ],
                    ];
                @endphp

                @foreach($incidents as $incident)
                    <div class="p-6 rounded-2xl glass border border-slate-200 dark:border-slate-800">
                        <div class="flex items-start justify-between mb-4">
                            <div>
                                <div class="text-sm text-slate-500 dark:text-slate-400 mb-1">{{ $incident['date'] }}</div>
                                <h3 class="font-bold text-lg mb-2">{{ $incident['title'] }}</h3>
                                <p class="text-slate-600 dark:text-slate-400 text-sm mb-3">{{ $incident['description'] }}</p>
                            </div>
                            <span class="px-3 py-1 rounded-full bg-amber-100 dark:bg-amber-900/30 text-amber-600 dark:text-amber-400 text-xs font-medium">
                            {{ ucfirst($incident['status']) }}
                        </span>
                        </div>

                        <div class="flex items-center gap-6 text-sm">
                            <div class="flex items-center gap-2">
                                <i class="fa-solid fa-clock text-slate-400"></i>
                                <span class="text-slate-600 dark:text-slate-400">Duration: {{ $incident['duration'] }}</span>
                            </div>
                            <div class="flex items-center gap-2">
                                <i class="fa-solid fa-exclamation-triangle text-slate-400"></i>
                                <span class="text-slate-600 dark:text-slate-400">Impact: {{ $incident['impact'] }}</span>
                            </div>
                        </div>
                    </div>
                @endforeach
            </div>
        </div>
    </section>

    <!-- Subscription -->
    <section class="py-20 bg-gradient-to-r from-emerald-600 to-teal-700">
        <div class="max-w-4xl mx-auto px-4 sm:px-6 lg:px-8 text-center">
            <h2 class="text-3xl font-bold text-white mb-4">Stay Updated</h2>
            <p class="text-xl text-emerald-200 mb-8">
                Subscribe to status updates and incident notifications.
            </p>

            <div class="max-w-md mx-auto">
                <form class="flex gap-3">
                    <input type="email"
                           placeholder="Your email address"
                           class="flex-grow px-4 py-3 rounded-xl border border-emerald-300 bg-white focus:outline-none focus:ring-2 focus:ring-emerald-500 focus:border-transparent">
                    <button type="submit"
                            class="px-6 py-3 bg-white text-emerald-700 font-semibold rounded-xl hover:bg-emerald-50 transition-all">
                        Subscribe
                    </button>
                </form>
                <p class="text-emerald-200 text-sm mt-3">
                    Get notified about incidents and maintenance. No spam.
                </p>
            </div>
        </div>
    </section>

    <!-- Last Updated -->
    <div class="py-8 text-center">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="inline-flex items-center gap-2 text-slate-600 dark:text-slate-400">
                <i class="fa-solid fa-clock"></i>
                <span>Last updated: {{ now()->format('F j, Y \a\t g:i A T') }}</span>
            </div>
        </div>
    </div>

@endsection
