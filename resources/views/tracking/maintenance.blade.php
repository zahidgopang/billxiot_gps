@extends($layout)
@section('title', __('app.tracking.maintenance_title') . ' - ' . __('app.brand'))
@push('styles')
@include('tracking.partials.module-styles')
<style>
    .gt-maint-toolbar { display:flex; justify-content:space-between; align-items:center; gap:.5rem; margin-bottom:1rem; flex-wrap:wrap; }
    .gt-modal { position:fixed; inset:0; background:rgba(15,23,42,.45); display:none; align-items:flex-start; justify-content:center; z-index:1080; padding:2rem 1rem; overflow:auto; }
    .gt-modal.open { display:flex; }
    .gt-modal__dialog { background:#fff; width:100%; max-width:760px; border-radius:8px; box-shadow:0 18px 48px rgba(0,0,0,.25); }
    .gt-modal__head { display:flex; align-items:center; justify-content:space-between; background:#1976d2; color:#fff; padding:.65rem 1rem; border-radius:8px 8px 0 0; }
    .gt-modal__head h6 { margin:0; font-size:.95rem; display:flex; align-items:center; gap:.5rem; }
    .gt-modal__close { background:none; border:0; color:#fff; font-size:1.1rem; line-height:1; cursor:pointer; }
    .gt-modal__body { padding:1rem 1.25rem; }
    .gt-modal__foot { display:flex; justify-content:center; gap:.5rem; padding:.75rem 1rem 1.1rem; }
    .gt-sec-title { color:#1976d2; font-weight:600; font-size:.8rem; text-transform:uppercase; letter-spacing:.03em; margin:.25rem 0 .65rem; }
    .gt-field-row { display:grid; grid-template-columns: 180px 1fr 150px 1fr; gap:.5rem .75rem; align-items:center; margin-bottom:.5rem; }
    .gt-field-row--full { grid-template-columns: 180px 1fr; align-items:start; }
    .gt-field-row label { font-size:.85rem; margin:0; }
    .gt-check { display:flex; align-items:center; gap:.45rem; font-size:.85rem; }
    .gt-maint-status { font-size:.72rem; }
    .gt-maint-expired { color:#c62828; font-weight:600; }
    .gt-maint-left { color:#2e7d32; }
    /* Select2 multi-select fits the modal width and renders above the overlay */
    .gt-obj-select { width:100%; }
    .gt-field-row .select2-container { width:100% !important; }
    #gtMaintModal .select2-container--open { z-index:1090; }
    @media (max-width: 640px){ .gt-field-row, .gt-field-row--full { grid-template-columns: 1fr; } }
</style>
@endpush
@section('content')
<div class="gt-module-page">
    @include('tracking.partials.hub-nav')
    <div class="gt-module-card">
        <div class="gt-maint-toolbar">
            <h5 class="mb-0">{{ __('app.tracking.maintenance_title') }}</h5>
            <button type="button" id="gtMaintAdd" class="btn btn-primary btn-sm">
                <i class="fas fa-plus me-1"></i>{{ __('app.tracking.maint_add_service') }}
            </button>
        </div>
        <div class="table-responsive">
            <table class="table table-sm align-middle">
                <thead><tr>
                    <th>{{ __('app.tracking.maint_objects') }}</th>
                    <th>{{ __('app.common.name') }}</th>
                    <th>{{ __('app.tracking.maint_current_odo') }}</th>
                    <th>{{ __('app.tracking.maint_odo_left') }}</th>
                    <th>{{ __('app.tracking.maint_hours_left_col') }}</th>
                    <th>{{ __('app.tracking.maint_days_left_col') }}</th>
                    <th>{{ __('app.common.status') }}</th>
                    <th></th>
                </tr></thead>
                <tbody id="gtMaintBody"></tbody>
            </table>
        </div>
    </div>
</div>

{{-- Service properties modal --}}
<div class="gt-modal" id="gtMaintModal" aria-hidden="true">
    <div class="gt-modal__dialog">
        <div class="gt-modal__head">
            <h6><i class="fas fa-wrench"></i> {{ __('app.tracking.maint_service_props') }}</h6>
            <button type="button" class="gt-modal__close" data-maint-close>&times;</button>
        </div>
        <form id="gtMaintForm">
            <div class="gt-modal__body">
                <input type="hidden" name="maintenance_id" id="gtMaintId">

                <div class="gt-sec-title">{{ __('app.tracking.maint_sec_service') }}</div>

                <div class="gt-field-row">
                    <label for="gtMaintName">{{ __('app.common.name') }}</label>
                    <input type="text" id="gtMaintName" name="name" class="form-control form-control-sm" maxlength="160" required>
                    <span></span><span></span>
                </div>

                <div class="gt-field-row gt-field-row--full">
                    <label for="gtMaintObjects">{{ __('app.tracking.maint_objects') }}</label>
                    <select id="gtMaintObjects" class="form-select form-select-sm gt-obj-select" multiple>
                        @foreach($vehicles as $v)
                            <option value="{{ $v['id'] }}">{{ $v['title'] ?? '#'.$v['id'] }}</option>
                        @endforeach
                    </select>
                </div>

                <div class="gt-field-row">
                    <label class="gt-check"><input type="checkbox" name="data_list" value="1" checked> {{ __('app.tracking.maint_data_list') }}</label>
                    <span></span><span></span><span></span>
                </div>
                <div class="gt-field-row">
                    <label class="gt-check"><input type="checkbox" name="popup" value="1" checked> {{ __('app.tracking.maint_popup') }}</label>
                    <span></span><span></span><span></span>
                </div>

                <div class="gt-field-row">
                    <label class="gt-check"><input type="checkbox" name="odometer_enabled" value="1" data-toggle-target="odometer_interval"> {{ __('app.tracking.maint_odo_interval') }}</label>
                    <input type="number" step="any" min="0" name="odometer_interval" class="form-control form-control-sm" disabled>
                    <label>{{ __('app.tracking.maint_last_km') }}</label>
                    <input type="number" step="any" min="0" name="odometer_last" class="form-control form-control-sm">
                </div>
                <div class="gt-field-row">
                    <label class="gt-check"><input type="checkbox" name="hours_enabled" value="1" data-toggle-target="hours_interval"> {{ __('app.tracking.maint_hours_interval') }}</label>
                    <input type="number" step="any" min="0" name="hours_interval" class="form-control form-control-sm" disabled>
                    <label>{{ __('app.tracking.maint_last_h') }}</label>
                    <input type="number" step="any" min="0" name="hours_last" class="form-control form-control-sm">
                </div>
                <div class="gt-field-row">
                    <label class="gt-check"><input type="checkbox" name="days_enabled" value="1" data-toggle-target="days_interval"> {{ __('app.tracking.maint_days_interval') }}</label>
                    <input type="number" step="1" min="0" name="days_interval" class="form-control form-control-sm" disabled>
                    <label>{{ __('app.tracking.maint_last_service') }}</label>
                    <input type="date" name="days_last" class="form-control form-control-sm">
                </div>

                <div class="gt-sec-title mt-3">{{ __('app.tracking.maint_sec_trigger') }}</div>
                <div class="gt-field-row">
                    <label class="gt-check"><input type="checkbox" name="trigger_odometer" value="1"> {{ __('app.tracking.maint_trig_odo') }}</label>
                    <span></span>
                    <label class="gt-check"><input type="checkbox" name="update_last_service" value="1" checked> {{ __('app.tracking.maint_update_last') }}</label>
                    <span></span>
                </div>
                <div class="gt-field-row">
                    <label class="gt-check"><input type="checkbox" name="trigger_hours" value="1"> {{ __('app.tracking.maint_trig_hours') }}</label>
                    <span></span><span></span><span></span>
                </div>
                <div class="gt-field-row">
                    <label class="gt-check"><input type="checkbox" name="trigger_days" value="1"> {{ __('app.tracking.maint_trig_days') }}</label>
                    <span></span><span></span><span></span>
                </div>
            </div>
            <div class="gt-modal__foot">
                <button type="submit" class="btn btn-primary btn-sm"><i class="fas fa-save me-1"></i>{{ __('app.common.save') }}</button>
                <button type="button" class="btn btn-light btn-sm" data-maint-close><i class="fas fa-times me-1"></i>{{ __('app.common.cancel') }}</button>
            </div>
        </form>
    </div>
</div>
@endsection
@push('scripts')
<script>window.TRACKING_MAINTENANCE_CONFIG = {
    jsonUrl: @json($jsonUrl),
    storeUrl: @json($storeUrl),
    baseUrl: @json($baseUrl),
    i18n: {
        status: {
            ok: @json(__('app.tracking.maint_status_ok')),
            soon: @json(__('app.tracking.maint_status_soon')),
            overdue: @json(__('app.tracking.maint_status_overdue')),
        },
        noRecords: @json(__('app.tracking.task_no_records')),
        confirmDelete: @json(__('app.tracking.maint_confirm_delete')),
        confirmComplete: @json(__('app.tracking.maint_confirm_complete')),
        completeTitle: @json(__('app.tracking.maint_complete')),
        completed: @json(__('app.tracking.maint_completed')),
        selectObject: @json(__('app.tracking.maint_select_object')),
        selectTrigger: @json(__('app.tracking.maint_select_trigger')),
        failed: @json(__('app.common.failed')),
        na: '—',
    },
};</script>
<script src="{{ protected_js('tracking-maintenance.js') }}"></script>
@endpush
