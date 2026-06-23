@extends('frontend.layout')

@section('content')
    <section class="py-12 md:py-16 lg:py-20 relative overflow-hidden">
        <!-- Animated Background -->
        <div class="absolute inset-0 bg-gradient-to-br from-slate-50 via-white to-indigo-50/30 dark:from-gray-900 dark:via-slate-900 dark:to-indigo-950/20"></div>
        <div class="absolute inset-0 bg-[radial-gradient(ellipse_at_top,_var(--tw-gradient-stops))] from-transparent via-indigo-50/20 to-transparent dark:via-indigo-900/10"></div>

        <!-- Floating Elements -->
        <div class="absolute top-10 left-10 w-72 h-72 bg-purple-300/10 rounded-full mix-blend-multiply filter blur-3xl opacity-20 animate-blob"></div>
        <div class="absolute top-10 right-10 w-72 h-72 bg-blue-300/10 rounded-full mix-blend-multiply filter blur-3xl opacity-20 animate-blob animation-delay-2000"></div>
        <div class="absolute -bottom-8 left-20 w-72 h-72 bg-indigo-300/10 rounded-full mix-blend-multiply filter blur-3xl opacity-20 animate-blob animation-delay-4000"></div>

        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 relative z-10">
            <!-- Header Section -->
            <div class="flex flex-col md:flex-row md:items-center md:justify-between mb-10 lg:mb-14">
                <div>
                    <h1 class="text-4xl md:text-5xl lg:text-6xl font-bold bg-clip-text text-transparent bg-gradient-to-r from-slate-900 via-indigo-700 to-purple-600 dark:from-white dark:via-indigo-300 dark:to-purple-400 mb-3 tracking-tight">
                        {{ __('demo.dashboard.title') }}
                    </h1>
                    <p class="text-lg text-slate-600 dark:text-slate-300 max-w-2xl">
                        {{ __('demo.dashboard.subtitle') }}
                    </p>
                </div>

                <div class="mt-6 md:mt-0">
                    <div class="inline-flex items-center gap-3 px-4 py-2.5 bg-white/80 dark:bg-slate-800/80 backdrop-blur-sm rounded-xl border border-slate-200/50 dark:border-slate-700/50 shadow-lg">
                        <div class="relative">
                            <div class="w-10 h-10 rounded-full bg-gradient-to-r from-emerald-400 to-emerald-500 flex items-center justify-center">
                                <span class="text-white font-bold">TC</span>
                            </div>
                            <div class="absolute -top-1 -right-1 w-5 h-5 bg-emerald-500 rounded-full border-2 border-white dark:border-slate-900 flex items-center justify-center">
                                <div class="w-2 h-2 bg-white rounded-full animate-pulse"></div>
                            </div>
                        </div>
                        <div>
                            <div class="font-semibold text-slate-900 dark:text-white">{{ __('demo.dashboard.vehicle_name') }}</div>
                            <div class="text-sm text-slate-500 dark:text-slate-400">{{ __('demo.dashboard.vehicle_id') }}</div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Main Stats Grid -->
            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6 mb-10">
                <!-- Vehicle Status Card -->
                <div class="group relative">
                    <div class="absolute -inset-0.5 bg-gradient-to-r from-emerald-500 to-emerald-600 rounded-2xl blur opacity-20 group-hover:opacity-40 transition duration-500"></div>
                    <div class="relative bg-white/90 dark:bg-slate-800/90 backdrop-blur-sm rounded-2xl border border-slate-200/50 dark:border-slate-700/50 p-6 shadow-xl hover:shadow-2xl transition-all duration-300">
                        <div class="flex items-center justify-between mb-4">
                            <div class="p-3 rounded-xl bg-emerald-100 dark:bg-emerald-900/30">
                                <svg class="w-6 h-6 text-emerald-600 dark:text-emerald-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                                </svg>
                            </div>
                            <div class="px-3 py-1 bg-emerald-100 dark:bg-emerald-900/30 text-emerald-700 dark:text-emerald-300 rounded-full text-sm font-medium">
                                Live
                            </div>
                        </div>
                        <h3 class="text-sm font-semibold text-slate-500 dark:text-slate-400 uppercase tracking-wider mb-2">Status</h3>
                        <div class="flex items-center gap-3">
                            <div class="relative">
                                <div class="w-3 h-3 bg-emerald-500 rounded-full animate-pulse"></div>
                                <div class="absolute inset-0 bg-emerald-500 rounded-full animate-ping opacity-20"></div>
                            </div>
                            <span class="text-2xl lg:text-3xl font-bold text-slate-900 dark:text-white">{{ __('demo.dashboard.status') }}</span>
                        </div>
                        <p class="mt-4 text-sm text-slate-600 dark:text-slate-300">{{ __('demo.dashboard.status_desc') }}</p>
                    </div>
                </div>

                <!-- Speed Card -->
                <div class="group relative">
                    <div class="absolute -inset-0.5 bg-gradient-to-r from-blue-500 to-indigo-600 rounded-2xl blur opacity-20 group-hover:opacity-40 transition duration-500"></div>
                    <div class="relative bg-white/90 dark:bg-slate-800/90 backdrop-blur-sm rounded-2xl border border-slate-200/50 dark:border-slate-700/50 p-6 shadow-xl hover:shadow-2xl transition-all duration-300">
                        <div class="flex items-center justify-between mb-4">
                            <div class="p-3 rounded-xl bg-blue-100 dark:bg-blue-900/30">
                                <svg class="w-6 h-6 text-blue-600 dark:text-blue-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z"></path>
                                </svg>
                            </div>
                            <div class="text-sm text-slate-500 dark:text-slate-400">
                                Last updated: 2 min ago
                            </div>
                        </div>
                        <h3 class="text-sm font-semibold text-slate-500 dark:text-slate-400 uppercase tracking-wider mb-2">Current Speed</h3>
                        <div class="flex items-end gap-2">
                            <span class="text-3xl lg:text-4xl font-bold bg-clip-text text-transparent bg-gradient-to-r from-blue-600 to-indigo-600 dark:from-blue-400 dark:to-indigo-400">55</span>
                            <span class="text-lg font-medium text-slate-600 dark:text-slate-300 mb-1.5">km/h</span>
                        </div>
                        <div class="mt-4 flex items-center gap-2">
                            <div class="flex-1 h-2 bg-slate-200 dark:bg-slate-700 rounded-full overflow-hidden">
                                <div class="h-full bg-gradient-to-r from-blue-500 to-indigo-500 rounded-full" style="width: 45%"></div>
                            </div>
                            <span class="text-sm text-slate-500 dark:text-slate-400">Optimal</span>
                        </div>
                    </div>
                </div>

                <!-- Fuel Level Card -->
                <div class="group relative">
                    <div class="absolute -inset-0.5 bg-gradient-to-r from-amber-500 to-orange-600 rounded-2xl blur opacity-20 group-hover:opacity-40 transition duration-500"></div>
                    <div class="relative bg-white/90 dark:bg-slate-800/90 backdrop-blur-sm rounded-2xl border border-slate-200/50 dark:border-slate-700/50 p-6 shadow-xl hover:shadow-2xl transition-all duration-300">
                        <div class="flex items-center justify-between mb-4">
                            <div class="p-3 rounded-xl bg-amber-100 dark:bg-amber-900/30">
                                <svg class="w-6 h-6 text-amber-600 dark:text-amber-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 15a4 4 0 004 4h9a5 5 0 10-.1-9.999 5.002 5.002 0 10-9.78 2.096A4 4 0 003 15z"></path>
                                </svg>
                            </div>
                            <span class="text-sm font-medium text-amber-600 dark:text-amber-400">15L remaining</span>
                        </div>
                        <h3 class="text-sm font-semibold text-slate-500 dark:text-slate-400 uppercase tracking-wider mb-2">Fuel Level</h3>
                        <div class="flex items-end gap-2">
                            <span class="text-3xl lg:text-4xl font-bold text-slate-900 dark:text-white">65%</span>
                            <span class="text-lg font-medium text-slate-600 dark:text-slate-300 mb-1.5">Full</span>
                        </div>
                        <div class="mt-4">
                            <div class="flex justify-between text-sm text-slate-500 dark:text-slate-400 mb-1">
                                <span>0%</span>
                                <span>100%</span>
                            </div>
                            <div class="h-3 bg-slate-200 dark:bg-slate-700 rounded-full overflow-hidden">
                                <div class="h-full bg-gradient-to-r from-amber-400 to-orange-500 rounded-full" style="width: 65%"></div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Distance Traveled Card -->
                <div class="group relative">
                    <div class="absolute -inset-0.5 bg-gradient-to-r from-purple-500 to-pink-600 rounded-2xl blur opacity-20 group-hover:opacity-40 transition duration-500"></div>
                    <div class="relative bg-white/90 dark:bg-slate-800/90 backdrop-blur-sm rounded-2xl border border-slate-200/50 dark:border-slate-700/50 p-6 shadow-xl hover:shadow-2xl transition-all duration-300">
                        <div class="flex items-center justify-between mb-4">
                            <div class="p-3 rounded-xl bg-purple-100 dark:bg-purple-900/30">
                                <svg class="w-6 h-6 text-purple-600 dark:text-purple-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19l-7-7 7-7m11 14l-7-7 7-7"></path>
                                </svg>
                            </div>
                            <span class="text-sm text-slate-500 dark:text-slate-400">Today</span>
                        </div>
                        <h3 class="text-sm font-semibold text-slate-500 dark:text-slate-400 uppercase tracking-wider mb-2">Distance Traveled</h3>
                        <div class="flex items-end gap-2">
                            <span class="text-3xl lg:text-4xl font-bold text-slate-900 dark:text-white">142</span>
                            <span class="text-lg font-medium text-slate-600 dark:text-slate-300 mb-1.5">km</span>
                        </div>
                        <p class="mt-4 text-sm text-slate-600 dark:text-slate-300">+18% compared to yesterday</p>
                    </div>
                </div>
            </div>

            <!-- Additional Metrics Section -->
            <div class="grid grid-cols-1 lg:grid-cols-3 gap-6 mb-10">
                <!-- Engine Health -->
                <div class="lg:col-span-2">
                    <div class="bg-white/90 dark:bg-slate-800/90 backdrop-blur-sm rounded-2xl border border-slate-200/50 dark:border-slate-700/50 p-6 shadow-xl">
                        <div class="flex items-center justify-between mb-6">
                            <div>
                                <h3 class="text-lg font-bold text-slate-900 dark:text-white">Engine Health & Performance</h3>
                                <p class="text-sm text-slate-600 dark:text-slate-300">Real-time engine metrics and diagnostics</p>
                            </div>
                            <div class="px-4 py-2 bg-green-100 dark:bg-green-900/30 text-green-700 dark:text-green-300 rounded-full text-sm font-medium">
                                Excellent
                            </div>
                        </div>

                        <div class="grid grid-cols-2 md:grid-cols-4 gap-4">
                            <div class="text-center p-4 rounded-xl bg-slate-50 dark:bg-slate-800/50">
                                <div class="text-2xl font-bold text-slate-900 dark:text-white mb-1">92°C</div>
                                <div class="text-sm text-slate-600 dark:text-slate-300">Temp</div>
                                <div class="mt-2 text-xs text-emerald-600 dark:text-emerald-400">Normal</div>
                            </div>
                            <div class="text-center p-4 rounded-xl bg-slate-50 dark:bg-slate-800/50">
                                <div class="text-2xl font-bold text-slate-900 dark:text-white mb-1">2.1k</div>
                                <div class="text-sm text-slate-600 dark:text-slate-300">RPM</div>
                                <div class="mt-2 text-xs text-emerald-600 dark:text-emerald-400">Optimal</div>
                            </div>
                            <div class="text-center p-4 rounded-xl bg-slate-50 dark:bg-slate-800/50">
                                <div class="text-2xl font-bold text-slate-900 dark:text-white mb-1">98%</div>
                                <div class="text-sm text-slate-600 dark:text-slate-300">Efficiency</div>
                                <div class="mt-2 text-xs text-emerald-600 dark:text-emerald-400">High</div>
                            </div>
                            <div class="text-center p-4 rounded-xl bg-slate-50 dark:bg-slate-800/50">
                                <div class="text-2xl font-bold text-slate-900 dark:text-white mb-1">0</div>
                                <div class="text-sm text-slate-600 dark:text-slate-300">Faults</div>
                                <div class="mt-2 text-xs text-emerald-600 dark:text-emerald-400">Clear</div>
                            </div>
                        </div>

                        <div class="mt-6 pt-6 border-t border-slate-200 dark:border-slate-700">
                            <div class="flex items-center justify-between text-sm">
                                <span class="text-slate-600 dark:text-slate-300">Last service: 15 days ago</span>
                                <span class="font-medium text-blue-600 dark:text-blue-400">Next due: 45 days</span>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Vehicle Information -->
                <div>
                    <div class="bg-white/90 dark:bg-slate-800/90 backdrop-blur-sm rounded-2xl border border-slate-200/50 dark:border-slate-700/50 p-6 shadow-xl h-full">
                        <h3 class="text-lg font-bold text-slate-900 dark:text-white mb-6">Vehicle Details</h3>

                        <div class="space-y-4">
                            <div class="flex items-center justify-between">
                                <span class="text-slate-600 dark:text-slate-300">Model</span>
                                <span class="font-medium text-slate-900 dark:text-white">{{ __('demo.dashboard.vehicle_name') }}</span>
                            </div>
                            <div class="flex items-center justify-between">
                                <span class="text-slate-600 dark:text-slate-300">License Plate</span>
                                <span class="font-medium text-slate-900 dark:text-white">ABC-1234</span>
                            </div>
                            <div class="flex items-center justify-between">
                                <span class="text-slate-600 dark:text-slate-300">VIN</span>
                                <span class="font-medium text-slate-900 dark:text-white">1HGCM82633A123456</span>
                            </div>
                            <div class="flex items-center justify-between">
                                <span class="text-slate-600 dark:text-slate-300">Driver</span>
                                <span class="font-medium text-slate-900 dark:text-white">John Doe</span>
                            </div>
                            <div class="flex items-center justify-between">
                                <span class="text-slate-600 dark:text-slate-300">Last Location</span>
                                <span class="font-medium text-slate-900 dark:text-white">Downtown</span>
                            </div>
                        </div>

                        <div class="mt-8 pt-6 border-t border-slate-200 dark:border-slate-700">
                            <div class="flex items-center gap-3">
                                <div class="w-10 h-10 rounded-full bg-gradient-to-r from-blue-500 to-indigo-500 flex items-center justify-center">
                                    <svg class="w-5 h-5 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z"></path>
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 11a3 3 0 11-6 0 3 3 0 016 0z"></path>
                                    </svg>
                                </div>
                                <div>
                                    <div class="font-medium text-slate-900 dark:text-white">Live Tracking</div>
                                    <div class="text-sm text-slate-600 dark:text-slate-300">View real-time location</div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Call to Action -->
            <div class="group relative">
                <div class="absolute -inset-0.5 bg-gradient-to-r from-sky-500 via-blue-600 to-indigo-600 rounded-3xl blur opacity-30 group-hover:opacity-50 transition duration-500"></div>
                <div class="relative bg-gradient-to-r from-sky-600 via-blue-700 to-indigo-700 rounded-3xl p-8 md:p-10 overflow-hidden">
                    <div class="absolute right-0 top-0 w-64 h-64 bg-white/10 rounded-full -translate-y-32 translate-x-32"></div>
                    <div class="absolute left-0 bottom-0 w-96 h-96 bg-white/5 rounded-full -translate-x-48 translate-y-48"></div>

                    <div class="relative z-10 flex flex-col md:flex-row md:items-center md:justify-between gap-6">
                        <div>
                            <h3 class="text-2xl font-bold text-white mb-3">Track Your Vehicle Live</h3>
                            <p class="text-sky-100 max-w-2xl">
                                Access real-time GPS tracking, detailed route history, and advanced analytics for optimal fleet management.
                            </p>
                        </div>

                        <a href="{{ url('/demo/tracking') }}"
                           class="group/btn inline-flex items-center justify-center gap-3 px-8 py-4 bg-white text-sky-700 font-bold rounded-xl hover:bg-slate-50 hover:scale-[1.02] active:scale-95 transition-all duration-300 shadow-2xl shadow-sky-900/30">
                            <span>Open Live Tracking</span>
                            <svg class="w-5 h-5 group-hover/btn:translate-x-1 transition-transform" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 7l5 5m0 0l-5 5m5-5H6"></path>
                            </svg>
                        </a>
                    </div>
                </div>
            </div>

            <!-- Quick Actions -->
            <div class="mt-10">
                <h3 class="text-xl font-bold text-slate-900 dark:text-white mb-6">Quick Actions</h3>
                <div class="grid grid-cols-2 md:grid-cols-4 gap-4">
                    <button class="p-4 bg-white/90 dark:bg-slate-800/90 backdrop-blur-sm rounded-xl border border-slate-200/50 dark:border-slate-700/50 hover:bg-slate-50 dark:hover:bg-slate-800 transition-colors text-left group">
                        <div class="w-12 h-12 rounded-lg bg-emerald-100 dark:bg-emerald-900/30 flex items-center justify-center mb-3 group-hover:scale-105 transition-transform">
                            <svg class="w-6 h-6 text-emerald-600 dark:text-emerald-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                            </svg>
                        </div>
                        <div class="font-medium text-slate-900 dark:text-white">Start Trip</div>
                        <div class="text-sm text-slate-500 dark:text-slate-400 mt-1">Begin new journey</div>
                    </button>

                    <button class="p-4 bg-white/90 dark:bg-slate-800/90 backdrop-blur-sm rounded-xl border border-slate-200/50 dark:border-slate-700/50 hover:bg-slate-50 dark:hover:bg-slate-800 transition-colors text-left group">
                        <div class="w-12 h-12 rounded-lg bg-blue-100 dark:bg-blue-900/30 flex items-center justify-center mb-3 group-hover:scale-105 transition-transform">
                            <svg class="w-6 h-6 text-blue-600 dark:text-blue-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z"></path>
                            </svg>
                        </div>
                        <div class="font-medium text-slate-900 dark:text-white">Reports</div>
                        <div class="text-sm text-slate-500 dark:text-slate-400 mt-1">View analytics</div>
                    </button>

                    <button class="p-4 bg-white/90 dark:bg-slate-800/90 backdrop-blur-sm rounded-xl border border-slate-200/50 dark:border-slate-700/50 hover:bg-slate-50 dark:hover:bg-slate-800 transition-colors text-left group">
                        <div class="w-12 h-12 rounded-lg bg-amber-100 dark:bg-amber-900/30 flex items-center justify-center mb-3 group-hover:scale-105 transition-transform">
                            <svg class="w-6 h-6 text-amber-600 dark:text-amber-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                            </svg>
                        </div>
                        <div class="font-medium text-slate-900 dark:text-white">Schedule</div>
                        <div class="text-sm text-slate-500 dark:text-slate-400 mt-1">Maintenance</div>
                    </button>

                    <button class="p-4 bg-white/90 dark:bg-slate-800/90 backdrop-blur-sm rounded-xl border border-slate-200/50 dark:border-slate-700/50 hover:bg-slate-50 dark:hover:bg-slate-800 transition-colors text-left group">
                        <div class="w-12 h-12 rounded-lg bg-purple-100 dark:bg-purple-900/30 flex items-center justify-center mb-3 group-hover:scale-105 transition-transform">
                            <svg class="w-6 h-6 text-purple-600 dark:text-purple-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z"></path>
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"></path>
                            </svg>
                        </div>
                        <div class="font-medium text-slate-900 dark:text-white">Settings</div>
                        <div class="text-sm text-slate-500 dark:text-slate-400 mt-1">Configure vehicle</div>
                    </button>
                </div>
            </div>
        </div>
    </section>

    <style>
        @keyframes blob {
            0%, 100% {
                transform: translate(0px, 0px) scale(1);
            }
            33% {
                transform: translate(30px, -50px) scale(1.1);
            }
            66% {
                transform: translate(-20px, 20px) scale(0.9);
            }
        }

        .animate-blob {
            animation: blob 7s infinite;
        }

        .animation-delay-2000 {
            animation-delay: 2s;
        }

        .animation-delay-4000 {
            animation-delay: 4s;
        }

        /* Smooth transitions */
        * {
            transition: background-color 0.3s ease, border-color 0.3s ease;
        }

        /* Custom scrollbar */
        ::-webkit-scrollbar {
            width: 8px;
            height: 8px;
        }

        ::-webkit-scrollbar-track {
            background: #f1f5f9;
        }

        .dark ::-webkit-scrollbar-track {
            background: #1e293b;
        }

        ::-webkit-scrollbar-thumb {
            background: #cbd5e1;
            border-radius: 4px;
        }

        .dark ::-webkit-scrollbar-thumb {
            background: #475569;
        }

        ::-webkit-scrollbar-thumb:hover {
            background: #94a3b8;
        }

        .dark ::-webkit-scrollbar-thumb:hover {
            background: #64748b;
        }
    </style>
@endsection
