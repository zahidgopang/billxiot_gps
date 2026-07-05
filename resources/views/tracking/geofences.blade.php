@extends($layout)
@section('title', __('app.tracking.geofences_title') . ' - ' . __('app.brand'))
@push('styles')
@include('tracking.partials.module-styles')
<style>
    .gt-geo-layout { display: grid; grid-template-columns: minmax(220px, 280px) 1fr; gap: 1rem; }
    @media (max-width: 991px) { .gt-geo-layout { grid-template-columns: 1fr; } }
    .gt-geo-side { display: flex; flex-direction: column; gap: 0.75rem; min-height: 0; }
    .gt-geo-side h6 { font-size: 0.8125rem; margin: 0; color: #334155; }
    .gt-geo-vehicle-picker { max-height: 260px; overflow-y: auto; border: 1px solid #e2e8f0; border-radius: 6px; padding: 0.5rem; background: #fafbfc; }
    .gt-geo-vehicle-picker label { display: flex; gap: 0.45rem; align-items: flex-start; font-size: 0.8125rem; margin-bottom: 0.35rem; cursor: pointer; line-height: 1.35; }
    .gt-geo-vehicle-picker input { margin-top: 0.2rem; flex-shrink: 0; }
    .gt-geo-vehicle-meta { display: block; font-size: 0.7rem; color: #64748b; }
    .gt-geo-toolbar { display: flex; flex-wrap: wrap; gap: 0.5rem; align-items: flex-end; }
    .gt-geo-toolbar .gt-geo-field { display: flex; flex-direction: column; flex: 1 1 160px; }
    .gt-geo-toolbar label { font-size: 0.72rem; color: #64748b; margin-bottom: 0.15rem; }
    .gt-geo-btns { display: flex; gap: 0.4rem; flex-wrap: wrap; }
    #gtGeofenceMap { height: min(58vh, 520px); min-height: 320px; }
    .gt-geo-row { display: flex; align-items: center; justify-content: space-between; gap: 0.5rem; padding: 0.4rem 0.5rem; border-bottom: 1px solid #eef0f3; }
    .gt-geo-row:last-child { border-bottom: 0; }
    .gt-geo-swatch { width: 12px; height: 12px; border-radius: 3px; flex-shrink: 0; display: inline-block; margin-inline-end: 0.4rem; }
    .gt-geo-pick-actions { display: flex; gap: 0.5rem; font-size: 0.75rem; }
</style>
@endpush
@section('content')
<div class="gt-module-page">
    @include('tracking.partials.hub-nav')
    <div class="gt-module-card">
        <h5 class="mb-3">{{ __('app.tracking.geofences_title') }}</h5>

        <div class="gt-geo-layout">
            <aside class="gt-geo-side">
                <div>
                    <div class="d-flex align-items-center justify-content-between mb-1">
                        <h6>{{ __('app.tracking.vehicle_list') }}</h6>
                        <div class="gt-geo-pick-actions">
                            <button type="button" class="btn btn-link btn-sm p-0" id="gtGeoSelectAll">{{ __('app.tracking.select_all') }}</button>
                            <button type="button" class="btn btn-link btn-sm p-0" id="gtGeoSelectNone">{{ __('app.tracking.select_none') }}</button>
                        </div>
                    </div>
                    <div class="gt-geo-vehicle-picker" id="gtGeoVehicles">
                        @forelse($vehicles as $v)
                            <label data-veh-id="{{ $v['id'] }}">
                                <input type="checkbox" class="gt-geo-veh-check" value="{{ $v['id'] }}" checked>
                                <span>
                                    {{ $v['title'] ?? $v['plate'] ?? '#'.$v['id'] }}
                                    <span class="gt-geo-vehicle-meta">{{ $v['status_label'] ?? '' }}</span>
                                </span>
                            </label>
                        @empty
                            <div class="text-muted small">{{ __('app.tracking.no_vehicles') }}</div>
                        @endforelse
                    </div>
                    <div class="form-text">{{ __('app.tracking.geofence_track_hint') }}</div>
                </div>

                <div class="gt-geo-field">
                    <label for="gtGeoName">{{ __('app.tracking.geofence_name') }}</label>
                    <input type="text" id="gtGeoName" class="form-control form-control-sm" placeholder="{{ __('app.tracking.geofence_name') }}" @disabled(empty($canManageGeofences))>
                </div>

                @if(! empty($canManageGeofences))
                <div class="gt-geo-btns">
                    <button type="button" class="btn btn-outline-primary btn-sm" id="gtGeoDrawPolygon"><i class="fas fa-draw-polygon me-1"></i>{{ __('app.tracking.geofence_draw_polygon') }}</button>
                    <button type="button" class="btn btn-outline-primary btn-sm" id="gtGeoDrawCircle"><i class="far fa-circle me-1"></i>{{ __('app.tracking.geofence_draw_circle') }}</button>
                    <button type="button" class="btn btn-primary btn-sm" id="gtGeoSave" disabled><i class="fas fa-save me-1"></i>{{ __('app.tracking.geofence_save') }}</button>
                    <button type="button" class="btn btn-outline-secondary btn-sm" id="gtGeoCancel" hidden>{{ __('app.tracking.geofence_cancel') }}</button>
                </div>
                @else
                <p class="small text-muted mb-0">{{ __('app.tracking.geofence_view_only_hint') }}</p>
                @endif
            </aside>

            <div>
                <div id="gtGeofenceMap" class="mb-3"></div>
                <div id="gtGeofenceList" class="small text-muted">{{ __('app.map.loading_geofences') }}</div>
            </div>
        </div>
    </div>
</div>
@endsection
@push('scripts')
<script>window.TRACKING_GEOFENCES_CONFIG = {
    jsonUrl: @json($jsonUrl),
    storeUrl: @json($storeUrl),
    deleteUrl: @json($deleteUrl),
    liveJsonUrl: @json($liveJsonUrl),
    googleMapsKey: @json($googleMapsKey),
    csrfToken: @json(csrf_token()),
    pollIntervalMs: 4000,
    stateColors: @json($stateColors),
    canManageGeofences: @json(! empty($canManageGeofences)),
    vehicles: @json($vehicles),
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
        mapLoadFailed: @json(__('app.map.loading_map_failed')),
        mapKeyMissing: @json(__('app.map.map_api_key_missing')),
        savedForVehicle: @json(__('app.tracking.geofence_saved_for_vehicle')),
    },
};</script>
<script src="{{ protected_js('geofence-map-draw.js') }}"></script>
<script src="{{ protected_js('tracking-geofences.js') }}"></script>
@endpush
