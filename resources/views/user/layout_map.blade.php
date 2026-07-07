<!doctype html>
<html lang="{{ $htmlLang ?? 'en' }}" dir="{{ $htmlDir ?? 'ltr' }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, maximum-scale=5, viewport-fit=cover">
    <meta name="csrf-token" content="{{ csrf_token() }}">

    @include('partials.seo-meta', [
        'seoTitle' => trim($__env->yieldContent('title')) ?: config('branding.seo.default_title'),
    ])

    @include('partials.head-core')
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/flatpickr/dist/flatpickr.min.css">
    <link rel="stylesheet" href="{{ asset('css/form-enhancements.css') }}">
    <link rel="stylesheet" href="{{ asset('css/user-panel-apple.css') }}?v={{ @filemtime(public_path('css/user-panel-apple.css')) }}">
    <link rel="stylesheet" href="{{ asset('css/user-device-map-shell.css') }}?v={{ @filemtime(public_path('css/user-device-map-shell.css')) }}">

    <style>
        .tracking-container {
            display: flex;
            height: 100%;
            width: 100%;
            overflow: hidden;
            position: relative;
        }

        .map-area {
            position: relative;
            flex: 1 1 auto;
            min-width: 0;
            width: 100%;
            height: 100%;
            transition: margin-inline-start 0.3s ease, margin-inline-end 0.3s ease;
        }

        @media (min-width: 769px) {
            .map-area.sidebar-open,
            body.map-sidebar-open .map-area {
                margin-inline-start: var(--map-sidebar-width);
                margin-left: var(--map-sidebar-width);
                margin-right: 0;
            }

            html[dir="rtl"] .map-area.sidebar-open,
            html[dir="rtl"] body.map-sidebar-open .map-area {
                margin-inline-start: 0;
                margin-inline-end: var(--map-sidebar-width);
                margin-left: 0;
                margin-right: var(--map-sidebar-width);
            }
        }

        #map {
            width: 100%;
            height: 100%;
            outline: none;
        }

        .nav-alerts-wrap { position: relative; }
        .nav-alert-badge {
            position: absolute;
            top: 2px;
            right: 2px;
            min-width: 16px;
            height: 16px;
            padding: 0 4px;
            border-radius: 999px;
            background: var(--apple-red, #ff3b30);
            color: #fff;
            font-size: 0.6rem;
            font-weight: 700;
            line-height: 16px;
            text-align: center;
        }
        .nav-alert-badge[hidden] { display: none !important; }
        .nav-alerts-dropdown {
            position: absolute;
            top: calc(100% + 8px);
            right: 0;
            width: min(320px, calc(100vw - 24px));
            max-height: min(400px, 58vh);
            background: #fff;
            border-radius: 14px;
            box-shadow: 0 12px 40px rgba(0, 0, 0, 0.12);
            border: 1px solid var(--apple-border, #e5e5ea);
            opacity: 0;
            visibility: hidden;
            transform: translateY(-6px);
            transition: opacity 0.2s, transform 0.2s, visibility 0.2s;
            z-index: 1200;
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
            padding: 10px 12px;
            border-bottom: 1px solid var(--apple-border, #e5e5ea);
            background: var(--apple-hover, #f2f2f7);
        }
        .nav-alerts-dropdown__head h6 {
            margin: 0;
            font-size: 0.8125rem;
            font-weight: 600;
        }
        .nav-alerts-dropdown__list {
            overflow-y: auto;
            flex: 1;
            padding: 4px 0;
        }
        .nav-alert-item {
            padding: 8px 12px;
            border-bottom: 1px solid var(--apple-border, #e5e5ea);
        }
        .nav-alert-item:last-child { border-bottom: none; }
        .nav-alert-item strong { display: block; font-size: 0.78rem; margin-bottom: 2px; }
        .nav-alert-item span { display: block; font-size: 0.72rem; color: var(--apple-muted, #6e6e73); }
        .nav-alert-item small { display: block; font-size: 0.65rem; color: #94a3b8; margin-top: 3px; }
        .nav-alerts-empty {
            padding: 20px 12px;
            text-align: center;
            color: var(--apple-muted, #6e6e73);
            font-size: 0.8rem;
        }
        .nav-alerts-dropdown__foot {
            padding: 8px 12px;
            border-top: 1px solid var(--apple-border, #e5e5ea);
            background: var(--apple-hover, #f2f2f7);
            text-align: center;
        }
        .nav-alerts-dropdown__foot a {
            font-size: 0.78rem;
            font-weight: 600;
            color: var(--apple-blue, #007aff);
            text-decoration: none;
        }
        .user-info {
            display: flex;
            align-items: center;
            gap: 8px;
        }
        .user-avatar {
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            color: #fff;
            font-weight: 600;
        }
    </style>

    @include('partials.locale-styles')
    @if(($htmlDir ?? 'ltr') === 'rtl')
        @include('partials.rtl-head')
    @endif
    @stack('styles')
</head>

<body class="user-panel apple-hig map-device-page {{ ($htmlDir ?? 'ltr') === 'rtl' ? 'user-panel-rtl' : 'user-panel-ltr' }}" dir="{{ $htmlDir ?? 'ltr' }}" data-map-session-end="{{ route('map.session.end') }}">

<nav class="navbar navbar-expand-lg map-page-nav d-flex align-items-center justify-content-between w-100" dir="{{ $htmlDir ?? 'ltr' }}">
    <div class="nav-start">
        <button type="button" id="toggleSidebar" aria-label="{{ __('app.map.filters_history') }}" title="{{ __('app.map.filters_history') }}" aria-expanded="false">
            <svg xmlns="http://www.w3.org/2000/svg" width="15" height="15" fill="none" viewBox="0 0 24 24" stroke-width="1.75" stroke="currentColor" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M10.5 6h9.75M10.5 12h9.75m-9.75 5.25h9.75M3.75 6h.007v.008H3.75V6zm0 6h.007v.008H3.75V12zm0 5.25h.007v.008H3.75v-.008z"/></svg>
        </button>
        <div class="map-nav-content">
            <a href="{{ ($isAdminMap ?? false) ? route(request()->routeIs('client.*') ? 'client.locations.index' : 'admin.locations.index') : route('user.devices.index') }}" class="map-nav-logo d-none d-xl-inline-flex" title="{{ ($isAdminMap ?? false) ? __('app.admin.locations.title') : __('app.user.devices.title') }}">
                @include('partials.brand-logo', ['size' => 'md', 'onDark' => false])
            </a>
            <div class="map-nav-device-row">
                <h1 class="map-nav-title">{{ $device->mapDisplayTitle() }}</h1>
                @if($device->mapNavSubtitle())
                    <span class="map-nav-sep d-none d-md-inline" aria-hidden="true">&middot;</span>
                    <small class="map-nav-subtitle d-none d-md-inline">{{ $device->mapNavSubtitle() }}</small>
                @endif
                @if($isAdminMap ?? false)
                    <span class="badge bg-warning text-dark ms-1">{{ __('app.common.admin') }}</span>
                @endif
                <span class="map-nav-sep d-none d-lg-inline" aria-hidden="true">&middot;</span>
                <code class="map-nav-imei d-none d-lg-inline" title="{{ __('app.map.imei_number') }}">{{ $device->imei }}</code>
                @if(($isAdminMap ?? false) && $device->relationLoaded('user') && $device->user)
                    <span class="map-nav-sep d-none d-xl-inline" aria-hidden="true">&middot;</span>
                    <small class="d-none d-xl-inline">{{ $device->user->name }}</small>
                @endif
                <span class="map-nav-status ms-md-auto" id="navLiveStatus">
                    <span class="badge bg-secondary">{{ __('app.common.waiting') }}</span>
                </span>
            </div>
        </div>
    </div>

    <div class="nav-end">
        @include('partials.language-toggle')
        <button type="button" class="nav-map-tour-btn" id="btnMapTour" title="{{ __('app.map.map_tour_title') }}" aria-label="{{ __('app.map.map_tour') }}">
            <i class="fas fa-compass" aria-hidden="true"></i>
        </button>
        <div class="nav-alerts-wrap">
            <button type="button" class="nav-alerts-btn" id="navAlertsBtn" aria-label="{{ __('app.map.alerts') }}" aria-expanded="false" aria-haspopup="true" title="{{ __('app.map.alerts') }}">
                <i class="fas fa-bell" aria-hidden="true"></i>
                <span class="nav-alert-badge" id="navAlertBadge" hidden>0</span>
            </button>
            <div class="nav-alerts-dropdown" id="navAlertsDropdown" role="menu">
                <div class="nav-alerts-dropdown__head">
                    <h6>{{ __('app.map.alerts') }}</h6>
                    <small class="text-muted" id="navAlertsSummary">{{ __('app.map.no_new_alerts') }}</small>
                </div>
                <div class="nav-alerts-dropdown__list" id="navAlertsList">
                    <div class="nav-alerts-empty">{{ __('app.map.no_alerts_yet') }}</div>
                </div>
                <div class="nav-alerts-dropdown__foot">
                    @if($isAdminMap ?? false)
                        <span class="text-muted small">{{ __('app.map.alerts_for_device') }}</span>
                    @else
                        <a href="{{ route('user.alerts.index', ['device_id' => $device->id]) }}" id="navAlertsViewAll">
                            {{ __('app.map.view_all_alerts') }}
                        </a>
                    @endif
                </div>
            </div>
        </div>
        <div class="user-info d-none d-xl-flex">
            @include('partials.user-avatar', [
                'user' => auth()->user(),
                'size' => 26,
                'class' => 'user-avatar',
            ])
            <span class="user-name">{{ auth()->user()->name }}</span>
        </div>
        <form method="POST" action="{{ route('logout') }}">
            @csrf
            <button type="submit" class="logout-btn" title="{{ __('app.common.logout') }}" aria-label="{{ __('app.common.logout') }}">
                <i class="fas fa-power-off" aria-hidden="true"></i>
            </button>
        </form>
    </div>
</nav>

<div class="content-wrap">
    @yield('content')
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script src="https://code.jquery.com/jquery-3.7.0.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/flatpickr"></script>
<script src="{{ protected_js('form-enhancements.js') }}"></script>
<script>window.APP_TIMEZONE = @json(config('app.timezone'));</script>
<script src="{{ protected_js('app-datetime.js') }}"></script>
@include('partials.reverb-echo')
@include('partials.session-expired-handler')
@stack('vendor-scripts')
<script src="{{ protected_js('map-session-guard.js') }}"></script>

@if(($htmlDir ?? 'ltr') === 'rtl')
    <script src="{{ asset('js/admin-rtl.js') }}"></script>
@endif
@include('partials.i18n-js')
@stack('scripts')

<script>
    function syncMapNavHeight() {
        const nav = document.querySelector('.navbar.map-page-nav');
        if (!nav) return;
        const h = Math.ceil(nav.getBoundingClientRect().height);
        document.documentElement.style.setProperty('--app-nav-height', h + 'px');
        document.documentElement.style.setProperty('--ud-map-topbar-h', h + 'px');
        document.body.style.paddingTop = h + 'px';
        if (typeof window.deviceMapResize === 'function') {
            window.deviceMapResize();
        }
    }
    syncMapNavHeight();
    window.syncMapNavHeight = syncMapNavHeight;
    window.addEventListener('resize', syncMapNavHeight);
    window.addEventListener('load', syncMapNavHeight);

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
        }

        if (isDesktop) {
            localStorage.setItem('sidebarState', open ? 'open' : 'closed');
            if (useOffset) {
                const sidebarWidth = getComputedStyle(document.documentElement)
                    .getPropertyValue('--map-sidebar-width')
                    .trim() || '256px';
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

        if (!sidebar || !mapArea) return;

        const isDesktop = window.innerWidth > 768;
        const savedState = localStorage.getItem('sidebarState');

        if (isDesktop) {
            applyMapSidebarState(savedState === 'open');
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
                if (panel && sidebarOpen && !panel.contains(event.target) && btn && !btn.contains(event.target)) {
                    window.toggleMapSidebar();
                }
            });
        }
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', initMapSidebarControls);
    } else {
        initMapSidebarControls();
    }

    window.addEventListener('resize', function () {
        const closeBtn = document.getElementById('closeSidebar');
        if (window.innerWidth > 768) {
            if (closeBtn) closeBtn.style.display = 'none';
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
        syncMapNavHeight();
    });

    setTimeout(function () { window.dispatchEvent(new Event('resize')); }, 100);
</script>

@include('partials.rtl-body-end')
@include('partials.client-code-protection')
</body>
</html>
