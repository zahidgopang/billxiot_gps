@extends($layout)

@section('title', __('app.tracking.live_title') . ' - ' . __('app.brand'))

@push('styles')
    <link rel="stylesheet" href="{{ asset('css/fleet-map.css') }}?v={{ filemtime(public_path('css/fleet-map.css')) }}">
    <style>
        @if($panel === 'user')
        body.gt-page-active { overflow: hidden; }
        body.gt-page-active .content-wrap {
            height: calc(100dvh - 96px);
            max-height: calc(100dvh - 96px);
            overflow: hidden;
            padding: 0 !important;
        }
        @else
        .content-wrap { padding: 0 !important; }
        .footer-premium { display: none; }
        @endif

        .tc-app {
            display: flex;
            flex-direction: column;
            height: @if($panel === 'user') 100% @else calc(100vh - 64px) @endif;
            min-height: 460px;
            background: #eef2f6;
        }

        /* ===== Top icon toolbar (Traccar style) ===== */
        .tc-iconbar {
            display: flex;
            align-items: center;
            gap: 0.15rem;
            flex-wrap: wrap;
            padding: 0.35rem 0.6rem;
            background: #fff;
            border-bottom: 1px solid #d9e1e8;
            box-shadow: 0 1px 3px rgba(15, 23, 42, 0.06);
            z-index: 6;
        }

        .tc-iconbar a {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            width: 38px;
            height: 38px;
            border-radius: 8px;
            color: #5b6b7b;
            text-decoration: none;
            font-size: 1.05rem;
            transition: background 0.15s ease, color 0.15s ease;
        }

        .tc-iconbar a:hover { background: #eef4fb; color: #1976d2; }
        .tc-iconbar a.active { background: #1976d2; color: #fff; }
        .tc-iconbar .tc-iconbar-sep { flex: 1; }

        .tc-main {
            flex: 1;
            display: flex;
            min-height: 0;
        }

        /* ===== Left panel ===== */
        .tc-panel {
            width: min(360px, 94vw);
            flex-shrink: 0;
            display: flex;
            flex-direction: column;
            background: #fff;
            border-inline-end: 1px solid #d9e1e8;
            z-index: 4;
        }

        .tc-tabs {
            display: flex;
            border-bottom: 1px solid #d9e1e8;
        }

        .tc-tab {
            flex: 1;
            padding: 0.6rem 0.4rem;
            text-align: center;
            font-size: 0.82rem;
            font-weight: 600;
            color: #5b6b7b;
            background: #f5f8fa;
            border: none;
            border-bottom: 2px solid transparent;
            cursor: pointer;
            white-space: nowrap;
        }

        .tc-tab:hover { color: #1976d2; }
        .tc-tab.active {
            color: #1976d2;
            background: #fff;
            border-bottom-color: #1976d2;
        }

        .tc-tab-body {
            flex: 1;
            min-height: 0;
            display: none;
            flex-direction: column;
        }

        .tc-tab-body.active { display: flex; }

        .tc-tab-head { padding: 0.6rem; border-bottom: 1px solid #eef0f3; }

        .tc-filter-chips {
            display: flex;
            flex-wrap: wrap;
            gap: 0.3rem;
            margin-top: 0.5rem;
        }

        .tc-chip {
            border: 1px solid #d9e1e8;
            background: #fff;
            color: #475569;
            border-radius: 999px;
            padding: 0.2rem 0.6rem;
            font-size: 0.72rem;
            font-weight: 600;
            cursor: pointer;
            display: inline-flex;
            align-items: center;
            gap: 0.3rem;
        }

        .tc-chip .tc-chip-count {
            background: #eef2f6;
            border-radius: 999px;
            padding: 0 0.35rem;
            font-size: 0.68rem;
            color: #64748b;
        }

        .tc-chip.active { background: #1976d2; border-color: #1976d2; color: #fff; }
        .tc-chip.active .tc-chip-count { background: rgba(255, 255, 255, 0.25); color: #fff; }

        .tc-list-head {
            display: flex;
            align-items: center;
            gap: 0.5rem;
            padding: 0.4rem 0.7rem;
            background: #f5f8fa;
            border-bottom: 1px solid #e2e8f0;
            font-size: 0.72rem;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.4px;
            color: #64748b;
        }

        .tc-list-head .tc-col { width: 16px; text-align: center; flex-shrink: 0; }
        .tc-list-head .tc-head-label { flex: 1; text-align: center; }

        .tc-list { flex: 1; overflow-y: auto; }

        .tc-row {
            display: flex;
            align-items: flex-start;
            gap: 0.5rem;
            padding: 0.55rem 0.7rem;
            border-bottom: 1px solid rgba(15, 23, 42, 0.05);
            cursor: pointer;
        }

        .tc-allrow { background: #fff; }
        .tc-allrow:hover { background: #fff; }

        .tc-row .tc-check { width: 16px; height: 16px; margin-top: 0.18rem; flex-shrink: 0; cursor: pointer; }
        .tc-check-spacer { width: 16px; flex-shrink: 0; }
        .tc-veh-icon { width: 20px; text-align: center; flex-shrink: 0; margin-top: 0.1rem; font-size: 1rem; line-height: 1.2; }

        .tc-row:hover { background: rgba(25, 118, 210, 0.05); }
        .tc-row.active { background: rgba(25, 118, 210, 0.1); }

        .tc-dot {
            width: 10px;
            height: 10px;
            border-radius: 50%;
            margin-top: 0.3rem;
            flex-shrink: 0;
        }

        .tc-row-info { flex: 1; min-width: 0; }
        .tc-row-titlewrap {
            display: flex;
            align-items: center;
            gap: 0.4rem;
            flex-wrap: wrap;
        }
        .tc-row-title { font-weight: 600; font-size: 0.86rem; color: #1e293b; }

        .tc-status-badge {
            display: inline-flex;
            align-items: center;
            gap: 0.32rem;
            font-size: 0.66rem;
            font-weight: 700;
            line-height: 1;
            white-space: nowrap;
            padding: 0.2rem 0.45rem;
            border-radius: 999px;
            color: var(--st, #64748b);
            background: color-mix(in srgb, var(--st, #64748b) 15%, #fff);
            border: 1px solid color-mix(in srgb, var(--st, #64748b) 35%, transparent);
        }
        .tc-status-badge::before {
            content: '';
            width: 7px;
            height: 7px;
            border-radius: 50%;
            background: var(--st, #64748b);
            flex-shrink: 0;
        }
        .tc-row-meta {
            font-size: 0.72rem;
            color: #64748b;
            margin-top: 0.1rem;
            line-height: 1.35;
            display: block;
        }

        .tc-row .tc-eye {
            color: #94a3b8;
            font-size: 0.95rem;
            margin-top: 0.2rem;
        }
        .tc-row .tc-eye.on { color: #1976d2; }

        .tc-hist-form { flex-shrink: 0; }

        .tc-hist-summary {
            flex-shrink: 0;
            padding: 0.55rem 0.75rem;
            background: #f0f7ff;
            border-bottom: 1px solid #dbeafe;
            font-size: 0.78rem;
            color: #334155;
        }
        .tc-hist-summary[hidden] { display: none !important; }
        .tc-hist-summary-row { display: flex; flex-wrap: wrap; gap: 0.5rem 1.1rem; }
        .tc-hist-summary-row span { white-space: nowrap; }
        .tc-hist-summary-row strong { color: #1e293b; }

        .tc-hist-results-wrap { flex: 1; min-height: 0; display: flex; flex-direction: column; }
        .tc-hist-results-wrap[hidden] { display: none !important; }
        .tc-stop-row { cursor: pointer; }
        .tc-stop-row:hover { background: rgba(37, 99, 235, 0.06); }

        .tc-pmark {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            width: 20px;
            height: 20px;
            border-radius: 4px;
            background: #2563eb;
            color: #fff;
            font-size: 0.7rem;
            font-weight: 700;
            flex-shrink: 0;
            margin-top: 0.15rem;
        }

        .tc-form { padding: 0.6rem; overflow-y: auto; }
        .tc-form label { font-size: 0.74rem; color: #64748b; margin-bottom: 0.1rem; display: block; }
        .tc-form .form-control,
        .tc-form .form-select { margin-bottom: 0.55rem; }
        .tc-form-actions { display: flex; gap: 0.4rem; }
        .tc-form-actions .btn { flex: 1; }

        /* Object select (Select2) sizing to match the small Bootstrap controls */
        .tc-form .select2-container { margin-bottom: 0.55rem; width: 100% !important; }
        .tc-form .select2-container--bootstrap-5 .select2-selection {
            min-height: calc(1.5em + 0.5rem + 2px);
            font-size: 0.875rem;
            padding: 0.05rem 0.5rem;
        }
        .tc-form .select2-container--bootstrap-5 .select2-selection--single .select2-selection__rendered {
            line-height: calc(1.5em + 0.5rem);
            padding: 0;
        }
        .tc-form .select2-container--bootstrap-5 .select2-selection--single .select2-selection__arrow {
            height: calc(1.5em + 0.5rem + 2px);
        }

        /* History date + time on one aligned row */
        .tc-datetime-row {
            display: flex;
            gap: 0.4rem;
            align-items: center;
            margin-bottom: 0.55rem;
        }
        .tc-datetime-row .tc-date-input { flex: 1 1 auto; min-width: 0; }
        .tc-datetime-row .tc-time-input { flex: 0 0 7.25rem; width: 7.25rem; }
        /* row already supplies bottom spacing; avoid double margin from inputs */
        .tc-datetime-row .form-control { margin-bottom: 0; }

        .tc-empty { padding: 1.2rem 0.8rem; text-align: center; color: #94a3b8; font-size: 0.82rem; }

        /* ===== Map ===== */
        .tc-map-wrap { position: relative; flex: 1; min-width: 0; display: flex; flex-direction: column; }
        .tc-map-area { position: relative; flex: 1; min-height: 0; }
        #tcMap { position: absolute; inset: 0; background: #dde6ee; }

        .tc-map-controls {
            position: absolute;
            top: 12px;
            inset-inline-end: 12px;
            display: flex;
            flex-direction: column;
            gap: 8px;
            z-index: 5;
        }

        .tc-map-controls .btn {
            width: 42px;
            height: 42px;
            padding: 0;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            box-shadow: 0 4px 14px rgba(15, 23, 42, 0.15);
        }
        .tc-map-controls .btn.active { background: #1976d2; color: #fff; }

        .tc-legend {
            position: absolute;
            bottom: 12px;
            inset-inline-start: 12px;
            background: rgba(255, 255, 255, 0.95);
            border-radius: 8px;
            padding: 0.5rem 0.7rem;
            box-shadow: 0 4px 14px rgba(15, 23, 42, 0.12);
            z-index: 5;
            font-size: 0.76rem;
        }
        .tc-legend:empty { display: none; }
        .tc-legend .tc-legend-item { display: flex; align-items: center; gap: 0.4rem; margin-bottom: 0.2rem; }
        .tc-legend .tc-legend-swatch { width: 14px; height: 14px; border-radius: 3px; }

        .tc-map-error {
            position: absolute;
            inset: 0;
            display: flex;
            align-items: center;
            justify-content: center;
            background: rgba(254, 242, 242, 0.94);
            z-index: 6;
        }
        .tc-map-error[hidden] { display: none !important; }

        /* Footer info panel (Data / Graph / Messages) */
        .tc-footer {
            height: 250px;
            flex-shrink: 0;
            display: flex;
            flex-direction: column;
            background: #fff;
            border-top: 1px solid #d9e1e8;
            box-shadow: 0 -2px 10px rgba(15, 23, 42, 0.08);
            z-index: 5;
        }
        .tc-footer[hidden] { display: none !important; }
        .tc-footer-head {
            display: flex;
            align-items: center;
            gap: 0.75rem;
            padding: 0 0.7rem;
            border-bottom: 1px solid #eef0f3;
            font-size: 0.82rem;
            font-weight: 600;
            color: #475569;
        }
        .tc-footer-tabs { display: flex; gap: 0.2rem; }
        .tc-ftab {
            border: none;
            background: none;
            padding: 0.55rem 0.7rem;
            font-size: 0.8rem;
            font-weight: 600;
            color: #64748b;
            border-bottom: 2px solid transparent;
            cursor: pointer;
        }
        .tc-ftab:hover { color: #1976d2; }
        .tc-ftab.active { color: #1976d2; border-bottom-color: #1976d2; }
        .tc-footer-title { flex: 1; font-weight: 700; color: #1e293b; text-align: center; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
        .tc-footer-body { flex: 1; min-height: 0; position: relative; }
        .tc-fbody { position: absolute; inset: 0; padding: 0.5rem 0.8rem; overflow: auto; display: none; }
        .tc-fbody.active { display: block; }
        /* Data tab scrolls horizontally (Traccar-style strip), never grows the footer height. */
        .tc-fbody[data-fbody="data"] { overflow-x: scroll; overflow-y: hidden; scrollbar-width: thin; scrollbar-color: #94a3b8 #eef0f3; }
        .tc-fbody[data-fbody="data"]::-webkit-scrollbar { height: 11px; }
        .tc-fbody[data-fbody="data"]::-webkit-scrollbar-track { background: #eef0f3; border-radius: 6px; }
        .tc-fbody[data-fbody="data"]::-webkit-scrollbar-thumb { background: #94a3b8; border-radius: 6px; border: 2px solid #eef0f3; }
        .tc-fbody[data-fbody="data"]::-webkit-scrollbar-thumb:hover { background: #64748b; }
        /* History mode: vertical stat grid instead of horizontal live-data strip */
        .tc-fbody[data-fbody="data"].tc-fbody--hist { overflow-x: hidden; overflow-y: auto; }
        .tc-hist-stats { display: grid; grid-template-columns: repeat(auto-fill, minmax(160px, 1fr)); gap: 0.55rem; }
        .tc-hist-stat {
            border: 1px solid #e6ebf0;
            border-radius: 8px;
            background: #fff;
            padding: 0.55rem 0.75rem;
        }
        .tc-hist-stat .tc-hist-stat-lbl { font-size: 0.68rem; text-transform: uppercase; letter-spacing: 0.35px; color: #94a3b8; font-weight: 700; margin-bottom: 0.15rem; }
        .tc-hist-stat .tc-hist-stat-val { font-size: 1.05rem; font-weight: 700; color: #1e293b; }
        .tc-fbody[data-fbody="messages"] .tc-msg-list { list-style: none; margin: 0; padding: 0; }
        .tc-fbody[data-fbody="messages"] .tc-msg-list li {
            display: flex; justify-content: space-between; gap: 0.75rem;
            padding: 0.35rem 0; border-bottom: 1px dashed #eef0f3; font-size: 0.82rem;
        }
        .tc-fbody[data-fbody="messages"] .tc-msg-list li[data-tc-locate] { cursor: pointer; }
        .tc-fbody[data-fbody="messages"] .tc-msg-list li[data-tc-locate]:hover { color: #1976d2; }
        .tc-fbody[data-fbody="messages"] .tc-msg-time { color: #94a3b8; white-space: nowrap; font-size: 0.76rem; }
        .tc-fbody[data-fbody="graph"] canvas,
        #tcSpeedChart { width: 100% !important; height: 100% !important; }

        /* Data tab: Traccar-style horizontally-scrollable widget cards */
        .tc-data-grid { display: flex; flex-wrap: nowrap; gap: 0.6rem; align-items: stretch; height: 100%; }
        .tc-data-col {
            box-sizing: border-box;
            flex: 0 0 250px;
            width: 250px;
            border: 1px solid #e6ebf0;
            border-radius: 8px;
            background: #fff;
            padding: 0.5rem 0.7rem;
            overflow-y: auto;
        }
        .tc-data-col h6 { font-size: 0.72rem; text-transform: uppercase; letter-spacing: 0.4px; color: #94a3b8; margin: 0 0 0.3rem; font-weight: 700; }
        .tc-kv { display: flex; align-items: center; justify-content: space-between; gap: 0.75rem; padding: 0.12rem 0; font-size: 0.8rem; border-bottom: 1px dashed #eef0f3; }
        .tc-kv .tc-kv-k { color: #64748b; display: inline-flex; align-items: center; gap: 0.4rem; }
        .tc-kv .tc-kv-v { color: #1e293b; font-weight: 600; text-align: end; }
        .tc-kv .tc-kv-v a { color: #1976d2; text-decoration: none; }
        .tc-mini-events { list-style: none; margin: 0; padding: 0; }
        .tc-mini-events li { font-size: 0.78rem; padding: 0.18rem 0; border-bottom: 1px dashed #eef0f3; display: flex; justify-content: space-between; gap: 0.75rem; }
        .tc-mini-events li[data-tc-locate] { cursor: pointer; }
        .tc-mini-events li[data-tc-locate]:hover { color: #1976d2; }
        .tc-mini-events .tc-me-time { color: #94a3b8; white-space: nowrap; }
        .tc-ctrl-row { display: flex; gap: 0.4rem; margin-top: 0.3rem; }
        .tc-ctrl-row .form-select, .tc-ctrl-row .form-control { font-size: 0.8rem; }

        /* Mileage bar chart (Traccar-style daily bars) */
        .tc-bars { display: flex; align-items: flex-end; gap: 0.45rem; height: 150px; padding-top: 0.4rem; }
        .tc-bar-col { flex: 1; display: flex; flex-direction: column; align-items: center; justify-content: flex-end; height: 100%; min-width: 0; }
        .tc-bar { width: 70%; min-height: 3px; border-radius: 3px 3px 0 0; }
        .tc-bar-val { font-size: 0.62rem; color: #475569; margin-bottom: 2px; }
        .tc-bar-lbl { font-size: 0.66rem; color: #94a3b8; margin-top: 3px; }

        /* Speedometer gauge */
        .tc-gauge { width: 100%; max-width: 190px; display: block; margin: 0.4rem auto 0; }
        .tc-gauge-val { font-size: 22px; font-weight: 700; fill: #1e293b; }
        .tc-gauge-unit { font-size: 9px; fill: #94a3b8; }

        /* Notes / Photo */
        .tc-notes { font-size: 0.82rem; color: #334155; white-space: pre-wrap; word-break: break-word; }
        .tc-photo { width: 100%; border-radius: 6px; display: block; }

        .tc-msg-table { width: 100%; font-size: 0.78rem; border-collapse: collapse; }
        .tc-msg-table th, .tc-msg-table td { padding: 0.2rem 0.5rem; border-bottom: 1px solid #eef0f3; text-align: start; white-space: nowrap; }
        .tc-msg-table th { color: #94a3b8; font-weight: 700; text-transform: uppercase; font-size: 0.68rem; }

        /* Module popup loader */
        .tc-module-loader {
            position: absolute; inset: 0; display: flex; align-items: center; justify-content: center;
            background: rgba(255, 255, 255, 0.9); z-index: 2;
        }
        .tc-module-loader[hidden] { display: none !important; }

        /* Stop info window */
        .tc-info { font-size: 0.8rem; min-width: 220px; }
        .tc-info-row { display: flex; justify-content: space-between; gap: 0.75rem; padding: 1px 0; }
        .tc-info-row span { color: #64748b; }
        .tc-info-row b { color: #1e293b; font-weight: 600; }

        /* Google Maps marker label (vehicle name + speed) — Traccar-style box */
        .tc-mk-label {
            background: rgba(255, 255, 255, 0.92);
            padding: 1px 6px;
            border-radius: 4px;
            border: 1px solid rgba(15, 23, 42, 0.18);
            box-shadow: 0 1px 3px rgba(15, 23, 42, 0.22);
            white-space: nowrap;
        }

        @media (max-width: 768px) {
            .tc-main { flex-direction: column; }
            .tc-panel { width: 100%; max-height: 44vh; border-inline-end: none; border-bottom: 1px solid #d9e1e8; }
        }
    </style>
@endpush

@section('content')
    @php
        $iconLinks = [
            ['key' => 'live', 'icon' => 'fa-location-arrow', 'route' => $hubRoutes['live'] ?? null, 'label' => __('app.tracking.live_link'), 'active' => true],
            ['key' => 'reports', 'icon' => 'fa-chart-bar', 'route' => $hubRoutes['reports'] ?? null, 'label' => __('app.tracking.reports_nav')],
            ['key' => 'geofences', 'icon' => 'fa-draw-polygon', 'route' => $hubRoutes['geofences'] ?? null, 'label' => __('app.tracking.geofences_nav')],
            ['key' => 'maintenance', 'icon' => 'fa-wrench', 'route' => $hubRoutes['maintenance'] ?? null, 'label' => __('app.tracking.maintenance_nav')],
            ['key' => 'drivers', 'icon' => 'fa-id-card', 'route' => $hubRoutes['drivers'] ?? null, 'label' => __('app.tracking.drivers_nav')],
            ['key' => 'commands', 'icon' => 'fa-terminal', 'route' => $hubRoutes['commands'] ?? null, 'label' => __('app.tracking.commands_nav')],
            ['key' => 'tasks', 'icon' => 'fa-tasks', 'route' => $hubRoutes['tasks'] ?? null, 'label' => __('app.tracking.tasks_nav')],
            ['key' => 'notifications', 'icon' => 'fa-bell', 'route' => $hubRoutes['notifications'] ?? null, 'label' => __('app.tracking.notifications_nav')],
            ['key' => 'settings', 'icon' => 'fa-sliders-h', 'route' => $hubRoutes['settings'] ?? null, 'label' => __('app.tracking.settings_nav')],
        ];
    @endphp
    <div class="tc-app">
        <div class="tc-iconbar">
            @foreach($iconLinks as $link)
                @if($link['route'] && Route::has($link['route']))
                    @if(!empty($link['active']))
                        <a href="{{ route($link['route']) }}" class="active"
                           title="{{ $link['label'] }}" aria-label="{{ $link['label'] }}">
                            <i class="fas {{ $link['icon'] }}"></i>
                        </a>
                    @else
                        <a href="{{ route($link['route']) }}"
                           data-tc-module="{{ route($link['route']) }}"
                           data-tc-module-title="{{ $link['label'] }}"
                           data-tc-module-icon="{{ $link['icon'] }}"
                           title="{{ $link['label'] }}" aria-label="{{ $link['label'] }}">
                            <i class="fas {{ $link['icon'] }}"></i>
                        </a>
                    @endif
                @endif
            @endforeach
            <span class="tc-iconbar-sep"></span>
        </div>

        <div class="tc-main">
            <aside class="tc-panel">
                <div class="tc-tabs" role="tablist">
                    <button type="button" class="tc-tab active" data-tab="objects">{{ __('app.tracking.tab_objects') }}</button>
                    <button type="button" class="tc-tab" data-tab="events">{{ __('app.tracking.events_nav') }}</button>
                    <button type="button" class="tc-tab" data-tab="places">{{ __('app.tracking.tab_places') }}</button>
                    <button type="button" class="tc-tab" data-tab="history">{{ __('app.tracking.history_link') }}</button>
                </div>

                {{-- Objects --}}
                <div class="tc-tab-body active" data-tab-body="objects">
                    <div class="tc-tab-head">
                        <input type="search" id="tcSearch" class="form-control form-control-sm"
                               placeholder="{{ __('app.tracking.search_vehicles') }}" autocomplete="off">
                        <div class="tc-filter-chips" id="tcChips">
                            <button type="button" class="tc-chip active" data-filter="all">{{ __('app.tracking.filter_all') }} <span class="tc-chip-count" data-count="all">0</span></button>
                            <button type="button" class="tc-chip" data-filter="moving">{{ __('app.tracking.filter_moving') }} <span class="tc-chip-count" data-count="moving">0</span></button>
                            <button type="button" class="tc-chip" data-filter="stopped">{{ __('app.tracking.filter_stopped') }} <span class="tc-chip-count" data-count="stopped">0</span></button>
                            <button type="button" class="tc-chip" data-filter="offline">{{ __('app.tracking.filter_offline') }} <span class="tc-chip-count" data-count="offline">0</span></button>
                        </div>
                    </div>
                    <div class="tc-list-head">
                        <span class="tc-col" title="{{ __('app.tracking.col_show') }}"><i class="fas fa-eye"></i></span>
                        <span class="tc-col" title="{{ __('app.tracking.col_follow') }}"><i class="fas fa-shoe-prints"></i></span>
                        <span class="tc-head-label">{{ __('app.tracking.object') }}</span>
                    </div>
                    <div class="tc-row tc-allrow">
                        <input type="checkbox" id="tcCheckAll" class="form-check-input tc-check" checked title="{{ __('app.tracking.col_show') }}">
                        <span class="tc-check-spacer"></span>
                        <span class="tc-row-info"><span class="tc-row-title">{{ __('app.tracking.filter_all') }} <span id="tcAllCount" class="text-muted"></span></span></span>
                    </div>
                    <div class="tc-list" id="tcVehicleList"></div>
                </div>

                {{-- Events --}}
                <div class="tc-tab-body" data-tab-body="events">
                    <div class="tc-tab-head">
                        <button type="button" class="btn btn-sm btn-outline-primary w-100" id="tcEventsReload">
                            <i class="fas fa-sync-alt me-1"></i>{{ __('app.tracking.refresh') }}
                        </button>
                    </div>
                    <div class="tc-list" id="tcEventsList">
                        <div class="tc-empty">{{ __('app.tracking.refresh') }}…</div>
                    </div>
                </div>

                {{-- Places (geofences) --}}
                <div class="tc-tab-body" data-tab-body="places">
                    <div class="tc-tab-head">
                        <button type="button" class="btn btn-sm btn-outline-primary w-100" id="tcPlacesReload">
                            <i class="fas fa-sync-alt me-1"></i>{{ __('app.tracking.refresh') }}
                        </button>
                    </div>
                    <div class="tc-list" id="tcPlacesList">
                        <div class="tc-empty">{{ __('app.tracking.refresh') }}…</div>
                    </div>
                </div>

                {{-- History --}}
                <div class="tc-tab-body" data-tab-body="history">
                    <div class="tc-form tc-hist-form">
                        <label for="tcHistVehicle">{{ __('app.tracking.object') }}</label>
                        <select id="tcHistVehicle" class="form-select form-select-sm no-select2">
                            @foreach($vehicles as $vehicle)
                                <option value="{{ $vehicle['id'] }}">{{ $vehicle['title'] ?? ('#' . $vehicle['id']) }}</option>
                            @endforeach
                        </select>

                        @php($tcToday = now()->format('Y-m-d'))
                        <label for="tcHistDateFrom">{{ __('app.tracking.date_from') }}</label>
                        <div class="tc-datetime-row">
                            <input type="date" id="tcHistDateFrom" class="form-control form-control-sm admin-ltr tc-date-input" dir="ltr" value="{{ $tcToday }}">
                            <input type="time" id="tcHistTimeFrom" class="form-control form-control-sm admin-ltr tc-time-input" dir="ltr" value="00:00">
                        </div>

                        <label for="tcHistDateTo">{{ __('app.tracking.date_to') }}</label>
                        <div class="tc-datetime-row">
                            <input type="date" id="tcHistDateTo" class="form-control form-control-sm admin-ltr tc-date-input" dir="ltr" value="{{ $tcToday }}">
                            <input type="time" id="tcHistTimeTo" class="form-control form-control-sm admin-ltr tc-time-input" dir="ltr" value="23:59">
                        </div>

                        <div class="tc-form-actions mt-2">
                            <button type="button" class="btn btn-primary btn-sm" id="tcHistShow">
                                <i class="fas fa-play me-1"></i>{{ __('app.tracking.show') }}
                            </button>
                            <button type="button" class="btn btn-outline-secondary btn-sm" id="tcHistHide">
                                <i class="fas fa-eraser me-1"></i>{{ __('app.tracking.hide') }}
                            </button>
                        </div>
                    </div>
                    <div id="tcHistSummary" class="tc-hist-summary" hidden></div>
                    <div id="tcHistResultsWrap" class="tc-hist-results-wrap" hidden>
                        <div class="tc-list-head">
                            <span class="tc-pmark" style="width:16px;height:16px;font-size:0.58rem;margin-top:0">P</span>
                            <span class="tc-head-label">{{ __('app.tracking.parking_stops') }}</span>
                        </div>
                        <div class="tc-list" id="tcHistResults"></div>
                    </div>
                </div>
            </aside>

            <div class="tc-map-wrap">
                <div class="tc-map-area">
                    <div id="tcMap" aria-label="{{ __('app.tracking.live_map_aria') }}"></div>
                    <div id="tcLegend" class="tc-legend"></div>
                    <div id="tcMapError" class="tc-map-error" hidden>
                        <div class="alert alert-danger mb-0">
                            <span data-tc-error-text>{{ __('app.map.loading_map_failed') }}</span>
                        </div>
                    </div>
                    <div class="tc-map-controls">
                        <button type="button" class="btn btn-light" id="tcFit" title="{{ __('app.tracking.fit_all') }}">
                            <i class="fas fa-compress-arrows-alt"></i>
                        </button>
                        <button type="button" class="btn btn-light" id="tcFollow" title="{{ __('app.tracking.follow_vehicle') }}" aria-pressed="false">
                            <i class="fas fa-crosshairs"></i>
                        </button>
                        <button type="button" class="btn btn-light" id="tcRefresh" title="{{ __('app.tracking.refresh') }}">
                            <i class="fas fa-sync-alt"></i>
                        </button>
                    </div>
                </div>

                <div class="tc-footer" id="tcFooter" hidden>
                    <div class="tc-footer-head">
                        <div class="tc-footer-tabs">
                            <button type="button" class="tc-ftab active" data-ftab="data">{{ __('app.tracking.ft_data') }}</button>
                            <button type="button" class="tc-ftab" data-ftab="graph">{{ __('app.tracking.ft_graph') }}</button>
                            <button type="button" class="tc-ftab" data-ftab="messages">{{ __('app.tracking.ft_messages') }}</button>
                        </div>
                        <span class="tc-footer-title" id="tcFooterTitle"></span>
                        <button type="button" class="btn btn-sm btn-link text-muted p-0" id="tcFooterClose" aria-label="{{ __('app.tracking.hide') }}">
                            <i class="fas fa-times"></i>
                        </button>
                    </div>
                    <div class="tc-footer-body">
                        <div class="tc-fbody active" data-fbody="data" id="tcFooterData"></div>
                        <div class="tc-fbody" data-fbody="graph"><canvas id="tcSpeedChart"></canvas></div>
                        <div class="tc-fbody" data-fbody="messages" id="tcFooterMessages"></div>
                    </div>
                </div>
            </div>
        </div>

        {{-- Module popup (Traccar-style): loads a module page in an iframe, saves without refreshing this page --}}
        <div class="modal fade" id="tcModuleModal" tabindex="-1" aria-hidden="true">
            <div class="modal-dialog modal-xl modal-dialog-scrollable" style="max-width:96vw;">
                <div class="modal-content" style="height:90vh;">
                    <div class="modal-header py-2">
                        <h6 class="modal-title mb-0" id="tcModuleTitle"><i class="fas fa-satellite-dish me-2"></i></h6>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="{{ __('app.tracking.hide') }}"></button>
                    </div>
                    <div class="modal-body p-0" style="position:relative;overflow:hidden;">
                        <div id="tcModuleLoader" class="tc-module-loader"><span class="spinner-border text-primary"></span></div>
                        <iframe id="tcModuleFrame" title="" style="border:0;width:100%;height:100%;display:block;"></iframe>
                    </div>
                </div>
            </div>
        </div>
    </div>
@endsection

@push('scripts')
    @if($panel !== 'user')
        <script src="https://js.pusher.com/8.2/pusher.min.js"></script>
        <script src="https://cdnjs.cloudflare.com/ajax/libs/laravel-echo/1.15.0/echo.iife.js"></script>
        @include('user._pusher')
    @endif
    <script>document.body.classList.add('gt-page-active');</script>
    <script>
        window.TRACCAR_UI_CONFIG = {
            panel: @json($panel),
            googleMapsKey: @json(config('services.google.maps_key')),
            liveJsonUrl: @json(route($routes['liveJson'])),
            historyJsonUrl: @json(route($routes['historyJson'])),
            eventsJsonUrl: @json(Route::has($routes['eventsJson']) ? route($routes['eventsJson']) : null),
            geofencesJsonUrl: @json(Route::has($routes['geofencesJson']) ? route($routes['geofencesJson']) : null),
            devicePanelUrl: @json(Route::has($routes['devicePanel']) ? route($routes['devicePanel']) : null),
            deviceMileageUrl: @json(Route::has($routes['deviceMileage']) ? route($routes['deviceMileage']) : null),
            commandsSendUrl: @json(Route::has($routes['commandsSend']) ? route($routes['commandsSend']) : null),
            commandTypes: @json(\App\Services\Tracking\CommandService::typeLabels()),
            csrfToken: @json(csrf_token()),
            pollIntervalMs: 2000,
            animDurationMs: 1200,
            stateColors: @json($stateColors),
            appTimezone: @json(config('app.timezone')),
            vehicles: @json($vehicles),
            startIconUrl: @json(asset('images/map/marker-start.svg')),
            endIconUrl: @json(asset('images/map/marker-end.svg')),
            i18n: {
                noVehicles: @json(__('app.tracking.no_vehicles')),
                selectVehicle: @json(__('app.tracking.select_vehicle')),
                loadFailed: @json(__('app.tracking.load_failed')),
                loadingMapFailed: @json(__('app.map.loading_map_failed')),
                mapApiKeyMissing: @json(__('app.map.map_api_key_missing')),
                kmhUnit: @json(__('app.map.kmh_unit')),
                ignitionOn: @json(__('app.map.ignition_on')),
                ignitionOff: @json(__('app.map.ignition_off')),
                routeStart: @json(__('app.map.route_start')),
                routeEnd: @json(__('app.map.route_end')),
                noData: @json(__('app.tracking.no_data')),
                noStops: @json(__('app.tracking.no_stops')),
                parkingStops: @json(__('app.tracking.parking_stops')),
                lblObject: @json(__('app.tracking.lbl_object')),
                lblPosition: @json(__('app.tracking.lbl_position')),
                lblAngle: @json(__('app.tracking.lbl_angle')),
                lblArrived: @json(__('app.tracking.lbl_arrived')),
                lblDeparted: @json(__('app.tracking.lbl_departed')),
                lblDuration: @json(__('app.tracking.lbl_duration')),
                colShow: @json(__('app.tracking.col_show')),
                colFollow: @json(__('app.tracking.col_follow')),
                ftData: @json(__('app.tracking.ft_data')),
                ftGraph: @json(__('app.tracking.ft_graph')),
                ftMessages: @json(__('app.tracking.ft_messages')),
                lblPlate: @json(__('app.tracking.lbl_plate')),
                lblStatus: @json(__('app.tracking.lbl_status')),
                lblSpeed: @json(__('app.tracking.lbl_speed')),
                lblAltitude: @json(__('app.tracking.lbl_altitude')),
                lblOdometer: @json(__('app.tracking.lbl_odometer')),
                lblTimePosition: @json(__('app.tracking.lbl_time_position')),
                lblTimeServer: @json(__('app.tracking.lbl_time_server')),
                lblIgnition: @json(__('app.tracking.lbl_ignition')),
                secObjectControl: @json(__('app.tracking.sec_object_control')),
                secDailyStats: @json(__('app.tracking.sec_daily_stats')),
                secRecentEvents: @json(__('app.tracking.sec_recent_events')),
                statRouteLength: @json(__('app.tracking.stat_route_length')),
                statMoveDuration: @json(__('app.tracking.stat_move_duration')),
                statStopDuration: @json(__('app.tracking.stat_stop_duration')),
                statTopSpeed: @json(__('app.tracking.stat_top_speed')),
                statAvgSpeed: @json(__('app.tracking.stat_avg_speed')),
                cmdSend: @json(__('app.tracking.cmd_send')),
                cmdSent: @json(__('app.tracking.cmd_sent')),
                colTime: @json(__('app.tracking.col_time')),
                noEvents: @json(__('app.tracking.no_events')),
                secRecentTasks: @json(__('app.tracking.sec_recent_tasks')),
                secFuel: @json(__('app.tracking.sec_fuel')),
                secMileage: @json(__('app.tracking.sec_mileage')),
                secSpeedometer: @json(__('app.tracking.sec_speedometer')),
                secNotes: @json(__('app.tracking.sec_notes')),
                secPhoto: @json(__('app.tracking.sec_photo')),
                lblFuel: @json(__('app.tracking.lbl_fuel')),
                lblBattery: @json(__('app.tracking.lbl_battery')),
                noTasks: @json(__('app.tracking.no_tasks')),
                noNotes: @json(__('app.tracking.no_notes')),
                noPhoto: @json(__('app.tracking.no_photo')),
                noDataFound: @json(__('app.tracking.no_data_found')),
            },
        };
    </script>
    <script src="{{ protected_js('app-datetime.js') }}"></script>
    <script src="{{ protected_js('vehicle-marker.js') }}"></script>
    <script src="{{ protected_js('tracking-traccar.js') }}"></script>
@endpush
