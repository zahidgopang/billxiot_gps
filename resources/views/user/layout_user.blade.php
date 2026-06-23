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
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/flatpickr/dist/flatpickr.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/select2-bootstrap-5-theme@1.3.0/dist/select2-bootstrap-5-theme.min.css">
    <link rel="stylesheet" href="{{ asset('css/form-enhancements.css') }}">

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

        /* Sidebar Toggle Button - FIXED POSITION */
        #toggleSidebar {
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

        #toggleSidebar:hover {
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

        /* =============================
           Premium Sidebar - FIXED POSITION
        ============================= */
        .filter-panel#filterPanel.user-dashboard-sidebar {
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

        html[dir="ltr"] .filter-panel#filterPanel.user-dashboard-sidebar,
        body.user-panel-ltr #filterPanel.user-dashboard-sidebar {
            left: 0 !important;
            right: auto !important;
            transform: translateX(-100%);
            border-inline-end: 1px solid rgba(255, 255, 255, 0.1);
            box-shadow: 10px 0 40px rgba(0, 0, 0, 0.3);
        }

        html[dir="rtl"] .filter-panel#filterPanel.user-dashboard-sidebar,
        body.user-panel-rtl #filterPanel.user-dashboard-sidebar {
            left: auto !important;
            right: 0 !important;
            transform: translateX(100%);
            border-inline-end: none;
            border-inline-start: 1px solid rgba(255, 255, 255, 0.1);
            box-shadow: -10px 0 40px rgba(0, 0, 0, 0.35);
        }

        html[dir="rtl"] .filter-panel#filterPanel.user-dashboard-sidebar.show,
        body.user-panel-rtl #filterPanel.user-dashboard-sidebar.show,
        html[dir="ltr"] .filter-panel#filterPanel.user-dashboard-sidebar.show,
        body.user-panel-ltr #filterPanel.user-dashboard-sidebar.show {
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
            .filter-panel.user-dashboard-sidebar {
                width: min(320px, 88vw);
                max-width: 100vw;
                padding: 1rem;
            }

            .navbar {
                padding: 0.8rem 1rem;
            }

            .user-avatar {
                width: 36px;
                height: 36px;
                font-size: 1rem;
            }

            #toggleSidebar {
                width: 40px;
                height: 40px;
                margin-inline-end: 10px;
            }

            /* Show close button on mobile */
            .close-sidebar {
                display: flex;
            }

        }

        @media (max-width: 576px) {
            .filter-panel.user-dashboard-sidebar {
                width: min(280px, 90vw);
            }
        }

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
    @if(($htmlDir ?? 'ltr') === 'rtl')
    <style>
        /* Arabic: user menu drawer — physical right edge (wins over map/global rtl.css) */
        body.user-panel-rtl #filterPanel.user-dashboard-sidebar {
            left: auto !important;
            right: 0 !important;
        }
        body.user-panel-rtl #filterPanel.user-dashboard-sidebar:not(.show) {
            transform: translateX(100%) !important;
        }
        body.user-panel-rtl #filterPanel.user-dashboard-sidebar.show {
            transform: translateX(0) !important;
        }
        body.user-panel-rtl .filter-header {
            flex-direction: row !important;
            text-align: right;
        }
        body.user-panel-rtl #filterPanel .card,
        body.user-panel-rtl #filterPanel .filter-header h5 {
            text-align: right;
        }
        @media (max-width: 768px) {
            body.user-panel-rtl #filterPanel.user-dashboard-sidebar {
                width: min(320px, 88vw) !important;
                border-radius: 20px 0 0 20px !important;
            }
        }
    </style>
    @endif
</head>

<body class="user-panel {{ ($htmlDir ?? 'ltr') === 'rtl' ? 'user-panel-rtl' : 'user-panel-ltr' }}" dir="{{ $htmlDir ?? 'ltr' }}" data-map-session-end="{{ route('map.session.end') }}">
<!-- Premium Navbar -->
<nav class="navbar navbar-expand-lg px-3 user-navbar w-100 d-flex align-items-center justify-content-between">
    <div class="nav-start d-flex align-items-center">
        <!-- Sidebar Toggle - FIXED Z-INDEX -->
        <button type="button" id="toggleSidebar" aria-label="{{ __('app.user.nav.menu') }}" aria-expanded="false">
            <i class="fa fa-sliders-h" aria-hidden="true"></i>
        </button>

        <!-- Brand Logo -->
        <a class="navbar-brand d-flex align-items-center" href="{{ route('user.dashboard') }}" aria-label="{{ __('app.common.dashboard') }}">
            @include('partials.brand-logo', ['size' => 'lg', 'onDark' => true])
        </a>
    </div>

    <div class="nav-end navbar-nav ms-auto d-flex align-items-center gap-2">
        @include('partials.language-toggle')
        <!-- User Info -->
        <div class="nav-item d-flex align-items-center">
            @include('partials.user-avatar', [
                'user' => auth()->user(),
                'size' => 36,
                'class' => 'user-avatar',
            ])
            <span class="nav-link d-none d-md-block" style="color: white;">{{ auth()->user()->name }}</span>
        </div>

        <div class="nav-item">
            <form method="POST" action="{{ route('logout') }}">
                @csrf
                <button type="submit" class="btn logout-btn">
                    <i class="fa fa-power-off me-2" aria-hidden="true"></i>
                    <span class="d-none d-md-inline">{{ __('app.common.logout') }}</span>
                </button>
            </form>
        </div>
    </div>
</nav>

<!-- Sidebar (fixed to viewport — outside content-wrap for correct RTL) -->
<div class="sidebar-overlay" id="sidebarOverlay"></div>
<div class="filter-panel user-dashboard-sidebar" id="filterPanel" role="dialog" aria-modal="true" aria-hidden="true">
        <!-- Sidebar Header with Close Button -->
        <div class="filter-header d-flex align-items-center justify-content-between">
            <h5 class="mb-0">
                <i class="fa fa-user-circle text-primary me-2"></i> {{ __('app.user.nav.menu') }}
            </h5>
            <div class="close-sidebar" id="closeSidebar">
                <i class="fa fa-times"></i>
            </div>
        </div>

        <!-- Dashboard -->
        <div class="card p-3 mb-3">
            <h6 class="mb-2">{{ __('app.common.dashboard') }}</h6>
            <p class="small text-muted" style="color: rgb(178 178 178 / 75%) !important;">{{ __('app.forms.go_dashboard_desc') }}</p>

            <a href="{{ route('user.dashboard') }}" class="btn btn-primary btn-sm w-100">
                {{ __('app.forms.open_dashboard') }}
            </a>
        </div>

        <!-- My Devices -->
        <div class="card p-3 mb-3">
            <h6 class="mb-2">{{ __('app.user.devices.title') }}</h6>
            <p class="small text-muted" style="color: rgb(178 178 178 / 75%) !important;">{{ __('app.forms.manage_devices_desc') }}</p>

            <a href="{{ route('user.devices.index') }}" class="btn btn-secondary btn-sm w-100">
                {{ __('app.forms.view_devices') }}
            </a>
        </div>

        <!-- Profile -->
        <div class="card p-3 mb-3">
            <h6 class="mb-2">{{ __('app.user.nav.my_profile') }}</h6>
            <p class="mb-1"><strong>{{ __('app.forms.name') }}:</strong> {{ auth()->user()->name }}</p>
            <p class="mb-1"><strong>{{ __('app.forms.email') }}:</strong> <span class="admin-ltr" dir="ltr">{{ auth()->user()->email }}</span></p>

            <a href="{{ route('user.profile') }}" class="btn btn-primary btn-sm w-100 mt-2">
                {{ __('app.forms.view_edit_profile') }}
            </a>
        </div>

        <!-- Password -->
        <div class="card p-3 mb-3">
            <h6 class="mb-2">{{ __('app.user.nav.security') }}</h6>
            <p class="small text-muted" style="color: rgb(178 178 178 / 75%) !important;">{{ __('app.forms.security_settings_desc') }}</p>

            <a href="{{ route('user.change.password') }}" class="btn btn-warning btn-sm w-100">
                {{ __('app.user.nav.change_password') }}
            </a>
        </div>

        <!-- Logout -->
        <div class="card p-3 mb-3">
            <h6 class="mb-2">{{ __('app.user.nav.manage_account') }}</h6>

            <form method="POST" action="{{ route('logout') }}">
                @csrf
                <button class="btn btn-danger btn-sm w-100">
                    <i class="fa fa-power-off me-1"></i> {{ __('app.common.logout') }}
                </button>
            </form>
        </div>
</div>

<!-- Page Content -->
<div class="content-wrap">
    @yield('content')
</div>

<!-- JS Libraries -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script src="https://code.jquery.com/jquery-3.7.0.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/flatpickr"></script>
<script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>
<script src="{{ protected_js('app-datetime.js') }}"></script>
<script src="{{ protected_js('form-enhancements.js') }}"></script>
<script src="{{ protected_js('map-session-guard.js') }}"></script>
<script src="https://js.pusher.com/8.2/pusher.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/laravel-echo/1.15.0/echo.iife.js"></script>

<script>
    // Fixed Sidebar Toggle - Single Click Functionality
    document.addEventListener('DOMContentLoaded', function() {
        const toggleBtn = document.getElementById('toggleSidebar');
        const sidebar = document.getElementById('filterPanel');
        const overlay = document.getElementById('sidebarOverlay');
        const closeBtn = document.getElementById('closeSidebar');

        let isSidebarOpen = false;
        let clickTimer = null;

        function isUserPanelRtl() {
            return document.documentElement.getAttribute('dir') === 'rtl'
                || document.body.getAttribute('dir') === 'rtl'
                || document.body.classList.contains('user-panel-rtl');
        }

        function applyUserSidebarPosition() {
            if (!sidebar) return;
            const rtl = isUserPanelRtl();
            sidebar.style.insetInlineStart = '';
            sidebar.style.insetInlineEnd = '';
            if (rtl) {
                sidebar.style.left = 'auto';
                sidebar.style.right = '0';
                sidebar.style.transform = sidebar.classList.contains('show')
                    ? 'translateX(0)'
                    : 'translateX(100%)';
            } else {
                sidebar.style.left = '0';
                sidebar.style.right = 'auto';
                sidebar.style.transform = sidebar.classList.contains('show')
                    ? 'translateX(0)'
                    : 'translateX(-100%)';
            }
        }

        function openSidebar() {
            isSidebarOpen = true;
            sidebar.classList.add('show');
            applyUserSidebarPosition();
            overlay.classList.add('show');
            document.body.classList.add('user-sidebar-open');
            sidebar.setAttribute('aria-hidden', 'false');
            document.body.style.overflow = 'hidden';
            toggleBtn.setAttribute('aria-expanded', 'true');

            const icon = toggleBtn.querySelector('i');
            icon.classList.remove('fa-sliders-h');
            icon.classList.add('fa-times');
        }

        function closeSidebar() {
            isSidebarOpen = false;
            sidebar.classList.remove('show');
            applyUserSidebarPosition();
            overlay.classList.remove('show');
            document.body.classList.remove('user-sidebar-open');
            sidebar.setAttribute('aria-hidden', 'true');
            document.body.style.overflow = '';
            toggleBtn.setAttribute('aria-expanded', 'false');

            const icon = toggleBtn.querySelector('i');
            icon.classList.remove('fa-times');
            icon.classList.add('fa-sliders-h');
        }

        function toggleSidebar() {
            if (isSidebarOpen) {
                closeSidebar();
            } else {
                openSidebar();
            }
        }

        // Single click event with debouncing
        toggleBtn.addEventListener('click', function(e) {
            e.preventDefault();
            e.stopPropagation();

            // Clear any existing timer
            if (clickTimer) {
                clearTimeout(clickTimer);
            }

            // Set new timer to handle the click
            clickTimer = setTimeout(() => {
                toggleSidebar();
                clickTimer = null;
            }, 50); // Small delay to prevent double-click issues
        });

        // Close sidebar when clicking overlay
        overlay.addEventListener('click', function(e) {
            if (isSidebarOpen) {
                closeSidebar();
            }
        });

        // Close sidebar when clicking close button
        if (closeBtn) {
            closeBtn.addEventListener('click', function(e) {
                e.preventDefault();
                e.stopPropagation();
                closeSidebar();
            });
        }

        // Close sidebar on escape key
        document.addEventListener('keydown', function(e) {
            if (e.key === 'Escape' && isSidebarOpen) {
                closeSidebar();
            }
        });

        // Close sidebar when clicking outside on mobile
        document.addEventListener('click', function(e) {
            if (isSidebarOpen && window.innerWidth <= 768) {
                if (!sidebar.contains(e.target) && e.target !== toggleBtn && !toggleBtn.contains(e.target)) {
                    closeSidebar();
                }
            }
        });

        // Close sidebar on window resize (for responsive behavior)
        let resizeTimer;
        window.addEventListener('resize', function() {
            clearTimeout(resizeTimer);
            resizeTimer = setTimeout(function() {
                if (window.innerWidth > 768 && isSidebarOpen) {
                    closeSidebar();
                }
            }, 250);
        });

        applyUserSidebarPosition();

        // Initialize - ensure sidebar is closed on load
        closeSidebar();

        // Add hover effects to cards
        document.querySelectorAll('.premium-card').forEach(card => {
            card.addEventListener('mouseenter', function() {
                this.style.transform = 'translateY(-5px)';
            });

            card.addEventListener('mouseleave', function() {
                this.style.transform = 'translateY(0)';
            });
        });
    });
</script>

@include('user._pusher')
@if(($htmlDir ?? 'ltr') === 'rtl')
    <script src="{{ asset('js/admin-rtl.js') }}"></script>
@endif
@include('partials.i18n-js')
@stack('scripts')
@include('partials.rtl-body-end')
@include('partials.client-code-protection')
</body>
</html>
