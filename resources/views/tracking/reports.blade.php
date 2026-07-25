@extends($layout)
@section('title', __('app.tracking.reports_title') . ' - ' . __('app.brand'))
@push('styles')
@include('tracking.partials.module-styles')
<style>
    .gt-report-toolbar {
        display: flex;
        align-items: center;
        justify-content: space-between;
        flex-wrap: wrap;
        gap: 0.5rem;
        margin-bottom: 0.5rem;
        min-height: 32px;
    }
    .gt-report-pager { display: flex; align-items: center; gap: 0.35rem; }
    .gt-report-pager .form-select-sm { width: auto; }
    .gt-report-tablewrap { position: relative; min-height: 160px; }
    .gt-report-overlay {
        position: absolute;
        inset: 0;
        display: flex;
        align-items: center;
        justify-content: center;
        background: rgba(245, 245, 247, 0.82);
        backdrop-filter: blur(4px);
        z-index: 5;
        border-radius: 12px;
    }
    .gt-report-overlay[hidden] { display: none !important; }
    .gt-report-empty {
        padding: 2.5rem 1rem;
        text-align: center;
        color: var(--apple-secondary);
        font-size: 0.875rem;
    }
    .gt-report-empty[hidden] { display: none !important; }
    #gtReportTable td, #gtReportTable th { white-space: nowrap; font-size: 0.8125rem; }
    #gtReportTable { direction: inherit; }
    .gt-report-num { display: inline-block; direction: ltr; unicode-bidi: embed; }
    html[dir="rtl"] .gt-report-pager { flex-direction: row-reverse; }
    .gt-report-kpis {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(120px, 1fr));
        gap: 0.65rem;
        margin-bottom: 0.85rem;
    }
    .gt-report-kpis[hidden] { display: none !important; }
    .gt-report-kpi {
        background: var(--apple-bg-group);
        border: 0.5px solid var(--tc-border-soft);
        border-radius: 12px;
        padding: 0.55rem 0.7rem;
    }
    .gt-report-kpi-label {
        display: block;
        font-size: 0.6875rem;
        font-weight: 500;
        letter-spacing: -0.01em;
        color: var(--apple-secondary);
        margin-bottom: 0.15rem;
        text-transform: none;
    }
    .gt-report-kpi-value {
        font-size: 0.9375rem;
        font-weight: 600;
        letter-spacing: -0.018em;
        color: var(--apple-label);
        direction: ltr;
        unicode-bidi: embed;
    }
    #gtReportMap {
        height: 280px;
        border-radius: 12px;
        border: 0.5px solid var(--tc-border);
    }
    .gt-report-vehicle-actions {
        display: flex;
        gap: 0.35rem;
        margin-bottom: 0.35rem;
    }
    .gt-report-vehicle-actions .btn { font-size: 0.72rem; padding: 0.15rem 0.45rem; }
    .gt-report-vehicle-search {
        margin-bottom: 0.35rem;
    }
    .gt-vehicle-picker label.is-filtered-out { display: none !important; }
    .gt-report-vehicle-empty {
        padding: 0.35rem 0.15rem;
        text-align: center;
    }
    .gt-report-vehicle-empty[hidden] { display: none !important; }
    .gt-report-subtitle { color: var(--apple-secondary); font-size: 0.8125rem; margin-bottom: 1rem; letter-spacing: -0.01em; }
    .gt-report-mode-tabs {
        display: flex;
        gap: 0.35rem;
        margin-bottom: 0.85rem;
        flex-wrap: wrap;
    }
    .gt-report-mode-tabs .btn {
        border-radius: 999px;
        font-size: 0.78rem;
        padding: 0.28rem 0.75rem;
    }
    .gt-report-mode-tabs .btn.is-active {
        background: var(--apple-blue, #007aff);
        border-color: var(--apple-blue, #007aff);
        color: #fff;
    }
    .gt-custom-fields {
        max-height: 180px;
        overflow-y: auto;
        border: 0.5px solid var(--tc-border-soft);
        border-radius: 10px;
        padding: 0.4rem 0.55rem;
        background: var(--apple-bg-group, #f5f5f7);
    }
    .gt-custom-fields .form-check {
        margin-bottom: 0.2rem;
    }
    .gt-custom-fields-actions {
        display: flex;
        gap: 0.35rem;
        margin-bottom: 0.35rem;
    }
    .gt-custom-fields-actions .btn { font-size: 0.72rem; padding: 0.15rem 0.45rem; }
    .gt-report-presets { display: flex; flex-wrap: wrap; gap: 0.35rem; margin-bottom: 0.35rem; }
    .gt-report-presets .btn { font-size: 0.72rem; padding: 0.15rem 0.5rem; border-radius: 999px; }
    .gt-report-loading-text { font-size: 0.8125rem; color: var(--tc-text-muted); margin-top: 0.35rem; }
    .gt-report-layout {
        display: grid;
        grid-template-columns: minmax(280px, 320px) minmax(0, 1fr);
        gap: 1rem;
        align-items: start;
    }
    @media (max-width: 991.98px) {
        .gt-report-layout { grid-template-columns: 1fr; }
    }
    .gt-report-sidebar {
        display: flex;
        flex-direction: column;
        gap: 0.65rem;
        max-height: calc(100vh - var(--tracking-topbar-height, 52px) - 7rem);
        overflow-y: auto;
        padding-inline-end: 0.15rem;
        -webkit-overflow-scrolling: touch;
    }
    body.tracking-embed .gt-report-sidebar {
        max-height: calc(100vh - 2rem);
    }
    .gt-report-sidebar .gt-vehicle-picker {
        max-height: 160px;
        flex: 0 0 auto;
    }
    .gt-report-filters {
        flex: 0 0 auto;
        padding: 0.65rem 0.7rem;
        border: 0.5px solid var(--tc-border-soft);
        border-radius: 12px;
        background: var(--apple-bg-group);
    }
    .gt-report-filters-title {
        font-size: 0.75rem;
        font-weight: 600;
        letter-spacing: -0.01em;
        color: var(--apple-label);
        margin-bottom: 0.45rem;
    }
    .gt-report-filters .form-check {
        display: flex;
        align-items: flex-start;
        gap: 0.45rem;
        margin-bottom: 0.35rem;
        min-height: auto;
        padding-left: 0;
    }
    .gt-report-filters .form-check-input {
        float: none;
        margin: 0.15rem 0 0;
        flex-shrink: 0;
        width: 1rem;
        height: 1rem;
        position: static;
    }
    .gt-report-filters .form-check-label {
        font-size: 0.78rem;
        color: var(--apple-label);
        line-height: 1.3;
    }
    .gt-report-filters .gt-filter-field {
        margin-top: 0.5rem;
    }
    .gt-report-filters .gt-filter-field[hidden],
    .gt-report-filters .form-check[hidden] {
        display: none !important;
    }
    .gt-report-filters .form-control-sm,
    .gt-report-filters .form-select-sm {
        font-size: 0.78rem;
    }
    .gt-report-filters-help {
        font-size: 0.72rem;
        line-height: 1.35;
        margin: 0;
    }
    .gt-report-filters-help.is-warn {
        color: #b54708 !important;
    }
    .gt-report-actions {
        position: static;
        display: flex;
        flex-wrap: wrap;
        gap: 0.4rem;
        padding: 0.55rem 0.45rem;
        margin-top: 0.25rem;
        border: 0.5px solid var(--tc-border-soft);
        border-radius: 12px;
        background: var(--apple-bg-card);
    }
    .gt-report-actions .btn {
        flex: 1 1 calc(50% - 0.4rem);
        min-width: 0;
    }
</style>
@endpush
@section('content')
<div class="gt-module-page">
    @include('tracking.partials.hub-nav')
    <div class="gt-module-card">
        <h5 class="mb-1">{{ __('app.tracking.reports_title') }}</h5>
        <p class="gt-report-subtitle">{{ __('app.tracking.report_subtitle') }}</p>
        <div class="gt-report-mode-tabs" role="tablist" aria-label="{{ __('app.tracking.reports_title') }}">
            <button type="button" class="btn btn-outline-secondary btn-sm is-active" id="gtReportModeStandard" data-report-mode="standard" role="tab" aria-selected="true">
                {{ __('app.tracking.report_mode_standard') }}
            </button>
            <button type="button" class="btn btn-outline-secondary btn-sm" id="gtReportModeCustom" data-report-mode="custom" role="tab" aria-selected="false">
                {{ __('app.tracking.report_mode_custom') }}
            </button>
        </div>
        <div class="gt-report-layout">
            <aside class="gt-report-sidebar" aria-label="{{ __('app.tracking.reports_title') }}">
                <div>
                    <label class="form-label small">{{ __('app.tracking.report_type') }}</label>
                    <select id="gtReportType" class="form-select form-select-sm">
                        <optgroup label="{{ __('app.tracking.report_group_general') }}">
                            <option value="summary">{{ __('app.tracking.report_summary') }}</option>
                            <option value="object_info">{{ __('app.tracking.report_object_info') }}</option>
                            <option value="current_position">{{ __('app.tracking.report_current_position') }}</option>
                        </optgroup>
                        <optgroup label="{{ __('app.tracking.report_group_text') }}">
                            <option value="trips">{{ __('app.tracking.report_trips') }}</option>
                            <option value="trips_stops">{{ __('app.tracking.report_trips_stops') }}</option>
                            <option value="stops">{{ __('app.tracking.report_stops') }}</option>
                            <option value="mileage">{{ __('app.tracking.report_mileage') }}</option>
                            <option value="odometer">{{ __('app.tracking.report_odometer') }}</option>
                            <option value="overspeeds">{{ __('app.tracking.report_overspeeds') }}</option>
                            <option value="zone_inout">{{ __('app.tracking.report_zone_inout') }}</option>
                            <option value="events">{{ __('app.tracking.report_events') }}</option>
                            <option value="diesel">{{ __('app.tracking.report_diesel') }}</option>
                            <option value="fuel_fillings">{{ __('app.tracking.report_fuel_fillings') }}</option>
                            <option value="service">{{ __('app.tracking.report_service') }}</option>
                            <option value="tasks">{{ __('app.tracking.report_tasks') }}</option>
                        </optgroup>
                        <optgroup label="{{ __('app.tracking.report_group_graphical') }}">
                            <option value="speed">{{ __('app.tracking.report_speed') }}</option>
                            <option value="altitude">{{ __('app.tracking.report_altitude') }}</option>
                            <option value="ignition">{{ __('app.tracking.report_ignition') }}</option>
                        </optgroup>
                        <optgroup label="{{ __('app.tracking.report_group_map') }}">
                            <option value="route">{{ __('app.tracking.report_route') }}</option>
                            <option value="positions">{{ __('app.tracking.report_positions') }}</option>
                        </optgroup>
                    </select>
                </div>

                <div id="gtCustomFieldsPanel" hidden>
                    <p class="text-muted small mb-1">{{ __('app.tracking.report_custom_help') }}</p>
                    <div class="gt-custom-fields-actions">
                        <button type="button" class="btn btn-outline-secondary btn-sm" id="gtCustomFieldsSelectAll">{{ __('app.tracking.report_custom_select_all') }}</button>
                        <button type="button" class="btn btn-outline-secondary btn-sm" id="gtCustomFieldsClear">{{ __('app.tracking.report_custom_clear') }}</button>
                    </div>
                    <label class="form-label small">{{ __('app.tracking.report_custom_fields') }}</label>
                    <div class="gt-custom-fields" id="gtCustomFields" role="group" aria-label="{{ __('app.tracking.report_custom_fields') }}"></div>
                </div>
                <div>
                    <label class="form-label small">{{ __('app.tracking.report_language') }}</label>
                    <select id="gtReportLang" class="form-select form-select-sm">
                        <option value="en" @selected(app()->getLocale() === 'en')>{{ __('app.tracking.report_lang_en') }}</option>
                        <option value="ar" @selected(app()->getLocale() === 'ar')>{{ __('app.tracking.report_lang_ar') }}</option>
                    </select>
                </div>
                <div>
                    <label class="form-label small">{{ __('app.tracking.date_from') }}</label>
                    <div class="gt-report-presets">
                        <button type="button" class="btn btn-outline-secondary btn-sm" data-report-preset="today">{{ __('app.tracking.report_preset_today') }}</button>
                        <button type="button" class="btn btn-outline-secondary btn-sm" data-report-preset="yesterday">{{ __('app.tracking.report_preset_yesterday') }}</button>
                        <button type="button" class="btn btn-outline-secondary btn-sm" data-report-preset="7d">{{ __('app.tracking.report_preset_7d') }}</button>
                    </div>
                    <input type="datetime-local" id="gtReportFrom" class="form-control form-control-sm admin-ltr" dir="ltr">
                    <label class="form-label small mt-2">{{ __('app.tracking.date_to') }}</label>
                    <input type="datetime-local" id="gtReportTo" class="form-control form-control-sm admin-ltr" dir="ltr">
                </div>

                <div class="gt-report-filters" id="gtReportFilters">
                    <div class="gt-report-filters-title">{{ __('app.tracking.report_filters') }}</div>
                    <p class="gt-report-filters-help text-muted small mb-2" id="gtReportFiltersHelp">
                        {{ __('app.tracking.report_filter_help_location') }}
                    </p>
                    <p class="gt-report-filters-help text-muted small mb-2">
                        {{ __('app.tracking.report_filter_help_priority') }}
                    </p>
                    <div class="form-check" data-filter-key="ignore_empty">
                        <input class="form-check-input" type="checkbox" id="gtFilterIgnoreEmpty">
                        <label class="form-check-label" for="gtFilterIgnoreEmpty">{{ __('app.tracking.report_filter_ignore_empty') }}</label>
                    </div>
                    <div class="form-check" data-filter-key="show_coordinates">
                        <input class="form-check-input" type="checkbox" id="gtFilterShowCoordinates" checked>
                        <label class="form-check-label" for="gtFilterShowCoordinates">{{ __('app.tracking.report_filter_show_coordinates') }}</label>
                    </div>
                    <div class="form-check" data-filter-key="show_addresses">
                        <input class="form-check-input" type="checkbox" id="gtFilterShowAddresses">
                        <label class="form-check-label" for="gtFilterShowAddresses">{{ __('app.tracking.report_filter_show_addresses') }}</label>
                    </div>
                    <div class="form-check" data-filter-key="markers_instead_of_addresses">
                        <input class="form-check-input" type="checkbox" id="gtFilterMarkersInstead">
                        <label class="form-check-label" for="gtFilterMarkersInstead">{{ __('app.tracking.report_filter_markers_instead') }}</label>
                    </div>
                    <div class="form-check" data-filter-key="zones_instead_of_addresses">
                        <input class="form-check-input" type="checkbox" id="gtFilterZonesInstead">
                        <label class="form-check-label" for="gtFilterZonesInstead">{{ __('app.tracking.report_filter_zones_instead') }}</label>
                    </div>
                    <div class="gt-filter-field" data-filter-key="stops">
                        <label class="form-label small mb-1" for="gtFilterStops">{{ __('app.tracking.report_filter_stops') }}</label>
                        <select id="gtFilterStops" class="form-select form-select-sm">
                            <option value="1" selected>{{ __('app.tracking.report_filter_stops_option', ['min' => 1]) }}</option>
                            <option value="5">{{ __('app.tracking.report_filter_stops_option', ['min' => 5]) }}</option>
                            <option value="10">{{ __('app.tracking.report_filter_stops_option', ['min' => 10]) }}</option>
                            <option value="15">{{ __('app.tracking.report_filter_stops_option', ['min' => 15]) }}</option>
                            <option value="30">{{ __('app.tracking.report_filter_stops_option', ['min' => 30]) }}</option>
                            <option value="60">{{ __('app.tracking.report_filter_stops_option', ['min' => 60]) }}</option>
                            <option value="custom">{{ __('app.tracking.report_filter_stops_custom') }}</option>
                        </select>
                        <input type="number"
                               id="gtFilterStopsCustom"
                               class="form-control form-control-sm mt-1 admin-ltr"
                               dir="ltr"
                               min="1"
                               step="1"
                               placeholder="{{ __('app.tracking.report_filter_stops_custom_placeholder') }}"
                               hidden>
                    </div>
                    <div class="gt-filter-field" data-filter-key="speed_limit">
                        <label class="form-label small mb-1" for="gtFilterSpeedLimit">{{ __('app.tracking.report_filter_speed_limit') }}</label>
                        <input type="number"
                               id="gtFilterSpeedLimit"
                               class="form-control form-control-sm admin-ltr"
                               dir="ltr"
                               min="1"
                               step="1"
                               placeholder="{{ __('app.tracking.report_filter_speed_limit_placeholder') }}">
                    </div>
                </div>

                <div>
                    <label class="form-label small">{{ __('app.tracking.vehicles') }}</label>
                    <div class="gt-report-vehicle-actions">
                        <button type="button" class="btn btn-outline-secondary btn-sm" id="gtReportSelectAll">{{ __('app.tracking.report_select_all') }}</button>
                        <button type="button" class="btn btn-outline-secondary btn-sm" id="gtReportSelectNone">{{ __('app.tracking.report_select_none') }}</button>
                    </div>
                    <input type="search"
                           id="gtReportVehicleSearch"
                           class="form-control form-control-sm gt-report-vehicle-search"
                           placeholder="{{ __('app.tracking.report_search_vehicles') }}"
                           autocomplete="off"
                           aria-label="{{ __('app.tracking.report_search_vehicles') }}">
                    <div class="gt-vehicle-picker" id="gtReportVehicles">
                        @foreach($vehicles as $v)
                            @php
                                $label = $v['title'] ?? $v['plate'] ?? '#'.$v['id'];
                                $searchHaystack = strtolower(trim(implode(' ', array_filter([
                                    (string) ($v['title'] ?? ''),
                                    (string) ($v['name'] ?? ''),
                                    (string) ($v['plate'] ?? ''),
                                    (string) ($v['vehicle_number'] ?? ''),
                                    (string) ($v['id'] ?? ''),
                                    (string) $label,
                                ]))));
                            @endphp
                            <label data-search="{{ e($searchHaystack) }}">
                                <input type="checkbox" value="{{ $v['id'] }}"> {{ $label }}
                            </label>
                        @endforeach
                        <div class="gt-report-vehicle-empty text-muted small" id="gtReportVehicleEmpty" hidden>
                            {{ __('app.tracking.report_no_vehicles_match') }}
                        </div>
                    </div>
                </div>

                <div class="gt-report-actions">
                    <button type="button" class="btn btn-primary btn-sm" id="gtReportRun">{{ __('app.tracking.load_history') }}</button>
                    <button type="button" class="btn btn-outline-secondary btn-sm" id="gtReportCsv">{{ __('app.tracking.export_csv') }}</button>
                    <button type="button" class="btn btn-outline-secondary btn-sm" id="gtReportXlsx">{{ __('app.tracking.export_xls') }}</button>
                    <button type="button" class="btn btn-outline-secondary btn-sm" id="gtReportPdf">{{ __('app.tracking.export_pdf') }}</button>
                </div>
            </aside>

            <div class="gt-report-main">
                <div id="gtReportKpis" class="gt-report-kpis" hidden></div>
                <div id="gtReportMap" class="mb-3" hidden></div>
                <div id="gtReportKpisAfter" class="gt-report-kpis" hidden></div>
                <div class="gt-report-toolbar">
                    <span id="gtReportCount" class="text-muted small"></span>
                    <div class="gt-report-pager" id="gtReportPager"></div>
                </div>
                <div class="gt-report-tablewrap">
                    <div class="table-responsive">
                        <table class="table table-sm table-hover" id="gtReportTable" dir="{{ $htmlDir ?? 'ltr' }}">
                            <thead><tr id="gtReportHead"></tr></thead>
                            <tbody id="gtReportBody"></tbody>
                        </table>
                    </div>
                    <div class="gt-report-empty" id="gtReportEmpty" hidden></div>
                    <div class="gt-report-overlay" id="gtReportOverlay" hidden>
                        <div class="text-center">
                            <div class="spinner-border text-primary" role="status" aria-hidden="true"></div>
                            <div class="gt-report-loading-text" id="gtReportLoadingText"></div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection
@push('scripts')
@php
    $reportI18n = array_merge(\App\Services\Tracking\Reports\ReportLabels::jsBundle(), [
        'hourSuffix' => __('app.tracking.report_duration_hours'),
        'minSuffix' => __('app.tracking.report_duration_minutes'),
        'secSuffix' => __('app.tracking.report_duration_seconds'),
        'pagerRange' => __('app.tracking.report_pager_range'),
        'pageOf' => __('app.tracking.report_page_of'),
        'rowsPerPage' => __('app.tracking.report_rows_per_page'),
        'noData' => __('app.tracking.report_no_data'),
        'loadFailed' => __('app.tracking.report_load_failed'),
        'ignitionOn' => __('app.tracking.report_ignition_on'),
        'ignitionOff' => __('app.tracking.report_ignition_off'),
        'selectVehicle' => __('app.tracking.report_select_vehicle'),
        'devicesCapped' => __('app.tracking.report_devices_capped'),
        'positionsTruncated' => __('app.tracking.report_positions_truncated'),
        'analyticsDownsampled' => __('app.tracking.report_analytics_downsampled'),
        'presetToday' => __('app.tracking.report_preset_today'),
        'presetYesterday' => __('app.tracking.report_preset_yesterday'),
        'preset7d' => __('app.tracking.report_preset_7d'),
        'loadingProgress' => __('app.tracking.report_loading_progress'),
        'loadingReport' => __('app.tracking.report_loading'),
        'exportFailed' => __('app.tracking.report_export_failed'),
        'filterHelpLocation' => __('app.tracking.report_filter_help_location'),
        'filterHelpNoLocationType' => __('app.tracking.report_filter_help_no_location_type'),
        'filterHelpGeocodeMissing' => __('app.tracking.report_filter_help_geocode_missing'),
        'colLocation' => __('app.tracking.report_col_address'),
    ]);
@endphp
<script>
window.APP_TIMEZONE = @json(config('app.timezone', 'Asia/Riyadh'));
window.TRACKING_REPORTS_CONFIG = {
    generateUrl: @json($generateUrl),
    exportUrl: @json($exportUrl),
    googleMapsKey: @json(config('services.google.maps_key')),
    googleMapsMapId: @json(config('services.google.maps_map_id')),
    hasGoogleMapsKey: @json(trim((string) config('services.google.maps_key', '')) !== ''),
    currentLang: @json(app()->getLocale()),
    timezone: @json(config('app.timezone', 'Asia/Riyadh')),
    i18n: @json($reportI18n),
};
</script>
@include('partials.google-maps-platform')
<script src="{{ protected_js('app-datetime.js') }}"></script>
<script src="{{ protected_js('builtin-map-icons.js') }}"></script>
<script src="{{ protected_js('vehicle-marker.js') }}"></script>
<script src="{{ protected_js('fleet-map-renderer.js') }}"></script>
<script src="{{ protected_js('tracking-reports.js') }}"></script>
@endpush
