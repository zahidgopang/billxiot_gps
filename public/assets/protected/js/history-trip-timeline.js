/**
 * Shared History trip timeline UI — summary cards, day nav, chronological segments.
 * Used by /tracking History tab and /tracking/history page.
 */
(function (global) {
    'use strict';

    function esc(s) {
        return String(s ?? '')
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/"/g, '&quot;');
    }

    function formatDuration(sec) {
        const s = Math.max(0, Math.floor(Number(sec) || 0));
        const h = Math.floor(s / 3600);
        const m = Math.floor((s % 3600) / 60);
        if (h > 0) return h + 'h ' + m + 'm';
        if (m > 0) return m + 'm';
        return s + 's';
    }

    function pad2(n) {
        return String(n).padStart(2, '0');
    }

    function ymdLocal(d) {
        return d.getFullYear() + '-' + pad2(d.getMonth() + 1) + '-' + pad2(d.getDate());
    }

    function parseYmd(value) {
        const m = String(value || '').match(/^(\d{4})-(\d{2})-(\d{2})/);
        if (!m) return null;
        return new Date(Number(m[1]), Number(m[2]) - 1, Number(m[3]));
    }

    function addDaysYmd(ymd, delta) {
        const d = parseYmd(ymd) || new Date();
        d.setDate(d.getDate() + delta);
        return ymdLocal(d);
    }

    function normalizeStats(raw, segments) {
        const s = raw || {};
        const stopCount = s.stop_count != null
            ? Number(s.stop_count)
            : (Array.isArray(segments) ? segments.filter((x) => x.kind === 'parking').length : 0);
        return {
            distance_km: Number(s.total_distance_km ?? s.distance_km ?? 0).toFixed(1),
            move_seconds: Number(s.moving_time_seconds ?? s.move_seconds ?? 0),
            idle_seconds: Number(s.idle_time_seconds ?? s.idle_seconds ?? 0),
            parking_seconds: Number(s.parking_time_seconds ?? s.parking_seconds ?? 0),
            stop_seconds: Number(s.stopped_time_seconds ?? s.stop_seconds ?? 0),
            max_speed: Number(s.max_speed_kmh ?? s.top_speed ?? 0).toFixed(0),
            avg_speed: Number(s.average_speed_kmh ?? s.avg_speed ?? 0).toFixed(1),
            stop_count: stopCount,
        };
    }

    function kindMeta(kind) {
        switch (kind) {
            case 'trip_start':
                return { mark: '🟢', cls: 'htt-start', label: 'Start Trip' };
            case 'trip_end':
                return { mark: '🔴', cls: 'htt-end', label: 'End Trip' };
            case 'drive':
                return { mark: '🚗', cls: 'htt-drive', label: 'Driving' };
            case 'parking':
                return { mark: '📍', cls: 'htt-park', label: 'Parking' };
            case 'ignition_on':
                return { mark: '🔑', cls: 'htt-ign', label: 'Ignition ON' };
            case 'ignition_off':
                return { mark: '🔒', cls: 'htt-ign', label: 'Ignition OFF' };
            case 'overspeed':
                return { mark: '⚠️', cls: 'htt-warn', label: 'Overspeed' };
            case 'geofence_enter':
                return { mark: '📥', cls: 'htt-geo', label: 'Geofence Entry' };
            case 'geofence_exit':
                return { mark: '📤', cls: 'htt-geo', label: 'Geofence Exit' };
            case 'fuel':
                return { mark: '⛽', cls: 'htt-fuel', label: 'Fuel Drop' };
            default:
                return { mark: '•', cls: 'htt-other', label: kind || 'Event' };
        }
    }

    function coordsLabel(lat, lng) {
        if (lat == null || lng == null) return '';
        return Number(lat).toFixed(5) + ', ' + Number(lng).toFixed(5);
    }

    function segmentHtml(seg, idx, i18n) {
        const meta = kindMeta(seg.kind);
        const label = seg.kind_label || seg.title || meta.label;
        const time = seg.start_display || seg.arrival_time || String(seg.start || '').slice(0, 19);
        let detail = '';

        if (seg.kind === 'drive') {
            const parts = [];
            if (seg.distance_km != null) parts.push(Number(seg.distance_km).toFixed(1) + ' km');
            if (seg.duration_seconds) parts.push(formatDuration(seg.duration_seconds));
            if (seg.max_speed_kmh != null) parts.push('max ' + Number(seg.max_speed_kmh).toFixed(0) + ' km/h');
            detail = parts.join(' · ');
        } else if (seg.kind === 'parking') {
            const parts = [];
            if (seg.arrival_time || seg.start_display) {
                parts.push((i18n.arrival || 'Arrival') + ': ' + (seg.arrival_time || seg.start_display));
            }
            if (seg.departure_time || seg.end_display) {
                parts.push((i18n.departure || 'Departure') + ': ' + (seg.departure_time || seg.end_display));
            }
            if (seg.duration_seconds) {
                parts.push((i18n.duration || 'Duration') + ': ' + formatDuration(seg.duration_seconds));
            }
            const addr = seg.address || coordsLabel(seg.lat, seg.lng);
            if (addr) parts.push(addr);
            if (seg.distance_before_km != null) {
                parts.push((i18n.distanceBefore || 'Before') + ': ' + Number(seg.distance_before_km).toFixed(1) + ' km');
            }
            if (seg.max_speed_before_kmh != null) {
                parts.push((i18n.maxBefore || 'Max') + ': ' + Number(seg.max_speed_before_kmh).toFixed(0) + ' km/h');
            }
            detail = parts.join(' · ');
        } else {
            detail = seg.message || time || '';
        }

        const maps = seg.maps_url
            ? `<a class="htt-maps" href="${esc(seg.maps_url)}" target="_blank" rel="noopener">${esc(i18n.openMaps || 'Maps')}</a>`
            : '';

        return `<div class="htt-row ${meta.cls}" data-htt-idx="${idx}" role="button" tabindex="0">
            <span class="htt-mark">${meta.mark}</span>
            <span class="htt-body">
                <span class="htt-title">${esc(label)}</span>
                <span class="htt-meta">${esc(time)}${detail ? ' · ' + esc(detail) : ''}${maps ? ' · ' + maps : ''}</span>
            </span>
        </div>`;
    }

    function ensureStyles() {
        if (document.getElementById('htt-styles')) return;
        const style = document.createElement('style');
        style.id = 'htt-styles';
        style.textContent = `
.htt-day { display:flex; align-items:center; gap:.4rem; margin:.5rem 0; flex-wrap:wrap; }
.htt-day input[type="date"] { max-width:10.5rem; }
.htt-summary { display:grid; grid-template-columns:repeat(auto-fill,minmax(96px,1fr)); gap:.4rem; margin:.5rem 0; }
.htt-card { background:rgba(0,0,0,.03); border:1px solid rgba(0,0,0,.06); border-radius:10px; padding:.45rem .5rem; }
.htt-card-lbl { font-size:.65rem; text-transform:uppercase; letter-spacing:.03em; color:#86868b; font-weight:700; }
.htt-card-val { font-size:.9rem; font-weight:700; color:#1d1d1f; }
.htt-toolbar { display:flex; gap:.35rem; flex-wrap:wrap; margin:.35rem 0 .55rem; }
.htt-list { display:flex; flex-direction:column; gap:.15rem; overflow:auto; min-height:0; flex:1; }
.htt-row { display:flex; gap:.55rem; padding:.45rem .5rem; border-radius:10px; cursor:pointer; border:1px solid transparent; }
.htt-row:hover, .htt-row.is-active { background:rgba(0,122,255,.08); border-color:rgba(0,122,255,.18); }
.htt-mark { width:1.4rem; text-align:center; flex-shrink:0; line-height:1.4; }
.htt-body { display:flex; flex-direction:column; gap:.1rem; min-width:0; }
.htt-title { font-weight:650; font-size:.8125rem; color:#1d1d1f; }
.htt-meta { font-size:.72rem; color:#6e6e73; line-height:1.35; word-break:break-word; }
.htt-maps { color:#007aff; text-decoration:none; font-weight:600; }
.htt-empty { padding:1rem; color:#86868b; text-align:center; font-size:.85rem; }
.htt-vehicle { font-weight:650; font-size:.875rem; margin-bottom:.35rem; }
`;
        document.head.appendChild(style);
    }

    /**
     * @param {object} opts
     * @param {HTMLElement} opts.summaryEl
     * @param {HTMLElement} opts.listEl
     * @param {HTMLElement} [opts.dayEl]
     * @param {HTMLElement} [opts.exportEl]
     * @param {HTMLElement} [opts.vehicleEl]
     * @param {object} [opts.i18n]
     * @param {function} [opts.onSelect]
     * @param {function} [opts.onDayChange] — (ymd) => void
     * @param {function} [opts.onExport] — (format) => void
     * @param {string} [opts.geocodeUrl]
     */
    function createHistoryTripTimeline(opts) {
        ensureStyles();
        const i18n = opts.i18n || {};
        let segments = [];
        let stats = null;
        let currentDay = ymdLocal(new Date());

        function renderSummary() {
            if (!opts.summaryEl) return;
            const n = normalizeStats(stats, segments);
            const kmh = i18n.kmhUnit || 'km/h';
            opts.summaryEl.hidden = false;
            opts.summaryEl.innerHTML = `<div class="htt-summary">
                <div class="htt-card"><div class="htt-card-lbl">${esc(i18n.distance || 'Distance')}</div><div class="htt-card-val">${esc(n.distance_km)} km</div></div>
                <div class="htt-card"><div class="htt-card-lbl">${esc(i18n.driveTime || 'Drive')}</div><div class="htt-card-val">${esc(formatDuration(n.move_seconds))}</div></div>
                <div class="htt-card"><div class="htt-card-lbl">${esc(i18n.idleTime || 'Idle')}</div><div class="htt-card-val">${esc(formatDuration(n.idle_seconds))}</div></div>
                <div class="htt-card"><div class="htt-card-lbl">${esc(i18n.parkTime || 'Park')}</div><div class="htt-card-val">${esc(formatDuration(n.parking_seconds || n.stop_seconds))}</div></div>
                <div class="htt-card"><div class="htt-card-lbl">${esc(i18n.maxSpeed || 'Max')}</div><div class="htt-card-val">${esc(n.max_speed)} ${esc(kmh)}</div></div>
                <div class="htt-card"><div class="htt-card-lbl">${esc(i18n.avgSpeed || 'Avg')}</div><div class="htt-card-val">${esc(n.avg_speed)} ${esc(kmh)}</div></div>
                <div class="htt-card"><div class="htt-card-lbl">${esc(i18n.stops || 'Stops')}</div><div class="htt-card-val">${esc(String(n.stop_count))}</div></div>
            </div>`;
        }

        function renderList() {
            if (!opts.listEl) return;
            if (!segments.length) {
                opts.listEl.innerHTML = `<div class="htt-empty">${esc(i18n.noTimeline || 'No trips for this day')}</div>`;
                return;
            }
            opts.listEl.innerHTML = segments.map((seg, idx) => segmentHtml(seg, idx, i18n)).join('');
            opts.listEl.querySelectorAll('[data-htt-idx]').forEach((row) => {
                const activate = () => {
                    opts.listEl.querySelectorAll('.htt-row').forEach((r) => r.classList.remove('is-active'));
                    row.classList.add('is-active');
                    const seg = segments[parseInt(row.dataset.httIdx, 10)];
                    if (typeof opts.onSelect === 'function') opts.onSelect(seg, parseInt(row.dataset.httIdx, 10));
                    maybeGeocode(seg, row);
                };
                row.addEventListener('click', (e) => {
                    if (e.target.closest('a')) return;
                    activate();
                });
                row.addEventListener('keydown', (e) => {
                    if (e.key === 'Enter' || e.key === ' ') {
                        e.preventDefault();
                        activate();
                    }
                });
            });
        }

        async function maybeGeocode(seg, row) {
            if (!opts.geocodeUrl || !seg || seg.address || seg.lat == null || seg.lng == null) return;
            if (seg.kind !== 'parking' && seg.kind !== 'trip_start' && seg.kind !== 'trip_end') return;
            try {
                const url = new URL(opts.geocodeUrl, window.location.origin);
                url.searchParams.set('lat', String(seg.lat));
                url.searchParams.set('lng', String(seg.lng));
                const res = await fetch(url.toString(), { headers: { Accept: 'application/json' }, credentials: 'same-origin' });
                const data = await res.json();
                if (data && data.address) {
                    seg.address = data.address;
                    const metaEl = row.querySelector('.htt-meta');
                    if (metaEl && !metaEl.textContent.includes(data.address)) {
                        metaEl.textContent = metaEl.textContent.replace(
                            coordsLabel(seg.lat, seg.lng),
                            data.address
                        );
                    }
                }
            } catch (_e) { /* ignore */ }
        }

        function renderDayNav() {
            if (!opts.dayEl) return;
            opts.dayEl.innerHTML = `<div class="htt-day">
                <button type="button" class="btn btn-outline-secondary btn-sm" data-htt-day="-1" title="Previous day">◀</button>
                <input type="date" class="form-control form-control-sm admin-ltr" dir="ltr" data-htt-date value="${esc(currentDay)}">
                <button type="button" class="btn btn-outline-secondary btn-sm" data-htt-day="1" title="Next day">▶</button>
                <button type="button" class="btn btn-outline-secondary btn-sm" data-htt-today>${esc(i18n.today || 'Today')}</button>
            </div>`;
            opts.dayEl.querySelectorAll('[data-htt-day]').forEach((btn) => {
                btn.addEventListener('click', () => {
                    currentDay = addDaysYmd(currentDay, parseInt(btn.dataset.httDay, 10));
                    if (typeof opts.onDayChange === 'function') opts.onDayChange(currentDay);
                    renderDayNav();
                });
            });
            opts.dayEl.querySelector('[data-htt-today]')?.addEventListener('click', () => {
                currentDay = ymdLocal(new Date());
                if (typeof opts.onDayChange === 'function') opts.onDayChange(currentDay);
                renderDayNav();
            });
            opts.dayEl.querySelector('[data-htt-date]')?.addEventListener('change', (e) => {
                currentDay = e.target.value || currentDay;
                if (typeof opts.onDayChange === 'function') opts.onDayChange(currentDay);
            });
        }

        function renderExport() {
            if (!opts.exportEl) return;
            opts.exportEl.innerHTML = `<div class="htt-toolbar">
                <button type="button" class="btn btn-outline-secondary btn-sm" data-htt-export="xlsx">${esc(i18n.exportExcel || 'Excel')}</button>
                <button type="button" class="btn btn-outline-secondary btn-sm" data-htt-export="pdf">${esc(i18n.exportPdf || 'PDF')}</button>
                <button type="button" class="btn btn-outline-secondary btn-sm" data-htt-export="csv">${esc(i18n.exportCsv || 'CSV')}</button>
            </div>`;
            opts.exportEl.querySelectorAll('[data-htt-export]').forEach((btn) => {
                btn.addEventListener('click', () => {
                    if (typeof opts.onExport === 'function') opts.onExport(btn.dataset.httExport);
                });
            });
        }

        function setVehicleLabel(name, plate) {
            if (!opts.vehicleEl) return;
            const label = [name, plate].filter(Boolean).join(' · ') || '—';
            opts.vehicleEl.innerHTML = `<div class="htt-vehicle">${esc(label)}</div>`;
        }

        function setDay(ymd) {
            currentDay = ymd || currentDay;
            renderDayNav();
        }

        function setData(payload) {
            segments = Array.isArray(payload?.segments)
                ? payload.segments
                : (Array.isArray(payload?.trip_timeline) ? payload.trip_timeline : []);
            stats = payload?.stats || payload || null;
            const name = payload?.name || payload?.title || '';
            const plate = payload?.plate || '';
            setVehicleLabel(name, plate);
            renderSummary();
            renderList();
        }

        function clear() {
            segments = [];
            stats = null;
            if (opts.summaryEl) {
                opts.summaryEl.innerHTML = '';
                opts.summaryEl.hidden = true;
            }
            if (opts.listEl) opts.listEl.innerHTML = '';
            if (opts.vehicleEl) opts.vehicleEl.innerHTML = '';
        }

        renderDayNav();
        renderExport();

        return {
            setData,
            clear,
            setDay,
            getDay: () => currentDay,
            formatDuration,
            normalizeStats,
            addDaysYmd,
            ymdLocal,
        };
    }

    global.HistoryTripTimeline = {
        create: createHistoryTripTimeline,
        formatDuration,
        normalizeStats,
        addDaysYmd,
        ymdLocal,
        kindMeta,
    };
})(typeof window !== 'undefined' ? window : globalThis);
