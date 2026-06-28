@extends($layout)
@section('title', __('app.tracking.tasks_title') . ' - ' . __('app.brand'))
@push('styles')@include('tracking.partials.module-styles')@endpush
@section('content')
<div class="gt-module-page">
    @include('tracking.partials.hub-nav')
    <div class="gt-module-card">
        <div class="d-flex justify-content-between align-items-center mb-3 flex-wrap gap-2">
            <h5 class="mb-0">{{ __('app.tracking.tasks_title') }}</h5>
            <div class="d-flex gap-2">
                <button type="button" id="tsDeleteAll" class="btn btn-outline-danger btn-sm">{{ __('app.tracking.task_delete_all') }}</button>
                <a id="tsExport" href="#" class="btn btn-outline-secondary btn-sm">{{ __('app.tracking.task_export') }}</a>
            </div>
        </div>

        {{-- Filter bar (Object + time window + Show) --}}
        <form id="tsFilter" class="row g-2 align-items-end mb-3">
            <div class="col-md-3">
                <label class="form-label small mb-1">{{ __('app.tracking.task_object') }}</label>
                <select name="device_id" class="form-select form-select-sm">
                    <option value="0">{{ __('app.tracking.task_all_objects') }}</option>
                    @foreach($vehicles as $v)<option value="{{ $v['id'] }}">{{ $v['title'] ?? '#'.$v['id'] }}</option>@endforeach
                </select>
            </div>
            <div class="col-md-3">
                <label class="form-label small mb-1">{{ __('app.tracking.task_time_from') }}</label>
                <input type="datetime-local" name="from" class="form-control form-control-sm">
            </div>
            <div class="col-md-3">
                <label class="form-label small mb-1">{{ __('app.tracking.task_time_to') }}</label>
                <input type="datetime-local" name="to" class="form-control form-control-sm">
            </div>
            <div class="col-md-3">
                <button type="submit" class="btn btn-primary btn-sm w-100">{{ __('app.tracking.task_show') }}</button>
            </div>
        </form>

        {{-- Add task --}}
        <form id="tsForm" class="row g-2 mb-3 border-top pt-3">
            <div class="col-md-3">
                <select name="device_id" class="form-select form-select-sm" required>
                    @foreach($vehicles as $v)<option value="{{ $v['id'] }}">{{ $v['title'] ?? '#'.$v['id'] }}</option>@endforeach
                </select>
            </div>
            <div class="col-md-3"><input name="name" class="form-control form-control-sm" placeholder="{{ __('app.tracking.task_name') }}" maxlength="160" required></div>
            <div class="col-md-3"><input name="start" class="form-control form-control-sm" placeholder="{{ __('app.tracking.task_start') }}" maxlength="255"></div>
            <div class="col-md-3"><input name="destination" class="form-control form-control-sm" placeholder="{{ __('app.tracking.task_destination') }}" maxlength="255"></div>
            <div class="col-md-2">
                <select name="priority" class="form-select form-select-sm" title="{{ __('app.tracking.task_priority') }}">
                    @foreach($priorities as $p)<option value="{{ $p }}" @selected($p==='normal')>{{ __('app.tracking.priority_'.$p) }}</option>@endforeach
                </select>
            </div>
            <div class="col-md-2">
                <select name="status" class="form-select form-select-sm" title="{{ __('app.tracking.task_status') }}">
                    @foreach($statuses as $s)<option value="{{ $s }}">{{ __('app.tracking.task_status_'.$s) }}</option>@endforeach
                </select>
            </div>
            <div class="col-md-3"><input type="datetime-local" name="time_from" class="form-control form-control-sm" title="{{ __('app.tracking.task_time_from') }}"></div>
            <div class="col-md-3"><input type="datetime-local" name="time_to" class="form-control form-control-sm" title="{{ __('app.tracking.task_time_to') }}"></div>
            <div class="col-md-2"><button type="submit" class="btn btn-success btn-sm w-100">{{ __('app.tracking.task_add') }}</button></div>
        </form>

        <div class="table-responsive">
            <table class="table table-sm align-middle">
                <thead><tr>
                    <th>{{ __('app.tracking.task_time') }}</th>
                    <th>{{ __('app.tracking.task_name') }}</th>
                    <th>{{ __('app.tracking.task_object') }}</th>
                    <th>{{ __('app.tracking.task_start') }}</th>
                    <th>{{ __('app.tracking.task_destination') }}</th>
                    <th>{{ __('app.tracking.task_priority') }}</th>
                    <th>{{ __('app.tracking.task_status') }}</th>
                    <th></th>
                </tr></thead>
                <tbody id="tsBody"></tbody>
            </table>
        </div>
    </div>
</div>
@endsection
@push('scripts')
<script>window.TRACKING_TASKS_CONFIG = {
    jsonUrl: @json($jsonUrl),
    storeUrl: @json($storeUrl),
    destroyUrl: @json($baseUrl),
    destroyAllUrl: @json($destroyAllUrl),
    exportUrl: @json($exportUrl),
    i18n: {
        priority: {
            low: @json(__('app.tracking.priority_low')),
            normal: @json(__('app.tracking.priority_normal')),
            high: @json(__('app.tracking.priority_high')),
        },
        status: {
            new: @json(__('app.tracking.task_status_new')),
            in_progress: @json(__('app.tracking.task_status_in_progress')),
            done: @json(__('app.tracking.task_status_done')),
            cancelled: @json(__('app.tracking.task_status_cancelled')),
        },
        noRecords: @json(__('app.tracking.task_no_records')),
        confirmDelete: @json(__('app.tracking.task_confirm_delete')),
        confirmDeleteAll: @json(__('app.tracking.task_confirm_delete_all')),
        failed: @json(__('app.common.failed')),
    },
};</script>
<script src="{{ protected_js('tracking-tasks.js') }}"></script>
@endpush
