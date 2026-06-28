{{-- Traccar-style tracking hub navigation --}}
@php
    $hub = $hubRoutes ?? [];
    $active = fn (string $key) => isset($hub[$key]) && request()->routeIs(str_replace('.', '.*', preg_replace('/\.index$/', '.*', $hub[$key])));
@endphp
<nav class="gt-hub-nav mb-3" aria-label="{{ __('app.tracking.hub_nav') }}">
    <ul class="nav nav-pills flex-wrap gap-1">
        @if(!empty($hub['live']))
            <li class="nav-item">
                <a class="nav-link {{ request()->routeIs('*tracking.index') ? 'active' : '' }}" href="{{ route($hub['live']) }}">
                    <i class="fas fa-map-marked-alt me-1"></i>{{ __('app.tracking.live_link') }}
                </a>
            </li>
        @endif
        @if(!empty($hub['history']))
            <li class="nav-item">
                <a class="nav-link {{ request()->routeIs('*tracking.history') ? 'active' : '' }}" href="{{ route($hub['history']) }}">
                    <i class="fas fa-route me-1"></i>{{ __('app.tracking.history_link') }}
                </a>
            </li>
        @endif
        @if(!empty($hub['reports']))
            <li class="nav-item">
                <a class="nav-link {{ request()->routeIs('*tracking.reports.*') ? 'active' : '' }}" href="{{ route($hub['reports']) }}">
                    <i class="fas fa-chart-bar me-1"></i>{{ __('app.tracking.reports_nav') }}
                </a>
            </li>
        @endif
        @if(!empty($hub['events']))
            <li class="nav-item">
                <a class="nav-link {{ request()->routeIs('*tracking.events.*') ? 'active' : '' }}" href="{{ route($hub['events']) }}">
                    <i class="fas fa-bell me-1"></i>{{ __('app.tracking.events_nav') }}
                </a>
            </li>
        @endif
        @if(!empty($hub['geofences']))
            <li class="nav-item">
                <a class="nav-link {{ request()->routeIs('*tracking.geofences.*') ? 'active' : '' }}" href="{{ route($hub['geofences']) }}">
                    <i class="fas fa-draw-polygon me-1"></i>{{ __('app.tracking.geofences_nav') }}
                </a>
            </li>
        @endif
        @if(!empty($hub['maintenance']))
            <li class="nav-item">
                <a class="nav-link {{ request()->routeIs('*tracking.maintenance.*') ? 'active' : '' }}" href="{{ route($hub['maintenance']) }}">
                    <i class="fas fa-wrench me-1"></i>{{ __('app.tracking.maintenance_nav') }}
                </a>
            </li>
        @endif
        @if(!empty($hub['drivers']))
            <li class="nav-item">
                <a class="nav-link {{ request()->routeIs('*tracking.drivers.*') ? 'active' : '' }}" href="{{ route($hub['drivers']) }}">
                    <i class="fas fa-id-card me-1"></i>{{ __('app.tracking.drivers_nav') }}
                </a>
            </li>
        @endif
        @if(!empty($hub['commands']))
            <li class="nav-item">
                <a class="nav-link {{ request()->routeIs('*tracking.commands.*') ? 'active' : '' }}" href="{{ route($hub['commands']) }}">
                    <i class="fas fa-terminal me-1"></i>{{ __('app.tracking.commands_nav') }}
                </a>
            </li>
        @endif
        @if(!empty($hub['notifications']))
            <li class="nav-item">
                <a class="nav-link {{ request()->routeIs('*tracking.notifications.*') ? 'active' : '' }}" href="{{ route($hub['notifications']) }}">
                    <i class="fas fa-envelope me-1"></i>{{ __('app.tracking.notifications_nav') }}
                </a>
            </li>
        @endif
        @if(!empty($hub['settings']))
            <li class="nav-item">
                <a class="nav-link {{ request()->routeIs('*tracking.settings.*') ? 'active' : '' }}" href="{{ route($hub['settings']) }}">
                    <i class="fas fa-cog me-1"></i>{{ __('app.tracking.settings_nav') }}
                </a>
            </li>
        @endif
    </ul>
</nav>
