@extends($layout)

@section('title', __('app.tracking.live_title') . ' - ' . __('app.brand'))

@push('styles')
    <link rel="stylesheet" href="{{ asset('css/fleet-map.css') }}?v={{ filemtime(public_path('css/fleet-map.css')) }}">
    <link rel="stylesheet" href="{{ asset('css/vehicle-map-popup.css') }}?v={{ filemtime(public_path('css/vehicle-map-popup.css')) }}">
    <link rel="stylesheet" href="{{ asset('css/route-trip-bar.css') }}?v={{ filemtime(public_path('css/route-trip-bar.css')) }}">
    <style>
        /* Map-specific tokens (extends shared design-tokens partial) */
        :root {
            --tc-shadow-lg: 0 12px 32px rgba(0, 0, 0, 0.08), 0 0 0 0.5px rgba(0, 0, 0, 0.04);
            --tc-border-strong: rgba(60, 60, 67, 0.18);
            --tc-text-soft: #3a3a3c;
            --tc-primary-strong: #0062cc;
            --tc-primary-softer: rgba(0, 122, 255, 0.06);
            --tc-running: #34c759;
            --tc-running-soft: rgba(52, 199, 89, 0.12);
            --tc-idle: #ff9500;
            --tc-idle-soft: rgba(255, 149, 0, 0.12);
            --tc-stopped: #ff9500;
            --tc-stopped-soft: rgba(255, 149, 0, 0.12);
            --tc-offline: #ff3b30;
            --tc-offline-soft: rgba(255, 59, 48, 0.12);
            --tc-alert: #ff3b30;
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
            height: calc(100dvh - var(--tracking-topbar-height, 52px));
            max-height: calc(100dvh - var(--tracking-topbar-height, 52px));
            overflow: hidden;
            padding: 0 !important;
        }
        body.gt-page-active .tc-app { height: 100%; }
        @endif
        .content-wrap { padding: 0 !important; }
        .footer-premium { display: none; }

        .tc-app {
            display: flex;
            flex-direction: column;
            height: calc(100vh - var(--tracking-topbar-height, 52px));
            min-height: 460px;
            background: var(--tc-bg);
            position: relative;
            --tc-chrome-panel: 0px;
        }
        @media (max-height: 520px), ((max-width: 900px) and (orientation: landscape)) {
            .tc-app { min-height: 0; }
        }
            font-family: -apple-system, BlinkMacSystemFont, 'SF Pro Text', 'SF Pro Display', 'Helvetica Neue', sans-serif;
            letter-spacing: -0.018em;
        }
        .tc-app:has(.tc-panel--open) {
            --tc-chrome-panel: 0px;
        }

        /* Desktop: fleet panel sits beside the map (no overlay, no dimming). */
        @media (min-width: 769px) {
            .tc-main.tc-main--drawer .tc-panel {
                position: relative;
                top: auto;
                bottom: auto;
                inset-inline-start: auto;
                transform: none;
                z-index: 4;
                box-shadow: none;
                width: min(360px, 34vw);
                max-width: 360px;
                transition: width 0.28s ease, min-width 0.28s ease, opacity 0.2s ease, border-color 0.28s ease;
                overflow: hidden;
            }
            .tc-main.tc-main--drawer .tc-panel:not(.tc-panel--open) {
                width: 0;
                min-width: 0;
                max-width: 0;
                border-inline-end-color: transparent;
                opacity: 0;
                pointer-events: none;
            }
            .tc-main.tc-main--drawer .tc-map-wrap {
                flex: 1;
                min-width: 0;
                width: auto;
            }
            .tc-panel-backdrop {
                display: none !important;
            }
        }

        /* ===== Apple-style module navigation ===== */
        .tc-workspace-nav {
            flex-shrink: 0;
            display: flex;
            align-items: center;
            gap: 0.35rem;
            min-height: 34px;
            padding: 0.25rem 0.5rem;
            background: var(--apple-bg-primary);
            border-bottom: 0.5px solid var(--tc-border);
            z-index: 30;
        }
        html.tc-module-nav-collapsed .tc-workspace-nav,
        body.tc-module-nav-collapsed .tc-workspace-nav {
            display: none;
        }
        html.tc-module-nav-collapsed .tc-workspace-nav__reveal,
        body.tc-module-nav-collapsed .tc-workspace-nav__reveal {
            display: flex;
        }

        .tc-workspace-nav__reveal {
            display: none;
            align-items: center;
            justify-content: center;
            width: 100%;
            min-height: 28px;
            padding: 0.2rem 0.65rem;
            background: var(--apple-bg-primary);
            border-bottom: 0.5px solid var(--tc-border-soft);
        }
        .tc-workspace-nav__reveal-btn {
            display: inline-flex;
            align-items: center;
            gap: 0.35rem;
            border: none;
            border-radius: 6px;
            background: transparent;
            color: #007aff;
            font-size: 0.6875rem;
            font-weight: 510;
            letter-spacing: -0.01em;
            padding: 0.2rem 0.5rem;
            cursor: pointer;
        }
        .tc-workspace-nav__reveal-btn:hover {
            background: rgba(0, 122, 255, 0.08);
        }
        .tc-workspace-nav__reveal-btn i { font-size: 0.65rem; opacity: 0.85; }

        .tc-workspace-nav__panel-btn {
            position: relative;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            flex-shrink: 0;
            width: 30px;
            height: 30px;
            border: none;
            border-radius: 8px;
            background: rgba(118, 118, 128, 0.12);
            color: #3a3a3c;
            font-size: 0.8rem;
            cursor: pointer;
            transition: background 0.2s ease, color 0.2s ease, transform 0.15s ease;
        }
        .tc-workspace-nav__panel-btn:hover {
            background: rgba(118, 118, 128, 0.18);
            color: #1d1d1f;
        }
        .tc-workspace-nav__panel-btn.active,
        .tc-app.tc-app--panel-open .tc-workspace-nav__panel-btn {
            background: #007aff;
            color: #fff;
        }

        .tc-workspace-nav__collapse-btn {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            flex-shrink: 0;
            width: 26px;
            height: 26px;
            border: none;
            border-radius: 6px;
            background: transparent;
            color: #86868b;
            font-size: 0.7rem;
            cursor: pointer;
            transition: background 0.2s ease, color 0.2s ease;
        }
        .tc-workspace-nav__collapse-btn:hover {
            background: rgba(118, 118, 128, 0.12);
            color: #3a3a3c;
        }

        .tc-workspace-nav__track {
            display: flex;
            align-items: center;
            flex: 1;
            min-width: 0;
            padding: 2px;
            border-radius: 9px;
            background: var(--apple-bg-secondary);
            overflow-x: auto;
            scrollbar-width: none;
            -webkit-overflow-scrolling: touch;
        }
        .tc-workspace-nav__track::-webkit-scrollbar { display: none; }

        .tc-workspace-nav__links {
            display: inline-flex;
            align-items: center;
            gap: 2px;
            min-width: min-content;
        }

        .tc-workspace-nav__links a {
            display: inline-flex;
            align-items: center;
            gap: 0.3rem;
            padding: 0.22rem 0.5rem;
            border-radius: 6px;
            color: #636366;
            text-decoration: none;
            font-size: 0.6875rem;
            font-weight: 500;
            letter-spacing: -0.02em;
            line-height: 1.25;
            white-space: nowrap;
            transition: background 0.2s ease, color 0.2s ease, box-shadow 0.2s ease;
        }
        .tc-workspace-nav__links a i {
            font-size: 0.6875rem;
            opacity: 0.75;
            width: 1em;
            text-align: center;
            color: #8e8e93;
        }
        .tc-workspace-nav__links a span {
            text-transform: none;
        }
        .tc-workspace-nav__links a:hover {
            color: #1d1d1f;
        }
        .tc-workspace-nav__links a:hover i { opacity: 0.95; color: #636366; }
        .tc-workspace-nav__links a.active {
            background: var(--apple-bg-primary);
            color: #1d1d1f;
            font-weight: 600;
            box-shadow: var(--tc-shadow-sm);
        }
        .tc-workspace-nav__links a.active i {
            opacity: 1;
            color: #007aff;
        }

        .tc-workspace-nav__actions {
            display: inline-flex;
            align-items: center;
            flex-shrink: 0;
            margin-inline-start: 0.15rem;
        }

        .tc-workspace-nav__panel-btn .tc-toggle-badge {
            position: absolute;
            top: -4px;
            inset-inline-end: -4px;
            min-width: 16px;
            height: 16px;
            padding: 0 4px;
            border-radius: 999px;
            background: #ff3b30;
            color: #fff;
            font-size: 0.5625rem;
            font-weight: 600;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            line-height: 1;
            box-shadow: 0 0 0 1.5px #fff;
            pointer-events: none;
        }
        .tc-workspace-nav__panel-btn .tc-toggle-badge[hidden] { display: none !important; }

        /* Legacy aliases */
        .tc-iconbar { display: contents; }
        .tc-nav-toggle, .tc-nav-close, .tc-iconbar-links { display: none; }
        .tc-workspace-nav__divider { display: none; }
        .tc-workspace-nav__tool-btn { display: none; }

        /* Alert controls in map area (legacy fallback) */
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

        /* Backdrop when fleet drawer is open — fully inert when closed. */
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

        /* ===== Left panel (Apple sidebar) ===== */
        .tc-panel {
            width: min(272px, 94vw);
            flex-shrink: 0;
            display: flex;
            flex-direction: column;
            background: var(--tc-sidebar);
            border-inline-end: 0.5px solid var(--tc-border);
            z-index: 4;
        }

        /* Slide-out drawer on small screens only */
        @media (max-width: 768px) {
        .tc-main.tc-main--drawer .tc-panel {
            position: absolute;
            top: 0;
            bottom: 0;
            inset-inline-start: 0;
            max-width: 360px;
            border-inline-end: 1px solid var(--tc-border);
            box-shadow: var(--tc-shadow-lg);
            transform: translateX(-106%);
            transition: transform 0.28s ease;
            z-index: 9;
            will-change: transform;
        }
        [dir="rtl"] .tc-main.tc-main--drawer .tc-panel { transform: translateX(106%); }
        .tc-main.tc-main--drawer .tc-panel.tc-panel--open { transform: none; }
        .tc-main.tc-main--drawer .tc-map-wrap { width: 100%; flex: 1; }
        .tc-app:has(.tc-panel--open) {
            --tc-chrome-panel: min(340px, 86vw);
        }
        }

        .tc-tabs {
            display: flex;
            gap: 2px;
            padding: 0.45rem 0.65rem 0.35rem;
            background: var(--tc-sidebar);
            border-bottom: 0.5px solid var(--tc-border-soft);
        }

        .tc-tab {
            flex: 1;
            padding: 0.38rem 0.4rem;
            text-align: center;
            font-size: 0.8125rem;
            font-weight: 500;
            letter-spacing: -0.02em;
            color: #636366;
            background: transparent;
            border: none;
            border-bottom: none;
            border-radius: 8px;
            cursor: pointer;
            white-space: nowrap;
            transition: color 0.14s ease, background 0.14s ease;
        }

        .tc-tab:hover { color: #1d1d1f; background: rgba(118, 118, 128, 0.08); }
        .tc-tab.active {
            color: #007aff;
            background: rgba(0, 122, 255, 0.1);
            font-weight: 600;
        }

        .tc-panel .tc-list {
            background: var(--tc-sidebar);
        }

        .tc-tab-body {
            flex: 1;
            min-height: 0;
            display: none;
            flex-direction: column;
        }

        .tc-tab-body.active { display: flex; }

        .tc-tab-head {
            padding: 0.6rem;
            border-bottom: 0.5px solid var(--tc-border-soft);
            background: var(--apple-bg-group);
        }

        .tc-filter-chips {
            display: flex;
            flex-wrap: wrap;
            gap: 0.3rem;
            margin-top: 0.5rem;
        }

        .tc-chip {
            border: none;
            background: var(--apple-bg-primary);
            color: #636366;
            border-radius: 999px;
            padding: 0.28rem 0.7rem;
            font-size: 0.75rem;
            font-weight: 500;
            letter-spacing: -0.01em;
            cursor: pointer;
            display: inline-flex;
            align-items: center;
            gap: 0.35rem;
            transition: all 0.14s ease;
        }
        .tc-chip:hover { background: rgba(118, 118, 128, 0.18); color: #1d1d1f; }

        .tc-chip .tc-chip-count {
            background: var(--apple-bg-secondary);
            border-radius: 999px;
            padding: 0 0.35rem;
            font-size: 0.6875rem;
            color: #8e8e93;
            font-weight: 600;
        }

        .tc-chip.active { background: #007aff; color: #fff; box-shadow: 0 2px 8px rgba(0, 122, 255, 0.28); }
        .tc-chip.active:hover { color: #fff; background: #0062cc; }
        .tc-chip.active .tc-chip-count { background: rgba(255, 255, 255, 0.25); color: #fff; }

        .tc-list-head {
            display: flex;
            align-items: center;
            gap: 0.5rem;
            padding: 0.45rem 0.75rem;
            background: var(--apple-bg-group);
            border-bottom: 0.5px solid var(--tc-border-soft);
            font-size: 0.6875rem;
            font-weight: 600;
            text-transform: none;
            letter-spacing: -0.01em;
            color: #86868b;
        }

        .tc-list-head .tc-col { width: 16px; text-align: center; flex-shrink: 0; }
        .tc-list-head .tc-head-label { flex: 1; text-align: center; }

        .tc-list { flex: 1; overflow-y: auto; }

        .tc-row {
            display: flex;
            align-items: flex-start;
            gap: 0.4rem;
            padding: 0.45rem 0.6rem;
            border-bottom: 0.5px solid var(--tc-border-soft);
            cursor: pointer;
            background: var(--apple-bg-primary);
        }

        .tc-allrow { background: var(--apple-bg-primary); }
        .tc-allrow:hover { background: var(--apple-bg-primary); }

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
        .tc-row-title { font-weight: 600; font-size: 0.8125rem; color: var(--tc-text); }

        .tc-status-badge {
            display: inline-flex;
            align-items: center;
            gap: 0.22rem;
            font-size: 0.58rem;
            font-weight: 600;
            line-height: 1;
            white-space: nowrap;
            padding: 0.12rem 0.35rem;
            border-radius: 999px;
            color: var(--st, var(--tc-text-muted));
            background: color-mix(in srgb, var(--st, var(--tc-text-muted)) 12%, #fff);
            border: 1px solid color-mix(in srgb, var(--st, var(--tc-text-muted)) 28%, transparent);
        }
        .tc-status-badge::before {
            content: '';
            width: 5px;
            height: 5px;
            border-radius: 50%;
            background: var(--st, var(--tc-text-muted));
            flex-shrink: 0;
        }
        .tc-row-meta {
            font-size: 0.66rem;
            color: var(--tc-text-muted);
            margin-top: 0.06rem;
            line-height: 1.25;
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

        .tc-evmark {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            min-width: 20px;
            height: 20px;
            padding: 0 4px;
            border-radius: 5px;
            background: var(--tc-primary);
            color: #fff;
            font-size: 0.62rem;
            font-weight: 700;
            flex-shrink: 0;
            margin-top: 0.15rem;
            text-transform: uppercase;
            letter-spacing: 0.02em;
        }
        .tc-evmark--geofence { background: #7c3aed; }
        .tc-evmark--overspeed { background: #dc2626; }
        .tc-evmark--ignition { background: #ea580c; }
        .tc-evmark--moving { background: #16a34a; }
        .tc-evmark--idle { background: #ca8a04; }
        .tc-evmark--parked, .tc-evmark--parking { background: var(--tc-primary); }
        .tc-evmark--offline { background: #64748b; }
        .tc-evmark--alert { background: #b91c1c; }

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
        .tc-map-area {
            position: relative;
            flex: 1;
            min-height: 0;
            --tc-chrome-top: 12px;
            --tc-chrome-start: calc(var(--tc-chrome-panel, 0px) + 12px);
        }

        /* Company card — separate overlay on map (not part of hub top bar) */
        .tc-map-overlay--start {
            position: absolute;
            top: var(--tc-chrome-top);
            inset-inline-start: var(--tc-chrome-start);
            z-index: 11;
            width: auto;
            max-width: min(340px, calc(100% - var(--tc-chrome-start) - 16px));
            display: flex;
            flex-direction: column;
            align-items: flex-start;
            gap: 8px;
            pointer-events: none;
            transition: top 0.24s ease, inset-inline-start 0.28s ease, max-width 0.28s ease;
        }
        .tc-map-overlay--start > * { pointer-events: auto; }
        .tc-map-overlay--start .tc-company-card:not(.is-open) {
            width: auto;
            min-width: 120px;
            max-width: min(280px, calc(100vw - var(--tc-chrome-start) - 24px));
            border-radius: 10px;
        }
        .tc-map-overlay--start .tc-company-card:not(.is-open) .tc-info-card__collapse {
            min-height: 42px;
            padding: 10px 14px;
            font-size: 13px;
        }
        .tc-map-overlay--start .tc-company-card.is-open {
            width: min(340px, calc(100vw - var(--tc-chrome-start) - 24px));
            max-width: 340px;
        }
        .tc-map-route-footer {
            position: absolute;
            bottom: 10px;
            left: 10px;
            right: 10px;
            z-index: 6;
            pointer-events: none;
            max-width: min(960px, calc(100% - 20px));
            margin-inline: auto;
        }
        .tc-map-area > .route-trip-bar.route-trip-bar--footer:not(.is-user-positioned) {
            position: absolute;
            bottom: 10px;
            left: 50%;
            transform: translateX(-50%);
            z-index: 6;
            width: min(960px, calc(100% - 20px));
            max-width: calc(100% - 20px);
            pointer-events: auto;
        }
        .tc-map-route-footer .route-trip-bar {
            position: static;
            top: auto;
            left: auto;
            transform: none;
            width: 100%;
            pointer-events: auto;
        }
        .map-panel-drag-grip {
            flex-shrink: 0;
            width: 24px;
            height: 28px;
            margin: 0;
            padding: 0;
            border: none;
            border-radius: 8px;
            background: transparent;
            color: #94a3b8;
            cursor: grab;
            touch-action: none;
            display: inline-flex;
            align-items: center;
            justify-content: center;
        }
        .map-panel-drag-grip:hover {
            color: #64748b;
            background: rgba(148, 163, 184, 0.16);
        }
        .map-panel-drag-grip:active { cursor: grabbing; }
        .map-panel-drag-grip--route {
            width: 20px;
            height: 22px;
            margin-inline-end: 4px;
        }
        .route-trip-bar.is-user-positioned {
            margin: 0;
            transform: none !important;
            z-index: 1205;
        }
        .route-trip-bar.is-dragging {
            z-index: 1206;
            box-shadow: 0 18px 48px rgba(15, 23, 42, 0.22);
            opacity: 0.97;
        }
        .route-trip-bar__head .map-panel-drag-grip { align-self: flex-start; }
        .route-trip-bar {
            position: absolute;
            top: var(--tc-chrome-top, 12px);
            left: 50%;
            transform: translateX(-50%);
            z-index: 6;
            width: min(720px, calc(100% - 24px));
            pointer-events: auto;
            transition: top 0.24s ease;
        }
        .route-trip-bar[hidden] { display: none !important; }
        .route-trip-bar__hint {
            font-size: 11px;
            color: var(--tc-text-muted);
            margin-bottom: 6px;
            text-align: center;
        }
        .route-trip-bar__manage-link {
            color: var(--tc-primary);
            font-weight: 600;
            text-decoration: none;
        }
        .route-trip-bar__manage-link:hover { text-decoration: underline; }
        .route-trip-bar__alert {
            background: #fef2f2;
            border: 1px solid #fecaca;
            color: #b91c1c;
            border-radius: 10px;
            padding: 8px 12px;
            font-size: 12px;
            font-weight: 600;
            margin-bottom: 8px;
            display: flex;
            align-items: center;
            gap: 8px;
        }
        .route-trip-bar__inner {
            background: var(--tc-surface);
            border: 1px solid var(--tc-border);
            box-shadow: var(--tc-shadow);
            border-radius: 14px;
            padding: 10px 14px 12px;
        }
        .route-trip-bar__cities {
            display: flex;
            justify-content: space-between;
            gap: 12px;
            font-size: 12px;
            font-weight: 700;
            color: var(--tc-text);
            margin-bottom: 8px;
        }
        .route-trip-bar__track {
            position: relative;
            height: 8px;
            border-radius: 999px;
            background: var(--tc-surface-3);
            overflow: visible;
        }
        .route-trip-bar__fill {
            height: 100%;
            border-radius: inherit;
            background: linear-gradient(90deg, #0ea5e9, #2563eb);
            transition: width 0.8s ease;
        }
        .route-trip-bar__fill--warn { background: linear-gradient(90deg, #f59e0b, #dc2626); }
        .route-trip-bar__bus {
            position: absolute;
            top: 50%;
            transform: translate(-50%, -50%);
            font-size: 18px;
            line-height: 1;
            transition: left 0.8s ease;
        }
        .route-trip-bar__meta {
            display: flex;
            flex-wrap: wrap;
            gap: 8px 14px;
            margin-top: 8px;
            font-size: 11px;
            color: var(--tc-text-soft);
        }
        .route-trip-bar__meta span:first-child { font-weight: 800; color: var(--tc-text); }
        .route-trip-complete-btn,
        .route-trip-start-btn { margin-top: 0; }
        .route-trip-bar__actions {
            display: flex;
            flex-wrap: wrap;
            gap: 8px;
            margin-top: 8px;
        }
        /* Apple-style map info cards (company / driver) */
        .tc-map-area--dark-panels {
            --tc-panel-bg: var(--apple-bg-card);
            --tc-panel-bg-deep: var(--apple-bg-group);
            --tc-panel-text: #1d1d1f;
            --tc-panel-label: #86868b;
            --tc-panel-radius: 16px;
            --tc-panel-shadow: var(--tc-shadow);
        }
        .tc-info-card {
            position: relative;
            width: 100%;
            pointer-events: auto;
            background: var(--tc-panel-bg);
            border: 0.5px solid var(--tc-border);
            border-radius: var(--tc-panel-radius);
            box-shadow: var(--tc-panel-shadow);
            color: var(--tc-panel-text);
            overflow: hidden;
        }
        .tc-info-card[hidden] { display: none !important; }
        .tc-info-card__collapse {
            position: absolute;
            top: 8px;
            inset-inline-end: 10px;
            z-index: 2;
            border: 0;
            background: transparent;
            color: var(--tc-panel-label);
            padding: 4px 6px;
            line-height: 1;
            cursor: pointer;
        }
        .tc-info-card__collapse:hover { color: var(--tc-panel-text); }
        .tc-info-card__collapse::before {
            content: '';
            display: none;
        }
        .tc-info-card:not(.is-open) .tc-info-card__collapse {
            position: static;
            width: 100%;
            min-height: 44px;
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 12px;
            padding: 11px 14px;
            color: var(--tc-panel-text);
            font-size: 0.8125rem;
            font-weight: 600;
            letter-spacing: -0.018em;
            text-align: start;
        }
        .tc-info-card:not(.is-open) .tc-info-card__collapse::before {
            content: attr(data-collapsed-label);
            display: inline-flex;
            min-width: 0;
            overflow: hidden;
            text-overflow: ellipsis;
            white-space: nowrap;
        }
        .tc-info-card__body {
            max-height: 520px;
            overflow: hidden;
            transition: max-height 0.28s ease, opacity 0.2s ease;
            opacity: 1;
            padding: 14px 16px;
            padding-inline-end: 32px;
        }
        .tc-info-card:not(.is-open) .tc-info-card__body {
            max-height: 0;
            opacity: 0;
            padding-top: 0;
            padding-bottom: 0;
        }
        .tc-info-row {
            display: flex;
            align-items: center;
            gap: 10px;
            padding: 8px 0;
            border-bottom: 0.5px solid rgba(60, 60, 67, 0.08);
        }
        .tc-info-row:first-child { padding-top: 0; }
        .tc-info-row:last-child { border-bottom: 0; padding-bottom: 0; }
        .tc-info-row[hidden] { display: none !important; }
        .tc-info-row__content {
            flex: 1 1 auto;
            min-width: 0;
        }
        .tc-info-row__labels {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            gap: 10px;
            margin-bottom: 2px;
        }
        .tc-info-row__label-en {
            flex: 0 1 46%;
            font-size: 0.6875rem;
            font-weight: 500;
            color: var(--tc-panel-label);
            line-height: 1.35;
            text-align: start;
            letter-spacing: -0.01em;
        }
        .tc-info-row__label-ar {
            flex: 0 1 46%;
            font-size: 0.6875rem;
            font-weight: 500;
            color: var(--tc-panel-label);
            direction: rtl;
            text-align: end;
            line-height: 1.35;
            unicode-bidi: plaintext;
        }
        .tc-info-row__icon {
            flex: 0 0 22px;
            width: 22px;
            text-align: center;
            font-size: 0.875rem;
            color: #007aff;
            line-height: 1;
            align-self: center;
            opacity: 0.85;
        }
        .tc-info-row__value {
            display: block;
            width: 100%;
            font-size: 0.875rem;
            font-weight: 600;
            color: var(--tc-panel-text);
            line-height: 1.4;
            letter-spacing: -0.018em;
            word-break: normal;
            overflow-wrap: break-word;
            white-space: normal;
            text-align: start;
            unicode-bidi: plaintext;
        }
        .tc-info-row__value--phone {
            font-size: 0.9375rem;
            letter-spacing: -0.01em;
        }
        .tc-info-row__value a {
            color: #007aff;
            text-decoration: none;
            font-weight: inherit;
            display: inline;
            white-space: normal;
        }
        .tc-info-row__value a:hover { text-decoration: underline; }
        .tc-driver-card__layout {
            display: flex;
            align-items: flex-start;
            gap: 10px;
            min-width: 0;
        }
        .tc-driver-card__fields {
            flex: 1 1 auto;
            min-width: 0;
        }
        .tc-driver-card__fields .tc-info-row {
            padding: 7px 0;
            border-bottom-color: var(--tc-panel-bg-deep);
        }
        .tc-driver-card__fields .tc-info-row:first-child { padding-top: 0; }
        .tc-driver-card__fields .tc-info-row:last-child { border-bottom: 0; padding-bottom: 0; }
        .tc-driver-card__photo-wrap {
            flex: 0 0 56px;
            width: 56px;
            height: 56px;
            background: var(--apple-bg-secondary);
            border-radius: 12px;
            overflow: hidden;
            box-shadow: 0 0 0 0.5px var(--tc-border);
        }
        .tc-driver-card__photo {
            width: 100%;
            height: 100%;
            object-fit: cover;
            display: block;
        }
        .tc-driver-card__photo--default {
            display: flex;
            align-items: center;
            justify-content: center;
            width: 100%;
            height: 100%;
            color: #94a3b8;
            font-size: 30px;
            background: #fff;
        }
        .tc-driver-card__empty {
            padding: 8px 0 0;
            font-size: 13px;
            font-weight: 500;
            color: var(--tc-panel-label);
            text-align: center;
        }

        /* Route progress bar — Apple elevated card on map */
        .tc-map-area--dark-panels .tc-info-card,
        .tc-map-area--dark-panels .route-trip-bar__card {
            background: var(--apple-bg-card);
            border: 0.5px solid var(--tc-border);
            border-radius: 16px;
            box-shadow: var(--tc-shadow);
            color: #1d1d1f;
        }
        .tc-map-area--dark-panels .route-trip-bar__head-title,
        .tc-map-area--dark-panels .route-trip-bar__stat-label,
        .tc-map-area--dark-panels .route-trip-bar__hint,
        .tc-map-area--dark-panels .route-trip-bar__compact-hint {
            color: #86868b;
            font-weight: 500;
            font-size: 0.75rem;
            letter-spacing: -0.01em;
        }
        .tc-map-area--dark-panels .route-trip-bar__stat-value,
        .tc-map-area--dark-panels .route-trip-bar__pct,
        .tc-map-area--dark-panels .route-trip-bar__meta {
            color: #1d1d1f;
            font-weight: 600;
            letter-spacing: -0.018em;
        }
        .tc-map-area--dark-panels .route-trip-bar__stat-value--muted {
            color: #86868b;
        }
        .tc-map-area--dark-panels .route-trip-bar__pct {
            font-weight: 700;
            font-size: 1rem;
            color: #007aff;
        }
        .tc-map-area--dark-panels .route-trip-bar__pct--warn { color: #ff3b30; }
        .tc-map-area--dark-panels .route-trip-bar__pct--frozen { color: #ff9500; }
        .tc-map-area--dark-panels .route-trip-bar__nav-badge {
            font-weight: 600;
            font-size: 0.6875rem;
            border: none;
            background: var(--apple-bg-secondary);
            color: #636366;
            border-radius: 6px;
        }
        .tc-map-area--dark-panels .route-trip-bar__nav-badge--on_assigned,
        .tc-map-area--dark-panels .route-trip-bar__nav-badge--destination_reached {
            color: #248a3d;
            background: rgba(52, 199, 89, 0.14);
        }
        .tc-map-area--dark-panels .route-trip-bar__nav-badge--off_route {
            color: #ff3b30;
            background: rgba(255, 59, 48, 0.12);
        }
        .tc-map-area--dark-panels .route-trip-bar__compact:hover {
            background: rgba(118, 118, 128, 0.06);
        }
        .tc-map-area--dark-panels .route-trip-bar__chevron {
            color: #86868b;
        }
        .tc-map-area--dark-panels .route-trip-bar__manage-link {
            color: #007aff;
            font-weight: 600;
            text-decoration: none;
        }
        .tc-map-area--dark-panels .route-trip-bar__manage-link:hover { text-decoration: underline; }
        .tc-map-area--dark-panels .route-trip-bar--footer .route-trip-bar__compact {
            padding: 14px 18px;
            gap: 10px;
        }
        .tc-map-area--dark-panels .route-trip-bar--footer .route-trip-bar__head {
            margin-bottom: 6px;
        }
        .tc-map-area--dark-panels .route-trip-bar--footer .route-trip-bar__head-title {
            font-size: 0.75rem;
            font-weight: 500;
            color: #86868b;
            text-transform: none;
            letter-spacing: -0.01em;
        }
        .tc-map-area--dark-panels .route-trip-bar--footer .route-trip-bar__pct {
            font-size: 1.0625rem;
            font-weight: 700;
            color: #007aff;
            letter-spacing: -0.022em;
        }
        .tc-map-area--dark-panels .route-trip-bar--footer .route-trip-bar__stats--compact {
            display: flex;
            flex-wrap: wrap;
            gap: 8px 16px;
            margin-top: 6px;
        }
        .tc-map-area--dark-panels .route-trip-bar--footer .route-trip-bar__stats--compact .route-trip-bar__stat {
            display: inline-flex;
            gap: 6px;
            align-items: baseline;
        }
        .tc-map-area--dark-panels .route-trip-bar--footer .route-trip-bar__stat-label {
            font-size: 0.75rem;
            font-weight: 500;
            color: #86868b;
            text-transform: none;
            letter-spacing: -0.01em;
            opacity: 1;
        }
        .tc-map-area--dark-panels .route-trip-bar--footer .route-trip-bar__stat-value {
            font-size: 0.875rem;
            font-weight: 600;
            color: #1d1d1f;
            letter-spacing: -0.018em;
        }
        .tc-map-area--dark-panels .route-trip-bar--footer .route-trip-bar__route-line {
            margin-top: 8px;
            height: 24px;
        }
        .tc-map-area--dark-panels .route-trip-bar--footer .route-trip-bar__line-bg {
            height: 5px;
            top: 10px;
            background: var(--apple-bg-secondary);
        }
        .tc-map-area--dark-panels .route-trip-bar--footer .route-trip-bar__line-fill {
            height: 4px;
            top: 9px;
        }
        .tc-map-area--dark-panels .route-trip-bar--footer .route-trip-bar__vehicle {
            font-size: 14px;
            top: 9px;
        }
        .tc-map-area--dark-panels .route-trip-bar--footer .route-trip-bar__details-inner {
            padding: 0 10px 8px;
            font-size: 10px;
        }
        .tc-map-area--dark-panels .route-trip-bar--footer .route-trip-bar__stats:not(.route-trip-bar__stats--compact) {
            display: none;
        }
        .tc-map-area--dark-panels .route-trip-bar--footer .route-trip-bar__card--idle .route-trip-bar__compact {
            padding: 16px 20px;
        }
        .tc-map-area--dark-panels .route-trip-bar__alert {
            background: rgba(127, 29, 29, 0.45);
            border-color: rgba(255, 255, 255, 0.15);
            color: #fecaca;
            font-weight: 700;
        }
        .tc-map-area--dark-panels .route-trip-bar__success {
            color: #bbf7d0;
            font-weight: 700;
        }

        .tc-map-overlay--end {
            position: absolute;
            top: var(--tc-chrome-top, 12px);
            inset-inline-end: 12px;
            z-index: 7;
            width: min(340px, calc(100% - 24px));
            display: flex;
            flex-direction: column;
            align-items: flex-end;
            gap: 8px;
            pointer-events: none;
            transition: top 0.24s ease;
        }
        .tc-map-overlay--end .tc-driver-card:not(.is-open) {
            border-radius: 10px;
        }
        .tc-map-overlay--end .tc-driver-card:not(.is-open) .tc-info-card__collapse {
            min-height: 42px;
            padding: 10px 14px;
            font-size: 13px;
        }
        .tc-map-overlay--end > * { pointer-events: auto; }
        .tc-map-overlay--end .tc-map-controls {
            position: static;
            top: auto;
            inset-inline-end: auto;
        }
        #tcMap { position: absolute; inset: 0; background: var(--apple-bg-secondary); }

        /* Vehicle marker click popup — rendered in Google Maps float pane */
        #tcVehicleMapPopupHost {
            display: none !important;
        }

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
        .tc-legend .tc-legend-item--heading { margin-top: 0.35rem; font-size: 0.68rem; text-transform: uppercase; letter-spacing: 0.04em; color: var(--tc-text-muted); }
        .tc-legend .tc-legend-swatch { width: 14px; height: 14px; border-radius: 3px; }
        .tc-legend .tc-legend-pin {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            width: 16px;
            height: 16px;
            border-radius: 999px;
            color: #fff;
            font-size: 0.58rem;
            font-weight: 800;
            line-height: 1;
        }

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

        /* Footer info panel (Data / Graph / Messages) — Apple elevated sheet */
        .tc-footer {
            height: var(--tc-footer-height, 250px);
            min-height: 0;
            max-height: 85vh;
            flex-shrink: 0;
            display: flex;
            flex-direction: column;
            background: var(--apple-bg-card);
            border-top: 0.5px solid var(--tc-border);
            box-shadow: 0 -4px 24px rgba(0, 0, 0, 0.06);
            z-index: 5;
            color: #1d1d1f;
            position: relative;
            overflow: hidden;
            transition: height 0.18s ease;
        }
        .tc-footer.is-dragging {
            transition: none;
            user-select: none;
        }
        .tc-footer-resize {
            flex-shrink: 0;
            height: 16px;
            cursor: ns-resize;
            touch-action: none;
            background: transparent;
            border-bottom: none;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        .tc-footer-resize::after {
            content: '';
            width: 40px;
            height: 5px;
            border-radius: 999px;
            background: rgba(60, 60, 67, 0.22);
        }
        .tc-footer-resize:hover::after,
        .tc-footer-resize:active::after,
        .tc-footer.is-dragging .tc-footer-resize::after {
            background: rgba(60, 60, 67, 0.4);
        }
        .tc-footer[hidden] { display: none !important; }
        .tc-footer-head {
            display: flex;
            align-items: center;
            gap: 0.5rem;
            padding: 0 0.65rem;
            min-height: 40px;
            border-bottom: 0.5px solid rgba(60, 60, 67, 0.1);
            font-size: 0.75rem;
            font-weight: 600;
            color: #1d1d1f;
            background: transparent;
            cursor: ns-resize;
            touch-action: none;
        }
        .tc-footer-tabs {
            display: flex;
            gap: 2px;
            padding: 3px;
            border-radius: 9px;
            background: var(--apple-bg-secondary);
        }
        .tc-ftab {
            border: none;
            background: none;
            padding: 0.28rem 0.55rem;
            font-size: 0.6875rem;
            font-weight: 500;
            letter-spacing: -0.018em;
            color: #636366;
            border-radius: 7px;
            border-bottom: none;
            cursor: pointer;
            touch-action: manipulation;
            transition: background 0.2s ease, color 0.2s ease, box-shadow 0.2s ease;
        }
        .tc-ftab:hover { color: #1d1d1f; }
        .tc-ftab.active {
            color: #1d1d1f;
            font-weight: 600;
            background: var(--apple-bg-primary);
            box-shadow: var(--tc-shadow-sm);
        }
        .tc-footer-title {
            flex: 1;
            font-weight: 600;
            font-size: 0.75rem;
            letter-spacing: -0.018em;
            color: #1d1d1f;
            text-align: center;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }
        .tc-footer-head .tc-footer-close {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            width: 28px;
            height: 28px;
            border: none;
            border-radius: 8px;
            background: rgba(118, 118, 128, 0.12);
            color: #636366;
            font-size: 0.8125rem;
            cursor: pointer;
            transition: background 0.2s ease, color 0.2s ease;
        }
        .tc-footer-head .tc-footer-close:hover {
            background: rgba(118, 118, 128, 0.2);
            color: #1d1d1f;
        }
        .tc-footer-body {
            flex: 1;
            min-height: 0;
            position: relative;
            background: transparent;
        }
        .tc-footer .tc-hist-stat {
            background: var(--apple-bg-group);
            border: 0.5px solid var(--tc-border-soft);
            border-radius: 12px;
            box-shadow: none;
        }
        .tc-footer .tc-hist-stat-lbl { color: #86868b; font-weight: 500; font-size: 0.625rem; }
        .tc-footer .tc-hist-stat-val { color: #1d1d1f; font-weight: 600; font-size: 0.8125rem; letter-spacing: -0.018em; }
        .tc-footer .tc-empty { color: #86868b; font-size: 0.75rem; }
        .tc-footer .tc-fbody[data-fbody="messages"] .tc-msg-list li {
            border-bottom-color: rgba(60, 60, 67, 0.08);
            color: #1d1d1f;
            font-weight: 500;
            font-size: 0.75rem;
        }
        .tc-footer .tc-fbody[data-fbody="messages"] .tc-msg-time {
            color: #86868b;
            font-size: 0.6875rem;
        }
        .tc-footer .tc-data-col {
            background: var(--apple-bg-group);
            border: 0.5px solid var(--tc-border-soft);
            border-radius: 10px;
        }
        .tc-footer .tc-data-col h6 {
            color: #007aff;
            font-size: 0.625rem;
            font-weight: 600;
            letter-spacing: -0.01em;
            text-transform: none;
            margin-bottom: 0.25rem;
        }
        .tc-footer .tc-kv .tc-kv-k { color: #86868b; font-size: 0.6875rem; }
        .tc-footer .tc-kv .tc-kv-v { color: #1d1d1f; font-weight: 600; font-size: 0.75rem; }
        .tc-footer .tc-kv { padding: 0.08rem 0; }
        .tc-footer .tc-muted { color: #86868b; font-size: 0.6875rem; font-weight: 500; }
        .tc-fbody { position: absolute; inset: 0; padding: 0.4rem 0.65rem; overflow: auto; display: none; -webkit-overflow-scrolling: touch; }
        .tc-fbody.active { display: block; }
        /* Data tab: scroll horizontally across cards and vertically through all fields. */
        .tc-fbody[data-fbody="data"] {
            overflow-x: auto;
            overflow-y: auto;
            scrollbar-width: thin;
            scrollbar-color: var(--tc-text-faint) var(--tc-border-soft);
        }
        .tc-fbody[data-fbody="data"]::-webkit-scrollbar { width: 10px; height: 11px; }
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
        .tc-data-grid {
            display: flex;
            flex-wrap: nowrap;
            gap: 0.45rem;
            align-items: flex-start;
            width: max-content;
            min-width: 100%;
            min-height: 100%;
            height: auto;
            padding-bottom: 0.35rem;
            box-sizing: border-box;
        }
        .tc-data-col {
            box-sizing: border-box;
            flex: 0 0 200px;
            width: 200px;
            border: 1px solid var(--tc-border);
            border-radius: var(--tc-radius);
            background: var(--tc-surface-2);
            padding: 0.45rem 0.6rem;
            overflow: visible;
            height: auto;
            max-height: none;
        }
        .tc-data-col h6 { font-size: 0.625rem; text-transform: none; letter-spacing: -0.01em; color: var(--tc-primary); margin: 0 0 0.28rem; font-weight: 600; }
        .tc-kv { display: flex; align-items: center; justify-content: space-between; gap: 0.5rem; padding: 0.08rem 0; font-size: 0.75rem; border-bottom: 1px dashed var(--tc-border-soft); }
        .tc-kv .tc-kv-k { color: var(--tc-text-muted); display: inline-flex; align-items: center; gap: 0.35rem; font-size: 0.6875rem; }
        .tc-kv .tc-kv-v { color: var(--tc-text); font-weight: 600; text-align: end; font-size: 0.75rem; }
        .tc-kv .tc-kv-v a { color: var(--tc-primary); text-decoration: none; }
        .tc-mini-events { list-style: none; margin: 0; padding: 0; }
        .tc-mini-events li { font-size: 0.6875rem; padding: 0.14rem 0; border-bottom: 1px dashed var(--tc-border-soft); display: flex; justify-content: space-between; gap: 0.5rem; }
        .tc-mini-events li[data-tc-locate] { cursor: pointer; }
        .tc-mini-events li[data-tc-locate]:hover { color: var(--tc-primary); }
        .tc-mini-events .tc-me-time { color: var(--tc-text-faint); white-space: nowrap; font-size: 0.625rem; }
        .tc-ctrl-row { display: flex; gap: 0.35rem; margin-top: 0.25rem; }
        .tc-ctrl-row .form-select, .tc-ctrl-row .form-control { font-size: 0.6875rem; }

        /* Mileage bar chart (Traccar-style daily bars) */
        .tc-bars { display: flex; align-items: flex-end; gap: 0.45rem; height: 150px; padding-top: 0.4rem; }
        .tc-bar-col { flex: 1; display: flex; flex-direction: column; align-items: center; justify-content: flex-end; height: 100%; min-width: 0; }
        .tc-bar { width: 70%; min-height: 3px; border-radius: 3px 3px 0 0; }
        .tc-bar-val { font-size: 0.62rem; color: var(--tc-text-soft); margin-bottom: 2px; }
        .tc-bar-lbl { font-size: 0.66rem; color: var(--tc-text-faint); margin-top: 3px; }

        /* Speedometer gauge */
        .tc-gauge { width: 100%; max-width: 190px; display: block; margin: 0.4rem auto 0; }
        .tc-gauge-val { font-size: 18px; font-weight: 700; fill: var(--tc-text); }
        .tc-gauge-unit { font-size: 8px; fill: var(--tc-text-faint); }

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

        /* Google Maps marker label (vehicle name + speed) — above marker, status-colored */
        .tc-mk-label {
            padding: 2px 7px;
            border-radius: 4px;
            box-shadow: 0 1px 4px rgba(15, 23, 42, 0.28);
            white-space: nowrap;
            line-height: 1.25;
            color: #ffffff !important;
            text-shadow: 0 1px 1px rgba(15, 23, 42, 0.35);
        }
        .tc-mk-label--focused,
        .gmap-adv-marker-wrap--focused .tc-mk-label {
            box-shadow: 0 0 0 2px rgba(245, 158, 11, 0.85), 0 2px 6px rgba(15, 23, 42, 0.3);
            font-weight: 700;
        }
        .gmap-adv-marker-wrap--focused img {
            filter: drop-shadow(0 0 5px rgba(245, 158, 11, 0.85));
        }

        /* ===== Enhanced vehicle list meta (badges/indicators) ===== */
        .tc-meta-chips { display: flex; flex-wrap: wrap; gap: 0.2rem 0.35rem; align-items: center; margin-top: 0.08rem; }
        .tc-meta-chip { display: inline-flex; align-items: center; gap: 0.18rem; font-size: 0.625rem; color: var(--tc-text-muted); }
        .tc-meta-chip i { font-size: 0.625rem; }
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
            .tc-panel { width: min(272px, 38vw); }
            .tc-map-controls .btn { width: 40px; height: 40px; }
        }

        /* ===== Mobile / small tablet: panel becomes an off-canvas drawer ===== */
        @media (max-width: 768px) {
            /* Toolbar scrolls horizontally instead of wrapping into tall rows */
            .tc-workspace-nav__links a span {
                max-width: 5.5rem;
                overflow: hidden;
                text-overflow: ellipsis;
            }

            /* Drawer width tweaks on small screens */
            .tc-main.tc-main--drawer .tc-panel,
            .tc-panel {
                width: 86vw;
                max-width: 340px;
            }
            .tc-app:has(.tc-panel--open) {
                --tc-chrome-panel: min(340px, 86vw);
            }

            /* Legacy mobile drawer rules (kept for width / footer) */
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

            /* Map controls a touch smaller on mobile */
            .tc-map-controls { top: 10px; inset-inline-end: 10px; gap: 6px; z-index: 6; }
            .tc-map-controls .btn { width: 40px; height: 40px; }

            /* Footer becomes a height-capped bottom sheet — drag handle resize still applies */
            .tc-footer {
                height: var(--tc-footer-height, 280px);
                max-height: 70dvh;
                overflow: hidden;
            }
            .tc-footer-body { min-height: 0; }
            .tc-footer-title { display: none; }

            /* Per-vehicle menu fills more of the width for easy tapping */
            .tc-veh-menu { min-width: min(260px, 80vw); }
        }

        @media (max-width: 768px) and (orientation: landscape) {
            .tc-panel { width: 48vw; max-width: 300px; }
            .tc-footer {
                height: var(--tc-footer-height, 160px);
                max-height: 50dvh;
                overflow: hidden;
            }
            .tc-footer-body { min-height: 0; }
            .tc-app { min-height: 0; }
            .tc-hist-form {
                max-height: 42%;
                overflow: auto;
            }
            .tc-hist-results-wrap,
            .htt-list,
            .tc-hist-virtual-host {
                max-height: none;
                flex: 1;
                min-height: 0;
            }
            .playback-panel {
                max-width: min(520px, 92vw);
                transform: scale(0.92);
                transform-origin: bottom center;
            }
            .map-history-load-banner {
                top: 6px;
                max-width: min(90vw, 560px);
                gap: 4px;
            }
            .map-history-load-banner__item {
                padding: 3px 8px;
                font-size: 0.65rem;
            }
            .tc-hist-summary { font-size: 0.75rem; }
        }

        /* Larger touch targets on touch devices */
        @media (hover: none) and (pointer: coarse) {
            .tc-row { padding-top: 0.7rem; padding-bottom: 0.7rem; }
            .tc-tab { padding: 0.75rem 0.4rem; }
        }

        .tc-app.tc-app--map-only .tc-map-wrap {
            width: 100%;
            flex: 1;
        }
        .tc-app.tc-app--map-only .tc-main {
            display: block;
        }

        /* Live-map-only permission: hide tracking shell chrome */
        body.tc-live-map-only .tracking-topbar { display: none !important; }
        body.tc-live-map-only .content-wrap { padding: 0 !important; }
        body.tc-live-map-only .tc-app {
            height: 100dvh !important;
            max-height: 100dvh;
        }
        body.tc-live-map-only.gt-page-active .content-wrap {
            height: 100dvh;
            max-height: 100dvh;
        }

        /* History progressive load banner */
        .map-history-load-banner {
            position: absolute;
            top: 12px;
            left: 50%;
            transform: translateX(-50%);
            z-index: 12;
            display: flex;
            flex-wrap: wrap;
            gap: 6px;
            justify-content: center;
            max-width: min(96vw, 720px);
            pointer-events: none;
        }
        .map-history-load-banner[hidden] { display: none !important; }
        .map-history-load-banner__item {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            padding: 5px 10px;
            border-radius: 999px;
            background: rgba(15, 23, 42, 0.88);
            color: #e2e8f0;
            font-size: 0.72rem;
            font-weight: 600;
            backdrop-filter: blur(8px);
            border: 1px solid rgba(148, 163, 184, 0.25);
        }
        .map-history-load-banner__item[data-state="done"] {
            background: rgba(22, 101, 52, 0.9);
            border-color: rgba(74, 222, 128, 0.35);
        }
        .map-history-load-banner__icon.is-spinning i { animation: tc-spin 0.8s linear infinite; }
        .map-history-load-banner__icon.is-done::before { content: '✓'; margin-right: 2px; }
        .map-history-load-banner__icon.is-done i { display: none; }
        @keyframes tc-spin { to { transform: rotate(360deg); } }

        /* Virtualized history event list */
        .tc-hist-virtual-host {
            position: relative;
            overflow: auto;
            max-height: 42vh;
        }
        .tc-hist-virtual__spacer { position: relative; width: 100%; }
        .tc-hist-virtual__viewport {
            position: absolute;
            left: 0;
            right: 0;
            top: 0;
        }

        /* Route playback panel */
        .playback-panel {
            position: absolute;
            left: 50%;
            bottom: max(16px, env(safe-area-inset-bottom));
            transform: translateX(-50%) translateY(120%);
            width: min(560px, calc(100% - 24px));
            z-index: 1100;
            opacity: 0;
            visibility: hidden;
            pointer-events: none;
            transition: transform 0.35s ease, opacity 0.25s ease, visibility 0.25s;
        }
        .playback-panel.active {
            transform: translateX(-50%) translateY(0);
            opacity: 1;
            visibility: visible;
            pointer-events: auto;
        }
        .playback-panel__inner {
            background: linear-gradient(145deg, #ffffff 0%, #f8fafc 100%);
            border: 1px solid var(--tc-border, #e2e8f0);
            border-radius: 14px;
            box-shadow: 0 12px 40px rgba(15, 23, 42, 0.18);
            padding: 14px 16px 16px;
        }
        .playback-header { display: flex; align-items: center; justify-content: space-between; gap: 12px; margin-bottom: 12px; }
        .playback-header__left { display: flex; align-items: center; gap: 10px; min-width: 0; }
        .playback-badge {
            width: 40px; height: 40px; border-radius: 12px;
            background: linear-gradient(135deg, #2563eb, #3b82f6);
            color: #fff; display: flex; align-items: center; justify-content: center;
        }
        .playback-title { margin: 0; font-size: 0.92rem; font-weight: 700; color: #0f172a; }
        .playback-subtitle { margin: 2px 0 0; font-size: 0.72rem; color: #64748b; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
        .playback-close {
            width: 34px; height: 34px; border: none; border-radius: 10px;
            background: #f1f5f9; color: #64748b; cursor: pointer;
        }
        .playback-progress {
            position: relative; height: 8px; background: #e2e8f0; border-radius: 999px; cursor: pointer;
        }
        .playback-progress-bar {
            position: absolute; inset: 0 auto 0 0; width: 0;
            background: linear-gradient(90deg, #2563eb, #3b82f6); border-radius: 999px;
        }
        .playback-progress-thumb {
            position: absolute; top: 50%; width: 14px; height: 14px;
            border-radius: 50%; background: #fff; border: 2px solid #2563eb;
            transform: translate(-50%, -50%); pointer-events: none;
        }
        .playback-time-row { display: flex; justify-content: space-between; font-size: 0.72rem; color: #64748b; margin-top: 6px; }
        .playback-stats-row { display: flex; gap: 16px; margin: 10px 0; font-size: 0.8rem; color: #334155; }
        .playback-toolbar { display: flex; flex-wrap: wrap; align-items: center; justify-content: space-between; gap: 10px; }
        .playback-transport { display: flex; gap: 6px; align-items: center; }
        .playback-btn {
            width: 36px; height: 36px; border-radius: 10px; border: 1px solid #e2e8f0;
            background: #fff; color: #334155; cursor: pointer;
        }
        .playback-btn--primary { background: #2563eb; border-color: #2563eb; color: #fff; width: 42px; height: 42px; }
        .playback-speed-group { display: flex; align-items: center; gap: 8px; }
        .playback-speed-pills { display: flex; gap: 4px; }
        .speed-btn {
            border: 1px solid #e2e8f0; background: #fff; border-radius: 8px;
            padding: 4px 8px; font-size: 0.72rem; cursor: pointer;
        }
        .speed-btn.active { background: #2563eb; color: #fff; border-color: #2563eb; }
        .tc-map-area.tc-playback-open > .route-trip-bar--footer:not(.is-user-positioned) { bottom: 130px; }
        .tc-map-area.tc-playback-open .tc-map-route-footer { margin-bottom: 120px; }
    </style>
@endpush

@section('content')
    @php
        $ui = $trackingUi ?? [];
        $hubPerms = $ui['hub'] ?? [];
        $tabPerms = $ui['sidebar_tabs'] ?? [];
        $mapCtl = $ui['map_controls'] ?? [];
        $firstSidebarTab = collect(['objects', 'events', 'places', 'history'])
            ->first(fn (string $tab) => ! empty($tabPerms[$tab])) ?? null;
    @endphp
    @if(! empty($ui['alert_controls']))
    @push('tracking-topbar-actions')
    <div class="tracking-topbar__alert-controls">
        <button type="button" class="tracking-topbar__alert-btn on" id="tcSoundToggle"
                title="{{ __('app.tracking.alert_sound') }}" aria-label="{{ __('app.tracking.alert_sound') }}" aria-pressed="true">
            <i class="fas fa-volume-high"></i>
        </button>
        <button type="button" class="tracking-topbar__alert-btn" id="tcDesktopToggle"
                title="{{ __('app.tracking.alert_desktop') }}" aria-label="{{ __('app.tracking.alert_desktop') }}" aria-pressed="false">
            <i class="fas fa-bell"></i>
        </button>
    </div>
    @endpush
    @endif
    <script>
    (function () {
        var root = document.documentElement;
        var body = document.body;
        root.classList.add('tc-module-nav-open');
        root.classList.remove('tc-module-nav-collapsed');
        if (body) {
            body.classList.add('tc-module-nav-open');
            body.classList.remove('tc-module-nav-collapsed');
        }
        try { localStorage.removeItem('tcModuleNavOpen'); } catch (e) { /* ignore */ }
    }());
    </script>
    <div class="tc-app tc-app--panel-open{{ ! empty($ui['map_only']) ? ' tc-app--map-only' : '' }}">
        @if(! empty($ui['iconbar']) || ! empty($ui['panel_toggle']))
        <div class="tc-workspace-nav" id="tcWorkspaceNav" role="navigation" aria-label="{{ __('app.tracking.hub_nav') }}">
            <button type="button" class="tc-workspace-nav__panel-btn active" id="tcPanelToggle"
                    aria-label="{{ __('app.tracking.tab_objects') }}" title="{{ __('app.tracking.tab_objects') }}"
                    aria-expanded="true" aria-controls="tcPanel">
                <i class="fas fa-list-ul"></i>
                @if(! empty($ui['panel_toggle']))
                <span class="tc-toggle-badge" id="tcToggleBadge" hidden>0</span>
                @endif
            </button>
            @if(! empty($ui['iconbar']))
            <div class="tc-workspace-nav__track">
                <div class="tc-workspace-nav__links" id="tcIconbarLinks">
                    @include('tracking.partials.workspace-nav-links', [
                        'hubRoutes' => $hubRoutes ?? [],
                        'trackingUi' => $ui,
                        'moduleEmbed' => false,
                        'activeHubKey' => 'live',
                    ])
                </div>
            </div>
            <div class="tc-workspace-nav__actions">
                <button type="button" class="tc-workspace-nav__collapse-btn" id="tcNavClose"
                        aria-label="{{ __('app.tracking.nav_collapse') }}" title="{{ __('app.tracking.nav_collapse') }}">
                    <i class="fas fa-chevron-up"></i>
                </button>
            </div>
            @endif
        </div>
        <div class="tc-workspace-nav__reveal" id="tcNavReveal">
            <button type="button" class="tc-workspace-nav__reveal-btn" id="tcNavRevealBtn">
                <i class="fas fa-chevron-down"></i><span>{{ __('app.tracking.nav_show') }}</span>
            </button>
        </div>
        {{-- Legacy hooks for scripts --}}
        <div class="tc-iconbar" id="tcIconbar" hidden aria-hidden="true">
            <button type="button" class="tc-nav-toggle" id="tcNavToggle" tabindex="-1"></button>
        </div>
        @endif

        <div class="tc-main{{ ! empty($ui['panel_toggle']) ? ' tc-main--drawer' : '' }}">
            @if(! empty($ui['panel_toggle']))
            <div class="tc-panel-backdrop" id="tcPanelBackdrop"></div>
            @endif
            @if(! empty($ui['sidebar']))
            <aside class="tc-panel tc-panel--open" id="tcPanel">
                <div class="tc-tabs" role="tablist">
                    @if(! empty($tabPerms['objects']))
                    <button type="button" class="tc-tab{{ $firstSidebarTab === 'objects' ? ' active' : '' }}" data-tab="objects">{{ __('app.tracking.tab_objects') }}</button>
                    @endif
                    @if(! empty($tabPerms['events']))
                    <button type="button" class="tc-tab{{ $firstSidebarTab === 'events' ? ' active' : '' }}" data-tab="events">{{ __('app.tracking.events_nav') }}<span class="tc-tab-badge" id="tcEventsBadge" hidden>0</span></button>
                    @endif
                    @if(! empty($tabPerms['places']))
                    <button type="button" class="tc-tab{{ $firstSidebarTab === 'places' ? ' active' : '' }}" data-tab="places">{{ __('app.tracking.tab_places') }}</button>
                    @endif
                    @if(! empty($tabPerms['history']))
                    <button type="button" class="tc-tab{{ $firstSidebarTab === 'history' ? ' active' : '' }}" data-tab="history">{{ __('app.tracking.history_link') }}</button>
                    @endif
                </div>

                @if(! empty($tabPerms['objects']))
                <div class="tc-tab-body{{ $firstSidebarTab === 'objects' ? ' active' : '' }}" data-tab-body="objects">
                    <div class="tc-tab-head">
                        @if(! empty($ui['vehicle_search']))
                        <input type="search" id="tcSearch" class="form-control form-control-sm"
                               placeholder="{{ __('app.tracking.search_vehicles') }}" autocomplete="off">
                        @endif
                        <div class="tc-filter-chips" id="tcChips">
                            <button type="button" class="tc-chip active" data-filter="all">{{ __('app.tracking.filter_all') }} <span class="tc-chip-count" data-count="all">0</span></button>
                            <button type="button" class="tc-chip" data-filter="moving">{{ __('app.tracking.filter_moving') }} <span class="tc-chip-count" data-count="moving">0</span></button>
                            <button type="button" class="tc-chip" data-filter="stopped">{{ __('app.tracking.filter_stopped') }} <span class="tc-chip-count" data-count="stopped">0</span></button>
                            <button type="button" class="tc-chip" data-filter="offline">{{ __('app.tracking.filter_offline') }} <span class="tc-chip-count" data-count="offline">0</span></button>
                        </div>
                    </div>
                    <div class="tc-list-head">
                        <span class="tc-col" title="{{ __('app.tracking.col_show') }}"><i class="fas fa-eye"></i></span>
                        <span class="tc-head-label">{{ __('app.tracking.object') }}</span>
                    </div>
                    <div class="tc-row tc-allrow">
                        <input type="checkbox" id="tcCheckAll" class="form-check-input tc-check" checked title="{{ __('app.tracking.col_show') }}">
                        <span class="tc-row-info"><span class="tc-row-title">{{ __('app.tracking.filter_all') }} <span id="tcAllCount" class="text-muted"></span></span></span>
                    </div>
                    <div class="tc-list" id="tcVehicleList"></div>
                </div>
                @endif

                @if(! empty($tabPerms['events']))
                <div class="tc-tab-body{{ $firstSidebarTab === 'events' ? ' active' : '' }}" data-tab-body="events">
                    <div class="tc-tab-head">
                        <button type="button" class="btn btn-sm btn-outline-primary w-100" id="tcEventsReload">
                            <i class="fas fa-sync-alt me-1"></i>{{ __('app.tracking.refresh') }}
                        </button>
                    </div>
                    <div class="tc-list" id="tcEventsList">
                        <div class="tc-empty">{{ __('app.tracking.refresh') }}…</div>
                    </div>
                </div>
                @endif

                @if(! empty($tabPerms['places']))
                <div class="tc-tab-body{{ $firstSidebarTab === 'places' ? ' active' : '' }}" data-tab-body="places">
                    <div class="tc-tab-head">
                        <button type="button" class="btn btn-sm btn-outline-primary w-100" id="tcPlacesReload">
                            <i class="fas fa-sync-alt me-1"></i>{{ __('app.tracking.refresh') }}
                        </button>
                    </div>
                    <div class="tc-list" id="tcPlacesList">
                        <div class="tc-empty">{{ __('app.tracking.refresh') }}…</div>
                    </div>
                </div>
                @endif

                @if(! empty($tabPerms['history']))
                <div class="tc-tab-body{{ $firstSidebarTab === 'history' ? ' active' : '' }}" data-tab-body="history">
                    <div class="tc-form tc-hist-form">
                        <label for="tcHistVehicle">{{ __('app.tracking.object') }}</label>
                        <select id="tcHistVehicle" class="form-select form-select-sm no-select2">
                            @foreach($vehicles as $vehicle)
                                <option value="{{ $vehicle['id'] }}">{{ $vehicle['title'] ?? ('#' . $vehicle['id']) }}</option>
                            @endforeach
                        </select>

                        <div id="tcHistVehicleLabel"></div>
                        <div id="tcHistDayNav"></div>

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
                        <div id="tcHistExport"></div>
                    </div>
                    <div id="tcHistSummary" class="tc-hist-summary" hidden></div>
                    <div id="tcHistResultsWrap" class="tc-hist-results-wrap" hidden>
                        <div class="tc-list-head">
                            <span class="tc-head-label">{{ __('app.tracking.trip_timeline') }}</span>
                        </div>
                        <div class="tc-list htt-list" id="tcHistResults"></div>
                    </div>
                </div>
                @endif
            </aside>
            @endif

            <div class="tc-map-wrap">
                <div class="tc-map-area tc-map-area--dark-panels" id="mapArea">
                    <div class="tc-map-overlay--start">
                        <div id="tcCompanyMapCard" class="tc-info-card tc-company-card is-open" hidden aria-live="polite">
                            <button type="button" class="tc-info-card__collapse" id="tcCompanyMapCardToggle" aria-expanded="true" aria-label="{{ __('app.tracking.company_map_collapse') }}" data-collapsed-label="{{ __('app.tracking.company_map_card_title') }}">
                                <i class="fas fa-chevron-down" aria-hidden="true"></i>
                            </button>
                            <div class="tc-info-card__body" id="tcCompanyMapCardBody">
                                <div class="tc-info-row" data-company-row="company_name">
                                    <div class="tc-info-row__content">
                                        <div class="tc-info-row__labels">
                                            <span class="tc-info-row__label-en">{{ __('app.tracking.company_map_label_company_name') }}</span>
                                            <span class="tc-info-row__label-ar" lang="ar">{{ __('app.tracking.company_map_label_company_name_ar') }}</span>
                                        </div>
                                        <div class="tc-info-row__value" data-company-value="company_name" dir="auto">—</div>
                                    </div>
                                    <div class="tc-info-row__icon"><i class="fas fa-building" aria-hidden="true"></i></div>
                                </div>
                                <div class="tc-info-row" data-company-row="company_number">
                                    <div class="tc-info-row__content">
                                        <div class="tc-info-row__labels">
                                            <span class="tc-info-row__label-en">{{ __('app.tracking.company_map_label_company_number') }}</span>
                                            <span class="tc-info-row__label-ar" lang="ar">{{ __('app.tracking.company_map_label_company_number_ar') }}</span>
                                        </div>
                                        <div class="tc-info-row__value" data-company-value="company_number" dir="ltr">—</div>
                                    </div>
                                    <div class="tc-info-row__icon"><i class="fas fa-hashtag" aria-hidden="true"></i></div>
                                </div>
                                <div class="tc-info-row" data-company-row="operation_card">
                                    <div class="tc-info-row__content">
                                        <div class="tc-info-row__labels">
                                            <span class="tc-info-row__label-en">{{ __('app.tracking.company_map_label_operation_card') }}</span>
                                            <span class="tc-info-row__label-ar" lang="ar">{{ __('app.tracking.company_map_label_operation_card_ar') }}</span>
                                        </div>
                                        <div class="tc-info-row__value" data-company-value="operation_card" dir="auto">—</div>
                                    </div>
                                    <div class="tc-info-row__icon"><i class="fas fa-id-card" aria-hidden="true"></i></div>
                                </div>
                                <div class="tc-info-row" data-company-row="bus_name">
                                    <div class="tc-info-row__content">
                                        <div class="tc-info-row__labels">
                                            <span class="tc-info-row__label-en">{{ __('app.tracking.company_map_label_vehicle_name', ['type' => __('app.forms.vehicle_type_car')]) }}</span>
                                            <span class="tc-info-row__label-ar" lang="ar">{{ __('app.tracking.company_map_label_vehicle_name_ar', ['type' => trans('app.forms.vehicle_type_car', [], 'ar')]) }}</span>
                                        </div>
                                        <div class="tc-info-row__value" data-company-value="bus_name" dir="auto">—</div>
                                    </div>
                                    <div class="tc-info-row__icon"><i class="fas fa-car" aria-hidden="true" data-company-icon="vehicle_name"></i></div>
                                </div>
                                <div class="tc-info-row" data-company-row="bus_plate">
                                    <div class="tc-info-row__content">
                                        <div class="tc-info-row__labels">
                                            <span class="tc-info-row__label-en">{{ __('app.tracking.company_map_label_vehicle_plate', ['type' => __('app.forms.vehicle_type_car')]) }}</span>
                                            <span class="tc-info-row__label-ar" lang="ar">{{ __('app.tracking.company_map_label_vehicle_plate_ar', ['type' => trans('app.forms.vehicle_type_car', [], 'ar')]) }}</span>
                                        </div>
                                        <div class="tc-info-row__value" data-company-value="bus_plate" dir="auto">—</div>
                                    </div>
                                    <div class="tc-info-row__icon"><i class="fas fa-tag" aria-hidden="true"></i></div>
                                </div>
                                <div class="tc-info-row" data-company-row="support">
                                    <div class="tc-info-row__content">
                                        <div class="tc-info-row__labels">
                                            <span class="tc-info-row__label-en">{{ __('app.tracking.company_map_label_support') }}</span>
                                            <span class="tc-info-row__label-ar" lang="ar">{{ __('app.tracking.company_map_label_support_ar') }}</span>
                                        </div>
                                        <div class="tc-info-row__value" data-company-value="support" dir="ltr">—</div>
                                    </div>
                                    <div class="tc-info-row__icon"><i class="fas fa-phone" aria-hidden="true"></i></div>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div id="tcMap" aria-label="{{ __('app.tracking.live_map_aria') }}"></div>
                    @if(! empty($ui['route_progress']))
                    <div id="routeTripProgressBar" class="route-trip-bar route-trip-bar--footer" hidden aria-live="polite"></div>
                    @endif
                    <div id="tcVehicleMapPopupHost" aria-live="polite" aria-label="Vehicle details"></div>
                    <div id="tcLegend" class="tc-legend"></div>
                    <div class="map-history-load-banner" id="tcHistoryLoadBanner" hidden aria-live="polite">
                        <div class="map-history-load-banner__item" data-load="route" data-state="idle">
                            <span class="map-history-load-banner__icon"><i class="fas fa-route" aria-hidden="true"></i></span>
                            <span class="map-history-load-banner__label">{{ __('app.map.loading_route') }}</span>
                        </div>
                        <div class="map-history-load-banner__item" data-load="stats" data-state="idle">
                            <span class="map-history-load-banner__icon"><i class="fas fa-chart-line" aria-hidden="true"></i></span>
                            <span class="map-history-load-banner__label">{{ __('app.map.loading_statistics') }}</span>
                        </div>
                        <div class="map-history-load-banner__item" data-load="stops" data-state="idle">
                            <span class="map-history-load-banner__icon"><i class="fas fa-parking" aria-hidden="true"></i></span>
                            <span class="map-history-load-banner__label">{{ __('app.map.loading_stops') }}</span>
                        </div>
                        <div class="map-history-load-banner__item" data-load="events" data-state="idle">
                            <span class="map-history-load-banner__icon"><i class="fas fa-bolt" aria-hidden="true"></i></span>
                            <span class="map-history-load-banner__label">{{ __('app.map.loading_events') }}</span>
                        </div>
                        <div class="map-history-load-banner__item" data-load="timeline" data-state="idle">
                            <span class="map-history-load-banner__icon"><i class="fas fa-stream" aria-hidden="true"></i></span>
                            <span class="map-history-load-banner__label">{{ __('app.map.loading_timeline') }}</span>
                        </div>
                    </div>
                    <div class="playback-panel" id="tcPlaybackPanel">
                        <div class="playback-panel__inner">
                            <header class="playback-header">
                                <div class="playback-header__left">
                                    <span class="playback-badge"><i class="fas fa-route"></i></span>
                                    <div>
                                        <h6 class="playback-title">{{ __('app.map.route_playback') }}</h6>
                                        <p class="playback-subtitle" id="tcPlaybackSubtitle">{{ __('app.map.load_history') }}</p>
                                    </div>
                                </div>
                                <button type="button" class="playback-close" id="tcPlaybackClose" aria-label="{{ __('app.map.playback_close') }}">
                                    <i class="fas fa-times"></i>
                                </button>
                            </header>
                            <div class="playback-timeline">
                                <div class="playback-progress" id="tcPlaybackProgress" role="slider" aria-label="{{ __('app.map.playback_position') }}">
                                    <div class="playback-progress-bar" id="tcPlaybackProgressBar"></div>
                                    <div class="playback-progress-thumb" id="tcPlaybackProgressThumb"></div>
                                </div>
                                <div class="playback-time-row">
                                    <span id="tcPlaybackTimeCurrent">00:00</span>
                                    <span id="tcPlaybackTimeTotal">00:00</span>
                                </div>
                            </div>
                            <div class="playback-stats-row">
                                <div class="playback-stat"><i class="fas fa-tachometer-alt"></i> <span><span id="tcPbLiveSpeed">0</span> km/h</span></div>
                                <div class="playback-stat"><i class="fas fa-location-dot"></i> <span><span id="tcPbPointIndex">0</span> / <span id="tcPbPointTotal">0</span></span></div>
                            </div>
                            <div class="playback-toolbar">
                                <div class="playback-transport">
                                    <button type="button" class="playback-btn playback-btn--ghost" id="tcPbStepBack" title="{{ __('app.map.step_back') }}"><i class="fas fa-step-backward"></i></button>
                                    <button type="button" class="playback-btn playback-btn--ghost" id="tcPbRewind" title="{{ __('app.map.restart') }}"><i class="fas fa-rotate-left"></i></button>
                                    <button type="button" class="playback-btn playback-btn--primary" id="tcPbPlayPause" title="{{ __('app.map.play') }}"><i class="fas fa-play" id="tcPbPlayPauseIcon"></i></button>
                                    <button type="button" class="playback-btn playback-btn--ghost" id="tcPbStepForward" title="{{ __('app.map.step_forward') }}"><i class="fas fa-step-forward"></i></button>
                                    <button type="button" class="playback-btn playback-btn--ghost" id="tcPbStop" title="{{ __('app.map.stop') }}"><i class="fas fa-stop"></i></button>
                                </div>
                                <div class="playback-speed-group">
                                    <span class="playback-speed-label">{{ __('app.map.speed_label') }}</span>
                                    <div class="playback-speed-pills">
                                        <button type="button" class="speed-btn active" data-tc-speed="1">1×</button>
                                        <button type="button" class="speed-btn" data-tc-speed="2">2×</button>
                                        <button type="button" class="speed-btn" data-tc-speed="4">4×</button>
                                        <button type="button" class="speed-btn" data-tc-speed="8">8×</button>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div id="tcMapError" class="tc-map-error" hidden>
                        <div class="alert alert-danger mb-0">
                            <span data-tc-error-text>{{ __('app.map.loading_map_failed') }}</span>
                        </div>
                    </div>
                    <div class="tc-map-overlay--end">
                        @if(! empty($ui['driver']))
                        <div id="tcDriverMapCard" class="tc-info-card tc-driver-card is-open" hidden aria-live="polite">
                            <button type="button" class="tc-info-card__collapse" id="tcDriverMapCardToggle" aria-expanded="true" aria-label="{{ __('app.tracking.driver_map_collapse') }}" data-collapsed-label="{{ __('app.tracking.driver_map_card_title') }}">
                                <i class="fas fa-chevron-down" aria-hidden="true"></i>
                            </button>
                            <div class="tc-info-card__body" id="tcDriverMapCardBody">
                                <div class="tc-driver-card__layout" id="tcDriverLayout">
                                    <div class="tc-driver-card__fields">
                                        <div class="tc-info-row" id="tcDriverNameRow">
                                            <div class="tc-info-row__content">
                                                <div class="tc-info-row__labels">
                                                    <span class="tc-info-row__label-en">{{ __('app.tracking.driver_map_label_name') }}</span>
                                                    <span class="tc-info-row__label-ar" lang="ar">{{ __('app.tracking.driver_map_label_name_ar') }}</span>
                                                </div>
                                                <div class="tc-info-row__value" id="tcDriverName" dir="auto">—</div>
                                            </div>
                                        </div>
                                        <div class="tc-info-row" id="tcDriverPhoneRow" hidden>
                                            <div class="tc-info-row__content">
                                                <div class="tc-info-row__labels">
                                                    <span class="tc-info-row__label-en">{{ __('app.tracking.driver_map_label_contact') }}</span>
                                                    <span class="tc-info-row__label-ar" lang="ar">{{ __('app.tracking.driver_map_label_contact_ar') }}</span>
                                                </div>
                                                <div class="tc-info-row__value tc-info-row__value--phone">
                                                    <a id="tcDriverPhone" href="#" dir="ltr">—</a>
                                                </div>
                                            </div>
                                            <div class="tc-info-row__icon"><i class="fas fa-phone" aria-hidden="true"></i></div>
                                        </div>
                                        <div class="tc-info-row" id="tcDriverEmailRow" hidden>
                                            <div class="tc-info-row__content">
                                                <div class="tc-info-row__labels">
                                                    <span class="tc-info-row__label-en">{{ __('app.tracking.driver_map_label_email') }}</span>
                                                    <span class="tc-info-row__label-ar" lang="ar">{{ __('app.tracking.driver_map_label_email_ar') }}</span>
                                                </div>
                                                <div class="tc-info-row__value">
                                                    <a id="tcDriverEmail" href="#" dir="ltr">—</a>
                                                </div>
                                            </div>
                                            <div class="tc-info-row__icon"><i class="fas fa-envelope" aria-hidden="true"></i></div>
                                        </div>
                                    </div>
                                    <div class="tc-driver-card__photo-wrap">
                                        <img id="tcDriverPhoto" class="tc-driver-card__photo" alt="" hidden>
                                        <div id="tcDriverPhotoDefault" class="tc-driver-card__photo tc-driver-card__photo--default" aria-hidden="true">
                                            <i class="fas fa-user"></i>
                                        </div>
                                    </div>
                                </div>
                                <div class="tc-driver-card__empty" id="tcDriverEmpty" hidden>{{ __('app.tracking.driver_map_none') }}</div>
                            </div>
                        </div>
                        @endif
                    @if(! empty($ui['show_map_controls']))
                    <div class="tc-map-controls">
                        @if(! empty($mapCtl['zoom']))
                        <div class="tc-ctrl-group">
                            <button type="button" class="btn btn-light" id="tcZoomIn" title="{{ __('app.tracking.zoom_in') }}">
                                <i class="fas fa-plus"></i>
                            </button>
                            <button type="button" class="btn btn-light" id="tcZoomOut" title="{{ __('app.tracking.zoom_out') }}">
                                <i class="fas fa-minus"></i>
                            </button>
                        </div>
                        @endif
                        @if(! empty($mapCtl['fit']))
                        <button type="button" class="btn btn-light" id="tcFit" title="{{ __('app.tracking.fit_all') }}">
                            <i class="fas fa-compress-arrows-alt"></i>
                        </button>
                        @endif
                        @if(! empty($mapCtl['follow']))
                        <button type="button" class="btn btn-light" id="tcFollow" title="{{ __('app.tracking.follow_vehicle') }}" aria-pressed="false">
                            <i class="fas fa-crosshairs"></i>
                        </button>
                        @endif
                        @if(! empty($mapCtl['refresh']))
                        <button type="button" class="btn btn-light" id="tcRefresh" title="{{ __('app.tracking.refresh') }}">
                            <i class="fas fa-sync-alt"></i>
                        </button>
                        @endif
                        @if(! empty($mapCtl['traffic']))
                        <button type="button" class="btn btn-light" id="tcTraffic" title="{{ __('app.tracking.layer_traffic') }}" aria-pressed="false">
                            <i class="fas fa-traffic-light"></i>
                        </button>
                        @endif
                        @if(! empty($mapCtl['layers']))
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
                        @endif
                        @if(! empty($mapCtl['capture']))
                        <button type="button" class="btn btn-light" id="tcCapture" title="{{ __('app.tracking.capture_image') }}">
                            <i class="fas fa-camera"></i>
                        </button>
                        @endif
                        @if(! empty($tabPerms['history']))
                        <button type="button" class="btn btn-light" data-tc-playback title="{{ __('app.map.open_playback') }}" disabled>
                            <i class="fas fa-play"></i>
                        </button>
                        @endif
                    </div>
                    @endif
                    </div>
                </div>

                @if(! empty($ui['vehicle_footer']))
                <div class="tc-footer" id="tcFooter" hidden>
                    <div class="tc-footer-resize" id="tcFooterResize" role="separator" aria-orientation="horizontal" aria-label="{{ __('app.tracking.resize_panel') }}"></div>
                    <div class="tc-footer-head">
                        <div class="tc-footer-tabs">
                            <button type="button" class="tc-ftab active" data-ftab="data">{{ __('app.tracking.ft_data') }}</button>
                            <button type="button" class="tc-ftab" data-ftab="graph">{{ __('app.tracking.ft_graph') }}</button>
                            <button type="button" class="tc-ftab" data-ftab="messages">{{ __('app.tracking.ft_messages') }}</button>
                        </div>
                        <span class="tc-footer-title" id="tcFooterTitle"></span>
                        <button type="button" class="tc-footer-close" id="tcFooterClose" aria-label="{{ __('app.tracking.hide') }}">
                            <i class="fas fa-times"></i>
                        </button>
                    </div>
                    <div class="tc-footer-body">
                        <div class="tc-fbody active" data-fbody="data" id="tcFooterData"></div>
                        <div class="tc-fbody" data-fbody="graph"><canvas id="tcSpeedChart"></canvas></div>
                        <div class="tc-fbody" data-fbody="messages" id="tcFooterMessages"></div>
                    </div>
                </div>
                @endif
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
    <script>document.body.classList.add('gt-page-active'@if(! empty($ui['map_only'])), 'tc-live-map-only'@endif);</script>
    <script>
        window.TRACCAR_UI_CONFIG = {
            panel: @json($panel),
            userId: @json(auth()->id()),
            alertPollIntervalMs: @json((int) config('tracking.alert_poll_interval_ms', 30000)),
            alertPollIntervalConnectedMs: @json((int) config('tracking.alert_poll_interval_connected_ms', 60000)),
            googleMapsKey: @json(config('services.google.maps_key')),
            googleMapsMapId: @json(config('services.google.maps_map_id')),
            liveJsonUrl: @json(route($routes['liveJson'])),
            historyJsonUrl: @json(route($routes['historyJson'])),
            historyPointsJsonUrl: @json(Route::has($routes['historyPoints'] ?? '') ? route($routes['historyPoints']) : null),
            historyAnalyticsJsonUrl: @json(Route::has($routes['historyAnalytics'] ?? '') ? route($routes['historyAnalytics']) : null),
            historyExportUrl: @json(Route::has($routes['historyExport'] ?? '') ? route($routes['historyExport']) : null),
            historyGeocodeUrl: @json(Route::has($routes['historyGeocode'] ?? '') ? route($routes['historyGeocode']) : null),
            historyWorkerUrl: @json(asset('js/history-map-worker.js')),
            eventsJsonUrl: @json(Route::has($routes['eventsJson']) ? route($routes['eventsJson']) : null),
            geofencesJsonUrl: @json(Route::has($routes['geofencesJson']) ? route($routes['geofencesJson']) : null),
            devicePanelUrl: @json(Route::has($routes['devicePanel']) ? route($routes['devicePanel']) : null),
            deviceMileageUrl: @json(Route::has($routes['deviceMileage']) ? route($routes['deviceMileage']) : null),
            completeTripUrl: @json(Route::has($routes['completeTrip']) ? route($routes['completeTrip']) : null),
            startNewTripUrl: @json(Route::has($routes['startNewTrip']) ? route($routes['startNewTrip']) : null),
            restartTripUrl: @json(Route::has($routes['restartTrip'] ?? '') ? route($routes['restartTrip']) : null),
            routeGuidanceUrl: @json(Route::has($routes['routeGuidance']) ? route($routes['routeGuidance']) : null),
            manageRoutesUrl: @json($manageRoutesUrl ?? null),
            commandsSendUrl: @json(
                ($trackingUi['hub']['commands'] ?? false) && Route::has($routes['commandsSend'])
                    ? route($routes['commandsSend'])
                    : null
            ),
            commandTypes: @json(\App\Services\Tracking\CommandService::typeLabels()),
            liveUrl: @json(route($routes['live'])),
            deviceEditUrl: @json($deviceEditUrlTemplate),
            csrfToken: @json(csrf_token()),
            pollIntervalMs: @json((int) config('tracking.live_poll_interval_ms', 10000)),
            pollIntervalConnectedMs: @json((int) config('tracking.live_poll_interval_connected_ms', 30000)),
            roadsSnapEnabled: @json((bool) config('tracking.roads_snap_enabled', false)),
            reverbStaleMs: @json(max(45000, (int) config('traccar.broadcast_interval_seconds', 30) * 1500)),
            animDurationMs: 1200,
            stateColors: @json($stateColors),
            appTimezone: @json(config('app.timezone')),
            vehicles: @json($vehicles),
            trackingUi: @json($trackingUi ?? []),
            companyMapCard: @json($companyMapCard ?? ['enabled' => false]),
            startIconUrl: @json(asset('images/map/marker-start.svg')),
            endIconUrl: @json(asset('images/map/marker-end.svg')),
            i18n: {
                noVehicles: @json(__('app.tracking.no_vehicles')),
                selectVehicle: @json(__('app.tracking.select_vehicle')),
                loadFailed: @json(__('app.tracking.load_failed')),
                historyFallback: @json(__('app.map.history_fallback_selected_period', ['count' => '…'])),
                historyPermissionDenied: @json(__('app.tracking.history_permission_denied')),
                eventsPermissionDenied: @json(__('app.tracking.events_permission_denied')),
                geofencePermissionDenied: @json(__('app.tracking.geofence_permission_denied')),
                accessDeniedTitle: @json(__('app.errors.403_title')),
                ok: @json(__('app.common.ok')),
                panelLoadFailed: @json(__('app.tracking.panel_load_failed')),
                loadingMapFailed: @json(__('app.map.loading_map_failed')),
                mapApiKeyMissing: @json(__('app.map.map_api_key_missing')),
                kmhUnit: @json(__('app.map.kmh_unit')),
                ignitionOn: @json(__('app.map.ignition_on')),
                ignitionOff: @json(__('app.map.ignition_off')),
                routeStart: @json(__('app.map.route_start')),
                routeEnd: @json(__('app.map.route_end')),
                loadingRoute: @json(__('app.map.loading_route')),
                loadingStatistics: @json(__('app.map.loading_statistics')),
                loadingTimeline: @json(__('app.map.loading_timeline')),
                loadingEvents: @json(__('app.map.loading_events')),
                loadingStops: @json(__('app.map.loading_stops')),
                routeLoaded: @json(__('app.map.route_loaded')),
                statisticsLoaded: @json(__('app.map.statistics_loaded')),
                timelineLoaded: @json(__('app.map.timeline_loaded')),
                eventsLoaded: @json(__('app.map.events_loaded')),
                stopsLoaded: @json(__('app.map.stops_loaded')),
                playRoute: @json(__('app.map.play_route')),
                loadHistoryPlayback: @json(__('app.map.load_history')),
                loadHistoryFirst: @json(__('app.map.load_history')),
                playbackFinished: @json(__('app.map.playback_finished')),
                noPlaybackData: @json(__('app.map.no_playback_data')),
                noData: @json(__('app.tracking.no_data')),
                noStops: @json(__('app.tracking.no_stops')),
                parkingStops: @json(__('app.tracking.parking_stops')),
                lblObject: @json(__('app.tracking.lbl_object')),
                lblPosition: @json(__('app.tracking.lbl_position')),
                lblAngle: @json(__('app.tracking.lbl_angle')),
                lblArrived: @json(__('app.tracking.lbl_arrived')),
                lblDeparted: @json(__('app.tracking.lbl_departed')),
                lblDuration: @json(__('app.tracking.lbl_duration')),
                lblAddress: @json(__('app.tracking.lbl_address')),
                addressLoading: @json(__('app.map.address_loading')),
                addressUnavailable: @json(__('app.map.address_not_found')),
                statusParking: @json(__('app.map.timeline_parked')),
                statusIdle: @json(__('app.map.timeline_idle')),
                statusStopped: @json(__('app.map.status_stopped')),
                statusOffline: @json(__('app.map.status_offline')),
                historyMarkers: @json(__('app.tracking.history_status_markers')),
                colShow: @json(__('app.tracking.col_show')),
                colFollow: @json(__('app.tracking.col_follow')),
                ftData: @json(__('app.tracking.ft_data')),
                ftGraph: @json(__('app.tracking.ft_graph')),
                ftMessages: @json(__('app.tracking.ft_messages')),
                lblPlate: @json(__('app.tracking.lbl_plate')),
                lblPosition: @json(__('app.tracking.lbl_position')),
                lblAngle: @json(__('app.tracking.lbl_angle')),
                hide: @json(__('app.tracking.hide')),
                navPin: @json(__('app.tracking.nav_pin')),
                navUnpin: @json(__('app.tracking.nav_unpin')),
                navShow: @json(__('app.tracking.nav_show')),
                navCollapse: @json(__('app.tracking.nav_collapse')),
                companyMapExpand: @json(__('app.tracking.company_map_expand')),
                companyMapCollapse: @json(__('app.tracking.company_map_collapse')),
                companyMapVehicleName: @json(__('app.tracking.company_map_label_vehicle_name')),
                companyMapVehicleNameAr: @json(__('app.tracking.company_map_label_vehicle_name_ar')),
                companyMapVehiclePlate: @json(__('app.tracking.company_map_label_vehicle_plate')),
                companyMapVehiclePlateAr: @json(__('app.tracking.company_map_label_vehicle_plate_ar')),
                vehicleTypeLabels: @json(\App\Support\VehicleIcons\VehicleIconLibrary::defaultTypeLabels()),
                vehicleTypeLabelsAr: @json(\App\Support\VehicleIcons\VehicleIconLibrary::defaultTypeLabels('ar')),
                driverMapExpand: @json(__('app.tracking.driver_map_expand')),
                driverMapCollapse: @json(__('app.tracking.driver_map_collapse')),
                driverMapNone: @json(__('app.tracking.driver_map_none')),
                callDriver: @json(__('app.map.call_driver')),
                lblStatus: @json(__('app.tracking.lbl_status')),
                lblSpeed: @json(__('app.tracking.lbl_speed')),
                lblAltitude: @json(__('app.tracking.lbl_altitude')),
                lblOdometer: @json(__('app.tracking.lbl_odometer')),
                lblEngine: @json(__('app.tracking.lbl_engine')),
                lblStatusDuration: @json(__('app.tracking.lbl_status_duration')),
                lblLastKnown: @json(__('app.admin.devices.last_known')),
                lblTimePosition: @json(__('app.tracking.lbl_time_position')),
                lblTimeServer: @json(__('app.tracking.lbl_time_server')),
                lblIgnition: @json(__('app.tracking.lbl_ignition')),
                secObjectControl: @json(__('app.tracking.sec_object_control')),
                secDailyStats: @json(__('app.tracking.sec_daily_stats')),
                secRecentEvents: @json(__('app.tracking.sec_recent_events')),
                statRouteLength: @json(__('app.tracking.stat_route_length')),
                statMoveDuration: @json(__('app.tracking.stat_move_duration')),
                statStopDuration: @json(__('app.tracking.stat_stop_duration')),
                totalIdleTime: @json(__('app.tracking.stat_idle_duration')),
                parkingTime: @json(__('app.tracking.stat_parking_duration')),
                statTopSpeed: @json(__('app.tracking.stat_top_speed')),
                statAvgSpeed: @json(__('app.tracking.stat_avg_speed')),
                cmdSend: @json(__('app.tracking.cmd_send')),
                cmdSent: @json(__('app.tracking.cmd_sent')),
                commandConfirmSend: @json(__('app.tracking.command_confirm_send')),
                commandCustomRequired: @json(__('app.tracking.command_custom_required')),
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
                routeRemaining: @json(__('app.routes.remaining')),
                routeEta: @json(__('app.routes.eta')),
                routeDuration: @json(__('app.routes.duration')),
                routeComplete: @json(__('app.routes.complete_trip')),
                routeStartNew: @json(__('app.routes.start_new_trip')),
                routeOffRoute: @json(__('app.routes.off_route_alert')),
                routeElapsed: @json(__('app.routes.elapsed')),
                routePlanned: @json(__('app.routes.planned')),
                routeCheckpointTotal: @json(__('app.routes.checkpoint_total')),
                routeMinAbbr: @json(__('app.routes.min_abbr')),
                routePending: @json(__('app.routes.pending')),
                routeToggleDetails: @json(__('app.routes.toggle_details')),
                routeProgressOffRoute: @json(__('app.routes.progress_off_route_zero')),
                routeProgressFrozen: @json(__('app.routes.progress_frozen_off_route')),
                routeProgressStale: @json(__('app.routes.progress_stale_position')),
                routeWaitingForStart: @json(__('app.routes.trip_waiting_for_start')),
                routeWaitingForStartHint: @json(__('app.routes.trip_waiting_for_start_hint')),
                routeManage: @json(__('app.routes.nav')),
                routeAssignedHint: @json(__('app.routes.index_subtitle')),
                routeProgressTitle: @json(__('app.routes.progress_title')),
                routeReachedStart: @json(__('app.routes.reached_start')),
                routeReachedCheckpoint: @json(__('app.routes.reached_checkpoint')),
                routeReachedDestination: @json(__('app.routes.reached_destination')),
                routeTraveled: @json(__('app.routes.distance_traveled')),
                routeCurrentSpeed: @json(__('app.routes.current_speed')),
                routeCurrentCheckpoint: @json(__('app.routes.current_checkpoint')),
                routeNextCheckpoint: @json(__('app.routes.next_checkpoint')),
                routeCurrentCity: @json(__('app.routes.current_city')),
                routeNextCity: @json(__('app.routes.next_city')),
                routeArrivalTime: @json(__('app.routes.arrival_time')),
                routeNavOnAssigned: @json(__('app.routes.nav_on_assigned')),
                routeNavJoining: @json(__('app.routes.nav_joining')),
                routeSuggestedRoute: @json(__('app.routes.suggested_route_to_destination')),
                routeNavRecalculated: @json(__('app.routes.nav_recalculated')),
                routeNavSlightDeviation: @json(__('app.routes.nav_slight_deviation')),
                routeNavDestinationReached: @json(__('app.routes.nav_destination_reached')),
                routeOffRouteBadge: @json(__('app.routes.off_route_badge')),
                dragPanel: @json(__('app.map.drag_panel_position')),
                resetPanelPosition: @json(__('app.map.reset_panel_position')),
            },
        };
    </script>
    <script src="{{ protected_js('app-datetime.js') }}"></script>
    @include('partials.google-maps-platform')
    <script src="{{ protected_js('builtin-map-icons.js') }}"></script>
    <script src="{{ protected_js('vehicle-marker.js') }}"></script>
    <script src="{{ protected_js('vehicle-motion.js') }}"></script>
    <script src="{{ protected_js('fleet-map-cluster.js') }}"></script>
    <script src="{{ protected_js('polyline-simplify.js') }}"></script>
    <script src="{{ protected_js('history-analytics.js') }}"></script>
    <script src="{{ protected_js('fleet-map-renderer.js') }}"></script>
    <script src="{{ protected_js('history-trip-timeline.js') }}"></script>
    <script src="{{ protected_js('history-map-processor.js') }}"></script>
    <script src="{{ protected_js('route-trip-progress.js') }}"></script>
    <script src="{{ protected_js('map-panel-position.js') }}"></script>
    <script src="{{ protected_js('vehicle-map-popup.js') }}"></script>
    <script src="{{ protected_js('tracking-traccar.js') }}"></script>
@endpush
