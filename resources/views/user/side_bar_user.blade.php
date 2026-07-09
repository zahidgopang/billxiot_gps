<div class="filter-panel" id="filterPanel">

    <!-- Sidebar Header -->
    <div class="sidebar-header">
        <h5 class="mb-0">
            <i class="fa fa-user-circle me-2" style="color: var(--primary-blue);"></i> User Menu
        </h5>
        <div class="close-sidebar" id="closeSidebar">
            <i class="fa fa-times"></i>
        </div>
    </div>

    <!-- Dashboard -->
    <div class="premium-card mb-3">
        <div class="d-flex align-items-center mb-3">
            <div class="icon-box-sm me-3" style="background: linear-gradient(135deg, var(--primary-blue), var(--secondary-blue));">
                <i class="fas fa-tachometer-alt text-white"></i>
            </div>
            <div>
                <h6 class="mb-1">Dashboard</h6>
                <p class="small text-muted mb-0">Main overview and statistics</p>
            </div>
        </div>
        <a href="{{ route('user.dashboard') }}" class="btn btn-premium btn-sm w-100">
            <i class="fas fa-external-link-alt me-2"></i> Open Dashboard
        </a>
    </div>

    <!-- My Devices -->
    <div class="premium-card mb-3">
        <div class="d-flex align-items-center mb-3">
            <div class="icon-box-sm me-3" style="background: linear-gradient(135deg, #10B981, #059669);">
                <i class="fas fa-satellite text-white"></i>
            </div>
            <div>
                <h6 class="mb-1">{{ __('app.user.devices.title') }}</h6>
                <p class="small text-muted mb-0">{{ __('app.user.devices.subtitle') }}</p>
            </div>
        </div>
        <a href="{{ route('user.devices.index') }}" class="btn btn-outline-premium btn-sm w-100">
            <i class="fas fa-list me-2"></i> {{ __('app.user.nav.view_devices') }}
        </a>
    </div>

    @if(auth()->user()?->canViewSubAccounts())
        <div class="premium-card mb-3">
            <div class="d-flex align-items-center mb-3">
                <div class="icon-box-sm me-3" style="background: linear-gradient(135deg, #6366F1, #4F46E5);">
                    <i class="fas fa-user-group text-white"></i>
                </div>
                <div>
                    <h6 class="mb-1">{{ __('app.sub_accounts.nav') }}</h6>
                    <p class="small text-muted mb-0">{{ __('app.sub_accounts.subtitle') }}</p>
                </div>
            </div>
            <a href="{{ route('user.sub-accounts.index') }}" class="btn btn-outline-premium btn-sm w-100">
                <i class="fas fa-users me-2"></i> {{ __('app.sub_accounts.manage') }}
            </a>
        </div>
    @endif

    <!-- Alerts -->
    <div class="premium-card mb-3">
        <div class="d-flex align-items-center mb-3">
            <div class="icon-box-sm me-3" style="background: linear-gradient(135deg, #EF4444, #DC2626);">
                <i class="fas fa-bell text-white"></i>
            </div>
            <div>
                <h6 class="mb-1">{{ __('app.user.nav.alerts_events') }}</h6>
                <p class="small text-muted mb-0">{{ __('app.user.nav.alerts_desc') }}</p>
            </div>
        </div>
        <a href="{{ route('user.alerts.index') }}" class="btn btn-outline-premium btn-sm w-100">
            <i class="fas fa-list me-2"></i> {{ __('app.user.nav.view_all_alerts') }}
        </a>
    </div>

    <!-- Profile -->
    <div class="premium-card mb-3">
        <div class="d-flex align-items-center mb-3">
            <div class="icon-box-sm me-3" style="background: linear-gradient(135deg, #3B82F6, #1D4ED8);">
                <i class="fas fa-user text-white"></i>
            </div>
            <div>
                <h6 class="mb-1">{{ __('app.user.nav.my_profile') }}</h6>
                <p class="small text-muted mb-0">{{ __('app.user.nav.account_info') }}</p>
            </div>
        </div>
        <div class="user-info mb-3 p-3 rounded" style="background: rgba(25, 118, 210, 0.1);">
            <div class="d-flex align-items-center mb-2">
                <i class="fas fa-user-circle me-2" style="color: var(--primary-blue);"></i>
                <span class="fw-medium">{{ auth()->user()->name }}</span>
            </div>
            <div class="d-flex align-items-center">
                <i class="fas fa-envelope me-2" style="color: var(--primary-blue);"></i>
                <span class="small">{{ auth()->user()->email }}</span>
            </div>
        </div>
        <a href="{{ route('user.profile') }}" class="btn btn-premium btn-sm w-100">
            <i class="fas fa-edit me-2"></i> {{ __('app.user.nav.edit_profile') }}
        </a>
    </div>

    <!-- Security -->
    <div class="premium-card mb-3">
        <div class="d-flex align-items-center mb-3">
            <div class="icon-box-sm me-3" style="background: linear-gradient(135deg, #F59E0B, #D97706);">
                <i class="fas fa-shield-alt text-white"></i>
            </div>
            <div>
                <h6 class="mb-1">Security</h6>
                <p class="small text-muted mb-0">Account protection</p>
            </div>
        </div>
        <a href="{{ route('user.change.password') }}" class="btn btn-outline-premium btn-sm w-100" style="border-color: #F59E0B; color: #F59E0B;">
            <i class="fas fa-key me-2"></i> Change Password
        </a>
    </div>

    <!-- Account -->
    <div class="premium-card">
        <div class="d-flex align-items-center mb-3">
            <div class="icon-box-sm me-3" style="background: linear-gradient(135deg, #EF4444, #DC2626);">
                <i class="fas fa-user-cog text-white"></i>
            </div>
            <div>
                <h6 class="mb-1">Account</h6>
                <p class="small text-muted mb-0">Manage account settings</p>
            </div>
        </div>
        <form method="POST" action="{{ route('logout') }}" class="mb-0">
            @csrf
            <button class="btn btn-danger w-100" style="background: linear-gradient(135deg, #EF4444, #DC2626); border: none;">
                <i class="fas fa-sign-out-alt me-2"></i> Logout Account
            </button>
        </form>
    </div>

    <!-- Quick Stats -->
    <div class="mt-4">
        <h6 class="text-muted mb-3"><i class="fas fa-chart-line me-2"></i>Quick Stats</h6>
        <div class="row g-2">
            <div class="col-6">
                <div class="p-3 rounded text-center" style="background: rgba(25, 118, 210, 0.1);">
                    <div class="mb-2">
                        <i class="fas fa-car" style="color: var(--primary-blue);"></i>
                    </div>
                    <h5 class="mb-1" style="color: var(--primary-blue);">8</h5>
                    <small class="text-muted">Vehicles</small>
                </div>
            </div>
            <div class="col-6">
                <div class="p-3 rounded text-center" style="background: rgba(16, 185, 129, 0.1);">
                    <div class="mb-2">
                        <i class="fas fa-check-circle" style="color: #10B981;"></i>
                    </div>
                    <h5 class="mb-1" style="color: #10B981;">6</h5>
                    <small class="text-muted">Active</small>
                </div>
            </div>
        </div>
    </div>

</div>
