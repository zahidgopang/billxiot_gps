@extends('user.layout_user')

@section('title', __('app.user.dashboard.title') . ' - ' . __('app.brand'))

@section('content')

    <div class="container-fluid dashboard-container">
        <!-- Welcome Section -->
        <div class="row mb-4" style="margin-top: 30px !important;">

            @if(isset($trackerAccountActive) && ! $trackerAccountActive)
                <div class="col-12">
                    <div class="alert alert-warning d-flex align-items-start shadow-sm">
                        <i class="fas fa-satellite-dish me-3 mt-1"></i>
                        <div>
                            <strong>{{ __('app.user.dashboard.tracker_unavailable_title') }}</strong>
                            <div class="small">
                                {{ __('app.user.dashboard.tracker_unavailable_msg') }}
                            </div>
                        </div>
                    </div>
                </div>
            @endif

            @if(session('tracker_unavailable'))
                <div class="col-12">
                    <div class="alert alert-warning mb-0">
                        {{ __('app.user.dashboard.tracker_route_blocked') }}
                    </div>
                </div>
            @endif

            {{-- ✅ ALERT: outside premium-card --}}
            @if($emailVerified)
                <div class="col-12">
                    <div class="alert alert-success d-flex align-items-start shadow-sm">
                        <i class="fas fa-check-circle me-3 mt-1"></i>
                        <div>
                            <strong>{{ __('app.user.dashboard.email_verified') }} 🎉</strong>
                            <div class="small">
                                {{ __('app.user.dashboard.email_verified_msg') }}
                            </div>
                        </div>
                    </div>
                </div>
            @endif

            <div class="col-12">
                <div class="premium-card">
                    <div class="row align-items-center">
                        <div class="col-md-8">
                            <h2 class="mb-3">
                                {{ __('app.user.dashboard.welcome_back') }}
                                <span style="color: var(--primary-blue);">
                            {{ $trackerDisplayName ?? auth()->user()->name }}
                        </span>! 👋
                            </h2>

                            <p class="text-muted mb-0">
                                <i class="fas fa-clock me-2"></i>
                                {{ now()->format('l, F j, Y') }} •
                                <span id="liveTime" style="color: var(--primary-blue);"></span>
                            </p>

                            <p class="mt-3 mb-0">
                                {{ __('app.user.dashboard.tagline') }}
                            </p>
                        </div>

                        <div class="col-md-4 text-md-end">
                            <div class="position-relative d-inline-block">
                                <i class="fas fa-satellite fa-4x" style="color: var(--primary-blue);"></i>
                                <span class="badge bg-danger position-absolute top-0 start-100 translate-middle">
                            {{ __('app.common.live') }}
                        </span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

        </div>


        <!-- Stats Overview -->
        <div class="row mb-4">
            <div class="col-xl-3 col-md-6 mb-4">
                <div class="premium-card stats-card h-100" data-stat="active">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <h6 class="text-uppercase text-muted mb-2">{{ __('app.user.dashboard.active_vehicles') }}</h6>
                            <h2 class="mb-0" style="color: var(--primary-blue);">{{ $activeDevices }}</h2>
                            <p class="mb-0"><span class="text-muted">{{ trans_choice('app.user.dashboard.total_devices', $totalDevices, ['count' => $totalDevices]) }}</span></p>
                        </div>
                        <div class="icon-box" style="background: linear-gradient(135deg, #10B981, #059669);">
                            <i class="fas fa-car fa-2x text-white"></i>
                        </div>
                    </div>
                    <div class="progress mt-3" style="height: 6px; background: #E5E7EB;">
                        <div class="progress-bar bg-success" style="width: {{ $activePercent }}%;"></div>
                    </div>
                </div>
            </div>

            <div class="col-xl-3 col-md-6 mb-4">
                <div class="premium-card stats-card h-100" data-stat="distance">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <h6 class="text-uppercase text-muted mb-2">{{ __('app.user.dashboard.total_distance') }}</h6>
                            <h2 class="mb-0" style="color: var(--primary-blue);">{{ number_format($totalDistanceKm) }}<span class="fs-6">km</span></h2>
                            <p class="mb-0"><span class="text-warning"><i class="fas fa-wave-square me-1"></i>{{ __('app.user.dashboard.last_30_days') }}</span></p>
                        </div>
                        <div class="icon-box" style="background: linear-gradient(135deg, #F59E0B, #D97706);">
                            <i class="fas fa-route fa-2x text-white"></i>
                        </div>
                    </div>
                    <div class="progress mt-3" style="height: 6px; background: #E5E7EB;">
                        <div class="progress-bar bg-warning" style="width: {{ $distancePercent }}%;"></div>
                    </div>
                </div>
            </div>

            <div class="col-xl-3 col-md-6 mb-4">
                <div class="premium-card stats-card h-100" data-stat="alerts">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <h6 class="text-uppercase text-muted mb-2">Active Alerts</h6>
                            <h2 class="mb-0" style="color: var(--primary-blue);">{{ $activeAlerts }}</h2>
                            <p class="mb-0"><span class="text-danger"><i class="fas fa-exclamation-circle me-1"></i>Geofence exits</span> last 7 days</p>
                        </div>
                        <div class="icon-box" style="background: linear-gradient(135deg, #EF4444, #DC2626);">
                            <i class="fas fa-bell fa-2x text-white"></i>
                        </div>
                    </div>
                    <div class="progress mt-3" style="height: 6px; background: #E5E7EB;">
                        <div class="progress-bar bg-danger" style="width: {{ $alertsPercent }}%;"></div>
                    </div>
                </div>
            </div>

            <div class="col-xl-3 col-md-6 mb-4">
                <div class="premium-card stats-card h-100" data-stat="online">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <h6 class="text-uppercase text-muted mb-2">Online Now</h6>
                            <h2 class="mb-0" style="color: var(--primary-blue);">{{ $onlineNow }}</h2>
                            <p class="mb-0"><span class="text-info"><i class="fas fa-signal me-1"></i>Reporting</span> in last 5 min</p>
                        </div>
                        <div class="icon-box" style="background: linear-gradient(135deg, var(--primary-blue), var(--secondary-blue));">
                            <i class="fas fa-satellite-dish fa-2x text-white"></i>
                        </div>
                    </div>
                    <div class="progress mt-3" style="height: 6px; background: #E5E7EB;">
                        <div class="progress-bar bg-info" style="width: {{ $onlinePercent }}%;"></div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Vehicles Overview Section -->
        <div class="row mb-4">
            <div class="col-12">
                <div class="premium-card">
                    <div class="d-flex justify-content-between align-items-center mb-4">
                        <h5 class="mb-0"><i class="fas fa-car me-2"></i>Vehicles Overview</h5>
                    </div>

                    <div class="row">
                        <div class="col-md-3 mb-3">
                            <div class="text-center p-4 premium-card">
                                <div class="mb-3">
                                    <i class="fas fa-car fa-3x" style="color: var(--success);"></i>
                                </div>
                                <h4 class="mb-2">{{ $vehicleStates['running'] }}</h4>
                                <p class="text-muted mb-0">Running</p>
                            </div>
                        </div>

                        <div class="col-md-3 mb-3">
                            <div class="text-center p-4 premium-card">
                                <div class="mb-3">
                                    <i class="fas fa-parking fa-3x" style="color: var(--warning);"></i>
                                </div>
                                <h4 class="mb-2">{{ $vehicleStates['parked'] }}</h4>
                                <p class="text-muted mb-0">Parked</p>
                            </div>
                        </div>

                        <div class="col-md-3 mb-3">
                            <div class="text-center p-4 premium-card">
                                <div class="mb-3">
                                    <i class="fas fa-wrench fa-3x" style="color: var(--info);"></i>
                                </div>
                                <h4 class="mb-2">{{ $vehicleStates['maintenance'] }}</h4>
                                <p class="text-muted mb-0">Inactive / Blocked</p>
                            </div>
                        </div>

                        <div class="col-md-3 mb-3">
                            <div class="text-center p-4 premium-card">
                                <div class="mb-3">
                                    <i class="fas fa-exclamation-triangle fa-3x" style="color: var(--danger);"></i>
                                </div>
                                <h4 class="mb-2">{{ $vehicleStates['alerts'] }}</h4>
                                <p class="text-muted mb-0">Alerts</p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Main Content Area -->
        <div class="row">
            <!-- Live Activity Feed -->
            <div class="col-xl-8 mb-4">
                <div class="premium-card h-100">
                    <div class="d-flex justify-content-between align-items-center mb-3">
                        <h5 class="mb-0"><i class="fas fa-bolt me-2"></i>Live Activity Feed</h5>
                        <span class="badge bg-danger">{{ __('app.common.live') }}</span>
                    </div>

                    <div class="activity-feed" style="max-height: 500px; overflow-y: auto;">
                        @include('user.partials.dashboard-activity-feed')
                    </div>

                    <!-- Refresh Button -->
                    <div class="text-center mt-3">
                        <button class="btn btn-premium w-100" id="refreshActivity">
                            <i class="fas fa-sync-alt me-2"></i>Refresh Feed
                        </button>
                    </div>
                </div>
            </div>

            <!-- Quick Actions -->
            <div class="col-xl-4 mb-4">
                <div class="premium-card h-100">
                    <h5 class="mb-4"><i class="fas fa-bolt me-2"></i>Quick Actions</h5>
                    <div class="row g-3">
                        <div class="col-6">
                            <button class="btn btn-action w-100 h-100 p-3" data-action="report">
                                <i class="fas fa-chart-bar fa-2x mb-2" style="color: var(--primary-blue);"></i>
                                <span>Generate Report</span>
                            </button>
                        </div>
                        <div class="col-6">
                            <button class="btn btn-action w-100 h-100 p-3" data-action="geofence">
                                <i class="fas fa-draw-polygon fa-2x mb-2" style="color: var(--primary-blue);"></i>
                                <span>Set Geofence</span>
                            </button>
                        </div>
                        <div class="col-6">
                            <button class="btn btn-action w-100 h-100 p-3" data-action="alert">
                                <i class="fas fa-bell fa-2x mb-2" style="color: var(--primary-blue);"></i>
                                <span>Manage Alerts</span>
                            </button>
                        </div>
                        <div class="col-6">
                            <button class="btn btn-action w-100 h-100 p-3" data-action="export">
                                <i class="fas fa-file-export fa-2x mb-2" style="color: var(--primary-blue);"></i>
                                <span>Export Data</span>
                            </button>
                        </div>
                        <div class="col-6">
                            <button class="btn btn-action w-100 h-100 p-3" data-action="history">
                                <i class="fas fa-history fa-2x mb-2" style="color: var(--primary-blue);"></i>
                                <span>View History</span>
                            </button>
                        </div>
                        <div class="col-6">
                            <button class="btn btn-action w-100 h-100 p-3" data-action="settings">
                                <i class="fas fa-cog fa-2x mb-2" style="color: var(--primary-blue);"></i>
                                <span>Settings</span>
                            </button>
                        </div>
                    </div>

                    <!-- Recent Reports -->
                    <div class="mt-4">
                        <h6 class="mb-3"><i class="fas fa-file-alt me-2"></i>Recent Reports</h6>
                        <div class="list-group">
                            <a href="#" class="list-group-item list-group-item-action border-0 mb-2 rounded">
                                <div class="d-flex w-100 justify-content-between">
                                    <h6 class="mb-1">Daily Summary</h6>
                                    <small>Today</small>
                                </div>
                                <p class="mb-1 text-muted">Vehicle performance and fuel report</p>
                            </a>
                            <a href="#" class="list-group-item list-group-item-action border-0 mb-2 rounded">
                                <div class="d-flex w-100 justify-content-between">
                                    <h6 class="mb-1">Weekly Analysis</h6>
                                    <small>2 days ago</small>
                                </div>
                                <p class="mb-1 text-muted">Route optimization and efficiency</p>
                            </a>
                            <a href="#" class="list-group-item list-group-item-action border-0 rounded">
                                <div class="d-flex w-100 justify-content-between">
                                    <h6 class="mb-1">Monthly Report</h6>
                                    <small>1 week ago</small>
                                </div>
                                <p class="mb-1 text-muted">Complete fleet performance</p>
                            </a>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Recent Vehicles Table -->
        <div class="row mt-4">
            <div class="col-12">
                <div class="premium-card h-100">
                    <div class="d-flex justify-content-between align-items-center mb-4">
                        <h5 class="mb-0"><i class="fas fa-car me-2"></i>Recent Vehicles</h5>
                        <a href="{{ route('user.devices.index') }}" class="btn btn-outline-premium btn-sm">View All</a>
                    </div>

                    <div class="table-responsive">
                        <table class="table table-hover">
                            <thead>
                            <tr>
                                <th>Vehicle ID</th>
                                <th>Status</th>
                                <th>Location</th>
                                <th>Speed</th>
                                <th>Battery</th>
                                <th>Last Update</th>
                                <th>Actions</th>
                            </tr>
                            </thead>
                            <tbody>
                            @include('user.partials.dashboard-recent-vehicles')
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <style>
        /* Additional Dashboard Styles */
        .icon-box {
            width: 60px;
            height: 60px;
            border-radius: 15px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 24px;
        }

        .icon-box-sm {
            width: 40px;
            height: 40px;
            border-radius: 10px;
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .activity-item {
            transition: all 0.3s ease;
            border-inline-start: 3px solid transparent;
        }

        .activity-item:hover {
            border-inline-start-color: var(--primary-blue);
        }

        html[dir="ltr"] .activity-item:hover {
            transform: translateX(5px);
        }

        html[dir="rtl"] .activity-item:hover {
            transform: translateX(-5px);
        }

        .btn-action {
            background: white;
            border: 2px solid var(--border-color);
            border-radius: 12px;
            color: var(--text-primary);
            transition: all 0.3s ease;
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            padding: 1rem;
        }

        .btn-action:hover {
            background: var(--primary-blue);
            color: white;
            transform: translateY(-3px);
            border-color: var(--primary-blue);
        }

        .stats-card {
            cursor: pointer;
        }

        .stats-card:hover {
            transform: translateY(-8px);
        }

        /* List group customization */
        .list-group-item {
            border: 1px solid var(--border-color);
            transition: all 0.3s ease;
        }

        .list-group-item:hover {
            background-color: var(--primary-blue);
            color: white;
            border-color: var(--primary-blue);
        }

        .list-group-item:hover .text-muted {
            color: rgba(255, 255, 255, 0.8) !important;
        }

        /* Responsive adjustments */
        @media (max-width: 768px) {
            .stats-card h2 {
                font-size: 1.5rem;
            }

            .icon-box {
                width: 50px;
                height: 50px;
                font-size: 20px;
            }

            .btn-action {
                padding: 0.75rem;
            }

            .btn-action i {
                font-size: 1.5rem;
            }
        }
    </style>

    <script>
        document.addEventListener('DOMContentLoaded', function() {
            // Live time update
            function updateLiveTime() {
                const now = new Date();
                const timeString = now.toLocaleTimeString('en-US', {
                    hour12: true,
                    hour: '2-digit',
                    minute: '2-digit',
                    second: '2-digit'
                });
                document.getElementById('liveTime').textContent = timeString;
            }

            setInterval(updateLiveTime, 1000);
            updateLiveTime();

            // Refresh activity feed
            document.getElementById('refreshActivity').addEventListener('click', function() {
                const originalHTML = this.innerHTML;
                this.innerHTML = '<i class="fas fa-spinner fa-spin me-2"></i>Refreshing...';
                this.classList.add('disabled');

                setTimeout(() => {
                    this.innerHTML = originalHTML;
                    this.classList.remove('disabled');
                    // Show success message
                    window.location.reload();
                }, 1500);
            });

            // Quick action buttons
            document.querySelectorAll('.btn-action').forEach(btn => {
                btn.addEventListener('click', function() {
                    const action = this.dataset.action;
                    const actionNames = {
                        'report': 'Generate Report',
                        'geofence': 'Set Geofence',
                        'alert': 'Manage Alerts',
                        'export': 'Export Data',
                        'history': 'View History',
                        'settings': 'Settings'
                    };
                    alert(`Action: ${actionNames[action]}`);
                });
            });

            // Add hover effect to stats cards
            document.querySelectorAll('.stats-card').forEach(card => {
                card.addEventListener('mouseenter', function() {
                    this.style.transform = 'translateY(-8px)';
                });

                card.addEventListener('mouseleave', function() {
                    this.style.transform = 'translateY(0)';
                });
            });
        });
    </script>

    @if(!empty($emailVerified))
        <script>
            setTimeout(() => {
                const alert = document.querySelector('.bg-green-50');
                if (alert) alert.classList.add('fade');
            }, 24000);
        </script>
    @endif

@endsection
