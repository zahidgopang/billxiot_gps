@extends($layout)
@section('title', __('app.tracking.commands_title') . ' - ' . __('app.brand'))
@push('styles')
@include('tracking.partials.module-styles')
<style>
    .gt-cmd-form { display:flex; flex-wrap:wrap; gap:.5rem; align-items:flex-end; margin-bottom:1rem; }
    .gt-cmd-field { display:flex; flex-direction:column; gap:.25rem; min-width:180px; }
    .gt-cmd-field label { font-size:.72rem; text-transform:uppercase; letter-spacing:.03em; color:#64748b; margin:0; }
    .gt-cmd-field--data { flex:1 1 220px; }
    .gt-cmd-badge { font-size:.7rem; }
    .gt-cmd-empty { text-align:center; color:#94a3b8; padding:1.5rem 0; }
    #gtCmdBody td { vertical-align:middle; }
</style>
@endpush
@section('content')
<div class="gt-module-page">
    @include('tracking.partials.hub-nav')
    <div class="gt-module-card">
        <h5 class="mb-1">{{ __('app.tracking.commands_title') }}</h5>
        <p class="small text-muted">{{ __('app.tracking.commands_hint') }}</p>

        <form id="gtCmdForm" class="gt-cmd-form">
            <div class="gt-cmd-field">
                <label for="gtCmdDevice">{{ __('app.tracking.vehicle') }}</label>
                <select id="gtCmdDevice" name="device_id" class="form-select form-select-sm" required>
                    @foreach($vehicles as $v)<option value="{{ $v['id'] }}">{{ $v['title'] ?? '#'.$v['id'] }}</option>@endforeach
                </select>
            </div>
            <div class="gt-cmd-field">
                <label for="gtCmdType">{{ __('app.common.type') }}</label>
                <select id="gtCmdType" name="type" class="form-select form-select-sm" required>
                    @foreach($commandTypes as $value => $label)<option value="{{ $value }}">{{ $label }}</option>@endforeach
                </select>
            </div>
            <div class="gt-cmd-field gt-cmd-field--data" id="gtCmdDataWrap">
                <label for="gtCmdData">{{ __('app.tracking.command_data') }}</label>
                <input id="gtCmdData" name="data" class="form-control form-control-sm" placeholder="{{ __('app.tracking.command_data') }}">
            </div>
            <div class="gt-cmd-field" style="min-width:auto;">
                <label>&nbsp;</label>
                <button type="submit" class="btn btn-warning btn-sm">
                    <i class="fas fa-paper-plane me-1"></i>{{ __('app.tracking.send_command') }}
                </button>
            </div>
        </form>

        <div class="table-responsive">
            <table class="table table-sm align-middle">
                <thead><tr>
                    <th>{{ __('app.common.time') }}</th>
                    <th>{{ __('app.tracking.vehicle') }}</th>
                    <th>{{ __('app.common.type') }}</th>
                    <th>{{ __('app.tracking.command_data') }}</th>
                    <th>{{ __('app.common.status') }}</th>
                    <th>{{ __('app.tracking.command_requested_by') }}</th>
                    <th></th>
                </tr></thead>
                <tbody id="gtCmdBody"></tbody>
            </table>
        </div>
    </div>
</div>
@endsection
@push('scripts')
<script>window.TRACKING_COMMANDS_CONFIG = {
    jsonUrl: @json($jsonUrl),
    sendUrl: @json($sendUrl),
    cancelUrl: @json($cancelUrl),
    i18n: {
        confirmSend: @json(__('app.tracking.command_confirm_send')),
        confirmCancel: @json(__('app.tracking.command_confirm_cancel')),
        sent: @json(__('app.tracking.command_queued_ok')),
        failed: @json(__('app.common.failed')),
        noHistory: @json(__('app.tracking.command_no_history')),
        cancel: @json(__('app.common.cancel')),
        statusPending: @json(__('app.tracking.command_status_pending')),
        statusSent: @json(__('app.tracking.command_status_sent')),
        statusDelivered: @json(__('app.tracking.command_status_delivered')),
        statusExecuted: @json(__('app.tracking.command_status_executed')),
        statusFailed: @json(__('app.tracking.command_status_failed')),
        statusTimeout: @json(__('app.tracking.command_status_timeout')),
        statusCanceled: @json(__('app.tracking.command_status_canceled')),
    },
};</script>
<script src="{{ protected_js('tracking-commands.js') }}"></script>
@endpush
