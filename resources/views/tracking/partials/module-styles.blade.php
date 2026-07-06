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

    /* Apple segmented hub nav (Reports, Geofences, etc.) */
    .gt-hub-nav {
        margin-bottom: 0.85rem !important;
        padding: 0;
    }
    .gt-hub-nav .nav {
        display: inline-flex;
        flex-wrap: wrap;
        gap: 2px;
        padding: 2px;
        border-radius: 9px;
        background: var(--apple-bg-secondary);
    }
    .gt-hub-nav .nav-link {
        display: inline-flex;
        align-items: center;
        gap: 0.35rem;
        padding: 0.32rem 0.65rem;
        border-radius: 7px;
        border: none;
        color: #636366;
        font-size: 0.75rem;
        font-weight: 500;
        letter-spacing: -0.02em;
        line-height: 1.25;
        transition: background 0.2s ease, color 0.2s ease, box-shadow 0.2s ease;
    }
    .gt-hub-nav .nav-link:hover {
        color: #1d1d1f;
        background: rgba(118, 118, 128, 0.08);
    }
    .gt-hub-nav .nav-link.active {
        background: var(--apple-bg-primary);
        color: #1d1d1f;
        font-weight: 600;
        box-shadow: var(--tc-shadow-sm);
    }
    .gt-hub-nav .nav-link.active i {
        color: #007aff;
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
