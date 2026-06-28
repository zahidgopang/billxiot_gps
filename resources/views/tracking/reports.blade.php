@extends($layout)
@section('title', __('app.tracking.reports_title') . ' - ' . __('app.brand'))
@push('styles')@include('tracking.partials.module-styles')@endpush
@section('content')
<div class="gt-module-page">
    @include('tracking.partials.hub-nav')
    <div class="gt-module-card">
        <h5 class="mb-3">{{ __('app.tracking.reports_title') }}</h5>
        <div class="row g-3">
            <div class="col-lg-3">
                <label class="form-label small">{{ __('app.tracking.report_type') }}</label>
                <select id="gtReportType" class="form-select form-select-sm">
                    <option value="summary">{{ __('app.tracking.report_summary') }}</option>
                    <option value="trips">{{ __('app.tracking.report_trips') }}</option>
                    <option value="stops">{{ __('app.tracking.report_stops') }}</option>
                    <option value="events">{{ __('app.tracking.report_events') }}</option>
                    <option value="route">{{ __('app.tracking.report_route') }}</option>
                </select>
                <label class="form-label small mt-2">{{ __('app.tracking.date_from') }}</label>
                <input type="date" id="gtReportFrom" class="form-control form-control-sm admin-ltr" dir="ltr">
                <label class="form-label small mt-2">{{ __('app.tracking.date_to') }}</label>
                <input type="date" id="gtReportTo" class="form-control form-control-sm admin-ltr" dir="ltr">
                <label class="form-label small mt-2">{{ __('app.tracking.vehicles') }}</label>
                <div class="gt-vehicle-picker" id="gtReportVehicles">
                    @foreach($vehicles as $v)
                        <label><input type="checkbox" value="{{ $v['id'] }}"> {{ $v['title'] ?? $v['plate'] ?? '#'.$v['id'] }}</label>
                    @endforeach
                </div>
                <div class="d-flex flex-wrap gap-2 mt-3">
                    <button type="button" class="btn btn-primary btn-sm" id="gtReportRun">{{ __('app.tracking.load_history') }}</button>
                    <button type="button" class="btn btn-outline-secondary btn-sm" id="gtReportCsv">CSV</button>
                    <button type="button" class="btn btn-outline-secondary btn-sm" id="gtReportXlsx">XLS</button>
                    <button type="button" class="btn btn-outline-secondary btn-sm" id="gtReportPdf">PDF</button>
                </div>
            </div>
            <div class="col-lg-9">
                <div id="gtReportMap" class="mb-3" hidden></div>
                <div class="table-responsive">
                    <table class="table table-sm table-hover" id="gtReportTable">
                        <thead><tr id="gtReportHead"></tr></thead>
                        <tbody id="gtReportBody"></tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection
@push('scripts')
<script>
window.TRACKING_REPORTS_CONFIG = {
    generateUrl: @json($generateUrl),
    exportUrl: @json($exportUrl),
    googleMapsKey: @json(config('services.google.maps_key')),
};
</script>
<script src="{{ protected_js('fleet-map-renderer.js') }}"></script>
<script src="{{ protected_js('tracking-reports.js') }}"></script>
@endpush
