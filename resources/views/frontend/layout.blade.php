<!DOCTYPE html>
<html lang="{{ $htmlLang ?? 'ar' }}" dir="{{ $htmlDir ?? 'ltr' }}" class="scroll-smooth">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, maximum-scale=5, viewport-fit=cover">
    @include('partials.seo-meta', [
        'seoTitle' => trim($__env->yieldContent('title')) ?: null,
        'seoDescription' => trim($__env->yieldContent('description')) ?: null,
    ])

    @include('partials.analytics')

    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css"/>

    <!-- Google Fonts -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link rel="preconnect" href="https://cdnjs.cloudflare.com" crossorigin>
    <link rel="dns-prefetch" href="https://cdn.tailwindcss.com">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    @if($isRtl ?? false)
    <link href="https://fonts.googleapis.com/css2?family=Noto+Sans+Arabic:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="{{ asset('css/frontend-rtl.css') }}?v={{ filemtime(public_path('css/frontend-rtl.css')) }}">
    @endif

    <!-- Tailwind CSS -->
    <script src="https://cdn.tailwindcss.com"></script>
    <script>
        tailwind.config = {
            darkMode: 'class',
            theme: {
                extend: {
                    fontFamily: {
                        'sans': ['Inter', 'system-ui', 'sans-serif'],
                    },
                    animation: {
                        'float': 'float 6s ease-in-out infinite',
                        'slide-in': 'slide-in 0.5s ease-out',
                        'gradient-x': 'gradient-x 15s ease infinite',
                    },
                    keyframes: {
                        float: {
                            '0%, 100%': { transform: 'translateY(0px)' },
                            '50%': { transform: 'translateY(-20px)' },
                        },
                        'slide-in': {
                            '0%': { transform: 'translateY(20px)', opacity: 0 },
                            '100%': { transform: 'translateY(0)', opacity: 1 },
                        },
                        'gradient-x': {
                            '0%, 100%': { 'background-size': '200% 200%', 'background-position': 'left center' },
                            '50%': { 'background-size': '200% 200%', 'background-position': 'right center' },
                        }
                    }
                }
            }
        }
    </script>

    <link rel="stylesheet" href="{{ asset('css/brand-logo.css') }}?v={{ filemtime(public_path('css/brand-logo.css')) }}">
    <link rel="stylesheet" href="{{ asset('css/site-header.css') }}?v={{ filemtime(public_path('css/site-header.css')) }}">

    @stack('head')

    <style>
        .site-header-overlay {
            position: fixed;
            inset: 0;
            top: var(--site-header-height, 4.5rem);
            background: rgba(0, 0, 0, 0.5);
            backdrop-filter: blur(4px);
            z-index: 40;
            opacity: 0;
            visibility: hidden;
            transition: opacity 0.3s ease, visibility 0.3s ease;
        }

        .site-header-overlay.active {
            opacity: 1;
            visibility: visible;
        }

        body.site-menu-open {
            overflow: hidden;
        }

        /* Glass effect */
        .glass {
            background: rgba(255, 255, 255, 0.05);
            backdrop-filter: blur(10px);
            border: 1px solid rgba(255, 255, 255, 0.1);
        }

        .dark .glass {
            background: rgba(15, 23, 42, 0.7);
            border: 1px solid rgba(255, 255, 255, 0.05);
        }

        html {
            scroll-padding-top: var(--site-header-height, 4.5rem);
            overflow-x: hidden;
            max-width: 100%;
        }

        main.site-main {
            overflow-x: hidden;
            max-width: 100%;
        }

        .site-header__mobile-panel.is-open::-webkit-scrollbar {
            width: 6px;
        }
    </style>
    @stack('styles')
</head>

<body class="font-sans bg-gradient-to-br from-slate-50 to-blue-50 dark:from-slate-950 dark:to-slate-900 text-slate-800 dark:text-slate-100 transition-colors duration-300 overflow-x-hidden">
@if(config('seo.google_tag_manager_id'))
<noscript><iframe src="https://www.googletagmanager.com/ns.html?id={{ config('seo.google_tag_manager_id') }}"
height="0" width="0" style="display:none;visibility:hidden" title="Google Tag Manager"></iframe></noscript>
@endif
<!-- Animated Background Elements -->
<div class="fixed inset-0 overflow-hidden pointer-events-none z-[-1]">
    <div class="absolute top-1/4 -left-32 w-64 h-64 bg-gradient-to-r from-sky-300/20 to-blue-400/10 rounded-full blur-3xl animate-float"></div>
    <div class="absolute bottom-1/4 -right-32 w-96 h-96 bg-gradient-to-r from-emerald-300/10 to-teal-400/5 rounded-full blur-3xl animate-float" style="animation-delay: 2s"></div>
</div>

{{-- ================= FIXED NAVBAR ================= --}}
<header id="siteHeader" class="site-header fixed top-0 inset-x-0 z-50 glass border-b border-slate-200 dark:border-slate-800 bg-white/80 dark:bg-slate-950/80 backdrop-blur-md">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 relative">
        <div class="site-header__inner">
            <a href="{{ url('/') }}" class="site-header__logo" aria-label="{{ config('branding.name') }}">
                @include('partials.brand-logo')
            </a>

            <nav class="site-header__nav" aria-label="Main navigation">
                <a href="{{ url('/') }}#features" class="site-header__nav-link">{{ __('frontend.nav.features') }}</a>
                <a href="{{ url('/pricing') }}" class="site-header__nav-link">{{ __('frontend.nav.pricing') }}</a>
                <a href="{{ url('/') }}#testimonials" class="site-header__nav-link site-header__nav-link--wide">{{ __('frontend.nav.testimonials') }}</a>
                <a href="{{ url('/') }}#use-cases" class="site-header__nav-link site-header__nav-link--wide">{{ __('frontend.nav.use_cases') }}</a>
                <a href="{{ url('/') }}#android-app" class="site-header__nav-link site-header__nav-link--app">
                    <i class="fa-brands fa-android site-header__nav-icon" aria-hidden="true"></i>
                    <span class="site-header__nav-label-full">{{ __('frontend.nav.android_app') }}</span>
                    <span class="site-header__nav-label-short">{{ __('frontend.nav.android_app_short') }}</span>
                </a>
            </nav>

            <div class="site-header__right">
                <div class="site-header__tools">
                    <div class="hidden sm:block">
                        @include('frontend.partials.language-toggle')
                    </div>
                    <button type="button" id="themeToggle"
                            class="w-10 h-10 rounded-xl glass flex items-center justify-center hover:bg-slate-100 dark:hover:bg-slate-800 transition-colors"
                            aria-label="Toggle theme">
                        <svg class="w-5 h-5 text-slate-600 dark:text-slate-400 hidden dark:block" fill="currentColor" viewBox="0 0 20 20">
                            <path d="M17.293 13.293A8 8 0 016.707 2.707a8.001 8.001 0 1010.586 10.586z"/>
                        </svg>
                        <svg class="w-5 h-5 text-slate-600 dark:text-slate-400 block dark:hidden" fill="currentColor" viewBox="0 0 20 20">
                            <path fill-rule="evenodd" d="M10 2a1 1 0 011 1v1a1 1 0 11-2 0V3a1 1 0 011-1zm4 8a4 4 0 11-8 0 4 4 0 018 0zm-.464 4.95l.707.707a1 1 0 001.414-1.414l-.707-.707a1 1 0 00-1.414 1.414zm2.12-10.607a1 1 0 010 1.414l-.706.707a1 1 0 11-1.414-1.414l.707-.707a1 1 0 011.414 0zM17 11a1 1 0 100-2h-1a1 1 0 100 2h1zm-7 4a1 1 0 011 1v1a1 1 0 11-2 0v-1a1 1 0 011-1zM5.05 6.464A1 1 0 106.465 5.05l-.708-.707a1 1 0 00-1.414 1.414l.707.707zm1.414 8.486l-.707.707a1 1 0 01-1.414-1.414l.707-.707a1 1 0 011.414 1.414zM4 11a1 1 0 100-2H3a1 1 0 000 2h1z" clip-rule="evenodd"/>
                        </svg>
                    </button>
                </div>

                <div class="site-header__cta">
                    <a href="{{ url('/demo/login') }}"
                       class="site-header__demo bg-gradient-to-r from-emerald-500 to-teal-600 text-white shadow-md hover:shadow-lg hover:scale-[1.02] transition-all">
                        {{ __('frontend.nav.live_demo') }}
                    </a>
                    <a href="{{ url('login') }}"
                       class="site-header__cta-signin text-slate-700 dark:text-slate-300 hover:bg-slate-100 dark:hover:bg-slate-800 transition-colors">
                        {{ __('frontend.nav.sign_in') }}
                    </a>
                    <a href="{{ url('/contact') }}"
                       class="site-header__cta-primary bg-gradient-to-r from-sky-600 to-blue-700 text-white shadow-lg hover:shadow-xl hover:scale-[1.02] transition-all">
                        {{ __('frontend.nav.get_started') }}
                    </a>
                </div>

                <button type="button" id="mobileMenuBtn"
                        class="lg:hidden w-10 h-10 rounded-xl glass flex items-center justify-center hover:bg-slate-100 dark:hover:bg-slate-800 transition-colors shrink-0"
                        aria-expanded="false"
                        aria-controls="mobileMenu">
                    <svg id="menuIcon" class="w-6 h-6 text-slate-600 dark:text-slate-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16"/>
                    </svg>
                    <svg id="closeIcon" class="w-6 h-6 text-slate-600 dark:text-slate-400 hidden" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                    </svg>
                </button>
            </div>
        </div>

        <div id="mobileMenu" class="site-header__mobile-panel lg:hidden bg-white dark:bg-slate-900">
            <div class="px-4 py-5 space-y-1">
                <div class="flex justify-center pb-3 sm:hidden">
                    @include('frontend.partials.language-toggle')
                </div>
                <a href="{{ url('/') }}" class="block py-3 px-4 rounded-xl hover:bg-slate-100 dark:hover:bg-slate-800 font-medium">{{ __('frontend.nav.home') }}</a>
                <a href="{{ url('/') }}#features" class="block py-3 px-4 rounded-xl hover:bg-slate-100 dark:hover:bg-slate-800 font-medium">{{ __('frontend.nav.features') }}</a>
                <a href="{{ url('/pricing') }}" class="block py-3 px-4 rounded-xl hover:bg-slate-100 dark:hover:bg-slate-800 font-medium">{{ __('frontend.nav.pricing') }}</a>
                <a href="{{ url('/') }}#testimonials" class="block py-3 px-4 rounded-xl hover:bg-slate-100 dark:hover:bg-slate-800 font-medium">{{ __('frontend.nav.testimonials') }}</a>
                <a href="{{ url('/') }}#use-cases" class="block py-3 px-4 rounded-xl hover:bg-slate-100 dark:hover:bg-slate-800 font-medium">{{ __('frontend.nav.use_cases') }}</a>
                <a href="{{ url('/') }}#android-app" class="block py-3 px-4 rounded-xl hover:bg-slate-100 dark:hover:bg-slate-800 font-medium">{{ __('frontend.nav.android_app') }}</a>
                <a href="{{ url('/demo/login') }}" class="block py-3 px-4 rounded-xl bg-gradient-to-r from-emerald-500 to-teal-600 text-white font-semibold text-center">
                    {{ __('frontend.nav.live_demo') }}
                </a>
                <div class="pt-4 border-t border-slate-200 dark:border-slate-800">
                    <div class="site-header__mobile-cta-row">
                        <a href="{{ url('login') }}" class="border border-slate-300 dark:border-slate-600 font-medium text-slate-800 dark:text-slate-200">
                            {{ __('frontend.nav.sign_in') }}
                        </a>
                        <a href="{{ url('/contact') }}" class="bg-gradient-to-r from-sky-600 to-blue-700 text-white font-semibold">
                            {{ __('frontend.nav.get_started') }}
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div id="mobileMenuOverlay" class="site-header-overlay lg:hidden" aria-hidden="true"></div>
</header>

<main class="site-main">
    @yield('content')
</main>

{{-- ================= FIXED FOOTER ================= --}}
<footer class="relative bg-gradient-to-b from-slate-900 to-slate-950 border-t border-slate-800">
    <!-- Background Pattern -->
    <div class="absolute inset-0 opacity-5">
        <div class="absolute inset-0" style="background-image: radial-gradient(circle at 2px 2px, #22d3ee 1px, transparent 1px); background-size: 40px 40px;"></div>
    </div>

    <!-- Gradient Overlay -->
    <div class="absolute inset-0 bg-gradient-to-t from-slate-950/50 to-transparent"></div>

    <div class="relative max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-16">
        <div class="grid lg:grid-cols-4 gap-12">
            {{-- Brand --}}
            <div class="lg:col-span-1">
                <a href="{{ url('/') }}" class="site-footer-brand inline-flex mb-6" aria-label="{{ config('branding.name') }}">
                    @include('partials.brand-logo')
                </a>
                <p class="text-slate-400 text-sm">
                    {{ __('frontend.meta.footer_blurb') }}
                </p>

                <!-- Social Links -->
                <div class="flex items-center space-x-4 mt-6">
                    <a href="#" class="w-10 h-10 rounded-lg bg-slate-800 flex items-center justify-center text-slate-400 hover:text-white hover:bg-slate-700 transition-colors">
                        <i class="fab fa-twitter"></i>
                    </a>
                    <a href="#" class="w-10 h-10 rounded-lg bg-slate-800 flex items-center justify-center text-slate-400 hover:text-white hover:bg-slate-700 transition-colors">
                        <i class="fab fa-linkedin"></i>
                    </a>
                    <a href="#" class="w-10 h-10 rounded-lg bg-slate-800 flex items-center justify-center text-slate-400 hover:text-white hover:bg-slate-700 transition-colors">
                        <i class="fab fa-facebook"></i>
                    </a>
                    <a href="#" class="w-10 h-10 rounded-lg bg-slate-800 flex items-center justify-center text-slate-400 hover:text-white hover:bg-slate-700 transition-colors">
                        <i class="fab fa-youtube"></i>
                    </a>
                </div>
            </div>

            {{-- Quick Links --}}
            <div>
                <h4 class="font-semibold text-white mb-4">{{ __('frontend.footer.product') }}</h4>
                <ul class="space-y-3">
                    <li><a href="{{ url('/') }}#features" class="text-slate-400 hover:text-white transition-colors text-sm">{{ __('frontend.nav.features') }}</a></li>
                    <li><a href="{{ url('/pricing') }}" class="text-slate-400 hover:text-white transition-colors text-sm">{{ __('frontend.nav.pricing') }}</a></li>
                    <li><a href="{{ url('/') }}#use-cases" class="text-slate-400 hover:text-white transition-colors text-sm">{{ __('frontend.nav.use_cases') }}</a></li>
                    <li><a href="{{ route('android-app') }}" class="text-slate-400 hover:text-white transition-colors text-sm">Android App</a></li>
                    <li><a href="{{ url('/demo/login') }}" class="text-slate-400 hover:text-white transition-colors text-sm">{{ __('frontend.nav.live_demo') }}</a></li>
                    <li><a href="{{ url('/') }}#testimonials" class="text-slate-400 hover:text-white transition-colors text-sm">{{ __('frontend.nav.testimonials') }}</a></li>
                </ul>
            </div>

            {{-- Company --}}
            <div>
                <h4 class="font-semibold text-white mb-4">{{ __('frontend.footer.company') }}</h4>
                <ul class="space-y-3">
                    <li><a href="{{ url('/about') }}" class="text-slate-400 hover:text-white transition-colors text-sm">{{ __('frontend.footer.about') }}</a></li>
                    <li><a href="{{ url('/contact') }}" class="text-slate-400 hover:text-white transition-colors text-sm">{{ __('frontend.footer.contact') }}</a></li>
                    <li><a href="{{ url('/careers') }}" class="text-slate-400 hover:text-white transition-colors text-sm">{{ __('frontend.footer.careers') }}</a></li>
                    <li><a href="{{ url('/blog') }}" class="text-slate-400 hover:text-white transition-colors text-sm">{{ __('frontend.footer.blog') }}</a></li>
                    <li><a href="{{ url('/press') }}" class="text-slate-400 hover:text-white transition-colors text-sm">{{ __('frontend.footer.press') }}</a></li>
                </ul>
            </div>

            {{-- Legal & Support --}}
            <div>
                <h4 class="font-semibold text-white mb-4">{{ __('frontend.footer.support') }}</h4>
                <ul class="space-y-3">
                    <li><a href="{{ url('/help') }}" class="text-slate-400 hover:text-white transition-colors text-sm">{{ __('frontend.footer.help') }}</a></li>
                    <li><a href="{{ url('/docs') }}" class="text-slate-400 hover:text-white transition-colors text-sm">{{ __('frontend.footer.docs') }}</a></li>
                    <li><a href="{{ url('/api') }}" class="text-slate-400 hover:text-white transition-colors text-sm">{{ __('frontend.footer.api') }}</a></li>
                    <li><a href="{{ url('/status') }}" class="text-slate-400 hover:text-white transition-colors text-sm">{{ __('frontend.footer.status') }}</a></li>
                    <li><a href="{{ url('/contact') }}" class="text-slate-400 hover:text-white transition-colors text-sm">{{ __('frontend.footer.contact_support') }}</a></li>
                </ul>
            </div>
        </div>

        {{-- Bottom Bar --}}
        <div class="mt-12 pt-8 border-t border-slate-800 flex flex-col md:flex-row justify-between items-center">
            <div class="text-center md:text-left mb-4 md:mb-0">
                <p class="text-slate-500 text-sm">
                    {{ str_replace(':year', (string) date('Y'), __('frontend.meta.copyright')) }}
                </p>
                <p class="text-slate-600 text-xs mt-1">
                    {{ __('frontend.meta.trademark') }}
                </p>
            </div>

            <div class="flex items-center space-x-6">
                <a href="{{ url('/privacy') }}" class="text-slate-500 hover:text-white transition-colors text-sm">{{ __('frontend.footer.privacy') }}</a>
                <a href="{{ url('/terms') }}" class="text-slate-500 hover:text-white transition-colors text-sm">{{ __('frontend.footer.terms') }}</a>
                <a href="{{ url('/security') }}" class="text-slate-500 hover:text-white transition-colors text-sm">{{ __('frontend.footer.security') }}</a>
                <a href="{{ url('/cookies') }}" class="text-slate-500 hover:text-white transition-colors text-sm">{{ __('frontend.footer.cookies') }}</a>
            </div>
        </div>
    </div>
</footer>

{{-- ================= SCRIPTS ================= --}}
<script>
    // Theme Toggle
    const themeToggle = document.getElementById('themeToggle');
    const applyTheme = () => {
        const theme = localStorage.getItem('theme') || (window.matchMedia('(prefers-color-scheme: dark)').matches ? 'dark' : 'light');
        document.documentElement.classList.toggle('dark', theme === 'dark');
    };

    themeToggle.addEventListener('click', () => {
        const isDark = document.documentElement.classList.contains('dark');
        document.documentElement.classList.toggle('dark');
        localStorage.setItem('theme', isDark ? 'light' : 'dark');
    });

    applyTheme();

    function syncSiteHeaderHeight() {
        const header = document.getElementById('siteHeader');
        if (!header) return;
        document.documentElement.style.setProperty('--site-header-height', header.offsetHeight + 'px');
    }

    syncSiteHeaderHeight();
    window.addEventListener('resize', syncSiteHeaderHeight);

    // Mobile Menu Functionality
    const mobileMenuBtn = document.getElementById('mobileMenuBtn');
    const mobileMenu = document.getElementById('mobileMenu');
    const mobileMenuOverlay = document.getElementById('mobileMenuOverlay');
    const menuIcon = document.getElementById('menuIcon');
    const closeIcon = document.getElementById('closeIcon');
    const body = document.body;

    function setMobileMenuOpen(open) {
        if (!mobileMenu || !mobileMenuBtn) return;
        mobileMenu.classList.toggle('is-open', open);
        mobileMenuOverlay?.classList.toggle('active', open);
        body.classList.toggle('site-menu-open', open);
        mobileMenuBtn.setAttribute('aria-expanded', open ? 'true' : 'false');
        menuIcon?.classList.toggle('hidden', open);
        closeIcon?.classList.toggle('hidden', !open);
        if (open) {
            syncSiteHeaderHeight();
        }
    }

    function closeMobileMenu() {
        setMobileMenuOpen(false);
    }

    mobileMenuBtn?.addEventListener('click', () => {
        setMobileMenuOpen(!mobileMenu.classList.contains('is-open'));
    });

    mobileMenuOverlay?.addEventListener('click', closeMobileMenu);

    document.querySelectorAll('#mobileMenu a').forEach(link => {
        link.addEventListener('click', closeMobileMenu);
    });

    document.addEventListener('keydown', (e) => {
        if (e.key === 'Escape' && mobileMenu?.classList.contains('is-open')) {
            closeMobileMenu();
        }
    });

    // Smooth scrolling for anchor links
    document.querySelectorAll('a[href^="#"]').forEach(anchor => {
        anchor.addEventListener('click', function (e) {
            const href = this.getAttribute('href');
            if (href === '#') return;

            e.preventDefault();
            const target = document.querySelector(href);
            if (target) {
                closeMobileMenu();
                target.scrollIntoView({
                    behavior: 'smooth',
                    block: 'start'
                });
            }
        });
    });
</script>

@stack('scripts')
@stack('json-ld')
</body>
</html>
