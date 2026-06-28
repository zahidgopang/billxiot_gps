@extends($layout)
@section('title', __('app.tracking.geofences_title') . ' - ' . __('app.brand'))
@push('styles')
@include('tracking.partials.module-styles')
<style>
    .gt-geo-toolbar { display: flex; flex-wrap: wrap; gap: 0.5rem; align-items: flex-end; margin-bottom: 0.75rem; }
    .gt-geo-toolbar .gt-geo-field { display: flex; flex-direction: column; }
    .gt-geo-toolbar label { font-size: 0.72rem; color: #64748b; margin-bottom: 0.15rem; }
    .gt-geo-toolbar .form-select-sm, .gt-geo-toolbar .form-control-sm { min-width: 170px; }
    .gt-geo-btns { display: flex; gap: 0.4rem; flex-wrap: wrap; }
    .gt-geo-row { display: flex; align-items: center; justify-content: space-between; gap: 0.5rem; padding: 0.4rem 0.5rem; border-bottom: 1px solid #eef0f3; }
    .gt-geo-row:last-child { border-bottom: 0; }
    .gt-geo-swatch { width: 12px; height: 12px; border-radius: 3px; flex-shrink: 0; display: inline-block; margin-inline-end: 0.4rem; }
</style>
@endpush
@section('content')
<div class="gt-module-page">
    @include('tracking.partials.hub-nav')
    <div class="gt-module-card">
        <h5 class="mb-3">{{ __('app.tracking.geofences_title') }}</h5>

        <div class="gt-geo-toolbar">
            <div class="gt-geo-field">
                <label for="gtGeoDevice">{{ __('app.tracking.vehicle') }}</label>
                <select id="gtGeoDevice" class="form-select form-select-sm">
                    @foreach($vehicles as $v)
                        <option value="{{ $v['id'] }}">{{ $v['title'] ?? $v['plate'] ?? '#'.$v['id'] }}</option>
                    @endforeach
                </select>
            </div>
            <div class="gt-geo-field">
                <label for="gtGeoName">{{ __('app.tracking.geofence_name') }}</label>
                <input type="text" id="gtGeoName" class="form-control form-control-sm" placeholder="{{ __('app.tracking.geofence_name') }}">
            </div>
            <div class="gt-geo-btns">
                <button type="button" class="btn btn-outline-primary btn-sm" id="gtGeoDrawPolygon"><i class="fas fa-draw-polygon me-1"></i>{{ __('app.tracking.geofence_draw_polygon') }}</button>
                <button type="button" class="btn btn-outline-primary btn-sm" id="gtGeoDrawCircle"><i class="far fa-circle me-1"></i>{{ __('app.tracking.geofence_draw_circle') }}</button>
                <button type="button" class="btn btn-primary btn-sm" id="gtGeoSave" disabled><i class="fas fa-save me-1"></i>{{ __('app.tracking.geofence_save') }}</button>
                <button type="button" class="btn btn-outline-secondary btn-sm" id="gtGeoCancel" hidden>{{ __('app.tracking.geofence_cancel') }}</button>
            </div>
        </div>

        <div id="gtGeofenceMap" class="mb-3"></div>
        <div id="gtGeofenceList" class="small text-muted">{{ __('app.map.loading_geofences') }}</div>
    </div>
</div>
@endsection
@push('scripts')
<script>window.TRACKING_GEOFENCES_CONFIG = {
    jsonUrl: @json($jsonUrl),
    storeUrl: @json($storeUrl),
    googleMapsKey: @json($googleMapsKey),
    csrfToken: @json(csrf_token()),
    i18n: {
        saved: @json(__('app.tracking.geofence_saved')),
        saveFailed: @json(__('app.tracking.geofence_save_failed')),
        drawFirst: @json(__('app.tracking.geofence_draw_first')),
        pickVehicle: @json(__('app.tracking.geofence_pick_vehicle')),
        deleteConfirm: @json(__('app.tracking.geofence_delete_confirm')),
        deleteFailed: @json(__('app.tracking.geofence_delete_failed')),
        none: @json(__('app.tracking.geofence_none')),
        del: @json(__('app.tracking.geofence_delete')),
        drawingUnavailable: @json(__('app.tracking.geofence_drawing_unavailable')),
    },
};</script>
<script src="{{ protected_js('tracking-geofences.js') }}"></script>
@endpush
