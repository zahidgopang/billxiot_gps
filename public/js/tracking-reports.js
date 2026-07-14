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

    const state = {
        columns: [],
        rows: [],
        page: 1,
        pageSize: 50,
        loading: false,
        lastPayload: null,
        runId: 0,
        abortController: null,
    };

    const $ = (id) => document.getElementById(id);

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
        return p;
    }

    function csrfToken() {
        return document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '';
    }

    function columnsFor(type) {
        return colSets[type] || colSets.summary || [];
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
        const fromRaw = (dateTimeParam('gtReportFrom') || '').slice(0, 10);
        const toRaw = (dateTimeParam('gtReportTo') || '').slice(0, 10);
        if (!/^\d{4}-\d{2}-\d{2}$/.test(fromRaw) || !/^\d{4}-\d{2}-\d{2}$/.test(toRaw)) {
            return [{ from: dateTimeParam('gtReportFrom'), to: dateTimeParam('gtReportTo') }];
        }
        let start = new Date(`${fromRaw}T00:00:00`);
        let end = new Date(`${toRaw}T00:00:00`);
        if (end < start) {
            const tmp = start;
            start = end;
            end = tmp;
        }
        const windows = [];
        const maxDays = 2;
        let cursor = new Date(start);
        while (cursor <= end) {
            const windowEnd = new Date(cursor);
            windowEnd.setDate(windowEnd.getDate() + (maxDays - 1));
            const clamped = windowEnd > end ? new Date(end) : windowEnd;
            const fmt = (d) => `${d.getFullYear()}-${String(d.getMonth() + 1).padStart(2, '0')}-${String(d.getDate()).padStart(2, '0')}`;
            windows.push({ from: fmt(cursor), to: fmt(clamped) });
            cursor = new Date(clamped);
            cursor.setDate(cursor.getDate() + 1);
        }
        return windows.length ? windows : [{ from: fromRaw, to: toRaw }];
    }

    function mergeDeviceWindowRows(parts) {
        if (!parts.length) return null;
        if (parts.length === 1) return parts[0];
        const base = { ...parts[0] };
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
        base.efficiency = base.total_distance_km > 0 && base.fuel_liters > 0
            ? round2((base.fuel_liters / base.total_distance_km) * 100)
            : 0;

        ['trips', 'stops', 'segments', 'days', 'events', 'positions', 'points'].forEach((key) => {
            const rows = cat(key);
            if (rows.length) {
                base[key] = rows;
                if (key === 'trips') base.trip_count = rows.length;
                if (key === 'stops') base.stop_count = rows.length;
                if (key === 'days') base.day_count = rows.length;
                if (key === 'events') base.event_count = rows.length;
                if (key === 'positions') base.position_count = rows.length;
                if (key === 'points') base.point_count = rows.length;
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
        } else if (first.type === 'diesel') {
            totals.device_count = devices.length;
            totals.total_distance_km = round2(devices.reduce((s, d) => s + (Number(d.total_distance_km) || 0), 0));
            totals.fuel_liters = round2(devices.reduce((s, d) => s + (Number(d.fuel_liters) || 0), 0));
            totals.trip_count = devices.reduce((s, d) => s + (Number(d.trip_count) || 0), 0);
            totals.day_count = devices.reduce((s, d) => s + (Number(d.day_count) || 0), 0);
            totals.efficiency = totals.total_distance_km > 0 && totals.fuel_liters > 0
                ? round2((totals.fuel_liters / totals.total_distance_km) * 100)
                : 0;
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
                d.start_time || '',
                d.end_time || '',
                formatDuration(d.total_duration_seconds),
            ]));
        } else if (type === 'trips') {
            devices.forEach((d) => (d.trips || []).forEach((t) => {
                rows.push([
                    d.device_name,
                    d.plate || '',
                    d.driver || '',
                    t.start_time || '',
                    t.end_time || '',
                    formatCoord(t.start_lat),
                    formatCoord(t.start_lng),
                    formatCoord(t.end_lat),
                    formatCoord(t.end_lng),
                    t.start_maps_url || '',
                    t.end_maps_url || '',
                    t.distance_km,
                    formatDuration(t.duration_seconds),
                    formatDuration(t.moving_time_seconds),
                    t.stop_count ?? 0,
                    t.route_point_count ?? 0,
                    t.max_speed_kmh,
                    t.average_speed_kmh,
                ]);
                (t.stops || []).forEach((s) => {
                    rows.push([
                        d.device_name,
                        d.plate || '',
                        '',
                        s.start_display || s.start || '',
                        s.end_display || s.end || '',
                        formatCoord(s.lat),
                        formatCoord(s.lng),
                        '',
                        '',
                        s.maps_url || '',
                        '',
                        '',
                        formatDuration(s.duration_seconds),
                        '',
                        s.status_label || 'Stop',
                        '',
                        '',
                        '',
                    ]);
                });
            }));
        } else if (type === 'stops') {
            devices.forEach((d) => (d.stops || []).forEach((s) => rows.push([
                d.device_name,
                d.plate || '',
                s.status_label || '',
                s.start_display || s.start || '',
                s.end_display || s.end || '',
                formatDuration(s.duration_seconds),
                formatCoord(s.lat),
                formatCoord(s.lng),
                s.maps_url || '',
            ])));
        } else if (type === 'trips_stops') {
            devices.forEach((d) => (d.segments || []).forEach((seg) => {
                const isTrip = seg.kind === 'trip';
                rows.push([
                    d.device_name,
                    d.plate || '',
                    seg.kind_label || seg.kind || '',
                    seg.start_time || seg.start_display || seg.start || '',
                    seg.end_time || seg.end_display || seg.end || '',
                    formatDuration(seg.duration_seconds),
                    isTrip ? (seg.distance_km ?? 0) : '',
                    formatCoord(isTrip ? seg.start_lat : seg.lat),
                    formatCoord(isTrip ? seg.start_lng : seg.lng),
                    seg.maps_url || seg.start_maps_url || '',
                    isTrip ? (seg.stop_count ?? 0) : '',
                ]);
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
                    d.start_time || '',
                    d.end_time || '',
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
                    t.start_time || '',
                    t.end_time || '',
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
            devices.forEach((d) => (d.events || []).forEach((e) => rows.push([
                d.device_name,
                d.plate || '',
                e.time_display || e.time || '',
                e.event_type || e.type || '',
                e.title || '',
                e.message || '',
                e.geofence || '',
                formatCoord(e.lat),
                formatCoord(e.lng),
                e.maps_url || '',
                e.speed ?? '',
            ])));
        } else if (type === 'positions') {
            devices.forEach((d) => (d.positions || []).forEach((p) => rows.push([
                d.device_name,
                d.plate || '',
                p.time_display || p.time || '',
                formatCoord(p.lat),
                formatCoord(p.lng),
                p.maps_url || '',
                p.speed ?? 0,
                formatCoord(p.heading),
                formatIgnition(p.ignition),
                p.status || '',
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
                d.start_time || '',
                d.end_time || '',
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

        if (!items.length) {
            wrap.hidden = true;
            wrap.innerHTML = '';
            return;
        }

        wrap.hidden = false;
        wrap.innerHTML = items.map(([label, value]) => (
            `<div class="gt-report-kpi"><span class="gt-report-kpi-label">${escapeHtml(label)}</span><span class="gt-report-kpi-value">${escapeHtml(value)}</span></div>`
        )).join('');
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
            const empty = $('gtReportEmpty');
            if (empty) { empty.hidden = true; empty.textContent = ''; }
        }
    }

    function resetReportView() {
        state.columns = columnsFor($('gtReportType').value);
        state.rows = [];
        state.page = 1;
        state.lastPayload = null;

        const empty = $('gtReportEmpty');
        if (empty) { empty.hidden = true; empty.textContent = ''; }

        $('gtReportHead').innerHTML = '';
        $('gtReportBody').innerHTML = '';
        $('gtReportPager').innerHTML = '';
        const count = $('gtReportCount');
        if (count) count.textContent = '';

        const kpis = $('gtReportKpis');
        if (kpis) { kpis.hidden = true; kpis.innerHTML = ''; }

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
                const flat = flatten(merged);
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
            const flat = flatten(merged);

            if (flat.type === 'route') {
                const mapDevice = routeMapDevice(flat.devices || [], requestedIds);
                if (mapDevice?.points?.length && cfg.googleMapsKey) {
                    $('gtReportMap').hidden = false;
                    loadMap(mapDevice.points);
                }
            }

            if (!state.rows.length) {
                showEmpty(i18n.noData || 'No data for the selected report and period.');
            } else {
                const notice = reportNotice(flat);
                if (notice) {
                    const count = $('gtReportCount');
                    if (count) count.textContent = notice;
                }
            }
        } catch (err) {
            if (err.name === 'AbortError') return;
            if (!isCurrentRun()) return;
            console.error('[reports] generate failed', err);
            showEmpty(err.message || i18n.loadFailed || 'Failed to load the report. Please try again.');
        } finally {
            if (isCurrentRun()) {
                state.abortController = null;
                setLoading(false, '');
            }
        }
    }

    async function exportFmt(fmt) {
        const ids = selectedIds();
        if (!ids.length) {
            showEmpty(i18n.selectVehicle || 'Select at least one vehicle.');
            return;
        }

        if (ids.length > 25) {
            const ok = global.confirm?.(
                i18n.exportManyConfirm || 'Exporting many vehicles can take a while. Continue?',
            );
            if (ok === false) return;
        }

        const exportButtons = ['gtReportCsv', 'gtReportXlsx', 'gtReportPdf']
            .map((id) => $(id))
            .filter(Boolean);
        exportButtons.forEach((btn) => { btn.disabled = true; });

        try {
            if (ids.length <= EXPORT_BATCH_SIZE) {
                await exportFmtBatch(fmt, ids, exportButtons);
                return;
            }

            const batches = chunkIds(ids, EXPORT_BATCH_SIZE);
            for (let i = 0; i < batches.length; i++) {
                const batch = batches[i];
                exportButtons.forEach((btn) => {
                    btn.disabled = true;
                    btn.dataset.exportLabel = btn.textContent;
                    btn.textContent = `${i18n.exportBatch || 'Export'} ${i + 1}/${batches.length}…`;
                });
                await exportFmtBatch(fmt, batch, exportButtons, i + 1);
            }
        } catch (err) {
            console.error('[reports] export failed', err);
            alert(err.message || i18n.exportFailed || 'Export failed. Please try again.');
        } finally {
            exportButtons.forEach((btn) => {
                btn.disabled = state.loading;
                if (btn.dataset.exportLabel) {
                    btn.textContent = btn.dataset.exportLabel;
                    delete btn.dataset.exportLabel;
                }
            });
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

    function setVehicleChecks(checked) {
        document.querySelectorAll('#gtReportVehicles input[type="checkbox"]').forEach((el) => {
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

    $('gtReportRun')?.addEventListener('click', runReport);
    $('gtReportType')?.addEventListener('change', () => {
        resetReportView();
        state.columns = columnsFor($('gtReportType').value);
        renderHead();
        renderPage();
    });
    $('gtReportCsv')?.addEventListener('click', () => exportFmt('csv'));
    $('gtReportXlsx')?.addEventListener('click', () => exportFmt('xlsx'));
    $('gtReportPdf')?.addEventListener('click', () => exportFmt('pdf'));
    $('gtReportSelectAll')?.addEventListener('click', () => setVehicleChecks(true));
    $('gtReportSelectNone')?.addEventListener('click', () => setVehicleChecks(false));
    document.querySelectorAll('[data-report-preset]').forEach((btn) => {
        btn.addEventListener('click', () => applyDatePreset(btn.getAttribute('data-report-preset')));
    });

    initDateTimes();
})(window);
