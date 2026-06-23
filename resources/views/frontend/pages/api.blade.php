<!-- resources/views/frontend/pages/api.blade.php -->
@extends('frontend.layout')

@section('title', __('frontend.pages.api.title'))
@section('description', 'Complete API documentation for FalconEyeGPS integration and development.')

@section('content')

    <!-- Hero -->
    <section class="relative min-h-[60vh] flex items-center overflow-hidden">
        <div class="absolute inset-0 bg-gradient-to-br from-slate-50 via-indigo-50 to-violet-100 dark:from-slate-950 dark:via-indigo-950 dark:to-violet-950"></div>

        <div class="relative max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-24">
            <div class="grid lg:grid-cols-2 gap-12 items-center">
                <div>
                <span class="inline-block px-4 py-1.5 bg-gradient-to-r from-indigo-500/10 to-violet-500/10 rounded-full text-indigo-600 dark:text-indigo-400 font-semibold text-sm mb-4">
                    DEVELOPER API
                </span>
                    <h1 class="text-5xl lg:text-6xl font-bold mb-6">
                        API <span class="bg-gradient-to-r from-indigo-500 to-violet-600 bg-clip-text text-transparent">Reference</span>
                    </h1>
                    <p class="text-xl text-slate-600 dark:text-slate-300">
                        Complete documentation for FalconEyeGPS REST API. Integrate fleet data into your applications.
                    </p>
                </div>

                <div class="bg-slate-900 rounded-xl p-8">
                    <div class="text-white font-mono text-sm space-y-4">
                        <div class="flex items-center gap-2">
                            <span class="text-emerald-400">GET</span>
                            <span class="text-slate-300">/api/v1/vehicles</span>
                        </div>
                        <div class="flex items-center gap-2">
                            <span class="text-sky-400">POST</span>
                            <span class="text-slate-300">/api/v1/geofences</span>
                        </div>
                        <div class="flex items-center gap-2">
                            <span class="text-amber-400">PUT</span>
                            <span class="text-slate-300">/api/v1/devices/{id}</span>
                        </div>
                        <div class="flex items-center gap-2">
                            <span class="text-red-400">DELETE</span>
                            <span class="text-slate-300">/api/v1/alerts/{id}</span>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- Quick Start -->
    <section class="py-20">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="text-center mb-16">
                <h2 class="text-3xl font-bold mb-4">Quick Start</h2>
                <p class="text-slate-600 dark:text-slate-400">Get started with FalconEyeGPS API in minutes</p>
            </div>

            <div class="space-y-8 max-w-4xl mx-auto">
                <!-- Step 1 -->
                <div class="flex items-start gap-6">
                    <div class="w-12 h-12 rounded-xl bg-gradient-to-br from-indigo-500 to-violet-600 flex items-center justify-center text-white font-bold text-xl flex-shrink-0">
                        1
                    </div>
                    <div class="flex-grow">
                        <h3 class="font-bold text-xl mb-4">Get Your API Key</h3>
                        <p class="text-slate-600 dark:text-slate-400 mb-4">
                            Generate an API key from your FalconEyeGPS dashboard under Settings → API.
                        </p>
                        <div class="bg-slate-900 rounded-xl p-6">
                            <div class="text-white font-mono text-sm">
                                <span class="text-emerald-400">curl</span> <span class="text-slate-300">-X POST https://api.falconeyegps.com/v1/auth/api-key \</span><br>
                                <span class="text-slate-300">  -H "Authorization: Bearer YOUR_TOKEN" \</span><br>
                                <span class="text-slate-300">  -H "Content-Type: application/json"</span>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Step 2 -->
                <div class="flex items-start gap-6">
                    <div class="w-12 h-12 rounded-xl bg-gradient-to-br from-indigo-500 to-violet-600 flex items-center justify-center text-white font-bold text-xl flex-shrink-0">
                        2
                    </div>
                    <div class="flex-grow">
                        <h3 class="font-bold text-xl mb-4">Make Your First Request</h3>
                        <p class="text-slate-600 dark:text-slate-400 mb-4">
                            Retrieve your vehicle data with a simple GET request.
                        </p>
                        <div class="bg-slate-900 rounded-xl p-6">
                            <div class="text-white font-mono text-sm">
                                <span class="text-emerald-400">curl</span> <span class="text-slate-300">-X GET https://api.falconeyegps.com/v1/vehicles \</span><br>
                                <span class="text-slate-300">  -H "X-API-Key: YOUR_API_KEY" \</span><br>
                                <span class="text-slate-300">  -H "Content-Type: application/json"</span>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Step 3 -->
                <div class="flex items-start gap-6">
                    <div class="w-12 h-12 rounded-xl bg-gradient-to-br from-indigo-500 to-violet-600 flex items-center justify-center text-white font-bold text-xl flex-shrink-0">
                        3
                    </div>
                    <div class="flex-grow">
                        <h3 class="font-bold text-xl mb-4">Handle Webhooks</h3>
                        <p class="text-slate-600 dark:text-slate-400 mb-4">
                            Set up webhooks to receive real-time notifications for events.
                        </p>
                        <div class="bg-slate-900 rounded-xl p-6">
                            <div class="text-white font-mono text-sm">
                                <span class="text-emerald-400">curl</span> <span class="text-slate-300">-X POST https://api.falconeyegps.com/v1/webhooks \</span><br>
                                <span class="text-slate-300">  -H "X-API-Key: YOUR_API_KEY" \</span><br>
                                <span class="text-slate-300">  -d '{"url": "https://your-app.com/webhooks", "events": ["vehicle.location.update"]}'</span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- Endpoints -->
    <section class="py-20 bg-slate-50 dark:bg-slate-900/50">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="text-center mb-16">
                <h2 class="text-3xl font-bold mb-4">API Endpoints</h2>
                <p class="text-slate-600 dark:text-slate-400">Complete list of available endpoints</p>
            </div>

            <div class="space-y-8 max-w-4xl mx-auto">
                @php
                    $endpoints = [
                        [
                            'method' => 'GET',
                            'method_color' => 'bg-emerald-500 text-white',
                            'path' => '/v1/vehicles',
                            'desc' => 'Retrieve list of vehicles with optional filters'
                        ],
                        [
                            'method' => 'GET',
                            'method_color' => 'bg-emerald-500 text-white',
                            'path' => '/v1/vehicles/{id}/location',
                            'desc' => 'Get real-time location of specific vehicle'
                        ],
                        [
                            'method' => 'GET',
                            'method_color' => 'bg-emerald-500 text-white',
                            'path' => '/v1/geofences',
                            'desc' => 'List all geofences with their configurations'
                        ],
                        [
                            'method' => 'POST',
                            'method_color' => 'bg-sky-500 text-white',
                            'path' => '/v1/geofences',
                            'desc' => 'Create new geofence with custom boundaries'
                        ],
                        [
                            'method' => 'GET',
                            'method_color' => 'bg-emerald-500 text-white',
                            'path' => '/v1/alerts',
                            'desc' => 'Retrieve alert history with filtering options'
                        ],
                        [
                            'method' => 'GET',
                            'method_color' => 'bg-emerald-500 text-white',
                            'path' => '/v1/reports/trips',
                            'desc' => 'Generate trip reports with date range filters'
                        ],
                    ];
                @endphp

                @foreach($endpoints as $endpoint)
                    <div class="p-6 rounded-2xl glass border border-slate-200 dark:border-slate-800">
                        <div class="flex flex-col md:flex-row md:items-center gap-4">
                        <span class="px-4 py-2 {{ $endpoint['method_color'] }} rounded-xl font-bold text-sm w-20 text-center">
                            {{ $endpoint['method'] }}
                        </span>
                            <code class="flex-grow font-mono text-lg text-slate-900 dark:text-slate-100">
                                {{ $endpoint['path'] }}
                            </code>
                            <div class="text-slate-600 dark:text-slate-400 md:text-right">
                                {{ $endpoint['desc'] }}
                            </div>
                        </div>
                    </div>
                @endforeach
            </div>
        </div>
    </section>

    <!-- SDKs & Libraries -->
    <section class="py-20">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="text-center mb-16">
                <h2 class="text-3xl font-bold mb-4">SDKs & Libraries</h2>
                <p class="text-slate-600 dark:text-slate-400">Official libraries for popular programming languages</p>
            </div>

            <div class="grid md:grid-cols-4 gap-8">
                <div class="p-6 rounded-2xl glass border border-slate-200 dark:border-slate-800 text-center">
                    <div class="w-12 h-12 rounded-xl bg-gradient-to-br from-blue-500 to-indigo-600 flex items-center justify-center mx-auto mb-4">
                        <i class="fab fa-js text-white text-xl"></i>
                    </div>
                    <h3 class="font-bold text-lg mb-2">JavaScript</h3>
                    <p class="text-slate-600 dark:text-slate-400 text-sm mb-4">Node.js & Browser</p>
                    <a href="#" class="text-indigo-600 dark:text-indigo-400 font-medium hover:underline">
                        View Docs →
                    </a>
                </div>

                <div class="p-6 rounded-2xl glass border border-slate-200 dark:border-slate-800 text-center">
                    <div class="w-12 h-12 rounded-xl bg-gradient-to-br from-emerald-500 to-teal-600 flex items-center justify-center mx-auto mb-4">
                        <i class="fab fa-python text-white text-xl"></i>
                    </div>
                    <h3 class="font-bold text-lg mb-2">Python</h3>
                    <p class="text-slate-600 dark:text-slate-400 text-sm mb-4">3.8+ compatible</p>
                    <a href="#" class="text-indigo-600 dark:text-indigo-400 font-medium hover:underline">
                        View Docs →
                    </a>
                </div>

                <div class="p-6 rounded-2xl glass border border-slate-200 dark:border-slate-800 text-center">
                    <div class="w-12 h-12 rounded-xl bg-gradient-to-br from-purple-500 to-pink-600 flex items-center justify-center mx-auto mb-4">
                        <i class="fab fa-java text-white text-xl"></i>
                    </div>
                    <h3 class="font-bold text-lg mb-2">Java</h3>
                    <p class="text-slate-600 dark:text-slate-400 text-sm mb-4">JDK 11+</p>
                    <a href="#" class="text-indigo-600 dark:text-indigo-400 font-medium hover:underline">
                        View Docs →
                    </a>
                </div>

                <div class="p-6 rounded-2xl glass border border-slate-200 dark:border-slate-800 text-center">
                    <div class="w-12 h-12 rounded-xl bg-gradient-to-br from-red-500 to-rose-600 flex items-center justify-center mx-auto mb-4">
                        <i class="fab fa-php text-white text-xl"></i>
                    </div>
                    <h3 class="font-bold text-lg mb-2">PHP</h3>
                    <p class="text-slate-600 dark:text-slate-400 text-sm mb-4">7.4+ compatible</p>
                    <a href="#" class="text-indigo-600 dark:text-indigo-400 font-medium hover:underline">
                        View Docs →
                    </a>
                </div>
            </div>
        </div>
    </section>

    <!-- Support -->
    <section class="py-20 bg-gradient-to-r from-indigo-600 to-violet-700">
        <div class="max-w-4xl mx-auto px-4 sm:px-6 lg:px-8 text-center">
            <h2 class="text-3xl font-bold text-white mb-4">Developer Support</h2>
            <p class="text-xl text-indigo-200 mb-8">
                Need help with API integration? Our developer team is here to assist.
            </p>

            <div class="grid md:grid-cols-2 gap-8 mb-8">
                <div class="p-6 rounded-2xl bg-white/10 backdrop-blur-sm border border-white/20">
                    <h3 class="font-bold text-white mb-2">API Status</h3>
                    <div class="flex items-center gap-2">
                        <div class="w-2 h-2 rounded-full bg-emerald-400 animate-pulse"></div>
                        <div class="text-emerald-200">All Systems Operational</div>
                    </div>
                    <a href="{{ route('status') }}" class="text-white hover:text-indigo-200 mt-3 inline-block">
                        View Status Page →
                    </a>
                </div>

                <div class="p-6 rounded-2xl bg-white/10 backdrop-blur-sm border border-white/20">
                    <h3 class="font-bold text-white mb-2">Rate Limits</h3>
                    <div class="text-indigo-200">1,000 requests/hour</div>
                    <a href="#" class="text-white hover:text-indigo-200 mt-3 inline-block">
                        View Limits →
                    </a>
                </div>
            </div>

            <a href="{{ route('contact') }}?subject=API Support"
               class="inline-flex items-center gap-2 px-8 py-3 bg-white text-indigo-700 font-semibold rounded-xl hover:bg-indigo-50 transition-colors">
                <i class="fa-solid fa-code"></i>
                Contact Developer Support
            </a>
        </div>
    </section>

@endsection
