@extends('admin.layouts.app')

@section('title', __('app.admin.dashboard.title') . ' - ' . __('app.brand'))

@push('styles')
    <style>
        .dashboard-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 2rem;
            padding-bottom: 1rem;
            border-bottom: 1px solid var(--admin-border);
            flex-wrap: wrap;
            gap: 1rem;
        }
        .dashboard-header h1 {
            color: var(--admin-text);
            font-weight: 700;
            margin: 0;
            display: flex;
            align-items: center;
            gap: 0.75rem;
        }
        .dashboard-header h1 i { color: var(--admin-primary); }
        .quick-actions { display: flex; gap: 0.75rem; flex-wrap: wrap; }
        .quick-action-btn {
            padding: 0.5rem 1rem;
            border-radius: 10px;
            background: var(--admin-primary);
            color: white;
            border: none;
            font-weight: 500;
            transition: all 0.3s ease;
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            text-decoration: none;
        }
        .quick-action-btn:hover { transform: translateY(-2px); box-shadow: 0 6px 20px rgba(25, 118, 210, 0.3); color: #fff; }
        .quick-action-btn.secondary {
            background: var(--admin-card);
            color: var(--admin-text);
            border: 1px solid var(--admin-border);
        }
        .chart-container { height: 300px; position: relative; }
        .activity-timeline {
            list-style: none;
            padding: 0;
            margin: 0;
            max-height: 420px;
            overflow-y: auto;
        }
        .activity-item {
            display: flex;
            gap: 1rem;
            padding: 1rem 0;
            border-bottom: 1px solid var(--admin-border);
        }
        .activity-item:last-child { border-bottom: none; }
        .activity-icon {
            width: 40px;
            height: 40px;
            border-radius: 10px;
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            flex-shrink: 0;
        }
        .activity-title { font-weight: 600; color: var(--admin-text); margin-bottom: 0.25rem; }
        .activity-desc { color: var(--admin-text-light); font-size: 0.875rem; margin-bottom: 0.25rem; }
        .activity-time { color: var(--admin-text-light); font-size: 0.75rem; }
        .map-preview {
            height: 220px;
            background: linear-gradient(135deg, #1e3a5f 0%, #0d1b2a 100%);
            border-radius: 12px;
            position: relative;
            overflow: hidden;
        }
        .map-overlay {
            position: absolute;
            inset: 0;
            background: rgba(0, 0, 0, 0.25);
            display: flex;
            flex-direction: column;
            justify-content: flex-end;
            padding: 1.25rem;
            color: white;
        }
        .system-status {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
            gap: 1rem;
            margin-bottom: 1.5rem;
        }
        .status-item {
            background: var(--admin-card);
            border: 1px solid var(--admin-border);
            border-radius: 12px;
            padding: 1rem;
            text-align: center;
        }
        .status-item.up { border-color: var(--admin-success); background: rgba(16, 185, 129, 0.05); }
        .status-item.down { border-color: var(--admin-danger); background: rgba(239, 68, 68, 0.05); }
        .status-label { font-size: 0.75rem; color: var(--admin-text-light); margin-bottom: 0.25rem; }
        .status-value { font-weight: 600; color: var(--admin-text); font-size: 0.85rem; }
        .status-indicator {
            width: 8px;
            height: 8px;
            border-radius: 50%;
            display: inline-block;
            margin-inline-end: 0.5rem;
        }
        .status-indicator.up { background: var(--admin-success); }
        .status-indicator.down { background: var(--admin-danger); }
        .stats-change { font-size: 0.75rem; margin-top: 0.35rem; }
        .stats-change.positive { color: var(--admin-success); }
        .stats-change.negative { color: var(--admin-danger); }
        .event-pill {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 0.5rem 0;
            border-bottom: 1px solid var(--admin-border);
            font-size: 0.85rem;
        }
        .event-pill:last-child { border-bottom: none; }
        .badge-status { padding: 0.35rem 0.6rem; border-radius: 999px; display: inline-block; font-size: 0.8rem; font-weight: 500; }
        .badge-status.badge-active { background: #dff7e0; color: #2f7d3a; }
        .badge-status.badge-inactive { background: #f3f4f6; color: #6b7280; }
        .badge-status.badge-blocked { background: #fee2e2; color: #b91c1c; }
        .badge-status.badge-offline { background: #fef3c7; color: #92400e; }
    </style>
@endpush

@section('content')
    <div class="dashboard-header">
        <div>
            <h1><i class="fas fa-tachometer-alt"></i> {{ __('app.admin.dashboard.title') }}</h1>
            <p class="text-muted mb-0">{{ __('app.admin.dashboard.subtitle') }}</p>
        </div>
        <div class="quick-actions">
            <a href="{{ route('admin.devices.create') }}" class="quick-action-btn">
                <i class="fas fa-plus"></i> {{ __('app.admin.dashboard.add_device') }}
            </a>
            <a href="{{ route('admin.users.index') }}" class="quick-action-btn secondary">
                <i class="fas fa-users"></i> {{ __('app.common.users') }}
            </a>
        </div>
    </div>

    <div class="system-status">
        <div class="status-item {{ $dbOk ? 'up' : 'down' }}">
            <div class="status-label">{{ __('app.admin.dashboard.database') }}</div>
            <div class="status-value">
                <span class="status-indicator {{ $dbOk ? 'up' : 'down' }}"></span>
                {{ $dbOk ? __('app.admin.dashboard.connected') : __('app.admin.dashboard.error') }}
            </div>
        </div>
        <div class="status-item {{ $gpsLive ? 'up' : 'down' }}">
            <div class="status-label">{{ __('app.admin.dashboard.gps_ingest') }}</div>
            <div class="status-value">
                <span class="status-indicator {{ $gpsLive ? 'up' : 'down' }}"></span>
                {{ $gpsLive ? __('app.common.live') : __('app.admin.dashboard.idle') }}
            </div>
        </div>
        <div class="status-item up">
            <div class="status-label">{{ __('app.admin.dashboard.api') }}</div>
            <div class="status-value"><span class="status-indicator up"></span> {{ __('app.admin.dashboard.operational') }}</div>
        </div>
        <a href="{{ route('admin.contact-messages.index') }}" class="status-item {{ $pendingContacts > 0 ? 'down' : 'up' }} text-decoration-none">
            <div class="status-label">{{ __('app.admin.dashboard.contact_inbox') }}</div>
            <div class="status-value">
                <span class="status-indicator {{ $pendingContacts > 0 ? 'down' : 'up' }}"></span>
                {{ __('app.admin.dashboard.pending', ['count' => $pendingContacts]) }}
            </div>
        </a>
    </div>

    <div class="row mb-4">
        <div class="col-xl-3 col-md-6 mb-4">
            <div class="premium-card stats-card">
                <div class="stats-icon" style="background: linear-gradient(135deg, var(--admin-primary), #2196F3);">
                    <i class="fas fa-users"></i>
                </div>
                <div class="stats-value">{{ number_format($totalUsers) }}</div>
                <div class="stats-label">{{ __('app.admin.dashboard.fleet_users') }}</div>
                <div class="stats-change {{ $userGrowth['positive'] ? 'positive' : 'negative' }}">
                    <i class="fas fa-{{ $userGrowth['positive'] ? 'arrow-up' : 'arrow-down' }} me-1"></i>
                    {{ $userGrowth['label'] }}
                </div>
            </div>
        </div>
        <div class="col-xl-3 col-md-6 mb-4">
            <div class="premium-card stats-card">
                <div class="stats-icon" style="background: linear-gradient(135deg, var(--admin-success), #059669);">
                    <i class="fas fa-satellite"></i>
                </div>
                <div class="stats-value">{{ number_format($totalDevices) }}</div>
                <div class="stats-label">{{ __('app.admin.dashboard.total_devices') }}</div>
                <small class="text-muted d-block">{{ __('app.admin.dashboard.active_inactive', ['active' => $activeDevices, 'inactive' => $inactiveDevices]) }}</small>
                <div class="stats-change {{ $deviceGrowth['positive'] ? 'positive' : 'negative' }}">
                    <i class="fas fa-{{ $deviceGrowth['positive'] ? 'arrow-up' : 'arrow-down' }} me-1"></i>
                    {{ $deviceGrowth['label'] }}
                </div>
            </div>
        </div>
        <div class="col-xl-3 col-md-6 mb-4">
            <div class="premium-card stats-card">
                <div class="stats-icon" style="background: linear-gradient(135deg, var(--admin-warning), #D97706);">
                    <i class="fas fa-signal"></i>
                </div>
                <div class="stats-value">{{ number_format($onlineNow) }}</div>
                <div class="stats-label">{{ __('app.admin.dashboard.online_now', ['minutes' => \App\Services\AdminDashboardService::ONLINE_MINUTES]) }}</div>
                <small class="text-muted d-block">{{ __('app.admin.dashboard.moving_offline', ['moving' => $movingNow, 'offline' => $offlineDevices]) }}</small>
                <div class="stats-change {{ $onlineChange['positive'] ? 'positive' : 'negative' }}">
                    <i class="fas fa-{{ $onlineChange['positive'] ? 'arrow-up' : 'arrow-down' }} me-1"></i>
                    {{ $onlineChange['label'] }}
                </div>
            </div>
        </div>
        <div class="col-xl-3 col-md-6 mb-4">
            <div class="premium-card stats-card">
                <div class="stats-icon" style="background: linear-gradient(135deg, #8B5CF6, #7C3AED);">
                    <i class="fas fa-credit-card"></i>
                </div>
                <div class="stats-value">{{ number_format($activeSubscriptions) }}</div>
                <div class="stats-label">{{ __('app.admin.dashboard.active_subscriptions') }}</div>
                <small class="text-muted d-block">{{ __('app.admin.dashboard.subs_total_expired', ['total' => $totalSubscriptions, 'expired' => $expiredSubscriptions]) }}</small>
            </div>
        </div>
    </div>

    <div class="row mb-4">
        <div class="col-xl-2 col-md-4 col-6 mb-3">
            <div class="premium-card p-3 text-center">
                <div class="h4 mb-0 text-danger">{{ number_format($alertsToday) }}</div>
                <small class="text-muted">{{ __('app.admin.dashboard.alerts_today') }}</small>
            </div>
        </div>
        <div class="col-xl-2 col-md-4 col-6 mb-3">
            <div class="premium-card p-3 text-center">
                <div class="h4 mb-0 text-warning">{{ number_format($alertsWeek) }}</div>
                <small class="text-muted">{{ __('app.admin.dashboard.alerts_week') }}</small>
            </div>
        </div>
        <div class="col-xl-2 col-md-4 col-6 mb-3">
            <div class="premium-card p-3 text-center">
                <div class="h4 mb-0">{{ number_format($dataPointsToday) }}</div>
                <small class="text-muted">{{ __('app.admin.dashboard.gps_points_today') }}</small>
            </div>
        </div>
        <div class="col-xl-2 col-md-4 col-6 mb-3">
            <div class="premium-card p-3 text-center">
                <div class="h4 mb-0">{{ number_format($geofenceCount) }}</div>
                <small class="text-muted">{{ __('app.admin.dashboard.geofences') }}</small>
            </div>
        </div>
        <div class="col-xl-2 col-md-4 col-6 mb-3">
            <div class="premium-card p-3 text-center">
                <div class="h4 mb-0">{{ number_format($blockedDevices) }}</div>
                <small class="text-muted">{{ __('app.admin.dashboard.blocked_devices') }}</small>
            </div>
        </div>
        <div class="col-xl-2 col-md-4 col-6 mb-3">
            <div class="premium-card p-3 text-center">
                <div class="h4 mb-0">{{ number_format($unassignedDevices) }}</div>
                <small class="text-muted">{{ __('app.admin.dashboard.unassigned_devices') }}</small>
            </div>
        </div>
    </div>

    <div class="row mb-4">
        <div class="col-xl-8 mb-4">
            <div class="premium-card">
                <div class="card-header">
                    <h5 class="card-title"><i class="fas fa-chart-line"></i> {{ __('app.admin.dashboard.gps_activity_chart') }}</h5>
                </div>
                <div class="chart-container">
                    <canvas id="usageChart"></canvas>
                </div>
            </div>
        </div>
        <div class="col-xl-4 mb-4">
            <div class="premium-card h-100">
                <div class="card-header d-flex justify-content-between align-items-center gap-2">
                    <h5 class="card-title mb-0"><i class="fas fa-history"></i> {{ __('app.admin.dashboard.recent_activity') }}</h5>
                    @can('permission', 'activity.view')
                        @if(Route::has('admin.activity-log.index'))
                            <a href="{{ route(request()->routeIs('client.*') ? 'client.activity-log.index' : 'admin.activity-log.index') }}"
                               class="btn btn-sm btn-outline-primary flex-shrink-0">
                                {{ __('app.admin.dashboard.view_all') }}
                            </a>
                        @endif
                    @endcan
                </div>
                <ul class="activity-timeline">
                    @forelse($recentActivities as $activity)
                        <li class="activity-item">
                            <div class="activity-icon" style="background: {{ $activity['color'] }};">
                                <i class="fas {{ $activity['icon'] }}"></i>
                            </div>
                            <div class="activity-content">
                                <div class="activity-title">{{ $activity['title'] }}</div>
                                <div class="activity-desc">{{ Str::limit($activity['desc'], 60) }}</div>
                                <div class="activity-time">
                                    <x-admin.ltr>
                                        {{ app_datetime_format($activity['time']) }}
                                        · {{ $activity['time']?->diffForHumans() }}
                                    </x-admin.ltr>
                                </div>
                            </div>
                        </li>
                    @empty
                        <li class="text-muted text-center py-4">{{ __('app.admin.dashboard.no_recent_activity') }}</li>
                    @endforelse
                </ul>
            </div>
        </div>
    </div>

    <div class="row">
        <div class="col-xl-8 mb-4">
            <div class="premium-card">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <h5 class="card-title mb-0"><i class="fas fa-list"></i> {{ __('app.admin.dashboard.recent_devices') }}</h5>
                    <a href="{{ route('admin.devices.index') }}" class="btn btn-sm btn-outline-primary">{{ __('app.admin.dashboard.view_all') }}</a>
                </div>
                <div class="table-responsive">
                    <table class="table table-premium">
                        <thead>
                        <tr>
                            <th>IMEI</th>
                            <th>{{ __('app.admin.devices.name') }}</th>
                            <th>User</th>
                            <th>Status</th>
                            <th>Last seen</th>
                            <th>Actions</th>
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
                                        <div class="user-avatar" style="width: 28px; height: 28px; font-size: 0.75rem;">
                                            {{ strtoupper(substr($d->user?->name ?? 'U', 0, 1)) }}
                                        </div>
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
                                    <a href="{{ route('admin.devices.edit', $d) }}" class="btn btn-sm btn-outline-primary" title="Edit">
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
        </div>
        <div class="col-xl-4 mb-4">
            <div class="premium-card h-100">
                <div class="card-header">
                    <h5 class="card-title"><i class="fas fa-map-marked-alt"></i> Fleet snapshot</h5>
                </div>
                <div class="map-preview">
                    <div class="map-overlay">
                        <h6 class="mb-1">{{ number_format($onlineNow) }} devices online</h6>
                        <p class="mb-0 small opacity-75">
                            @if($lastGpsAt)
                                {{ __('app.admin.dashboard.last_gps') }}: <x-admin.ltr>{{ $lastGpsAt->diffForHumans() }}</x-admin.ltr>
                            @else
                                No GPS data yet
                            @endif
                        </p>
                    </div>
                </div>
                <div class="mt-3">
                    <div class="d-flex justify-content-between mb-2">
                        <span class="text-muted">GPS points today</span>
                        <span class="fw-semibold">{{ number_format($dataPointsToday) }}</span>
                    </div>
                    <div class="d-flex justify-content-between mb-2">
                        <span class="text-muted">GPS points (7d)</span>
                        <span class="fw-semibold">{{ number_format($dataPointsWeek) }}</span>
                    </div>
                    <div class="d-flex justify-content-between mb-2">
                        <span class="text-muted">Admins</span>
                        <span class="fw-semibold">{{ $totalAdmins }}</span>
                    </div>
                    <hr>
                    <h6 class="small text-muted text-uppercase mb-2">Alerts by type (7d)</h6>
                    @forelse($eventsByType as $row)
                        <div class="event-pill">
                            <span>{{ \App\Models\VehicleEvent::make(['type' => $row->type])->typeLabel() }}</span>
                            <span class="badge bg-secondary">{{ $row->total }}</span>
                        </div>
                    @empty
                        <p class="text-muted small mb-0">No vehicle events logged yet.</p>
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
                            borderColor: 'var(--admin-primary)',
                            backgroundColor: 'rgba(25, 118, 210, 0.1)',
                            tension: 0.35,
                            fill: true
                        }, {
                            label: 'Devices reporting',
                            data: activeDevices,
                            borderColor: 'var(--admin-success)',
                            backgroundColor: 'rgba(16, 185, 129, 0.08)',
                            tension: 0.35,
                            fill: true
                        }]
                    },
                    options: {
                        responsive: true,
                        maintainAspectRatio: false,
                        plugins: { legend: { position: 'top', labels: { color: 'var(--admin-text)' } } },
                        scales: {
                            x: { ticks: { color: 'var(--admin-text-light)', maxTicksLimit: 10 } },
                            y: { beginAtZero: true, ticks: { color: 'var(--admin-text-light)' } }
                        }
                    }
                });
            }
        });
    </script>
@endpush
