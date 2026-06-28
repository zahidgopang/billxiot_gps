@extends($layout)
@section('title', __('app.tracking.commands_title') . ' - ' . __('app.brand'))
@push('styles')@include('tracking.partials.module-styles')@endpush
@section('content')
<div class="gt-module-page">
    @include('tracking.partials.hub-nav')
    <div class="gt-module-card">
        <h5 class="mb-3">{{ __('app.tracking.commands_title') }}</h5>
        <p class="small text-muted">{{ __('app.tracking.commands_hint') }}</p>
        <form id="gtCmdForm" class="row g-2 mb-3">
            <div class="col-md-3">
                <select name="device_id" class="form-select form-select-sm" required>
                    @foreach($vehicles as $v)<option value="{{ $v['id'] }}">{{ $v['title'] ?? '#'.$v['id'] }}</option>@endforeach
                </select>
            </div>
            <div class="col-md-3">
                <select name="type" class="form-select form-select-sm" required>
                    @foreach($allowedTypes as $t)<option value="{{ $t }}">{{ $t }}</option>@endforeach
                </select>
            </div>
            <div class="col-md-3"><input name="data" class="form-control form-control-sm" placeholder="{{ __('app.tracking.command_data') }}"></div>
            <div class="col-md-2"><button type="submit" class="btn btn-warning btn-sm">{{ __('app.tracking.send_command') }}</button></div>
        </form>
        <div class="table-responsive">
            <table class="table table-sm"><thead><tr><th>{{ __('app.common.time') }}</th><th>{{ __('app.tracking.vehicle') }}</th><th>{{ __('app.common.type') }}</th></tr></thead><tbody id="gtCmdBody"></tbody></table>
        </div>
    </div>
</div>
@endsection
@push('scripts')
<script>window.TRACKING_COMMANDS_CONFIG = { jsonUrl: @json($jsonUrl), sendUrl: @json($sendUrl) };</script>
<script src="{{ protected_js('tracking-commands.js') }}"></script>
@endpush
