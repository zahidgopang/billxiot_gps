@include('tracking.partials.design-tokens')
<style>
    body.tracking-shell {
        background: var(--apple-bg-secondary);
        color: var(--apple-label);
        font-family: -apple-system, BlinkMacSystemFont, 'SF Pro Text', 'SF Pro Display', 'Helvetica Neue', sans-serif;
        letter-spacing: -0.018em;
        -webkit-font-smoothing: antialiased;
    }

    .gt-module-page {
        padding: 0.85rem 1rem 1.5rem;
        background: var(--apple-bg-secondary);
        min-height: calc(100vh - var(--tracking-topbar-height, 52px));
    }

    /* Shared /tracking workspace nav (same bar on Live + Maintenance/Reports/…) */
    .tc-workspace-nav {
        flex-shrink: 0;
        display: flex;
        align-items: center;
        gap: 0.35rem;
        min-height: 34px;
        padding: 0.25rem 0.5rem;
        background: var(--apple-bg-primary);
        border-bottom: 0.5px solid var(--apple-separator);
        z-index: 30;
        margin: 0 0 0.85rem;
        border-radius: 0;
    }
    .tc-workspace-nav--modules {
        margin-inline: -1rem;
        margin-top: -0.85rem;
        width: calc(100% + 2rem);
        max-width: none;
        box-sizing: border-box;
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
    .tc-workspace-nav__links a:hover { color: #1d1d1f; }
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
        text-decoration: none;
        cursor: pointer;
    }
    .tc-workspace-nav__panel-btn:hover {
        background: rgba(118, 118, 128, 0.18);
        color: #1d1d1f;
    }
    .tc-workspace-nav__actions {
        display: inline-flex;
        align-items: center;
        flex-shrink: 0;
        margin-inline-start: 0.15rem;
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
    }

    .gt-module-card {
        background: var(--apple-bg-card);
        border: 0.5px solid var(--apple-separator);
        border-radius: var(--tc-radius-lg);
        padding: 1rem 1.1rem;
        box-shadow: var(--tc-shadow-sm);
    }
    .gt-module-card h5 {
        font-size: 1.0625rem;
        font-weight: 600;
        letter-spacing: -0.022em;
        color: var(--apple-label);
    }

    .gt-vehicle-picker {
        max-height: 220px;
        overflow-y: auto;
        border: 0.5px solid var(--tc-border);
        border-radius: 10px;
        padding: 0.5rem 0.65rem;
        background: var(--apple-bg-group);
    }
    .gt-vehicle-picker label {
        display: flex;
        gap: 0.5rem;
        align-items: center;
        font-size: 0.8125rem;
        margin-bottom: 0.35rem;
        cursor: pointer;
        color: var(--apple-label);
    }

    #gtReportMap, #gtGeofenceMap {
        height: 320px;
        border-radius: 12px;
        background: var(--apple-bg-secondary);
        border: 0.5px solid var(--tc-border);
    }
    body.tracking-embed #gtGeofenceMap {
        height: min(52vh, 480px);
        min-height: 280px;
    }

    .gt-module-page .form-label {
        color: var(--apple-secondary);
        font-weight: 500;
        letter-spacing: -0.01em;
    }
    .gt-module-page .btn-primary {
        background: #007aff;
        border-color: #007aff;
    }
    .gt-module-page .btn-outline-secondary {
        border-color: var(--tc-border);
        color: #636366;
    }
    .gt-module-page .btn-outline-secondary:hover {
        background: var(--apple-fill);
        border-color: var(--tc-border);
        color: #1d1d1f;
    }
    .gt-module-page .table {
        --bs-table-bg: transparent;
    }
    .gt-module-page .table thead th {
        background: var(--apple-bg-group);
        color: var(--apple-secondary);
        font-size: 0.6875rem;
        font-weight: 600;
        text-transform: none;
        letter-spacing: -0.01em;
        border-bottom-color: var(--tc-border-soft);
    }
</style>
