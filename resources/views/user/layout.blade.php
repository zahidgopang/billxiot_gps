<!doctype html>
<html lang="{{ $htmlLang ?? 'en' }}" dir="{{ $htmlDir ?? 'ltr' }}" class="theme-dark">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, maximum-scale=5, viewport-fit=cover">

    <!-- CSRF Token -->
    <meta name="csrf-token" content="{{ csrf_token() }}">

    @include('partials.seo-meta', [
        'seoTitle' => trim($__env->yieldContent('title')) ?: config('branding.seo.default_title'),
    ])

    <!-- CSS -->
    @include('partials.head-core')
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/flatpickr/dist/flatpickr.min.css">
    <link rel="stylesheet" href="{{ asset('css/form-enhancements.css') }}">

    <style>
        :root {
            --map-primary: #1976D2;
            --map-dark: #0A0F2D;
            --map-sidebar: #1A1F3C;
            --map-card: #FFFFFF;
            --map-border: #E0E0E0;
            --map-text: #2D3748;
            --map-text-light: #718096;
            --map-success: #10B981;
            --map-warning: #F59E0B;
            --map-danger: #EF4444;
            --map-info: #3B82F6;
            --map-bg: #F8FAFC;
        }

        /* =============================
           Base Layout - FIXED
        ============================= */
        body {
            background: var(--map-dark);
            overflow: hidden; /* Prevent body scroll */
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
            margin: 0;
            padding: 0;
            height: 100vh;
            padding-top: var(--app-nav-height, 204px);
        }

        .content-wrap {
            width: 100%;
            margin: 0;
            padding: 0;
            height: calc(100vh - var(--app-nav-height, 204px));
            overflow-y: auto; /* Allow content scrolling if needed */
        }

        /* =============================
           Premium Tracking Container
        ============================= */
        .tracking-container {
            display: flex;
            height: 100%;
            width: 100%;
            overflow: hidden;
            position: relative;
        }

        /* =============================
           Premium Navbar (Fixed)
        ============================= */

        .navbar {
            background: var(--map-dark) !important;
            border-bottom: 1px solid rgba(255, 255, 255, 0.1);
            box-shadow: 0 4px 20px rgba(0, 0, 0, 0.2);
            padding: 1rem 1.5rem;
            min-height: var(--app-nav-height, 204px);
            height: auto;
            z-index: 1000;
            position: fixed; /* Keep it fixed */
            top: 0;
            left: 0;
            right: 0;
        }

        /* =============================
           Premium Tracking Container
        ============================= */
        .tracking-container {
            display: flex;
            height: 100%;
            width: 100%;
            overflow: hidden;
            position: relative;
        }

        .navbar-brand {
            color: white;
            font-weight: 700;
            font-size: 1.5rem;
            display: flex;
            align-items: center;
            gap: 0.75rem;
        }

        .navbar-brand:hover .brand-logo,
        .map-nav-logo:hover .brand-logo {
            transform: scale(1.02);
            transition: transform 0.2s ease;
        }

        /* Sidebar Toggle Button */
        #toggleSidebar {
            width: 44px;
            height: 44px;
            flex-shrink: 0;
            border-radius: 12px;
            background: var(--map-primary);
            border: none;
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-size: 1.1rem;
            cursor: pointer;
            transition: all 0.3s ease;
            box-shadow: 0 4px 15px rgba(25, 118, 210, 0.3);
            position: relative;
            z-index: 1100;
            pointer-events: auto;
        }

        #toggleSidebar:hover {
            background: #1565C0;
            transform: translateY(-2px);
            box-shadow: 0 8px 25px rgba(25, 118, 210, 0.4);
        }

        /* Map page header */
        .map-page-nav {
            padding: 0.5rem 1rem !important;
            gap: 0.75rem;
        }
        .map-page-nav .nav-start {
            display: flex;
            align-items: center;
            gap: 12px;
            min-width: 0;
            flex: 1;
        }
        .map-page-nav .nav-end {
            display: flex;
            align-items: center;
            gap: 10px;
            flex-shrink: 0;
        }
        .map-nav-content {
            display: flex;
            align-items: center;
            gap: 10px;
            min-width: 0;
            flex: 1;
        }
        .map-nav-logo {
            flex-shrink: 0;
            display: flex;
            align-items: center;
            line-height: 0;
        }
        .map-nav-logo .brand-logo {
            opacity: 0.98;
        }
        .map-nav-device-row {
            display: flex;
            align-items: center;
            gap: 8px;
            min-width: 0;
            flex: 1;
            flex-wrap: nowrap;
        }
        .map-nav-title {
            margin: 0;
            font-size: 1rem;
            font-weight: 700;
            color: #fff;
            line-height: 1.2;
            white-space: nowrap;
            flex-shrink: 0;
        }
        .map-nav-sep {
            color: rgba(255, 255, 255, 0.35);
            font-size: 0.85rem;
            flex-shrink: 0;
        }
        .map-nav-imei {
            font-size: 0.72rem;
            font-family: ui-monospace, SFMono-Regular, Menlo, Monaco, Consolas, monospace;
            color: rgba(255, 255, 255, 0.65);
            background: rgba(255, 255, 255, 0.08);
            padding: 3px 8px;
            border-radius: 6px;
            border: 1px solid rgba(255, 255, 255, 0.1);
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
            min-width: 0;
            max-width: min(200px, 38vw);
        }
        .map-nav-status {
            flex-shrink: 0;
            margin-left: auto;
        }
        .map-nav-status .badge {
            font-size: 0.65rem;
            font-weight: 600;
            padding: 0.3em 0.55em;
            letter-spacing: 0.02em;
        }

        .nav-map-tour-btn {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            height: 44px;
            padding: 0 14px;
            margin-right: 8px;
            border-radius: 12px;
            border: 1px solid rgba(25, 118, 210, 0.45);
            background: rgba(25, 118, 210, 0.2);
            color: #fff;
            font-size: 0.8rem;
            font-weight: 600;
            cursor: pointer;
            transition: background 0.2s, border-color 0.2s, transform 0.2s;
        }
        .nav-map-tour-btn:hover {
            background: rgba(25, 118, 210, 0.35);
            border-color: rgba(66, 165, 245, 0.7);
            transform: translateY(-1px);
        }
        @media (max-width: 576px) {
            .nav-map-tour-btn { padding: 0; width: 44px; justify-content: center; }
        }

        /* Navbar alerts bell */
        .nav-alerts-wrap {
            position: relative;
        }
        .nav-alerts-btn {
            width: 44px;
            height: 44px;
            border-radius: 12px;
            border: 1px solid rgba(255, 255, 255, 0.12);
            background: rgba(255, 255, 255, 0.08);
            color: #fff;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.1rem;
            cursor: pointer;
            position: relative;
            transition: background 0.2s, border-color 0.2s, transform 0.2s;
        }
        .nav-alerts-btn:hover,
        .nav-alerts-btn[aria-expanded="true"] {
            background: rgba(255, 255, 255, 0.14);
            border-color: rgba(255, 255, 255, 0.22);
        }
        .nav-alerts-btn.has-unread {
            border-color: rgba(245, 158, 11, 0.5);
            background: rgba(245, 158, 11, 0.12);
        }
        .nav-alerts-btn.has-unread i {
            animation: bell-ring 0.6s ease;
        }
        @keyframes bell-ring {
            0%, 100% { transform: rotate(0); }
            25% { transform: rotate(12deg); }
            75% { transform: rotate(-12deg); }
        }
        .nav-alert-badge {
            position: absolute;
            top: 4px;
            right: 4px;
            min-width: 18px;
            height: 18px;
            padding: 0 5px;
            border-radius: 999px;
            background: var(--map-danger);
            color: #fff;
            font-size: 0.65rem;
            font-weight: 700;
            line-height: 18px;
            text-align: center;
            box-shadow: 0 2px 8px rgba(239, 68, 68, 0.5);
        }
        .nav-alert-badge[hidden] {
            display: none !important;
        }
        .nav-alerts-dropdown {
            position: absolute;
            top: calc(100% + 10px);
            right: 0;
            width: min(340px, calc(100vw - 24px));
            max-height: min(420px, 60vh);
            background: #fff;
            border-radius: 14px;
            box-shadow: 0 16px 48px rgba(15, 23, 42, 0.22);
            border: 1px solid #e2e8f0;
            opacity: 0;
            visibility: hidden;
            transform: translateY(-8px);
            transition: opacity 0.2s, transform 0.2s, visibility 0.2s;
            z-index: 1100;
            overflow: hidden;
            display: flex;
            flex-direction: column;
        }
        .nav-alerts-dropdown.show {
            opacity: 1;
            visibility: visible;
            transform: translateY(0);
        }
        .nav-alerts-dropdown__head {
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 12px 14px;
            border-bottom: 1px solid #f1f5f9;
            background: #f8fafc;
        }
        .nav-alerts-dropdown__head h6 {
            margin: 0;
            font-size: 0.9rem;
            font-weight: 700;
            color: #0f172a;
        }
        .nav-alerts-dropdown__list {
            overflow-y: auto;
            flex: 1;
            padding: 6px 0;
        }
        .nav-alert-item {
            padding: 10px 14px;
            border-bottom: 1px solid #f1f5f9;
            cursor: default;
        }
        .nav-alert-item:last-child { border-bottom: none; }
        .nav-alert-item strong {
            display: block;
            font-size: 0.82rem;
            color: #0f172a;
            margin-bottom: 2px;
        }
        .nav-alert-item span {
            display: block;
            font-size: 0.75rem;
            color: #64748b;
            line-height: 1.35;
        }
        .nav-alert-item small {
            display: block;
            font-size: 0.68rem;
            color: #94a3b8;
            margin-top: 4px;
        }
        .nav-alert-item--error strong { color: #dc2626; }
        .nav-alert-item--warning strong { color: #d97706; }
        .nav-alert-item--geofence strong { color: #2563eb; }
        .nav-alert-detail {
            display: block;
            font-size: 0.78rem;
            color: #475569;
            margin-top: 4px;
        }
        .nav-alert-detail i { margin-inline-end: 4px; color: #3b82f6; }
        .nav-alerts-empty {
            padding: 24px 14px;
            text-align: center;
            color: #94a3b8;
            font-size: 0.85rem;
        }
        .nav-alerts-dropdown__foot {
            padding: 10px 14px;
            border-top: 1px solid #f1f5f9;
            background: #f8fafc;
            text-align: center;
        }
        .nav-alerts-dropdown__foot a {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 6px;
            font-size: 0.82rem;
            font-weight: 600;
            color: var(--map-primary);
            text-decoration: none;
        }
        .nav-alerts-dropdown__foot a:hover {
            text-decoration: underline;
        }

        /* User Info */
        .user-info {
            display: flex;
            align-items: center;
            gap: 0.75rem;
        }

        .user-avatar {
            width: 36px;
            height: 36px;
            background: linear-gradient(135deg, var(--map-primary), #2196F3);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-weight: 600;
            font-size: 0.875rem;
            box-shadow: 0 4px 15px rgba(25, 118, 210, 0.3);
        }

        .user-name {
            color: white;
            font-weight: 500;
            font-size: 0.9375rem;
        }

        /* {{ __('app.common.logout') }} Button */
        .logout-btn {
            width: 44px;
            height: 44px;
            border-radius: 12px;
            background: linear-gradient(135deg, var(--map-danger), #DC2626);
            border: none;
            color: white;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.2rem;
            transition: all 0.3s ease;
            box-shadow: 0 4px 15px rgba(239, 68, 68, 0.3);
        }

        .logout-btn:hover {
            background: #DC2626;
            transform: translateY(-2px);
            box-shadow: 0 8px 25px rgba(239, 68, 68, 0.4);
        }

        /* Device Info */
        .device-info {
            display: flex;
            align-items: center;
            gap: 8px;
            padding: 6px 12px;
            background: rgba(255, 255, 255, 0.1);
            border-radius: 8px;
        }

        .device-icon {
            width: 30px;
            height: 30px;
            background: rgba(25, 118, 210, 0.2);
            border-radius: 6px;
            display: flex;
            align-items: center;
            justify-content: center;
            color: var(--map-primary);
        }

        .device-name {
            color: white;
            font-weight: 500;
        }

        /* =============================
           Map Area
        ============================= */
        .tracking-container {
            --map-sidebar-width: 340px;
        }

        .map-area {
            position: relative;
            flex: 1 1 auto;
            min-width: 0;
            width: 100%;
            height: 100%; /* Full height of container */
            margin-inline-start: 0;
            margin-inline-end: 0;
            transition: margin-inline-start 0.35s ease, margin-inline-end 0.35s ease;
        }

        /* Offset map beside fixed sidebar when open (desktop) */
        @media (min-width: 769px) {
            .map-area.sidebar-open,
            body.map-sidebar-open .map-area {
                margin-inline-start: var(--map-sidebar-width);
                margin-inline-end: 0;
                margin-left: var(--map-sidebar-width);
                margin-right: 0;
            }

            html[dir="rtl"] .map-area.sidebar-open,
            html[dir="rtl"] body.map-sidebar-open .map-area {
                margin-inline-start: 0;
                margin-inline-end: 0;
                margin-left: 0;
                margin-right: var(--map-sidebar-width);
            }
        }

        #map {
            width: 100%;
            height: 100%;
            outline: none; /* Prevent focus outline */
        }

        /* =============================
           Responsive
        ============================= */
        @media (max-width: 768px) {
            body {
                padding-top: 70px;
            }

            .map-area.sidebar-open {
                margin-inline-start: 0;
                margin-inline-end: 0;
            }

            /* Ensure map fills screen on mobile */
            .tracking-container {
                height: calc(100vh - 70px);
            }
        }
        /* =============================
           Responsive
        ============================= */
        @media (max-width: 768px) {
            .navbar {
                padding: 0.75rem 1rem;
            }

            .navbar-brand {
                font-size: 1.25rem;
            }

            #toggleSidebar {
                width: 40px;
                height: 40px;
                margin-right: 10px;
                font-size: 1rem;
            }

            .user-name {
                display: none;
            }

            .device-info {
                display: none;
            }

            .logout-btn {
                width: 40px;
                height: 40px;
            }

            .map-area.sidebar-open {
                margin-inline-start: 0;
                margin-inline-end: 0;
            }
        }

        @media (max-width: 576px) {
            .navbar {
                padding: 0.5rem;
            }

            .navbar-brand span {
                font-size: 1.1rem;
            }

            #toggleSidebar {
                width: 36px;
                height: 36px;
            }

            .map-nav-title {
                font-size: 0.9rem;
            }

            .map-nav-imei {
                max-width: 120px;
                font-size: 0.65rem;
            }
        }

        /* Arabic: map filter drawer docks on the physical right */
        html[dir="rtl"] .filter-panel#filterPanel {
            left: auto !important;
            right: 0 !important;
        }

        html[dir="rtl"] .map-page-nav > .nav-start {
            flex-direction: row;
        }

        html[dir="rtl"] .map-page-nav > .nav-start #toggleSidebar {
            order: 1;
            margin-inline-start: 0;
            margin-inline-end: 12px;
        }

        html[dir="rtl"] .map-page-nav > .nav-start .map-nav-content {
            order: 2;
            flex-direction: row;
        }
    </style>
    @include('partials.locale-styles')
    @if(($htmlDir ?? 'ltr') === 'rtl')
        @include('partials.rtl-head')
    @endif
    @stack('styles')
</head>

<body>

<!-- Premium Navbar -->
<nav class="navbar navbar-expand-lg d-flex align-items-center justify-content-between w-100 @if(isset($device) && $device) map-page-nav @endif">
    <div class="nav-start">
        @if(isset($device) && $device)
            <button type="button" id="toggleSidebar" aria-label="{{ __('app.map.filters_history') }}" title="{{ __('app.map.filters_history') }}">
                <i class="fas fa-sliders-h"></i>
            </button>
            <div class="map-nav-content">
                <a href="{{ ($isAdminMap ?? false) ? route(request()->routeIs('client.*') ? 'client.locations.index' : 'admin.locations.index') : route('user.dashboard') }}" class="map-nav-logo" title="{{ ($isAdminMap ?? false) ? __('app.admin.locations.title') : __('app.common.dashboard') }}">
                    @include('partials.brand-logo', ['size' => 'lg', 'onDark' => true])
                </a>
                <div class="map-nav-device-row">
                    <h1 class="map-nav-title">{{ $device->mapDisplayTitle() }}</h1>
                    @if($device->mapNavSubtitle())
                        <span class="map-nav-sep d-none d-md-inline" aria-hidden="true">&middot;</span>
                        <small class="text-muted map-nav-subtitle d-block d-md-inline" style="font-size:0.75rem;">{{ $device->mapNavSubtitle() }}</small>
                    @endif
                    @if($isAdminMap ?? false)
                        <span class="badge bg-warning text-dark ms-1" style="font-size:0.65rem;vertical-align:middle;">{{ __('app.common.admin') }}</span>
                    @endif
                    <span class="map-nav-sep" aria-hidden="true">&middot;</span>
                    <code class="map-nav-imei" title="{{ __('app.map.imei_number') }}">{{ $device->imei }}</code>
                    @if(($isAdminMap ?? false) && $device->relationLoaded('user') && $device->user)
                        <span class="map-nav-sep" aria-hidden="true">&middot;</span>
                        <small class="text-muted" style="font-size:0.75rem;">{{ $device->user->name }}</small>
                    @endif
                    <span class="map-nav-status" id="navLiveStatus">
                        <span class="badge bg-secondary">{{ __('app.common.waiting') }}</span>
                    </span>
                </div>
            </div>
        @else
            <button type="button" id="toggleSidebar" aria-label="{{ __('app.forms.toggle_sidebar') }}">
                <i class="fas fa-sliders-h"></i>
            </button>
            <a class="navbar-brand" href="{{ route('user.dashboard') }}" aria-label="{{ __('app.common.dashboard') }}">
                @include('partials.brand-logo', ['size' => 'lg', 'onDark' => true])
            </a>
        @endif
    </div>

    <div class="nav-end">
        @include('partials.language-toggle')
        @if(isset($device) && $device)
            <button type="button" class="nav-map-tour-btn" id="btnMapTour" title="{{ __('app.map.map_tour_title') }}" aria-label="{{ __('app.map.map_tour') }}">
                <i class="fas fa-compass"></i>
                <span class="d-none d-lg-inline">{{ __('app.map.map_tour') }}</span>
            </button>
            <div class="nav-alerts-wrap">
                <button type="button" class="nav-alerts-btn" id="navAlertsBtn" aria-label="{{ __('app.map.alerts') }}" aria-expanded="false" aria-haspopup="true" title="{{ __('app.map.alerts') }}">
                    <i class="fas fa-bell"></i>
                    <span class="nav-alert-badge" id="navAlertBadge" hidden>0</span>
                </button>
                <div class="nav-alerts-dropdown" id="navAlertsDropdown" role="menu">
                    <div class="nav-alerts-dropdown__head">
                        <h6><i class="fas fa-bell me-2 text-warning"></i>{{ __('app.map.alerts') }}</h6>
                        <small class="text-muted" id="navAlertsSummary">{{ __('app.map.no_new_alerts') }}</small>
                    </div>
                    <div class="nav-alerts-dropdown__list" id="navAlertsList">
                        <div class="nav-alerts-empty">{{ __('app.map.no_alerts_yet') }}</div>
                    </div>
                    <div class="nav-alerts-dropdown__foot">
                        @if($isAdminMap ?? false)
                            <span class="text-muted small">{{ __('app.map.alerts_for_device') }}</span>
                        @else
                            <a href="{{ route('user.alerts.index', isset($device) && $device ? ['device_id' => $device->id] : []) }}" id="navAlertsViewAll">
                                {{ __('app.map.view_all_alerts') }} <i class="fas fa-arrow-right"></i>
                            </a>
                        @endif
                    </div>
                </div>
            </div>
        @endif

        <!-- User Info -->
        <div class="user-info">
            <div class="user-avatar">
                {{ strtoupper(substr(auth()->user()->name, 0, 1)) }}
            </div>
            <span class="user-name d-none d-md-block">{{ auth()->user()->name }}</span>
        </div>

        <!-- {{ __('app.common.logout') }} -->
        <form method="POST" action="{{ route('logout') }}">
            @csrf
            <button type="submit" class="logout-btn" title="{{ __('app.common.logout') }}">
                <i class="fas fa-power-off"></i>
            </button>
        </form>
    </div>
</nav>

<!-- Page Content -->
<div class="content-wrap">
    @yield('content')
</div>

<!-- JS Libraries -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script src="https://code.jquery.com/jquery-3.7.0.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/flatpickr"></script>
<script src="{{ protected_js('form-enhancements.js') }}"></script>
<script src="https://js.pusher.com/8.2/pusher.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/laravel-echo/1.15.0/echo.iife.js"></script>

@include('user._pusher')

@if(($htmlDir ?? 'ltr') === 'rtl')
    <script src="{{ asset('js/admin-rtl.js') }}"></script>
@endif
@include('partials.i18n-js')
@stack('scripts')
<script>
    // Global sidebar state (exposed for map-tour and other scripts)
    let sidebarOpen = false;
    window.isMapSidebarOpen = function () { return sidebarOpen; };

    function applyMapSidebarState(open) {
        const sidebar = document.getElementById('filterPanel');
        const mapArea = document.querySelector('.map-area');
        const toggleBtn = document.getElementById('toggleSidebar');
        const isDesktop = window.innerWidth > 768;
        const useOffset = open && isDesktop;

        if (!sidebar || !mapArea) return;

        sidebar.classList.toggle('show', open);
        mapArea.classList.toggle('sidebar-open', useOffset);
        document.body.classList.toggle('map-sidebar-open', useOffset);
        sidebarOpen = open;

        mapArea.style.marginLeft = '';
        mapArea.style.marginRight = '';
        mapArea.style.width = '';

        if (toggleBtn) {
            toggleBtn.setAttribute('aria-expanded', open ? 'true' : 'false');
            const icon = toggleBtn.querySelector('i');
            if (icon) {
                icon.classList.toggle('fa-sliders-h', !open);
                icon.classList.toggle('fa-times', open);
            }
        }

        if (isDesktop) {
            localStorage.setItem('sidebarState', open ? 'open' : 'closed');
            if (useOffset) {
                const sidebarWidth = getComputedStyle(document.documentElement)
                    .getPropertyValue('--map-sidebar-width')
                    .trim() || '380px';
                const isRtl = document.documentElement.getAttribute('dir') === 'rtl';
                if (isRtl) {
                    mapArea.style.marginRight = sidebarWidth;
                    mapArea.style.marginLeft = '0';
                } else {
                    mapArea.style.marginLeft = sidebarWidth;
                    mapArea.style.marginRight = '0';
                }
            }
        }

        window.dispatchEvent(new Event('map-sidebar-toggled'));
        if (typeof window.deviceMapResize === 'function') {
            window.deviceMapResize();
        }
    }

    window.applyMapSidebarState = applyMapSidebarState;
    window.toggleMapSidebar = function () {
        applyMapSidebarState(!sidebarOpen);
    };

    function initMapSidebarControls() {
        const toggleBtn = document.getElementById('toggleSidebar');
        const sidebar = document.getElementById('filterPanel');
        const mapArea = document.querySelector('.map-area');
        const closeBtn = document.getElementById('closeSidebar');

        if (!sidebar || !mapArea) {
            return;
        }

        const isDesktop = window.innerWidth > 768;
        const savedState = localStorage.getItem('sidebarState');

        if (isDesktop) {
            const shouldOpen = savedState === 'open';
            applyMapSidebarState(shouldOpen);
        } else {
            applyMapSidebarState(false);
        }

        if (toggleBtn && toggleBtn.dataset.mapSidebarBound !== '1') {
            toggleBtn.dataset.mapSidebarBound = '1';
            toggleBtn.addEventListener('click', function (e) {
                e.preventDefault();
                e.stopPropagation();
                window.toggleMapSidebar();
            });
        }

        if (closeBtn && closeBtn.dataset.mapSidebarBound !== '1') {
            closeBtn.dataset.mapSidebarBound = '1';
            closeBtn.addEventListener('click', function (e) {
                e.preventDefault();
                e.stopPropagation();
                window.toggleMapSidebar();
            });
        }

        if (!window._mapSidebarOutsideClickBound && window.innerWidth <= 768) {
            window._mapSidebarOutsideClickBound = true;
            document.addEventListener('click', function (event) {
                const panel = document.getElementById('filterPanel');
                const btn = document.getElementById('toggleSidebar');

                if (panel && sidebarOpen &&
                    !panel.contains(event.target) &&
                    btn && !btn.contains(event.target)) {
                    window.toggleMapSidebar();
                }
            });
        }

        if (typeof flatpickr !== 'undefined' && document.getElementById('dateRange') && !document.getElementById('dateRange')._flatpickr) {
            flatpickr('#dateRange', {
                mode: 'range',
                dateFormat: 'Y-m-d',
                maxDate: 'today',
            });
        }
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', initMapSidebarControls);
    } else {
        initMapSidebarControls();
    }

    // Handle window resize
    window.addEventListener('resize', function() {
        const sidebar = document.getElementById('filterPanel');
        const mapArea = document.querySelector('.map-area');
        const closeBtn = document.getElementById('closeSidebar');
        const toggleBtn = document.getElementById('toggleSidebar');

        if (window.innerWidth > 768) {
            // Desktop
            if (closeBtn) closeBtn.style.display = 'none';

            // Restore saved state on desktop
            const savedState = localStorage.getItem('sidebarState');
            const shouldOpen = savedState === 'open';

            if (shouldOpen !== sidebarOpen) {
                applyMapSidebarState(shouldOpen);
            }
        } else {
            if (closeBtn) closeBtn.style.display = 'flex';
            if (sidebarOpen) {
                applyMapSidebarState(false);
            }
        }
    });

    // Trigger initial resize
    setTimeout(() => window.dispatchEvent(new Event('resize')), 100);
</script>

@include('partials.rtl-body-end')
@include('partials.client-code-protection')

</body>
</html>
