@extends($layout)
@section('title', __('app.tracking.notifications_title') . ' - ' . __('app.brand'))
@push('styles')
@include('tracking.partials.module-styles')
<style>
    .gt-notif-table th, .gt-notif-table td { vertical-align:middle; }
    .gt-notif-table th.gt-notif-ch { width:120px; text-align:center; }
    .gt-notif-table td.gt-notif-ch { text-align:center; }
    .gt-notif-ch-head { display:flex; flex-direction:column; align-items:center; gap:.2rem; }
    .gt-notif-ch-head small { font-size:.66rem; color:#94a3b8; font-weight:400; cursor:pointer; text-decoration:underline; }
    .gt-notif-empty { text-align:center; color:#94a3b8; padding:1.25rem 0; }
    .gt-notif-foot { display:flex; align-items:center; gap:.75rem; margin-top:.75rem; }
</style>
@endpush
@section('content')
<div class="gt-module-page">
    @include('tracking.partials.hub-nav')
    <div class="gt-module-card">
        <h5 class="mb-1">{{ __('app.tracking.notifications_title') }}</h5>
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
@endsection
@push('scripts')
<script>window.TRACKING_NOTIFICATIONS_CONFIG = {
    jsonUrl: @json($jsonUrl),
    updateUrl: @json($updateUrl),
    i18n: {
        web: @json(__('app.tracking.channel_web')),
        push: @json(__('app.tracking.channel_push')),
        saved: @json(__('app.tracking.notif_saved')),
        failed: @json(__('app.common.failed')),
        noTypes: @json(__('app.tracking.notif_none')),
    },
};</script>
<script src="{{ protected_js('tracking-notifications.js') }}"></script>
@endpush
