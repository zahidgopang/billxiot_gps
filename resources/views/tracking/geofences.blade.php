@extends($layout)
@section('title', __('app.tracking.geofences_title') . ' - ' . __('app.brand'))
@push('styles')@include('tracking.partials.module-styles')@endpush
@section('content')
<div class="gt-module-page">
    @include('tracking.partials.hub-nav')
    <div class="gt-module-card">
        <h5 class="mb-3">{{ __('app.tracking.geofences_title') }}</h5>
        <div id="gtGeofenceMap" class="mb-3"></div>
        <div id="gtGeofenceList" class="small text-muted">{{ __('app.map.loading_geofences') }}</div>
    </div>
</div>
@endsection
@push('scripts')
<script>window.TRACKING_GEOFENCES_CONFIG = { jsonUrl: @json($jsonUrl), storeUrl: @json($storeUrl), googleMapsKey: @json($googleMapsKey) };</script>
<script src="{{ protected_js('tracking-geofences.js') }}"></script>
@endpush
