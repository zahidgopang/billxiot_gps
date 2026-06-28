@extends($layout)

@section('title', __('app.tracking.live_title') . ' - ' . __('app.brand'))

@push('styles')
    <link rel="stylesheet" href="{{ asset('css/fleet-map.css') }}?v={{ filemtime(public_path('css/fleet-map.css')) }}">
    <style>
        @if($panel === 'user')
        body.gt-page-active { overflow: hidden; }
        body.gt-page-active .content-wrap {
            height: calc(100dvh - 96px);
            max-height: calc(100dvh - 96px);
            overflow: hidden;
            padding: 0 !important;
        }
        @else
        .content-wrap { padding: 0 !important; }
        .footer-premium { display: none; }
        @endif

        .gt-page {
            display: flex;
            flex-direction: column;
            height: @if($panel === 'user') 100% @else calc(100vh - 64px) @endif;
            min-height: 420px;
            background: var(--light-bg, #f8fafc);
        }

        .gt-toolbar {
            flex-shrink: 0;
            display: flex;
            flex-wrap: wrap;
            align-items: center;
            justify-content: space-between;
            gap: 0.75rem;
            padding: 0.75rem 1rem;
            background: #fff;
            border-bottom: 1px solid var(--border-color, #e0e0e0);
            z-index: 3;
        }

        .gt-toolbar__title {
            display: flex;
            flex-wrap: wrap;
            align-items: center;
            gap: 0.5rem;
            font-weight: 700;
        }

        .gt-body {
            flex: 1;
            display: flex;
            min-height: 0;
        }

        .gt-sidebar {
            width: min(360px, 92vw);
            flex-shrink: 0;
            display: flex;
            flex-direction: column;
            background: #fff;
            border-inline-end: 1px solid var(--border-color, #e0e0e0);
            z-index: 2;
        }

        .gt-sidebar-head {
            padding: 0.75rem;
            border-bottom: 1px solid var(--border-color, #e0e0e0);
        }

        .gt-sidebar-actions {
            display: flex;
            gap: 0.35rem;
            margin-top: 0.5rem;
        }

        .gt-vehicle-list {
            flex: 1;
            overflow-y: auto;
            padding: 0.35rem 0;
        }

        .gt-vehicle-row {
            display: flex;
            align-items: flex-start;
            gap: 0.5rem;
            padding: 0.55rem 0.75rem;
            margin: 0;
            cursor: pointer;
            border-bottom: 1px solid rgba(15, 23, 42, 0.06);
        }

        .gt-vehicle-row:hover { background: rgba(25, 118, 210, 0.04); }

        .gt-status-dot {
            width: 10px;
            height: 10px;
            border-radius: 50%;
            margin-top: 0.35rem;
            flex-shrink: 0;
        }

        .gt-vehicle-info { flex: 1; min-width: 0; }

        .gt-vehicle-title {
            display: block;
            font-weight: 600;
            font-size: 0.875rem;
            color: var(--text-primary, #1e293b);
        }

        .gt-vehicle-meta {
            display: block;
            font-size: 0.75rem;
            color: var(--text-secondary, #64748b);
            margin-top: 0.15rem;
            line-height: 1.35;
        }

        .gt-map-wrap {
            position: relative;
            flex: 1;
            min-width: 0;
        }

        #gtMap {
            position: absolute;
            inset: 0;
            background: #e8eef4;
        }

        .gt-map-controls {
            position: absolute;
            top: 12px;
            inset-inline-end: 12px;
            display: flex;
            flex-direction: column;
            gap: 8px;
            z-index: 5;
        }

        .gt-map-controls .btn {
            width: 42px;
            height: 42px;
            padding: 0;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            box-shadow: 0 4px 14px rgba(15, 23, 42, 0.15);
        }

        .gt-map-controls .btn.active {
            background: var(--bs-primary, #1976d2);
            color: #fff;
        }

        .gt-map-error {
            position: absolute;
            inset: 0;
            display: flex;
            align-items: center;
            justify-content: center;
            background: rgba(254, 242, 242, 0.94);
            z-index: 4;
        }

        .gt-map-error[hidden] { display: none !important; }

        @media (max-width: 768px) {
            .gt-body { flex-direction: column; }
            .gt-sidebar {
                width: 100%;
                max-height: 42vh;
                border-inline-end: none;
                border-bottom: 1px solid var(--border-color, #e0e0e0);
            }
        }
    </style>
@endpush

@section('content')
    <div class="gt-page">
        <div class="gt-toolbar">
            <div class="gt-toolbar__title">
                <i class="fas fa-map-marked-alt text-primary"></i>
                <span>{{ __('app.tracking.live_title') }}</span>
            </div>
        </div>
        @include('tracking.partials.hub-nav')

        <div class="gt-body">
            <aside class="gt-sidebar" aria-label="{{ __('app.tracking.vehicle_list') }}">
                <div class="gt-sidebar-head">
                    <input type="search" id="gtSearch" class="form-control form-control-sm"
                           placeholder="{{ __('app.tracking.search_vehicles') }}" autocomplete="off">
                    <div class="gt-sidebar-actions">
                        <button type="button" class="btn btn-sm btn-outline-secondary" id="gtSelectAll">{{ __('app.tracking.select_all') }}</button>
                        <button type="button" class="btn btn-sm btn-outline-secondary" id="gtSelectNone">{{ __('app.tracking.select_none') }}</button>
                    </div>
                </div>
                <div class="gt-vehicle-list" id="gtVehicleList"></div>
            </aside>

            <div class="gt-map-wrap">
                <div id="gtMap" aria-label="{{ __('app.tracking.live_map_aria') }}"></div>
                <div id="gtMapError" class="gt-map-error" hidden>
                    <div class="alert alert-danger mb-0">
                        <span data-gt-error-text>{{ __('app.map.loading_map_failed') }}</span>
                    </div>
                </div>
                <div class="gt-map-controls">
                    <button type="button" class="btn btn-light" id="gtBtnFit" title="{{ __('app.tracking.fit_all') }}">
                        <i class="fas fa-compress-arrows-alt"></i>
                    </button>
                    <button type="button" class="btn btn-light" id="gtBtnFollow" title="{{ __('app.tracking.follow_vehicle') }}" aria-pressed="false">
                        <i class="fas fa-crosshairs"></i>
                    </button>
                    <button type="button" class="btn btn-light" id="gtBtnRefresh" title="{{ __('app.tracking.refresh') }}">
                        <i class="fas fa-sync-alt"></i>
                    </button>
                </div>
            </div>
        </div>
    </div>
@endsection

@push('scripts')
    @if($panel !== 'user')
        <script src="https://js.pusher.com/8.2/pusher.min.js"></script>
        <script src="https://cdnjs.cloudflare.com/ajax/libs/laravel-echo/1.15.0/echo.iife.js"></script>
        @include('user._pusher')
    @endif
    <script>document.body.classList.add('gt-page-active');</script>
    <script>
        window.GLOBAL_TRACKING_CONFIG = {
            panel: @json($panel),
            googleMapsKey: @json(config('services.google.maps_key')),
            liveJsonUrl: @json(route($routes['liveJson'])),
            pollIntervalMs: 4000,
            animDurationMs: 1200,
            stateColors: @json($stateColors),
            appTimezone: @json(config('app.timezone')),
            vehicles: @json($vehicles),
            i18n: {
                noVehicles: @json(__('app.tracking.no_vehicles')),
                loadingMapFailed: @json(__('app.map.loading_map_failed')),
                mapApiKeyMissing: @json(__('app.map.map_api_key_missing')),
                kmhUnit: @json(__('app.map.kmh_unit')),
                ignitionOn: @json(__('app.map.ignition_on')),
                ignitionOff: @json(__('app.map.ignition_off')),
            },
        };
    </script>
    <script src="{{ protected_js('app-datetime.js') }}"></script>
    <script src="{{ protected_js('vehicle-marker.js') }}"></script>
    <script src="{{ protected_js('global-tracking.js') }}"></script>
@endpush
