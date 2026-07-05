(function (global) {
    'use strict';
    const cfg = global.TRACKING_REPORTS_CONFIG;
    if (!cfg) return;

    const PAGE_SIZES = [50, 100, 250, 500];
    const i18n = cfg.i18n || {};
    const colSets = i18n.columns || {};

    const state = {
        columns: [],
        rows: [],
        page: 1,
        pageSize: 50,
        loading: false,
        lastPayload: null,
    };

    const $ = (id) => document.getElementById(id);

    function escapeHtml(value) {
        return String(value ?? '')
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;');
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

    function reportNotice(flat) {
        const meta = flat.meta || {};
        const parts = [];
        if (meta.devices_capped) {
            parts.push(i18n.devicesCapped || 'Showing first 50 vehicles. Narrow the selection for faster results.');
        }
        if (meta.positions_truncated) {
            parts.push(i18n.positionsTruncated || 'Large GPS datasets were trimmed per vehicle. Use CSV export for full data.');
        }
        return parts.join(' ');
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
            devices.forEach((d) => (d.trips || []).forEach((t) => rows.push([
                d.device_name,
                d.plate || '',
                d.driver || '',
                t.start_time || '',
                t.end_time || '',
                formatCoord(t.start_lat),
                formatCoord(t.start_lng),
                formatCoord(t.end_lat),
                formatCoord(t.end_lng),
                t.distance_km,
                formatDuration(t.duration_seconds),
                formatDuration(t.moving_time_seconds),
                t.max_speed_kmh,
                t.average_speed_kmh,
            ])));
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
            ])));
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
                e.speed ?? '',
            ])));
        } else if (type === 'positions') {
            devices.forEach((d) => (d.positions || []).forEach((p) => rows.push([
                d.device_name,
                d.plate || '',
                p.time_display || p.time || '',
                formatCoord(p.lat),
                formatCoord(p.lng),
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
        } else if (type === 'trips') {
            items = [
                [i18n.kpiDevices, totals.device_count ?? flat.devices.length],
                [i18n.kpiTrips, totals.trip_count ?? flat.rows.length],
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

    function setLoading(on) {
        state.loading = on;
        const overlay = $('gtReportOverlay');
        if (overlay) overlay.hidden = !on;
        const btn = $('gtReportRun');
        if (btn) btn.disabled = on;
        if (on) showEmpty(null);
    }

    function showEmpty(msg) {
        const el = $('gtReportEmpty');
        if (!el) return;
        if (!msg) { el.hidden = true; el.textContent = ''; return; }
        el.hidden = false;
        el.textContent = msg;
        $('gtReportHead').innerHTML = '';
        $('gtReportBody').innerHTML = '';
        $('gtReportPager').innerHTML = '';
        $('gtReportCount').textContent = '';
        const kpis = $('gtReportKpis');
        if (kpis) { kpis.hidden = true; kpis.innerHTML = ''; }
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

    async function runReport() {
        if (state.loading) return;
        if (!selectedIds().length) {
            showEmpty(i18n.selectVehicle || 'Select at least one vehicle.');
            return;
        }

        $('gtReportMap').hidden = true;
        setLoading(true);
        try {
            const body = queryParams();
            const res = await fetch(cfg.generateUrl, {
                method: 'POST',
                credentials: 'same-origin',
                cache: 'no-store',
                headers: {
                    Accept: 'application/json',
                    'Content-Type': 'application/x-www-form-urlencoded',
                    'X-CSRF-TOKEN': csrfToken(),
                    'X-Requested-With': 'XMLHttpRequest',
                },
                body: body.toString(),
            });

            const data = await res.json().catch(() => ({}));
            if (!res.ok || data.success === false) {
                throw new Error(data.message || `HTTP ${res.status}`);
            }

            state.lastPayload = data;
            const flat = flatten(data);
            state.columns = flat.columns;
            state.rows = flat.rows;
            state.page = 1;

            if (flat.type === 'route' && flat.devices[0]?.points?.length && cfg.googleMapsKey) {
                $('gtReportMap').hidden = false;
                loadMap(flat.devices[0].points);
            }

            if (!state.rows.length) {
                showEmpty(i18n.noData || 'No data for the selected report and period.');
            } else {
                showEmpty(null);
                renderKpis(flat);
                renderHead();
                renderPage();
                const notice = reportNotice(flat);
                if (notice) {
                    const count = $('gtReportCount');
                    if (count) count.textContent = notice;
                }
            }
        } catch (err) {
            console.error('[reports] generate failed', err);
            showEmpty(err.message || i18n.loadFailed || 'Failed to load the report. Please try again.');
        } finally {
            setLoading(false);
        }
    }

    function exportFmt(fmt) {
        if (!selectedIds().length) {
            showEmpty(i18n.selectVehicle || 'Select at least one vehicle.');
            return;
        }

        const form = document.createElement('form');
        form.method = 'POST';
        form.action = cfg.exportUrl;
        form.style.display = 'none';

        const addField = (name, value) => {
            const input = document.createElement('input');
            input.type = 'hidden';
            input.name = name;
            input.value = value;
            form.appendChild(input);
        };

        addField('_token', csrfToken());
        queryParams().forEach((value, key) => addField(key, value));
        addField('format', fmt);

        document.body.appendChild(form);
        form.submit();
        form.remove();
    }

    function setVehicleChecks(checked) {
        document.querySelectorAll('#gtReportVehicles input[type="checkbox"]').forEach((el) => {
            el.checked = checked;
        });
    }

    function pad(n) {
        return String(n).padStart(2, '0');
    }

    function initDateTimes() {
        const now = new Date();
        const start = new Date(now);
        start.setHours(0, 0, 0, 0);
        const end = new Date(now);
        end.setHours(23, 59, 0, 0);
        const fmt = (d) => `${d.getFullYear()}-${pad(d.getMonth() + 1)}-${pad(d.getDate())}T${pad(d.getHours())}:${pad(d.getMinutes())}`;
        $('gtReportFrom').value = fmt(start);
        $('gtReportTo').value = fmt(end);
    }

    $('gtReportRun')?.addEventListener('click', runReport);
    $('gtReportCsv')?.addEventListener('click', () => exportFmt('csv'));
    $('gtReportXlsx')?.addEventListener('click', () => exportFmt('xlsx'));
    $('gtReportPdf')?.addEventListener('click', () => exportFmt('pdf'));
    $('gtReportSelectAll')?.addEventListener('click', () => setVehicleChecks(true));
    $('gtReportSelectNone')?.addEventListener('click', () => setVehicleChecks(false));

    initDateTimes();
})(window);
