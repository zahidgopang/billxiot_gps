@extends($layout)
@section('title', __('app.tracking.odometer_title') . ' - ' . __('app.brand'))
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
    }
    .gt-report-kpi-value {
        font-size: 0.9375rem;
        font-weight: 600;
        letter-spacing: -0.018em;
        color: var(--apple-label);
        direction: ltr;
        unicode-bidi: embed;
    }
    .gt-report-vehicle-actions {
        display: flex;
        gap: 0.35rem;
        margin-bottom: 0.35rem;
    }
    .gt-report-vehicle-actions .btn { font-size: 0.72rem; padding: 0.15rem 0.45rem; }
    .gt-report-vehicle-search { margin-bottom: 0.35rem; }
    .gt-vehicle-picker label.is-filtered-out { display: none !important; }
    .gt-report-vehicle-empty {
        padding: 0.35rem 0.15rem;
        text-align: center;
    }
    .gt-report-vehicle-empty[hidden] { display: none !important; }
    .gt-report-subtitle { color: var(--apple-secondary); font-size: 0.8125rem; margin-bottom: 1rem; letter-spacing: -0.01em; }
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
    .gt-report-actions {
        position: sticky;
        bottom: 0;
        z-index: 3;
        display: flex;
        flex-wrap: wrap;
        gap: 0.4rem;
        padding: 0.55rem 0.45rem;
        margin-top: 0.25rem;
        border: 0.5px solid var(--tc-border-soft);
        border-radius: 12px;
        background: var(--apple-bg-card);
        box-shadow: 0 -4px 14px rgba(0, 0, 0, 0.06);
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
        <h5 class="mb-1">{{ __('app.tracking.odometer_title') }}</h5>
        <p class="gt-report-subtitle">{{ __('app.tracking.odometer_subtitle') }}</p>
        <div class="gt-report-layout">
            <aside class="gt-report-sidebar" aria-label="{{ __('app.tracking.odometer_title') }}">
                <input type="hidden" id="gtReportType" value="odometer">

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
                    <div class="form-check" data-filter-key="ignore_empty">
                        <input class="form-check-input" type="checkbox" id="gtFilterIgnoreEmpty">
                        <label class="form-check-label" for="gtFilterIgnoreEmpty">{{ __('app.tracking.report_filter_ignore_empty') }}</label>
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
                    <button type="button" class="btn btn-outline-secondary btn-sm" id="gtReportCsv" disabled title="{{ __('app.tracking.report_load_before_export') }}">{{ __('app.tracking.export_csv') }}</button>
                    <button type="button" class="btn btn-outline-secondary btn-sm" id="gtReportXlsx" disabled title="{{ __('app.tracking.report_load_before_export') }}">{{ __('app.tracking.export_xls') }}</button>
                    <button type="button" class="btn btn-outline-secondary btn-sm" id="gtReportPdf" disabled title="{{ __('app.tracking.report_load_before_export') }}">{{ __('app.tracking.export_pdf') }}</button>
                </div>
            </aside>

            <div class="gt-report-main">
                <div id="gtReportKpis" class="gt-report-kpis" hidden></div>
                <div id="gtReportMap" class="mb-3" hidden></div>
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
        'selectVehicle' => __('app.tracking.report_select_vehicle'),
        'devicesCapped' => __('app.tracking.report_devices_capped'),
        'analyticsDownsampled' => __('app.tracking.report_analytics_downsampled'),
        'presetToday' => __('app.tracking.report_preset_today'),
        'presetYesterday' => __('app.tracking.report_preset_yesterday'),
        'preset7d' => __('app.tracking.report_preset_7d'),
        'loadingProgress' => __('app.tracking.report_loading_progress'),
        'loadingReport' => __('app.tracking.report_loading'),
        'waitUntilLoaded' => __('app.tracking.report_wait_until_loaded'),
        'loadBeforeExport' => __('app.tracking.report_load_before_export'),
        'exportFailed' => __('app.tracking.report_export_failed'),
    ]);
@endphp
<script>
window.APP_TIMEZONE = @json(config('app.timezone', 'Asia/Riyadh'));
window.TRACKING_REPORTS_CONFIG = {
    generateUrl: @json($generateUrl),
    exportUrl: @json($exportUrl),
    lockedType: @json($lockedType ?? 'odometer'),
    googleMapsKey: @json(config('services.google.maps_key')),
    googleMapsMapId: @json(config('services.google.maps_map_id')),
    hasGoogleMapsKey: @json(trim((string) config('services.google.maps_key', '')) !== ''),
    currentLang: @json(app()->getLocale()),
    timezone: @json(config('app.timezone', 'Asia/Riyadh')),
    i18n: @json($reportI18n),
};
</script>
<script src="{{ protected_js('app-datetime.js') }}"></script>
<script src="{{ protected_js('tracking-reports.js') }}"></script>
@endpush
