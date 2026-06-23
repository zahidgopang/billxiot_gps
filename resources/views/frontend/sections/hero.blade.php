<!-- frontend/sections/hero.blade.php -->
<section class="relative min-h-[90vh] flex items-center overflow-hidden">
    <!-- Animated Background -->
    <div class="absolute inset-0">
        <div class="absolute inset-0 bg-gradient-to-br from-slate-50 via-blue-50 to-sky-100 dark:from-slate-950 dark:via-slate-900 dark:to-sky-950"></div>
        <div class="absolute inset-0 opacity-30 bg-grid-pattern"></div>

        <!-- Floating elements -->
        <div class="absolute top-1/4 left-1/4 w-96 h-96 bg-gradient-to-r from-sky-300/20 to-blue-400/10 rounded-full blur-3xl animate-float"></div>
        <div class="absolute bottom-1/4 right-1/4 w-96 h-96 bg-gradient-to-r from-emerald-300/10 to-teal-400/5 rounded-full blur-3xl animate-float" style="animation-delay: 3s"></div>

        <!-- GPS Signal Animation -->
        <div class="absolute top-1/2 left-1/2 -translate-x-1/2 -translate-y-1/2">
            <div class="relative w-80 h-80">
                <div class="absolute inset-0 rounded-full border-2 border-sky-400/30 animate-ping" style="animation-delay: 0s"></div>
                <div class="absolute inset-8 rounded-full border-2 border-sky-400/20 animate-ping" style="animation-delay: 0.5s"></div>
                <div class="absolute inset-16 rounded-full border-2 border-sky-400/10 animate-ping" style="animation-delay: 1s"></div>
            </div>
        </div>
    </div>

    <div class="relative max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-24 lg:py-32">
        <div class="grid lg:grid-cols-2 gap-16 items-center">
            {{-- Left Content --}}
            <div class="{{ ($isRtl ?? false) ? 'text-right' : 'text-left' }} animate-slide-in">
                <!-- Trust Badge -->
                <div class="inline-flex items-center gap-2 px-4 py-2 bg-gradient-to-r from-emerald-500/10 to-teal-500/5 rounded-full border border-emerald-500/20 mb-8">
                    <div class="relative flex h-2 w-2">
                        <span class="animate-ping absolute inline-flex h-full w-full rounded-full bg-emerald-400 opacity-75"></span>
                        <span class="relative inline-flex rounded-full h-2 w-2 bg-emerald-500"></span>
                    </div>
                    <span class="text-sm font-medium text-emerald-700 dark:text-emerald-400">{{ __('frontend.hero.badge') }}</span>
                </div>

                <!-- Main Headline -->
                <h1 class="text-5xl lg:text-7xl font-bold leading-tight mb-6">
                    <span class="block text-slate-900 dark:text-white">{{ __('frontend.hero.title_line1') }}</span>
                    <span class="block bg-gradient-to-r from-sky-500 via-blue-500 to-purple-600 bg-clip-text text-transparent animate-gradient">
                        {{ __('frontend.hero.title_line2') }}
                    </span>
                </h1>

                <!-- Subheadline -->
                <p class="text-xl lg:text-2xl text-slate-600 dark:text-slate-300 mb-4 max-w-xl">
                    {{ __('frontend.hero.subtitle') }}
                </p>
                <p class="text-base text-slate-500 dark:text-slate-400 mb-8 max-w-xl leading-relaxed">
                    {{ __('frontend.hero.seo_intro') }}
                </p>

                <!-- Stats Grid -->
                <div class="grid grid-cols-2 sm:grid-cols-4 gap-4 mb-12">
                    <div class="text-center p-4 rounded-xl glass">
                        <div class="text-2xl font-bold text-sky-600 dark:text-sky-400">24/7</div>
                        <div class="text-sm text-slate-500 dark:text-slate-400">{{ __('frontend.hero.support') }}</div>
                    </div>
                    <div class="text-center p-4 rounded-xl glass">
                        <div class="text-2xl font-bold text-sky-600 dark:text-sky-400">Live</div>
                        <div class="text-sm text-slate-500 dark:text-slate-400">{{ __('frontend.hero.live_map') }}</div>
                    </div>
                    <div class="text-center p-4 rounded-xl glass">
                        <div class="text-2xl font-bold text-sky-600 dark:text-sky-400">GPS</div>
                        <div class="text-sm text-slate-500 dark:text-slate-400">{{ __('frontend.hero.uptime') }}</div>
                    </div>
                    <div class="text-center p-4 rounded-xl glass">
                        <div class="text-2xl font-bold text-sky-600 dark:text-sky-400">Trips</div>
                        <div class="text-sm text-slate-500 dark:text-slate-400">{{ __('frontend.hero.history') }}</div>
                    </div>
                </div>

                <!-- CTA Buttons -->
                <div class="flex flex-col sm:flex-row gap-4 mb-12">
                    <a href="{{ url('/contact') }}"
                       class="group relative px-8 py-4 bg-gradient-to-r from-sky-600 to-blue-700 text-white font-semibold rounded-xl shadow-lg hover:shadow-xl transition-all duration-300 overflow-hidden">
                        <span class="relative z-10 flex items-center justify-center gap-2">
                            {{ __('frontend.hero.request_demo') }}
                            <svg class="w-5 h-5 group-hover:translate-x-1 transition-transform" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 7l5 5m0 0l-5 5m5-5H6"/>
                            </svg>
                        </span>
                        <div class="absolute inset-0 bg-gradient-to-r from-blue-600 to-purple-600 transform -translate-x-full group-hover:translate-x-0 transition-transform duration-500"></div>
                    </a>

                    <button type="button" onclick="openVideoModal()"
                            class="group px-8 py-4 glass border border-slate-300 dark:border-slate-700 rounded-xl font-semibold text-center hover:bg-slate-100 dark:hover:bg-slate-800 transition-colors">
                        <span class="flex items-center justify-center gap-2">
                            <svg class="w-5 h-5 group-hover:scale-110 transition-transform" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M14.752 11.168l-3.197-2.132A1 1 0 0010 9.87v4.263a1 1 0 001.555.832l3.197-2.132a1 1 0 000-1.664z"/>
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
                            </svg>
                            {{ __('frontend.hero.watch_demo') }}
                        </span>
                    </button>
                </div>

                <!-- Trusted By -->
                <div class="flex flex-wrap items-center gap-x-4 gap-y-2 text-sm text-slate-500 dark:text-slate-400">
                    <span>{{ __('frontend.hero.trusted_label') }}</span>
                    @foreach(__('frontend.trusted_partners') as $partner)
                        <span class="font-medium opacity-80">{{ $partner }}</span>
                    @endforeach
                </div>
            </div>

            {{-- Right - Interactive Dashboard Preview --}}
            <div class="relative hero-parallax">
                <div class="gradient-border">
                    <div class="relative bg-slate-900 rounded-xl overflow-hidden">
                        <!-- Dashboard Header -->
                        <div class="p-4 border-b border-slate-800 flex items-center justify-between">
                            <div class="flex items-center gap-2">
                                <div class="flex gap-1.5">
                                    <div class="w-3 h-3 rounded-full bg-red-500"></div>
                                    <div class="w-3 h-3 rounded-full bg-yellow-500"></div>
                                    <div class="w-3 h-3 rounded-full bg-emerald-500"></div>
                                </div>
                                <span class="text-xs text-slate-400 ml-2">{{ __('frontend.hero.map_title') }}</span>
                            </div>
                            <div class="flex items-center gap-2">
                                <div class="w-2 h-2 rounded-full bg-emerald-500 animate-pulse"></div>
                                <span class="text-xs text-emerald-500">{{ __('frontend.hero.map_active', ['count' => 18]) }}</span>
                            </div>
                        </div>

                        <!-- Map Container -->
                        <div class="relative h-64 bg-gradient-to-b from-blue-950/50 to-slate-900">
                            <!-- Animated Route Lines -->
                            <svg class="absolute inset-0 w-full h-full" viewBox="0 0 100 100" preserveAspectRatio="none">
                                <defs>
                                    <linearGradient id="route-gradient" x1="0%" y1="0%" x2="100%" y2="0%">
                                        <stop offset="0%" stop-color="#0ea5e9" stop-opacity="0.8"/>
                                        <stop offset="100%" stop-color="#3b82f6" stop-opacity="0.8"/>
                                    </linearGradient>
                                    <mask id="route-mask">
                                        <path d="M10,50 Q30,30 50,50 T90,50" stroke="white" stroke-width="2" fill="none" stroke-dasharray="5,5"/>
                                    </mask>
                                </defs>
                                <path d="M10,50 Q30,30 50,50 T90,50" stroke="url(#route-gradient)" stroke-width="3" fill="none" mask="url(#route-mask)">
                                    <animate attributeName="stroke-dashoffset" from="100" to="0" dur="3s" repeatCount="indefinite"/>
                                </path>
                            </svg>

                            <!-- Moving Vehicle -->
                            <div class="absolute top-1/3 left-1/4 animate-float" style="animation-duration: 8s">
                                <div class="relative">
                                    <div class="w-10 h-10 bg-gradient-to-br from-sky-500 to-blue-600 rounded-lg flex items-center justify-center shadow-lg">
                                        <svg class="w-5 h-5 text-white" fill="currentColor" viewBox="0 0 20 20">
                                            <path d="M8 16.5a1.5 1.5 0 11-3 0 1.5 1.5 0 013 0zM15 16.5a1.5 1.5 0 11-3 0 1.5 1.5 0 013 0z"/>
                                            <path d="M3 4a1 1 0 00-1 1v10a1 1 0 001 1h1.05a2.5 2.5 0 014.9 0H10a1 1 0 001-1v-1h4v1a1 1 0 001 1h1.05a2.5 2.5 0 014.9 0H20a1 1 0 001-1v-4a1 1 0 00-1-1h-1.5V7a1 1 0 00-1-1h-3.5V4a1 1 0 00-1-1H3z"/>
                                        </svg>
                                    </div>
                                    <div class="absolute -bottom-1 inset-x-0 h-1 bg-sky-500/30 blur-sm"></div>
                                </div>
                            </div>
                        </div>

                        <!-- Live Stats -->
                        <div class="p-4 grid grid-cols-2 gap-4">
                            <div class="p-3 rounded-lg bg-slate-800/50">
                                <div class="text-xs text-slate-400 mb-1">{{ __('frontend.hero.map_speed') }}</div>
                                <div class="text-lg font-semibold text-white">72 km/h</div>
                            </div>
                            <div class="p-3 rounded-lg bg-slate-800/50">
                                <div class="text-xs text-slate-400 mb-1">{{ __('frontend.hero.map_route') }}</div>
                                <div class="text-lg font-semibold text-white">284 km</div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Floating Stats Card -->
                <div class="absolute -bottom-6 -right-6 gradient-border">
                    <div class="bg-gradient-to-br from-emerald-500 to-teal-600 rounded-xl p-6 w-40">
                        <div class="text-center text-white">
                            <div class="text-2xl font-bold">3</div>
                            <div class="text-sm">{{ __('frontend.hero.map_alert') }}</div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Scroll Indicator -->
    <div class="absolute bottom-8 left-1/2 transform -translate-x-1/2 animate-bounce">
        <div class="w-6 h-10 rounded-full border-2 border-slate-400 flex justify-center">
            <div class="w-1 h-3 bg-slate-400 rounded-full mt-2 animate-pulse"></div>
        </div>
    </div>
</section>

<!-- Product Tour Video Modal -->
<div id="videoModal" class="fixed inset-0 z-50 hidden overflow-y-auto">
    <div class="flex items-center justify-center min-h-screen px-4 pt-4 pb-20 text-center sm:p-0">
        <div class="fixed inset-0 bg-black/80 backdrop-blur-sm transition-opacity" onclick="closeVideoModal()"></div>

        <div class="relative bg-gradient-to-br from-slate-900 to-slate-800 rounded-3xl shadow-2xl transform transition-all sm:my-8 w-full max-w-4xl mx-auto overflow-hidden">
            <div class="p-6 border-b border-slate-700">
                <div class="flex items-center justify-between">
                    <div class="flex items-center gap-3">
                        <div class="w-10 h-10 rounded-xl bg-gradient-to-r from-sky-500 to-blue-600 flex items-center justify-center">
                            <svg class="w-5 h-5 text-white" fill="currentColor" viewBox="0 0 20 20">
                                <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zM9.555 7.168A1 1 0 008 8v4a1 1 0 001.555.832l3-2a1 1 0 000-1.664l-3-2z" clip-rule="evenodd"/>
                            </svg>
                        </div>
                        <div>
                            <h3 class="text-xl font-bold text-white">{{ __('frontend.hero.video_title') }}</h3>
                            <p class="text-sm text-slate-300">{{ __('frontend.hero.video_subtitle') }}</p>
                        </div>
                    </div>
                    <button type="button" onclick="closeVideoModal()"
                            class="p-2 rounded-lg hover:bg-slate-800 transition-colors group">
                        <svg class="w-6 h-6 text-slate-400 group-hover:text-white transition-colors" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                        </svg>
                    </button>
                </div>
            </div>

            <div class="p-2 md:p-4">
                <div class="relative aspect-video rounded-xl overflow-hidden bg-black">
                    <div id="youtubePlayer" class="w-full h-full"></div>
                    <div id="videoLoading" class="absolute inset-0 flex items-center justify-center bg-gradient-to-br from-slate-900 to-slate-800">
                        <div class="text-center">
                            <div class="w-16 h-16 rounded-full border-4 border-slate-700 border-t-sky-500 animate-spin mx-auto mb-4"></div>
                            <p class="text-slate-300">Loading video player...</p>
                        </div>
                    </div>
                </div>
            </div>

            <div class="p-6 border-t border-slate-700">
                <div class="flex flex-col sm:flex-row justify-between items-center gap-4">
                    <div class="text-sm text-slate-400">
                        Need a personalized demo?
                        <a href="{{ url('/contact') }}" class="text-sky-400 hover:text-sky-300 font-medium ml-1">
                            {{ __('frontend.hero.video_demo_link') }}
                        </a>
                    </div>
                    <div class="flex gap-3">
                        <button type="button" onclick="shareVideo()"
                                class="px-4 py-2 rounded-lg bg-slate-800 hover:bg-slate-700 transition-colors text-white flex items-center gap-2">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8.684 13.342C8.886 12.938 9 12.482 9 12c0-.482-.114-.938-.316-1.342m0 2.684a3 3 0 110-2.684m0 2.684l6.632 3.316m-6.632-6l6.632-3.316m0 0a3 3 0 105.367-2.684 3 3 0 00-5.367 2.684zm0 9.316a3 3 0 105.368 2.684 3 3 0 00-5.368-2.684z"/>
                            </svg>
                            Share
                        </button>
                        <a href="{{ url('/contact') }}"
                           class="px-6 py-2 rounded-lg bg-gradient-to-r from-sky-500 to-blue-600 hover:from-sky-600 hover:to-blue-700 transition-all text-white font-semibold">
                            {{ __('frontend.hero.request_demo') }}
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<style>
    /* Custom animations */
    @keyframes gradient {
        0%, 100% { background-position: 0% 50%; }
        50% { background-position: 100% 50%; }
    }

    .animate-gradient {
        background-size: 200% 200%;
        animation: gradient 3s ease infinite;
    }

    .bg-grid-pattern {
        background-image:
            linear-gradient(to right, rgba(14, 165, 233, 0.1) 1px, transparent 1px),
            linear-gradient(to bottom, rgba(14, 165, 233, 0.1) 1px, transparent 1px);
        background-size: 40px 40px;
    }

    .glass {
        background: rgba(255, 255, 255, 0.1);
        backdrop-filter: blur(10px);
        -webkit-backdrop-filter: blur(10px);
    }

    .gradient-border {
        position: relative;
        padding: 1px;
        background: linear-gradient(135deg, #0ea5e9, #3b82f6, #8b5cf6);
        border-radius: 16px;
    }

    .gradient-border > div {
        background: #0f172a;
        border-radius: 15px;
        overflow: hidden;
    }

    .hero-parallax {
        transform: perspective(1000px) rotateY(-5deg) rotateX(5deg);
        transition: transform 0.3s ease;
    }

    .hero-parallax:hover {
        transform: perspective(1000px) rotateY(0) rotateX(0);
    }

    @keyframes modalFadeIn {
        from {
            opacity: 0;
            transform: scale(0.95) translateY(-20px);
        }
        to {
            opacity: 1;
            transform: scale(1) translateY(0);
        }
    }

    #videoModal:not(.hidden) > div > div:last-child {
        animation: modalFadeIn 0.3s ease-out;
    }
</style>

<script>
    let player;
    const videoId = 'CFef9VZcB8s';
    const videoUrl = 'https://www.youtube.com/watch?v=CFef9VZcB8s';

    function loadYouTubeAPI() {
        if (window.YT && window.YT.Player) {
            return;
        }
        const tag = document.createElement('script');
        tag.src = 'https://www.youtube.com/iframe_api';
        const firstScriptTag = document.getElementsByTagName('script')[0];
        firstScriptTag.parentNode.insertBefore(tag, firstScriptTag);
    }

    function onYouTubeIframeAPIReady() {
        player = new YT.Player('youtubePlayer', {
            height: '100%',
            width: '100%',
            videoId: videoId,
            playerVars: {
                autoplay: 0,
                controls: 1,
                modestbranding: 1,
                rel: 0,
                playsinline: 1
            },
            events: {
                onReady: function () {
                    const loading = document.getElementById('videoLoading');
                    if (loading) loading.style.display = 'none';
                }
            }
        });
    }

    window.onYouTubeIframeAPIReady = onYouTubeIframeAPIReady;

    function openVideoModal() {
        const modal = document.getElementById('videoModal');
        modal.classList.remove('hidden');
        document.body.style.overflow = 'hidden';

        if (typeof YT === 'undefined' || !YT.Player) {
            loadYouTubeAPI();
        } else if (player && player.playVideo) {
            player.playVideo();
        } else if (!player) {
            onYouTubeIframeAPIReady();
        }
    }

    function closeVideoModal() {
        const modal = document.getElementById('videoModal');
        modal.classList.add('hidden');
        document.body.style.overflow = 'auto';

        if (player && player.pauseVideo) {
            player.pauseVideo();
        }
    }

    function shareVideo() {
        const title = 'FalconEyeGPS Product Tour';
        const text = 'Check out the FalconEyeGPS Fleet Management product tour!';

        if (navigator.share) {
            navigator.share({ title, text, url: videoUrl });
        } else {
            navigator.clipboard.writeText(videoUrl).then(() => {
                alert('Video link copied to clipboard!');
            });
        }
    }

    document.addEventListener('keydown', (e) => {
        if (e.key === 'Escape') {
            closeVideoModal();
        }
    });
</script>
