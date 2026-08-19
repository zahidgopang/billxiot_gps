@extends($layout)
@section('title', __('app.tracking.notifications_inbox_title') . ' - ' . __('app.brand'))
@push('styles')
@include('tracking.partials.module-styles')
<style>
    .gt-notif-tabs { display:flex; gap:.35rem; margin-bottom:1rem; flex-wrap:wrap; }
    .gt-notif-tabs .btn.is-active { background: var(--apple-blue, #0071e3); color:#fff; border-color: transparent; }
    .gt-notif-panel[hidden] { display:none !important; }
    .gt-notif-toolbar { display:flex; flex-wrap:wrap; gap:.5rem; align-items:center; margin-bottom:.75rem; }
    .gt-notif-toolbar .form-control, .gt-notif-toolbar .form-select { max-width:11rem; }
    .gt-notif-list { list-style:none; margin:0; padding:0; }
    .gt-notif-item {
        display:flex; gap:.75rem; align-items:flex-start;
        padding:.75rem .65rem; border-bottom:1px solid rgba(0,0,0,.06);
        cursor:pointer;
    }
    .gt-notif-item:hover { background: rgba(0,0,0,.03); }
    .gt-notif-item.is-unread { background: rgba(0,113,227,.06); }
    .gt-notif-item.is-unread .gt-notif-title { font-weight:700; }
    .gt-notif-dot {
        width:.55rem; height:.55rem; border-radius:50%; margin-top:.45rem; flex-shrink:0;
        background: transparent;
    }
    .gt-notif-item.is-unread .gt-notif-dot { background: var(--apple-blue, #0071e3); }
    .gt-notif-title { font-size:.9rem; margin:0 0 .15rem; }
    .gt-notif-meta { font-size:.75rem; color:#64748b; }
    .gt-notif-msg { font-size:.82rem; color:#334155; margin:.2rem 0 0; }
    .gt-notif-empty { text-align:center; color:#94a3b8; padding:2rem 0; }
    .gt-notif-pager { display:flex; align-items:center; gap:.5rem; margin-top:.75rem; flex-wrap:wrap; }
    .gt-notif-table th, .gt-notif-table td { vertical-align:middle; }
    .gt-notif-table th.gt-notif-ch { width:96px; text-align:center; font-size:.78rem; }
    .gt-notif-table td.gt-notif-ch { text-align:center; }
    .gt-notif-ch-head { display:flex; flex-direction:column; align-items:center; gap:.2rem; }
    .gt-notif-ch-head small { font-size:.66rem; color:#94a3b8; font-weight:400; cursor:pointer; text-decoration:underline; }
    .gt-notif-foot { display:flex; align-items:center; gap:.75rem; margin-top:.75rem; }
</style>
@endpush
@section('content')
<div class="gt-module-page">
    @include('tracking.partials.hub-nav')
    <div class="gt-module-card">
        <h5 class="mb-1">{{ __('app.tracking.notifications_inbox_title') }}</h5>
        <p class="small text-muted mb-3">{{ __('app.tracking.notifications_inbox_hint') }}</p>

        <div class="gt-notif-tabs" role="tablist">
            <button type="button" class="btn btn-outline-secondary btn-sm is-active" data-gt-notif-tab="inbox" role="tab" aria-selected="true">
                {{ __('app.tracking.notif_tab_inbox') }}
                <span class="badge text-bg-primary ms-1" id="gtNotifUnreadBadge" hidden>0</span>
            </button>
            <button type="button" class="btn btn-outline-secondary btn-sm" data-gt-notif-tab="settings" role="tab" aria-selected="false">
                {{ __('app.tracking.notif_tab_settings') }}
            </button>
        </div>

        <div class="gt-notif-panel" id="gtNotifInboxPanel" role="tabpanel">
            <div class="gt-notif-toolbar">
                <input type="date" id="gtNotifFrom" class="form-control form-control-sm admin-ltr" dir="ltr">
                <input type="date" id="gtNotifTo" class="form-control form-control-sm admin-ltr" dir="ltr">
                <select id="gtNotifFilter" class="form-select form-select-sm">
                    <option value="all">{{ __('app.tracking.notif_filter_all') }}</option>
                    <option value="unread">{{ __('app.tracking.notif_filter_unread') }}</option>
                </select>
                <button type="button" class="btn btn-primary btn-sm" id="gtNotifReload">{{ __('app.common.filter') }}</button>
                <button type="button" class="btn btn-outline-secondary btn-sm" id="gtNotifMarkAll">{{ __('app.tracking.notif_mark_all_read') }}</button>
            </div>
            <ul class="gt-notif-list" id="gtNotifList"></ul>
            <div class="gt-notif-empty" id="gtNotifEmpty" hidden>{{ __('app.tracking.notif_inbox_empty') }}</div>
            <div class="gt-notif-pager" id="gtNotifPager" hidden>
                <button type="button" class="btn btn-outline-secondary btn-sm" id="gtNotifPrev">&lsaquo;</button>
                <span class="small text-muted" id="gtNotifPageLabel"></span>
                <button type="button" class="btn btn-outline-secondary btn-sm" id="gtNotifNext">&rsaquo;</button>
            </div>
        </div>

        <div class="gt-notif-panel" id="gtNotifSettingsPanel" role="tabpanel" hidden>
            <p class="small text-muted">{{ __('app.tracking.notifications_hint') }}</p>
            <form id="gtNotifForm">
                <div class="table-responsive">
                    <table class="table table-sm gt-notif-table">
                        <thead><tr>
                            <th>{{ __('app.common.type') }}</th>
                            <th class="gt-notif-ch">
                                <span class="gt-notif-ch-head">
                                    {{ __('app.tracking.channel_web') }}
                                    <small data-toggle-col="web">{{ __('app.tracking.notif_toggle_all') }}</small>
                                </span>
                            </th>
                            <th class="gt-notif-ch">
                                <span class="gt-notif-ch-head">
                                    {{ __('app.tracking.channel_push') }}
                                    <small data-toggle-col="push">{{ __('app.tracking.notif_toggle_all') }}</small>
                                </span>
                            </th>
                            <th class="gt-notif-ch">
                                <span class="gt-notif-ch-head">
                                    {{ __('app.tracking.channel_email') }}
                                    <small data-toggle-col="email">{{ __('app.tracking.notif_toggle_all') }}</small>
                                </span>
                            </th>
                            <th class="gt-notif-ch">
                                <span class="gt-notif-ch-head">
                                    {{ __('app.tracking.channel_whatsapp') }}
                                    <small data-toggle-col="whatsapp">{{ __('app.tracking.notif_toggle_all') }}</small>
                                </span>
                            </th>
                        </tr></thead>
                        <tbody id="gtNotifBody"></tbody>
                    </table>
                </div>
                <div class="gt-notif-foot">
                    <button type="submit" class="btn btn-primary btn-sm"><i class="fas fa-save me-1"></i>{{ __('app.common.save') }}</button>
                </div>
            </form>
        </div>
    </div>
</div>
@endsection
@push('scripts')
<script>window.TRACKING_NOTIFICATIONS_CONFIG = {
    jsonUrl: @json($jsonUrl),
    updateUrl: @json($updateUrl),
    inboxUrl: @json($inboxUrl),
    markReadUrl: @json($markReadUrl),
    i18n: {
        web: @json(__('app.tracking.channel_web')),
        push: @json(__('app.tracking.channel_push')),
        email: @json(__('app.tracking.channel_email')),
        whatsapp: @json(__('app.tracking.channel_whatsapp')),
        saved: @json(__('app.tracking.notif_saved')),
        failed: @json(__('app.common.failed')),
        noTypes: @json(__('app.tracking.notif_none')),
        empty: @json(__('app.tracking.notif_inbox_empty')),
        loadFailed: @json(__('app.tracking.notif_inbox_load_failed')),
        markedRead: @json(__('app.tracking.notif_marked_read')),
        pageOf: @json(__('app.tracking.report_page_of')),
    },
};</script>
<script src="{{ protected_js('tracking-notifications.js') }}"></script>
@endpush
