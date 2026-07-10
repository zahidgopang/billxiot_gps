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

    @include('tracking.partials.design-tokens')
    <style>
        body.tracking-shell {
            margin: 0;
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
            background: var(--apple-bg-primary);
            color: var(--apple-label);
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            z-index: 1050;
            box-shadow: 0 0.5px 0 rgba(0, 0, 0, 0.04);
            border-bottom: 0.5px solid var(--apple-separator);
        }

        .tracking-topbar__brand {
            display: flex;
            align-items: center;
            gap: 0.65rem;
            color: var(--apple-label);
            text-decoration: none;
            font-weight: 600;
            font-size: 0.9375rem;
            letter-spacing: -0.022em;
            min-width: 0;
        }

        .tracking-topbar__brand img,
        .tracking-topbar__brand svg {
            max-height: 28px;
            width: auto;
        }

        .tracking-topbar__title {
            display: inline-flex;
            align-items: center;
            gap: 0.45rem;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }

        .tracking-topbar__title i {
            color: #007aff;
            font-size: 0.8125rem;
        }

        .tracking-topbar__title span {
            font-weight: 600;
            font-size: 0.875rem;
            letter-spacing: -0.018em;
        }

        .tracking-topbar__actions {
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
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
            width: 32px;
            height: 32px;
            border: none;
            border-radius: 8px;
            background: var(--apple-fill);
            color: #3a3a3c;
            font-size: 0.8125rem;
            cursor: pointer;
            transition: background 0.2s ease, transform 0.15s ease;
        }

        .tracking-topbar__alert-btn:hover {
            background: rgba(118, 118, 128, 0.18);
            color: #1d1d1f;
        }

        .tracking-topbar__alert-btn.on {
            background: rgba(0, 122, 255, 0.14);
            color: #007aff;
        }

        .tracking-topbar__alert-btn.muted {
            color: #aeaeb2;
        }

        .tracking-back-btn {
            display: inline-flex;
            align-items: center;
            gap: 0.35rem;
            padding: 0.35rem 0.7rem;
            border-radius: 8px;
            background: var(--apple-fill);
            border: none;
            color: #007aff;
            text-decoration: none;
            font-weight: 510;
            font-size: 0.8125rem;
            letter-spacing: -0.018em;
            transition: background 0.2s ease;
            white-space: nowrap;
        }

        .tracking-back-btn:hover,
        .tracking-back-btn:focus-visible {
            background: rgba(0, 122, 255, 0.1);
            color: #0062cc;
        }

        html[dir="rtl"] .tracking-back-btn i { transform: scaleX(-1); }

        .tracking-topbar .lang-toggle {
            background: var(--apple-bg-secondary);
            border-radius: 8px;
            padding: 2px;
        }
        .tracking-topbar .lang-toggle__btn {
            color: #636366;
            font-size: 0.75rem;
            font-weight: 500;
            letter-spacing: -0.01em;
            border-radius: 6px;
            padding: 0.25rem 0.5rem;
        }
        .tracking-topbar .lang-toggle__btn:hover {
            color: #1d1d1f;
            background: rgba(118, 118, 128, 0.08);
        }
        .tracking-topbar .lang-toggle__btn--active {
            background: var(--apple-bg-primary);
            color: #1d1d1f;
            font-weight: 600;
            box-shadow: 0 1px 3px rgba(0, 0, 0, 0.06), 0 0 0 0.5px rgba(0, 0, 0, 0.04);
        }

        .content-wrap {
            padding: 0;
            margin-top: var(--tracking-topbar-height);
            min-height: calc(100vh - var(--tracking-topbar-height));
        }

        body.tracking-shell:has(.tc-workspace-nav) {
            --tracking-nav-height: 48px;
        }

        body.tracking-shell.tc-module-nav-open {
            --tracking-nav-height: 52px;
        }

        body.tracking-shell.tc-module-nav-collapsed:has(.tc-workspace-nav__reveal) {
            --tracking-nav-height: 28px;
        }

        @media (max-width: 576px) {
            .tracking-topbar__title span { display: none; }
            .tracking-back-btn span { display: none; }
        }

        /* Embedded (inside a popup iframe): hide chrome so only the module content shows. */
        body.tracking-embed .tracking-topbar { display: none !important; }
        body.tracking-embed .content-wrap { margin-top: 0; padding: 0.85rem; }
        body.tracking-embed .gt-hub-nav { display: none !important; }
        body.tracking-embed .tc-workspace-nav { display: none !important; }
        body.tracking-embed .gt-module-page > h1:first-child { display: none; }
    </style>
</head>
<body class="tracking-shell {{ $isEmbed ? 'tracking-embed' : '' }}" data-map-session-end="{{ route('map.session.end') }}" data-admin-locale="{{ app()->getLocale() }}">
    @unless($isEmbed)
    <header class="tracking-topbar">
        <a href="{{ Route::has($dashboardRoute) ? route($dashboardRoute) : url('/') }}" class="tracking-topbar__brand" aria-label="{{ __('app.common.dashboard') }}">
            @include('partials.brand-logo', ['onDark' => false])
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
