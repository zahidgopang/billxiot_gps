@extends($layout)
@section('title', __('app.tracking.drivers_title') . ' - ' . __('app.brand'))
@push('styles')@include('tracking.partials.module-styles')@endpush
@section('content')
<div class="gt-module-page">
    @include('tracking.partials.hub-nav')
    <div class="gt-module-card">
        <h5 class="mb-3">{{ __('app.tracking.drivers_title') }}</h5>
        <form id="gtDriverForm" class="row g-2 mb-3">
            <div class="col-md-3"><input name="name" class="form-control form-control-sm" placeholder="{{ __('app.tracking.driver_name') }}" required></div>
            <div class="col-md-3"><input name="phone" class="form-control form-control-sm admin-ltr" dir="ltr" placeholder="{{ __('app.tracking.driver_phone') }}"></div>
            <div class="col-md-2"><button type="submit" class="btn btn-primary btn-sm">{{ __('app.common.add') }}</button></div>
        </form>
        <div class="table-responsive">
            <table class="table table-sm"><thead><tr><th>{{ __('app.tracking.driver_name') }}</th><th>{{ __('app.tracking.driver_phone') }}</th><th>{{ __('app.tracking.vehicle') }}</th><th></th></tr></thead><tbody id="gtDriverBody"></tbody></table>
        </div>
    </div>
</div>
@endsection
@push('scripts')
<script>window.TRACKING_DRIVERS_CONFIG = { jsonUrl: @json($jsonUrl), storeUrl: @json($storeUrl), vehicles: @json($vehicles), confirmDelete: @json(__('app.tracking.driver_confirm_delete')), deleteFailed: @json(__('app.tracking.driver_delete_failed')) };</script>
<script src="{{ protected_js('tracking-drivers.js') }}"></script>
@endpush
