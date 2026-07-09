@extends($layout)
@section('title', __('app.tracking.events_title') . ' - ' . __('app.brand'))
@push('styles')@include('tracking.partials.module-styles')@endpush
@section('content')
<div class="gt-module-page">
    @include('tracking.partials.hub-nav')
    <div class="gt-module-card">
        <h5 class="mb-3">{{ __('app.tracking.events_title') }}</h5>
        <div class="row g-2 mb-3">
            <div class="col-md-3"><input type="date" id="gtEventsFrom" class="form-control form-control-sm admin-ltr" dir="ltr"></div>
            <div class="col-md-3"><input type="date" id="gtEventsTo" class="form-control form-control-sm admin-ltr" dir="ltr"></div>
            <div class="col-md-3">
                <select id="gtEventsDevice" class="form-select form-select-sm">
                    <option value="">{{ __('app.tracking.all_vehicles') }}</option>
                    @foreach($vehicles as $v)
                        <option value="{{ $v['id'] }}">{{ $v['title'] ?? '#'.$v['id'] }}</option>
                    @endforeach
                </select>
            </div>
            <div class="col-md-3"><button type="button" class="btn btn-primary btn-sm" id="gtEventsLoad">{{ __('app.common.filter') }}</button></div>
        </div>
        <div class="table-responsive">
            <table class="table table-sm table-hover">
                <thead><tr><th>{{ __('app.common.time') }}</th><th>{{ __('app.tracking.vehicle') }}</th><th>{{ __('app.common.type') }}</th><th>{{ __('app.common.message') }}</th></tr></thead>
                <tbody id="gtEventsBody"></tbody>
            </table>
        </div>
    </div>
</div>
@endsection
@push('scripts')
<script>
    window.TRACKING_EVENTS_CONFIG = {
        jsonUrl: @json($jsonUrl),
        panel: @json($panel),
        i18n: {
            loadFailed: @json(__('app.tracking.events_load_failed')),
            eventsPermissionDenied: @json(__('app.tracking.events_permission_denied')),
            accessDeniedTitle: @json(__('app.errors.403_title')),
            ok: @json(__('app.common.ok')),
            noData: @json(__('app.tracking.no_data')),
        },
    };
</script>
<script src="{{ protected_js('tracking-events.js') }}"></script>
@endpush
