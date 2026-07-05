@extends($layout)

@section('title', __('app.tracking.history_title') . ' - ' . __('app.brand'))

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
        }

        .gt-toolbar__title {
            display: flex;
            align-items: center;
            gap: 0.5rem;
            font-weight: 700;
        }

        .gt-filters {
            display: flex;
            flex-wrap: wrap;
            gap: 0.5rem;
            align-items: flex-end;
            padding: 0.75rem;
            background: #fff;
            border-bottom: 1px solid var(--border-color, #e0e0e0);
        }

        .gt-filters label {
            font-size: 0.75rem;
            margin-bottom: 0.15rem;
            color: var(--text-secondary, #64748b);
        }

        .gt-filters > div { display: flex; flex-direction: column; }

        .gt-filter-object { min-width: 220px; flex: 1 1 240px; max-width: 360px; }
        .gt-filter-object .select2-container { width: 100% !important; }
        .gt-filter-object .select2-selection--single {
            height: calc(1.5em + 0.5rem + 2px);
            display: flex;
            align-items: center;
        }

        .gt-map-empty {
            position: absolute;
            inset: 0;
            display: flex;
            align-items: center;
            justify-content: center;
            background: rgba(248, 250, 252, 0.85);
            z-index: 4;
            pointer-events: none;
        }

        .gt-map-empty[hidden] { display: none !important; }

        .gt-body {
            flex: 1;
            display: flex;
            min-height: 0;
        }

        .gt-sidebar {
            width: min(320px, 92vw);
            flex-shrink: 0;
            display: flex;
            flex-direction: column;
            background: #fff;
            border-inline-end: 1px solid var(--border-color, #e0e0e0);
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
        }

        .gt-vehicle-row {
            display: flex;
            align-items: center;
            gap: 0.5rem;
            padding: 0.55rem 0.75rem;
            margin: 0;
            cursor: pointer;
            border-bottom: 1px solid rgba(15, 23, 42, 0.06);
        }

        .gt-vehicle-row:hover { background: rgba(25, 118, 210, 0.04); }

        .gt-vehicle-title {
            font-weight: 600;
            font-size: 0.875rem;
        }

        .gt-map-wrap {
            position: relative;
            flex: 1;
            min-width: 0;
        }

        #gtHistoryMap {
            position: absolute;
            inset: 0;
            background: #e8eef4;
        }

        .gt-legend {
            position: absolute;
            bottom: 12px;
            inset-inline-start: 12px;
            background: rgba(255, 255, 255, 0.94);
            border-radius: 8px;
            padding: 0.5rem 0.75rem;
            box-shadow: 0 4px 14px rgba(15, 23, 42, 0.12);
            z-index: 5;
            max-width: 240px;
        }

        .gt-legend-item {
            display: flex;
            align-items: center;
            gap: 0.5rem;
            font-size: 0.8125rem;
            margin-bottom: 0.25rem;
        }

        .gt-legend-swatch {
            width: 14px;
            height: 14px;
            border-radius: 3px;
            flex-shrink: 0;
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
                max-height: 36vh;
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
                <i class="fas fa-route text-primary"></i>
                <span>{{ __('app.tracking.history_title') }}</span>
            </div>
        </div>
        @include('tracking.partials.hub-nav')

        <div class="gt-filters">
            <div class="gt-filter-object">
                <label for="gtHistoryVehicle">{{ __('app.tracking.object') }}</label>
                <select id="gtHistoryVehicle" class="form-select form-select-sm">
                    @foreach($vehicles as $vehicle)
                        <option value="{{ $vehicle['id'] }}">
                            {{ $vehicle['title'] ?? ($vehicle['plate'] ?? ('#' . $vehicle['id'])) }}
                        </option>
                    @endforeach
                </select>
            </div>
            <div>
                <label for="gtDateFrom">{{ __('app.tracking.date_from') }}</label>
                <input type="date" id="gtDateFrom" class="form-control form-control-sm admin-ltr" dir="ltr">
            </div>
            <div>
                <label for="gtTimeFrom">{{ __('app.tracking.time_from') }}</label>
                <input type="time" id="gtTimeFrom" class="form-control form-control-sm admin-ltr" dir="ltr" value="00:00">
            </div>
            <div>
                <label for="gtDateTo">{{ __('app.tracking.date_to') }}</label>
                <input type="date" id="gtDateTo" class="form-control form-control-sm admin-ltr" dir="ltr">
            </div>
            <div>
                <label for="gtTimeTo">{{ __('app.tracking.time_to') }}</label>
                <input type="time" id="gtTimeTo" class="form-control form-control-sm admin-ltr" dir="ltr" value="23:59">
            </div>
            <button type="button" class="btn btn-primary btn-sm" id="gtHistoryLoad">
                <i class="fas fa-search me-1"></i>{{ __('app.tracking.load_history') }}
            </button>
        </div>

        <div class="gt-body">
            <div class="gt-map-wrap">
                <div id="gtHistoryMap" aria-label="{{ __('app.tracking.history_map_aria') }}"></div>
                <div id="gtHistoryLegend" class="gt-legend"></div>
                <div id="gtHistoryEmpty" class="gt-map-empty">
                    <div class="text-center text-muted">
                        <i class="fas fa-route fa-2x mb-2 d-block"></i>
                        {{ __('app.tracking.select_vehicle') }}
                    </div>
                </div>
                <div id="gtHistoryError" class="gt-map-error" hidden>
                    <div class="alert alert-danger mb-0">
                        <span data-gt-error-text>{{ __('app.map.loading_map_failed') }}</span>
                    </div>
                </div>
                <div class="gt-map-controls">
                    <button type="button" class="btn btn-light" id="gtHistoryFit" title="{{ __('app.tracking.fit_all') }}">
                        <i class="fas fa-compress-arrows-alt"></i>
                    </button>
                    <button type="button" class="btn btn-light" id="gtHistoryClear" title="{{ __('app.tracking.clear_map') }}">
                        <i class="fas fa-eraser"></i>
                    </button>
                </div>
            </div>
        </div>
    </div>
@endsection

@push('scripts')
    <script>document.body.classList.add('gt-page-active');</script>
    <script>
        window.GLOBAL_TRACKING_HISTORY_CONFIG = {
            panel: @json($panel),
            googleMapsKey: @json(config('services.google.maps_key')),
            googleMapsMapId: @json(config('services.google.maps_map_id')),
            historyJsonUrl: @json(route($routes['historyJson'])),
            multiColors: @json($multiColors),
            appTimezone: @json(config('app.timezone')),
            vehicles: @json($vehicles),
            startIconUrl: @json(asset('images/map/marker-start.svg')),
            endIconUrl: @json(asset('images/map/marker-end.svg')),
            i18n: {
                noVehicles: @json(__('app.tracking.no_vehicles')),
                selectVehicle: @json(__('app.tracking.select_vehicle')),
                loadFailed: @json(__('app.tracking.load_failed')),
                loadingMapFailed: @json(__('app.map.loading_map_failed')),
                mapApiKeyMissing: @json(__('app.map.map_api_key_missing')),
                routeStart: @json(__('app.map.route_start')),
                routeEnd: @json(__('app.map.route_end')),
            },
        };
    </script>
    <script src="{{ protected_js('app-datetime.js') }}"></script>
@include('partials.google-maps-platform')
<script src="{{ protected_js('vehicle-marker.js') }}"></script>
<script src="{{ protected_js('global-tracking-history.js') }}"></script>
@endpush
