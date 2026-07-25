@extends('user.layout_user')

@section('title', __('app.user.dashboard.title') . ' - ' . __('app.brand'))

@section('content')

@php
    // Prefer server-built buckets so KPIs and donut always share one snapshot.
    $statusDonut = $statusDonut ?? \App\Services\UserDashboardService::statusDonutFromFleetCounts($fleetCounts ?? []);
@endphp

<div class="ud-dashboard ud-fade-in">
    <header class="mb-4">
        <h1 class="ud-page-title">{{ __('app.user.dashboard.title') }}</h1>
        <p class="ud-page-sub">
            {{ __('app.user.dashboard.welcome_back') }}
            {{ $trackerDisplayName ?? auth()->user()?->name }} ·
            <span id="udLiveClock"></span>
        </p>
    </header>

    @if(isset($trackerAccountActive) && ! $trackerAccountActive)
        <div class="ud-alert ud-alert--warn">
            <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" fill="none" viewBox="0 0 24 24" stroke-width="1.75" stroke="#FF9F0A" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M12 9v3.75m-9.303 3.376c-.866 1.5.217 3.374 1.948 3.374h14.71c1.73 0 2.813-1.874 1.948-3.374L13.949 3.378c-.866-1.5-3.032-1.5-3.898 0L2.697 16.126zM12 15.75h.007v.008H12v-.008z"/></svg>
            <div>
                <strong>{{ __('app.user.dashboard.tracker_unavailable_title') }}</strong>
                <div>{{ __('app.user.dashboard.tracker_unavailable_msg') }}</div>
            </div>
        </div>
    @endif

    @if(session('tracker_unavailable'))
        <div class="ud-alert ud-alert--warn">{{ __('app.user.dashboard.tracker_route_blocked') }}</div>
    @endif

    @if(!empty($emailVerified))
        <div class="ud-alert ud-alert--ok">
            <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" fill="none" viewBox="0 0 24 24" stroke-width="1.75" stroke="#34C759" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M9 12.75L11.25 15 15 9.75M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
            <div>
                <strong>{{ __('app.user.dashboard.email_verified') }}</strong>
                <div>{{ __('app.user.dashboard.email_verified_msg') }}</div>
            </div>
        </div>
    @endif

    @include('user.partials.dashboard-maintenance-due')

    @if(($needsSubscriptionCount ?? 0) > 0)
        <div class="ud-alert ud-alert--warn">
            <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" fill="none" viewBox="0 0 24 24" stroke-width="1.75" stroke="#FF9F0A" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M12 9v3.75m-9.303 3.376c-.866 1.5.217 3.374 1.948 3.374h14.71c1.73 0 2.813-1.874 1.948-3.374L13.949 3.378c-.866-1.5-3.032-1.5-3.898 0L2.697 16.126zM12 15.75h.007v.008H12v-.008z"/></svg>
            <div>
                <strong>{{ __('app.user.devices.subscribe_for_map_title') }}</strong>
                <div>{{ trans_choice('app.user.dashboard.subscribe_for_map_banner', $needsSubscriptionCount, ['count' => $needsSubscriptionCount]) }}</div>
                <div class="mt-2">
                    <a href="{{ route('user.devices.index') }}" class="ud-btn ud-btn--secondary">{{ __('app.user.dashboard.view_devices') }}</a>
                </div>
            </div>
        </div>
    @endif

    {{-- Live fleet KPIs --}}
    <div class="ud-kpi-grid">
        <div class="ud-card">
            <div class="ud-kpi-head">
                <p class="ud-kpi-title">{{ __('app.user.devices.total') }}</p>
            </div>
            <div class="ud-kpi-value" data-count="{{ $totalDevices }}">0</div>
            <p class="ud-kpi-meta">{{ $activeDevices }} {{ strtolower(__('app.user.dashboard.active_vehicles')) }}</p>
        </div>
        <div class="ud-card">
            <div class="ud-kpi-head">
                <p class="ud-kpi-title">{{ __('app.user.devices.online_now') }}</p>
            </div>
            <div class="ud-kpi-value" data-count="{{ ($statusDonut['running'] ?? 0) + ($statusDonut['parked'] ?? 0) + ($statusDonut['idle'] ?? 0) }}">0</div>
            <p class="ud-kpi-meta"><span class="up">{{ $onlinePercent }}%</span> {{ __('app.user.dashboard.of_fleet') }}</p>
        </div>
        <div class="ud-card">
            <div class="ud-kpi-head">
                <p class="ud-kpi-title">{{ __('app.user.devices.moving') }}</p>
            </div>
            <div class="ud-kpi-value" data-count="{{ $statusDonut['running'] ?? $running }}">0</div>
            <p class="ud-kpi-meta">{{ __('app.user.dashboard.offline') }}: {{ $statusDonut['offline'] ?? $offlineNow }}</p>
        </div>
        <div class="ud-card">
            <div class="ud-kpi-head">
                <p class="ud-kpi-title">{{ __('app.user.devices.parked_idle') }}</p>
            </div>
            <div class="ud-kpi-value" data-count="{{ $parkedIdle ?? $parked }}">0</div>
            <p class="ud-kpi-meta">{{ __('app.user.dashboard.idle_stopped', [
                'idle' => (int) (($fleetCounts['idle'] ?? 0) + ($fleetCounts['delayed'] ?? 0) + ($fleetCounts['stale'] ?? 0) + ($fleetCounts['alert'] ?? 0)),
                'stopped' => (int) (($fleetCounts['parked'] ?? 0) + ($fleetCounts['stopped'] ?? 0)),
            ]) }}</p>
        </div>
    </div>

    <div class="ud-kpi-grid">
        <div class="ud-card">
            <div class="ud-kpi-head">
                <p class="ud-kpi-title">{{ __('app.user.dashboard.distance_today') }}</p>
            </div>
            <div class="ud-kpi-value"><span data-deferred="distanceTodayKm" data-count="0">—</span><span style="font-size:18px;font-weight:600;"> km</span></div>
            <p class="ud-kpi-meta">{{ __('app.user.dashboard.today') }}</p>
        </div>
        <div class="ud-card">
            <div class="ud-kpi-head">
                <p class="ud-kpi-title">{{ __('app.user.dashboard.alerts') }}</p>
            </div>
            <div class="ud-kpi-value" data-deferred="activeAlerts" data-count="0">—</div>
            <p class="ud-kpi-meta">{{ __('app.user.dashboard.last_7_days') }}</p>
        </div>
        <div class="ud-card">
            <div class="ud-kpi-head">
                <p class="ud-kpi-title">{{ __('app.user.dashboard.total_distance') }}</p>
            </div>
            <div class="ud-kpi-value"><span data-deferred="totalDistanceKm" data-count="0">—</span><span style="font-size:18px;font-weight:600;"> km</span></div>
            <p class="ud-kpi-meta">{{ __('app.user.dashboard.last_30_days') }}</p>
        </div>
        <div class="ud-card">
            <div class="ud-kpi-head">
                <p class="ud-kpi-title">{{ __('app.user.dashboard.offline') }}</p>
            </div>
            <div class="ud-kpi-value" data-count="{{ $statusDonut['offline'] ?? $offlineNow }}">0</div>
            <p class="ud-kpi-meta">{{ __('app.user.dashboard.not_reporting') }}</p>
        </div>
    </div>

    {{-- Vehicle status (live counts — rendered immediately) --}}
    <div class="ud-card" style="margin-bottom: 24px;">
        <h2 class="ud-card-title">{{ __('app.user.dashboard.vehicle_status') }}</h2>
        <div id="udChartStatus" class="ud-chart ud-chart--loading" aria-busy="true"></div>
    </div>

    {{-- Fleet table — ~5 rows visible, then scroll --}}
    <div class="ud-card">
        <div class="ud-table-toolbar">
            <h2 class="ud-card-title mb-0">{{ __('app.user.dashboard.fleet_overview') }}</h2>
            <div class="d-flex gap-2 flex-wrap">
                <input type="search" id="udFleetSearch" class="ud-table-search" placeholder="{{ __('app.user.dashboard.search_vehicles') }}" aria-label="{{ __('app.user.dashboard.search_vehicles') }}">
                <a href="{{ route('user.devices.index') }}" class="ud-btn ud-btn--secondary">{{ __('app.user.dashboard.view_devices') }}</a>
            </div>
        </div>
        <div class="ud-table-wrap ud-table-wrap--fleet">
            <table class="ud-table" id="udFleetTable">
                <thead>
                    <tr>
                        <th>{{ __('app.user.dashboard.vehicle') }}</th>
                        <th>{{ __('app.user.dashboard.status') }}</th>
                        <th>{{ __('app.user.dashboard.location') }}</th>
                        <th>{{ __('app.user.dashboard.speed') }}</th>
                        <th>{{ __('app.user.dashboard.battery') }}</th>
                        <th>{{ __('app.user.dashboard.last_update') }}</th>
                        <th></th>
                    </tr>
                </thead>
                <tbody>
        @include('user.partials.dashboard-recent-vehicles', [
            'alertDeviceIds' => $alertDeviceIds ?? collect(),
            'deviceAccessMap' => $deviceAccessMap ?? [],
        ])
                </tbody>
            </table>
        </div>
    </div>
</div>
@endsection

@push('scripts')
<script>
    window.USER_DASHBOARD_CONFIG = {
        metricsUrl: @json(route('user.dashboard.metrics-json')),
        charts: {
            statusDonut: @json($statusDonut),
        },
    };
</script>
<script src="{{ protected_js('user-dashboard.js') }}"></script>
<script>
    (function () {
        function tick() {
            const el = document.getElementById('udLiveClock');
            if (!el) return;
            el.textContent = new Date().toLocaleString(@json(app()->getLocale()), {
                weekday: 'short', month: 'short', day: 'numeric',
                hour: '2-digit', minute: '2-digit', second: '2-digit'
            });
        }
        tick();
        setInterval(tick, 1000);
    })();
</script>
@endpush
