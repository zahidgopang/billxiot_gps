@extends($layout)

@section('title', __('app.tracking.live_title') . ' - ' . __('app.brand'))

@push('styles')
    <link rel="stylesheet" href="{{ asset('css/fleet-map.css') }}?v={{ filemtime(public_path('css/fleet-map.css')) }}">
    <style>
        /* ===== Design tokens (single source of truth; dark-mode-ready) =====
           Defined on :root so they resolve everywhere, including menus/info-windows
           that get appended outside the .tc-app subtree. */
        :root {
            --tc-radius-sm: 7px;
            --tc-radius: 11px;
            --tc-radius-lg: 16px;
            /* Soft, layered shadows for a premium depth */
            --tc-shadow-sm: 0 1px 2px rgba(16, 24, 40, 0.06), 0 1px 3px rgba(16, 24, 40, 0.05);
            --tc-shadow: 0 8px 20px -10px rgba(16, 24, 40, 0.22), 0 2px 6px -2px rgba(16, 24, 40, 0.10);
            --tc-shadow-lg: 0 24px 48px -16px rgba(16, 24, 40, 0.30), 0 8px 20px -12px rgba(16, 24, 40, 0.16);
            /* Neutrals — refined cool slate */
            --tc-bg: #eff2f7;
            --tc-surface: #ffffff;
            --tc-surface-2: #f6f8fb;
            --tc-surface-3: #eef1f6;
            --tc-border: #e6ebf2;
            --tc-border-strong: #d6dce6;
            --tc-border-soft: #eef2f7;
            --tc-text: #1c2533;
            --tc-text-soft: #475467;
            --tc-text-muted: #7a8699;
            --tc-text-faint: #9aa6b6;
            /* Accent — refined indigo (moderate, premium) */
            --tc-primary: #4154d6;
            --tc-primary-strong: #3343b8;
            --tc-primary-soft: #eef1fd;
            --tc-primary-softer: #f5f7fe;
            /* Status palette (spec colors, slightly desaturated for a softer look) */
            --tc-running: #1f9d57;
            --tc-running-soft: #e7f6ee;
            --tc-idle: #d99a16;
            --tc-idle-soft: #fbf2dc;
            --tc-stopped: #e8763a;
            --tc-stopped-soft: #fcefe6;
            --tc-offline: #e1556a;
            --tc-offline-soft: #fcebef;
            --tc-alert: #d6394d;
        }

        /* Dark mode scaffold — flip by adding data-theme="dark" on .tc-app (no logic change needed). */
        .tc-app[data-theme="dark"] {
            --tc-bg: #0e1626;
            --tc-surface: #18233a;
            --tc-surface-2: #131c2e;
            --tc-surface-3: #1d2940;
            --tc-border: #2a3650;
            --tc-border-strong: #364563;
            --tc-border-soft: #243049;
            --tc-text: #e8edf5;
            --tc-text-soft: #b6c0d0;
            --tc-text-muted: #8d9aae;
            --tc-text-faint: #6b7689;
            --tc-primary-soft: #1e2a4d;
            --tc-primary-softer: #18223c;
        }

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
            background: var(--tc-bg);
        }

        /* ===== Top icon toolbar (Traccar style) ===== */
        .tc-iconbar {
            display: flex;
            align-items: center;
            gap: 0.15rem;
            flex-wrap: wrap;
            padding: 0.4rem 0.7rem;
            background: var(--tc-surface);
            border-bottom: 1px solid var(--tc-border);
            box-shadow: var(--tc-shadow-sm);
            z-index: 6;
        }

        .tc-iconbar a {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            width: 38px;
            height: 38px;
            border-radius: var(--tc-radius-sm);
            color: var(--tc-text-muted);
            text-decoration: none;
            font-size: 1.02rem;
            transition: background 0.15s ease, color 0.15s ease, box-shadow 0.15s ease;
        }

        .tc-iconbar a:hover { background: var(--tc-primary-soft); color: var(--tc-primary); }
        .tc-iconbar a.active { background: var(--tc-primary); color: #fff; box-shadow: 0 4px 10px -3px color-mix(in srgb, var(--tc-primary) 55%, transparent); }
        .tc-iconbar .tc-iconbar-sep { flex: 1; }

        /* Alert controls (sound + desktop notifications) */
        .tc-alert-controls { display: flex; align-items: center; gap: 0.25rem; flex-shrink: 0; }
        .tc-alert-btn {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            width: 38px;
            height: 38px;
            border: 1px solid var(--tc-border);
            border-radius: var(--tc-radius-sm);
            background: var(--tc-surface);
            color: var(--tc-text-muted);
            font-size: 1rem;
            cursor: pointer;
            transition: background 0.15s ease, color 0.15s ease, border-color 0.15s ease;
        }
        .tc-alert-btn:hover { background: var(--tc-surface-2); color: var(--tc-primary); border-color: var(--tc-primary); }
        .tc-alert-btn.on { background: var(--tc-primary-soft); color: var(--tc-primary); border-color: color-mix(in srgb, var(--tc-primary) 40%, transparent); }
        .tc-alert-btn.muted { color: var(--tc-text-faint); }

        /* Unread badge on the Events tab */
        .tc-tab { position: relative; }
        .tc-tab-badge {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            min-width: 17px;
            height: 17px;
            padding: 0 4px;
            margin-inline-start: 5px;
            border-radius: 9px;
            background: var(--tc-alert);
            color: #fff;
            font-size: 0.62rem;
            font-weight: 700;
            vertical-align: middle;
        }

        .tc-main {
            flex: 1;
            display: flex;
            min-height: 0;
            position: relative;
        }

        /* Mobile drawer toggle (hidden on desktop) */
        .tc-panel-toggle {
            display: none;
            position: absolute;
            top: 12px;
            inset-inline-start: 12px;
            z-index: 7;
            width: 42px;
            height: 42px;
            border: none;
            border-radius: 10px;
            background: var(--tc-surface);
            color: var(--tc-primary);
            align-items: center;
            justify-content: center;
            font-size: 1.05rem;
            box-shadow: var(--tc-shadow);
            cursor: pointer;
        }
        .tc-panel-toggle .tc-toggle-badge {
            position: absolute;
            top: -5px;
            inset-inline-end: -5px;
            min-width: 18px;
            height: 18px;
            padding: 0 4px;
            border-radius: 9px;
            background: var(--tc-primary);
            color: #fff;
            font-size: 0.62rem;
            font-weight: 700;
            display: flex;
            align-items: center;
            justify-content: center;
            line-height: 1;
        }

        /* Backdrop behind the mobile drawer. Uses visibility (not display) so it is
           fully inert when closed and never intercepts map taps. */
        .tc-panel-backdrop {
            position: absolute;
            inset: 0;
            background: rgba(15, 23, 42, 0.45);
            z-index: 8;
            opacity: 0;
            visibility: hidden;
            pointer-events: none;
            transition: opacity 0.25s ease, visibility 0.25s ease;
        }
        .tc-panel-backdrop.show { opacity: 1; visibility: visible; pointer-events: auto; }

        /* ===== Left panel ===== */
        .tc-panel {
            width: min(360px, 94vw);
            flex-shrink: 0;
            display: flex;
            flex-direction: column;
            background: var(--tc-surface);
            border-inline-end: 1px solid var(--tc-border);
            z-index: 4;
        }

        .tc-tabs {
            display: flex;
            border-bottom: 1px solid var(--tc-border);
        }

        .tc-tab {
            flex: 1;
            padding: 0.6rem 0.4rem;
            text-align: center;
            font-size: 0.82rem;
            font-weight: 600;
            color: var(--tc-text-muted);
            background: var(--tc-surface-2);
            border: none;
            border-bottom: 2px solid transparent;
            cursor: pointer;
            white-space: nowrap;
            transition: color 0.14s ease, background 0.14s ease, border-color 0.14s ease;
        }

        .tc-tab:hover { color: var(--tc-primary); background: var(--tc-surface); }
        .tc-tab.active {
            color: var(--tc-primary);
            background: var(--tc-surface);
            border-bottom-color: var(--tc-primary);
        }

        .tc-tab-body {
            flex: 1;
            min-height: 0;
            display: none;
            flex-direction: column;
        }

        .tc-tab-body.active { display: flex; }

        .tc-tab-head { padding: 0.6rem; border-bottom: 1px solid var(--tc-border); }

        .tc-filter-chips {
            display: flex;
            flex-wrap: wrap;
            gap: 0.3rem;
            margin-top: 0.5rem;
        }

        .tc-chip {
            border: 1px solid var(--tc-border);
            background: var(--tc-surface);
            color: var(--tc-text-soft);
            border-radius: 999px;
            padding: 0.22rem 0.65rem;
            font-size: 0.72rem;
            font-weight: 600;
            cursor: pointer;
            display: inline-flex;
            align-items: center;
            gap: 0.3rem;
            transition: all 0.14s ease;
        }
        .tc-chip:hover { border-color: var(--tc-primary); color: var(--tc-primary); }

        .tc-chip .tc-chip-count {
            background: var(--tc-surface-3);
            border-radius: 999px;
            padding: 0 0.35rem;
            font-size: 0.68rem;
            color: var(--tc-text-muted);
        }

        .tc-chip.active { background: var(--tc-primary); border-color: var(--tc-primary); color: #fff; box-shadow: 0 3px 8px -3px color-mix(in srgb, var(--tc-primary) 55%, transparent); }
        .tc-chip.active:hover { color: #fff; }
        .tc-chip.active .tc-chip-count { background: rgba(255, 255, 255, 0.25); color: #fff; }

        .tc-list-head {
            display: flex;
            align-items: center;
            gap: 0.5rem;
            padding: 0.4rem 0.7rem;
            background: var(--tc-surface-2);
            border-bottom: 1px solid var(--tc-border);
            font-size: 0.7rem;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            color: var(--tc-text-muted);
        }

        .tc-list-head .tc-col { width: 16px; text-align: center; flex-shrink: 0; }
        .tc-list-head .tc-head-label { flex: 1; text-align: center; }

        .tc-list { flex: 1; overflow-y: auto; }

        .tc-row {
            display: flex;
            align-items: flex-start;
            gap: 0.5rem;
            padding: 0.6rem 0.7rem;
            border-bottom: 1px solid var(--tc-border-soft);
            cursor: pointer;
        }

        .tc-allrow { background: var(--tc-surface); }
        .tc-allrow:hover { background: var(--tc-surface); }

        .tc-row .tc-check { width: 16px; height: 16px; margin-top: 0.18rem; flex-shrink: 0; cursor: pointer; }
        .tc-check-spacer { width: 16px; flex-shrink: 0; }
        .tc-veh-icon { width: 20px; text-align: center; flex-shrink: 0; margin-top: 0.1rem; font-size: 1rem; line-height: 1.2; }

        .tc-row { transition: background 0.14s ease, box-shadow 0.14s ease; }
        .tc-row:hover { background: var(--tc-primary-softer); }
        .tc-row.active {
            background: var(--tc-primary-soft);
            box-shadow: inset 3px 0 0 0 var(--tc-primary);
        }

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
        .tc-row-title { font-weight: 600; font-size: 0.86rem; color: var(--tc-text); }

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
            color: var(--st, var(--tc-text-muted));
            background: color-mix(in srgb, var(--st, var(--tc-text-muted)) 15%, #fff);
            border: 1px solid color-mix(in srgb, var(--st, var(--tc-text-muted)) 35%, transparent);
        }
        .tc-status-badge::before {
            content: '';
            width: 7px;
            height: 7px;
            border-radius: 50%;
            background: var(--st, var(--tc-text-muted));
            flex-shrink: 0;
        }
        .tc-row-meta {
            font-size: 0.72rem;
            color: var(--tc-text-muted);
            margin-top: 0.1rem;
            line-height: 1.35;
            display: block;
        }

        .tc-row .tc-eye {
            color: var(--tc-text-faint);
            font-size: 0.95rem;
            margin-top: 0.2rem;
        }
        .tc-row .tc-eye.on { color: var(--tc-primary); }

        .tc-hist-form { flex-shrink: 0; }

        .tc-hist-summary {
            flex-shrink: 0;
            padding: 0.55rem 0.75rem;
            background: var(--tc-primary-soft);
            border-bottom: 1px solid color-mix(in srgb, var(--tc-primary) 18%, transparent);
            font-size: 0.78rem;
            color: var(--tc-text-soft);
        }
        .tc-hist-summary[hidden] { display: none !important; }
        .tc-hist-summary-row { display: flex; flex-wrap: wrap; gap: 0.5rem 1.1rem; }
        .tc-hist-summary-row span { white-space: nowrap; }
        .tc-hist-summary-row strong { color: var(--tc-text); }

        .tc-hist-results-wrap { flex: 1; min-height: 0; display: flex; flex-direction: column; }
        .tc-hist-results-wrap[hidden] { display: none !important; }
        .tc-stop-row { cursor: pointer; }
        .tc-stop-row:hover { background: var(--tc-primary-softer); }

        .tc-pmark {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            width: 20px;
            height: 20px;
            border-radius: 5px;
            background: var(--tc-primary);
            color: #fff;
            font-size: 0.7rem;
            font-weight: 700;
            flex-shrink: 0;
            margin-top: 0.15rem;
        }

        .tc-form { padding: 0.6rem; overflow-y: auto; }
        .tc-form label { font-size: 0.74rem; color: var(--tc-text-muted); margin-bottom: 0.1rem; display: block; }
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

        .tc-empty { padding: 1.2rem 0.8rem; text-align: center; color: var(--tc-text-faint); font-size: 0.82rem; }

        /* ===== Map ===== */
        .tc-map-wrap { position: relative; flex: 1; min-width: 0; display: flex; flex-direction: column; }
        .tc-map-area { position: relative; flex: 1; min-height: 0; }
        #tcMap { position: absolute; inset: 0; background: var(--tc-surface-3); }

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
            background: var(--tc-surface);
            color: var(--tc-text-soft);
            border: 1px solid var(--tc-border);
            border-radius: var(--tc-radius);
            box-shadow: var(--tc-shadow);
            transition: background 0.14s ease, color 0.14s ease;
        }
        .tc-map-controls .btn:hover { background: var(--tc-surface-2); color: var(--tc-primary); }
        .tc-map-controls .btn.active { background: var(--tc-primary); color: #fff; border-color: var(--tc-primary); }
        .tc-map-controls .tc-ctrl-group {
            display: flex;
            flex-direction: column;
            border-radius: var(--tc-radius);
            overflow: hidden;
            box-shadow: var(--tc-shadow);
        }
        .tc-map-controls .tc-ctrl-group .btn {
            box-shadow: none;
            border-radius: 0;
            border: none;
        }
        .tc-map-controls .tc-ctrl-group .btn + .btn { border-top: 1px solid var(--tc-border); }

        /* Per-vehicle kebab menu (Traccar-style row actions) */
        .tc-row-menu-btn {
            border: 0;
            background: transparent;
            color: var(--tc-text-faint);
            width: 26px;
            height: 26px;
            border-radius: 6px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            flex-shrink: 0;
            cursor: pointer;
            transition: background 0.15s, color 0.15s;
        }
        .tc-row-menu-btn:hover { background: var(--tc-surface-3); color: var(--tc-text-soft); }
        .tc-veh-menu {
            position: fixed;
            z-index: 1080;
            min-width: 220px;
            background: var(--tc-surface);
            border: 1px solid var(--tc-border);
            border-radius: var(--tc-radius);
            box-shadow: var(--tc-shadow-lg);
            padding: 6px;
            border-top: 3px solid var(--tc-primary);
            font-size: 0.875rem;
        }
        .tc-veh-menu-item {
            display: flex;
            align-items: center;
            gap: 10px;
            width: 100%;
            padding: 8px 12px;
            border: 0;
            background: transparent;
            border-radius: 7px;
            cursor: pointer;
            color: var(--tc-text-soft);
            text-align: start;
            white-space: nowrap;
            position: relative;
        }
        .tc-veh-menu-item:hover { background: var(--tc-primary-soft); color: var(--tc-primary); }
        .tc-veh-menu-item:hover i.tc-mi-icon { color: var(--tc-primary); }
        .tc-veh-menu-item i.tc-mi-icon { width: 18px; text-align: center; color: var(--tc-text-muted); }
        .tc-veh-menu-item .tc-mi-caret { margin-inline-start: auto; color: var(--tc-text-faint); font-size: 0.7rem; }
        .tc-veh-menu-sep { height: 1px; background: var(--tc-border-soft); margin: 4px 6px; }
        .tc-veh-submenu { min-width: 170px; }
        [dir="rtl"] .tc-veh-menu-item { text-align: right; }

        .tc-legend {
            position: absolute;
            bottom: 12px;
            inset-inline-start: 12px;
            background: color-mix(in srgb, var(--tc-surface) 94%, transparent);
            backdrop-filter: blur(6px);
            border: 1px solid var(--tc-border);
            border-radius: var(--tc-radius);
            padding: 0.5rem 0.7rem;
            box-shadow: var(--tc-shadow);
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
            background: var(--tc-surface);
            border-top: 1px solid var(--tc-border);
            box-shadow: 0 -6px 20px -8px rgba(16, 24, 40, 0.14);
            z-index: 5;
        }
        .tc-footer[hidden] { display: none !important; }
        .tc-footer-head {
            display: flex;
            align-items: center;
            gap: 0.75rem;
            padding: 0 0.7rem;
            border-bottom: 1px solid var(--tc-border-soft);
            font-size: 0.82rem;
            font-weight: 600;
            color: var(--tc-text-soft);
        }
        .tc-footer-tabs { display: flex; gap: 0.2rem; }
        .tc-ftab {
            border: none;
            background: none;
            padding: 0.55rem 0.7rem;
            font-size: 0.8rem;
            font-weight: 600;
            color: var(--tc-text-muted);
            border-bottom: 2px solid transparent;
            cursor: pointer;
        }
        .tc-ftab:hover { color: var(--tc-primary); }
        .tc-ftab.active { color: var(--tc-primary); border-bottom-color: var(--tc-primary); }
        .tc-footer-title { flex: 1; font-weight: 700; color: var(--tc-text); text-align: center; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
        .tc-footer-body { flex: 1; min-height: 0; position: relative; }
        .tc-fbody { position: absolute; inset: 0; padding: 0.5rem 0.8rem; overflow: auto; display: none; }
        .tc-fbody.active { display: block; }
        /* Data tab scrolls horizontally (Traccar-style strip), never grows the footer height. */
        .tc-fbody[data-fbody="data"] { overflow-x: scroll; overflow-y: hidden; scrollbar-width: thin; scrollbar-color: var(--tc-text-faint) var(--tc-border-soft); }
        .tc-fbody[data-fbody="data"]::-webkit-scrollbar { height: 11px; }
        .tc-fbody[data-fbody="data"]::-webkit-scrollbar-track { background: var(--tc-border-soft); border-radius: 6px; }
        .tc-fbody[data-fbody="data"]::-webkit-scrollbar-thumb { background: var(--tc-text-faint); border-radius: 6px; border: 2px solid var(--tc-border-soft); }
        .tc-fbody[data-fbody="data"]::-webkit-scrollbar-thumb:hover { background: var(--tc-text-muted); }
        /* History mode: vertical stat grid instead of horizontal live-data strip */
        .tc-fbody[data-fbody="data"].tc-fbody--hist { overflow-x: hidden; overflow-y: auto; }
        .tc-hist-stats { display: grid; grid-template-columns: repeat(auto-fill, minmax(160px, 1fr)); gap: 0.55rem; }
        .tc-hist-stat {
            border: 1px solid var(--tc-border);
            border-radius: var(--tc-radius);
            background: var(--tc-surface-2);
            padding: 0.55rem 0.75rem;
        }
        .tc-hist-stat .tc-hist-stat-lbl { font-size: 0.68rem; text-transform: uppercase; letter-spacing: 0.35px; color: var(--tc-text-faint); font-weight: 700; margin-bottom: 0.15rem; }
        .tc-hist-stat .tc-hist-stat-val { font-size: 1.05rem; font-weight: 700; color: var(--tc-text); }
        .tc-fbody[data-fbody="messages"] .tc-msg-list { list-style: none; margin: 0; padding: 0; }
        .tc-fbody[data-fbody="messages"] .tc-msg-list li {
            display: flex; justify-content: space-between; gap: 0.75rem;
            padding: 0.35rem 0; border-bottom: 1px dashed var(--tc-border-soft); font-size: 0.82rem;
        }
        .tc-fbody[data-fbody="messages"] .tc-msg-list li[data-tc-locate] { cursor: pointer; }
        .tc-fbody[data-fbody="messages"] .tc-msg-list li[data-tc-locate]:hover { color: var(--tc-primary); }
        .tc-fbody[data-fbody="messages"] .tc-msg-time { color: var(--tc-text-faint); white-space: nowrap; font-size: 0.76rem; }
        .tc-fbody[data-fbody="graph"] canvas,
        #tcSpeedChart { width: 100% !important; height: 100% !important; }

        /* Data tab: Traccar-style horizontally-scrollable widget cards */
        .tc-data-grid { display: flex; flex-wrap: nowrap; gap: 0.6rem; align-items: stretch; height: 100%; }
        .tc-data-col {
            box-sizing: border-box;
            flex: 0 0 250px;
            width: 250px;
            border: 1px solid var(--tc-border);
            border-radius: var(--tc-radius);
            background: var(--tc-surface-2);
            padding: 0.55rem 0.75rem;
            overflow-y: auto;
        }
        .tc-data-col h6 { font-size: 0.7rem; text-transform: uppercase; letter-spacing: 0.5px; color: var(--tc-primary); margin: 0 0 0.35rem; font-weight: 700; }
        .tc-kv { display: flex; align-items: center; justify-content: space-between; gap: 0.75rem; padding: 0.12rem 0; font-size: 0.8rem; border-bottom: 1px dashed var(--tc-border-soft); }
        .tc-kv .tc-kv-k { color: var(--tc-text-muted); display: inline-flex; align-items: center; gap: 0.4rem; }
        .tc-kv .tc-kv-v { color: var(--tc-text); font-weight: 600; text-align: end; }
        .tc-kv .tc-kv-v a { color: var(--tc-primary); text-decoration: none; }
        .tc-mini-events { list-style: none; margin: 0; padding: 0; }
        .tc-mini-events li { font-size: 0.78rem; padding: 0.18rem 0; border-bottom: 1px dashed var(--tc-border-soft); display: flex; justify-content: space-between; gap: 0.75rem; }
        .tc-mini-events li[data-tc-locate] { cursor: pointer; }
        .tc-mini-events li[data-tc-locate]:hover { color: var(--tc-primary); }
        .tc-mini-events .tc-me-time { color: var(--tc-text-faint); white-space: nowrap; }
        .tc-ctrl-row { display: flex; gap: 0.4rem; margin-top: 0.3rem; }
        .tc-ctrl-row .form-select, .tc-ctrl-row .form-control { font-size: 0.8rem; }

        /* Mileage bar chart (Traccar-style daily bars) */
        .tc-bars { display: flex; align-items: flex-end; gap: 0.45rem; height: 150px; padding-top: 0.4rem; }
        .tc-bar-col { flex: 1; display: flex; flex-direction: column; align-items: center; justify-content: flex-end; height: 100%; min-width: 0; }
        .tc-bar { width: 70%; min-height: 3px; border-radius: 3px 3px 0 0; }
        .tc-bar-val { font-size: 0.62rem; color: var(--tc-text-soft); margin-bottom: 2px; }
        .tc-bar-lbl { font-size: 0.66rem; color: var(--tc-text-faint); margin-top: 3px; }

        /* Speedometer gauge */
        .tc-gauge { width: 100%; max-width: 190px; display: block; margin: 0.4rem auto 0; }
        .tc-gauge-val { font-size: 22px; font-weight: 700; fill: var(--tc-text); }
        .tc-gauge-unit { font-size: 9px; fill: var(--tc-text-faint); }

        /* Notes / Photo */
        .tc-notes { font-size: 0.82rem; color: var(--tc-text-soft); white-space: pre-wrap; word-break: break-word; }
        .tc-photo { width: 100%; border-radius: 6px; display: block; }

        .tc-msg-table { width: 100%; font-size: 0.78rem; border-collapse: collapse; }
        .tc-msg-table th, .tc-msg-table td { padding: 0.2rem 0.5rem; border-bottom: 1px solid var(--tc-border-soft); text-align: start; white-space: nowrap; }
        .tc-msg-table th { color: var(--tc-text-faint); font-weight: 700; text-transform: uppercase; font-size: 0.68rem; }

        /* Module popup loader */
        .tc-module-loader {
            position: absolute; inset: 0; display: flex; align-items: center; justify-content: center;
            background: rgba(255, 255, 255, 0.9); z-index: 2;
        }
        .tc-module-loader[hidden] { display: none !important; }

        /* Stop info window */
        .tc-info { font-size: 0.8rem; min-width: 220px; }
        .tc-info-row { display: flex; justify-content: space-between; gap: 0.75rem; padding: 1px 0; }
        .tc-info-row span { color: var(--tc-text-muted); }
        .tc-info-row b { color: var(--tc-text); font-weight: 600; }

        /* Google Maps marker label (vehicle name + speed) — Traccar-style box */
        .tc-mk-label {
            background: rgba(255, 255, 255, 0.92);
            padding: 1px 6px;
            border-radius: 4px;
            border: 1px solid rgba(15, 23, 42, 0.18);
            box-shadow: 0 1px 3px rgba(15, 23, 42, 0.22);
            white-space: nowrap;
        }

        /* ===== Enhanced vehicle list meta (badges/indicators) ===== */
        .tc-meta-chips { display: flex; flex-wrap: wrap; gap: 0.3rem 0.5rem; align-items: center; margin-top: 0.15rem; }
        .tc-meta-chip { display: inline-flex; align-items: center; gap: 0.22rem; font-size: 0.72rem; color: var(--tc-text-muted); }
        .tc-meta-chip i { font-size: 0.72rem; }
        .tc-ign-on { color: var(--tc-running); }
        .tc-ign-off { color: var(--tc-text-muted); }
        .tc-sig-strong { color: var(--tc-running); }
        .tc-sig-mid { color: var(--tc-idle); }
        .tc-sig-weak { color: var(--tc-offline); }
        .tc-batt-ok { color: var(--tc-running); }
        .tc-batt-mid { color: var(--tc-idle); }
        .tc-batt-low { color: var(--tc-offline); }

        /* ===== Map layer switcher ===== */
        .tc-map-controls .tc-layer-wrap { position: relative; }
        .tc-layer-menu {
            position: absolute;
            top: 0;
            inset-inline-end: calc(100% + 8px);
            background: var(--tc-surface);
            border: 1px solid var(--tc-border);
            border-radius: var(--tc-radius);
            box-shadow: var(--tc-shadow-lg);
            padding: 6px;
            min-width: 150px;
            display: none;
        }
        .tc-layer-menu.open { display: block; }
        .tc-layer-item {
            display: flex; align-items: center; gap: 8px;
            width: 100%; padding: 7px 10px; border: 0; background: transparent;
            border-radius: var(--tc-radius-sm); cursor: pointer; color: var(--tc-text);
            font-size: 0.82rem; text-align: start;
        }
        .tc-layer-item:hover { background: var(--tc-surface-2); }
        .tc-layer-item.active { background: color-mix(in srgb, var(--tc-primary) 12%, transparent); color: var(--tc-primary); font-weight: 600; }
        .tc-layer-item i { width: 16px; text-align: center; }

        /* ===== Skeleton loaders + fade-in ===== */
        @keyframes tcShimmer { 0% { background-position: -320px 0; } 100% { background-position: 320px 0; } }
        .tc-skel {
            background: linear-gradient(90deg, var(--tc-surface-2) 25%, var(--tc-surface-3) 37%, var(--tc-surface-2) 63%);
            background-size: 640px 100%;
            animation: tcShimmer 1.2s infinite linear;
            border-radius: var(--tc-radius-sm);
        }
        .tc-skel-row { display: flex; align-items: center; gap: 0.6rem; padding: 0.55rem 0.7rem; }
        .tc-skel-dot { width: 26px; height: 26px; border-radius: 50%; flex-shrink: 0; }
        .tc-skel-lines { flex: 1; display: flex; flex-direction: column; gap: 6px; }
        .tc-skel-line { height: 10px; }
        .tc-skel-line.sm { width: 55%; height: 8px; }
        @keyframes tcFadeIn { from { opacity: 0; transform: translateY(2px); } to { opacity: 1; transform: none; } }
        .tc-fade-in { animation: tcFadeIn 0.25s ease both; }

        /* ===== Tablet ===== */
        @media (max-width: 1024px) and (min-width: 769px) {
            .tc-panel { width: min(320px, 42vw); }
            .tc-map-controls .btn { width: 40px; height: 40px; }
        }

        /* ===== Mobile / small tablet: panel becomes an off-canvas drawer ===== */
        @media (max-width: 768px) {
            /* Toolbar scrolls horizontally instead of wrapping into tall rows */
            .tc-iconbar {
                flex-wrap: nowrap;
                overflow-x: auto;
                overflow-y: hidden;
                -webkit-overflow-scrolling: touch;
                scrollbar-width: none;
            }
            .tc-iconbar::-webkit-scrollbar { display: none; }
            .tc-iconbar a { flex: 0 0 auto; width: 40px; height: 40px; }
            .tc-iconbar .tc-iconbar-sep { display: none; }

            /* Drawer */
            .tc-panel {
                position: absolute;
                top: 0;
                bottom: 0;
                inset-inline-start: 0;
                width: 86vw;
                max-width: 340px;
                border-inline-end: 1px solid var(--tc-border);
                box-shadow: var(--tc-shadow-lg);
                transform: translateX(-106%);
                transition: transform 0.28s ease;
                z-index: 9;
                will-change: transform;
            }
            [dir="rtl"] .tc-panel { transform: translateX(106%); }
            .tc-panel.tc-panel--open { transform: none; }

            .tc-panel-toggle { display: inline-flex; }

            /* Map controls a touch smaller and out of the way of the toggle */
            .tc-map-controls { top: 10px; inset-inline-end: 10px; gap: 6px; z-index: 6; }
            .tc-map-controls .btn { width: 40px; height: 40px; }

            /* Footer becomes a height-capped bottom sheet */
            .tc-footer { height: auto; max-height: 58dvh; }
            .tc-footer-title { display: none; }

            /* Per-vehicle menu fills more of the width for easy tapping */
            .tc-veh-menu { min-width: min(260px, 80vw); }
        }

        @media (max-width: 768px) and (orientation: landscape) {
            .tc-panel { width: 62vw; max-width: 320px; }
            .tc-footer { max-height: 70dvh; }
        }

        /* Larger touch targets on touch devices */
        @media (hover: none) and (pointer: coarse) {
            .tc-row { padding-top: 0.7rem; padding-bottom: 0.7rem; }
            .tc-tab { padding: 0.75rem 0.4rem; }
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
            <div class="tc-alert-controls">
                <button type="button" class="tc-alert-btn" id="tcSoundToggle"
                        title="{{ __('app.tracking.alert_sound') }}" aria-label="{{ __('app.tracking.alert_sound') }}" aria-pressed="true">
                    <i class="fas fa-volume-high"></i>
                </button>
                <button type="button" class="tc-alert-btn" id="tcDesktopToggle"
                        title="{{ __('app.tracking.alert_desktop') }}" aria-label="{{ __('app.tracking.alert_desktop') }}" aria-pressed="false">
                    <i class="fas fa-bell"></i>
                </button>
            </div>
        </div>

        <div class="tc-main">
            <button type="button" class="tc-panel-toggle" id="tcPanelToggle"
                    aria-label="{{ __('app.tracking.panel_toggle') }}" title="{{ __('app.tracking.panel_toggle') }}"
                    aria-expanded="false" aria-controls="tcPanel">
                <i class="fas fa-list-ul"></i>
                <span class="tc-toggle-badge" id="tcToggleBadge" hidden>0</span>
            </button>
            <div class="tc-panel-backdrop" id="tcPanelBackdrop"></div>
            <aside class="tc-panel" id="tcPanel">
                <div class="tc-tabs" role="tablist">
                    <button type="button" class="tc-tab active" data-tab="objects">{{ __('app.tracking.tab_objects') }}</button>
                    <button type="button" class="tc-tab" data-tab="events">{{ __('app.tracking.events_nav') }}<span class="tc-tab-badge" id="tcEventsBadge" hidden>0</span></button>
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
                        <div class="tc-ctrl-group">
                            <button type="button" class="btn btn-light" id="tcZoomIn" title="{{ __('app.tracking.zoom_in') }}">
                                <i class="fas fa-plus"></i>
                            </button>
                            <button type="button" class="btn btn-light" id="tcZoomOut" title="{{ __('app.tracking.zoom_out') }}">
                                <i class="fas fa-minus"></i>
                            </button>
                        </div>
                        <button type="button" class="btn btn-light" id="tcFit" title="{{ __('app.tracking.fit_all') }}">
                            <i class="fas fa-compress-arrows-alt"></i>
                        </button>
                        <button type="button" class="btn btn-light" id="tcFollow" title="{{ __('app.tracking.follow_vehicle') }}" aria-pressed="false">
                            <i class="fas fa-crosshairs"></i>
                        </button>
                        <button type="button" class="btn btn-light" id="tcRefresh" title="{{ __('app.tracking.refresh') }}">
                            <i class="fas fa-sync-alt"></i>
                        </button>
                        <button type="button" class="btn btn-light" id="tcTraffic" title="{{ __('app.tracking.layer_traffic') }}" aria-pressed="false">
                            <i class="fas fa-traffic-light"></i>
                        </button>
                        <div class="tc-layer-wrap">
                            <button type="button" class="btn btn-light" id="tcLayers" title="{{ __('app.tracking.map_layers') }}" aria-haspopup="true" aria-expanded="false">
                                <i class="fas fa-layer-group"></i>
                            </button>
                            <div class="tc-layer-menu" id="tcLayerMenu" role="menu">
                                <button type="button" class="tc-layer-item active" data-layer="roadmap" role="menuitemradio"><i class="fas fa-map"></i>{{ __('app.tracking.layer_map') }}</button>
                                <button type="button" class="tc-layer-item" data-layer="satellite" role="menuitemradio"><i class="fas fa-satellite"></i>{{ __('app.tracking.layer_satellite') }}</button>
                                <button type="button" class="tc-layer-item" data-layer="hybrid" role="menuitemradio"><i class="fas fa-globe"></i>{{ __('app.tracking.layer_hybrid') }}</button>
                                <button type="button" class="tc-layer-item" data-layer="terrain" role="menuitemradio"><i class="fas fa-mountain"></i>{{ __('app.tracking.layer_terrain') }}</button>
                            </div>
                        </div>
                        <button type="button" class="btn btn-light" id="tcCapture" title="{{ __('app.tracking.capture_image') }}">
                            <i class="fas fa-camera"></i>
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
            userId: @json(auth()->id()),
            alertPollIntervalMs: 15000,
            googleMapsKey: @json(config('services.google.maps_key')),
            liveJsonUrl: @json(route($routes['liveJson'])),
            historyJsonUrl: @json(route($routes['historyJson'])),
            eventsJsonUrl: @json(Route::has($routes['eventsJson']) ? route($routes['eventsJson']) : null),
            geofencesJsonUrl: @json(Route::has($routes['geofencesJson']) ? route($routes['geofencesJson']) : null),
            devicePanelUrl: @json(Route::has($routes['devicePanel']) ? route($routes['devicePanel']) : null),
            deviceMileageUrl: @json(Route::has($routes['deviceMileage']) ? route($routes['deviceMileage']) : null),
            commandsSendUrl: @json(Route::has($routes['commandsSend']) ? route($routes['commandsSend']) : null),
            commandTypes: @json(\App\Services\Tracking\CommandService::typeLabels()),
            liveUrl: @json(route($routes['live'])),
            deviceEditUrl: @json(Route::has("{$panel}.devices.edit") ? str_replace('/0/', '/__DEVICE_ID__/', route("{$panel}.devices.edit", ['device' => 0])) : null),
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
                menuShowHistory: @json(__('app.tracking.menu_show_history')),
                menuFollow: @json(__('app.tracking.menu_follow')),
                menuFollowNew: @json(__('app.tracking.menu_follow_new')),
                menuStreetView: @json(__('app.tracking.menu_street_view')),
                menuShare: @json(__('app.tracking.menu_share')),
                menuSendCommand: @json(__('app.tracking.menu_send_command')),
                menuEdit: @json(__('app.tracking.menu_edit')),
                menuActions: @json(__('app.tracking.menu_actions')),
                rangeLastHour: @json(__('app.tracking.range_last_hour')),
                rangeToday: @json(__('app.tracking.range_today')),
                rangeYesterday: @json(__('app.tracking.range_yesterday')),
                rangeBefore2: @json(__('app.tracking.range_before_2_days')),
                rangeBefore3: @json(__('app.tracking.range_before_3_days')),
                rangeThisWeek: @json(__('app.tracking.range_this_week')),
                rangeLastWeek: @json(__('app.tracking.range_last_week')),
                rangeThisMonth: @json(__('app.tracking.range_this_month')),
                rangeLastMonth: @json(__('app.tracking.range_last_month')),
                shareCopied: @json(__('app.tracking.share_copied')),
                sharePositionTitle: @json(__('app.tracking.share_position_title')),
                noPosition: @json(__('app.tracking.no_position')),
                gpsSignal: @json(__('app.tracking.gps_signal')),
                satellitesLabel: @json(__('app.tracking.satellites_label')),
                alertSound: @json(__('app.tracking.alert_sound')),
                alertSoundOff: @json(__('app.tracking.alert_sound_off')),
                alertDesktop: @json(__('app.tracking.alert_desktop')),
                alertDesktopOn: @json(__('app.tracking.alert_desktop_on')),
                alertDesktopOff: @json(__('app.tracking.alert_desktop_off')),
                alertDesktopBlocked: @json(__('app.tracking.alert_desktop_blocked')),
                newAlertTitle: @json(__('app.tracking.new_alert_title')),
            },
        };
    </script>
    <script src="{{ protected_js('app-datetime.js') }}"></script>
    <script src="{{ protected_js('vehicle-marker.js') }}"></script>
    <script src="{{ protected_js('tracking-traccar.js') }}"></script>
@endpush
