@extends($layout)
@section('title', __('app.tracking.notifications_title') . ' - ' . __('app.brand'))
@push('styles')@include('tracking.partials.module-styles')@endpush
@section('content')
<div class="gt-module-page">
    @include('tracking.partials.hub-nav')
    <div class="gt-module-card">
        <h5 class="mb-3">{{ __('app.tracking.notifications_title') }}</h5>
        <form id="gtNotifForm">
            <div class="table-responsive">
                <table class="table table-sm">
                    <thead><tr><th>{{ __('app.common.type') }}</th><th>{{ __('app.tracking.channel_web') }}</th><th>{{ __('app.tracking.channel_push') }}</th></tr></thead>
                    <tbody id="gtNotifBody"></tbody>
                </table>
            </div>
            <button type="submit" class="btn btn-primary btn-sm">{{ __('app.common.save') }}</button>
        </form>
    </div>
</div>
@endsection
@push('scripts')
<script>window.TRACKING_NOTIFICATIONS_CONFIG = { jsonUrl: @json($jsonUrl), updateUrl: @json($updateUrl) };</script>
<script src="{{ protected_js('tracking-notifications.js') }}"></script>
@endpush
