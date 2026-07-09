@php
    $isSuperAdmin = (bool) auth()->user()?->isSuperAdmin();
    $roleLabel = $isSuperAdmin ? __('app.admin.nav.super_admin') : __('app.admin.nav.administrator');
    $navOnlineCount = $onlineNow ?? $onlineDevices ?? 0;
@endphp
<!-- Apple HIG Admin Topbar -->
<nav class="admin-navbar">
    <div class="navbar-content">
        <!-- Left Side: Toggle & Page Title -->
        <div class="navbar-left">
            <button type="button" class="sidebar-toggle" id="sidebarToggle" aria-label="{{ __('app.forms.toggle_sidebar') }}" aria-expanded="false">
                <i class="fas fa-bars" aria-hidden="true"></i>
            </button>

            <!-- Page Title -->
            <div class="page-title">
                <h5 class="mb-0">
                    <i class="fas fa-@yield('page-icon','tachometer-alt') me-2" aria-hidden="true"></i>
                    @yield('page-title', __('app.common.dashboard'))
                </h5>
                @hasSection('page-subtitle')
                    <small class="text-muted">@yield('page-subtitle')</small>
                @endif
            </div>
        </div>

        <!-- Right Side: Quick stats, language, user menu -->
        <div class="navbar-right">
            <!-- Quick Stats (Desktop Only) -->
            <div class="ad-stat-chips d-none d-lg-flex">
                <span class="ad-stat-chip" title="{{ __('app.admin.navbar.online') }}">
                    <span class="ad-stat-dot" aria-hidden="true"></span>
                    <strong>{{ number_format($navOnlineCount) }}</strong> {{ __('app.admin.navbar.online') }}
                </span>
                <span class="ad-stat-chip" title="{{ __('app.admin.navbar.users_stat') }}">
                    <i class="fas fa-users" aria-hidden="true"></i>
                    <strong>{{ number_format($totalUsers ?? 0) }}</strong> {{ __('app.admin.navbar.users_stat') }}
                </span>
                <span class="ad-stat-chip" title="{{ __('app.admin.navbar.devices_stat') }}">
                    <i class="fas fa-satellite" aria-hidden="true"></i>
                    <strong>{{ number_format($activeDevices ?? 0) }}</strong> {{ __('app.admin.navbar.devices_stat') }}
                </span>
            </div>

            @include('partials.language-toggle')

            <!-- User Menu -->
            <div class="dropdown">
                <div class="user-menu" data-bs-toggle="dropdown" aria-expanded="false" role="button" tabindex="0">
                    @include('partials.user-avatar', ['user' => auth()->user(), 'size' => 38, 'class' => 'user-avatar'])
                    <div class="user-info d-none d-md-block">
                        <div class="user-name">{{ auth()->user()->name }}</div>
                        <div class="user-role">
                            <span class="ad-role-badge {{ $isSuperAdmin ? 'ad-role-badge--super' : '' }}">
                                @if($isSuperAdmin)
                                    <i class="fas fa-crown" aria-hidden="true"></i>
                                @endif
                                {{ $roleLabel }}
                            </span>
                        </div>
                    </div>
                    <i class="fas fa-chevron-down text-muted ms-2 d-none d-md-inline-block" aria-hidden="true" style="font-size: 0.7rem;"></i>
                </div>
                <ul class="dropdown-menu dropdown-menu-end">
                    <li>
                        <a class="dropdown-item" href="{{ route('profile.edit') }}">
                            <i class="fas fa-user-circle me-2"></i> {{ __('app.admin.nav.my_profile') }}
                        </a>
                    </li>
                    <li><hr class="dropdown-divider"></li>
                    <li>
                        <form method="POST" action="{{ route('logout') }}" class="mb-0">
                            @csrf
                            <button type="submit" class="dropdown-item text-danger">
                                <i class="fas fa-sign-out-alt me-2"></i> {{ __('app.common.logout') }}
                            </button>
                        </form>
                    </li>
                </ul>
            </div>
        </div>
    </div>
</nav>
