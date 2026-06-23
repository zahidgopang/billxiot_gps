<!-- Premium Map Sidebar -->
<div class="filter-panel" id="filterPanel">
    <div class="sidebar-header">
        <a href="{{ ($isAdminMap ?? false) ? route(request()->routeIs('client.*') ? 'client.locations.index' : 'admin.locations.index') : route('user.devices.index') }}" class="sidebar-back-link" data-map-tour="sidebar-back">
            <i class="fas fa-arrow-left" aria-hidden="true"></i>
            <span>{{ ($isAdminMap ?? false) ? __('app.map.back_location_history') : __('app.map.back_devices') }}</span>
        </a>
        <button type="button" class="sidebar-close" id="closeSidebar" aria-label="{{ __('app.map.close_panel') }}">
            <i class="fas fa-times"></i>
        </button>
    </div>

    {{-- Date filter --}}
    <section class="sidebar-section sidebar-section--filter" data-map-tour="date-range">
        <h6 class="sidebar-section__title">
            <i class="fas fa-calendar-alt" aria-hidden="true"></i>
            {{ __('app.map.date_range') }}
        </h6>

        <div class="date-preset-grid" role="group" aria-label="{{ __('app.map.date_range') }}">
            <button type="button" class="date-preset-btn is-active" data-date-preset="24h">{{ __('app.map.preset_last_24_hours') }}</button>
            <button type="button" class="date-preset-btn" data-date-preset="today">{{ __('app.map.preset_today') }}</button>
            <button type="button" class="date-preset-btn" data-date-preset="yesterday">{{ __('app.map.preset_yesterday') }}</button>
            <button type="button" class="date-preset-btn" data-date-preset="7d">{{ __('app.map.preset_last_7_days') }}</button>
            <button type="button" class="date-preset-btn" data-date-preset="30d">{{ __('app.map.preset_last_30_days') }}</button>
        </div>

        <div class="date-fields-grid">
            <div class="date-field">
                <label class="date-field__label" for="dateFrom">{{ __('app.map.from_date') }}</label>
                <div class="date-field__input-wrap">
                    <i class="fas fa-calendar-day date-field__icon" aria-hidden="true"></i>
                    <input id="dateFrom" class="date-field__input no-select2" type="text"
                           placeholder="{{ __('app.map.date_placeholder') }}" readonly autocomplete="off" data-flatpickr-manual>
                </div>
            </div>
            <div class="date-field">
                <label class="date-field__label" for="dateTo">{{ __('app.map.to_date') }}</label>
                <div class="date-field__input-wrap">
                    <i class="fas fa-calendar-day date-field__icon" aria-hidden="true"></i>
                    <input id="dateTo" class="date-field__input no-select2" type="text"
                           placeholder="{{ __('app.map.date_placeholder') }}" readonly autocomplete="off" data-flatpickr-manual>
                </div>
            </div>
        </div>

        <button type="button" id="applyFilter" class="btn-search-history">
            <span class="search-btn__spinner" hidden aria-hidden="true"><i class="fas fa-circle-notch fa-spin"></i></span>
            <span class="search-btn__label"><i class="fas fa-search" aria-hidden="true"></i> {{ __('app.map.search') }}</span>
        </button>
        <p class="sidebar-hint">{{ __('app.map.date_hint_from_to') }}</p>
    </section>

    {{-- Device summary hero --}}
    <section class="device-hero-card" data-map-tour="device-summary">
        <div class="device-hero-card__top">
            <div class="device-hero-card__avatar" aria-hidden="true"><i class="fas fa-truck"></i></div>
            <div class="device-hero-card__meta">
                <strong class="device-hero-card__name">{{ $device->listPrimaryLabel() }}</strong>
                @if($plate = $device->listSecondaryLabel())
                    <span class="device-hero-card__plate"><x-admin.ltr>{{ $plate }}</x-admin.ltr></span>
                @endif
            </div>
            <span class="map-status-chip map-status-chip--offline" id="curStatus">{{ __('app.map.dash') }}</span>
        </div>
        <div class="device-hero-card__stats">
            <div class="device-stat">
                <span class="device-stat__label">{{ __('app.map.last_update') }}</span>
                <span class="device-stat__value" id="lastSeen">{{ __('app.map.dash') }}</span>
            </div>
            <div class="device-stat">
                <span class="device-stat__label">{{ __('app.map.imei_number') }}</span>
                <span class="device-stat__value device-stat__value--mono">{{ $device->imei }}</span>
            </div>
        </div>
    </section>

    <p class="sidebar-map-hint">
        <i class="fas fa-route" aria-hidden="true"></i>
        {{ __('app.map.route_summary_map_hint') }}
    </p>

    {{-- Collapsible panels --}}
    <details class="sidebar-accordion" open>
        <summary class="sidebar-accordion__summary">
            <i class="fas fa-tachometer-alt" aria-hidden="true"></i>
            {{ __('app.map.live_telemetry') }}
        </summary>
        <div class="sidebar-accordion__body">
            <div class="telemetry-grid">
                <div class="telemetry-cell"><span>{{ __('app.map.speed') }}</span><strong id="telemetrySpeed">{{ __('app.map.dash') }}</strong></div>
                <div class="telemetry-cell"><span>{{ __('app.map.heading') }}</span><strong id="telemetryHeading">{{ __('app.map.dash') }}</strong></div>
                <div class="telemetry-cell"><span>{{ __('app.map.battery') }}</span><strong id="telemetryBattery">{{ __('app.map.dash') }}</strong></div>
                <div class="telemetry-cell"><span>{{ __('app.map.ignition') }}</span><strong id="telemetryIgnition">{{ __('app.map.dash') }}</strong></div>
                <div class="telemetry-cell"><span>{{ __('app.map.gsm_signal') }}</span><strong id="telemetryGsm">{{ __('app.map.dash') }}</strong></div>
                <div class="telemetry-cell"><span>{{ __('app.map.satellites') }}</span><strong id="telemetrySatellites">{{ __('app.map.dash') }}</strong></div>
                <div class="telemetry-cell telemetry-cell--wide"><span>{{ __('app.map.odometer') }}</span><strong id="telemetryOdometer">{{ __('app.map.dash') }}</strong></div>
            </div>
        </div>
    </details>

    <details class="sidebar-accordion" data-map-tour="trip-events">
        <summary class="sidebar-accordion__summary">
            <i class="fas fa-list-ul" aria-hidden="true"></i>
            {{ __('app.map.trip_events') }}
            <span class="sidebar-accordion__badge" id="tripEventsCount">0</span>
        </summary>
        <div class="sidebar-accordion__body sidebar-accordion__body--flush">
            <div class="trip-events-list" id="tripEventsList">
                <div class="trip-event-empty">{{ __('app.map.load_history_stops') }}</div>
            </div>
        </div>
    </details>

    <details class="sidebar-accordion">
        <summary class="sidebar-accordion__summary">
            <i class="fas fa-file-export" aria-hidden="true"></i>
            {{ __('app.map.export') }}
        </summary>
        <div class="sidebar-accordion__body">
            <div class="sidebar-actions-stack">
                <button type="button" class="sidebar-action-btn" id="btnExportCsv"><i class="fas fa-file-csv"></i> {{ __('app.map.export_csv') }}</button>
                <button type="button" class="sidebar-action-btn" id="btnExportGpx"><i class="fas fa-route"></i> {{ __('app.map.export_gpx') }}</button>
            </div>
        </div>
    </details>

    <details class="sidebar-accordion" data-map-tour="geofence-list">
        <summary class="sidebar-accordion__summary">
            <i class="fas fa-draw-polygon" aria-hidden="true"></i>
            {{ __('app.map.geofences') }}
        </summary>
        <div class="sidebar-accordion__body">
            <div class="geofence-list" id="geofenceList">
                <div class="sidebar-loading"><i class="fas fa-spinner fa-spin"></i> {{ __('app.map.loading_geofences') }}</div>
            </div>
        </div>
    </details>

    <details class="sidebar-accordion">
        <summary class="sidebar-accordion__summary">
            <i class="fas fa-map-marker-alt" aria-hidden="true"></i>
            {{ __('app.map.address_lookup') }}
        </summary>
        <div class="sidebar-accordion__body">
            <button type="button" id="reverseBtn" class="sidebar-action-btn sidebar-action-btn--primary">
                <i class="fas fa-search-location"></i> {{ __('app.map.get_address') }}
            </button>
            <div id="addressBox" class="address-result" role="status" aria-live="polite"></div>
        </div>
    </details>
</div>

<style>
    .filter-panel {
        width: 340px;
        position: fixed;
        top: 70px;
        left: 0;
        height: calc(100vh - 70px);
        background: linear-gradient(180deg, #0f172a 0%, #1e293b 100%);
        border-inline-end: 1px solid rgba(255, 255, 255, 0.08);
        box-shadow: 8px 0 32px rgba(0, 0, 0, 0.25);
        padding: 1rem 1rem 2rem;
        overflow-x: hidden;
        overflow-y: auto;
        transition: transform 0.35s ease;
        z-index: 1050;
        box-sizing: border-box;
        scrollbar-width: thin;
        scrollbar-color: rgba(255,255,255,0.2) transparent;
    }

    html[dir="ltr"] .filter-panel#filterPanel:not(.show) { transform: translateX(-100%); }
    html[dir="rtl"] .filter-panel#filterPanel {
        left: auto !important;
        right: 0 !important;
        border-inline-end: none;
        border-inline-start: 1px solid rgba(255, 255, 255, 0.08);
        box-shadow: -8px 0 32px rgba(0, 0, 0, 0.25);
    }
    html[dir="rtl"] .filter-panel#filterPanel:not(.show) { transform: translateX(100%) !important; }
    .filter-panel#filterPanel.show { transform: translateX(0) !important; }

    .sidebar-header {
        display: flex;
        justify-content: space-between;
        align-items: center;
        gap: 10px;
        margin-bottom: 1rem;
        padding-bottom: 0.75rem;
        border-bottom: 1px solid rgba(255, 255, 255, 0.08);
    }

    .sidebar-back-link {
        display: inline-flex;
        align-items: center;
        gap: 8px;
        font-size: 0.78rem;
        font-weight: 600;
        color: rgba(255, 255, 255, 0.85);
        text-decoration: none;
    }
    .sidebar-back-link:hover { color: #fff; }

    .sidebar-close {
        width: 34px;
        height: 34px;
        border-radius: 10px;
        background: rgba(255, 255, 255, 0.08);
        border: 1px solid rgba(255, 255, 255, 0.12);
        color: #fff;
        display: none;
        align-items: center;
        justify-content: center;
        cursor: pointer;
    }

    .sidebar-section { margin-bottom: 1rem; }
    .sidebar-section__title {
        display: flex;
        align-items: center;
        gap: 8px;
        margin: 0 0 0.75rem;
        font-size: 0.8rem;
        font-weight: 700;
        letter-spacing: 0.04em;
        text-transform: uppercase;
        color: rgba(255, 255, 255, 0.55);
    }

    .date-preset-grid {
        display: grid;
        grid-template-columns: repeat(2, minmax(0, 1fr));
        gap: 8px;
        margin-bottom: 12px;
    }

    .date-preset-btn {
        min-height: 36px;
        padding: 0 10px;
        border-radius: 10px;
        border: 1px solid rgba(255, 255, 255, 0.14);
        background: rgba(255, 255, 255, 0.06);
        color: rgba(255, 255, 255, 0.88);
        font-size: 0.75rem;
        font-weight: 600;
        cursor: pointer;
        transition: background 0.2s, border-color 0.2s, color 0.2s, transform 0.15s;
    }
    .date-preset-btn:hover {
        background: rgba(255, 255, 255, 0.12);
        border-color: rgba(255, 255, 255, 0.22);
    }
    .date-preset-btn.is-active {
        background: linear-gradient(135deg, #1976D2, #2563eb);
        border-color: transparent;
        color: #fff;
        box-shadow: 0 4px 14px rgba(37, 99, 235, 0.35);
    }

    .date-fields-grid {
        display: grid;
        grid-template-columns: 1fr;
        gap: 10px;
        margin-bottom: 12px;
    }

    .date-field__label {
        display: block;
        margin-bottom: 5px;
        font-size: 0.75rem;
        font-weight: 700;
        color: rgba(255, 255, 255, 0.82);
        letter-spacing: 0.02em;
    }

    .date-field__input-wrap {
        position: relative;
        display: flex;
        align-items: center;
    }

    .date-field__icon {
        position: absolute;
        inset-inline-start: 12px;
        z-index: 2;
        font-size: 0.85rem;
        color: #93c5fd;
        pointer-events: none;
    }

    .date-field__input,
    .date-field__input-wrap .flatpickr-input {
        width: 100%;
        height: 42px;
        padding: 0 12px 0 38px;
        border-radius: 10px;
        border: 1px solid rgba(148, 163, 184, 0.35);
        background: rgba(255, 255, 255, 0.14);
        color: #f8fafc;
        font-size: 0.9rem;
        font-weight: 600;
        font-variant-numeric: tabular-nums;
        letter-spacing: 0.01em;
        cursor: pointer;
        transition: border-color 0.2s, box-shadow 0.2s, background 0.2s;
    }
    html[dir="rtl"] .date-field__input,
    html[dir="rtl"] .date-field__input-wrap .flatpickr-input {
        padding: 0 38px 0 12px;
    }
    .date-field__input::placeholder,
    .date-field__input-wrap .flatpickr-input::placeholder {
        color: rgba(255, 255, 255, 0.45);
        font-weight: 500;
    }
    .date-field__input:focus,
    .date-field__input-wrap .flatpickr-input:focus {
        outline: none;
        border-color: #60a5fa;
        box-shadow: 0 0 0 3px rgba(59, 130, 246, 0.28);
        background: rgba(255, 255, 255, 0.18);
    }
    .date-field__input-wrap .flatpickr-mobile {
        display: none;
    }

    /* Flatpickr calendar — dark sidebar theme */
    #filterPanel .flatpickr-calendar {
        background: #1e293b;
        border: 1px solid rgba(255, 255, 255, 0.12);
        box-shadow: 0 16px 40px rgba(0, 0, 0, 0.45);
        border-radius: 12px;
    }
    #filterPanel .flatpickr-months .flatpickr-month,
    #filterPanel .flatpickr-weekdays,
    #filterPanel span.flatpickr-weekday {
        background: #1e293b;
        color: rgba(255, 255, 255, 0.85);
    }
    #filterPanel .flatpickr-current-month .flatpickr-monthDropdown-months,
    #filterPanel .flatpickr-current-month input.cur-year {
        color: #fff;
        font-weight: 700;
    }
    #filterPanel .flatpickr-day {
        color: rgba(255, 255, 255, 0.88);
    }
    #filterPanel .flatpickr-day:hover,
    #filterPanel .flatpickr-day:focus {
        background: rgba(59, 130, 246, 0.25);
        border-color: transparent;
    }
    #filterPanel .flatpickr-day.selected,
    #filterPanel .flatpickr-day.startRange,
    #filterPanel .flatpickr-day.endRange {
        background: #2563eb;
        border-color: #2563eb;
        color: #fff;
    }
    #filterPanel .flatpickr-day.today {
        border-color: #60a5fa;
    }
    #filterPanel .flatpickr-day.flatpickr-disabled {
        color: rgba(255, 255, 255, 0.25);
    }

    .btn-search-history {
        width: 100%;
        min-height: 42px;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        gap: 8px;
        border: none;
        border-radius: 12px;
        background: linear-gradient(135deg, #1976D2, #2563eb);
        color: #fff;
        font-size: 0.85rem;
        font-weight: 700;
        cursor: pointer;
        box-shadow: 0 6px 20px rgba(37, 99, 235, 0.35);
        transition: transform 0.2s, opacity 0.2s, box-shadow 0.2s;
    }
    .btn-search-history:hover:not(:disabled) {
        transform: translateY(-1px);
        box-shadow: 0 8px 24px rgba(37, 99, 235, 0.45);
    }
    .btn-search-history:disabled {
        opacity: 0.72;
        cursor: not-allowed;
        transform: none;
    }
    .btn-search-history.is-loading .search-btn__label { opacity: 0.85; }

    .sidebar-hint {
        margin: 8px 0 0;
        font-size: 0.68rem;
        line-height: 1.4;
        color: rgba(255, 255, 255, 0.45);
    }

    .device-hero-card {
        background: rgba(255, 255, 255, 0.97);
        border-radius: 14px;
        padding: 12px;
        margin-bottom: 10px;
        border: 1px solid rgba(255, 255, 255, 0.08);
        box-shadow: 0 8px 24px rgba(0, 0, 0, 0.12);
    }

    .device-hero-card__top {
        display: flex;
        align-items: flex-start;
        gap: 10px;
    }

    .device-hero-card__avatar {
        width: 40px;
        height: 40px;
        border-radius: 12px;
        background: linear-gradient(135deg, #1976D2, #42a5f5);
        color: #fff;
        display: flex;
        align-items: center;
        justify-content: center;
        flex-shrink: 0;
    }

    .device-hero-card__meta { flex: 1; min-width: 0; }
    .device-hero-card__name {
        display: block;
        font-size: 0.9rem;
        font-weight: 800;
        color: #0f172a;
        line-height: 1.25;
    }
    .device-hero-card__plate {
        display: block;
        font-size: 0.72rem;
        color: #64748b;
        margin-top: 2px;
    }

    .device-hero-card__stats {
        display: grid;
        grid-template-columns: 1fr 1fr;
        gap: 8px;
        margin-top: 10px;
        padding-top: 10px;
        border-top: 1px solid rgba(15, 23, 42, 0.08);
    }

    .device-stat__label {
        display: block;
        font-size: 0.65rem;
        font-weight: 600;
        text-transform: uppercase;
        letter-spacing: 0.03em;
        color: #94a3b8;
    }
    .device-stat__value {
        display: block;
        font-size: 0.78rem;
        font-weight: 700;
        color: #0f172a;
        margin-top: 2px;
        word-break: break-word;
    }
    .device-stat__value--mono {
        font-family: ui-monospace, monospace;
        font-size: 0.72rem;
    }

    .sidebar-map-hint {
        display: flex;
        align-items: flex-start;
        gap: 8px;
        margin: 0 0 12px;
        padding: 8px 10px;
        border-radius: 10px;
        background: rgba(59, 130, 246, 0.12);
        border: 1px solid rgba(59, 130, 246, 0.2);
        color: rgba(255, 255, 255, 0.75);
        font-size: 0.68rem;
        line-height: 1.4;
    }

    .sidebar-accordion {
        margin-bottom: 8px;
        border-radius: 12px;
        background: rgba(255, 255, 255, 0.04);
        border: 1px solid rgba(255, 255, 255, 0.08);
        overflow: hidden;
    }

    .sidebar-accordion__summary {
        display: flex;
        align-items: center;
        gap: 8px;
        padding: 10px 12px;
        font-size: 0.78rem;
        font-weight: 700;
        color: rgba(255, 255, 255, 0.9);
        cursor: pointer;
        list-style: none;
        user-select: none;
    }
    .sidebar-accordion__summary::-webkit-details-marker { display: none; }
    .sidebar-accordion__summary::after {
        content: '\f078';
        font-family: 'Font Awesome 6 Free';
        font-weight: 900;
        margin-inline-start: auto;
        font-size: 0.65rem;
        color: rgba(255, 255, 255, 0.45);
        transition: transform 0.2s;
    }
    .sidebar-accordion[open] .sidebar-accordion__summary::after { transform: rotate(180deg); }

    .sidebar-accordion__badge {
        margin-inline-start: auto;
        margin-inline-end: 6px;
        min-width: 20px;
        height: 20px;
        padding: 0 6px;
        border-radius: 999px;
        background: rgba(255, 255, 255, 0.12);
        font-size: 0.65rem;
        display: inline-flex;
        align-items: center;
        justify-content: center;
    }
    .sidebar-accordion__summary::after { margin-inline-start: 0; }
    .sidebar-accordion__summary .sidebar-accordion__badge { margin-inline-start: auto; margin-inline-end: 8px; }

    .sidebar-accordion__body {
        padding: 0 12px 12px;
        border-top: 1px solid rgba(255, 255, 255, 0.06);
    }
    .sidebar-accordion__body--flush { padding: 0; }

    .telemetry-grid {
        display: grid;
        grid-template-columns: repeat(2, minmax(0, 1fr));
        gap: 8px;
        padding-top: 10px;
    }
    .telemetry-cell {
        background: rgba(255, 255, 255, 0.06);
        border-radius: 10px;
        padding: 8px 10px;
    }
    .telemetry-cell span {
        display: block;
        font-size: 0.65rem;
        color: rgba(255, 255, 255, 0.5);
        font-weight: 600;
        text-transform: uppercase;
    }
    .telemetry-cell strong {
        display: block;
        margin-top: 3px;
        font-size: 0.82rem;
        color: #fff;
        font-weight: 700;
    }
    .telemetry-cell--wide { grid-column: 1 / -1; }

    .sidebar-actions-stack { display: grid; gap: 8px; padding-top: 10px; }

    .sidebar-action-btn {
        display: flex;
        align-items: center;
        justify-content: center;
        gap: 8px;
        min-height: 38px;
        border-radius: 10px;
        border: 1px solid rgba(255, 255, 255, 0.14);
        background: rgba(255, 255, 255, 0.06);
        color: rgba(255, 255, 255, 0.9);
        font-size: 0.78rem;
        font-weight: 600;
        cursor: pointer;
        transition: background 0.2s;
    }
    .sidebar-action-btn:hover { background: rgba(255, 255, 255, 0.12); }
    .sidebar-action-btn--primary {
        background: rgba(37, 99, 235, 0.25);
        border-color: rgba(59, 130, 246, 0.35);
    }

    .sidebar-loading {
        padding: 12px;
        text-align: center;
        font-size: 0.78rem;
        color: rgba(255, 255, 255, 0.55);
    }

    .geofence-list { max-height: 180px; overflow-y: auto; padding-top: 8px; }

    .geofence-list-item {
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: 8px;
        padding: 8px 0;
        border-bottom: 1px solid rgba(255, 255, 255, 0.06);
    }
    .geofence-list-item:last-child { border-bottom: none; }
    .geofence-list-item__info strong { font-size: 0.78rem; color: #fff; display: block; }
    .geofence-list-item__info small { font-size: 0.68rem; color: rgba(255,255,255,0.5); }

    .geofence-list-item__actions {
        display: flex;
        gap: 4px;
        flex-shrink: 0;
    }
    .geofence-list-item__actions .geofence-action-btn {
        width: 30px;
        height: 30px;
        padding: 0;
        border-radius: 8px;
        border: 1px solid rgba(255, 255, 255, 0.18);
        background: rgba(255, 255, 255, 0.08);
        color: rgba(255, 255, 255, 0.9);
        cursor: pointer;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        font-size: 0.72rem;
    }
    .geofence-list-item__actions .geofence-action-btn:hover {
        background: rgba(255, 255, 255, 0.16);
    }
    .geofence-list-item__actions .geofence-action-btn--danger {
        border-color: rgba(239, 68, 68, 0.45);
        color: #fca5a5;
    }
    .geofence-list-item__actions .geofence-action-btn--danger:hover {
        background: rgba(239, 68, 68, 0.22);
    }

    .geofence-list-empty {
        padding: 12px;
        text-align: center;
        font-size: 0.75rem;
        color: rgba(255, 255, 255, 0.45);
    }

    .address-result {
        margin-top: 10px;
        padding: 10px;
        border-radius: 10px;
        background: rgba(255, 255, 255, 0.06);
        color: rgba(255, 255, 255, 0.85);
        font-size: 0.78rem;
        line-height: 1.4;
        min-height: 0;
    }
    .address-result:empty { display: none; }

    .trip-events-list { max-height: 180px; overflow-y: auto; }
    .trip-event-item {
        padding: 10px 12px;
        border-bottom: 1px solid rgba(255, 255, 255, 0.06);
        font-size: 0.75rem;
        cursor: pointer;
    }
    .trip-event-item:hover { background: rgba(255, 255, 255, 0.04); }
    .trip-event-item strong { display: block; color: #fff; font-size: 0.78rem; }
    .trip-event-item span { color: rgba(255,255,255,0.55); font-size: 0.68rem; }
    .trip-event-empty {
        padding: 14px;
        text-align: center;
        font-size: 0.75rem;
        color: rgba(255, 255, 255, 0.45);
    }

    /* Status chips — sidebar device hero */
    #filterPanel .map-status-chip {
        flex-shrink: 0;
        display: inline-flex;
        align-items: center;
        padding: 4px 10px;
        border-radius: 999px;
        font-size: 0.65rem;
        font-weight: 700;
        line-height: 1.2;
        border: 1px solid transparent;
    }
    #filterPanel .map-status-chip--moving { background: #16a34a; color: #fff; }
    #filterPanel .map-status-chip--idle { background: #ea580c; color: #fff; }
    #filterPanel .map-status-chip--parked { background: #2563eb; color: #fff; }
    #filterPanel .map-status-chip--stopped { background: #dc2626; color: #fff; }
    #filterPanel .map-status-chip--offline { background: #64748b; color: #fff; }
    #filterPanel .map-status-chip--alert { background: #b91c1c; color: #fff; }

    @media (max-width: 768px) {
        .filter-panel#filterPanel {
            width: min(320px, 92vw);
            z-index: 1001;
        }
        .sidebar-close { display: flex; }
    }

    @media (max-width: 400px) {
        .date-preset-grid { grid-template-columns: 1fr 1fr; }
    }
</style>
