<!doctype html>
@php
    $trackingPanel = $panel ?? 'admin';
    $dashboardRoute = $trackingPanel . '.dashboard';
    $isEmbed = request()->boolean('embed');
@endphp
<html lang="{{ $htmlLang ?? 'en' }}" dir="{{ $htmlDir ?? 'ltr' }}" class="theme-dark">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, maximum-scale=5, viewport-fit=cover">
    <meta name="csrf-token" content="{{ csrf_token() }}">

    @include('partials.seo-meta', [
        'seoTitle' => trim($__env->yieldContent('title')) ?: (__('app.tracking.hub_nav') . ' — ' . config('branding.name')),
        'seoDescription' => config('branding.seo.admin_description'),
        'seoKeywords' => config('branding.seo.admin_keywords'),
    ])

    @include('partials.head-core')
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/select2-bootstrap-5-theme@1.3.0/dist/select2-bootstrap-5-theme.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/flatpickr/dist/flatpickr.min.css">
    <link rel="stylesheet" href="{{ asset('css/form-enhancements.css') }}">
    @include('partials.locale-styles')
    @if(($htmlDir ?? 'ltr') === 'rtl')
        @include('partials.rtl-head')
    @endif
    @stack('styles')

    <style>
        :root {
            --tracking-topbar-height: 64px;
            --tracking-primary: #1976D2;
            --tracking-dark: #0A0F2D;
        }

        body.tracking-shell {
            margin: 0;
            background: #f8fafc;
            color: #1f2937;
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
            min-height: 100vh;
            overflow-x: hidden;
        }

        .tracking-topbar {
            height: var(--tracking-topbar-height);
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 1rem;
            padding: 0 1rem;
            background: var(--tracking-dark);
            color: #fff;
            position: sticky;
            top: 0;
            z-index: 1050;
            box-shadow: 0 2px 10px rgba(10, 15, 45, 0.25);
        }

        .tracking-topbar__brand {
            display: flex;
            align-items: center;
            gap: 0.75rem;
            color: #fff;
            text-decoration: none;
            font-weight: 700;
            min-width: 0;
        }

        .tracking-topbar__brand img,
        .tracking-topbar__brand svg {
            max-height: 34px;
            width: auto;
        }

        .tracking-topbar__title {
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }

        .tracking-topbar__actions {
            display: inline-flex;
            align-items: center;
            gap: 0.6rem;
        }

        .tracking-topbar__alert-controls {
            display: inline-flex;
            align-items: center;
            gap: 0.35rem;
        }

        .tracking-topbar__alert-btn {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            width: 38px;
            height: 38px;
            border: 1px solid rgba(255, 255, 255, 0.28);
            border-radius: 8px;
            background: rgba(255, 255, 255, 0.1);
            color: rgba(255, 255, 255, 0.88);
            font-size: 1rem;
            cursor: pointer;
            transition: background 0.15s ease, color 0.15s ease, border-color 0.15s ease;
        }

        .tracking-topbar__alert-btn:hover {
            background: rgba(255, 255, 255, 0.2);
            border-color: rgba(255, 255, 255, 0.45);
            color: #fff;
        }

        .tracking-topbar__alert-btn.on {
            background: rgba(25, 118, 210, 0.35);
            border-color: rgba(255, 255, 255, 0.45);
            color: #fff;
        }

        .tracking-topbar__alert-btn.muted {
            color: rgba(255, 255, 255, 0.55);
        }

        .tracking-back-btn {
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            padding: 0.5rem 1rem;
            border-radius: 8px;
            background: rgba(255, 255, 255, 0.12);
            border: 1px solid rgba(255, 255, 255, 0.25);
            color: #fff;
            text-decoration: none;
            font-weight: 600;
            font-size: 0.875rem;
            transition: background 0.2s ease, border-color 0.2s ease;
            white-space: nowrap;
        }

        .tracking-back-btn:hover,
        .tracking-back-btn:focus-visible {
            background: rgba(255, 255, 255, 0.22);
            border-color: rgba(255, 255, 255, 0.45);
            color: #fff;
        }

        html[dir="rtl"] .tracking-back-btn i { transform: scaleX(-1); }

        .content-wrap {
            padding: 1.5rem;
        }

        @media (max-width: 576px) {
            .tracking-topbar__title span { display: none; }
            .tracking-back-btn span { display: none; }
        }

        /* Embedded (inside a popup iframe): hide chrome so only the module content shows. */
        body.tracking-embed .tracking-topbar { display: none !important; }
        body.tracking-embed .content-wrap { padding: 0.85rem; }
        body.tracking-embed .gt-hub-nav { display: none !important; }
        body.tracking-embed .gt-module-page > h1:first-child { display: none; }
    </style>
</head>
<body class="tracking-shell {{ $isEmbed ? 'tracking-embed' : '' }}" data-map-session-end="{{ route('map.session.end') }}" data-admin-locale="{{ app()->getLocale() }}">
    @unless($isEmbed)
    <header class="tracking-topbar">
        <a href="{{ Route::has($dashboardRoute) ? route($dashboardRoute) : url('/') }}" class="tracking-topbar__brand" aria-label="{{ __('app.common.dashboard') }}">
            @include('partials.brand-logo', ['onDark' => true])
            <span class="tracking-topbar__title">
                <i class="fas fa-satellite-dish"></i><span>{{ __('app.tracking.hub_nav') }}</span>
            </span>
        </a>

        <div class="tracking-topbar__actions">
            @stack('tracking-topbar-actions')
            @include('partials.language-toggle')
            <a href="{{ Route::has($dashboardRoute) ? route($dashboardRoute) : url('/') }}" class="tracking-back-btn">
                <i class="fas fa-arrow-left"></i><span>{{ __('app.tracking.back_to_management') }}</span>
            </a>
        </div>
    </header>
    @endunless

    <main class="content-wrap">
        @yield('content')
    </main>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://code.jquery.com/jquery-3.7.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/flatpickr"></script>
    @if(($htmlDir ?? 'ltr') === 'rtl')
        <script src="{{ asset('js/admin-rtl.js') }}"></script>
    @endif
    <script>window.APP_TIMEZONE = @json(config('app.timezone'));</script>
    <script src="{{ protected_js('app-datetime.js') }}"></script>
    <script src="{{ protected_js('form-enhancements.js') }}"></script>
    <script src="{{ protected_js('map-session-guard.js') }}"></script>
    @include('partials.session-expired-handler')
    @include('partials.reverb-echo')

    @include('partials.i18n-js')

    @stack('scripts')

    @include('partials.rtl-body-end')
    @include('partials.client-code-protection')
</body>
</html>
