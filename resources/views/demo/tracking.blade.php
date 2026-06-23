<!DOCTYPE html>
<html lang="en" class="dark">
<head>
    <meta charset="UTF-8">
    <title>Live Tracking Dashboard – FalconEyeGPS Premium</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">

    <!-- Tailwind -->
    <script src="https://cdn.tailwindcss.com"></script>
    <script>
        tailwind.config = {
            darkMode: 'class',
            theme: {
                extend: {
                    animation: {
                        'pulse-glow': 'pulse-glow 2s infinite',
                        'float': 'float 6s ease-in-out infinite',
                        'shimmer': 'shimmer 2s infinite linear',
                        'bounce-slow': 'bounce 3s infinite',
                    },
                    keyframes: {
                        'pulse-glow': {
                            '0%, 100%': {
                                'box-shadow': '0 0 20px rgba(14, 165, 233, 0.5)',
                                'transform': 'scale(1)'
                            },
                            '50%': {
                                'box-shadow': '0 0 40px rgba(14, 165, 233, 0.8)',
                                'transform': 'scale(1.05)'
                            },
                        },
                        'float': {
                            '0%, 100%': { transform: 'translateY(0px)' },
                            '50%': { transform: 'translateY(-10px)' },
                        },
                        'shimmer': {
                            '0%': { 'background-position': '-1000px 0' },
                            '100%': { 'background-position': '1000px 0' },
                        }
                    },
                    backdropBlur: {
                        'xs': '2px',
                    }
                }
            }
        }
    </script>

    <style>
        * {
            transition: background-color 0.3s ease, border-color 0.3s ease;
        }

        html, body {
            height: 100%;
            margin: 0;
            font-family: 'Inter', system-ui, -apple-system, sans-serif;
        }

        #map {
            width: 100%;
            height: 100%;
            font-family: inherit;
        }

        /* Premium Pulse Animation */
        .premium-pulse {
            width: 48px;
            height: 48px;
            background: linear-gradient(135deg, #0ea5e9 0%, #3b82f6 100%);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-size: 20px;
            position: relative;
            z-index: 10;
            animation: premium-pulse 2s infinite;
            box-shadow: 0 10px 25px rgba(14, 165, 233, 0.4);
        }

        @keyframes premium-pulse {
            0% {
                box-shadow: 0 0 0 0 rgba(14, 165, 233, 0.7),
                0 0 0 0 rgba(59, 130, 246, 0.5);
            }
            70% {
                box-shadow: 0 0 0 20px rgba(14, 165, 233, 0),
                0 0 0 40px rgba(59, 130, 246, 0);
            }
            100% {
                box-shadow: 0 0 0 0 rgba(14, 165, 233, 0),
                0 0 0 0 rgba(59, 130, 246, 0);
            }
        }

        /* Glass Morphism */
        .glass-card {
            background: rgba(255, 255, 255, 0.1);
            backdrop-filter: blur(10px);
            -webkit-backdrop-filter: blur(10px);
            border: 1px solid rgba(255, 255, 255, 0.2);
        }

        .dark .glass-card {
            background: rgba(30, 41, 59, 0.7);
            border: 1px solid rgba(71, 85, 105, 0.3);
        }

        /* Custom Scrollbar */
        .custom-scrollbar::-webkit-scrollbar {
            width: 6px;
        }

        .custom-scrollbar::-webkit-scrollbar-track {
            background: rgba(255, 255, 255, 0.1);
            border-radius: 10px;
        }

        .custom-scrollbar::-webkit-scrollbar-thumb {
            background: rgba(14, 165, 233, 0.5);
            border-radius: 10px;
        }

        .custom-scrollbar::-webkit-scrollbar-thumb:hover {
            background: rgba(14, 165, 233, 0.7);
        }

        /* Gradient Border */
        .gradient-border {
            position: relative;
            border: double 2px transparent;
            border-radius: 12px;
            background-origin: border-box;
            background-clip: padding-box, border-box;
        }

        .gradient-border-blue {
            background-image: linear-gradient(white, white),
            linear-gradient(135deg, #0ea5e9, #3b82f6);
        }

        .dark .gradient-border-blue {
            background-image: linear-gradient(#1e293b, #1e293b),
            linear-gradient(135deg, #0ea5e9, #3b82f6);
        }

        /* Route Animation */
        .moving-dot {
            position: absolute;
            width: 12px;
            height: 12px;
            background: #0ea5e9;
            border-radius: 50%;
            box-shadow: 0 0 15px #0ea5e9;
            z-index: 1000;
            pointer-events: none;
            animation: move-dot 30s linear infinite;
        }

        @keyframes move-dot {
            0% { left: 0%; }
            100% { left: 100%; }
        }

    </style>
</head>

<body class="bg-gradient-to-br from-slate-50 to-blue-50 dark:from-gray-900 dark:to-slate-900 overflow-hidden">
<!-- Theme Toggle -->
<button id="themeToggle" class="fixed top-4 right-4 z-[1000] w-12 h-12 rounded-full glass-card flex items-center justify-center text-slate-700 dark:text-slate-300 hover:scale-105 transition-all duration-300 shadow-lg">
    <i id="themeIcon" class="fas fa-moon text-lg"></i>
</button>

<!-- Main Layout -->
<div class="flex flex-col h-screen">
    <!-- Premium Header -->
    <header class="relative z-50">
        <div class="absolute inset-0 bg-gradient-to-r from-sky-600/10 via-blue-600/10 to-indigo-600/10 dark:from-sky-900/20 dark:via-blue-900/20 dark:to-indigo-900/20"></div>
        <div class="relative max-w-7xl mx-auto px-4 py-4">
            <div class="flex items-center justify-between">
                <!-- Logo & Title -->
                <div class="flex items-center gap-4">
                    <div class="relative group">
                        <div class="absolute -inset-1 bg-gradient-to-r from-sky-500 to-blue-500 rounded-2xl blur opacity-30 group-hover:opacity-50 transition duration-500"></div>
                        <div class="relative bg-gradient-to-r from-sky-600 to-blue-600 rounded-xl p-3">
                            <i class="fas fa-satellite text-white text-xl"></i>
                        </div>
                    </div>
                    <div>
                        <h1 class="text-2xl font-bold bg-clip-text text-transparent bg-gradient-to-r from-sky-600 to-blue-600 dark:from-sky-400 dark:to-blue-400">
                            Live Tracking Dashboard
                        </h1>
                        <p class="text-sm text-slate-600 dark:text-slate-300 flex items-center gap-2">
                                <span class="flex items-center gap-1">
                                    <span class="w-2 h-2 bg-emerald-500 rounded-full animate-pulse"></span>
                                    Real-time monitoring
                                </span>
                            <span class="text-slate-400">•</span>
                            <span>{{ __('demo.tracking.vehicle_name') }}</span>
                        </p>
                    </div>
                </div>

                <!-- Controls -->
                <div class="flex items-center gap-3">
                    <!-- Demo Badge -->
                    <div class="relative group">
                        <div class="absolute -inset-1 bg-gradient-to-r from-amber-400 to-orange-500 rounded-full blur opacity-30 group-hover:opacity-50 transition duration-500"></div>
                        <div class="relative px-4 py-2 bg-gradient-to-r from-amber-400 to-orange-400 rounded-full">
                                <span class="text-sm font-bold text-slate-900 flex items-center gap-2">
                                    <i class="fas fa-flask"></i>
                                    DEMO MODE
                                </span>
                        </div>
                    </div>

                    <!-- Back Button -->
                    <a href="{{url('/')}}"
                       class="group relative px-6 py-3 bg-gradient-to-r from-sky-600 to-blue-600 text-white font-semibold rounded-xl hover:shadow-2xl hover:shadow-sky-500/30 transition-all duration-300 hover:-translate-y-0.5 active:translate-y-0 flex items-center gap-3">
                        <i class="fas fa-arrow-left group-hover:-translate-x-1 transition-transform"></i>
                        Back to Dashboard
                    </a>
                </div>
            </div>
        </div>
    </header>

    <!-- Main Content -->
    <div class="flex flex-1 overflow-hidden">
        <!-- Sidebar - Vehicle Stats -->
        <aside class="w-80 lg:w-96 border-r border-slate-200/50 dark:border-slate-700/50 bg-white/50 dark:bg-slate-900/50 backdrop-blur-sm overflow-y-auto custom-scrollbar p-6 hidden md:block">
            <!-- Current Vehicle -->
            <div class="mb-8">
                <div class="flex items-center gap-4 mb-6">
                    <div class="relative">
                        <div class="w-16 h-16 rounded-2xl bg-gradient-to-br from-sky-500 to-blue-600 flex items-center justify-center text-white text-2xl">
                            <i class="fas fa-car"></i>
                        </div>
                        <div class="absolute -top-2 -right-2 w-8 h-8 bg-emerald-500 rounded-full border-4 border-white dark:border-slate-900 flex items-center justify-center">
                            <div class="w-2 h-2 bg-white rounded-full animate-pulse"></div>
                        </div>
                    </div>
                    <div>
                        <h3 class="text-xl font-bold text-slate-900 dark:text-white">{{ __('demo.tracking.vehicle_name') }}</h3>
                        <p class="text-slate-600 dark:text-slate-300">License: ABC-1234</p>
                    </div>
                </div>

                <!-- Status -->
                <div class="mb-6 p-4 rounded-xl bg-gradient-to-r from-emerald-50 to-emerald-100 dark:from-emerald-900/20 dark:to-emerald-800/20 border border-emerald-200 dark:border-emerald-700/30">
                    <div class="flex items-center justify-between">
                        <div class="flex items-center gap-3">
                            <div class="relative">
                                <div class="w-4 h-4 bg-emerald-500 rounded-full animate-pulse"></div>
                                <div class="absolute inset-0 bg-emerald-500 rounded-full animate-ping opacity-20"></div>
                            </div>
                            <span class="font-semibold text-emerald-700 dark:text-emerald-300">Active & Running</span>
                        </div>
                        <span class="text-sm text-emerald-600 dark:text-emerald-400">Live</span>
                    </div>
                </div>
            </div>

            <!-- Stats Grid -->
            <div class="grid grid-cols-2 gap-4 mb-8">
                <div class="p-4 rounded-xl glass-card">
                    <div class="text-sm text-slate-500 dark:text-slate-400 mb-1">Speed</div>
                    <div class="text-2xl font-bold text-slate-900 dark:text-white">55 <span class="text-lg text-slate-600 dark:text-slate-300">km/h</span></div>
                    <div class="mt-2 h-2 bg-slate-200 dark:bg-slate-700 rounded-full overflow-hidden">
                        <div class="h-full bg-gradient-to-r from-blue-500 to-indigo-500 rounded-full" style="width: 45%"></div>
                    </div>
                </div>

                <div class="p-4 rounded-xl glass-card">
                    <div class="text-sm text-slate-500 dark:text-slate-400 mb-1">Fuel</div>
                    <div class="text-2xl font-bold text-slate-900 dark:text-white">65%</div>
                    <div class="mt-2 h-2 bg-slate-200 dark:bg-slate-700 rounded-full overflow-hidden">
                        <div class="h-full bg-gradient-to-r from-amber-400 to-orange-500 rounded-full" style="width: 65%"></div>
                    </div>
                </div>

                <div class="p-4 rounded-xl glass-card">
                    <div class="text-sm text-slate-500 dark:text-slate-400 mb-1">Distance</div>
                    <div class="text-2xl font-bold text-slate-900 dark:text-white">142 <span class="text-lg text-slate-600 dark:text-slate-300">km</span></div>
                    <div class="text-xs text-slate-500 dark:text-slate-400 mt-1">Today</div>
                </div>

                <div class="p-4 rounded-xl glass-card">
                    <div class="text-sm text-slate-500 dark:text-slate-400 mb-1">Temp</div>
                    <div class="text-2xl font-bold text-slate-900 dark:text-white">92°C</div>
                    <div class="text-xs text-emerald-600 dark:text-emerald-400 mt-1">Normal</div>
                </div>
            </div>

            <!-- Route Information -->
            <div class="mb-8">
                <h3 class="text-lg font-semibold text-slate-900 dark:text-white mb-4 flex items-center gap-2">
                    <i class="fas fa-route text-blue-500"></i>
                    Current Route
                </h3>
                <div class="space-y-3">
                    <div class="flex items-center gap-3 p-3 rounded-lg bg-blue-50 dark:bg-blue-900/20">
                        <div class="w-8 h-8 rounded-full bg-blue-100 dark:bg-blue-800 flex items-center justify-center">
                            <i class="fas fa-play text-blue-600 dark:text-blue-300"></i>
                        </div>
                        <div>
                            <div class="font-medium text-slate-900 dark:text-white">Downtown Karachi</div>
                            <div class="text-sm text-slate-600 dark:text-slate-300">10:24 AM</div>
                        </div>
                    </div>

                    <div class="flex items-center gap-3 p-3 rounded-lg bg-emerald-50 dark:bg-emerald-900/20">
                        <div class="w-8 h-8 rounded-full bg-emerald-100 dark:bg-emerald-800 flex items-center justify-center">
                            <i class="fas fa-flag-checkered text-emerald-600 dark:text-emerald-300"></i>
                        </div>
                        <div>
                            <div class="font-medium text-slate-900 dark:text-white">Clifton Area</div>
                            <div class="text-sm text-slate-600 dark:text-slate-300">Estimated: 11:45 AM</div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Controls -->
            <div class="space-y-3">
                <button class="w-full p-3 rounded-xl bg-gradient-to-r from-sky-500 to-blue-600 text-white font-medium hover:shadow-lg hover:shadow-sky-500/30 transition-all duration-300 flex items-center justify-center gap-3">
                    <i class="fas fa-map-marker-alt"></i>
                    Set Destination
                </button>
                <button class="w-full p-3 rounded-xl glass-card hover:bg-slate-100 dark:hover:bg-slate-800 transition-colors flex items-center justify-center gap-3 text-slate-700 dark:text-slate-300">
                    <i class="fas fa-history"></i>
                    View History
                </button>
            </div>
        </aside>

        <!-- Main Map Area -->
        <main class="flex-1 relative overflow-hidden">
            <!-- Map Container -->
            <div id="map" class="w-full h-full"></div>

            <!-- Floating Controls -->
            <div class="absolute top-6 left-6 space-y-3">
                <div class="bg-white/90 dark:bg-slate-800/90 backdrop-blur-sm rounded-xl shadow-xl p-4 w-72">
                    <div class="flex items-center justify-between mb-3">
                        <h3 class="font-semibold text-slate-900 dark:text-white">Live Updates</h3>
                        <span class="text-xs px-2 py-1 bg-emerald-100 dark:bg-emerald-900/30 text-emerald-700 dark:text-emerald-300 rounded-full">Active</span>
                    </div>
                    <div class="space-y-2">
                        <div class="flex items-center justify-between">
                            <span class="text-sm text-slate-600 dark:text-slate-300">Last Signal</span>
                            <span class="text-sm font-medium text-slate-900 dark:text-white" id="lastUpdate">Just now</span>
                        </div>
                        <div class="flex items-center justify-between">
                            <span class="text-sm text-slate-600 dark:text-slate-300">Signal Strength</span>
                            <span class="flex items-center gap-1">
                                    <i class="fas fa-signal text-emerald-500"></i>
                                    <span class="text-sm font-medium text-slate-900 dark:text-white">Excellent</span>
                                </span>
                        </div>
                    </div>
                </div>

                <!-- Speed Gauge -->
                <div class="bg-white/90 dark:bg-slate-800/90 backdrop-blur-sm rounded-xl shadow-xl p-4 w-72">
                    <div class="flex items-center justify-between mb-3">
                        <h3 class="font-semibold text-slate-900 dark:text-white">Speed Monitor</h3>
                        <div class="relative" id="speedIndicator">
                            <div class="w-3 h-3 bg-emerald-500 rounded-full animate-pulse"></div>
                        </div>
                    </div>
                    <div class="text-center mb-4">
                        <div class="text-4xl font-bold text-slate-900 dark:text-white" id="currentSpeed">55</div>
                        <div class="text-sm text-slate-600 dark:text-slate-300">kilometers per hour</div>
                    </div>
                    <div class="h-2 bg-slate-200 dark:bg-slate-700 rounded-full overflow-hidden">
                        <div class="h-full bg-gradient-to-r from-green-400 via-yellow-400 to-red-400 rounded-full" style="width: 45%" id="speedBar"></div>
                    </div>
                </div>
            </div>

            <!-- Bottom Controls -->
            <div class="absolute bottom-6 left-6 right-6 flex items-center justify-between">
                <!-- Timeline -->
                <div class="bg-white/90 dark:bg-slate-800/90 backdrop-blur-sm rounded-xl shadow-xl px-6 py-3">
                    <div class="flex items-center gap-4">
                        <button class="p-2 rounded-lg hover:bg-slate-100 dark:hover:bg-slate-700 transition-colors">
                            <i class="fas fa-step-backward text-slate-600 dark:text-slate-300"></i>
                        </button>
                        <div class="text-sm text-slate-600 dark:text-slate-300">10:24 AM</div>
                        <div class="w-48 h-2 bg-slate-200 dark:bg-slate-700 rounded-full overflow-hidden">
                            <div class="h-full bg-gradient-to-r from-blue-500 to-indigo-500 rounded-full w-1/3" id="timelineProgress"></div>
                        </div>
                        <div class="text-sm text-slate-600 dark:text-slate-300">11:45 AM</div>
                        <button class="p-2 rounded-lg hover:bg-slate-100 dark:hover:bg-slate-700 transition-colors">
                            <i class="fas fa-step-forward text-slate-600 dark:text-slate-300"></i>
                        </button>
                    </div>
                </div>

                <!-- Map Controls -->
                <div class="flex items-center gap-3">
                    <button class="p-3 rounded-xl glass-card hover:bg-slate-100 dark:hover:bg-slate-800 transition-colors" onclick="toggleDemo()">
                        <i class="fas fa-pause text-slate-600 dark:text-slate-300"></i>
                    </button>
                    <button class="p-3 rounded-xl glass-card hover:bg-slate-100 dark:hover:bg-slate-800 transition-colors" onclick="resetMap()">
                        <i class="fas fa-sync-alt text-slate-600 dark:text-slate-300"></i>
                    </button>
                    <button class="p-3 rounded-xl bg-gradient-to-r from-sky-500 to-blue-600 text-white hover:shadow-lg hover:shadow-sky-500/30 transition-all duration-300" onclick="shareLocation()">
                        <i class="fas fa-share-alt"></i>
                    </button>
                </div>
            </div>
        </main>
    </div>
</div>

<script>
    const GOOGLE_MAPS_KEY = @json(config('services.google.maps_key', env('GOOGLE_MAPS_API_KEY')));

    let map;
    let marker;
    let polyline;
    let routeLine;
    let trailPath = [];
    let poiMarkers = [];
    let points = [];
    let currentIndex = 0;
    let demoInterval;
    let isPlaying = true;
    let speedValues = [55, 60, 50, 65, 45, 70, 55, 60, 58, 62];

    const darkMapStyles = [
        { elementType: 'geometry', stylers: [{ color: '#1e293b' }] },
        { elementType: 'labels.text.stroke', stylers: [{ color: '#1e293b' }] },
        { elementType: 'labels.text.fill', stylers: [{ color: '#94a3b8' }] },
        { featureType: 'road', elementType: 'geometry', stylers: [{ color: '#334155' }] },
        { featureType: 'water', elementType: 'geometry', stylers: [{ color: '#0f172a' }] },
    ];

    function toLatLng(point) {
        return { lat: point[0], lng: point[1] };
    }

    function mapsApiReady() {
        return typeof window.google?.maps?.importLibrary === 'function';
    }

    function injectMapsScript() {
        return new Promise((resolve, reject) => {
            if (!GOOGLE_MAPS_KEY) {
                reject(new Error('Missing Google Maps API key'));
                return;
            }

            const existing = document.querySelector('script[data-demo-gmaps]');
            if (existing) {
                let tries = 0;
                (function waitExisting() {
                    if (mapsApiReady()) {
                        resolve();
                    } else if (++tries > 120) {
                        reject(new Error('Google Maps API timeout'));
                    } else {
                        setTimeout(waitExisting, 50);
                    }
                })();
                return;
            }

            const script = document.createElement('script');
            script.dataset.demoGmaps = '1';
            script.async = true;
            script.defer = true;
            script.src = `https://maps.googleapis.com/maps/api/js?key=${encodeURIComponent(GOOGLE_MAPS_KEY)}&loading=async`;
            script.onerror = () => reject(new Error('Google Maps failed to load'));
            script.onload = () => {
                let tries = 0;
                (function waitMaps() {
                    if (mapsApiReady()) {
                        resolve();
                    } else if (++tries > 120) {
                        reject(new Error('Google Maps API unavailable'));
                    } else {
                        setTimeout(waitMaps, 50);
                    }
                })();
            };
            document.head.appendChild(script);
        });
    }

    async function loadGoogleMaps() {
        await injectMapsScript();

        if (typeof google.maps.importLibrary === 'function') {
            await google.maps.importLibrary('maps');
        }

        let tries = 0;
        while (typeof google?.maps?.Map !== 'function' && tries < 120) {
            await new Promise((r) => setTimeout(r, 50));
            tries++;
        }

        if (typeof google?.maps?.Map !== 'function') {
            throw new Error('Google Maps Map constructor not available');
        }

        return google.maps;
    }

    function showMapLoadError(message) {
        const el = document.getElementById('map');
        if (!el) return;
        el.innerHTML = `<div class="flex h-full items-center justify-center p-6 text-center text-slate-600 dark:text-slate-300">
            <div><i class="fas fa-map-marked-alt text-3xl mb-3 opacity-50"></i><p class="font-medium">Map could not load</p><p class="text-sm mt-1 opacity-80">${message}</p></div>
        </div>`;
    }

    function circleIcon(fillColor, scale) {
        return {
            path: google.maps.SymbolPath.CIRCLE,
            fillColor,
            fillOpacity: 1,
            strokeColor: '#ffffff',
            strokeWeight: 2,
            scale,
        };
    }

    function generateKarachiRoute() {
        return [
            [24.8607, 67.0011],
            [24.8612, 67.0018],
            [24.8619, 67.0026],
            [24.8626, 67.0034],
            [24.8633, 67.0042],
            [24.8640, 67.0050],
            [24.8647, 67.0058],
            [24.8654, 67.0066],
            [24.8661, 67.0074],
            [24.8668, 67.0082],
            [24.8675, 67.0090],
            [24.8682, 67.0098],
            [24.8689, 67.0106],
            [24.8696, 67.0114],
            [24.8703, 67.0122],
            [24.8710, 67.0130],
            [24.8717, 67.0138],
            [24.8724, 67.0146],
            [24.8731, 67.0154],
            [24.8738, 67.0162],
            [24.8745, 67.0170],
            [24.8752, 67.0178],
            [24.8759, 67.0186],
            [24.8766, 67.0194],
            [24.8773, 67.0202],
            [24.8780, 67.0210],
            [24.8787, 67.0218],
            [24.8794, 67.0226],
            [24.8801, 67.0234],
            [24.8808, 67.0242],
        ];
    }

    function applyMapTheme() {
        if (!map) return;
        const isDark = document.documentElement.classList.contains('dark');
        map.setOptions({ styles: isDark ? darkMapStyles : [] });
    }

    function initMap() {
        points = generateKarachiRoute();
        const isDark = document.documentElement.classList.contains('dark');

        map = new google.maps.Map(document.getElementById('map'), {
            center: toLatLng(points[0]),
            zoom: 14,
            mapTypeControl: false,
            streetViewControl: false,
            fullscreenControl: true,
            styles: isDark ? darkMapStyles : [],
        });

        marker = new google.maps.Marker({
            position: toLatLng(points[0]),
            map,
            title: 'Vehicle',
            icon: circleIcon('#0ea5e9', 12),
            zIndex: 1000,
        });

        polyline = new google.maps.Polyline({
            path: [],
            geodesic: true,
            strokeColor: '#0ea5e9',
            strokeOpacity: 0.8,
            strokeWeight: 4,
            map,
        });

        routeLine = new google.maps.Polyline({
            path: points.map(toLatLng),
            geodesic: true,
            strokeColor: '#3b82f6',
            strokeOpacity: 0.3,
            strokeWeight: 2,
            map,
        });

        new google.maps.Marker({
            position: toLatLng(points[0]),
            map,
            title: 'Starting Point',
            icon: circleIcon('#10b981', 10),
        });

        new google.maps.Marker({
            position: toLatLng(points[points.length - 1]),
            map,
            title: 'Destination',
            icon: circleIcon('#ef4444', 10),
        });

        startDemo();
    }

    function startDemo() {
        isPlaying = true;
        updateSpeedDisplay();
        updateLastUpdate();

        demoInterval = setInterval(() => {
            if (!isPlaying) return;

            const currentPoint = points[currentIndex];
            const latLng = toLatLng(currentPoint);
            marker.setPosition(latLng);
            trailPath.push(latLng);
            polyline.setPath(trailPath);
            map.panTo(latLng);

            const progress = ((currentIndex + 1) / points.length) * 100;
            document.getElementById('timelineProgress').style.width = `${progress}%`;

            updateSpeedDisplay();

            currentIndex = (currentIndex + 1) % points.length;

            if (currentIndex === 0) {
                trailPath = [];
                polyline.setPath([]);
            }
        }, 1500);
    }

    function updateSpeedDisplay() {
        const speed = speedValues[Math.floor(Math.random() * speedValues.length)];
        document.getElementById('currentSpeed').textContent = speed;

        // Update speed bar (0-120 km/h scale)
        const speedPercentage = (speed / 120) * 100;
        document.getElementById('speedBar').style.width = `${speedPercentage}%`;

        // Update speed indicator color
        const indicator = document.getElementById('speedIndicator');
        if (speed > 70) {
            indicator.innerHTML = '<div class="w-3 h-3 bg-red-500 rounded-full animate-pulse"></div>';
        } else if (speed > 50) {
            indicator.innerHTML = '<div class="w-3 h-3 bg-yellow-500 rounded-full animate-pulse"></div>';
        } else {
            indicator.innerHTML = '<div class="w-3 h-3 bg-emerald-500 rounded-full animate-pulse"></div>';
        }
    }

    function updateLastUpdate() {
        const now = new Date();
        const timeString = now.toLocaleTimeString([], {hour: '2-digit', minute:'2-digit'});
        document.getElementById('lastUpdate').textContent = timeString;

        // Update every minute
        setTimeout(updateLastUpdate, 60000);
    }

    function toggleDemo() {
        isPlaying = !isPlaying;
        const button = document.querySelector('[onclick="toggleDemo()"] i');
        if (isPlaying) {
            button.className = 'fas fa-pause text-slate-600 dark:text-slate-300';
        } else {
            button.className = 'fas fa-play text-slate-600 dark:text-slate-300';
        }
    }

    function resetMap() {
        clearInterval(demoInterval);
        currentIndex = 0;
        trailPath = [];
        polyline.setPath([]);
        marker.setPosition(toLatLng(points[0]));
        map.setCenter(toLatLng(points[0]));
        map.setZoom(14);
        document.getElementById('timelineProgress').style.width = '0%';
        startDemo();
    }

    function shareLocation() {
        const currentPoint = points[currentIndex];
        const url = `https://www.google.com/maps?q=${currentPoint[0]},${currentPoint[1]}`;

        // Show notification
        showNotification('Location shared!', 'success');

        // In a real app, this would use Web Share API
        if (navigator.share) {
            navigator.share({
                title: 'My Current Location',
                text: 'Check out my current location on the map',
                url: url
            });
        } else {
            window.open(url, '_blank');
        }
    }

    function showNotification(message, type = 'info') {
        // Create notification element
        const notification = document.createElement('div');
        notification.className = `fixed top-20 right-6 z-[9999] px-6 py-3 rounded-xl shadow-2xl backdrop-blur-sm ${
            type === 'success' ? 'bg-emerald-500/90 text-white' : 'bg-blue-500/90 text-white'
        }`;
        notification.textContent = message;
        document.body.appendChild(notification);

        // Remove after 3 seconds
        setTimeout(() => {
            notification.style.opacity = '0';
            notification.style.transform = 'translateX(100%)';
            setTimeout(() => notification.remove(), 300);
        }, 3000);
    }

    // Theme Toggle
    document.getElementById('themeToggle').addEventListener('click', () => {
        const html = document.documentElement;
        const icon = document.getElementById('themeIcon');

        if (html.classList.contains('dark')) {
            html.classList.remove('dark');
            icon.className = 'fas fa-moon text-lg';
        } else {
            html.classList.add('dark');
            icon.className = 'fas fa-sun text-lg';
        }

        applyMapTheme();
    });

    window.addEventListener('load', async () => {
        try {
            await loadGoogleMaps();
            initMap();
            updateLastUpdate();
            setTimeout(addPointsOfInterest, 2000);
        } catch (err) {
            console.error('Demo map failed to load', err);
            showMapLoadError(err?.message || 'Please check GOOGLE_MAPS_API_KEY in .env');
        }
    });

    function addPointsOfInterest() {
        if (!map) return;

        poiMarkers.forEach((m) => m.setMap(null));
        poiMarkers = [];

        const pois = [
            { lat: 24.8640, lng: 67.0050, title: 'Pakistan Stock Exchange' },
            { lat: 24.8675, lng: 67.0090, title: 'Mazar-e-Quaid' },
            { lat: 24.8752, lng: 67.0178, title: 'Sea View Beach' },
            { lat: 24.8808, lng: 67.0242, title: 'Dolmen Mall' },
        ];

        pois.forEach((poi) => {
            const m = new google.maps.Marker({
                position: { lat: poi.lat, lng: poi.lng },
                map,
                title: poi.title,
                icon: circleIcon('#a855f7', 8),
            });
            const info = new google.maps.InfoWindow({
                content: `<b>${poi.title}</b><br><small>Point of Interest</small>`,
            });
            m.addListener('click', () => info.open({ anchor: m, map }));
            poiMarkers.push(m);
        });
    }
</script>
</body>
</html>
