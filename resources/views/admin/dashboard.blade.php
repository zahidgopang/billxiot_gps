@extends('admin.layouts.app')

@section('title', __('app.admin.dashboard.title') . ' - ' . __('app.brand'))

@push('styles')
    <style>
        /* Status badges reused across admin devices/subscriptions listings —
           kept local (Apple CSS handles the rest of this page). */
        .badge-status { padding: 0.35rem 0.6rem; border-radius: 999px; display: inline-block; font-size: 0.8rem; font-weight: 500; }
        .badge-status.badge-active { background: #dff7e0; color: #2f7d3a; }
        .badge-status.badge-inactive { background: #f3f4f6; color: #6b7280; }
        .badge-status.badge-blocked { background: #fee2e2; color: #b91c1c; }
        .badge-status.badge-offline { background: #fee2e2; color: #b91c1c; }
        .badge-status.badge-running { background: #dcfce7; color: #15803d; }
        .badge-status.badge-stopped { background: #ffedd5; color: #c2410c; }
        .badge-status.badge-parked { background: #f1f5f9; color: #475569; }
        .badge-status.badge-delayed { background: #fef9c3; color: #854d0e; }
        .badge-status.badge-stale { background: #fef3c7; color: #92400e; }
    </style>
@endpush

@section('content')
    <div class="ad-dashboard">
        <div class="ad-dashboard-header">
            <div>
                <h1 class="ad-page-title">{{ __('app.admin.dashboard.title') }}</h1>
                <p class="ad-page-sub">{{ __('app.admin.dashboard.subtitle') }}</p>
            </div>
            <div class="ad-header-actions">
                <a href="{{ route('admin.devices.create') }}" class="ad-btn ad-btn--primary">
                    <i class="fas fa-plus"></i> {{ __('app.admin.dashboard.add_device') }}
                </a>
                <a href="{{ route('admin.users.index') }}" class="ad-btn ad-btn--secondary">
                    <i class="fas fa-users"></i> {{ __('app.common.users') }}
                </a>
            </div>
        </div>

        <!-- Health row: DB / GPS / API / Contact inbox -->
        <div class="ad-health-grid">
            <div class="ad-health-item {{ $dbOk ? 'is-ok' : 'is-down' }}">
                <div class="ad-health-top">
                    <span class="ad-health-dot"></span>
                    <span class="ad-health-label">{{ __('app.admin.dashboard.database') }}</span>
                </div>
                <div class="ad-health-value">{{ $dbOk ? __('app.admin.dashboard.connected') : __('app.admin.dashboard.error') }}</div>
            </div>
            <div class="ad-health-item {{ $gpsLive ? 'is-ok' : 'is-down' }}">
                <div class="ad-health-top">
                    <span class="ad-health-dot"></span>
                    <span class="ad-health-label">{{ __('app.admin.dashboard.gps_ingest') }}</span>
                </div>
                <div class="ad-health-value">{{ $gpsLive ? __('app.common.live') : __('app.admin.dashboard.idle') }}</div>
            </div>
            <div class="ad-health-item is-ok">
                <div class="ad-health-top">
                    <span class="ad-health-dot"></span>
                    <span class="ad-health-label">{{ __('app.admin.dashboard.api') }}</span>
                </div>
                <div class="ad-health-value">{{ __('app.admin.dashboard.operational') }}</div>
            </div>
            <a href="{{ route('admin.contact-messages.index') }}" class="ad-health-item ad-health-item--link {{ $pendingContacts > 0 ? 'is-down' : 'is-ok' }}">
                <div class="ad-health-top">
                    <span class="ad-health-dot"></span>
                    <span class="ad-health-label">{{ __('app.admin.dashboard.contact_inbox') }}</span>
                </div>
                <div class="ad-health-value">{{ __('app.admin.dashboard.pending', ['count' => $pendingContacts]) }}</div>
            </a>
        </div>

        <!-- KPI grid -->
        <div class="ad-kpi-grid">
            <div class="ad-card">
                <div class="ad-kpi-head">
                    <p class="ad-kpi-title">{{ __('app.admin.dashboard.fleet_users') }}</p>
                    <div class="ad-kpi-icon"><i class="fas fa-users"></i></div>
                </div>
                <div class="ad-kpi-value">{{ number_format($totalUsers) }}</div>
                <div class="ad-kpi-meta {{ $userGrowth['positive'] ? 'up' : 'down' }}">
                    <i class="fas fa-{{ $userGrowth['positive'] ? 'arrow-up' : 'arrow-down' }}"></i>
                    {{ $userGrowth['label'] }}
                </div>
            </div>

            <div class="ad-card">
                <div class="ad-kpi-head">
                    <p class="ad-kpi-title">{{ __('app.admin.dashboard.total_devices') }}</p>
                    <div class="ad-kpi-icon"><i class="fas fa-satellite"></i></div>
                </div>
                <div class="ad-kpi-value">{{ number_format($totalDevices) }}</div>
                <div class="ad-kpi-sub">{{ __('app.admin.dashboard.active_inactive', ['active' => $activeDevices, 'inactive' => $inactiveDevices]) }}</div>
                <div class="ad-kpi-meta {{ $deviceGrowth['positive'] ? 'up' : 'down' }}">
                    <i class="fas fa-{{ $deviceGrowth['positive'] ? 'arrow-up' : 'arrow-down' }}"></i>
                    {{ $deviceGrowth['label'] }}
                </div>
            </div>

            <div class="ad-card">
                <div class="ad-kpi-head">
                    <p class="ad-kpi-title">{{ __('app.admin.dashboard.online_now') }}</p>
                    <div class="ad-kpi-icon"><i class="fas fa-signal"></i></div>
                </div>
                <div class="ad-kpi-value">{{ number_format($onlineNow) }}</div>
                <div class="ad-kpi-sub">{{ __('app.admin.dashboard.moving_offline', ['moving' => $movingNow, 'offline' => $offlineDevices]) }}</div>
                <div class="ad-kpi-meta {{ $onlineChange['positive'] ? 'up' : 'down' }}">
                    <i class="fas fa-{{ $onlineChange['positive'] ? 'arrow-up' : 'arrow-down' }}"></i>
                    {{ $onlineChange['label'] }}
                </div>
            </div>

            <div class="ad-card">
                <div class="ad-kpi-head">
                    <p class="ad-kpi-title">{{ __('app.admin.dashboard.active_subscriptions') }}</p>
                    <div class="ad-kpi-icon"><i class="fas fa-credit-card"></i></div>
                </div>
                <div class="ad-kpi-value">{{ number_format($activeSubscriptions) }}</div>
                <div class="ad-kpi-sub">{{ __('app.admin.dashboard.subs_total_expired', ['total' => $totalSubscriptions, 'expired' => $expiredSubscriptions]) }}</div>
            </div>
        </div>

        <!-- Mini chip grid -->
        <div class="ad-chip-grid">
            <div class="ad-chip">
                <div class="ad-chip-value ad-chip-value--danger">{{ number_format($alertsToday) }}</div>
                <span class="ad-chip-label">{{ __('app.admin.dashboard.alerts_today') }}</span>
            </div>
            <div class="ad-chip">
                <div class="ad-chip-value ad-chip-value--warning">{{ number_format($alertsWeek) }}</div>
                <span class="ad-chip-label">{{ __('app.admin.dashboard.alerts_week') }}</span>
            </div>
            <div class="ad-chip">
                <div class="ad-chip-value">{{ number_format($dataPointsToday) }}</div>
                <span class="ad-chip-label">{{ __('app.admin.dashboard.gps_points_today') }}</span>
            </div>
            <div class="ad-chip">
                <div class="ad-chip-value">{{ number_format($geofenceCount) }}</div>
                <span class="ad-chip-label">{{ __('app.admin.dashboard.geofences') }}</span>
            </div>
            <div class="ad-chip">
                <div class="ad-chip-value">{{ number_format($blockedDevices) }}</div>
                <span class="ad-chip-label">{{ __('app.admin.dashboard.blocked_devices') }}</span>
            </div>
            <div class="ad-chip">
                <div class="ad-chip-value">{{ number_format($unassignedDevices) }}</div>
                <span class="ad-chip-label">{{ __('app.admin.dashboard.unassigned_devices') }}</span>
            </div>
        </div>

        <!-- Chart + recent activity -->
        <div class="ad-grid-2">
            <div class="ad-card">
                <h5 class="ad-card-title"><i class="fas fa-chart-line"></i> {{ __('app.admin.dashboard.gps_activity_chart') }}</h5>
                <div class="ad-chart-wrap">
                    <canvas id="usageChart"></canvas>
                </div>
            </div>
            <div class="ad-card">
                <div class="ad-card-head">
                    <h5 class="ad-card-title mb-0"><i class="fas fa-history"></i> {{ __('app.admin.dashboard.recent_activity') }}</h5>
                    @can('permission', 'activity.view')
                        @if(Route::has('admin.activity-log.index'))
                            <a href="{{ route(request()->routeIs('client.*') ? 'client.activity-log.index' : 'admin.activity-log.index') }}"
                               class="ad-btn ad-btn--secondary flex-shrink-0" style="padding: 6px 12px; font-size: 12.5px;">
                                {{ __('app.admin.dashboard.view_all') }}
                            </a>
                        @endif
                    @endcan
                </div>
                <div class="ad-timeline">
                    @forelse($recentActivities as $activity)
                        <div class="ad-timeline-item">
                            <div class="ad-timeline-time">
                                <x-admin.ltr>{{ $activity['time']?->diffForHumans() }}</x-admin.ltr>
                            </div>
                            <div class="ad-timeline-dot" style="background: {{ $activity['color'] }};"></div>
                            <div>
                                <p class="ad-timeline-title">{{ $activity['title'] }}</p>
                                <p class="ad-timeline-desc">{{ Str::limit($activity['desc'], 60) }}</p>
                            </div>
                        </div>
                    @empty
                        <p class="ad-empty mb-0">{{ __('app.admin.dashboard.no_recent_activity') }}</p>
                    @endforelse
                </div>
            </div>
        </div>

        <!-- Recent devices + fleet snapshot -->
        <div class="ad-grid-2 ad-grid-2--wide">
            <div class="ad-card">
                <div class="ad-card-head">
                    <h5 class="ad-card-title mb-0"><i class="fas fa-list"></i> {{ __('app.admin.dashboard.recent_devices') }}</h5>
                    <a href="{{ route('admin.devices.index') }}" class="ad-btn ad-btn--secondary" style="padding: 6px 12px; font-size: 12.5px;">
                        {{ __('app.admin.dashboard.view_all') }}
                    </a>
                </div>
                <div class="ad-table-wrap">
                    <table class="ad-table">
                        <thead>
                        <tr>
                            <th>IMEI</th>
                            <th>{{ __('app.admin.devices.name') }}</th>
                            <th>{{ __('app.admin.devices.user') }}</th>
                            <th>{{ __('app.common.status') }}</th>
                            <th>{{ __('app.admin.devices.last_known') }}</th>
                            <th></th>
                        </tr>
                        </thead>
                        <tbody>
                        @forelse($recentDevices as $d)
                            @php $status = $dashboardService->deviceStatusLabel($d); @endphp
                            <tr>
                                <td><x-admin.ltr tag="code" class="bg-light p-1 rounded">{{ Str::limit($d->imei, 15) }}</x-admin.ltr></td>
                                <td>{{ $d->name ?? 'Unnamed' }}</td>
                                <td>
                                    <div class="d-flex align-items-center gap-2">
                                        @if($d->user)
                                            @include('partials.user-avatar', ['user' => $d->user, 'size' => 26, 'class' => 'ad-mini-avatar'])
                                        @else
                                            <div class="user-avatar ad-mini-avatar" style="width: 26px; height: 26px; font-size: 11px;">U</div>
                                        @endif
                                        <span>{{ $d->user?->name ?? 'Unassigned' }}</span>
                                    </div>
                                </td>
                                <td>
                                    <span class="badge-status {{ $status['class'] }}">{{ $status['label'] }}</span>
                                </td>
                                <td>
                                    @if($d->latestLocation?->recorded_at)
                                        <x-admin.ltr class="text-muted" title="{{ app_datetime_format($d->latestLocation->recorded_at, 'log') }}">
                                            {{ $d->latestLocation->recorded_at->diffForHumans() }}
                                        </x-admin.ltr>
                                    @else
                                        <span class="text-muted">—</span>
                                    @endif
                                </td>
                                <td>
                                    <a href="{{ route('admin.devices.edit', $d) }}" class="btn btn-sm btn-outline-primary" title="{{ __('app.common.edit') }}">
                                        <i class="fas fa-edit"></i>
                                    </a>
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="6" class="text-center text-muted py-4">No devices registered.</td></tr>
                        @endforelse
                        </tbody>
                    </table>
                </div>
            </div>

            <div class="ad-card">
                <h5 class="ad-card-title"><i class="fas fa-map-marked-alt"></i> {{ __('app.admin.dashboard.fleet_map') }}</h5>
                <div class="ad-map-preview">
                    <div class="ad-map-overlay">
                        <h6 class="mb-1">{{ __('app.admin.dashboard.devices_online', ['count' => number_format($onlineNow)]) }}</h6>
                        <p class="mb-0 small opacity-75">
                            @if($lastGpsAt)
                                {{ __('app.admin.dashboard.last_gps') }}: <x-admin.ltr>{{ $lastGpsAt->diffForHumans() }}</x-admin.ltr>
                            @else
                                {{ __('app.admin.dashboard.idle') }}
                            @endif
                        </p>
                    </div>
                </div>
                <div class="mt-3">
                    <div class="ad-row-between">
                        <span class="text-muted small">{{ __('app.admin.dashboard.gps_points_today') }}</span>
                        <span class="fw-semibold small">{{ number_format($dataPointsToday) }}</span>
                    </div>
                    <div class="ad-row-between">
                        <span class="text-muted small">GPS points (7d)</span>
                        <span class="fw-semibold small">{{ number_format($dataPointsWeek) }}</span>
                    </div>
                    <div class="ad-row-between">
                        <span class="text-muted small">Admins</span>
                        <span class="fw-semibold small">{{ $totalAdmins }}</span>
                    </div>
                    <hr class="my-2">
                    <h6 class="small text-muted text-uppercase mb-2">{{ __('app.admin.dashboard.event_breakdown') }}</h6>
                    @forelse($eventsByType as $row)
                        <div class="ad-event-pill">
                            <span>{{ \App\Models\VehicleEvent::make(['type' => $row->type])->typeLabel() }}</span>
                            <span class="ad-badge-count">{{ $row->total }}</span>
                        </div>
                    @empty
                        <p class="ad-empty mb-0" style="padding: 12px 0;">{{ __('app.admin.dashboard.no_events') }}</p>
                    @endforelse
                </div>
            </div>
        </div>
    </div>
@endsection

@push('scripts')
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const chartLabels = @json($chart['labels']);
            const gpsPings = @json($chart['gpsPings']);
            const activeDevices = @json($chart['activeDevices']);
            const ctx = document.getElementById('usageChart');
            if (ctx && typeof Chart !== 'undefined') {
                new Chart(ctx.getContext('2d'), {
                    type: 'line',
                    data: {
                        labels: chartLabels,
                        datasets: [{
                            label: 'GPS data points',
                            data: gpsPings,
                            borderColor: '#007AFF',
                            backgroundColor: 'rgba(0, 122, 255, 0.1)',
                            tension: 0.35,
                            fill: true
                        }, {
                            label: 'Devices reporting',
                            data: activeDevices,
                            borderColor: '#34C759',
                            backgroundColor: 'rgba(52, 199, 89, 0.08)',
                            tension: 0.35,
                            fill: true
                        }]
                    },
                    options: {
                        responsive: true,
                        maintainAspectRatio: false,
                        plugins: { legend: { position: 'top', labels: { color: '#1D1D1F' } } },
                        scales: {
                            x: { grid: { display: false }, ticks: { color: '#6E6E73', maxTicksLimit: 10 } },
                            y: { beginAtZero: true, grid: { color: '#E5E5EA' }, ticks: { color: '#6E6E73' } }
                        }
                    }
                });
            }
        });
    </script>
@endpush
