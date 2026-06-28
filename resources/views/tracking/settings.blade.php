@extends($layout)
@section('title', __('app.tracking.settings_title') . ' - ' . __('app.brand'))
@push('styles')
@include('tracking.partials.module-styles')
<style>
    .gt-settings-sec { font-size:.8rem; font-weight:600; color:#1976d2; text-transform:uppercase; letter-spacing:.03em; margin:1rem 0 .5rem; }
    .gt-settings-sec:first-child { margin-top:0; }
</style>
@endpush
@section('content')
<div class="gt-module-page">
    @include('tracking.partials.hub-nav')
    <div class="gt-module-card">
        <h5 class="mb-1">{{ __('app.tracking.settings_title') }}</h5>
        <p class="small text-muted mb-3">{{ __('app.tracking.settings_hint') }}</p>
        <form id="gtSettingsForm" class="row g-3">
            <div class="col-12"><div class="gt-settings-sec">{{ __('app.tracking.settings_sec_speed') }}</div></div>
            @foreach(['overspeed_kmh','stopped_speed_kmh','slow_speed_max_kmh','moving_speed_kmh'] as $key)
                <div class="col-md-4">
                    <label class="form-label small" for="set_{{ $key }}">{{ __('app.tracking.setting_'.$key) }}</label>
                    <input type="number" step="any" min="0" id="set_{{ $key }}" name="{{ $key }}" class="form-control form-control-sm admin-ltr" dir="ltr" value="{{ $settings[$key] ?? '' }}">
                </div>
            @endforeach

            <div class="col-12"><div class="gt-settings-sec">{{ __('app.tracking.settings_sec_connectivity') }}</div></div>
            @foreach(['delayed_min_seconds','stale_min_seconds','offline_seconds'] as $key)
                <div class="col-md-4">
                    <label class="form-label small" for="set_{{ $key }}">{{ __('app.tracking.setting_'.$key) }}</label>
                    <input type="number" step="1" min="0" id="set_{{ $key }}" name="{{ $key }}" class="form-control form-control-sm admin-ltr" dir="ltr" value="{{ $settings[$key] ?? '' }}">
                </div>
            @endforeach

            <div class="col-12"><div class="gt-settings-sec">{{ __('app.tracking.settings_sec_signals') }}</div></div>
            @foreach(['low_battery_percent','gsm_weak_percent','gps_weak_percent','gps_min_satellites','event_cooldown_seconds'] as $key)
                <div class="col-md-4">
                    <label class="form-label small" for="set_{{ $key }}">{{ __('app.tracking.setting_'.$key) }}</label>
                    <input type="number" step="any" min="0" id="set_{{ $key }}" name="{{ $key }}" class="form-control form-control-sm admin-ltr" dir="ltr" value="{{ $settings[$key] ?? '' }}">
                </div>
            @endforeach

            <div class="col-12">
                <button type="submit" class="btn btn-primary btn-sm"><i class="fas fa-save me-1"></i>{{ __('app.common.save') }}</button>
            </div>
        </form>
    </div>
</div>
@endsection
@push('scripts')
<script>window.TRACKING_SETTINGS_CONFIG = {
    jsonUrl: @json($jsonUrl),
    updateUrl: @json($updateUrl),
    i18n: {
        saved: @json(__('app.tracking.settings_saved')),
        failed: @json(__('app.tracking.settings_save_failed')),
    },
};</script>
<script src="{{ protected_js('tracking-settings.js') }}"></script>
@endpush
