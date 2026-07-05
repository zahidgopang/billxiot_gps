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
        background: rgba(255, 255, 255, 0.7);
        z-index: 5;
        border-radius: 8px;
    }
    .gt-report-overlay[hidden] { display: none !important; }
    .gt-report-empty {
        padding: 2.5rem 1rem;
        text-align: center;
        color: #94a3b8;
        font-size: 0.9rem;
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
        background: #f8fafc;
        border: 1px solid #e2e8f0;
        border-radius: 8px;
        padding: 0.55rem 0.7rem;
    }
    .gt-report-kpi-label {
        display: block;
        font-size: 0.68rem;
        text-transform: uppercase;
        letter-spacing: 0.03em;
        color: #64748b;
        margin-bottom: 0.15rem;
    }
    .gt-report-kpi-value {
        font-size: 0.95rem;
        font-weight: 700;
        color: #0f172a;
        direction: ltr;
        unicode-bidi: embed;
    }
    #gtReportMap {
        height: 280px;
        border-radius: 8px;
        border: 1px solid #e2e8f0;
    }
    .gt-report-vehicle-actions {
        display: flex;
        gap: 0.35rem;
        margin-bottom: 0.35rem;
    }
    .gt-report-vehicle-actions .btn { font-size: 0.72rem; padding: 0.15rem 0.45rem; }
    .gt-report-subtitle { color: #64748b; font-size: 0.875rem; margin-bottom: 1rem; }
</style>
@endpush
@section('content')
<div class="gt-module-page">
    @include('tracking.partials.hub-nav')
    <div class="gt-module-card">
        <h5 class="mb-1">{{ __('app.tracking.reports_title') }}</h5>
        <p class="gt-report-subtitle">{{ __('app.tracking.report_subtitle') }}</p>
        <div class="row g-3">
            <div class="col-lg-3">
                <label class="form-label small">{{ __('app.tracking.report_type') }}</label>
                <select id="gtReportType" class="form-select form-select-sm">
                    <option value="summary">{{ __('app.tracking.report_summary') }}</option>
                    <option value="trips">{{ __('app.tracking.report_trips') }}</option>
                    <option value="stops">{{ __('app.tracking.report_stops') }}</option>
                    <option value="events">{{ __('app.tracking.report_events') }}</option>
                    <option value="route">{{ __('app.tracking.report_route') }}</option>
                    <option value="positions">{{ __('app.tracking.report_positions') }}</option>
                </select>
                <label class="form-label small mt-2">{{ __('app.tracking.report_language') }}</label>
                <select id="gtReportLang" class="form-select form-select-sm">
                    <option value="en" @selected(app()->getLocale() === 'en')>{{ __('app.tracking.report_lang_en') }}</option>
                    <option value="ar" @selected(app()->getLocale() === 'ar')>{{ __('app.tracking.report_lang_ar') }}</option>
                </select>
                <label class="form-label small mt-2">{{ __('app.tracking.date_from') }}</label>
                <input type="datetime-local" id="gtReportFrom" class="form-control form-control-sm admin-ltr" dir="ltr">
                <label class="form-label small mt-2">{{ __('app.tracking.date_to') }}</label>
                <input type="datetime-local" id="gtReportTo" class="form-control form-control-sm admin-ltr" dir="ltr">
                <label class="form-label small mt-2">{{ __('app.tracking.vehicles') }}</label>
                <div class="gt-report-vehicle-actions">
                    <button type="button" class="btn btn-outline-secondary btn-sm" id="gtReportSelectAll">{{ __('app.tracking.report_select_all') }}</button>
                    <button type="button" class="btn btn-outline-secondary btn-sm" id="gtReportSelectNone">{{ __('app.tracking.report_select_none') }}</button>
                </div>
                <div class="gt-vehicle-picker" id="gtReportVehicles">
                    @foreach($vehicles as $v)
                        <label><input type="checkbox" value="{{ $v['id'] }}"> {{ $v['title'] ?? $v['plate'] ?? '#'.$v['id'] }}</label>
                    @endforeach
                </div>
                <div class="d-flex flex-wrap gap-2 mt-3">
                    <button type="button" class="btn btn-primary btn-sm" id="gtReportRun">{{ __('app.tracking.load_history') }}</button>
                    <button type="button" class="btn btn-outline-secondary btn-sm" id="gtReportCsv">{{ __('app.tracking.export_csv') }}</button>
                    <button type="button" class="btn btn-outline-secondary btn-sm" id="gtReportXlsx">{{ __('app.tracking.export_xls') }}</button>
                    <button type="button" class="btn btn-outline-secondary btn-sm" id="gtReportPdf">{{ __('app.tracking.export_pdf') }}</button>
                </div>
            </div>
            <div class="col-lg-9">
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
                        <div class="spinner-border text-primary" role="status" aria-hidden="true"></div>
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
    ]);
@endphp
<script>
window.TRACKING_REPORTS_CONFIG = {
    generateUrl: @json($generateUrl),
    exportUrl: @json($exportUrl),
    googleMapsKey: @json(config('services.google.maps_key')),
    googleMapsMapId: @json(config('services.google.maps_map_id')),
    currentLang: @json(app()->getLocale()),
    i18n: @json($reportI18n),
};
</script>
@include('partials.google-maps-platform')
<script src="{{ protected_js('vehicle-marker.js') }}"></script>
<script src="{{ protected_js('fleet-map-renderer.js') }}"></script>
<script src="{{ protected_js('tracking-reports.js') }}"></script>
@endpush
