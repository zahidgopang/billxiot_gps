@extends('user.layout_user')

@section('title', __('app.user.dashboard.title') . ' - ' . __('app.brand'))

@section('content')

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

    {{-- KPI row 1 — matches /user/devices summary stats --}}
    <div class="ud-kpi-grid">
        <div class="ud-card">
            <div class="ud-kpi-head">
                <p class="ud-kpi-title">{{ __('app.user.devices.total') }}</p>
                <div class="ud-kpi-icon"><svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" fill="none" viewBox="0 0 24 24" stroke-width="1.75" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M8.25 18.75a1.5 1.5 0 01-3 0m3 0a1.5 1.5 0 00-3 0m3 0h6m-9 0H3.375a1.125 1.125 0 01-1.125-1.125V14.25m17.25 4.5a1.5 1.5 0 01-3 0m3 0a1.5 1.5 0 00-3 0m3 0h1.125c.621 0 1.129-.504 1.09-1.124a17.902 17.902 0 00-3.213-9.193 2.056 2.056 0 00-1.58-.86H14.25M16.5 18.75h-2.25m0-11.177v-.958c0-.568-.422-1.048-.987-1.106a48.554 48.554 0 00-10.026 0 1.106 1.106 0 00-.987 1.106v7.635m12-6.677v6.677m0 4.5v-4.5m0 0h-12"/></svg></div>
            </div>
            <div class="ud-kpi-value" data-count="{{ $totalDevices }}">0</div>
            <p class="ud-kpi-meta">{{ $activeDevices }} {{ strtolower(__('app.user.dashboard.active_vehicles')) }}</p>
        </div>
        <div class="ud-card">
            <div class="ud-kpi-head">
                <p class="ud-kpi-title">{{ __('app.user.devices.online_now') }}</p>
                <div class="ud-kpi-icon"><svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" fill="none" viewBox="0 0 24 24" stroke-width="1.75" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M8.288 15.038a5.25 5.25 0 017.424 0M5.106 11.856c3.807-3.808 9.98-3.808 13.788 0M2.34 8.223c5.112-5.112 13.404-5.112 18.516 0"/></svg></div>
            </div>
            <div class="ud-kpi-value" data-count="{{ $onlineNow }}">0</div>
            <p class="ud-kpi-meta"><span class="up">{{ $onlinePercent }}%</span> {{ __('app.user.dashboard.of_fleet') }}</p>
        </div>
        <div class="ud-card">
            <div class="ud-kpi-head">
                <p class="ud-kpi-title">{{ __('app.user.dashboard.offline') }}</p>
                <div class="ud-kpi-icon"><svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" fill="none" viewBox="0 0 24 24" stroke-width="1.75" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M3 3l18 18M9.879 9.88a3 3 0 104.243 4.242"/></svg></div>
            </div>
            <div class="ud-kpi-value" data-count="{{ $offlineNow }}">0</div>
            <p class="ud-kpi-meta">{{ __('app.user.dashboard.not_reporting') }}</p>
        </div>
        <div class="ud-card">
            <div class="ud-kpi-head">
                <p class="ud-kpi-title">{{ __('app.user.dashboard.distance_today') }}</p>
                <div class="ud-kpi-icon"><svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" fill="none" viewBox="0 0 24 24" stroke-width="1.75" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M9 6.75V15m6-6v8.25m.503 3.498l4.875-2.437c.381-.19.622-.58.622-1.006V4.82c0-.836-.88-1.38-1.628-1.006l-3.869 1.934c-.317.159-.69.159-1.006 0L9.503 3.252a1.125 1.125 0 00-1.006 0L3.622 5.689C3.24 5.88 3 6.27 3 6.695V19.18c0 .836.88 1.38 1.628 1.006l3.869-1.934c.317-.159.69-.159 1.006 0l4.994 2.497c.317.158.69.158 1.006 0z"/></svg></div>
            </div>
            <div class="ud-kpi-value"><span data-deferred="distanceTodayKm" data-count="0">—</span><span style="font-size:18px;font-weight:600;"> km</span></div>
            <p class="ud-kpi-meta">{{ __('app.user.dashboard.today') }}</p>
        </div>
    </div>

    {{-- KPI row 2 — live fleet motion (same source as /user/devices) --}}
    <div class="ud-kpi-grid">
        <div class="ud-card">
            <div class="ud-kpi-head"><p class="ud-kpi-title">{{ __('app.user.devices.moving') }}</p></div>
            <div class="ud-kpi-value" data-count="{{ $running }}">0</div>
            <p class="ud-kpi-meta">{{ __('app.user.devices.moving') }}</p>
        </div>
        <div class="ud-card">
            <div class="ud-kpi-head"><p class="ud-kpi-title">{{ __('app.user.devices.parked_idle') }}</p></div>
            <div class="ud-kpi-value" data-count="{{ $parked }}">0</div>
            <p class="ud-kpi-meta">{{ __('app.user.dashboard.idle_stopped', ['idle' => $idle ?? 0, 'stopped' => $fleetCounts['stopped'] ?? 0]) }}</p>
        </div>
        <div class="ud-card">
            <div class="ud-kpi-head"><p class="ud-kpi-title">{{ __('app.user.dashboard.alerts') }}</p></div>
            <div class="ud-kpi-value" data-deferred="activeAlerts" data-count="0">—</div>
            <p class="ud-kpi-meta">{{ __('app.user.dashboard.last_7_days') }}</p>
        </div>
        <div class="ud-card">
            <div class="ud-kpi-head"><p class="ud-kpi-title">{{ __('app.user.dashboard.total_distance') }}</p></div>
            <div class="ud-kpi-value"><span data-deferred="totalDistanceKm" data-count="0">—</span><span style="font-size:18px;font-weight:600;"> km</span></div>
            <p class="ud-kpi-meta">{{ __('app.user.dashboard.last_30_days') }}</p>
        </div>
    </div>

    {{-- Charts (loaded asynchronously) --}}
    <div class="ud-grid-2">
        <div class="ud-card">
            <h2 class="ud-card-title">{{ __('app.user.dashboard.vehicle_status') }}</h2>
            <div id="udChartStatus" class="ud-chart ud-chart--loading" aria-busy="true"></div>
        </div>
        <div class="ud-card">
            <h2 class="ud-card-title">{{ __('app.user.dashboard.vehicle_activity_24h') }}</h2>
            <div id="udChartActivity" class="ud-chart ud-chart--loading" aria-busy="true"></div>
        </div>
    </div>

    <div class="ud-grid-2">
        <div class="ud-card">
            <h2 class="ud-card-title">{{ __('app.user.dashboard.alerts') }}</h2>
            <div id="udChartAlerts" class="ud-chart ud-chart--loading" aria-busy="true"></div>
        </div>
        <div class="ud-card">
            <h2 class="ud-card-title">{{ __('app.user.dashboard.fleet_activity_30d') }}</h2>
            <div id="udChartPerformance" class="ud-chart ud-chart--loading" aria-busy="true"></div>
        </div>
    </div>

    <div class="ud-card" style="margin-bottom: 24px;">
        <h2 class="ud-card-title">{{ __('app.user.dashboard.weekly_distance') }}</h2>
        <div id="udChartWeekly" class="ud-chart ud-chart--loading" aria-busy="true"></div>
    </div>

    {{-- Activity --}}
    <div class="ud-card" style="margin-bottom: 24px;">
        <div class="d-flex justify-content-between align-items-center mb-3">
            <h2 class="ud-card-title mb-0">{{ __('app.user.dashboard.recent_activity') }}</h2>
            <button type="button" class="ud-btn ud-btn--secondary" id="udRefreshActivity">{{ __('app.user.dashboard.refresh') }}</button>
        </div>
        <div class="ud-timeline">
            @include('user.partials.dashboard-activity-feed')
        </div>
    </div>

    {{-- Fleet table --}}
    <div class="ud-card">
        <div class="ud-table-toolbar">
            <h2 class="ud-card-title mb-0">{{ __('app.user.dashboard.fleet_overview') }}</h2>
            <div class="d-flex gap-2 flex-wrap">
                <input type="search" id="udFleetSearch" class="ud-table-search" placeholder="{{ __('app.user.dashboard.search_vehicles') }}" aria-label="{{ __('app.user.dashboard.search_vehicles') }}">
                <a href="{{ route('user.devices.index') }}" class="ud-btn ud-btn--secondary">{{ __('app.user.dashboard.view_devices') }}</a>
            </div>
        </div>
        <div class="ud-table-wrap">
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
