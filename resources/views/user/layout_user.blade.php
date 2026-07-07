<!doctype html>
<html lang="{{ $htmlLang ?? 'en' }}" dir="{{ $htmlDir ?? 'ltr' }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, maximum-scale=5">

    <!-- CSRF Token -->
    <meta name="csrf-token" content="{{ csrf_token() }}">

    @include('partials.seo-meta', [
        'seoTitle' => trim($__env->yieldContent('title')) ?: config('branding.seo.default_title'),
    ])

    <!-- CSS -->
    @include('partials.head-core')
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    <link rel="stylesheet" href="{{ asset('css/form-enhancements.css') }}">
    @stack('vendor-styles')

    <style>
        :root {
            --primary-blue: #1976D2;
            --primary-dark: #0A0F2D;
            --secondary-blue: #2196F3;
            --accent-blue: #00D4FF;
            --card-bg: #FFFFFF;
            --sidebar-bg: #1A1F3C;
            --border-color: #E0E0E0;
            --text-primary: #2D3748;
            --text-secondary: #718096;
            --success: #10B981;
            --warning: #F59E0B;
            --danger: #EF4444;
            --info: #3B82F6;
            --light-bg: #F8FAFC;
        }

        /* =============================
           Base Layout
        ============================= */
        body {
            background: var(--light-bg);
            color: var(--text-primary);
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
            overflow-x: hidden;
            min-height: 100vh;
        }

        .vehicle-list-identity { min-width: 0; }
        .vehicle-list-name {
            display: block;
            font-size: 0.92rem;
            font-weight: 700;
            color: var(--text-primary);
            line-height: 1.3;
        }
        .vehicle-list-plate {
            display: block;
            margin-top: 2px;
            font-size: 0.78rem;
            font-weight: 600;
            color: var(--text-secondary);
            letter-spacing: 0.02em;
            line-height: 1.25;
        }

        .content-wrap {
            width: 100%;
            margin: 0;
            padding: 0;
        }

        /* =============================
           Premium Navbar
        ============================= */
        .navbar {
            background: var(--primary-dark) !important;
            border-bottom: 1px solid rgba(255, 255, 255, 0.1);
            box-shadow: 0 4px 20px rgba(0, 0, 0, 0.1);
            padding: 1rem 1.5rem;
            position: sticky;
            top: 0;
            z-index: 1000;
        }

        .navbar-brand {
            color: white;
            font-weight: 700;
            font-size: 1.5rem;
            letter-spacing: -0.5px;
        }

        .navbar-brand .brand-logo-wrap--on-dark {
            padding: 0.35rem 0.65rem;
        }

        .navbar-brand .brand-logo {
            height: 72px;
            filter: none;
            transition: transform 0.3s ease;
        }

        .navbar-brand:hover .brand-logo {
            transform: scale(1.05);
        }

        /* User Profile Circle */
        .user-avatar {
            width: 40px;
            height: 40px;
            background: linear-gradient(135deg, var(--primary-blue), var(--secondary-blue));
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-weight: 600;
            font-size: 1.1rem;
            margin-inline-end: 12px;
            box-shadow: 0 4px 15px rgba(25, 118, 210, 0.3);
            transition: all 0.3s ease;
        }

        .user-avatar:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 25px rgba(25, 118, 210, 0.4);
        }

        /* Sidebar Toggle Button — legacy shell only (Apple HIG uses user-panel-apple.css) */
        body:not(.apple-hig) #toggleSidebar {
            width: 44px;
            height: 44px;
            border-radius: 12px;
            background: var(--primary-blue);
            border: none;
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-size: 1.2rem;
            cursor: pointer;
            margin-inline-end: 15px;
            transition: all 0.3s ease;
            position: relative;
            z-index: 1100; /* Higher than sidebar */
        }

        body:not(.apple-hig) #toggleSidebar:hover {
            background: var(--secondary-blue);
            transform: translateY(-2px);
            box-shadow: 0 8px 20px rgba(25, 118, 210, 0.4);
        }

        /* Logout Button */
        .logout-btn {
            background: linear-gradient(135deg, var(--danger), #DC2626) !important;
            border: none !important;
            border-radius: 10px !important;
            padding: 0.5rem 1.2rem !important;
            color: white !important;
            font-weight: 600 !important;
            transition: all 0.3s ease !important;
            box-shadow: 0 4px 15px rgba(239, 68, 68, 0.3) !important;
        }

        .logout-btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 20px rgba(239, 68, 68, 0.4) !important;
        }

        /* Legacy drawer sidebar (non–Apple HIG pages only) */
        body:not(.apple-hig) .filter-panel#filterPanel.user-dashboard-sidebar {
            width: 380px;
            position: fixed;
            top: 0;
            height: 100vh;
            padding-top: 0;
            background: var(--sidebar-bg);
            padding: 1.5rem;
            overflow-y: auto;
            transition: transform 0.4s cubic-bezier(0.4, 0, 0.2, 1);
            z-index: 1050;
            margin-top: 0 !important;
            box-sizing: border-box;
        }

        html[dir="ltr"] body:not(.apple-hig) .filter-panel#filterPanel.user-dashboard-sidebar,
        body:not(.apple-hig).user-panel-ltr #filterPanel.user-dashboard-sidebar {
            left: 0 !important;
            right: auto !important;
            transform: translateX(-100%);
            border-inline-end: 1px solid rgba(255, 255, 255, 0.1);
            box-shadow: 10px 0 40px rgba(0, 0, 0, 0.3);
        }

        html[dir="rtl"] body:not(.apple-hig) .filter-panel#filterPanel.user-dashboard-sidebar,
        body:not(.apple-hig).user-panel-rtl #filterPanel.user-dashboard-sidebar {
            left: auto !important;
            right: 0 !important;
            transform: translateX(100%);
            border-inline-end: none;
            border-inline-start: 1px solid rgba(255, 255, 255, 0.1);
            box-shadow: -10px 0 40px rgba(0, 0, 0, 0.35);
        }

        html[dir="rtl"] body:not(.apple-hig) .filter-panel#filterPanel.user-dashboard-sidebar.show,
        body:not(.apple-hig).user-panel-rtl #filterPanel.user-dashboard-sidebar.show,
        html[dir="ltr"] body:not(.apple-hig) .filter-panel#filterPanel.user-dashboard-sidebar.show,
        body:not(.apple-hig).user-panel-ltr #filterPanel.user-dashboard-sidebar.show {
            transform: translateX(0) !important;
        }

        /* Sidebar Overlay - FIXED */
        .sidebar-overlay {
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: rgba(0, 0, 0, 0.5);
            z-index: 1048;
            opacity: 0;
            visibility: hidden;
            transition: all 0.3s ease;
        }

        .sidebar-overlay.show {
            opacity: 1;
            visibility: visible;
        }

        /* Sidebar Scrollbar */
        .filter-panel::-webkit-scrollbar {
            width: 6px;
        }

        .filter-panel::-webkit-scrollbar-track {
            background: rgba(255, 255, 255, 0.05);
            border-radius: 10px;
        }

        .filter-panel::-webkit-scrollbar-thumb {
            background: var(--primary-blue);
            border-radius: 10px;
        }

        .filter-panel::-webkit-scrollbar-thumb:hover {
            background: var(--secondary-blue);
        }

        /* Premium Cards */
        .premium-card {
            background: var(--card-bg);
            border: 1px solid var(--border-color);
            border-radius: 16px;
            padding: 1.5rem;
            margin-bottom: 1.5rem;
            transition: all 0.3s ease;
            box-shadow: 0 4px 20px rgba(0, 0, 0, 0.08);
        }

        .premium-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 8px 30px rgba(0, 0, 0, 0.15);
            border-color: var(--primary-blue);
        }

        /* =============================
           Sidebar Header - FIXED
        ============================= */
        .filter-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 2rem;
            padding-bottom: 1rem;
            border-bottom: 1px solid rgba(255, 255, 255, 0.1);
        }

        .filter-header h5 {
            color: white;
            font-weight: 600;
            margin: 0;
        }

        .close-sidebar {
            width: 36px;
            height: 36px;
            border-radius: 8px;
            background: rgba(255, 255, 255, 0.1);
            border: 1px solid rgba(255, 255, 255, 0.2);
            color: white;
            display: flex;
            align-items: center;
            justify-content: center;
            cursor: pointer;
            transition: all 0.3s ease;
            display: none; /* Hide on desktop, show on mobile */
        }

        .close-sidebar:hover {
            background: rgba(239, 68, 68, 0.2);
            transform: rotate(90deg);
        }

        /* Sidebar Content Cards */
        .filter-panel .card {
            background: rgba(255, 255, 255, 0.1);
            border: 1px solid rgba(255, 255, 255, 0.2);
            border-radius: 12px;
            padding: 1rem;
            margin-bottom: 1rem;
            color: white;
        }

        .filter-panel .card h6 {
            color: white;
            font-weight: 600;
        }

        .filter-panel .card p {
            color: rgba(255, 255, 255, 0.8);
            font-size: 0.875rem;
        }

        .filter-panel .btn {
            border-radius: 8px;
            font-weight: 500;
            transition: all 0.3s ease;
        }

        .filter-panel .btn-primary {
            background: var(--primary-blue);
            border: none;
        }

        .filter-panel .btn-secondary {
            background: #6c757d;
            border: none;
        }

        .filter-panel .btn-warning {
            background: #ffc107;
            border: none;
            color: #212529;
        }

        .filter-panel .btn-danger {
            background: var(--danger);
            border: none;
        }

        /* =============================
           Responsive Design
        ============================= */
        @media (max-width: 768px) {
            body:not(.apple-hig) .filter-panel.user-dashboard-sidebar {
                width: min(320px, 88vw);
                max-width: 100vw;
                padding: 1rem;
            }

            body:not(.apple-hig) .navbar {
                padding: 0.8rem 1rem;
            }

            body:not(.apple-hig) .user-avatar {
                width: 36px;
                height: 36px;
                font-size: 1rem;
            }

            body:not(.apple-hig) #toggleSidebar {
                width: 40px;
                height: 40px;
                margin-inline-end: 10px;
            }

            /* Show close button on mobile — legacy shell only */
            body:not(.apple-hig) .close-sidebar {
                display: flex;
            }

        }

        @media (max-width: 576px) {
            body:not(.apple-hig) .filter-panel.user-dashboard-sidebar {
                width: min(280px, 90vw);
            }
        }

        /* Embedded (inside a tracking popup iframe): hide user chrome. */
        body.tracking-embed .navbar,
        body.tracking-embed #filterPanel,
        body.tracking-embed #sidebarOverlay,
        body.tracking-embed .gt-hub-nav { display: none !important; }
        body.tracking-embed .content-wrap { padding: 0.85rem; }

        /* =============================
           Custom Button
        ============================= */
        .btn-premium {
            background: var(--primary-blue);
            border: none;
            color: white;
            border-radius: 12px;
            padding: 0.75rem 1.5rem;
            font-weight: 600;
            transition: all 0.3s ease;
        }

        .btn-premium:hover {
            background: var(--secondary-blue);
            transform: translateY(-2px);
            box-shadow: 0 8px 20px rgba(25, 118, 210, 0.3);
        }

        .btn-outline-premium {
            background: transparent;
            border: 2px solid var(--primary-blue);
            color: var(--primary-blue);
            border-radius: 12px;
            padding: 0.75rem 1.5rem;
            font-weight: 600;
            transition: all 0.3s ease;
        }

        .btn-outline-premium:hover {
            background: var(--primary-blue);
            color: white;
            transform: translateY(-2px);
        }

        /*Sidebar css*/
        /* Sidebar Card Styles */
        .filter-panel .premium-card {
            background: rgba(255, 255, 255, 0.95);
            border: 1px solid rgba(0, 0, 0, 0.08);
            border-radius: 16px;
            padding: 1.25rem;
            margin-bottom: 1rem;
            transition: all 0.3s ease;
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.05);
        }

        .filter-panel .premium-card:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 24px rgba(0, 0, 0, 0.1);
            border-color: rgba(25, 118, 210, 0.3);
        }

        .filter-panel .premium-card h6 {
            color: var(--text-primary);
            font-weight: 600;
            font-size: 1rem;
            margin-bottom: 0.25rem;
        }

        .filter-panel .premium-card .text-muted {
            color: var(--text-secondary) !important;
            font-size: 0.8125rem;
        }

        .filter-panel .btn-premium {
            background: linear-gradient(135deg, var(--primary-blue), var(--secondary-blue));
            border: none;
            color: white;
            border-radius: 10px;
            padding: 0.5rem 1rem;
            font-weight: 500;
            font-size: 0.875rem;
            transition: all 0.3s ease;
        }

        .filter-panel .btn-premium:hover {
            transform: translateY(-2px);
            box-shadow: 0 6px 20px rgba(25, 118, 210, 0.3);
        }

        .filter-panel .btn-outline-premium {
            background: transparent;
            border: 2px solid var(--primary-blue);
            color: var(--primary-blue);
            border-radius: 10px;
            padding: 0.5rem 1rem;
            font-weight: 500;
            font-size: 0.875rem;
            transition: all 0.3s ease;
        }

        .filter-panel .btn-outline-premium:hover {
            background: var(--primary-blue);
            color: white;
            transform: translateY(-2px);
        }

        .filter-panel .btn-danger {
            background: linear-gradient(135deg, #EF4444, #DC2626);
            border: none;
            color: white;
            border-radius: 10px;
            padding: 0.5rem 1rem;
            font-weight: 500;
            font-size: 0.875rem;
            transition: all 0.3s ease;
        }

        .filter-panel .btn-danger:hover {
            transform: translateY(-2px);
            box-shadow: 0 6px 20px rgba(239, 68, 68, 0.3);
        }

        .filter-panel .icon-box-sm {
            width: 40px;
            height: 40px;
            border-radius: 10px;
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .filter-panel .user-info {
            background: rgba(25, 118, 210, 0.05);
            border: 1px solid rgba(25, 118, 210, 0.1);
        }

        .filter-panel .sidebar-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 1.5rem;
            padding-bottom: 1rem;
            border-bottom: 1px solid rgba(0, 0, 0, 0.1);
        }

        .filter-panel .sidebar-header h5 {
            color: var(--text-primary);
            font-weight: 600;
            margin: 0;
        }

        .filter-panel .close-sidebar {
            width: 32px;
            height: 32px;
            border-radius: 8px;
            background: rgba(0, 0, 0, 0.05);
            border: 1px solid rgba(0, 0, 0, 0.1);
            color: var(--text-secondary);
            display: flex;
            align-items: center;
            justify-content: center;
            cursor: pointer;
            transition: all 0.3s ease;
        }

        .filter-panel .close-sidebar:hover {
            background: rgba(239, 68, 68, 0.1);
            color: var(--danger);
            transform: rotate(90deg);
        }

        /* Quick Stats */
        .filter-panel .quick-stats {
            margin-top: 1.5rem;
            padding-top: 1.5rem;
            border-top: 1px solid rgba(0, 0, 0, 0.1);
        }

        /* Responsive adjustments */
        @media (max-width: 768px) {
            .filter-panel .premium-card {
                padding: 1rem;
            }

            .filter-panel .icon-box-sm {
                width: 36px;
                height: 36px;
            }
        }
    </style>

    @include('partials.locale-styles')
    @if(($htmlDir ?? 'ltr') === 'rtl')
        @include('partials.rtl-head')
    @endif
    @stack('styles')
    <link rel="stylesheet" href="{{ asset('css/user-panel-apple.css') }}?v={{ @filemtime(public_path('css/user-panel-apple.css')) }}">
    @if(($htmlDir ?? 'ltr') === 'rtl')
    <style>
        /* Legacy drawer rules — skip Apple HIG shell (handled in user-panel-apple.css) */
        body.user-panel-rtl:not(.apple-hig) #filterPanel.user-dashboard-sidebar {
            left: auto !important;
            right: 0 !important;
        }
        body.user-panel-rtl:not(.apple-hig) #filterPanel.user-dashboard-sidebar:not(.show) {
            transform: translateX(100%) !important;
        }
        body.user-panel-rtl:not(.apple-hig) #filterPanel.user-dashboard-sidebar.show {
            transform: translateX(0) !important;
        }
        body.user-panel-rtl:not(.apple-hig) .filter-header {
            flex-direction: row !important;
            text-align: right;
        }
        body.user-panel-rtl:not(.apple-hig) #filterPanel .card,
        body.user-panel-rtl:not(.apple-hig) #filterPanel .filter-header h5 {
            text-align: right;
        }
        @media (max-width: 768px) {
            body.user-panel-rtl:not(.apple-hig) #filterPanel.user-dashboard-sidebar {
                width: min(320px, 88vw) !important;
                border-radius: 20px 0 0 20px !important;
            }
        }
    </style>
    @endif
</head>

<body class="user-panel apple-hig {{ ($htmlDir ?? 'ltr') === 'rtl' ? 'user-panel-rtl' : 'user-panel-ltr' }} {{ request()->boolean('embed') ? 'tracking-embed' : '' }}" dir="{{ $htmlDir ?? 'ltr' }}" data-map-session-end="{{ route('map.session.end') }}">
<nav class="navbar navbar-expand-lg user-navbar w-100 d-flex align-items-center justify-content-between" dir="{{ $htmlDir ?? 'ltr' }}">
    <div class="nav-start d-flex align-items-center flex-shrink-0">
        <button type="button" id="toggleSidebar" aria-label="{{ __('app.user.nav.menu') }}" aria-expanded="false">
            <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" fill="none" viewBox="0 0 24 24" stroke-width="1.75" stroke="currentColor" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M3.75 6.75h16.5M3.75 12h16.5m-16.5 5.25h16.5"/></svg>
        </button>
        <a class="navbar-brand d-flex align-items-center ms-2 me-0" href="{{ route('user.dashboard') }}" aria-label="{{ __('app.common.dashboard') }}">
            @include('partials.brand-logo', ['size' => 'md', 'onDark' => false])
        </a>
    </div>

    <div class="ud-search d-none d-md-block">
        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.75" stroke="currentColor" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M21 21l-5.197-5.197m0 0A7.5 7.5 0 105.196 5.196a7.5 7.5 0 0010.607 10.607z"/></svg>
        <input type="search" id="udGlobalSearch" data-devices-url="{{ route('user.devices.index') }}" placeholder="{{ __('app.user.devices.search_placeholder') }}" aria-label="{{ __('app.user.devices.search_placeholder') }}">
    </div>

    <div class="nav-end navbar-nav ms-auto d-flex align-items-center gap-2 flex-shrink-0">
        <a href="{{ route('tracking.events.index') }}" class="ud-btn ud-btn--secondary d-none d-sm-inline-flex" style="padding:8px 10px;" title="{{ __('app.tracking.events_nav') }}" aria-label="{{ __('app.tracking.events_nav') }}">
            <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" fill="none" viewBox="0 0 24 24" stroke-width="1.75" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M14.857 17.082a23.848 23.848 0 005.454-1.31A8.967 8.967 0 0118 9.75v-.7V9A6 6 0 006 9v.75a8.967 8.967 0 01-2.312 6.022c1.733.64 3.56 1.085 5.455 1.31m5.714 0a24.255 24.255 0 01-5.714 0m5.714 0a3 3 0 11-5.714 0"/></svg>
        </a>
        @include('partials.language-toggle')
        <a href="{{ route('user.profile') }}" class="d-flex align-items-center text-decoration-none" title="{{ __('app.user.nav.my_profile') }}">
            @include('partials.user-avatar', [
                'user' => auth()->user(),
                'size' => 36,
                'class' => 'user-avatar',
            ])
        </a>
    </div>
</nav>

<div class="ud-app" dir="{{ $htmlDir ?? 'ltr' }}" data-layout-dir="{{ $htmlDir ?? 'ltr' }}">
    <div class="sidebar-overlay" id="sidebarOverlay"></div>
    @include('user.partials.apple-sidebar-shell')
    <div class="content-wrap ud-main" dir="{{ $htmlDir ?? 'ltr' }}">
        @yield('content')
    </div>
</div>

<!-- JS Libraries -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script src="https://code.jquery.com/jquery-3.7.0.min.js"></script>
<script>window.APP_TIMEZONE = @json(config('app.timezone'));</script>
<script src="{{ protected_js('app-datetime.js') }}"></script>
@stack('vendor-scripts')
<script src="{{ protected_js('map-session-guard.js') }}"></script>
@include('partials.session-expired-handler')
@stack('realtime-scripts')

<script src="{{ protected_js('user-panel-apple.js') }}"></script>

@if(($htmlDir ?? 'ltr') === 'rtl')
    <script src="{{ asset('js/admin-rtl.js') }}"></script>
@endif
@include('partials.i18n-js')
@stack('scripts')
@include('partials.rtl-body-end')
@include('partials.client-code-protection')
</body>
</html>
