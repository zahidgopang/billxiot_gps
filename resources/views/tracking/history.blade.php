@extends($layout)

@section('title', __('app.tracking.history_title') . ' - ' . __('app.brand'))

@push('styles')
    @include('tracking.partials.module-styles')
    <link rel="stylesheet" href="{{ asset('css/fleet-map.css') }}?v={{ filemtime(public_path('css/fleet-map.css')) }}">
    <style>
        body.gt-page-active { overflow: hidden; }
        body.gt-page-active .content-wrap {
            height: calc(100dvh - var(--tracking-topbar-height, 52px));
            max-height: calc(100dvh - var(--tracking-topbar-height, 52px));
            overflow: hidden;
            padding: 0 !important;
        }

        .gt-page {
            display: flex;
            flex-direction: column;
            height: 100%;
            min-height: 420px;
            background: var(--apple-bg-secondary);
        }

        .gt-toolbar {
            flex-shrink: 0;
            display: flex;
            flex-wrap: wrap;
            align-items: center;
            justify-content: space-between;
            gap: 0.75rem;
            padding: 0.65rem 1rem;
            background: var(--apple-bg-primary);
            border-bottom: 0.5px solid var(--apple-separator);
        }

        .gt-toolbar__title {
            display: flex;
            align-items: center;
            gap: 0.5rem;
            font-weight: 600;
            font-size: 0.9375rem;
            letter-spacing: -0.02em;
            color: var(--apple-label);
        }

        .gt-filters {
            display: flex;
            flex-wrap: wrap;
            gap: 0.5rem;
            align-items: flex-end;
            padding: 0.75rem;
            background: var(--apple-bg-group);
            border-bottom: 0.5px solid var(--apple-separator);
        }

        .gt-filters label {
            font-size: 0.75rem;
            margin-bottom: 0.15rem;
            color: var(--apple-secondary);
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
            background: rgba(245, 245, 247, 0.88);
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
            width: min(380px, 94vw);
            flex-shrink: 0;
            display: flex;
            flex-direction: column;
            background: var(--apple-bg-sidebar);
            border-inline-end: 0.5px solid var(--apple-separator);
            max-height: 100%;
        }

        .gt-sidebar-head {
            padding: 0.75rem;
            border-bottom: 0.5px solid var(--apple-separator);
            background: var(--apple-bg-primary);
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
                max-height: 48vh;
                border-inline-end: none;
                border-bottom: 0.5px solid var(--apple-separator);
            }
            .gt-map-wrap { min-height: 42vh; }
        }

        @media (max-width: 900px) and (orientation: landscape) {
            .gt-page { min-height: 0; }
            body.gt-page-active .content-wrap {
                height: calc(100dvh - var(--tracking-topbar-height, 52px));
                max-height: calc(100dvh - var(--tracking-topbar-height, 52px));
            }
            .gt-filters {
                flex-wrap: nowrap;
                overflow-x: auto;
                padding: 0.45rem 0.65rem;
                gap: 0.4rem;
            }
            .gt-filters > div { flex-shrink: 0; }
            .gt-body {
                flex-direction: row;
                min-height: 0;
                flex: 1;
            }
            .gt-sidebar {
                width: min(42vw, 340px);
                max-height: none;
                height: 100%;
                border-inline-end: 0.5px solid var(--apple-separator);
                border-bottom: none;
            }
            .gt-map-wrap {
                min-height: 0;
                flex: 1;
            }
            .gt-playback {
                padding: 0.35rem 0.5rem;
            }
            .gt-playback-row { margin-top: 0.2rem; }
            .htt-summary {
                grid-template-columns: repeat(auto-fill, minmax(72px, 1fr));
                gap: 0.3rem;
            }
            .htt-list { max-height: none; }
        }

        .gt-sidebar-panel {
            display: flex;
            flex-direction: column;
            min-height: 0;
            flex: 1;
            padding: 0.55rem 0.65rem 0.75rem;
            gap: 0.25rem;
        }

        .gt-playback {
            flex-shrink: 0;
            border-top: 0.5px solid var(--apple-separator);
            padding: 0.55rem 0.65rem;
            background: var(--apple-bg-primary);
        }

        .gt-playback-row {
            display: flex;
            align-items: center;
            gap: 0.35rem;
            flex-wrap: wrap;
            margin-top: 0.35rem;
        }

        .gt-playback-progress {
            height: 6px;
            background: rgba(0,0,0,.08);
            border-radius: 99px;
            position: relative;
            cursor: pointer;
            margin: 0.35rem 0;
        }

        .gt-playback-bar {
            height: 100%;
            width: 0;
            border-radius: 99px;
            background: #007aff;
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
            <aside class="gt-sidebar">
                <div class="gt-sidebar-panel">
                    <div id="gtHistVehicleLabel"></div>
                    <div id="gtHistDayNav"></div>
                    <div id="gtHistExport"></div>
                    <div id="gtHistSummary" class="tc-hist-summary" hidden></div>
                    <div class="tc-list-head">
                        <span class="tc-head-label">{{ __('app.tracking.trip_timeline') }}</span>
                    </div>
                    <div id="gtHistTimeline" class="htt-list"></div>
                </div>
                <div class="gt-playback" id="gtPlaybackPanel">
                    <div class="small fw-semibold mb-1">{{ __('app.map.route_playback') }}</div>
                    <div class="gt-playback-progress" id="gtPlaybackProgress">
                        <div class="gt-playback-bar" id="gtPlaybackBar"></div>
                    </div>
                    <div class="d-flex justify-content-between small text-muted">
                        <span id="gtPbTimeCurrent">00:00</span>
                        <span id="gtPbPointLabel">0 / 0</span>
                        <span id="gtPbTimeTotal">00:00</span>
                    </div>
                    <div class="gt-playback-row">
                        <button type="button" class="btn btn-sm btn-outline-secondary" id="gtPbPlay" title="Play/Pause"><i class="fas fa-play"></i></button>
                        <button type="button" class="btn btn-sm btn-outline-secondary" id="gtPbStop" title="Stop"><i class="fas fa-stop"></i></button>
                        <div class="btn-group btn-group-sm" role="group">
                            <button type="button" class="btn btn-outline-secondary active" data-gt-speed="1">1×</button>
                            <button type="button" class="btn btn-outline-secondary" data-gt-speed="2">2×</button>
                            <button type="button" class="btn btn-outline-secondary" data-gt-speed="4">4×</button>
                            <button type="button" class="btn btn-outline-secondary" data-gt-speed="8">8×</button>
                        </div>
                        <span class="small"><span id="gtPbLiveSpeed">0</span> km/h</span>
                    </div>
                </div>
            </aside>
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
            historyPointsJsonUrl: @json(Route::has($routes['historyPoints'] ?? '') ? route($routes['historyPoints']) : null),
            historyAnalyticsJsonUrl: @json(Route::has($routes['historyAnalytics'] ?? '') ? route($routes['historyAnalytics']) : null),
            historyExportUrl: @json(Route::has($routes['historyExport'] ?? '') ? route($routes['historyExport']) : null),
            historyGeocodeUrl: @json(Route::has($routes['historyGeocode'] ?? '') ? route($routes['historyGeocode']) : null),
            multiColors: @json($multiColors),
            appTimezone: @json(config('app.timezone')),
            vehicles: @json($vehicles),
            startIconUrl: @json(asset('images/map/marker-start.svg')),
            endIconUrl: @json(asset('images/map/marker-end.svg')),
            i18n: {
                noVehicles: @json(__('app.tracking.no_vehicles')),
                selectVehicle: @json(__('app.tracking.select_vehicle')),
                loadFailed: @json(__('app.tracking.load_failed')),
                historyPermissionDenied: @json(__('app.tracking.history_permission_denied')),
                accessDeniedTitle: @json(__('app.errors.403_title')),
                ok: @json(__('app.common.ok')),
                loadingMapFailed: @json(__('app.map.loading_map_failed')),
                mapApiKeyMissing: @json(__('app.map.map_api_key_missing')),
                routeStart: @json(__('app.map.route_start')),
                routeEnd: @json(__('app.map.route_end')),
                exportExcel: 'Excel',
                exportPdf: 'PDF',
                exportCsv: 'CSV',
                exportFailed: @json(__('app.tracking.report_export_failed')),
                today: 'Today',
                noTimeline: 'No trips for this day',
            },
        };
    </script>
    <script src="{{ protected_js('app-datetime.js') }}"></script>
@include('partials.google-maps-platform')
<script src="{{ protected_js('builtin-map-icons.js') }}"></script>
<script src="{{ protected_js('vehicle-marker.js') }}"></script>
<script src="{{ protected_js('history-trip-timeline.js') }}"></script>
<script src="{{ protected_js('global-tracking-history.js') }}"></script>
@endpush
