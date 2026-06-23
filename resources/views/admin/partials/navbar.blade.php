<!-- Premium Admin Navbar -->
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
                    <i class="fas fa-@yield('page-icon','tachometer-alt') me-2" style="color: var(--admin-primary);"></i>
                    @yield('page-title','Dashboard')
                </h5>
                @hasSection('page-subtitle')
                    <small class="text-muted">@yield('page-subtitle')</small>
                @endif
            </div>
        </div>

        <!-- Right Side: Search, Notifications, User Menu -->
        <div class="navbar-right">
            @include('partials.language-toggle')
            <!-- Quick Stats (Desktop Only) -->
            <div class="quick-stats d-none d-md-flex align-items-center gap-3 me-3">
                <div class="stat-item">
                    <small class="text-muted d-block">{{ __('app.admin.navbar.online') }}</small>
                    <span class="fw-semibold" style="color: var(--admin-success);">
                        {{ $onlineDevices ?? 0 }}
                    </span>
                </div>
                <div class="stat-divider"></div>
                <div class="stat-item">
                    <small class="text-muted d-block">{{ __('app.admin.navbar.users_stat') }}</small>
                    <span class="fw-semibold">
                        {{ $totalUsers ?? 0 }}
                    </span>
                </div>
                <div class="stat-divider"></div>
                <div class="stat-item">
                    <small class="text-muted d-block">{{ __('app.admin.navbar.devices_stat') }}</small>
                    <span class="fw-semibold">
                        {{ $activeDevices ?? 0 }}
                    </span>
                </div>
            </div>

            <!-- User Menu -->
            <div class="dropdown">
                <div class="user-menu" data-bs-toggle="dropdown" aria-expanded="false">
                    <div class="user-avatar">
                        {{ strtoupper(substr(auth()->user()->name, 0, 1)) }}
                    </div>
                    <div class="user-info d-none d-md-block">
                        <div class="user-name">{{ auth()->user()->name }}</div>
                        <div class="user-role">{{ __('app.admin.nav.administrator') }}</div>
                    </div>
                    <i class="fas fa-chevron-down text-muted ms-2"></i>
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

<style>
    /* Page Title Styling */
    .page-title h5 {
        color: var(--admin-text);
        font-weight: 600;
        font-size: 1.125rem;
        margin: 0;
    }

    .page-title small {
        font-size: 0.75rem;
        color: var(--admin-text-light);
    }

    /* Quick Stats */
    .quick-stats {
        background: var(--admin-bg);
        padding: 0.5rem 1rem;
        border-radius: 10px;
        border: 1px solid var(--admin-border);
    }

    .stat-item {
        text-align: center;
        min-width: 60px;
    }

    .stat-item small {
        font-size: 0.6875rem;
        font-weight: 500;
    }

    .stat-item .fw-semibold {
        font-size: 0.9375rem;
        font-weight: 600;
    }

    .stat-divider {
        width: 1px;
        height: 20px;
        background: var(--admin-border);
    }

    /* User Menu Dropdown */
    .dropdown-menu {
        border: 1px solid var(--admin-border);
        box-shadow: 0 10px 30px rgba(0, 0, 0, 0.1);
        border-radius: 12px;
        padding: 0.5rem;
        min-width: 220px;
    }

    .dropdown-item {
        padding: 0.75rem 1rem;
        border-radius: 8px;
        color: var(--admin-text);
        transition: all 0.3s ease;
        display: flex;
        align-items: center;
    }

    .dropdown-item:hover {
        background: var(--admin-bg);
        color: var(--admin-primary);
    }

    html[dir="ltr"] .dropdown-item:hover {
        transform: translateX(3px);
    }

    html[dir="rtl"] .dropdown-item:hover {
        transform: translateX(-3px);
    }

    .dropdown-item.text-danger:hover {
        color: var(--admin-danger);
    }

    .dropdown-divider {
        margin: 0.5rem 0;
        border-color: var(--admin-border);
    }

    /* Responsive Adjustments */
    @media (max-width: 768px) {
        .quick-stats {
            display: none !important;
        }

        .user-info {
            display: none !important;
        }

        .page-title h5 {
            font-size: 1rem;
        }

        .page-title h5 i {
            display: none;
        }
    }

    @media (max-width: 576px) {
        .navbar-content {
            padding: 0.5rem 0;
        }

        .sidebar-toggle {
            width: 36px;
            height: 36px;
            font-size: 0.875rem;
        }

        .user-avatar {
            width: 36px;
            height: 36px;
            font-size: 0.875rem;
        }

        .page-title h5 {
            font-size: 0.9375rem;
        }
    }

    /* Dark Mode Toggle */
    #darkModeToggle .badge {
        font-size: 0.625rem;
        padding: 0.125rem 0.375rem;
    }

    /* Smooth transitions */
    .dropdown-item,
    .sidebar-toggle,
    .user-menu {
        transition: all 0.3s ease;
    }

    /* Active state for current page */
    .navbar-left .page-title h5 {
        position: relative;
    }

    .navbar-left .page-title h5::after {
        content: '';
        position: absolute;
        bottom: -4px;
        left: 0;
        width: 30px;
        height: 2px;
        background: var(--admin-primary);
        border-radius: 1px;
    }
</style>

<script>
    document.addEventListener('DOMContentLoaded', function() {
        // Dark mode toggle
        const darkModeToggle = document.getElementById('darkModeToggle');
        if (darkModeToggle) {
            darkModeToggle.addEventListener('click', function(e) {
                e.preventDefault();
                e.stopPropagation();

                const body = document.body;
                const isDark = body.classList.toggle('theme-dark');

                // Update icon
                const icon = this.querySelector('i');
                const badge = this.querySelector('.badge');

                if (isDark) {
                    icon.classList.remove('fa-moon');
                    icon.classList.add('fa-sun');
                    badge.textContent = window.APP_I18N?.on || 'On';
                    localStorage.setItem('admin-dark-mode', 'true');
                } else {
                    icon.classList.remove('fa-sun');
                    icon.classList.add('fa-moon');
                    badge.textContent = window.APP_I18N?.beta || 'Beta';
                    localStorage.setItem('admin-dark-mode', 'false');
                }

                // Show notification
                Swal.fire({
                    toast: true,
                    position: window.APP_I18N?.isRtl ? 'top-start' : 'top-end',
                    icon: 'success',
                    title: isDark ? (window.APP_I18N?.darkModeEnabled || 'Dark mode enabled') : (window.APP_I18N?.darkModeDisabled || 'Dark mode disabled'),
                    showConfirmButton: false,
                    timer: 1500,
                    timerProgressBar: true,
                    background: 'var(--admin-card)',
                    color: 'var(--admin-text)',
                    iconColor: 'var(--admin-success)'
                });
            });

            // Check saved preference
            const savedMode = localStorage.getItem('admin-dark-mode');
            if (savedMode === 'true') {
                document.body.classList.add('theme-dark');
                const icon = darkModeToggle.querySelector('i');
                const badge = darkModeToggle.querySelector('.badge');
                icon.classList.remove('fa-moon');
                icon.classList.add('fa-sun');
                badge.textContent = 'On';
            }
        }

        // Auto-update quick stats
        function updateQuickStats() {
            // This would typically be an API call
            const stats = {
                online: {{ $onlineDevices ?? 0 }},
                users: {{ $totalUsers ?? 0 }},
                devices: {{ $activeDevices ?? 0 }}
            };

            // Update DOM elements
            document.querySelectorAll('.quick-stats .stat-item').forEach(item => {
                const label = item.querySelector('small').textContent.trim();
                const valueSpan = item.querySelector('.fw-semibold');

                const onlineLabel = window.APP_I18N?.online || 'Online';
                const usersLabel = window.APP_I18N?.users || 'Users';
                const devicesLabel = window.APP_I18N?.devices || 'Devices';
                if (label === onlineLabel && valueSpan) {
                    valueSpan.textContent = stats.online;
                    valueSpan.style.color = stats.online > 0 ? 'var(--admin-success)' : 'var(--admin-text)';
                } else if (label === usersLabel && valueSpan) {
                    valueSpan.textContent = stats.users;
                } else if (label === devicesLabel && valueSpan) {
                    valueSpan.textContent = stats.devices;
                }
            });
        }

        // Update every 30 seconds
        setInterval(updateQuickStats, 30000);

        // Initialize tooltips
        const tooltipTriggerList = [].slice.call(document.querySelectorAll('[title]'));
        tooltipTriggerList.map(function (tooltipTriggerEl) {
            return new bootstrap.Tooltip(tooltipTriggerEl);
        });

        // Update page title based on current route
        function updatePageTitle() {
            const path = window.location.pathname;
            const titles = {
                '/admin/dashboard': { title: 'Dashboard', icon: 'tachometer-alt' },
                '/admin/devices': { title: 'Devices', icon: 'satellite' },
                '/admin/users': { title: 'Users', icon: 'users' },
                '/admin/subscriptions': { title: 'Subscriptions', icon: 'credit-card' },
                '/profile': { title: 'Profile', icon: 'user-circle' },
            };

            const route = Object.keys(titles).find(r => path.startsWith(r));
            if (route && titles[route]) {
                const pageTitle = document.querySelector('.page-title h5');
                if (pageTitle) {
                    pageTitle.innerHTML = `<i class="fas fa-${titles[route].icon} me-2" style="color: var(--admin-primary);"></i>${titles[route].title}`;
                }
            }
        }

        // Initial title update
        updatePageTitle();
    });
</script>
