@php
    $navPanel = request()->routeIs('client.*') ? 'client' : 'admin';
    $canTrackMaps = app(\App\Services\Authorization\TenantScopeService::class)->actorMayTrackMaps(auth()->user());
@endphp
<!-- Premium Admin Sidebar - Working Version -->
<aside class="admin-sidebar is-open" id="adminSidebar" aria-label="{{ __('app.forms.toggle_sidebar') }}">
    <!-- Sidebar Header -->
    <div class="sidebar-header">
        <a href="{{ route($navPanel . '.dashboard') }}" class="sidebar-brand" aria-label="{{ __('app.common.dashboard') }}">
            @include('partials.brand-logo', ['onDark' => true])
        </a>
    </div>

    <!-- Sidebar Navigation -->
    <nav class="sidebar-nav">
        <!-- Dashboard -->
        <div class="nav-group">
            <div class="nav-group-title">{{ __('app.admin.nav.main') }}</div>
            <a href="{{ route($navPanel . '.dashboard') }}"
               class="nav-link-premium {{ request()->routeIs($navPanel . '.dashboard') ? 'active' : '' }}">
                <i class="fas fa-tachometer-alt"></i>
                <span>{{ __('app.common.dashboard') }}</span>
            </a>
        </div>

        @php
            $adminFlowStep = 0;
            if (request()->routeIs($navPanel . '.users.*')) {
                $adminFlowStep = 1;
            } elseif (request()->routeIs($navPanel . '.devices.*')) {
                $adminFlowStep = 2;
            } elseif (request()->routeIs($navPanel . '.subscriptions.*')) {
                $adminFlowStep = 3;
            } elseif ($canTrackMaps && request()->routeIs($navPanel . '.locations.*', $navPanel . '.device.map')) {
                $adminFlowStep = 4;
            }
        @endphp

        <!-- Management (workflow stepper) -->
        <div class="nav-group nav-group--flow">
            <div class="nav-group-title">{{ __('app.admin.nav.management') }}</div>
            <div class="nav-flow-stepper"
                 role="list"
                 aria-label="{{ __('app.admin.nav.management') }}"
                 style="--flow-active-index: {{ $adminFlowStep }};">
                <a href="{{ route($navPanel . '.users.index') }}"
                   role="listitem"
                   class="nav-link-premium nav-flow-step {{ request()->routeIs($navPanel . '.users.*') ? 'active' : '' }} {{ $adminFlowStep > 1 ? 'is-done' : '' }}">
                    <span class="nav-flow-marker" aria-hidden="true"><span class="nav-flow-dot"></span></span>
                    <span class="nav-flow-body">
                        <i class="fas fa-users"></i>
                        <span>{{ __('app.common.users') }}</span>
                    </span>
                </a>

                <a href="{{ route($navPanel . '.devices.index') }}"
                   role="listitem"
                   class="nav-link-premium nav-flow-step {{ request()->routeIs($navPanel . '.devices.*') ? 'active' : '' }} {{ $adminFlowStep > 2 ? 'is-done' : '' }}">
                    <span class="nav-flow-marker" aria-hidden="true"><span class="nav-flow-dot"></span></span>
                    <span class="nav-flow-body">
                        <i class="fas fa-satellite"></i>
                        <span>{{ __('app.admin.nav.install_devices') }}</span>
                    </span>
                </a>

                @if(Route::has($navPanel . '.subscriptions.index'))
                    <a href="{{ route($navPanel . '.subscriptions.index') }}"
                       role="listitem"
                       class="nav-link-premium nav-flow-step {{ request()->routeIs($navPanel . '.subscriptions.*') ? 'active' : '' }} {{ $adminFlowStep > 3 ? 'is-done' : '' }}">
                        <span class="nav-flow-marker" aria-hidden="true"><span class="nav-flow-dot"></span></span>
                        <span class="nav-flow-body">
                            <i class="fas fa-credit-card"></i>
                            <span>{{ __('app.common.subscriptions') }}</span>
                        </span>
                    </a>
                @else
                    <a href="#"
                       role="listitem"
                       class="nav-link-premium nav-flow-step"
                       onclick="showComingSoon('Subscriptions')">
                        <span class="nav-flow-marker" aria-hidden="true"><span class="nav-flow-dot"></span></span>
                        <span class="nav-flow-body">
                            <i class="fas fa-credit-card"></i>
                            <span>{{ __('app.common.subscriptions') }}</span>
                            <span class="nav-badge">{{ __('app.common.soon') }}</span>
                        </span>
                    </a>
                @endif

                @if($canTrackMaps && Route::has($navPanel . '.locations.index'))
                <a href="{{ route($navPanel . '.locations.index') }}"
                   role="listitem"
                   class="nav-link-premium nav-flow-step {{ request()->routeIs($navPanel . '.locations.*') || request()->routeIs($navPanel . '.device.map') ? 'active' : '' }}">
                    <span class="nav-flow-marker" aria-hidden="true"><span class="nav-flow-dot"></span></span>
                    <span class="nav-flow-body">
                        <i class="fas fa-map-marked-alt"></i>
                        <span>{{ __('app.admin.nav.track_devices') }}</span>
                    </span>
                </a>
                @endif
            </div>

            @can('permission', 'stock.view')
                @if($navPanel === 'admin' && Route::has('admin.device-stock.index'))
                    <a href="{{ route('admin.device-stock.index') }}"
                       class="nav-link-premium mt-1 {{ request()->routeIs('admin.device-stock.*') && !request()->routeIs('admin.device-stock.repairs') ? 'active' : '' }}">
                        <i class="fas fa-boxes-stacked"></i>
                        <span>{{ __('app.admin.nav.device_stock') }}</span>
                    </a>
                    @if(Route::has('admin.device-stock.repairs'))
                        <a href="{{ route('admin.device-stock.repairs') }}"
                           class="nav-link-premium {{ request()->routeIs('admin.device-stock.repairs') ? 'active' : '' }}">
                            <i class="fas fa-screwdriver-wrench"></i>
                            <span>{{ __('app.admin.nav.stock_repairs') }}</span>
                        </a>
                    @endif
                @endif
            @endcan

            @can('permission', 'stock.manage')
                @if($navPanel === 'admin' && Route::has('admin.device-stock-sales.index'))
                    <a href="{{ route('admin.device-stock-sales.index') }}"
                       class="nav-link-premium {{ request()->routeIs('admin.device-stock-sales.*') ? 'active' : '' }}">
                        <i class="fas fa-file-invoice-dollar"></i>
                        <span>{{ __('app.admin.nav.stock_sales') }}</span>
                    </a>
                @endif
            @endcan

            @can('permission', 'billing.view')
                @if(Route::has($navPanel . '.billing-invoices.index'))
                    <a href="{{ route($navPanel . '.billing-invoices.index') }}"
                       class="nav-link-premium {{ request()->routeIs($navPanel . '.billing-invoices.*') ? 'active' : '' }}">
                        <i class="fas fa-receipt"></i>
                        <span>{{ __('app.billing.invoices') }}</span>
                    </a>
                @endif
                @if(Route::has($navPanel . '.reports.profit-loss'))
                    <a href="{{ route($navPanel . '.reports.profit-loss') }}"
                       class="nav-link-premium {{ request()->routeIs($navPanel . '.reports.profit-loss') ? 'active' : '' }}">
                        <i class="fas fa-chart-line"></i>
                        <span>{{ __('app.billing.profit_loss') }}</span>
                    </a>
                @endif
            @endcan

            @can('permission', 'billing.manage')
                @if($navPanel === 'admin' && Route::has('admin.subscription-plans.index'))
                    <a href="{{ route('admin.subscription-plans.index') }}"
                       class="nav-link-premium {{ request()->routeIs('admin.subscription-plans.*') ? 'active' : '' }}">
                        <i class="fas fa-layer-group"></i>
                        <span>{{ __('app.billing.plans') }}</span>
                    </a>
                @endif
            @endcan
        </div>

        <!-- System -->
        <div class="nav-group">
            <div class="nav-group-title">{{ __('app.admin.nav.system') }}</div>
            @can('permission', 'activity.view')
                @if(Route::has($navPanel . '.activity-log.index'))
                    <a href="{{ route($navPanel . '.activity-log.index') }}"
                       class="nav-link-premium {{ request()->routeIs($navPanel . '.activity-log.*') ? 'active' : '' }}">
                        <i class="fas fa-clipboard-list"></i>
                        <span>{{ __('app.admin.nav.activity_log') }}</span>
                    </a>
                @endif
            @endcan
            @if($navPanel === 'admin' && Route::has('admin.contact-messages.index'))
                @php $newContactCount = \App\Models\ContactMessage::where('status', 'new')->count(); @endphp
                <a href="{{ route('admin.contact-messages.index') }}"
                   class="nav-link-premium {{ request()->routeIs('admin.contact-messages.*') ? 'active' : '' }}">
                    <i class="fas fa-envelope-open-text"></i>
                    <span>{{ __('app.admin.nav.contact_us') }}</span>
                    @if($newContactCount > 0)
                        <span class="nav-badge">{{ $newContactCount }}</span>
                    @endif
                </a>
            @endif
        </div>
    </nav>

    <!-- Sidebar Footer -->
    <div class="sidebar-footer p-3">
        <div class="text-center text-muted small">
            <div class="mb-1">{{ __('app.admin.nav.logged_in_as') }}</div>
            <div class="fw-semibold">{{ auth()->user()->name }}</div>
            <div class="mt-2">
                <i class="fas fa-circle text-success me-1" style="font-size: 0.5rem;"></i>
                <span>{{ __('app.admin.nav.system_online') }}</span>
            </div>
        </div>
    </div>
</aside>

<script>
    function showComingSoon(feature) {
        Swal.fire({
            title: 'Coming Soon!',
            text: feature + ' feature is currently under development.',
            icon: 'info',
            confirmButtonText: 'OK',
            background: 'var(--admin-card)',
            color: 'var(--admin-text)'
        });
    }
</script>

<style>
    .sidenav {
        display: none !important;
    }

    /* Management workflow stepper — SaaS-style timeline */
    .nav-group--flow {
        --nav-flow-step-height: 2.75rem;
        --nav-flow-marker-col: 1.375rem;
        --nav-flow-dot-size: 0.5rem;
        --nav-flow-dot-active: 0.625rem;
        --nav-flow-rail-center: calc(0.5rem + var(--nav-flow-marker-col) / 2);
    }

    .nav-group--flow .nav-flow-stepper {
        position: relative;
        margin-top: 0.25rem;
        padding-inline-start: 0.5rem;
    }

    /* Track line (centered on dots) */
    .nav-group--flow .nav-flow-stepper::before {
        content: '';
        position: absolute;
        inset-inline-start: calc(var(--nav-flow-rail-center) - 0.5px);
        top: calc(var(--nav-flow-step-height) / 2);
        bottom: calc(var(--nav-flow-step-height) / 2);
        width: 1px;
        background: rgba(255, 255, 255, 0.1);
        border-radius: 1px;
        pointer-events: none;
        z-index: 0;
    }

    /* Completed portion of the track */
    .nav-group--flow .nav-flow-stepper::after {
        content: '';
        position: absolute;
        inset-inline-start: calc(var(--nav-flow-rail-center) - 0.5px);
        top: calc(var(--nav-flow-step-height) / 2);
        height: calc(max(0, var(--flow-active-index, 0) - 0.5) * var(--nav-flow-step-height));
        width: 1px;
        background: linear-gradient(
            180deg,
            rgba(25, 118, 210, 0.45) 0%,
            rgba(100, 181, 246, 0.65) 100%
        );
        border-radius: 1px;
        pointer-events: none;
        z-index: 1;
        transition: height 0.3s ease;
    }

    .nav-group--flow .nav-flow-step {
        display: grid;
        grid-template-columns: var(--nav-flow-marker-col) 1fr;
        align-items: center;
        min-height: var(--nav-flow-step-height);
        padding: 0;
        gap: 0;
        overflow: visible;
    }

    .nav-group--flow .nav-flow-step:hover,
    .nav-group--flow .nav-flow-step:focus-visible {
        padding-inline-start: 0;
    }

    .nav-group--flow .nav-flow-body i {
        width: 20px;
        flex-shrink: 0;
        text-align: center;
        font-size: 1.125rem;
    }

    .nav-group--flow .nav-flow-marker {
        display: flex;
        align-items: center;
        justify-content: center;
        height: 100%;
        position: relative;
        z-index: 2;
    }

    .nav-group--flow .nav-flow-dot {
        display: block;
        width: var(--nav-flow-dot-size);
        height: var(--nav-flow-dot-size);
        border-radius: 50%;
        box-sizing: border-box;
        border: 1.5px solid rgba(255, 255, 255, 0.22);
        background: var(--admin-sidebar, #1a1f3c);
        transition:
            width 0.25s ease,
            height 0.25s ease,
            background 0.25s ease,
            border-color 0.25s ease,
            box-shadow 0.25s ease;
    }

    /* Upcoming steps — hollow ring */
    .nav-group--flow .nav-flow-step:not(.is-done):not(.active) .nav-flow-dot {
        background: transparent;
    }

    /* Completed steps — filled muted circle */
    .nav-group--flow .nav-flow-step.is-done .nav-flow-dot {
        width: var(--nav-flow-dot-size);
        height: var(--nav-flow-dot-size);
        background: rgba(255, 255, 255, 0.42);
        border-color: rgba(255, 255, 255, 0.42);
        box-shadow: none;
    }

    /* Active step — accent + glow */
    .nav-group--flow .nav-flow-step.active .nav-flow-dot {
        width: var(--nav-flow-dot-active);
        height: var(--nav-flow-dot-active);
        background: var(--admin-primary, #1976d2);
        border: 2px solid rgba(144, 202, 255, 0.95);
        box-shadow:
            0 0 0 3px rgba(25, 118, 210, 0.22),
            0 0 14px rgba(25, 118, 210, 0.45);
    }

    .nav-group--flow .nav-flow-step:hover .nav-flow-dot,
    .nav-group--flow .nav-flow-step:focus-visible .nav-flow-dot {
        border-color: rgba(255, 255, 255, 0.5);
    }

    .nav-group--flow .nav-flow-step.is-done:hover .nav-flow-dot {
        background: rgba(255, 255, 255, 0.55);
        border-color: rgba(255, 255, 255, 0.55);
    }

    .nav-group--flow .nav-flow-body {
        display: flex;
        align-items: center;
        gap: 0.75rem;
        min-width: 0;
        min-height: var(--nav-flow-step-height);
        padding: 0.5rem 1.5rem 0.5rem 0;
    }

    .nav-group--flow .nav-flow-body > span:not(.nav-badge) {
        flex: 1;
        min-width: 0;
    }

    .nav-group--flow .nav-flow-step:hover .nav-flow-body,
    .nav-group--flow .nav-flow-step:focus-visible .nav-flow-body {
        color: #fff;
    }

    .nav-group--flow .nav-flow-step:hover {
        border-inline-start-color: var(--admin-primary, #1976d2);
    }

    .nav-group--flow .nav-flow-step:hover .nav-flow-body {
        background: rgba(255, 255, 255, 0.06);
        border-radius: 0 6px 6px 0;
    }

    .nav-group--flow .nav-flow-step.active .nav-flow-body {
        color: #fff;
    }
</style>
