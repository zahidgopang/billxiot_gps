@extends($layout)
@section('title', __('app.tracking.settings_title') . ' - ' . __('app.brand'))
@push('styles')@include('tracking.partials.module-styles')@endpush
@section('content')
<div class="gt-module-page">
    @include('tracking.partials.hub-nav')
    <div class="gt-module-card">
        <h5 class="mb-3">{{ __('app.tracking.settings_title') }}</h5>
        <form id="gtSettingsForm" class="row g-3">
            @foreach(['overspeed_kmh','stopped_speed_kmh','moving_speed_kmh','offline_seconds','stale_min_seconds','delayed_min_seconds','low_battery_percent'] as $key)
                <div class="col-md-4">
                    <label class="form-label small">{{ str_replace('_', ' ', $key) }}</label>
                    <input type="number" step="any" name="{{ $key }}" class="form-control form-control-sm admin-ltr" dir="ltr" value="{{ $settings[$key] ?? '' }}">
                </div>
            @endforeach
            <div class="col-12"><button type="submit" class="btn btn-primary btn-sm">{{ __('app.common.save') }}</button></div>
        </form>
    </div>
</div>
@endsection
@push('scripts')
<script>window.TRACKING_SETTINGS_CONFIG = { updateUrl: @json($updateUrl) };</script>
<script src="{{ protected_js('tracking-settings.js') }}"></script>
@endpush
