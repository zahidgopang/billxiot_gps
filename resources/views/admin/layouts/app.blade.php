<!doctype html>
<html lang="{{ $htmlLang ?? 'en' }}" dir="{{ $htmlDir ?? 'ltr' }}" class="theme-dark">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, maximum-scale=5, viewport-fit=cover">

    <!-- CSRF Token -->
    <meta name="csrf-token" content="{{ csrf_token() }}">

    @include('partials.seo-meta', [
        'seoTitle' => trim($__env->yieldContent('title')) ?: 'Admin — '.config('branding.name'),
        'seoDescription' => config('branding.seo.admin_description'),
        'seoKeywords' => config('branding.seo.admin_keywords'),
    ])

    <!-- ========== CORE CSS ========== -->
    @include('partials.head-core')
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css">

    <!-- DataTables CSS -->
    <link rel="stylesheet" href="https://cdn.datatables.net/1.13.6/css/dataTables.bootstrap5.min.css">
    <link rel="stylesheet" href="https://cdn.datatables.net/responsive/2.5.0/css/responsive.bootstrap5.min.css">

    <!-- Chart.js -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.min.css">

    <!-- Select2 + Flatpickr -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/select2-bootstrap-5-theme@1.3.0/dist/select2-bootstrap-5-theme.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/flatpickr/dist/flatpickr.min.css">
    <link rel="stylesheet" href="{{ asset('css/form-enhancements.css') }}">
    <link rel="stylesheet" href="{{ asset('css/admin-list-filters.css') }}">
    <link rel="stylesheet" href="{{ asset('css/admin-forms.css') }}">
    @include('partials.locale-styles')
    @if(($htmlDir ?? 'ltr') === 'rtl')
        @include('partials.rtl-head')
    @endif
    @stack('styles')

    <style>
        :root {
            --admin-primary: #1976D2;
            --admin-dark: #0A0F2D;
            --admin-sidebar-width: 260px;
            --admin-sidebar: #1A1F3C;
            --admin-card: #FFFFFF;
            --admin-border: #E0E0E0;
            --admin-text: #2D3748;
            --admin-text-light: #718096;
            --admin-success: #10B981;
            --admin-warning: #F59E0B;
            --admin-danger: #EF4444;
            --admin-info: #3B82F6;
            --admin-bg: #F8FAFC;
        }

        /* =============================
           Base Layout
        ============================= */
        body {
            background: var(--admin-bg);
            color: var(--admin-text);
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
            overflow-x: hidden;
            min-height: 100vh;
        }

        /* Main column (navbar + page) — kept separate from fixed sidebar */
        .admin-main {
            position: relative;
            min-width: 0;
            width: 100%;
            max-width: 100%;
            margin: 0;
            overflow-x: hidden;
            transition: width 0.3s ease, margin 0.3s ease;
        }

        /* Bootstrap modals must sit above sidebar (1100) and backdrop */
        .modal {
            z-index: 1200 !important;
        }

        .modal-backdrop {
            z-index: 1190 !important;
        }

        .flatpickr-calendar {
            z-index: 1210 !important;
        }

        .admin-main .container-fluid {
            max-width: 100%;
        }

        .content-wrap {
            padding: 1.5rem;
        }

        /* Desktop: shrink main column when sidebar is open */
        @media (min-width: 769px) {
            body.admin-panel.admin-sidebar-open .admin-main {
                width: calc(100% - var(--admin-sidebar-width));
                max-width: calc(100% - var(--admin-sidebar-width));
                margin-left: var(--admin-sidebar-width);
                margin-right: 0;
            }

            html[dir="rtl"] body.admin-panel.admin-sidebar-open .admin-main {
                margin-left: 0;
                margin-right: var(--admin-sidebar-width);
            }
        }

        @media (max-width: 768px) {
            .content-wrap {
                padding: 1rem;
            }

            body.admin-panel.admin-sidebar-open .admin-main {
                width: 100%;
                max-width: 100%;
                margin-left: 0;
                margin-right: 0;
            }
        }

        /* =============================
           Premium Sidebar
        ============================= */
        .admin-sidebar {
            width: var(--admin-sidebar-width);
            min-height: 100vh;
            max-width: min(var(--admin-sidebar-width), 100vw);
            position: fixed;
            top: 0;
            left: 0;
            right: auto;
            background: var(--admin-sidebar);
            border-inline-end: 1px solid rgba(255, 255, 255, 0.1);
            box-shadow: 4px 0 24px rgba(0, 0, 0, 0.2);
            z-index: 1100;
            transition: transform 0.3s ease;
            overflow-x: hidden;
            overflow-y: auto;
            display: flex;
            flex-direction: column;
            box-sizing: border-box;
        }

        /* Closed: slide off-screen (LTR = left, RTL rule below = right) */
        body.admin-panel .admin-sidebar:not(.is-open) {
            transform: translateX(-100%);
        }

        /* RTL: dock right, slide off-screen when closed */
        html[dir="rtl"] body.admin-panel .admin-sidebar {
            left: auto;
            right: 0;
            border-inline-end: none;
            border-inline-start: 1px solid rgba(255, 255, 255, 0.1);
            box-shadow: -4px 0 24px rgba(0, 0, 0, 0.2);
        }

        html[dir="rtl"] body.admin-panel .admin-sidebar:not(.is-open) {
            transform: translateX(100%);
        }

        /* Open state must beat RTL/LTR hidden rules */
        body.admin-panel .admin-sidebar.is-open {
            transform: translateX(0) !important;
        }

        .sidebar-overlay {
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: rgba(0, 0, 0, 0.5);
            z-index: 999;
            display: none;
        }

        .sidebar-overlay.show {
            display: block;
        }

        /* Bootstrap modals must sit above sidebar (1100) and backdrop */
        .modal-backdrop {
            z-index: 1190 !important;
        }

        .modal {
            z-index: 1200 !important;
        }

        .flatpickr-calendar {
            z-index: 1210 !important;
        }

        /* Sidebar Header */
        .sidebar-header {
            padding: 1.5rem;
            border-bottom: 1px solid rgba(255, 255, 255, 0.1);
            display: flex;
            align-items: center;
            justify-content: space-between;
        }

        .sidebar-brand {
            color: white;
            text-decoration: none;
        }

        /* Sidebar Links */
        .sidebar-nav {
            flex: 1 1 auto;
            min-height: 0;
            padding: 1rem 0;
            overflow-x: hidden;
            overflow-y: auto;
        }

        .admin-sidebar .nav-link-premium span,
        .admin-sidebar .nav-group-title,
        .admin-sidebar .sidebar-brand span {
            overflow-wrap: anywhere;
            word-break: break-word;
        }

        .admin-sidebar .sidebar-footer {
            flex-shrink: 0;
            overflow-wrap: anywhere;
        }

        .nav-group {
            margin-bottom: 1rem;
        }

        .nav-group-title {
            padding: 0.5rem 1.5rem;
            color: rgba(255, 255, 255, 0.6);
            font-size: 0.75rem;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            font-weight: 600;
        }

        .nav-link-premium {
            display: flex;
            align-items: center;
            gap: 0.75rem;
            min-width: 0;
            padding: 0.75rem 1.5rem;
            color: rgba(255, 255, 255, 0.8);
            text-decoration: none;
            transition: all 0.3s ease;
            border-inline-start: 3px solid transparent;
            position: relative;
            overflow: hidden;
        }

        .nav-link-premium:hover {
            background: rgba(255, 255, 255, 0.1);
            color: white;
            border-inline-start-color: var(--admin-primary);
            padding-inline-start: 1.75rem;
        }

        .nav-link-premium.active {
            background: rgba(25, 118, 210, 0.2);
            color: white;
            border-inline-start-color: var(--admin-primary);
        }

        .nav-link-premium i {
            width: 20px;
            flex-shrink: 0;
            text-align: center;
            font-size: 1.125rem;
        }

        .nav-link-premium span {
            flex: 1;
            min-width: 0;
        }

        .nav-badge {
            margin-inline-start: auto;
            background: var(--admin-primary);
            color: white;
            font-size: 0.6875rem;
            padding: 0.125rem 0.5rem;
            border-radius: 10px;
            min-width: 20px;
            text-align: center;
        }

        /* =============================
           Premium Navbar
        ============================= */
        .admin-navbar {
            background: var(--admin-card);
            border-bottom: 1px solid var(--admin-border);
            box-shadow: 0 4px 20px rgba(0, 0, 0, 0.08);
            padding: 0.875rem 1.5rem;
            position: sticky;
            top: 0;
            z-index: 900;
        }

        .navbar-content {
            display: flex;
            align-items: center;
            justify-content: space-between;
        }

        /* Left Side: Toggle & Breadcrumb */
        .navbar-left {
            display: flex;
            align-items: center;
            gap: 1rem;
        }

        .sidebar-toggle {
            width: 40px;
            height: 40px;
            border-radius: 10px;
            background: var(--admin-primary);
            border: none;
            color: white;
            display: flex;
            align-items: center;
            justify-content: center;
            cursor: pointer;
            transition: all 0.3s ease;
        }

        .sidebar-toggle:hover {
            background: #1565C0;
            transform: rotate(15deg);
        }

        .breadcrumb-premium {
            display: flex;
            align-items: center;
            gap: 0.5rem;
            list-style: none;
            padding: 0;
            margin: 0;
        }

        .breadcrumb-item {
            color: var(--admin-text-light);
            font-size: 0.875rem;
        }

        .breadcrumb-item.active {
            color: var(--admin-text);
            font-weight: 500;
        }

        .breadcrumb-divider {
            color: var(--admin-text-light);
        }

        /* Right Side: User Menu & Notifications */
        .navbar-right {
            display: flex;
            align-items: center;
            gap: 1rem;
        }

        .notification-badge {
            position: relative;
        }

        .notification-btn {
            width: 40px;
            height: 40px;
            border-radius: 10px;
            background: transparent;
            border: 1px solid var(--admin-border);
            color: var(--admin-text-light);
            display: flex;
            align-items: center;
            justify-content: center;
            cursor: pointer;
            transition: all 0.3s ease;
            position: relative;
        }

        .notification-btn:hover {
            background: var(--admin-bg);
            color: var(--admin-primary);
        }

        .notification-dot {
            position: absolute;
            top: 5px;
            inset-inline-end: 5px;
            width: 8px;
            height: 8px;
            background: var(--admin-danger);
            border-radius: 50%;
            border: 2px solid var(--admin-card);
        }

        .user-menu {
            display: flex;
            align-items: center;
            gap: 0.75rem;
            cursor: pointer;
        }

        .user-avatar {
            width: 40px;
            height: 40px;
            background: linear-gradient(135deg, var(--admin-primary), #2196F3);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-weight: 600;
            font-size: 1rem;
            box-shadow: 0 4px 15px rgba(25, 118, 210, 0.3);
        }

        .user-info {
            display: flex;
            flex-direction: column;
        }

        .user-name {
            font-weight: 600;
            color: var(--admin-text);
            font-size: 0.9375rem;
        }

        .user-role {
            font-size: 0.75rem;
            color: var(--admin-text-light);
        }

        /* =============================
           Premium Cards
        ============================= */
        .premium-card {
            background: var(--admin-card);
            border: 1px solid var(--admin-border);
            border-radius: 16px;
            padding: 1.5rem;
            margin-bottom: 1.5rem;
            transition: all 0.3s ease;
            box-shadow: 0 4px 20px rgba(0, 0, 0, 0.05);
        }

        .premium-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 8px 30px rgba(0, 0, 0, 0.1);
            border-color: var(--admin-primary);
        }

        .premium-card .card-header {
            background: transparent;
            border-bottom: 1px solid var(--admin-border);
            padding: 0 0 1rem 0;
            margin-bottom: 1rem;
        }

        .premium-card .card-title {
            color: var(--admin-text);
            font-weight: 600;
            font-size: 1.125rem;
            margin: 0;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        .stats-card {
            text-align: center;
            padding: 1.5rem 1rem;
        }

        .stats-icon {
            width: 56px;
            height: 56px;
            border-radius: 14px;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 1rem;
            font-size: 1.5rem;
            color: white;
        }

        .stats-value {
            font-size: 2rem;
            font-weight: 700;
            color: var(--admin-text);
            margin-bottom: 0.25rem;
        }

        .stats-label {
            color: var(--admin-text-light);
            font-size: 0.875rem;
            margin-bottom: 0.5rem;
        }

        .stats-change {
            font-size: 0.75rem;
            font-weight: 500;
        }

        .stats-change.positive {
            color: var(--admin-success);
        }

        .stats-change.negative {
            color: var(--admin-danger);
        }

        /* =============================
           Tables
        ============================= */
        .table-premium {
            --bs-table-bg: transparent;
            --bs-table-striped-bg: rgba(0, 0, 0, 0.02);
            --bs-table-hover-bg: rgba(25, 118, 210, 0.04);
            border-color: var(--admin-border);
        }

        .table-premium thead th {
            background-color: var(--admin-bg);
            border-bottom: 2px solid var(--admin-border);
            color: var(--admin-text);
            font-weight: 600;
            padding: 1rem;
        }

        .table-premium tbody td {
            padding: 1rem;
            vertical-align: middle;
            border-color: var(--admin-border);
        }

        .table-actions {
            display: flex;
            gap: 0.5rem;
        }

        .btn-table {
            width: 32px;
            height: 32px;
            border-radius: 8px;
            display: flex;
            align-items: center;
            justify-content: center;
            border: none;
            transition: all 0.3s ease;
        }

        .btn-table-view {
            background: rgba(25, 118, 210, 0.1);
            color: var(--admin-primary);
        }

        .btn-table-edit {
            background: rgba(16, 185, 129, 0.1);
            color: var(--admin-success);
        }

        .btn-table-delete {
            background: rgba(239, 68, 68, 0.1);
            color: var(--admin-danger);
        }

        .btn-table:hover {
            transform: translateY(-2px);
        }

        /* =============================
           Badges
        ============================= */
        .badge-premium {
            padding: 0.25rem 0.75rem;
            border-radius: 20px;
            font-size: 0.75rem;
            font-weight: 500;
        }

        .badge-online {
            background: rgba(16, 185, 129, 0.1);
            color: var(--admin-success);
            border: 1px solid rgba(16, 185, 129, 0.2);
        }

        .badge-offline {
            background: rgba(107, 114, 128, 0.1);
            color: #6B7280;
            border: 1px solid rgba(107, 114, 128, 0.2);
        }

        .badge-warning {
            background: rgba(245, 158, 11, 0.1);
            color: var(--admin-warning);
            border: 1px solid rgba(245, 158, 11, 0.2);
        }

        /* =============================
           Pagination (Bootstrap 5)
        ============================= */
        .admin-pagination .pagination {
            margin-bottom: 0;
            flex-wrap: wrap;
        }

        .admin-pagination .page-link {
            color: var(--admin-primary);
            border-color: var(--admin-border);
            background: var(--admin-card);
        }

        .admin-pagination .page-item.active .page-link {
            background: var(--admin-primary);
            border-color: var(--admin-primary);
            color: #fff;
        }

        .admin-pagination .page-item.disabled .page-link {
            color: var(--admin-text-light);
            background: var(--admin-bg);
        }

        /* SweetAlert2 modals above Select2 */
        .swal2-container {
            z-index: 10050 !important;
        }

        /* Hide Select2 while Bootstrap flash toast is visible */
        body.admin-flash-visible .select2-container,
        body.admin-flash-visible .select2-dropdown {
            visibility: hidden !important;
            pointer-events: none !important;
        }

        #adminToastContainer .toast {
            min-width: min(320px, calc(100vw - 2rem));
        }

        #adminToastContainer .toast-body {
            flex: 1;
        }

        /* =============================
           Footer
        ============================= */
        .footer-premium {
            margin-top: 2rem;
            padding-top: 1.5rem;
            border-top: 1px solid var(--admin-border);
            color: var(--admin-text-light);
            font-size: 0.875rem;
        }

        /* =============================
           Responsive
        ============================= */
        @media (max-width: 768px) {
            .sidebar-brand span {
                display: none;
            }

            .sidebar-header {
                padding: 1rem;
                justify-content: center;
            }

            .user-info {
                display: none;
            }

            .stats-card {
                padding: 1rem;
            }

            .stats-value {
                font-size: 1.75rem;
            }

            .table-actions {
                flex-direction: column;
                gap: 0.25rem;
            }
        }

        @media (max-width: 576px) {
            .content-wrap {
                padding: 0.75rem;
            }

            .premium-card {
                padding: 1rem;
            }

            .navbar-content {
                flex-direction: column;
                gap: 1rem;
                align-items: flex-start;
            }

            .navbar-right {
                width: 100%;
                justify-content: space-between;
            }
        }
    </style>
</head>

<body class="admin-panel admin-sidebar-open" data-map-session-end="{{ route('map.session.end') }}" data-admin-locale="{{ app()->getLocale() }}">
<!-- Sidebar Overlay -->
<div class="sidebar-overlay" id="sidebarOverlay"></div>

<!-- Premium Sidebar (fixed; outside main column) -->
@include('admin.partials.sidebar')

<div class="admin-main">
@include('admin.partials.navbar')

<!-- Main Content -->
<main class="content-wrap">
    @yield('content')

    <!-- Footer -->
    <footer class="footer-premium">
        <div class="container-fluid">
            <div class="row align-items-center justify-content-between">
                <div class="col-md-6">
                    © {{ date('Y') }} {{ __('app.brand') }} • {{ __('app.admin.footer') }}
                </div>
                <div class="col-md-6 text-md-end">
                    <span class="text-muted">{{ __('app.common.last_updated') }}: <x-admin.ltr>{{ app_datetime_format(now()) }}</x-admin.ltr></span>
                </div>
            </div>
        </div>
    </footer>
</main>
</div>

<!-- Core JS -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script src="https://code.jquery.com/jquery-3.7.0.min.js"></script>

<!-- DataTables -->
<script src="https://cdn.datatables.net/1.13.6/js/jquery.dataTables.min.js"></script>
<script src="https://cdn.datatables.net/1.13.6/js/dataTables.bootstrap5.min.js"></script>
<script src="https://cdn.datatables.net/responsive/2.5.0/js/dataTables.responsive.min.js"></script>
<script src="https://cdn.datatables.net/responsive/2.5.0/js/responsive.bootstrap5.min.js"></script>

<!-- Chart.js -->
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>

<!-- SweetAlert2 -->
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

<!-- Select2 + Flatpickr -->
<script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/flatpickr"></script>
@if(($htmlDir ?? 'ltr') === 'rtl')
    <script src="{{ asset('js/admin-rtl.js') }}"></script>
@endif
<script src="{{ protected_js('app-datetime.js') }}"></script>
<script src="{{ protected_js('form-enhancements.js') }}"></script>
<script src="{{ protected_js('map-session-guard.js') }}"></script>

@include('partials.i18n-js')

{{-- Move modals to <body> before page scripts (fixes blur/backdrop blocking clicks) --}}
<script>
    (function () {
        document.querySelectorAll('.modal').forEach(function (modalEl) {
            if (modalEl.parentElement !== document.body) {
                document.body.appendChild(modalEl);
            }
        });
    })();
</script>

@stack('scripts')

<script>
    document.addEventListener('DOMContentLoaded', function() {
        const sidebar = document.getElementById('adminSidebar');
        const overlay = document.getElementById('sidebarOverlay');
        const toggleBtn = document.getElementById('sidebarToggle');
        function isMobile() {
            return window.innerWidth <= 768;
        }

        function setSidebarOpen(open) {
            if (!sidebar) return;
            const useOffset = open && !isMobile();
            sidebar.classList.toggle('is-open', open);
            document.body.classList.toggle('admin-sidebar-open', useOffset);
            if (overlay) {
                overlay.classList.toggle('show', open && isMobile());
            }
            document.body.style.overflow = (open && isMobile()) ? 'hidden' : '';

            if (toggleBtn) {
                toggleBtn.setAttribute('aria-expanded', open ? 'true' : 'false');
                const icon = toggleBtn.querySelector('i');
                if (icon) {
                    icon.classList.toggle('fa-bars', !open);
                    icon.classList.toggle('fa-times', open);
                }
            }
        }

        function toggleSidebar() {
            setSidebarOpen(!sidebar.classList.contains('is-open'));
        }

        function syncSidebarForViewport() {
            if (!sidebar) return;
            if (isMobile()) {
                setSidebarOpen(false);
            } else {
                setSidebarOpen(true);
            }
        }

        if (toggleBtn) {
            toggleBtn.addEventListener('click', function(e) {
                e.preventDefault();
                e.stopPropagation();
                toggleSidebar();
            });
        }

        if (overlay) {
            overlay.addEventListener('click', function() {
                setSidebarOpen(false);
            });
        }

        document.addEventListener('keydown', function(e) {
            if (e.key === 'Escape' && sidebar && sidebar.classList.contains('is-open') && isMobile()) {
                setSidebarOpen(false);
            }
        });

        window.addEventListener('resize', syncSidebarForViewport);
        syncSidebarForViewport();

        // Initialize DataTables (English; Arabic uses admin-rtl.js)
        if ($.fn.DataTable && document.documentElement.getAttribute('dir') !== 'rtl') {
            $('.data-table').DataTable({
                responsive: true,
                pageLength: 25,
                language: {
                    search: '_INPUT_',
                    searchPlaceholder: @json(__('app.forms.search_placeholder_datatable')),
                    lengthMenu: '_MENU_ ' + @json(__('app.forms.records_per_page')),
                },
            });
        } else if (typeof window.adminInitDataTablesRtl === 'function') {
            window.adminInitDataTablesRtl();
        }

        // Flash toasts — after Select2 init; Bootstrap toast (not Swal)
        @if(session('success') || session('error'))
        window.setTimeout(function () {
            @if(session('success'))
            if (typeof window.adminFlashToast === 'function') {
                window.adminFlashToast('success', @json(session('success')));
            }
            @endif
            @if(session('error'))
            if (typeof window.adminFlashToast === 'function') {
                window.adminFlashToast('error', @json(session('error')));
            }
            @endif
        }, 50);
        @endif
    });
</script>
@include('partials.rtl-body-end')
@include('partials.client-code-protection')
</body>
</html>
