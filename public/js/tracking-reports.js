(function (global) {
    'use strict';
    const cfg = global.TRACKING_REPORTS_CONFIG;
    if (!cfg) return;

    const PAGE_SIZES = [50, 100, 250, 500];
    const PARALLEL_DEVICE_LIMIT = 3;
    // One vehicle per request keeps week-long analytics under the PHP time budget.
    const BATCH_DEVICE_SIZE = 1;
    const EXPORT_BATCH_SIZE = 4;
    const i18n = cfg.i18n || {};
    const colSets = i18n.columns || {};
    const fieldSets = i18n.fields || {};

    const FILTER_STORAGE_KEY = 'gt.reports.filters.v1';
    const MODE_STORAGE_KEY = 'gt.reports.mode.v1';
    const CUSTOM_FIELDS_PREFIX = 'gt.report.custom.fields.';
    const lockedType = cfg.lockedType ? String(cfg.lockedType) : null;

    const state = {
        columns: [],
        rows: [],
        page: 1,
        pageSize: 50,
        loading: false,
        exporting: false,
        /** True only after the latest Load History run finished successfully. */
        reportLoaded: false,
        lastPayload: null,
        runId: 0,
        abortController: null,
        mode: 'standard', // standard | custom
    };

    function exportButtons() {
        return ['gtReportCsv', 'gtReportXlsx', 'gtReportPdf']
            .map((id) => $(id))
            .filter(Boolean);
    }

    /** Keep CSV / Excel / PDF disabled while loading or until a report has finished. */
    function syncExportButtons() {
        const disabled = state.loading || state.exporting || !state.reportLoaded;
        exportButtons().forEach((btn) => {
            btn.disabled = disabled;
            btn.setAttribute('aria-disabled', disabled ? 'true' : 'false');
            if (state.loading) {
                btn.title = i18n.waitUntilLoaded || i18n.loadingReport || 'Wait until the report finishes loading.';
            } else if (!state.reportLoaded) {
                btn.title = i18n.loadBeforeExport || i18n.exportFailed || 'Load the report first, then export.';
            } else if (!state.exporting) {
                btn.removeAttribute('title');
            }
        });
    }

    const $ = (id) => document.getElementById(id);

    function defaultFilters() {
        return {
            ignore_empty: false,
            show_coordinates: true,
            show_addresses: false,
            markers_instead_of_addresses: false,
            zones_instead_of_addresses: false,
            stop_preset: '1',
            stop_custom_minutes: '',
            speed_limit_kmh: '',
        };
    }

    function readFiltersFromDom() {
        const stops = $('gtFilterStops')?.value || '1';
        const custom = $('gtFilterStopsCustom')?.value || '';
        return {
            ignore_empty: !!$('gtFilterIgnoreEmpty')?.checked,
            show_coordinates: !!$('gtFilterShowCoordinates')?.checked,
            show_addresses: !!$('gtFilterShowAddresses')?.checked,
            markers_instead_of_addresses: !!$('gtFilterMarkersInstead')?.checked,
            zones_instead_of_addresses: !!$('gtFilterZonesInstead')?.checked,
            stop_preset: stops,
            stop_custom_minutes: custom,
            speed_limit_kmh: ($('gtFilterSpeedLimit')?.value || '').trim(),
        };
    }

    function applyFiltersToDom(filters) {
        const f = { ...defaultFilters(), ...(filters || {}) };
        if ($('gtFilterIgnoreEmpty')) $('gtFilterIgnoreEmpty').checked = !!f.ignore_empty;
        if ($('gtFilterShowCoordinates')) $('gtFilterShowCoordinates').checked = f.show_coordinates !== false;
        if ($('gtFilterShowAddresses')) $('gtFilterShowAddresses').checked = !!f.show_addresses;
        if ($('gtFilterMarkersInstead')) $('gtFilterMarkersInstead').checked = !!f.markers_instead_of_addresses;
        if ($('gtFilterZonesInstead')) $('gtFilterZonesInstead').checked = !!f.zones_instead_of_addresses;
        if ($('gtFilterStops')) $('gtFilterStops').value = f.stop_preset || '1';
        if ($('gtFilterStopsCustom')) $('gtFilterStopsCustom').value = f.stop_custom_minutes || '';
        if ($('gtFilterSpeedLimit')) $('gtFilterSpeedLimit').value = f.speed_limit_kmh || '';
        syncStopsCustomVisibility();
    }

    function persistFilters() {
        try {
            sessionStorage.setItem(FILTER_STORAGE_KEY, JSON.stringify(readFiltersFromDom()));
        } catch (_) { /* ignore quota / private mode */ }
    }

    function restoreFilters() {
        try {
            const raw = sessionStorage.getItem(FILTER_STORAGE_KEY);
            if (!raw) {
                applyFiltersToDom(defaultFilters());
                return;
            }
            applyFiltersToDom(JSON.parse(raw));
        } catch (_) {
            applyFiltersToDom(defaultFilters());
        }
    }

    function syncStopsCustomVisibility() {
        const custom = $('gtFilterStopsCustom');
        if (!custom) return;
        custom.hidden = ($('gtFilterStops')?.value || '') !== 'custom';
    }

    function stopMinSeconds() {
        const preset = $('gtFilterStops')?.value || '1';
        if (preset === 'custom') {
            const mins = Math.max(1, parseInt($('gtFilterStopsCustom')?.value || '1', 10) || 1);
            return mins * 60;
        }
        return Math.max(1, (parseInt(preset, 10) || 1) * 60);
    }

    const LOCATION_REPORT_TYPES = [
        'trips', 'stops', 'trips_stops', 'events', 'positions', 'overspeeds',
        'zone_inout', 'fuel_fillings', 'current_position',
    ];

    function wantsAddressColumns() {
        if (isCustomMode()) {
            const keys = selectedFieldKeys(false);
            const addressKeys = new Set(i18n.addressFieldKeys || ['address', 'start_address', 'end_address']);
            return keys.some((k) => addressKeys.has(k));
        }
        return !!$('gtFilterShowAddresses')?.checked
            || !!$('gtFilterMarkersInstead')?.checked
            || !!$('gtFilterZonesInstead')?.checked;
    }

    function showCoordinates() {
        if (isCustomMode()) {
            const keys = selectedFieldKeys(false);
            const coordKeys = new Set(i18n.coordFieldKeys || ['lat', 'lng', 'start_lat', 'start_lng', 'end_lat', 'end_lng']);
            return keys.some((k) => coordKeys.has(k));
        }
        return !!$('gtFilterShowCoordinates')?.checked;
    }

    function isCustomMode() {
        return state.mode === 'custom';
    }

    function fieldsFor(type) {
        return fieldSets[type] || fieldSets.summary || [];
    }

    function customFieldsStorageKey(type) {
        return CUSTOM_FIELDS_PREFIX + (type || 'summary');
    }

    function selectedFieldKeys(requireCustom = true) {
        if (requireCustom && !isCustomMode()) return null;
        if (!isCustomMode()) return null;
        return [...document.querySelectorAll('#gtCustomFields input[type="checkbox"]:checked')]
            .map((el) => el.value)
            .filter(Boolean);
    }

    function loadSavedCustomFields(type) {
        try {
            const raw = localStorage.getItem(customFieldsStorageKey(type));
            if (!raw) return null;
            const parsed = JSON.parse(raw);
            return Array.isArray(parsed) ? parsed.map(String) : null;
        } catch (_) {
            return null;
        }
    }

    function saveCustomFields(type, keys) {
        try {
            localStorage.setItem(customFieldsStorageKey(type), JSON.stringify(keys || []));
        } catch (_) { /* ignore */ }
    }

    function rebuildCustomFields() {
        const host = $('gtCustomFields');
        if (!host) return;
        const type = $('gtReportType')?.value || 'summary';
        const fields = fieldsFor(type);
        const saved = loadSavedCustomFields(type);
        // null = never saved → default all on; [] = user cleared → keep empty.
        const selected = new Set(saved === null ? fields.map((f) => f.key) : saved);

        host.innerHTML = fields.map((f) => {
            const id = `gtCustomField_${f.key}`;
            const checked = selected.has(f.key) ? ' checked' : '';
            return `<div class="form-check">`
                + `<input class="form-check-input" type="checkbox" id="${id}" value="${escapeHtml(f.key)}"${checked}>`
                + `<label class="form-check-label" for="${id}">${escapeHtml(f.label)}</label>`
                + `</div>`;
        }).join('');

        host.querySelectorAll('input[type="checkbox"]').forEach((el) => {
            el.addEventListener('change', () => {
                saveCustomFields(type, selectedFieldKeys(false) || []);
                if (state.lastPayload) rerenderFromPayload(state.lastPayload);
            });
        });
    }

    function setReportMode(mode) {
        state.mode = mode === 'custom' ? 'custom' : 'standard';
        try { localStorage.setItem(MODE_STORAGE_KEY, state.mode); } catch (_) { /* ignore */ }

        const stdBtn = $('gtReportModeStandard');
        const cusBtn = $('gtReportModeCustom');
        stdBtn?.classList.toggle('is-active', state.mode === 'standard');
        cusBtn?.classList.toggle('is-active', state.mode === 'custom');
        stdBtn?.setAttribute('aria-selected', state.mode === 'standard' ? 'true' : 'false');
        cusBtn?.setAttribute('aria-selected', state.mode === 'custom' ? 'true' : 'false');

        const panel = $('gtCustomFieldsPanel');
        if (panel) panel.hidden = state.mode !== 'custom';

        if (state.mode === 'custom') rebuildCustomFields();
        if (state.lastPayload) rerenderFromPayload(state.lastPayload);
        else {
            state.columns = projectColumns(columnsFor($('gtReportType')?.value || 'summary'));
            renderHead();
            renderPage();
        }
    }

    function projectColumns(columns) {
        const keys = selectedFieldKeys();
        if (keys === null) return columns;
        if (!keys.length) return [];
        const type = $('gtReportType')?.value || 'summary';
        const labelByKey = {};
        fieldsFor(type).forEach((f) => { labelByKey[f.key] = f.label; });
        const out = [];
        keys.forEach((key) => {
            const label = labelByKey[key];
            if (!label) return;
            const idx = columns.indexOf(label);
            if (idx >= 0) out.push(label);
        });
        return out;
    }

    function projectFlat(flat) {
        const keys = selectedFieldKeys();
        if (keys === null) return flat;
        if (!keys.length) {
            return { ...flat, columns: [], rows: (flat.rows || []).map(() => []) };
        }
        const labelByKey = {};
        fieldsFor(flat.type || $('gtReportType')?.value || 'summary').forEach((f) => {
            labelByKey[f.key] = f.label;
        });
        const indices = [];
        const columns = [];
        keys.forEach((key) => {
            const label = labelByKey[key];
            if (!label) return;
            const idx = flat.columns.indexOf(label);
            if (idx >= 0) {
                indices.push(idx);
                columns.push(label);
            }
        });
        if (!indices.length) {
            return { ...flat, columns: [], rows: (flat.rows || []).map(() => []) };
        }
        return {
            ...flat,
            columns,
            rows: (flat.rows || []).map((row) => indices.map((i) => row[i] ?? '')),
        };
    }

    function isLocationReportType(type) {
        return LOCATION_REPORT_TYPES.includes(type || $('gtReportType')?.value || '');
    }

    function locationText(row) {
        if (!row || typeof row !== 'object') return '';
        return row.location_label || row.address || row.resolved_address
            || row.start_address || row.resolved_zone || row.resolved_marker || '';
    }

    function updateFilterHelp() {
        const help = $('gtReportFiltersHelp');
        if (!help) return;
        const type = $('gtReportType')?.value || 'summary';
        help.classList.remove('is-warn');
        if (!isLocationReportType(type) && (wantsAddressColumns() || !showCoordinates())) {
            help.textContent = i18n.filterHelpNoLocationType || i18n.filterHelpLocation || '';
            help.classList.add('is-warn');
            return;
        }
        if ($('gtFilterShowAddresses')?.checked && !cfg.hasGoogleMapsKey) {
            help.textContent = i18n.filterHelpGeocodeMissing || i18n.filterHelpLocation || '';
            help.classList.add('is-warn');
            return;
        }
        help.textContent = i18n.filterHelpLocation || '';
    }

    function deviceLooksEmpty(type, d) {
        if (!d) return true;
        switch (type) {
            case 'trips': return !(d.trips || []).length;
            case 'stops': return !(d.stops || []).length;
            case 'trips_stops': return !(d.segments || []).length;
            case 'events': return !(d.events || []).length;
            case 'overspeeds': return !(d.overspeeds || []).length;
            case 'zone_inout': return !((d.zone_events || d.events || []).length);
            case 'fuel_fillings': return !(d.fillings || []).length;
            case 'positions': return !(d.positions || []).length && !(d.point_count > 0);
            case 'route': return !(d.point_count > 0) && !(d.points || []).length;
            case 'mileage': return !(d.days || []).length;
            case 'summary': return !(Number(d.total_distance_km) > 0) && !(d.trip_count > 0) && !(d.stop_count > 0);
            case 'odometer': return !(Number(d.total_distance_km) > 0) && !(Number(d.odometer_delta_km) > 0);
            case 'diesel': return !(Number(d.total_distance_km) > 0);
            case 'speed':
            case 'altitude': return !(d.series || []).length;
            case 'ignition': return !(d.changes || []).length;
            case 'service': return !(d.services || []).length;
            case 'tasks': return !(d.tasks || []).length;
            case 'current_position': return d.lat == null || d.lng == null;
            default: return false;
        }
    }

    function applyClientFilters(payload) {
        if (!payload || typeof payload !== 'object') return payload;
        const type = payload.type || $('gtReportType')?.value || 'summary';
        let devices = Array.isArray(payload.devices) ? [...payload.devices] : [];
        if (readFiltersFromDom().ignore_empty) {
            devices = devices.filter((d) => !deviceLooksEmpty(type, d));
        }
        return { ...payload, devices };
    }

    function rerenderFromPayload(payload) {
        if (!payload) return;
        const filtered = applyClientFilters(payload);
        state.lastPayload = payload;
        const flat = projectFlat(flatten(filtered));
        state.columns = flat.columns;
        state.rows = flat.rows;
        renderKpis(flat);
        renderHead();
        renderPage();
        if (!state.rows.length) {
            showEmpty(i18n.noData || 'No data for the selected report and period.');
        } else {
            const empty = $('gtReportEmpty');
            if (empty) { empty.hidden = true; empty.textContent = ''; }
        }
    }

    function onFilterChanged(sourceId) {
        persistFilters();
        updateFilterHelp();
        if (sourceId === 'gtFilterStops') syncStopsCustomVisibility();

        const displayOnly = sourceId === 'gtFilterShowCoordinates';
        if (displayOnly) {
            if (state.lastPayload) rerenderFromPayload(state.lastPayload);
            return;
        }

        // Server-backed filters: auto re-run so Location/empty/stops data actually appears.
        if (selectedIds().length && (state.lastPayload || sourceId)) {
            runReport();
        } else if (state.lastPayload) {
            rerenderFromPayload(state.lastPayload);
        }
    }

    function appendFilterParams(p) {
        const f = readFiltersFromDom();
        p.set('ignore_empty', f.ignore_empty ? '1' : '0');
        p.set('show_coordinates', f.show_coordinates ? '1' : '0');
        p.set('show_addresses', f.show_addresses ? '1' : '0');
        p.set('markers_instead_of_addresses', f.markers_instead_of_addresses ? '1' : '0');
        p.set('zones_instead_of_addresses', f.zones_instead_of_addresses ? '1' : '0');
        p.set('stop_min_seconds', String(stopMinSeconds()));
        if (f.speed_limit_kmh !== '') {
            p.set('speed_limit_kmh', f.speed_limit_kmh);
        }
        return p;
    }

    function updateFilterVisibility() {
        const type = $('gtReportType')?.value || 'summary';
        const map = i18n.filterApplicability || {};
        const typeKeys = Array.isArray(map[type]) ? map[type] : [];
        document.querySelectorAll('#gtReportFilters [data-filter-key]').forEach((el) => {
            const key = el.getAttribute('data-filter-key');
            if (key === 'speed_limit') {
                el.hidden = !(type === 'overspeeds' || type === 'summary' || typeKeys.includes('speed_limit'));
            } else {
                el.hidden = false;
            }
        });
        const box = $('gtReportFilters');
        if (box) box.hidden = false;
        updateFilterHelp();
    }

    function escapeHtml(value) {
        return String(value ?? '')
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;');
    }

    function escapeAttr(value) {
        return escapeHtml(value).replace(/'/g, '&#39;');
    }

    function isMapsUrl(value) {
        if (typeof value !== 'string' || value === '') return false;
        return /^https:\/\/(www\.)?google\.com\/maps\b/i.test(value)
            || /^https:\/\/maps\.google\./i.test(value);
    }

    function formatDuration(sec) {
        const total = Math.round(parseFloat(sec) || 0);
        if (total <= 0) return `0${i18n.secSuffix || 's'}`;
        const h = Math.floor(total / 3600);
        const m = Math.floor((total % 3600) / 60);
        const s = total % 60;
        const parts = [];
        if (h) parts.push(`${h}${i18n.hourSuffix || 'h'}`);
        if (h || m) parts.push(`${m}${i18n.minSuffix || 'm'}`);
        if (!h) parts.push(`${s}${i18n.secSuffix || 's'}`);
        return parts.join(' ');
    }

    function formatCoord(value) {
        if (value === null || value === undefined || value === '') return '';
        const num = Number(value);
        return Number.isFinite(num) ? num.toFixed(6) : String(value);
    }

    function formatIgnition(value) {
        if (value === null || value === undefined || value === '') return '';
        return value ? (i18n.ignitionOn || 'On') : (i18n.ignitionOff || 'Off');
    }

    function formatReportTime(value) {
        if (value == null || value === '') return '';
        const raw = String(value).trim();
        if (!raw) return '';
        // Already a server display string (12-hour AM/PM) — keep as-is.
        if (/\b(?:AM|PM)\b/i.test(raw) && !/^\d{4}-\d{2}-\d{2}T/.test(raw)) {
            return raw.replace(/\b(am|pm)\b/gi, (m) => m.toUpperCase());
        }
        if (global.AppDateTime && typeof global.AppDateTime.formatDateTime === 'function') {
            const formatted = global.AppDateTime.formatDateTime(raw);
            return formatted === '—' ? raw : formatted;
        }
        const ms = Date.parse(raw);
        if (!Number.isFinite(ms)) return raw;
        try {
            const tz = (cfg && cfg.timezone) || 'Asia/Riyadh';
            const parts = new Intl.DateTimeFormat('en-GB', {
                timeZone: tz,
                day: 'numeric',
                month: 'short',
                year: 'numeric',
                hour: 'numeric',
                minute: '2-digit',
                hour12: true,
            }).formatToParts(new Date(ms));
            const get = (type) => parts.find((p) => p.type === type)?.value ?? '';
            const dayPeriod = String(get('dayPeriod') || '').toUpperCase();
            return `${get('day')} ${get('month')} ${get('year')}, ${get('hour')}:${get('minute')} ${dayPeriod}`.trim();
        } catch (_) {
            return raw;
        }
    }

    /** Prefer server display labels; fall back to formatting ISO timestamps. */
    function displayStartTime(row) {
        if (!row) return '';
        return row.start_time_display || row.start_display
            || formatReportTime(row.start_time || row.start)
            || row.start_time || row.start || '';
    }

    function displayEndTime(row) {
        if (!row) return '';
        return row.end_time_display || row.end_display
            || formatReportTime(row.end_time || row.end)
            || row.end_time || row.end || '';
    }

    function reportRowStartMs(row) {
        if (!row) return 0;
        const raw = row.start || row.start_time || row.start_display || row.start_time_display || '';
        const ms = Date.parse(String(raw));
        return Number.isFinite(ms) ? ms : 0;
    }

    function sortRowsByStart(rows) {
        if (!Array.isArray(rows) || rows.length < 2) return rows || [];
        return [...rows].sort((a, b) => reportRowStartMs(a) - reportRowStartMs(b));
    }

    function numCell(value) {
        return `<span class="gt-report-num" dir="ltr">${escapeHtml(value)}</span>`;
    }

    function selectedIds() {
        return [...document.querySelectorAll('#gtReportVehicles input:checked')].map((el) => el.value);
    }

    function selectedIdsParam() {
        const all = document.querySelectorAll('#gtReportVehicles input[type="checkbox"]');
        const checked = selectedIds();
        if (!checked.length) return '';
        if (checked.length === all.length && all.length > 0) return 'all';
        return checked.join(',');
    }

    function reportLang() {
        const el = $('gtReportLang');
        return el ? el.value : (cfg.currentLang || 'en');
    }

    function dateTimeParam(id) {
        const raw = $(id)?.value || '';
        if (!raw) return '';
        return raw.replace('T', ' ');
    }

    function queryParams() {
        const p = new URLSearchParams();
        p.set('type', $('gtReportType').value);
        p.set('from', dateTimeParam('gtReportFrom'));
        p.set('to', dateTimeParam('gtReportTo'));
        p.set('ids', selectedIdsParam());
        p.set('lang', reportLang());
        appendFilterParams(p);
        if (isCustomMode()) {
            const keys = selectedFieldKeys(false) || [];
            if (keys.length) p.set('fields', keys.join(','));
            // Custom field picks override filter checkboxes for address/coord columns.
            const addressKeys = new Set(i18n.addressFieldKeys || ['address', 'start_address', 'end_address']);
            const coordKeys = new Set(i18n.coordFieldKeys || ['lat', 'lng', 'start_lat', 'start_lng', 'end_lat', 'end_lng']);
            p.set('show_addresses', keys.some((k) => addressKeys.has(k)) ? '1' : '0');
            p.set('show_coordinates', keys.some((k) => coordKeys.has(k)) ? '1' : '0');
            // Avoid marker/zone address modes overriding explicit field selection.
            p.set('markers_instead_of_addresses', '0');
            p.set('zones_instead_of_addresses', '0');
        }
        return p;
    }

    function csrfToken() {
        return document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '';
    }

    function columnsFor(type) {
        let cols = [...(colSets[type] || colSets.summary || [])];
        if (!showCoordinates()) {
            const hideLabels = new Set(i18n.coordLabels || []);
            cols = cols.filter((c) => !hideLabels.has(c));
        }
        if (wantsAddressColumns() && isLocationReportType(type)) {
            const addressLabel = i18n.colAddress || i18n.colLocation || 'Location';
            const startAddress = i18n.colStartAddress || 'Start address';
            const endAddress = i18n.colEndAddress || 'End address';
            const mapsIdx = cols.findIndex((c) => {
                const l = String(c).toLowerCase();
                return l.includes('map') || l.includes('خرائط');
            });
            const at = mapsIdx >= 0 ? mapsIdx : cols.length;
            if (type === 'trips') {
                cols.splice(at, 0, startAddress, endAddress);
            } else {
                cols.splice(at, 0, addressLabel);
            }
        }
        return cols;
    }

    function pushCoords(row, ...values) {
        if (showCoordinates()) {
            values.forEach((v) => row.push(v));
        }
        return row;
    }

    function pushAddress(row, ...values) {
        if (wantsAddressColumns()) {
            values.forEach((v) => row.push(v ?? ''));
        }
        return row;
    }

    function queryParamsForIds(ids, runId, fromOverride, toOverride) {
        const p = queryParams();
        if (!ids.length) {
            p.set('ids', '');
        } else {
            p.set('ids', ids.join(','));
        }
        if (fromOverride) p.set('from', fromOverride);
        if (toOverride) p.set('to', toOverride);
        if (runId != null) {
            p.set('_nonce', String(runId));
        }
        p.set('_ts', String(Date.now()));
        return p;
    }

    function dateWindowsFromInputs() {
        const fromFull = dateTimeParam('gtReportFrom') || '';
        const toFull = dateTimeParam('gtReportTo') || '';
        const fromRaw = fromFull.slice(0, 10);
        const toRaw = toFull.slice(0, 10);
        if (!/^\d{4}-\d{2}-\d{2}$/.test(fromRaw) || !/^\d{4}-\d{2}-\d{2}$/.test(toRaw)) {
            return [{ from: fromFull, to: toFull }];
        }
        let start = new Date(`${fromRaw}T00:00:00`);
        let end = new Date(`${toRaw}T00:00:00`);
        if (end < start) {
            const tmp = start;
            start = end;
            end = tmp;
        }
        const daySpan = Math.round((end.getTime() - start.getTime()) / 86400000) + 1;
        // Short ranges: keep the exact selected datetimes (more accurate than date-only).
        if (daySpan <= 2) {
            return [{ from: fromFull, to: toFull }];
        }

        const fmtDate = (d) => `${d.getFullYear()}-${String(d.getMonth() + 1).padStart(2, '0')}-${String(d.getDate()).padStart(2, '0')}`;
        const endOfDay = (d) => `${fmtDate(d)} 23:59:59`;
        const startOfDay = (d) => `${fmtDate(d)} 00:00:00`;

        const windows = [];
        const maxDays = 2;
        let cursor = new Date(start);
        let isFirst = true;
        while (cursor <= end) {
            const windowEnd = new Date(cursor);
            windowEnd.setDate(windowEnd.getDate() + (maxDays - 1));
            const clamped = windowEnd > end ? new Date(end) : windowEnd;
            const isLast = clamped.getTime() >= end.getTime();
            windows.push({
                from: isFirst ? fromFull : startOfDay(cursor),
                to: isLast ? toFull : endOfDay(clamped),
            });
            isFirst = false;
            cursor = new Date(clamped);
            cursor.setDate(cursor.getDate() + 1);
        }
        return windows.length ? windows : [{ from: fromFull, to: toFull }];
    }

    function recomputeEfficiency(distanceKm, fuelLiters, unit) {
        const dist = Number(distanceKm) || 0;
        const fuel = Number(fuelLiters) || 0;
        if (!(dist > 0) || !(fuel > 0)) return 0;
        if (unit === 'km_per_l') {
            return round2(dist / fuel);
        }
        // Default: L/100km
        return round2((fuel / dist) * 100);
    }

    function mergeDeviceWindowRows(parts) {
        if (!parts.length) return null;
        if (parts.length === 1) return parts[0];
        // Prefer a window that actually has GPS so start odometer/time are not null.
        const ranked = [...parts].sort((a, b) => {
            const aPts = Number(a.point_count || a.position_count || 0);
            const bPts = Number(b.point_count || b.position_count || 0);
            if (aPts === bPts) return 0;
            return bPts > 0 && aPts === 0 ? 1 : (aPts > 0 && bPts === 0 ? -1 : 0);
        });
        const base = { ...ranked[0] };
        const sumN = (key) => parts.reduce((s, d) => s + (Number(d[key]) || 0), 0);
        const maxN = (key) => Math.max(0, ...parts.map((d) => Number(d[key]) || 0));
        const cat = (key) => parts.flatMap((d) => (Array.isArray(d[key]) ? d[key] : []));

        base.total_distance_km = round2(sumN('total_distance_km'));
        base.moving_time_seconds = sumN('moving_time_seconds');
        base.stopped_time_seconds = sumN('stopped_time_seconds');
        base.idle_time_seconds = sumN('idle_time_seconds');
        base.parking_time_seconds = sumN('parking_time_seconds');
        base.offline_time_seconds = sumN('offline_time_seconds');
        base.total_duration_seconds = sumN('total_duration_seconds');
        base.trip_count = sumN('trip_count');
        base.stop_count = sumN('stop_count');
        base.overspeed_events = sumN('overspeed_events');
        base.point_count = sumN('point_count');
        base.position_count = sumN('position_count');
        base.event_count = sumN('event_count');
        base.day_count = sumN('day_count');
        base.fuel_liters = round2(sumN('fuel_liters'));
        base.max_speed_kmh = maxN('max_speed_kmh');
        if (base.moving_time_seconds > 0 && base.total_distance_km > 0) {
            base.average_speed_kmh = round2(base.total_distance_km / (base.moving_time_seconds / 3600));
        }
        // Keep the device efficiency unit (L/100km or km/L) when stitching date windows.
        base.efficiency_unit = parts.find((p) => p.efficiency_unit)?.efficiency_unit || base.efficiency_unit || 'l_per_100km';
        base.efficiency = recomputeEfficiency(base.total_distance_km, base.fuel_liters, base.efficiency_unit);

        // Odometer report: keep first start / last end across date windows (do not sum deltas).
        let startOdo = null;
        let endOdo = null;
        let startTime = null;
        let endTime = null;
        parts.forEach((p) => {
            if (p.start_odometer_km != null && p.start_odometer_km !== '' && startOdo == null) {
                startOdo = Number(p.start_odometer_km);
            }
            if (p.end_odometer_km != null && p.end_odometer_km !== '') {
                endOdo = Number(p.end_odometer_km);
            }
            if (p.start_time && (!startTime || String(p.start_time) < String(startTime))) {
                startTime = p.start_time;
            }
            if (p.end_time && (!endTime || String(p.end_time) > String(endTime))) {
                endTime = p.end_time;
            }
        });
        if (startOdo != null) base.start_odometer_km = startOdo;
        if (endOdo != null) base.end_odometer_km = endOdo;
        if (startOdo != null && endOdo != null) {
            base.odometer_delta_km = round2(Math.max(0, endOdo - startOdo));
            // Keep traveled distance aligned with device odometer across windows.
            if (!(Number(base.total_distance_km) > 0)) {
                base.total_distance_km = base.odometer_delta_km;
            } else {
                // Prefer odometer delta when both exist (more stable than summed GPS windows).
                base.total_distance_km = base.odometer_delta_km;
            }
        }
        if (startTime) {
            base.start_time = startTime;
            base.start_time_display = formatReportTime(startTime);
        }
        if (endTime) {
            base.end_time = endTime;
            base.end_time_display = formatReportTime(endTime);
        }

        ['trips', 'stops', 'segments', 'days', 'events', 'positions', 'points', 'overspeeds', 'fillings', 'series', 'changes', 'services', 'tasks', 'zone_events'].forEach((key) => {
            let rows = cat(key);
            if (key === 'trips' || key === 'stops' || key === 'segments') {
                rows = sortRowsByStart(rows);
            }
            if (rows.length) {
                base[key] = rows;
                if (key === 'trips') base.trip_count = rows.length;
                if (key === 'stops') base.stop_count = rows.length;
                if (key === 'days') base.day_count = rows.length;
                if (key === 'events' || key === 'zone_events') base.event_count = rows.length;
                if (key === 'positions') base.position_count = rows.length;
                if (key === 'points' || key === 'series') base.point_count = rows.length;
                if (key === 'overspeeds') base.overspeed_count = rows.length;
                if (key === 'fillings') {
                    base.filling_count = rows.length;
                    base.total_filled_liters = round2(rows.reduce((s, r) => s + (Number(r.liters) || 0), 0));
                }
                if (key === 'changes') base.change_count = rows.length;
                if (key === 'services') base.service_count = rows.length;
                if (key === 'tasks') base.task_count = rows.length;
            }
        });
        return base;
    }

    function mergeReportPayloads(partials, orderedIds) {
        if (!partials.length) return null;
        const first = partials[0];
        const byId = new Map();
        partials.forEach((p) => {
            (p.devices || []).forEach((d) => {
                if (d.device_id == null) return;
                const id = String(d.device_id);
                if (!byId.has(id)) byId.set(id, []);
                byId.get(id).push(d);
            });
        });
        const order = orderedIds && orderedIds.length
            ? orderedIds.map(String)
            : [...byId.keys()];
        const devices = order
            .map((id) => mergeDeviceWindowRows(byId.get(String(id)) || []))
            .filter(Boolean);
        const totals = { ...(first.totals || {}) };

        if (first.type === 'summary' || first.type === 'route') {
            totals.device_count = devices.length;
            totals.total_distance_km = round2(devices.reduce((s, d) => s + (Number(d.total_distance_km) || 0), 0));
            totals.moving_time_seconds = devices.reduce((s, d) => s + (Number(d.moving_time_seconds) || 0), 0);
            totals.stopped_time_seconds = devices.reduce((s, d) => s + (Number(d.stopped_time_seconds) || 0), 0);
            totals.idle_time_seconds = devices.reduce((s, d) => s + (Number(d.idle_time_seconds) || 0), 0);
            totals.parking_time_seconds = devices.reduce((s, d) => s + (Number(d.parking_time_seconds) || 0), 0);
            totals.offline_time_seconds = devices.reduce((s, d) => s + (Number(d.offline_time_seconds) || 0), 0);
            totals.trip_count = devices.reduce((s, d) => s + (Number(d.trip_count) || 0), 0);
            totals.stop_count = devices.reduce((s, d) => s + (Number(d.stop_count) || 0), 0);
            totals.overspeed_events = devices.reduce((s, d) => s + (Number(d.overspeed_events) || 0), 0);
            totals.point_count = devices.reduce((s, d) => s + (Number(d.point_count) || 0), 0);
            totals.max_speed_kmh = Math.max(0, ...devices.map((d) => Number(d.max_speed_kmh) || 0));
        } else if (first.type === 'trips' || first.type === 'trips_stops') {
            totals.device_count = devices.length;
            totals.trip_count = devices.reduce((s, d) => s + (Number(d.trip_count) || 0), 0);
            totals.stop_count = devices.reduce((s, d) => s + (Number(d.stop_count) || 0), 0);
            totals.total_distance_km = round2(devices.reduce((s, d) => s + (Number(d.total_distance_km) || 0), 0));
        } else if (first.type === 'mileage') {
            totals.device_count = devices.length;
            totals.total_distance_km = round2(devices.reduce((s, d) => s + (Number(d.total_distance_km) || 0), 0));
            totals.day_count = devices.reduce((s, d) => s + (Number(d.day_count) || 0), 0);
            totals.trip_count = devices.reduce((s, d) => s + (Number(d.trip_count) || 0), 0);
        } else if (first.type === 'odometer') {
            totals.device_count = devices.length;
            totals.total_distance_km = round2(devices.reduce((s, d) => s + (Number(d.total_distance_km) || 0), 0));
            totals.moving_time_seconds = devices.reduce((s, d) => s + (Number(d.moving_time_seconds) || 0), 0);
        } else if (first.type === 'diesel') {
            totals.device_count = devices.length;
            totals.total_distance_km = round2(devices.reduce((s, d) => s + (Number(d.total_distance_km) || 0), 0));
            totals.fuel_liters = round2(devices.reduce((s, d) => s + (Number(d.fuel_liters) || 0), 0));
            totals.trip_count = devices.reduce((s, d) => s + (Number(d.trip_count) || 0), 0);
            totals.day_count = devices.reduce((s, d) => s + (Number(d.day_count) || 0), 0);
            const unit = devices.find((d) => d.efficiency_unit)?.efficiency_unit || 'l_per_100km';
            totals.efficiency_unit = unit;
            totals.efficiency = recomputeEfficiency(totals.total_distance_km, totals.fuel_liters, unit);
        } else if (first.type === 'stops') {
            totals.device_count = devices.length;
            totals.stop_count = devices.reduce((s, d) => s + (Number(d.stop_count) || 0), 0);
        } else if (first.type === 'events') {
            totals.device_count = devices.length;
            totals.event_count = devices.reduce((s, d) => s + (Number(d.event_count) || 0), 0);
        } else if (first.type === 'positions') {
            totals.device_count = devices.length;
            totals.position_count = devices.reduce((s, d) => s + (Number(d.position_count) || 0), 0);
        }

        const meta = {
            devices_requested: orderedIds?.length || devices.length,
            devices_in_report: devices.length,
            devices_capped: partials.some((p) => p.meta?.devices_capped),
            positions_truncated: partials.some((p) => p.meta?.positions_truncated),
            analytics_downsampled: partials.some((p) => p.meta?.analytics_downsampled),
            range_windows: partials.length,
        };

        let overallFrom = first.from;
        let overallTo = first.to;
        partials.forEach((p) => {
            if (p.from && (!overallFrom || String(p.from) < String(overallFrom))) overallFrom = p.from;
            if (p.to && (!overallTo || String(p.to) > String(overallTo))) overallTo = p.to;
        });

        return {
            success: true,
            type: first.type,
            from: overallFrom,
            to: overallTo,
            devices,
            totals,
            meta,
        };
    }

    function round2(n) {
        return Math.round(n * 100) / 100;
    }

    async function fetchReportPayload(ids, signal, runId, fromOverride, toOverride) {
        const body = queryParamsForIds(ids, runId, fromOverride, toOverride);
        const res = await fetch(cfg.generateUrl, {
            method: 'POST',
            credentials: 'same-origin',
            cache: 'no-store',
            signal,
            headers: {
                Accept: 'application/json',
                'Content-Type': 'application/x-www-form-urlencoded',
                'X-CSRF-TOKEN': csrfToken(),
                'X-Requested-With': 'XMLHttpRequest',
                'Cache-Control': 'no-cache',
                Pragma: 'no-cache',
            },
            body: body.toString(),
        });
        const data = await res.json().catch(() => ({}));
        if (!res.ok || data.success === false) {
            throw new Error(data.message || `HTTP ${res.status}`);
        }
        if (runId != null && data._nonce != null && String(data._nonce) !== String(runId)) {
            return null;
        }
        return data;
    }

    async function runPool(items, limit, worker) {
        const queue = [...items];
        const workers = Array.from({ length: Math.min(limit, queue.length) }, async () => {
            while (queue.length) {
                const item = queue.shift();
                await worker(item);
            }
        });
        await Promise.all(workers);
    }

    function reportNotice(flat) {
        const meta = flat.meta || {};
        const parts = [];
        if (meta.devices_capped) {
            parts.push(i18n.devicesCapped || 'Showing the first 200 vehicles. Narrow the selection for faster results.');
        }
        if (meta.positions_truncated) {
            parts.push(i18n.positionsTruncated || 'Large GPS datasets were trimmed per vehicle. Use CSV export for full data.');
        }
        if (meta.analytics_downsampled) {
            parts.push(i18n.analyticsDownsampled || 'Time and stop totals were estimated from a large GPS sample. Distance uses all points.');
        }
        return parts.join(' ');
    }

    function routeMapDevice(devices, preferredIds) {
        const order = preferredIds && preferredIds.length ? preferredIds : [];
        for (const id of order) {
            const match = devices.find((d) => String(d.device_id) === String(id));
            if (match?.points?.length) {
                return match;
            }
        }

        return devices.find((d) => d.points?.length) || null;
    }

    /** Flatten API payload into columns + rows for the table. */
    function flatten(data) {
        const type = data.type;
        const devices = data.devices || [];
        const columns = columnsFor(type);
        const rows = [];

        if (type === 'summary') {
            devices.forEach((d) => rows.push([
                d.device_name,
                d.plate || '',
                d.driver || '',
                d.total_distance_km,
                formatDuration(d.moving_time_seconds),
                formatDuration(d.stopped_time_seconds),
                formatDuration(d.idle_time_seconds),
                formatDuration(d.parking_time_seconds),
                formatDuration(d.offline_time_seconds),
                d.max_speed_kmh,
                d.average_speed_kmh,
                d.stop_count,
                d.trip_count,
                d.overspeed_events,
                d.start_time_display || formatReportTime(d.start_time) || d.start_time || '',
                d.end_time_display || formatReportTime(d.end_time) || d.end_time || '',
                formatDuration(d.total_duration_seconds),
            ]));
        } else if (type === 'trips') {
            devices.forEach((d) => (d.trips || []).forEach((t) => {
                const tripRow = [
                    d.device_name,
                    d.plate || '',
                    d.driver || '',
                    displayStartTime(t),
                    displayEndTime(t),
                ];
                pushCoords(tripRow, formatCoord(t.start_lat), formatCoord(t.start_lng), formatCoord(t.end_lat), formatCoord(t.end_lng));
                pushAddress(tripRow, t.start_address || locationText(t) || '', t.end_address || '');
                tripRow.push(
                    t.start_maps_url || '',
                    t.end_maps_url || '',
                    t.distance_km,
                    formatDuration(t.duration_seconds),
                    formatDuration(t.moving_time_seconds),
                    t.stop_count ?? 0,
                    t.route_point_count ?? 0,
                    t.max_speed_kmh,
                    t.average_speed_kmh,
                );
                rows.push(tripRow);
                (t.stops || []).forEach((s) => {
                    const stopRow = [
                        d.device_name,
                        d.plate || '',
                        '',
                        displayStartTime(s),
                        displayEndTime(s),
                    ];
                    pushCoords(stopRow, formatCoord(s.lat), formatCoord(s.lng), '', '');
                    pushAddress(stopRow, locationText(s), '');
                    stopRow.push(
                        s.maps_url || '',
                        '',
                        '',
                        formatDuration(s.duration_seconds),
                        '',
                        s.status_label || 'Stop',
                        '',
                        '',
                        '',
                    );
                    rows.push(stopRow);
                });
            }));
        } else if (type === 'stops') {
            devices.forEach((d) => (d.stops || []).forEach((s) => {
                const row = [
                    d.device_name,
                    d.plate || '',
                    s.status_label || '',
                    displayStartTime(s),
                    displayEndTime(s),
                    formatDuration(s.duration_seconds),
                ];
                pushCoords(row, formatCoord(s.lat), formatCoord(s.lng));
                pushAddress(row, locationText(s));
                row.push(s.maps_url || '');
                rows.push(row);
            }));
        } else if (type === 'trips_stops') {
            devices.forEach((d) => (d.segments || []).forEach((seg) => {
                const isTrip = seg.kind === 'trip';
                const row = [
                    d.device_name,
                    d.plate || '',
                    seg.kind_label || seg.kind || '',
                    displayStartTime(seg),
                    displayEndTime(seg),
                    formatDuration(seg.duration_seconds),
                    isTrip ? (seg.distance_km ?? 0) : '',
                ];
                pushCoords(row, formatCoord(isTrip ? seg.start_lat : seg.lat), formatCoord(isTrip ? seg.start_lng : seg.lng));
                pushAddress(row, locationText(seg) || seg.start_address || '');
                row.push(seg.maps_url || seg.start_maps_url || '', isTrip ? (seg.stop_count ?? 0) : '');
                rows.push(row);
            }));
        } else if (type === 'mileage') {
            devices.forEach((d) => (d.days || []).forEach((day) => rows.push([
                d.device_name,
                d.plate || '',
                day.date || '',
                day.distance_km ?? 0,
                formatDuration(day.duration_seconds),
                day.point_count ?? 0,
                day.start_time || '',
                day.end_time || '',
                day.start_maps_url || '',
                day.end_maps_url || '',
            ])));
        } else if (type === 'odometer') {
            devices.forEach((d) => {
                const startOdo = d.start_odometer_km != null && d.start_odometer_km !== ''
                    ? Number(d.start_odometer_km)
                    : null;
                const endOdo = d.end_odometer_km != null && d.end_odometer_km !== ''
                    ? Number(d.end_odometer_km)
                    : null;
                const delta = d.odometer_delta_km != null && d.odometer_delta_km !== ''
                    ? Number(d.odometer_delta_km)
                    : (startOdo != null && endOdo != null ? round2(Math.max(0, endOdo - startOdo)) : null);
                const traveled = d.total_distance_km != null
                    ? Number(d.total_distance_km)
                    : (delta != null ? delta : 0);
                rows.push([
                    d.device_name,
                    d.plate || '',
                    Number.isFinite(traveled) ? traveled : 0,
                    formatDuration(d.moving_time_seconds),
                    startOdo != null && Number.isFinite(startOdo) ? startOdo : '—',
                    endOdo != null && Number.isFinite(endOdo) ? endOdo : '—',
                    delta != null && Number.isFinite(delta) ? delta : '—',
                    d.start_time_display || formatReportTime(d.start_time) || '—',
                    d.end_time_display || formatReportTime(d.end_time) || '—',
                ]);
            });
        } else if (type === 'diesel') {
            const periodLabel = i18n.segPeriod || 'Period total';
            const tripLabel = i18n.segTrip || 'Trip';
            const dayLabel = i18n.segDay || 'Day';
            devices.forEach((d) => {
                const eff = d.efficiency != null && d.efficiency !== ''
                    ? `${d.efficiency}${d.efficiency_label ? ` ${d.efficiency_label}` : ''}`
                    : '';
                rows.push([
                    d.device_name,
                    d.plate || '',
                    periodLabel,
                    d.start_time_display || formatReportTime(d.start_time) || d.start_time || '',
                    d.end_time_display || formatReportTime(d.end_time) || d.end_time || '',
                    d.total_distance_km ?? 0,
                    d.fuel_liters ?? '',
                    eff,
                    d.fuel_method_label || d.fuel_method || '',
                    d.rate_l_per_100km ?? '',
                    '',
                ]);
                (d.trips || []).forEach((t) => rows.push([
                    d.device_name,
                    d.plate || '',
                    tripLabel,
                    displayStartTime(t),
                    displayEndTime(t),
                    t.distance_km ?? 0,
                    t.fuel_liters ?? '',
                    t.efficiency ?? '',
                    t.fuel_method || '',
                    '',
                    t.maps_url || t.start_maps_url || '',
                ]));
                (d.days || []).forEach((day) => rows.push([
                    d.device_name,
                    d.plate || '',
                    dayLabel,
                    day.date || day.start_time || '',
                    day.end_time || '',
                    day.distance_km ?? 0,
                    day.fuel_liters ?? '',
                    day.efficiency ?? '',
                    day.fuel_method || '',
                    '',
                    day.maps_url || day.start_maps_url || '',
                ]));
            });
        } else if (type === 'events') {
            devices.forEach((d) => (d.events || []).forEach((e) => {
                const row = [
                    d.device_name,
                    d.plate || '',
                    e.time_display || e.time || '',
                    e.event_type || e.type || '',
                    e.title || '',
                    e.message || '',
                    e.geofence || '',
                ];
                pushCoords(row, formatCoord(e.lat), formatCoord(e.lng));
                pushAddress(row, locationText(e));
                row.push(e.maps_url || '', e.speed ?? '');
                rows.push(row);
            }));
        } else if (type === 'positions') {
            devices.forEach((d) => (d.positions || []).forEach((p) => {
                const row = [
                    d.device_name,
                    d.plate || '',
                    p.time_display || p.time || '',
                ];
                pushCoords(row, formatCoord(p.lat), formatCoord(p.lng));
                pushAddress(row, locationText(p));
                row.push(
                    p.maps_url || '',
                    p.speed ?? 0,
                    formatCoord(p.heading),
                    formatIgnition(p.ignition),
                    p.status || '',
                );
                rows.push(row);
            }));
        } else if (type === 'overspeeds') {
            devices.forEach((d) => (d.overspeeds || []).forEach((o) => {
                const row = [
                    d.device_name,
                    d.plate || '',
                    displayStartTime(o),
                    displayEndTime(o),
                    formatDuration(o.duration_seconds),
                    o.max_speed_kmh ?? '',
                    o.limit_kmh ?? d.overspeed_limit_kmh ?? '',
                ];
                pushAddress(row, locationText(o));
                row.push(o.maps_url || '');
                rows.push(row);
            }));
        } else if (type === 'zone_inout') {
            devices.forEach((d) => (d.zone_events || d.events || []).forEach((e) => {
                const row = [
                    d.device_name,
                    d.plate || '',
                    e.time_display || e.time || '',
                    e.event_type || e.type || '',
                    e.geofence || '',
                    e.title || '',
                ];
                pushCoords(row, formatCoord(e.lat), formatCoord(e.lng));
                pushAddress(row, locationText(e));
                row.push(e.maps_url || '');
                rows.push(row);
            }));
        } else if (type === 'fuel_fillings') {
            devices.forEach((d) => (d.fillings || []).forEach((f) => {
                const row = [
                    d.device_name,
                    d.plate || '',
                    f.time || '',
                    f.liters ?? '',
                    f.level_before ?? '',
                    f.level_after ?? '',
                ];
                pushCoords(row, formatCoord(f.lat), formatCoord(f.lng));
                pushAddress(row, locationText(f));
                row.push(f.maps_url || '');
                rows.push(row);
            }));
        } else if (type === 'current_position') {
            devices.forEach((d) => {
                const row = [
                    d.device_name,
                    d.plate || '',
                    d.driver || '',
                    d.time || '',
                    d.status || '',
                    d.speed ?? '',
                    d.heading ?? '',
                    d.altitude ?? '',
                    formatIgnition(d.ignition),
                ];
                pushCoords(row, formatCoord(d.lat), formatCoord(d.lng));
                pushAddress(row, locationText(d));
                row.push(d.maps_url || '');
                rows.push(row);
            });
        } else if (type === 'object_info') {
            devices.forEach((d) => rows.push([
                d.device_name,
                d.plate || '',
                d.driver || '',
                d.imei || '',
                d.model || '',
                d.phone || '',
                d.status || '',
                d.last_update || '',
                d.speed ?? '',
                formatIgnition(d.ignition),
                d.odometer_km ?? '',
                d.maps_url || '',
            ]));
        } else if (type === 'service') {
            devices.forEach((d) => (d.services || []).forEach((s) => rows.push([
                d.device_name,
                d.plate || '',
                s.name || '',
                s.summary || '',
                s.status || '',
                s.current_odometer_label || '',
                s.odometer_left_label || '',
                s.days_left_label || '',
            ])));
        } else if (type === 'tasks') {
            devices.forEach((d) => (d.tasks || []).forEach((t) => rows.push([
                d.device_name,
                d.plate || '',
                t.name || '',
                t.start || '',
                t.destination || '',
                t.priority || '',
                t.status || '',
                t.time_from || '',
                t.time_to || '',
            ])));
        } else if (type === 'speed' || type === 'altitude') {
            devices.forEach((d) => (d.series || []).forEach((p) => rows.push([
                d.device_name,
                d.plate || '',
                p.time || '',
                p.value ?? '',
                formatCoord(p.lat),
                formatCoord(p.lng),
                p.maps_url || '',
            ])));
        } else if (type === 'ignition') {
            devices.forEach((d) => (d.changes || []).forEach((c) => rows.push([
                d.device_name,
                d.plate || '',
                c.time || '',
                formatIgnition(c.ignition),
                formatCoord(c.lat),
                formatCoord(c.lng),
                c.maps_url || '',
            ])));
        } else if (type === 'route') {
            devices.forEach((d) => rows.push([
                d.device_name,
                d.plate || '',
                d.point_count,
                d.total_distance_km ?? 0,
                formatDuration(d.moving_time_seconds),
                formatDuration(d.stopped_time_seconds),
                formatDuration(d.idle_time_seconds),
                formatDuration(d.parking_time_seconds),
                formatDuration(d.offline_time_seconds),
                d.max_speed_kmh ?? 0,
                d.average_speed_kmh ?? 0,
                d.trip_count ?? 0,
                d.start_time_display || formatReportTime(d.start_time) || d.start_time || '',
                d.end_time_display || formatReportTime(d.end_time) || d.end_time || '',
                formatDuration(d.total_duration_seconds),
            ]));
        }

        return { columns, rows, type, devices, totals: data.totals || {}, meta: data.meta || {} };
    }

    function renderKpis(flat) {
        const wrap = $('gtReportKpis');
        if (!wrap) return;

        const totals = flat.totals || {};
        const type = flat.type;
        let items = [];

        if (type === 'summary' || type === 'route') {
            items = [
                [i18n.kpiDevices, totals.device_count ?? flat.devices.length],
                [i18n.kpiDistance, `${totals.total_distance_km ?? 0} km`],
                [i18n.kpiMoving, formatDuration(totals.moving_time_seconds)],
                [i18n.kpiStopped, formatDuration(totals.stopped_time_seconds)],
                [i18n.kpiTrips, totals.trip_count ?? 0],
                [i18n.kpiStops, totals.stop_count ?? 0],
                [i18n.kpiMaxSpeed, `${totals.max_speed_kmh ?? 0} km/h`],
            ];
            if (type === 'route') {
                items.push([i18n.kpiPoints, totals.point_count ?? 0]);
            }
        } else if (type === 'trips' || type === 'trips_stops') {
            items = [
                [i18n.kpiDevices, totals.device_count ?? flat.devices.length],
                [i18n.kpiTrips, totals.trip_count ?? 0],
                [i18n.kpiStops, totals.stop_count ?? 0],
                [i18n.kpiDistance, `${totals.total_distance_km ?? 0} km`],
            ];
        } else if (type === 'mileage') {
            items = [
                [i18n.kpiDevices, totals.device_count ?? flat.devices.length],
                [i18n.kpiDistance, `${totals.total_distance_km ?? 0} km`],
                [i18n.kpiDays || 'Days', totals.day_count ?? flat.rows.length],
                [i18n.kpiTrips, totals.trip_count ?? 0],
            ];
        } else if (type === 'odometer') {
            items = [
                [i18n.kpiDevices, totals.device_count ?? flat.devices.length],
                [i18n.kpiDistance, `${totals.total_distance_km ?? 0} km`],
                [i18n.kpiMoving, formatDuration(totals.moving_time_seconds)],
            ];
        } else if (type === 'diesel') {
            items = [
                [i18n.kpiDevices, totals.device_count ?? flat.devices.length],
                [i18n.kpiDistance, `${totals.total_distance_km ?? 0} km`],
                [i18n.kpiFuel || 'Diesel used', `${totals.fuel_liters ?? 0} L`],
                [i18n.kpiEfficiency || 'Efficiency', `${totals.efficiency ?? 0} L/100km`],
                [i18n.kpiTrips, totals.trip_count ?? 0],
            ];
        } else if (type === 'stops') {
            items = [
                [i18n.kpiDevices, totals.device_count ?? flat.devices.length],
                [i18n.kpiStops, totals.stop_count ?? flat.rows.length],
            ];
        } else if (type === 'events') {
            items = [
                [i18n.kpiDevices, totals.device_count ?? flat.devices.length],
                [i18n.kpiEvents, totals.event_count ?? flat.rows.length],
            ];
        } else if (type === 'positions') {
            items = [
                [i18n.kpiDevices, totals.device_count ?? flat.devices.length],
                [i18n.kpiPoints, totals.position_count ?? flat.rows.length],
            ];
        }

        const after = $('gtReportKpisAfter');
        const mapEl = $('gtReportMap');
        const html = items.length
            ? items.map(([label, value]) => (
                `<div class="gt-report-kpi"><span class="gt-report-kpi-label">${escapeHtml(label)}</span><span class="gt-report-kpi-value">${escapeHtml(value)}</span></div>`
            )).join('')
            : '';

        if (!items.length) {
            wrap.hidden = true;
            wrap.innerHTML = '';
            if (after) { after.hidden = true; after.innerHTML = ''; }
            return;
        }

        wrap.hidden = false;
        wrap.innerHTML = html;

        // Repeat stats under the map when a route map is shown (esp. mobile scroll).
        if (after) {
            const mapVisible = mapEl && !mapEl.hidden;
            after.hidden = !mapVisible;
            after.innerHTML = mapVisible ? html : '';
        }
    }

    function renderHead() {
        $('gtReportHead').innerHTML = state.columns
            .map((c) => `<th>${escapeHtml(c)}</th>`)
            .join('');
    }

    function renderPage() {
        const body = $('gtReportBody');
        const total = state.rows.length;
        const pages = Math.max(1, Math.ceil(total / state.pageSize));
        state.page = Math.min(Math.max(1, state.page), pages);

        const start = (state.page - 1) * state.pageSize;
        const end = Math.min(start + state.pageSize, total);

        let html = '';
        for (let i = start; i < end; i++) {
            const cells = state.rows[i];
            html += '<tr>';
            for (let c = 0; c < cells.length; c++) {
                const val = cells[c];
                if (isMapsUrl(val)) {
                    const label = escapeHtml(i18n.openMaps || 'Open in Maps');
                    html += `<td><a class="gt-report-maps-link" href="${escapeAttr(String(val))}" target="_blank" rel="noopener noreferrer">${label}</a></td>`;
                    continue;
                }
                const isNum = typeof val === 'number' || (c > 0 && /^-?\d/.test(String(val)));
                html += `<td>${isNum ? numCell(val) : escapeHtml(val)}</td>`;
            }
            html += '</tr>';
        }
        body.innerHTML = html;

        renderPager(total, start, end, pages);
    }

    function renderPager(total, start, end, pages) {
        const count = $('gtReportCount');
        if (count) {
            const tpl = i18n.pagerRange || ':from–:to of :total';
            count.textContent = total
                ? tpl
                    .replace(':from', String(start + 1))
                    .replace(':to', String(end))
                    .replace(':total', String(total))
                : '';
        }

        const pager = $('gtReportPager');
        if (!pager) return;

        const sizeSel = `<select class="form-select form-select-sm" id="gtReportPageSize" title="${escapeHtml(i18n.rowsPerPage || 'Rows per page')}">${
            PAGE_SIZES.map((n) => `<option value="${n}" ${n === state.pageSize ? 'selected' : ''}>${n}</option>`).join('')
        }</select>`;

        const prevDis = state.page <= 1 ? 'disabled' : '';
        const nextDis = state.page >= pages ? 'disabled' : '';
        const pageTpl = i18n.pageOf || ':page / :pages';
        const pageLabel = pageTpl
            .replace(':page', String(state.page))
            .replace(':pages', String(pages));

        pager.innerHTML = `${sizeSel}
            <button type="button" class="btn btn-sm btn-outline-secondary" id="gtReportPrev" ${prevDis}>&lsaquo;</button>
            <span class="small text-muted">${pageLabel}</span>
            <button type="button" class="btn btn-sm btn-outline-secondary" id="gtReportNext" ${nextDis}>&rsaquo;</button>`;

        $('gtReportPageSize').addEventListener('change', (e) => {
            state.pageSize = parseInt(e.target.value, 10) || PAGE_SIZES[0];
            state.page = 1;
            renderPage();
        });
        $('gtReportPrev').addEventListener('click', () => { state.page--; renderPage(); });
        $('gtReportNext').addEventListener('click', () => { state.page++; renderPage(); });
    }

    function setLoading(on, progressText) {
        state.loading = on;
        const overlay = $('gtReportOverlay');
        if (overlay) overlay.hidden = !on;
        const btn = $('gtReportRun');
        if (btn) btn.disabled = on;
        const progressEl = $('gtReportLoadingText');
        if (progressEl) progressEl.textContent = progressText || '';
        if (on) {
            state.reportLoaded = false;
            const empty = $('gtReportEmpty');
            if (empty) { empty.hidden = true; empty.textContent = ''; }
        }
        syncExportButtons();
    }

    function resetReportView() {
        state.columns = projectColumns(columnsFor($('gtReportType').value));
        state.rows = [];
        state.page = 1;
        state.lastPayload = null;
        state.reportLoaded = false;
        syncExportButtons();

        const empty = $('gtReportEmpty');
        if (empty) { empty.hidden = true; empty.textContent = ''; }

        $('gtReportHead').innerHTML = '';
        $('gtReportBody').innerHTML = '';
        $('gtReportPager').innerHTML = '';
        const count = $('gtReportCount');
        if (count) count.textContent = '';

        const kpis = $('gtReportKpis');
        if (kpis) { kpis.hidden = true; kpis.innerHTML = ''; }

        const kpisAfter = $('gtReportKpisAfter');
        if (kpisAfter) { kpisAfter.hidden = true; kpisAfter.innerHTML = ''; }

        const map = $('gtReportMap');
        if (map) map.hidden = true;
    }

    function showEmpty(msg) {
        resetReportView();
        const el = $('gtReportEmpty');
        if (!el) return;
        if (!msg) return;
        el.hidden = false;
        el.textContent = msg;
    }

    function loadMap(points) {
        const cb = '__gtReportMapReady';
        const init = () => {
            const baseMapOpts = { zoom: 11, center: { lat: points[0].lat, lng: points[0].lng } };
            const map = new google.maps.Map(
                $('gtReportMap'),
                global.GoogleMapsPlatform?.mapOptions
                    ? global.GoogleMapsPlatform.mapOptions(baseMapOpts, cfg.googleMapsMapId)
                    : baseMapOpts,
            );
            if (global.FleetMapRenderer) {
                const r = new global.FleetMapRenderer({ googleMaps: google, speedToColor: (s) => s > 80 ? '#ef4444' : s > 60 ? '#eab308' : '#22c55e' });
                r.attachMap(map);
                r.drawRoute(points);
            }
        };
        if (global.google && global.google.maps) { init(); return; }
        global[cb] = init;
        const s = document.createElement('script');
        s.src = `https://maps.googleapis.com/maps/api/js?key=${encodeURIComponent(cfg.googleMapsKey)}&loading=async&v=weekly&callback=${cb}`;
        document.head.appendChild(s);
    }

    function chunkIds(ids, size) {
        const chunks = [];
        for (let i = 0; i < ids.length; i += size) {
            chunks.push(ids.slice(i, i + size));
        }
        return chunks;
    }

    async function runReport() {
        const ids = selectedIds();
        if (!ids.length) {
            showEmpty(i18n.selectVehicle || 'Select at least one vehicle.');
            return;
        }
        if (isCustomMode() && !(selectedFieldKeys(false) || []).length) {
            showEmpty(i18n.customPickOne || 'Select at least one field.');
            return;
        }

        if (state.abortController) {
            state.abortController.abort();
        }

        const runId = ++state.runId;
        const abortController = new AbortController();
        state.abortController = abortController;
        const isCurrentRun = () => runId === state.runId;
        const requestedIds = [...ids];
        const deviceBatches = chunkIds(requestedIds, BATCH_DEVICE_SIZE);
        const windows = dateWindowsFromInputs();
        const jobs = [];
        windows.forEach((window) => {
            deviceBatches.forEach((batch) => {
                jobs.push({ ids: batch, from: window.from, to: window.to });
            });
        });

        resetReportView();
        renderHead();
        renderPage();
        setLoading(true, i18n.loadingReport || 'Loading report…');

        try {
            const partials = [];
            let done = 0;
            const totalJobs = jobs.length;
            const updateProgress = () => {
                if (!isCurrentRun()) return;
                const tpl = i18n.loadingProgress || 'Loading :done / :total…';
                setLoading(true, tpl.replace(':done', String(Math.min(done, totalJobs))).replace(':total', String(totalJobs)));
            };

            const consumePayload = (data, batchSize) => {
                if (!isCurrentRun() || !data) return;
                if (data._nonce != null && String(data._nonce) !== String(runId)) return;
                partials.push(data);
                done += batchSize || 1;
                updateProgress();
                const merged = mergeReportPayloads(partials, requestedIds);
                state.lastPayload = merged;
                const flat = projectFlat(flatten(applyClientFilters(merged)));
                state.columns = flat.columns;
                state.rows = flat.rows;
                renderKpis(flat);
                renderHead();
                renderPage();
            };

            updateProgress();
            await runPool(jobs, PARALLEL_DEVICE_LIMIT, async (job) => {
                if (!isCurrentRun()) return;
                consumePayload(
                    await fetchReportPayload(job.ids, abortController.signal, runId, job.from, job.to),
                    1,
                );
            });

            if (!isCurrentRun()) return;

            const merged = mergeReportPayloads(partials, requestedIds);
            state.lastPayload = merged;
            const flat = projectFlat(flatten(applyClientFilters(merged)));
            state.columns = flat.columns;
            state.rows = flat.rows;
            renderKpis(flat);
            renderHead();
            renderPage();

            if (flat.type === 'route') {
                const mapDevice = routeMapDevice(flat.devices || [], requestedIds);
                if (mapDevice?.points?.length && cfg.googleMapsKey) {
                    $('gtReportMap').hidden = false;
                    loadMap(mapDevice.points);
                    renderKpis(flat); // refresh after-map stats once map is visible
                }
            }

            updateFilterHelp();

            if (!state.rows.length) {
                // Keep payload so export can still run after an empty-but-successful load.
                const keptPayload = state.lastPayload;
                showEmpty(i18n.noData || 'No data for the selected report and period.');
                state.lastPayload = keptPayload;
            } else {
                const notice = reportNotice(flat);
                if (notice) {
                    const count = $('gtReportCount');
                    if (count) count.textContent = notice;
                }
            }
            if (isCurrentRun()) {
                state.reportLoaded = true;
            }
        } catch (err) {
            if (err.name === 'AbortError') return;
            if (!isCurrentRun()) return;
            console.error('[reports] generate failed', err);
            state.reportLoaded = false;
            showEmpty(err.message || i18n.loadFailed || 'Failed to load the report. Please try again.');
        } finally {
            if (isCurrentRun()) {
                state.abortController = null;
                setLoading(false, '');
                syncExportButtons();
            }
        }
    }

    async function exportFmt(fmt) {
        if (state.loading || state.exporting) {
            alert(i18n.waitUntilLoaded || i18n.loadingReport || 'Wait until the report finishes loading.');
            return;
        }
        if (!state.reportLoaded || !state.lastPayload) {
            alert(i18n.loadBeforeExport || i18n.exportFailed || 'Load the report first, then export.');
            return;
        }

        const ids = selectedIds();
        if (!ids.length) {
            showEmpty(i18n.selectVehicle || 'Select at least one vehicle.');
            return;
        }
        if (isCustomMode() && !(selectedFieldKeys(false) || []).length) {
            showEmpty(i18n.customPickOne || 'Select at least one field.');
            return;
        }

        if (ids.length > 25) {
            const ok = global.confirm?.(
                i18n.exportManyConfirm || 'Exporting many vehicles can take a while. Continue?',
            );
            if (ok === false) return;
        }

        const buttons = exportButtons();
        state.exporting = true;
        buttons.forEach((btn) => {
            btn.disabled = true;
            btn.dataset.exportLabel = btn.textContent;
        });
        syncExportButtons();

        try {
            if (ids.length <= EXPORT_BATCH_SIZE) {
                await exportFmtBatch(fmt, ids, buttons);
                return;
            }

            const batches = chunkIds(ids, EXPORT_BATCH_SIZE);
            for (let i = 0; i < batches.length; i++) {
                const batch = batches[i];
                buttons.forEach((btn) => {
                    btn.disabled = true;
                    btn.textContent = `${i18n.exportBatch || 'Export'} ${i + 1}/${batches.length}…`;
                });
                await exportFmtBatch(fmt, batch, buttons, i + 1);
            }
        } catch (err) {
            console.error('[reports] export failed', err);
            alert(err.message || i18n.exportFailed || 'Export failed. Please try again.');
        } finally {
            state.exporting = false;
            buttons.forEach((btn) => {
                if (btn.dataset.exportLabel) {
                    btn.textContent = btn.dataset.exportLabel;
                    delete btn.dataset.exportLabel;
                }
            });
            syncExportButtons();
        }
    }

    async function exportFmtBatch(fmt, ids, exportButtons, batchIndex) {
        const body = queryParamsForIds(ids, null);
        body.set('format', fmt);
        body.set('_ts', String(Date.now()));

        const res = await fetch(cfg.exportUrl, {
            method: 'POST',
            credentials: 'same-origin',
            cache: 'no-store',
            headers: {
                Accept: '*/*',
                'Content-Type': 'application/x-www-form-urlencoded',
                'X-CSRF-TOKEN': csrfToken(),
                'X-Requested-With': 'XMLHttpRequest',
                'Cache-Control': 'no-cache',
                Pragma: 'no-cache',
            },
            body: body.toString(),
        });

        const contentType = res.headers.get('Content-Type') || '';
        if (!res.ok || contentType.includes('json')) {
            let message = i18n.exportFailed || 'Export failed. Please try again.';
            try {
                const data = contentType.includes('json') ? await res.json() : null;
                if (data?.message) message = data.message;
            } catch (_) { /* ignore parse errors */ }
            throw new Error(message);
        }

        const blob = await res.blob();
        if (!blob.size) {
            throw new Error(i18n.exportFailed || 'Export failed. Please try again.');
        }

        const ext = fmt === 'xlsx' ? 'xls' : fmt;
        const reportType = $('gtReportType')?.value || 'summary';
        const suffix = batchIndex != null ? `-part${batchIndex}` : '';
        const filename = parseExportFilename(res.headers.get('Content-Disposition'))
            || `report-${reportType}${suffix}-${Date.now()}.${ext}`;

        triggerFileDownload(blob, filename);
    }

    function parseExportFilename(header) {
        if (!header) return null;
        const utf8 = /filename\*=UTF-8''([^;]+)/i.exec(header);
        if (utf8) {
            try {
                return decodeURIComponent(utf8[1].trim());
            } catch (_) {
                return utf8[1].trim();
            }
        }
        const plain = /filename="?([^";]+)"?/i.exec(header);
        return plain ? plain[1].trim() : null;
    }

    function triggerFileDownload(blob, filename) {
        const url = URL.createObjectURL(blob);
        const root = global.top || global;
        const doc = root.document || document;
        const link = doc.createElement('a');
        link.href = url;
        link.download = filename;
        link.style.display = 'none';
        doc.body.appendChild(link);
        link.click();
        window.setTimeout(() => {
            URL.revokeObjectURL(url);
            link.remove();
        }, 1500);
    }

    function vehiclePickerLabels() {
        return [...document.querySelectorAll('#gtReportVehicles label[data-search]')];
    }

    function visibleVehicleCheckboxes() {
        return vehiclePickerLabels()
            .filter((label) => !label.classList.contains('is-filtered-out'))
            .map((label) => label.querySelector('input[type="checkbox"]'))
            .filter(Boolean);
    }

    function filterReportVehicles(query) {
        const q = String(query || '').trim().toLowerCase();
        const labels = vehiclePickerLabels();
        let visible = 0;
        labels.forEach((label) => {
            const hay = label.getAttribute('data-search') || (label.textContent || '').toLowerCase();
            const match = !q || hay.includes(q);
            label.classList.toggle('is-filtered-out', !match);
            if (match) visible += 1;
        });
        const empty = $('gtReportVehicleEmpty');
        if (empty) empty.hidden = visible > 0 || labels.length === 0;
    }

    function setVehicleChecks(checked) {
        // When a search filter is active, only toggle the visible matches.
        const boxes = visibleVehicleCheckboxes();
        const targets = boxes.length
            ? boxes
            : [...document.querySelectorAll('#gtReportVehicles input[type="checkbox"]')];
        targets.forEach((el) => {
            el.checked = checked;
        });
    }

    function pad(n) {
        return String(n).padStart(2, '0');
    }

    function fmtDateTime(d) {
        return `${d.getFullYear()}-${pad(d.getMonth() + 1)}-${pad(d.getDate())}T${pad(d.getHours())}:${pad(d.getMinutes())}`;
    }

    function applyDatePreset(preset) {
        const now = new Date();
        let start;
        let end;

        if (preset === 'yesterday') {
            start = new Date(now);
            start.setDate(start.getDate() - 1);
            start.setHours(0, 0, 0, 0);
            end = new Date(start);
            end.setHours(23, 59, 0, 0);
        } else if (preset === '7d') {
            end = new Date(now);
            end.setHours(23, 59, 0, 0);
            start = new Date(now);
            start.setDate(start.getDate() - 6);
            start.setHours(0, 0, 0, 0);
        } else {
            start = new Date(now);
            start.setHours(0, 0, 0, 0);
            end = new Date(now);
            end.setHours(23, 59, 0, 0);
        }

        $('gtReportFrom').value = fmtDateTime(start);
        $('gtReportTo').value = fmtDateTime(end);
    }

    function initDateTimes() {
        applyDatePreset('today');
    }

    function bindClick(id, handler) {
        $(id)?.addEventListener('click', handler);
    }

    bindClick('gtReportRun', runReport);
    $('gtReportType')?.addEventListener('change', () => {
        updateFilterVisibility();
        persistFilters();
        if (isCustomMode()) rebuildCustomFields();
        resetReportView();
        state.columns = projectColumns(columnsFor($('gtReportType').value));
        renderHead();
        renderPage();
        syncExportButtons();
    });
    bindClick('gtReportCsv', () => exportFmt('csv'));
    bindClick('gtReportXlsx', () => exportFmt('xlsx'));
    bindClick('gtReportPdf', () => exportFmt('pdf'));
    $('gtReportSelectAll')?.addEventListener('click', () => setVehicleChecks(true));
    $('gtReportSelectNone')?.addEventListener('click', () => setVehicleChecks(false));
    $('gtReportVehicleSearch')?.addEventListener('input', (ev) => {
        filterReportVehicles(ev.target?.value);
    });
    document.querySelectorAll('[data-report-preset]').forEach((btn) => {
        btn.addEventListener('click', () => applyDatePreset(btn.getAttribute('data-report-preset')));
    });

    bindClick('gtReportModeStandard', () => setReportMode('standard'));
    bindClick('gtReportModeCustom', () => setReportMode('custom'));
    bindClick('gtCustomFieldsSelectAll', () => {
        document.querySelectorAll('#gtCustomFields input[type="checkbox"]').forEach((el) => { el.checked = true; });
        const type = $('gtReportType')?.value || 'summary';
        saveCustomFields(type, selectedFieldKeys(false) || []);
        if (state.lastPayload) rerenderFromPayload(state.lastPayload);
    });
    bindClick('gtCustomFieldsClear', () => {
        document.querySelectorAll('#gtCustomFields input[type="checkbox"]').forEach((el) => { el.checked = false; });
        const type = $('gtReportType')?.value || 'summary';
        saveCustomFields(type, []);
        if (state.lastPayload) rerenderFromPayload(state.lastPayload);
    });

    const filterPersistIds = [
        'gtFilterIgnoreEmpty', 'gtFilterShowCoordinates', 'gtFilterShowAddresses',
        'gtFilterMarkersInstead', 'gtFilterZonesInstead', 'gtFilterStops',
        'gtFilterStopsCustom', 'gtFilterSpeedLimit',
    ];
    filterPersistIds.forEach((id) => {
        const el = $(id);
        if (!el) return;
        el.addEventListener('change', () => onFilterChanged(id));
        // Debounce free-text numeric filters so we don't spam regenerate while typing.
        if (id === 'gtFilterStopsCustom' || id === 'gtFilterSpeedLimit') {
            let timer = null;
            el.addEventListener('input', () => {
                persistFilters();
                clearTimeout(timer);
                timer = setTimeout(() => onFilterChanged(id), 500);
            });
        }
    });

    restoreFilters();
    updateFilterVisibility();
    updateFilterHelp();
    initDateTimes();
    filterReportVehicles('');
    if (lockedType) {
        const typeEl = $('gtReportType');
        if (typeEl) typeEl.value = lockedType;
        setReportMode('standard');
        state.columns = projectColumns(columnsFor(lockedType));
        renderHead();
        renderPage();
    } else {
        try {
            const savedMode = localStorage.getItem(MODE_STORAGE_KEY);
            setReportMode(savedMode === 'custom' ? 'custom' : 'standard');
        } catch (_) {
            setReportMode('standard');
        }
    }
    syncExportButtons();
})(window);
