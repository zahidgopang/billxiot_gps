(function (global) {
    'use strict';
    const cfg = global.TRACKING_REPORTS_CONFIG;
    if (!cfg) return;

    const PAGE_SIZES = [50, 100, 250, 500];
    const state = {
        columns: [],
        rows: [],
        page: 1,
        pageSize: 50,
        loading: false,
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
        if (total <= 0) return '0s';
        const h = Math.floor(total / 3600);
        const m = Math.floor((total % 3600) / 60);
        const s = total % 60;
        const parts = [];
        if (h) parts.push(`${h}h`);
        if (h || m) parts.push(`${m}m`);
        if (!h) parts.push(`${s}s`);
        return parts.join(' ');
    }

    function selectedIds() {
        return [...document.querySelectorAll('#gtReportVehicles input:checked')].map((el) => el.value);
    }

    function queryParams() {
        const p = new URLSearchParams();
        p.set('type', $('gtReportType').value);
        p.set('from', $('gtReportFrom').value);
        p.set('to', $('gtReportTo').value);
        p.set('ids', selectedIds().join(','));
        return p;
    }

    /** Flatten the API payload into columns + a single flat rows array (fast to paginate/render). */
    function flatten(data) {
        const type = data.type;
        const devices = data.devices || [];
        let columns = [];
        const rows = [];

        if (type === 'summary') {
            columns = ['Vehicle', 'Distance (km)', 'Max speed (km/h)', 'Stops', 'Duration'];
            devices.forEach((d) => rows.push([
                d.device_name, d.total_distance_km, d.max_speed_kmh, d.stop_count, formatDuration(d.total_duration_seconds),
            ]));
        } else if (type === 'trips') {
            columns = ['Vehicle', 'Start', 'End', 'Distance (km)', 'Duration'];
            devices.forEach((d) => (d.trips || []).forEach((t) => rows.push([
                d.device_name, t.start_time || '', t.end_time || '', t.distance_km, formatDuration(t.duration_seconds),
            ])));
        } else if (type === 'stops') {
            columns = ['Vehicle', 'Start', 'Duration', 'Lat', 'Lng'];
            devices.forEach((d) => (d.stops || []).forEach((s) => rows.push([
                d.device_name, s.start_display || s.start || '', formatDuration(s.duration_seconds), s.lat, s.lng,
            ])));
        } else if (type === 'events') {
            columns = ['Vehicle', 'Time', 'Title', 'Message'];
            devices.forEach((d) => (d.events || []).forEach((e) => rows.push([
                d.device_name, e.time || '', e.title || '', e.message || '',
            ])));
        } else if (type === 'route') {
            columns = ['Vehicle', 'Points'];
            devices.forEach((d) => rows.push([d.device_name, d.point_count]));
        }

        return { columns, rows, type, devices };
    }

    function renderHead() {
        $('gtReportHead').innerHTML = state.columns.map((c) => `<th>${escapeHtml(c)}</th>`).join('');
    }

    /** Render only the current page — keeps the DOM small and rendering instant for huge datasets. */
    function renderPage() {
        const body = $('gtReportBody');
        const total = state.rows.length;
        const pages = Math.max(1, Math.ceil(total / state.pageSize));
        state.page = Math.min(Math.max(1, state.page), pages);

        const start = (state.page - 1) * state.pageSize;
        const end = Math.min(start + state.pageSize, total);

        // Build the whole page as one string, then assign once (avoids O(n^2) innerHTML +=).
        let html = '';
        for (let i = start; i < end; i++) {
            const cells = state.rows[i];
            html += '<tr>';
            for (let c = 0; c < cells.length; c++) html += `<td>${escapeHtml(cells[c])}</td>`;
            html += '</tr>';
        }
        body.innerHTML = html;

        renderPager(total, start, end, pages);
    }

    function renderPager(total, start, end, pages) {
        const count = $('gtReportCount');
        if (count) count.textContent = total ? `${start + 1}–${end} of ${total}` : '';

        const pager = $('gtReportPager');
        if (!pager) return;
        if (total <= state.pageSize && state.pageSize === PAGE_SIZES[0] && pages <= 1) {
            // still show page-size selector even for small sets
        }

        const sizeSel = `<select class="form-select form-select-sm" id="gtReportPageSize" title="Rows per page">${
            PAGE_SIZES.map((n) => `<option value="${n}" ${n === state.pageSize ? 'selected' : ''}>${n}/page</option>`).join('')
        }</select>`;

        const prevDis = state.page <= 1 ? 'disabled' : '';
        const nextDis = state.page >= pages ? 'disabled' : '';
        pager.innerHTML = `${sizeSel}
            <button type="button" class="btn btn-sm btn-outline-secondary" id="gtReportPrev" ${prevDis}>&lsaquo;</button>
            <span class="small text-muted">${state.page} / ${pages}</span>
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
    }

    function loadMap(points) {
        const cb = '__gtReportMapReady';
        const init = () => {
            const map = new google.maps.Map($('gtReportMap'), { zoom: 11, center: { lat: points[0].lat, lng: points[0].lng } });
            if (global.FleetMapRenderer) {
                const r = new global.FleetMapRenderer({ googleMaps: google, speedToColor: (s) => s > 80 ? '#ef4444' : s > 60 ? '#eab308' : '#22c55e' });
                r.attachMap(map);
                r.drawRoute(points);
            }
        };
        if (global.google && global.google.maps) { init(); return; }
        global[cb] = init;
        const s = document.createElement('script');
        s.src = `https://maps.googleapis.com/maps/api/js?key=${encodeURIComponent(cfg.googleMapsKey)}&callback=${cb}`;
        document.head.appendChild(s);
    }

    async function runReport() {
        if (state.loading) return;
        $('gtReportMap').hidden = true;
        setLoading(true);
        try {
            const res = await fetch(`${cfg.generateUrl}?${queryParams()}&_=${Date.now()}`, {
                credentials: 'same-origin',
                cache: 'no-store',
                headers: { Accept: 'application/json' },
            });
            if (!res.ok) throw new Error(`HTTP ${res.status}`);
            const data = await res.json();
            const flat = flatten(data);
            state.columns = flat.columns;
            state.rows = flat.rows;
            state.page = 1;

            if (flat.type === 'route' && flat.devices[0]?.points?.length && cfg.googleMapsKey) {
                $('gtReportMap').hidden = false;
                loadMap(flat.devices[0].points);
            }

            if (!state.rows.length) {
                showEmpty('No data for the selected report and period.');
            } else {
                showEmpty(null);
                renderHead();
                renderPage();
            }
        } catch (err) {
            console.error('[reports] generate failed', err);
            showEmpty('Failed to load the report. Please try again.');
        } finally {
            setLoading(false);
        }
    }

    function exportFmt(fmt) {
        window.location.href = `${cfg.exportUrl}?${queryParams()}&format=${fmt}`;
    }

    $('gtReportRun')?.addEventListener('click', runReport);
    $('gtReportCsv')?.addEventListener('click', () => exportFmt('csv'));
    $('gtReportXlsx')?.addEventListener('click', () => exportFmt('xlsx'));
    $('gtReportPdf')?.addEventListener('click', () => exportFmt('pdf'));

    const today = new Date().toISOString().slice(0, 10);
    $('gtReportFrom').value = today;
    $('gtReportTo').value = today;
})(window);
