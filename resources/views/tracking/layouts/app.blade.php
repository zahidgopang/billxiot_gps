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
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/@tabler/icons-webfont@3.31.0/dist/tabler-icons.min.css">
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
    <link rel="stylesheet" href="{{ asset('css/tracking-chrome.css') }}?v={{ @filemtime(public_path('css/tracking-chrome.css')) }}">
    <style>
        body.tracking-shell {
            margin: 0;
            min-height: 100vh;
            overflow-x: hidden;
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
            --tracking-nav-height: 30px;
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
                <i class="fas fa-satellite-dish" aria-hidden="true"></i><span>{{ __('app.tracking.hub_nav') }}</span>
            </span>
        </a>

        <div class="tracking-topbar__actions">
            @stack('tracking-topbar-actions')
            @include('partials.language-toggle')
            <a href="{{ Route::has($dashboardRoute) ? route($dashboardRoute) : url('/') }}" class="tracking-back-btn">
                <i class="fas fa-arrow-left" aria-hidden="true"></i><span>{{ __('app.tracking.back_to_management') }}</span>
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
