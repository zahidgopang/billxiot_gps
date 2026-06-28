/**
 * Traccar-style single-page tracking UI on Google Maps.
 *
 * One shared map with four left-panel tabs:
 *   Objects  — live vehicles (status filter chips, colored markers, trails, animation)
 *   Events   — recent events list, click to locate
 *   Places   — geofences, drawn on the map
 *   History  — single-vehicle speed-colored route for a date range
 */
(function (global) {
    'use strict';

    const DEFAULT_CENTER = { lat: 25.276987, lng: 55.296249 };
    const TRAIL_MAX = 22;
    // Minimum movement (deg, ~2.5 m) before a new trail vertex is committed.
    const TRAIL_MIN_STEP_DEG = 0.000022;
    const MEDIUM_SPEED = 60;
    const OVER_SPEED = 80;
    const STOP_MIN_SEC = 120;
    const MOVING_KEYS = new Set(['running', 'moving']);
    const STOPPED_KEYS = new Set(['stopped', 'idle', 'parked', 'ignition_off']);
    const OFFLINE_KEYS = new Set(['offline', 'stale', 'delayed', 'blocked']);

    function escHtml(s) {
        return String(s ?? '')
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/"/g, '&quot;');
    }

    function colorForPoint(point, stateColors) {
        const key = point?.status_key || 'offline';
        return point?.color || stateColors[key] || stateColors.offline || '#94a3b8';
    }

    function speedToColor(speed) {
        const spd = parseFloat(speed || 0);
        if (spd <= 0) return '#64748b';
        if (spd <= MEDIUM_SPEED) return '#22c55e';
        if (spd <= OVER_SPEED) return '#eab308';
        return '#ef4444';
    }

    // Traccar-style directional arrow marker (rotates by heading, colored by status).
    const arrowIconCache = Object.create(null);
    function arrowIcon(color, heading) {
        const g = global.google;
        if (!g?.maps) return null;
        const bucket = Math.round((((heading || 0) % 360) + 360) % 360 / 5) * 5;
        const key = `${color}|${bucket}`;
        if (arrowIconCache[key]) return arrowIconCache[key];
        const svg = `<svg xmlns="http://www.w3.org/2000/svg" width="40" height="52" viewBox="0 0 40 52">
            <g transform="rotate(${bucket} 20 20)">
                <path d="M20 3 L31 31 L20 24 L9 31 Z" fill="${color}" stroke="#ffffff" stroke-width="1.8" stroke-linejoin="round"/>
            </g>
        </svg>`;
        const icon = {
            url: 'data:image/svg+xml;charset=UTF-8,' + encodeURIComponent(svg),
            scaledSize: new g.maps.Size(40, 52),
            anchor: new g.maps.Point(20, 20),
            labelOrigin: new g.maps.Point(20, 44),
        };
        arrowIconCache[key] = icon;
        return icon;
    }

    function markerLabel(text) {
        return { text, color: '#1f2937', fontSize: '12px', fontWeight: '600', className: 'tc-mk-label' };
    }

    function fmtTime(point) {
        if (!point) return '—';
        if (point.timestamp) return String(point.timestamp);
        if (point.recorded_at) return String(point.recorded_at).replace('T', ' ').slice(0, 19);
        return '—';
    }

    function formatDuration(sec) {
        sec = Math.round(sec || 0);
        const h = Math.floor(sec / 3600);
        const m = Math.floor((sec % 3600) / 60);
        const s = sec % 60;
        const parts = [];
        if (h) parts.push(`${h} h`);
        if (h || m) parts.push(`${m} min`);
        parts.push(`${s} s`);
        return parts.join(' ');
    }

    // Parking/stop runs along the route (speed ~0 for >= STOP_MIN_SEC).
    function computeStops(points) {
        const stops = [];
        let run = [];
        const flush = () => {
            if (run.length >= 2) {
                const t0 = run[0].recorded_at ? new Date(run[0].recorded_at).getTime() : null;
                const t1 = run[run.length - 1].recorded_at ? new Date(run[run.length - 1].recorded_at).getTime() : null;
                if (t0 && t1 && (t1 - t0) / 1000 >= STOP_MIN_SEC) {
                    const rep = run[0];
                    stops.push({
                        lat: rep.lat, lng: rep.lng,
                        heading: rep.heading || 0,
                        altitude: rep.altitude ?? null,
                        arrived: run[0],
                        departed: run[run.length - 1],
                        durationSec: (t1 - t0) / 1000,
                    });
                }
            }
            run = [];
        };
        (points || []).forEach((p) => { if (parseFloat(p.speed || 0) <= 2) run.push(p); else flush(); });
        flush();
        return stops;
    }

    function haversineKm(lat1, lng1, lat2, lng2) {
        const earth = 6371;
        const dLat = (lat2 - lat1) * Math.PI / 180;
        const dLng = (lng2 - lng1) * Math.PI / 180;
        const a = Math.sin(dLat / 2) ** 2
            + Math.cos(lat1 * Math.PI / 180) * Math.cos(lat2 * Math.PI / 180) * Math.sin(dLng / 2) ** 2;
        return earth * 2 * Math.asin(Math.min(1, Math.sqrt(a)));
    }

    function computeHistoryStats(points, stops) {
        let distKm = 0;
        let moveSec = 0;
        let topSpeed = 0;
        let speedSum = 0;
        let speedCount = 0;

        for (let i = 1; i < (points || []).length; i++) {
            const a = points[i - 1];
            const b = points[i];
            if (a.lat != null && a.lng != null && b.lat != null && b.lng != null) {
                distKm += haversineKm(a.lat, a.lng, b.lat, b.lng);
            }
            const spd = parseFloat(b.speed || 0);
            if (spd > topSpeed) topSpeed = spd;
            if (spd > 2) { speedSum += spd; speedCount++; }
            const t0 = a.recorded_at ? new Date(a.recorded_at).getTime() : null;
            const t1 = b.recorded_at ? new Date(b.recorded_at).getTime() : null;
            if (t0 && t1 && spd > 2) moveSec += (t1 - t0) / 1000;
        }

        const stopSec = (stops || []).reduce((s, x) => s + (x.durationSec || 0), 0);
        const avgSpeed = speedCount ? speedSum / speedCount : 0;

        return {
            distance_km: Math.round(distKm * 10) / 10,
            move_seconds: Math.round(moveSec),
            stop_seconds: Math.round(stopSec),
            top_speed: Math.round(topSpeed),
            avg_speed: Math.round(avgSpeed),
            stop_count: (stops || []).length,
            point_count: (points || []).length,
        };
    }

    let _pIcon = null;
    function pStopIcon() {
        const g = global.google;
        if (!g?.maps) return null;
        if (_pIcon) return _pIcon;
        const svg = `<svg xmlns="http://www.w3.org/2000/svg" width="28" height="38" viewBox="0 0 28 38">
            <path d="M14 1 C7 1 2 6 2 13 C2 22 14 37 14 37 C14 37 26 22 26 13 C26 6 21 1 14 1 Z" fill="#2563eb" stroke="#ffffff" stroke-width="2"/>
            <text x="14" y="17.5" text-anchor="middle" font-size="12" font-family="Arial, sans-serif" font-weight="bold" fill="#ffffff">P</text>
        </svg>`;
        _pIcon = {
            url: 'data:image/svg+xml;charset=UTF-8,' + encodeURIComponent(svg),
            scaledSize: new g.maps.Size(28, 38),
            anchor: new g.maps.Point(14, 38),
        };
        return _pIcon;
    }

    function ensureChartJs() {
        if (global.Chart) return Promise.resolve(global.Chart);
        if (ensureChartJs._p) return ensureChartJs._p;
        ensureChartJs._p = new Promise((resolve, reject) => {
            const s = document.createElement('script');
            s.src = 'https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js';
            s.onload = () => resolve(global.Chart);
            s.onerror = reject;
            document.head.appendChild(s);
        });
        return ensureChartJs._p;
    }

    function filterBucket(statusKey) {
        const k = statusKey || 'offline';
        if (MOVING_KEYS.has(k)) return 'moving';
        if (OFFLINE_KEYS.has(k)) return 'offline';
        if (STOPPED_KEYS.has(k)) return 'stopped';
        return 'stopped';
    }

    function samePosition(a, b) {
        if (!a || !b) return false;
        return Math.abs(a.lat - b.lat) < 1e-6 && Math.abs(a.lng - b.lng) < 1e-6;
    }

    // Valid map coordinate? Rejects non-finite, out-of-range, and the (0,0)
    // "null island" (Traccar uses 0/0 for events/positions with no GPS fix).
    function hasGeo(lat, lng) {
        const a = parseFloat(lat);
        const b = parseFloat(lng);
        if (!Number.isFinite(a) || !Number.isFinite(b)) return false;
        if (a < -90 || a > 90 || b < -180 || b > 180) return false;
        if (Math.abs(a) < 1e-4 && Math.abs(b) < 1e-4) return false;
        return true;
    }

    function detectStops(points) {
        if (!points || points.length < 2) return [];
        const events = [];
        let run = [];
        const flush = () => {
            if (run.length >= 2) {
                const t0 = run[0].recorded_at ? new Date(run[0].recorded_at).getTime() : null;
                const t1 = run[run.length - 1].recorded_at ? new Date(run[run.length - 1].recorded_at).getTime() : null;
                if (t0 && t1 && (t1 - t0) / 1000 >= STOP_MIN_SEC) {
                    const mid = run[Math.floor(run.length / 2)];
                    events.push({ type: 'stop', lat: mid.lat, lng: mid.lng, title: 'Stop' });
                }
            }
            run = [];
        };
        points.forEach((p) => { if (parseFloat(p.speed || 0) <= 1) run.push(p); else flush(); });
        flush();
        return events;
    }

    class TraccarUi {
        constructor(cfg) {
            this.cfg = cfg;
            this.stateColors = cfg.stateColors || {};
            this.map = null;
            this.vehicles = new Map();
            this.visible = new Set();
            this.states = new Map();
            this.followId = null;
            this.activeFilter = 'all';
            this.activeTab = 'objects';
            this.pollTimer = null;
            this.pollInFlight = false;
            this.echoChannels = new Map();
            this.initialFitDone = false;
            this.motionRaf = null;
            this._tick = (t) => this.motionTick(t);

            // history
            this.historyLayers = [];
            this.historyActive = false;
            this.historyPulse = null;

            // places
            this.placeLayers = [];

            // events
            this.eventMarker = null;
        }

        showError(message) {
            const el = document.getElementById('tcMapError');
            if (el) {
                el.hidden = false;
                const t = el.querySelector('[data-tc-error-text]');
                if (t) t.textContent = message;
            }
        }

        boot() {
            const cfg = this.cfg;
            if (!cfg.googleMapsKey) {
                this.showError(cfg.i18n?.mapApiKeyMissing || 'Google Maps API key is missing.');
                return;
            }
            (cfg.vehicles || []).forEach((v) => this.vehicles.set(v.id, { ...v }));

            const cbName = '__traccarUiReady';
            global[cbName] = () => {
                try { delete global[cbName]; } catch (_) { global[cbName] = undefined; }
                if (!global.google?.maps?.Map) {
                    this.showError(cfg.i18n?.loadingMapFailed || 'Google Maps failed to initialize.');
                    return;
                }
                this.initMap();
            };

            const script = document.createElement('script');
            script.async = true;
            script.defer = true;
            script.onerror = () => this.showError(cfg.i18n?.loadingMapFailed || 'Could not load Google Maps.');
            script.src = `https://maps.googleapis.com/maps/api/js?key=${encodeURIComponent(cfg.googleMapsKey)}&callback=${cbName}`;
            document.head.appendChild(script);
        }

        initMap() {
            const mapEl = document.getElementById('tcMap');
            if (!mapEl) return;

            this.map = new google.maps.Map(mapEl, {
                center: DEFAULT_CENTER,
                zoom: 11,
                mapTypeControl: true,
                streetViewControl: false,
                fullscreenControl: false,
            });
            this.legendEl = document.getElementById('tcLegend');

            this.bindTabs();
            this.bindObjects();
            this.bindHistory();
            this.bindEventsTab();
            this.bindPlacesTab();
            this.bindMapControls();
            this.bindFooterTabs();
            this.bindModules();
            this.setDefaultDates();

            // Show all vehicles by default (Traccar behavior).
            this.vehicles.forEach((_, id) => this.visible.add(id));
            this.renderList();
            this.updateCounts();
            this.visible.forEach((id) => { this.ensureMarker(id); this.subscribePusher(id); });
            this.fitAll();
            this.startPolling();
        }

        /* ---------- Tabs ---------- */
        bindTabs() {
            document.querySelectorAll('.tc-tab').forEach((btn) => {
                btn.addEventListener('click', () => this.switchTab(btn.dataset.tab));
            });
        }

        switchTab(tab) {
            this.activeTab = tab;
            document.querySelectorAll('.tc-tab').forEach((b) => b.classList.toggle('active', b.dataset.tab === tab));
            document.querySelectorAll('.tc-tab-body').forEach((b) => b.classList.toggle('active', b.dataset.tabBody === tab));

            if (tab === 'objects') {
                this.exitHistory();
                this.showLiveLayer(true);
            } else if (tab === 'history') {
                this.showLiveLayer(false);
                this.ensureHistorySelect2();
            } else {
                this.showLiveLayer(true);
            }

            if (tab === 'events' && !this._eventsLoaded) this.loadEvents();
            if (tab === 'places' && !this._placesLoaded) this.loadPlaces();
        }

        /* ---------- Objects (live) ---------- */
        bindObjects() {
            document.getElementById('tcSearch')?.addEventListener('input', () => this.renderList());
            document.querySelectorAll('#tcChips .tc-chip').forEach((chip) => {
                chip.addEventListener('click', () => {
                    this.activeFilter = chip.dataset.filter;
                    document.querySelectorAll('#tcChips .tc-chip').forEach((c) => c.classList.toggle('active', c === chip));
                    this.renderList();
                });
            });
            document.getElementById('tcCheckAll')?.addEventListener('change', (e) => {
                if (e.target.checked) {
                    this.vehicles.forEach((_, id) => {
                        if (!this.visible.has(id)) { this.visible.add(id); this.ensureMarker(id); this.subscribePusher(id); }
                    });
                } else {
                    [...this.visible].forEach((id) => { this.visible.delete(id); this.teardownVehicle(id); });
                    this.followId = null;
                    this.updateFollowBtn();
                }
                this.renderList();
                this.startPolling();
            });
        }

        filteredVehicles() {
            const q = (document.getElementById('tcSearch')?.value || '').trim().toLowerCase();
            return [...this.vehicles.values()].filter((v) => {
                if (this.activeFilter !== 'all' && filterBucket(v.status_key) !== this.activeFilter) return false;
                if (!q) return true;
                const hay = `${v.title || ''} ${v.plate || ''} ${v.imei || ''}`.toLowerCase();
                return hay.includes(q);
            });
        }

        renderList() {
            const listEl = document.getElementById('tcVehicleList');
            if (!listEl) return;
            const items = this.filteredVehicles();
            if (items.length === 0) {
                listEl.innerHTML = `<div class="tc-empty">${escHtml(this.cfg.i18n?.noVehicles || 'No vehicles')}</div>`;
                return;
            }
            const i18n = this.cfg.i18n || {};
            listEl.innerHTML = items.map((v) => {
                const dot = v.color || colorForPoint(v, this.stateColors);
                const eye = this.visible.has(v.id) ? 'checked' : '';
                const follow = this.followId === v.id ? 'checked' : '';
                return `<div class="tc-row ${this.followId === v.id ? 'active' : ''}" data-id="${v.id}">
                    <input type="checkbox" class="form-check-input tc-check tc-check-eye" data-eye="${v.id}" ${eye} title="${escHtml(i18n.colShow || 'Show on map')}">
                    <input type="checkbox" class="form-check-input tc-check tc-check-follow" data-follow="${v.id}" ${follow} title="${escHtml(i18n.colFollow || 'Follow / zoom')}">
                    <span class="tc-veh-icon" data-veh-icon="${v.id}" style="color:${escHtml(dot)}" title="${escHtml(v.status_label || v.status || '')}"><i class="fas ${escHtml(v.icon || 'fa-location-crosshairs')}"></i></span>
                    <span class="tc-row-info">
                        <span class="tc-row-titlewrap">
                            <span class="tc-row-title">${escHtml(v.title || v.plate || ('#' + v.id))}</span>
                            <span class="tc-status-badge" data-status="${v.id}" style="--st:${escHtml(dot)}">${escHtml(v.status_label || v.status || v.status_key || '—')}</span>
                        </span>
                        <span class="tc-row-meta" data-meta="${v.id}">${this.metaHtml(v)}</span>
                    </span>
                </div>`;
            }).join('');

            listEl.querySelectorAll('.tc-row').forEach((row) => {
                const id = parseInt(row.dataset.id, 10);
                row.addEventListener('click', (e) => {
                    if (e.target.closest('.tc-check')) return;
                    this.locateVehicle(id);
                });
            });
            listEl.querySelectorAll('.tc-check').forEach((cb) => cb.addEventListener('click', (e) => e.stopPropagation()));
            listEl.querySelectorAll('.tc-check-eye').forEach((cb) => {
                cb.addEventListener('change', (e) => this.toggleVisible(parseInt(e.target.dataset.eye, 10)));
            });
            listEl.querySelectorAll('.tc-check-follow').forEach((cb) => {
                cb.addEventListener('change', (e) => this.setFollow(parseInt(e.target.dataset.follow, 10), e.target.checked));
            });
            this.syncCheckAll();
        }

        syncCheckAll() {
            const all = document.getElementById('tcCheckAll');
            if (!all) return;
            const total = this.vehicles.size;
            const shown = this.visible.size;
            all.checked = total > 0 && shown === total;
            all.indeterminate = shown > 0 && shown < total;
        }

        metaHtml(v) {
            const i18n = this.cfg.i18n || {};
            const speed = v.speed != null ? `${v.speed} ${i18n.kmhUnit || 'km/h'}` : '—';
            const updated = escHtml(v.recorded_at_human || '—');
            const parts = [`<span>${speed}</span>`, `<span>${updated}</span>`];
            if (v.ignition != null) parts.push(`<span>${v.ignition ? (i18n.ignitionOn || 'Ign ON') : (i18n.ignitionOff || 'Ign OFF')}</span>`);
            return parts.join(' · ');
        }

        updateCounts() {
            const counts = { all: this.vehicles.size, moving: 0, stopped: 0, offline: 0 };
            this.vehicles.forEach((v) => { counts[filterBucket(v.status_key)] = (counts[filterBucket(v.status_key)] || 0) + 1; });
            document.querySelectorAll('#tcChips [data-count]').forEach((el) => {
                el.textContent = counts[el.dataset.count] ?? 0;
            });
            const allLabel = document.getElementById('tcAllCount');
            if (allLabel) allLabel.textContent = `(${this.vehicles.size})`;
        }

        vehicleState(id) {
            if (!this.states.has(id)) {
                this.states.set(id, { marker: null, trail: [], trailPolylines: [], lastPoint: null, animFrame: null, motion: null, renderPos: null, renderHeading: 0 });
            }
            return this.states.get(id);
        }

        labelFor(v) {
            const spd = v.speed != null ? Math.round(parseFloat(v.speed) || 0) : 0;
            return `${v.title || v.plate || ('#' + v.id)} (${spd} kph)`;
        }

        toggleVisible(id) {
            if (this.visible.has(id)) {
                this.visible.delete(id);
                this.teardownVehicle(id);
                if (this.followId === id) { this.followId = null; this.updateFollowBtn(); }
            } else {
                this.visible.add(id);
                this.ensureMarker(id);
                this.subscribePusher(id);
            }
            this.renderList();
            this.startPolling();
        }

        locateVehicle(id) {
            const v = this.vehicles.get(id);
            if (!this.visible.has(id)) this.toggleVisible(id);
            if (v?.lat != null && v?.lng != null) {
                this.map.panTo({ lat: v.lat, lng: v.lng });
                if (this.map.getZoom() < 14) this.map.setZoom(15);
            }
            this.openDevicePanel(id);
        }

        // Footprints column: follow + zoom exactly to one vehicle (single-select).
        setFollow(id, on) {
            if (on) {
                if (!this.visible.has(id)) {
                    this.visible.add(id);
                    this.ensureMarker(id);
                    this.subscribePusher(id);
                    this.startPolling();
                }
                this.followId = id;
                const v = this.vehicles.get(id);
                if (v?.lat != null && v?.lng != null) {
                    this.map.panTo({ lat: v.lat, lng: v.lng });
                    this.map.setZoom(17);
                }
            } else if (this.followId === id) {
                this.followId = null;
            }
            this.updateFollowBtn();
            this.renderList();
        }

        ensureMarker(id) {
            const v = this.vehicles.get(id);
            const st = this.vehicleState(id);
            if (!this.map || !v || st.marker) return;
            const color = colorForPoint(v, this.stateColors);
            const pos = v.lat != null && v.lng != null ? { lat: v.lat, lng: v.lng } : null;
            st.marker = new google.maps.Marker({
                map: pos && !this.historyActive ? this.map : null,
                position: pos || DEFAULT_CENTER,
                icon: arrowIcon(color, v.heading || 0),
                label: markerLabel(this.labelFor(v)),
                title: this.labelFor(v),
                zIndex: 500 + id,
                optimized: false,
            });
            st.marker.addListener('click', () => this.locateVehicle(id));
            if (pos) {
                st.lastPoint = { ...v };
                st.renderPos = pos;
                st.renderHeading = parseFloat(v.heading || 0);
            }
        }

        teardownVehicle(id) {
            const st = this.vehicleState(id);
            if (st.animFrame) { cancelAnimationFrame(st.animFrame); st.animFrame = null; }
            st.motion = null;
            st.marker?.setMap(null);
            this.clearTrail(st);
            st.marker = null; st.lastPoint = null; st.renderPos = null;
            this.unsubscribePusher(id);
        }

        clearTrail(st) {
            st.trail = [];
            st.trailPolylines.forEach((p) => p.setMap(null));
            st.trailPolylines = [];
        }

        /**
         * Short Traccar-style tail behind a moving marker. Samples the live
         * (animated) render position, keeps the last TRAIL_MAX vertices and
         * always ends exactly at the marker head so the tail follows smoothly.
         */
        appendTrail(st, lat, lng, color) {
            if (!this.map || lat == null || lng == null) return;
            const committed = st.trail;
            const last = committed[committed.length - 1];
            if (!last || Math.abs(lat - last.lat) > TRAIL_MIN_STEP_DEG || Math.abs(lng - last.lng) > TRAIL_MIN_STEP_DEG) {
                committed.push({ lat, lng });
                while (committed.length > TRAIL_MAX) committed.shift();
            }
            const path = committed.slice();
            const tail = path[path.length - 1];
            if (!tail || tail.lat !== lat || tail.lng !== lng) path.push({ lat, lng });
            if (path.length < 2) return;

            let line = st.trailPolylines[0];
            if (!line) {
                line = new google.maps.Polyline({
                    map: this.map,
                    path,
                    strokeColor: color,
                    strokeOpacity: 0.6,
                    strokeWeight: 5,
                    zIndex: 400,
                    clickable: false,
                });
                st.trailPolylines = [line];
            } else {
                line.setPath(path);
                line.setOptions({ strokeColor: color });
                if (line.getMap() !== this.map) line.setMap(this.map);
            }
        }

        applyPoint(id, point) {
            if (!this.visible.has(id) || !point || point.lat == null || point.lng == null) return;
            const st = this.vehicleState(id);
            this.ensureMarker(id);
            const merged = { ...this.vehicles.get(id), ...point, id };
            this.vehicles.set(id, merged);

            const metaEl = document.querySelector(`[data-meta="${id}"]`);
            if (metaEl) metaEl.innerHTML = this.metaHtml(merged);
            const liveColor = colorForPoint(merged, this.stateColors);
            const iconEl = document.querySelector(`[data-veh-icon="${id}"]`);
            if (iconEl) {
                iconEl.style.color = liveColor;
                iconEl.title = merged.status_label || merged.status || '';
                if (merged.icon) iconEl.innerHTML = `<i class="fas ${merged.icon}"></i>`;
            }
            const statusEl = document.querySelector(`[data-status="${id}"]`);
            if (statusEl) {
                statusEl.style.setProperty('--st', liveColor);
                statusEl.textContent = merged.status_label || merged.status || merged.status_key || '—';
            }
            this.updateCounts();

            const prev = st.lastPoint;
            const key = merged.status_key || 'offline';
            if (!MOVING_KEYS.has(key)) this.clearTrail(st);

            if (this.historyActive) { st.lastPoint = merged; return; }

            const color = colorForPoint(merged, this.stateColors);
            st.marker.setLabel(markerLabel(this.labelFor(merged)));
            st.marker.setTitle(this.labelFor(merged));

            if (!prev || samePosition(prev, merged)) {
                st.marker.setMap(this.map);
                st.marker.setPosition({ lat: merged.lat, lng: merged.lng });
                st.marker.setIcon(arrowIcon(color, merged.heading || 0));
                st.renderPos = { lat: merged.lat, lng: merged.lng };
                st.renderHeading = parseFloat(merged.heading || 0);
                st.lastPoint = merged;
                st.motion = null;
                if (MOVING_KEYS.has(key)) this.appendTrail(st, merged.lat, merged.lng, color);
                if (this.followId === id) this.map.panTo({ lat: merged.lat, lng: merged.lng });
                return;
            }
            this.startMotion(id, merged);
        }

        /**
         * Continuous motion: glide from the current rendered position to the
         * latest fix over the poll gap, then dead-reckon along the heading at the
         * reported speed if the next poll is late — so moving markers never stop.
         */
        startMotion(id, to) {
            const st = this.vehicleState(id);
            const toLL = { lat: to.lat, lng: to.lng };
            const from = st.renderPos
                || (st.lastPoint ? { lat: st.lastPoint.lat, lng: st.lastPoint.lng } : toLL);
            const fromH = st.renderHeading != null ? st.renderHeading : parseFloat(to.heading || 0);
            const moving = MOVING_KEYS.has(to.status_key || 'offline');
            const speedKmh = Math.max(0, parseFloat(to.speed) || 0);
            const interval = this.cfg.pollIntervalMs || 2000;

            st.motion = {
                from,
                to: toLL,
                fromH,
                toH: parseFloat(to.heading || 0),
                speedKmh,
                color: colorForPoint(to, this.stateColors),
                catchupMs: Math.max(300, interval),
                cruise: moving && speedKmh > 0,
                maxCruiseSec: (interval * 1.5) / 1000,
                start: performance.now(),
            };
            st.lastPoint = to;
            if (!moving) this.clearTrail(st);
            this.startMotionLoop();
        }

        startMotionLoop() {
            if (!this.motionRaf) this.motionRaf = requestAnimationFrame(this._tick);
        }

        motionTick(now) {
            if (this.historyActive) {
                this.states.forEach((st) => { st.motion = null; });
                this.motionRaf = null;
                return;
            }

            let active = false;
            this.states.forEach((st, id) => {
                const m = st.motion;
                if (!m || !st.marker || !this.visible.has(id)) return;

                const elapsed = now - m.start;
                let lat;
                let lng;
                let heading;
                let done = false;

                if (elapsed <= m.catchupMs) {
                    const t = m.catchupMs > 0 ? elapsed / m.catchupMs : 1;
                    const eased = 1 - Math.pow(1 - t, 3);
                    lat = m.from.lat + (m.to.lat - m.from.lat) * eased;
                    lng = m.from.lng + (m.to.lng - m.from.lng) * eased;
                    const delta = ((m.toH - m.fromH + 540) % 360) - 180;
                    heading = (m.fromH + delta * eased + 360) % 360;
                } else if (m.cruise) {
                    const cruiseSec = (elapsed - m.catchupMs) / 1000;
                    const cappedSec = Math.min(cruiseSec, m.maxCruiseSec);
                    const mps = (m.speedKmh * 1000) / 3600;
                    const dist = mps * cappedSec;
                    const hRad = (m.toH * Math.PI) / 180;
                    const cosLat = Math.cos((m.to.lat * Math.PI) / 180) || 1e-6;
                    lat = m.to.lat + (dist * Math.cos(hRad)) / 111320;
                    lng = m.to.lng + (dist * Math.sin(hRad)) / (111320 * cosLat);
                    heading = m.toH;
                    if (cruiseSec >= m.maxCruiseSec) done = true;
                } else {
                    lat = m.to.lat;
                    lng = m.to.lng;
                    heading = m.toH;
                    done = true;
                }

                st.renderPos = { lat, lng };
                st.renderHeading = heading;
                st.marker.setMap(this.map);
                st.marker.setPosition({ lat, lng });
                st.marker.setIcon(arrowIcon(m.color, heading));
                if (MOVING_KEYS.has(st.lastPoint?.status_key || '')) this.appendTrail(st, lat, lng, m.color);
                if (this.followId === id) this.map.panTo({ lat, lng });

                if (done) { st.motion = null; } else { active = true; }
            });

            this.motionRaf = active ? requestAnimationFrame(this._tick) : null;
        }

        showLiveLayer(show) {
            this.states.forEach((st, id) => {
                if (!this.visible.has(id)) return;
                if (show && !this.historyActive) {
                    if (st.lastPoint?.lat != null) st.marker?.setMap(this.map);
                    st.trailPolylines.forEach((l) => l.setMap(this.map));
                } else {
                    st.marker?.setMap(null);
                    st.trailPolylines.forEach((l) => l.setMap(null));
                }
            });
        }

        /* ---------- Polling + realtime ---------- */
        startPolling() {
            if (this.pollTimer) { clearInterval(this.pollTimer); this.pollTimer = null; }
            if (this.visible.size === 0) return;
            this.pollLive(true);
            this.pollTimer = setInterval(() => this.pollLive(false), this.cfg.pollIntervalMs || 4000);
        }

        async pollLive(force) {
            if (this.pollInFlight && !force) return;
            const ids = [...this.visible];
            if (ids.length === 0) return;
            this.pollInFlight = true;
            try {
                const url = `${this.cfg.liveJsonUrl}?ids=${ids.join(',')}&_=${Date.now()}`;
                const res = await fetch(url, { credentials: 'same-origin', cache: 'no-store' });
                if (!res.ok) return;
                const data = await res.json();
                (data.devices || []).forEach((d) => this.applyPoint(d.id, d));
            } catch (err) {
                console.warn('[traccar-ui] poll failed', err);
            } finally {
                this.pollInFlight = false;
            }
        }

        subscribePusher(id) {
            if (!global.Echo || typeof global.Echo.private !== 'function' || this.echoChannels.has(id)) return;
            try {
                const channel = global.Echo.private(`device.${id}`);
                channel.listen('.DeviceLocationUpdated', (payload) => {
                    const loc = payload.location || payload;
                    if (loc && loc.id == null) loc.id = id;
                    this.applyPoint(id, loc);
                });
                this.echoChannels.set(id, channel);
            } catch (err) { console.warn('[traccar-ui] echo subscribe failed', id, err); }
        }

        unsubscribePusher(id) {
            if (!this.echoChannels.has(id)) return;
            try { global.Echo.leave(`device.${id}`); } catch (_) { /* ignore */ }
            this.echoChannels.delete(id);
        }

        /* ---------- Map controls ---------- */
        bindMapControls() {
            document.getElementById('tcFit')?.addEventListener('click', () => this.fitAll());
            document.getElementById('tcFollow')?.addEventListener('click', () => this.toggleFollow());
            document.getElementById('tcRefresh')?.addEventListener('click', () => this.pollLive(true));
        }

        fitAll() {
            if (!this.map) return;
            const bounds = new google.maps.LatLngBounds();
            let count = 0;
            this.visible.forEach((id) => {
                const v = this.vehicles.get(id);
                if (v?.lat != null && v?.lng != null) { bounds.extend({ lat: v.lat, lng: v.lng }); count++; }
            });
            if (count === 0) return;
            if (count === 1) { this.map.setCenter(bounds.getCenter()); this.map.setZoom(14); }
            else this.map.fitBounds(bounds, 60);
        }

        toggleFollow() {
            const ids = [...this.visible];
            if (ids.length === 0) return;
            if (this.followId && this.visible.has(this.followId)) this.followId = null;
            else { this.followId = ids[0]; const v = this.vehicles.get(this.followId); if (v?.lat != null) this.map.panTo({ lat: v.lat, lng: v.lng }); }
            this.updateFollowBtn();
            this.renderList();
        }

        updateFollowBtn() {
            const btn = document.getElementById('tcFollow');
            if (!btn) return;
            btn.classList.toggle('active', !!this.followId);
            btn.setAttribute('aria-pressed', this.followId ? 'true' : 'false');
        }

        /* ---------- History ---------- */
        setDefaultDates() {
            const fmt = (d) => d.toISOString().slice(0, 10);
            const now = new Date();
            const from = document.getElementById('tcHistDateFrom');
            const to = document.getElementById('tcHistDateTo');
            if (from && !from.value) from.value = fmt(now);
            if (to && !to.value) to.value = fmt(now);
        }

        ensureHistorySelect2() {
            if (this._histSelectReady) return;
            const selectEl = document.getElementById('tcHistVehicle');
            const $ = window.jQuery;
            if (!selectEl || !$ || typeof $.fn?.select2 !== 'function') return;
            // Initialise only once the History tab is visible so Select2 can
            // measure the panel width correctly (init while display:none breaks sizing).
            if (!selectEl.offsetParent) return;
            this._histSelectReady = true;
            const $sel = $(selectEl);
            if ($sel.hasClass('select2-hidden-accessible')) $sel.select2('destroy');
            $sel.select2({
                theme: 'bootstrap-5',
                width: '100%',
                dropdownParent: $sel.closest('[data-tab-body="history"]'),
                placeholder: this.cfg.i18n?.selectVehicle || 'Select a vehicle',
                dir: document.documentElement.getAttribute('dir') || 'ltr',
            });
        }

        bindHistory() {
            document.getElementById('tcHistShow')?.addEventListener('click', () => this.loadHistory());
            document.getElementById('tcHistHide')?.addEventListener('click', () => this.exitHistory());
            document.getElementById('tcFooterClose')?.addEventListener('click', () => {
                const f = document.getElementById('tcFooter');
                if (f) f.hidden = true;
                this.resizeMapSoon();
            });
        }

        clearHistory() {
            this.historyLayers.forEach((l) => l.setMap(null));
            this.historyLayers = [];
            this.historyPulse?.hide();
            this.stopInfo?.close();
            if (this.legendEl) this.legendEl.innerHTML = '';
            const res = document.getElementById('tcHistResults');
            if (res) res.innerHTML = '';
            const wrap = document.getElementById('tcHistResultsWrap');
            if (wrap) wrap.hidden = true;
            const summary = document.getElementById('tcHistSummary');
            if (summary) { summary.innerHTML = ''; summary.hidden = true; }
            const dataBody = document.querySelector('.tc-fbody[data-fbody="data"]');
            if (dataBody) dataBody.classList.remove('tc-fbody--hist');
            const footer = document.getElementById('tcFooter');
            if (footer) footer.hidden = true;
            if (this._chart) { this._chart.destroy(); this._chart = null; }
            this._historyGraphPoints = null;
        }

        exitHistory() {
            this.clearHistory();
            this.historyActive = false;
            this.showLiveLayer(true);
        }

        async loadHistory() {
            const id = parseInt(document.getElementById('tcHistVehicle')?.value, 10);
            if (!id) { alert(this.cfg.i18n?.selectVehicle || 'Select a vehicle.'); return; }

            const fromDate = document.getElementById('tcHistDateFrom')?.value || '';
            const toDate = document.getElementById('tcHistDateTo')?.value || '';
            const tFrom = document.getElementById('tcHistTimeFrom')?.value || '';
            const tTo = document.getElementById('tcHistTimeTo')?.value || '';
            let from = fromDate, to = toDate;
            if (fromDate && tFrom) from = `${fromDate} ${tFrom}`;
            if (toDate && tTo) to = `${toDate} ${tTo}`;

            const params = new URLSearchParams();
            params.set('ids', String(id));
            if (from) params.set('from', from);
            if (to) params.set('to', to);

            const btn = document.getElementById('tcHistShow');
            btn?.setAttribute('disabled', 'disabled');
            try {
                const url = `${this.cfg.historyJsonUrl}?${params.toString()}&_=${Date.now()}`;
                const res = await fetch(url, { credentials: 'same-origin', cache: 'no-store' });
                const data = await res.json();
                if (!res.ok) throw new Error(data.message || 'Request failed');
                this.drawHistory((data.vehicles || [])[0] || null);
            } catch (err) {
                console.error('[traccar-ui] history', err);
                alert(this.cfg.i18n?.loadFailed || 'Failed to load history.');
            } finally {
                btn?.removeAttribute('disabled');
            }
        }

        drawHistory(vehicle) {
            this.clearHistory();
            this.historyActive = true;
            this.showLiveLayer(false);

            const points = vehicle?.points || [];
            if (points.length < 2) {
                alert(this.cfg.i18n?.noData || 'No data for the selected period.');
                this.exitHistory();
                return;
            }

            for (let i = 1; i < points.length; i++) {
                const a = points[i - 1], b = points[i];
                const speed = Math.max(parseFloat(a.speed || 0), parseFloat(b.speed || 0));
                const line = new google.maps.Polyline({
                    path: [{ lat: a.lat, lng: a.lng }, { lat: b.lat, lng: b.lng }],
                    strokeColor: speedToColor(speed),
                    strokeOpacity: 0.92,
                    strokeWeight: speed <= 0 ? 6 : speed <= MEDIUM_SPEED ? 7 : speed <= OVER_SPEED ? 8 : 9,
                    map: this.map, zIndex: 2, clickable: false,
                });
                this.historyLayers.push(line);
            }
            const startPoint = points[0];
            const endPoint = points[points.length - 1];
            this.addEndpoint(startPoint, 'start');
            this.addEndpoint(endPoint, 'end');
            this.showHistoryPulse(endPoint, vehicle);

            (vehicle.events || []).forEach((ev) => {
                if (hasGeo(ev.lat, ev.lng)) this.addEventDot({ type: ev.event_type || ev.type || 'event', lat: ev.lat, lng: ev.lng, title: ev.title || ev.message || 'Event' });
            });

            const name = vehicle.name || '';
            this._historyName = name;
            const stops = computeStops(points);
            stops.forEach((stop) => this.addStop(stop, name));
            this.renderStopList(stops, name, computeHistoryStats(points, stops));
            this.renderSpeedLegend(name);
            this.renderHistoryFooter(vehicle, points, stops);
            this.renderGraph(points);
            this.fitLayers(this.historyLayers);
        }

        addStop(stop, name) {
            const marker = new google.maps.Marker({
                position: { lat: stop.lat, lng: stop.lng }, map: this.map,
                icon: pStopIcon(), zIndex: 570, title: 'P',
            });
            marker.addListener('click', () => this.openStopInfo(stop, name, marker));
            stop._marker = marker;
            this.historyLayers.push(marker);
        }

        openStopInfo(stop, name, marker) {
            if (!this.stopInfo) this.stopInfo = new google.maps.InfoWindow();
            const i18n = this.cfg.i18n || {};
            const rows = [
                [i18n.lblObject || 'Object', escHtml(name)],
                [i18n.lblPosition || 'Position', `${Number(stop.lat).toFixed(6)}, ${Number(stop.lng).toFixed(6)}`],
                [i18n.lblAngle || 'Angle', `${Math.round(stop.heading || 0)}\u00b0`],
                [i18n.lblArrived || 'Arrived', escHtml(fmtTime(stop.arrived))],
                [i18n.lblDeparted || 'Departed', escHtml(fmtTime(stop.departed))],
                [i18n.lblDuration || 'Duration', escHtml(formatDuration(stop.durationSec))],
            ];
            if (stop.altitude != null) rows.splice(3, 0, ['Altitude', `${Math.round(stop.altitude)} m`]);
            const html = `<div class="tc-info">${rows.map((r) => `<div class="tc-info-row"><span>${r[0]}</span><b>${r[1]}</b></div>`).join('')}</div>`;
            this.stopInfo.setContent(html);
            this.stopInfo.open(this.map, marker || stop._marker);
        }

        renderStopList(stops, name, stats) {
            const res = document.getElementById('tcHistResults');
            const wrap = document.getElementById('tcHistResultsWrap');
            const summary = document.getElementById('tcHistSummary');
            const i = this.cfg.i18n || {};
            const kmh = i.kmhUnit || 'km/h';

            if (summary && stats) {
                summary.hidden = false;
                summary.innerHTML = `<div class="tc-hist-summary-row">
                    <span>${escHtml(i.statRouteLength || 'Route')}: <strong>${stats.distance_km} km</strong></span>
                    <span>${escHtml(i.statMoveDuration || 'Move')}: <strong>${escHtml(formatDuration(stats.move_seconds))}</strong></span>
                    <span>${escHtml(i.statStopDuration || 'Stop')}: <strong>${escHtml(formatDuration(stats.stop_seconds))}</strong></span>
                    <span>${escHtml(i.statTopSpeed || 'Top')}: <strong>${stats.top_speed} ${escHtml(kmh)}</strong></span>
                    <span>${escHtml(i.statAvgSpeed || 'Avg')}: <strong>${stats.avg_speed} ${escHtml(kmh)}</strong></span>
                </div>`;
            }

            if (wrap) wrap.hidden = false;
            if (!res) return;
            if (!stops.length) {
                res.innerHTML = `<div class="tc-empty">${escHtml(i.noStops || 'No stops')}</div>`;
                return;
            }
            res.innerHTML = stops.map((s, idx) => `<div class="tc-row tc-stop-row" data-stop="${idx}">
                <span class="tc-pmark">P</span>
                <span class="tc-row-info">
                    <span class="tc-row-title">${escHtml(fmtTime(s.arrived))}</span>
                    <span class="tc-row-meta">${escHtml(formatDuration(s.durationSec))}</span>
                </span></div>`).join('');
            res.querySelectorAll('[data-stop]').forEach((row) => {
                row.addEventListener('click', () => {
                    const s = stops[parseInt(row.dataset.stop, 10)];
                    if (!s) return;
                    this.map.panTo({ lat: s.lat, lng: s.lng });
                    if (this.map.getZoom() < 15) this.map.setZoom(16);
                    this.openStopInfo(s, name, s._marker);
                });
            });
        }

        renderHistoryFooter(vehicle, points, stops) {
            const dataEl = document.getElementById('tcFooterData');
            const msgEl = document.getElementById('tcFooterMessages');
            if (!dataEl) return;

            const i = this.cfg.i18n || {};
            const kmh = i.kmhUnit || 'km/h';
            const stats = computeHistoryStats(points, stops);
            const events = vehicle?.events || [];

            dataEl.classList.add('tc-fbody--hist');
            const statCard = (lbl, val) =>
                `<div class="tc-hist-stat"><div class="tc-hist-stat-lbl">${escHtml(lbl)}</div><div class="tc-hist-stat-val">${val}</div></div>`;

            dataEl.innerHTML = `<div class="tc-hist-stats">
                ${statCard(i.statRouteLength || 'Route length', `${stats.distance_km} km`)}
                ${statCard(i.statMoveDuration || 'Move duration', escHtml(formatDuration(stats.move_seconds)))}
                ${statCard(i.statStopDuration || 'Stop duration', escHtml(formatDuration(stats.stop_seconds)))}
                ${statCard(i.statTopSpeed || 'Top speed', `${stats.top_speed} ${escHtml(kmh)}`)}
                ${statCard(i.statAvgSpeed || 'Average speed', `${stats.avg_speed} ${escHtml(kmh)}`)}
                ${statCard(i.parkingStops || 'Stops', String(stats.stop_count))}
                ${statCard(i.lblTimePosition || 'Points', String(stats.point_count))}
            </div>`;

            if (msgEl) {
                if (!events.length) {
                    msgEl.innerHTML = `<div class="tc-empty">${escHtml(i.noEvents || 'No events')}</div>`;
                } else {
                    msgEl.innerHTML = `<ul class="tc-msg-list">${events.map((ev) => {
                        const loc = hasGeo(ev.lat, ev.lng) ? `data-tc-locate="${ev.lat},${ev.lng}"` : '';
                        return `<li ${loc}><span>${escHtml(ev.title || ev.message || ev.type || ev.event_type || 'Event')}</span><span class="tc-msg-time">${escHtml(String(ev.time || ev.recorded_at || '').slice(0, 19))}</span></li>`;
                    }).join('')}</ul>`;
                    msgEl.querySelectorAll('[data-tc-locate]').forEach((node) => {
                        node.addEventListener('click', (e) => {
                            e.preventDefault();
                            const [lat, lng] = node.dataset.tcLocate.split(',').map(parseFloat);
                            if (hasGeo(lat, lng)) {
                                this.map.panTo({ lat, lng });
                                if (this.map.getZoom() < 15) this.map.setZoom(16);
                            }
                        });
                    });
                }
            }
        }

        /* ---------- Footer info panel (Data / Graph / Messages) ---------- */
        bindFooterTabs() {
            document.querySelectorAll('.tc-ftab').forEach((btn) => {
                btn.addEventListener('click', () => this.switchFooterTab(btn.dataset.ftab));
            });
        }

        showFooter(title) {
            const footer = document.getElementById('tcFooter');
            const wasHidden = footer ? footer.hidden : true;
            if (footer) footer.hidden = false;
            const t = document.getElementById('tcFooterTitle');
            if (t) t.textContent = title || '';
            if (wasHidden) this.resizeMapSoon();
        }

        resizeMapSoon() {
            if (!this.map || !global.google?.maps?.event) return;
            const c = this.map.getCenter();
            setTimeout(() => {
                global.google.maps.event.trigger(this.map, 'resize');
                if (c) this.map.setCenter(c);
            }, 60);
        }

        switchFooterTab(tab) {
            document.querySelectorAll('.tc-ftab').forEach((b) => b.classList.toggle('active', b.dataset.ftab === tab));
            document.querySelectorAll('.tc-fbody').forEach((b) => b.classList.toggle('active', b.dataset.fbody === tab));
            if (tab === 'graph') this._renderActiveGraph();
        }

        _renderActiveGraph() {
            if (this._footerMode === 'history') {
                const pts = this._historyGraphPoints || [];
                this.drawChart(pts.map((p) => ({ label: fmtTime(p).slice(11, 16), speed: Math.round(parseFloat(p.speed || 0)) })));
            } else {
                this.drawChart(this._panelGraphRows || []);
            }
        }

        async drawChart(rows) {
            const canvas = document.getElementById('tcSpeedChart');
            if (!canvas) return;
            let Chart;
            try { Chart = await ensureChartJs(); } catch (_) { return; }
            if (this._chart) { this._chart.destroy(); this._chart = null; }

            const step = Math.max(1, Math.ceil(rows.length / 600));
            const sampled = rows.filter((_, i) => i % step === 0);

            this._chart = new Chart(canvas.getContext('2d'), {
                type: 'line',
                data: {
                    labels: sampled.map((r) => r.label),
                    datasets: [{
                        label: this.cfg.i18n?.kmhUnit || 'km/h',
                        data: sampled.map((r) => r.speed),
                        borderColor: '#2563eb',
                        backgroundColor: 'rgba(37, 99, 235, 0.15)',
                        borderWidth: 1.5,
                        pointRadius: 0,
                        fill: true,
                        tension: 0.25,
                    }],
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    animation: false,
                    interaction: { intersect: false, mode: 'index' },
                    scales: { y: { beginAtZero: true }, x: { ticks: { maxTicksLimit: 12, autoSkip: true } } },
                    plugins: { legend: { display: false } },
                },
            });
        }

        renderGraph(points) {
            this._footerMode = 'history';
            this._historyGraphPoints = points;
            this.showFooter(this._historyName || '');
            this.switchFooterTab('data');
        }

        async openDevicePanel(id) {
            if (!this.cfg.devicePanelUrl) return;
            const v = this.vehicles.get(id);
            this._footerMode = 'panel';
            this._panelDeviceId = id;
            this.showFooter(this.labelFor(v) || ('#' + id));
            this.switchFooterTab('data');
            const dataEl = document.getElementById('tcFooterData');
            const msgEl = document.getElementById('tcFooterMessages');
            if (dataEl) {
                dataEl.classList.remove('tc-fbody--hist');
                dataEl.innerHTML = `<div class="tc-empty">…</div>`;
            }
            if (msgEl) msgEl.innerHTML = '';
            try {
                const res = await fetch(`${this.cfg.devicePanelUrl}?device_id=${id}&_=${Date.now()}`, { credentials: 'same-origin', cache: 'no-store' });
                const data = await res.json();
                if (!res.ok || !data.success) throw new Error('failed');
                if (this._panelDeviceId !== id) return; // a newer panel was opened
                this._panel = data.panel;
                this._panelGraphRows = (data.panel.positions || []).map((p) => ({ label: String(p.time || '').slice(11, 16), speed: Math.round(p.speed || 0) }));
                this.renderPanelData(data.panel);
                this.renderPanelMessages(data.panel.positions || []);
            } catch (err) {
                if (dataEl) dataEl.innerHTML = `<div class="tc-empty">${escHtml(this.cfg.i18n?.loadFailed || 'Failed')}</div>`;
            }
        }

        renderPanelData(panel) {
            const el = document.getElementById('tcFooterData');
            if (!el) return;
            const i = this.cfg.i18n || {};
            const kmh = i.kmhUnit || 'km/h';
            const dash = '—';
            const kv = (k, v, icon) => `<div class="tc-kv"><span class="tc-kv-k">${icon ? `<i class="fas ${icon}"></i>` : ''}${escHtml(k)}</span><span class="tc-kv-v">${v}</span></div>`;
            const pos = (panel.lat != null && panel.lng != null)
                ? `<a href="#" data-tc-locate="${panel.lat},${panel.lng}">${Number(panel.lat).toFixed(6)}, ${Number(panel.lng).toFixed(6)}</a>`
                : dash;
            const colA = [
                kv(i.lblObject || 'Object', escHtml(panel.name || dash), panel.icon),
                kv(i.lblPlate || 'Plate', escHtml(panel.plate || dash), 'fa-id-card'),
                kv(i.lblStatus || 'Status', `<span style="color:${escHtml(panel.color || '#475569')}">${escHtml(panel.status || dash)}</span>`, 'fa-circle-info'),
                kv(i.lblOdometer || 'Odometer', panel.odometer != null ? `${Math.round(panel.odometer)} km` : dash, 'fa-gauge'),
                kv(i.lblAltitude || 'Altitude', panel.altitude != null ? `${panel.altitude} m` : dash, 'fa-mountain'),
                kv(i.lblAngle || 'Angle', panel.angle != null ? `${panel.angle}\u00b0` : dash, 'fa-compass'),
            ].join('');
            const colB = [
                kv(i.lblPosition || 'Position', pos, 'fa-location-dot'),
                kv(i.lblSpeed || 'Speed', `${panel.speed != null ? panel.speed : 0} ${kmh}`, 'fa-gauge-high'),
                kv(i.lblTimePosition || 'Time (position)', escHtml(panel.time_position || dash), 'fa-clock'),
                kv(i.lblTimeServer || 'Time (server)', escHtml(panel.time_server || dash), 'fa-server'),
                kv(i.lblIgnition || 'Ignition', panel.ignition == null ? dash : (panel.ignition ? (i.ignitionOn || 'On') : (i.ignitionOff || 'Off')), 'fa-key'),
            ].join('');

            const cmdTypes = this.cfg.commandTypes || {};
            const cmdEntries = Array.isArray(cmdTypes) ? cmdTypes.map((t) => [t, t]) : Object.entries(cmdTypes);
            const cmdOpts = cmdEntries.map(([value, label]) => `<option value="${escHtml(value)}">${escHtml(label)}</option>`).join('');
            const control = this.cfg.commandsSendUrl ? `
                <div class="tc-ctrl-row">
                    <select class="form-select form-select-sm" id="tcCmdType">${cmdOpts}</select>
                </div>
                <div class="tc-ctrl-row">
                    <input type="text" class="form-control form-control-sm" id="tcCmdData" placeholder="data (optional)">
                    <button type="button" class="btn btn-sm btn-primary" id="tcCmdSend">${escHtml(i.cmdSend || 'Send')}</button>
                </div>` : `<div class="tc-empty">—</div>`;

            const s = panel.stats || {};
            const stats = [
                kv(i.statRouteLength || 'Route length', `${s.distance_km != null ? s.distance_km : 0} km`),
                kv(i.statMoveDuration || 'Move duration', formatDuration(s.move_seconds || 0)),
                kv(i.statStopDuration || 'Stop duration', formatDuration(s.stop_seconds || 0)),
                kv(i.statTopSpeed || 'Top speed', `${s.top_speed != null ? s.top_speed : 0} ${kmh}`),
                kv(i.statAvgSpeed || 'Average speed', `${s.avg_speed != null ? s.avg_speed : 0} ${kmh}`),
            ].join('');

            const events = (panel.events || []);
            const eventsHtml = events.length
                ? `<ul class="tc-mini-events">${events.map((ev, idx) => {
                    const loc = hasGeo(ev.lat, ev.lng) ? `data-tc-locate="${ev.lat},${ev.lng}"` : '';
                    return `<li ${loc}><span>${escHtml(ev.title || ev.message || ev.type || ev.event_type || 'Event')}</span><span class="tc-me-time">${escHtml(String(ev.time || '').slice(0, 19))}</span></li>`;
                }).join('')}</ul>`
                : `<div class="tc-empty">${escHtml(i.noEvents || 'No events')}</div>`;

            // Recent tasks
            const tasks = panel.tasks || [];
            const tasksHtml = tasks.length
                ? `<ul class="tc-mini-events">${tasks.map((t) =>
                    `<li><span>${escHtml(t.name || '')}</span><span class="tc-me-time">${escHtml(t.status || '')}</span></li>`).join('')}</ul>`
                : `<div class="tc-empty">${escHtml(i.noTasks || 'No tasks.')}</div>`;

            // Fuel (falls back to battery, else "no data" like Traccar)
            let fuelHtml;
            if (panel.fuel != null) {
                fuelHtml = kv(i.lblFuel || 'Fuel', `${escHtml(String(panel.fuel))}`, 'fa-gas-pump')
                    + (panel.battery != null ? kv(i.lblBattery || 'Battery', `${escHtml(String(panel.battery))}%`, 'fa-battery-half') : '');
            } else if (panel.battery != null) {
                fuelHtml = kv(i.lblBattery || 'Battery', `${escHtml(String(panel.battery))}%`, 'fa-battery-half');
            } else {
                fuelHtml = `<div class="tc-empty">${escHtml(i.noDataFound || 'No data has been found.')}</div>`;
            }

            // Mileage (km) — daily bars (lazy-loaded to keep the panel instant)
            const mileageHtml = panel.mileage
                ? this.mileageBarsHtml(panel.mileage)
                : `<div id="tcMileageBody"><div class="tc-empty">…</div></div>`;

            // Speedometer gauge (SVG semicircle)
            const spd = Math.max(0, Math.round(parseFloat(panel.speed) || 0));
            const maxSpd = parseFloat(panel.speed_max) || 160;
            const frac = Math.min(1, spd / maxSpd);
            const gaugeColor = frac > 0.8 ? '#dc2626' : frac > 0.5 ? '#f59e0b' : '#16a34a';
            const speedoHtml = `<svg class="tc-gauge" viewBox="0 0 120 72">
                <path d="M10 62 A 50 50 0 0 1 110 62" fill="none" stroke="#e2e8f0" stroke-width="10" stroke-linecap="round" pathLength="100"></path>
                <path d="M10 62 A 50 50 0 0 1 110 62" fill="none" stroke="${gaugeColor}" stroke-width="10" stroke-linecap="round" pathLength="100" stroke-dasharray="${(frac * 100).toFixed(1)} 100"></path>
                <text x="60" y="52" text-anchor="middle" class="tc-gauge-val">${spd}</text>
                <text x="60" y="66" text-anchor="middle" class="tc-gauge-unit">${escHtml(kmh)}</text>
            </svg>`;

            // Notes / Photo
            const notesHtml = panel.notes
                ? `<div class="tc-notes">${escHtml(panel.notes)}</div>`
                : `<div class="tc-empty">${escHtml(i.noNotes || 'No notes.')}</div>`;
            const photoHtml = panel.photo
                ? `<img src="${escHtml(panel.photo)}" alt="" class="tc-photo">`
                : `<div class="tc-empty">${escHtml(i.noPhoto || 'No photo.')}</div>`;

            el.innerHTML = `<div class="tc-data-grid">
                <div class="tc-data-col">${colA}</div>
                <div class="tc-data-col">${colB}</div>
                <div class="tc-data-col"><h6>${escHtml(i.secObjectControl || 'Object control')}</h6>${control}</div>
                <div class="tc-data-col"><h6>${escHtml(i.secDailyStats || 'Daily statistics')}</h6>${stats}</div>
                <div class="tc-data-col"><h6>${escHtml(i.secRecentEvents || 'Recent events')}</h6>${eventsHtml}</div>
                <div class="tc-data-col"><h6>${escHtml(i.secRecentTasks || 'Recent tasks')}</h6>${tasksHtml}</div>
                <div class="tc-data-col"><h6>${escHtml(i.secFuel || 'Fuel')}</h6>${fuelHtml}</div>
                <div class="tc-data-col"><h6>${escHtml(i.secMileage || 'Mileage (km)')}</h6>${mileageHtml}</div>
                <div class="tc-data-col"><h6>${escHtml(i.secSpeedometer || 'Speedometer')}</h6>${speedoHtml}</div>
                <div class="tc-data-col"><h6>${escHtml(i.secNotes || 'Notes')}</h6>${notesHtml}</div>
                <div class="tc-data-col"><h6>${escHtml(i.secPhoto || 'Photo')}</h6>${photoHtml}</div>
            </div>`;

            el.querySelectorAll('[data-tc-locate]').forEach((node) => {
                node.addEventListener('click', (e) => {
                    e.preventDefault();
                    const [lat, lng] = node.dataset.tcLocate.split(',').map(parseFloat);
                    if (hasGeo(lat, lng)) {
                        this.map.panTo({ lat, lng });
                        if (this.map.getZoom() < 15) this.map.setZoom(16);
                    }
                });
            });
            el.querySelector('#tcCmdSend')?.addEventListener('click', () => this.sendCommand(panel.id));

            if (panel.mileage == null && this.cfg.deviceMileageUrl) {
                this.loadPanelMileage(panel.id);
            }
        }

        mileageBarsHtml(mileage) {
            const i = this.cfg.i18n || {};
            if (!mileage || !mileage.length) {
                return `<div class="tc-empty">${escHtml(i.noDataFound || 'No data has been found.')}</div>`;
            }
            const maxKm = Math.max(1, ...mileage.map((m) => parseFloat(m.km) || 0));
            const barColors = ['#60a5fa', '#38bdf8', '#f87171', '#34d399', '#a78bfa', '#fbbf24'];
            return `<div class="tc-bars">${mileage.map((m, idx) => {
                const km = parseFloat(m.km) || 0;
                const h = Math.max(3, Math.round((km / maxKm) * 100));
                return `<div class="tc-bar-col"><span class="tc-bar-val">${km}</span><div class="tc-bar" style="height:${h}%;background:${barColors[idx % barColors.length]}"></div><span class="tc-bar-lbl">${escHtml(m.label)}</span></div>`;
            }).join('')}</div>`;
        }

        async loadPanelMileage(id) {
            const i = this.cfg.i18n || {};
            try {
                const res = await fetch(`${this.cfg.deviceMileageUrl}?device_id=${id}&_=${Date.now()}`, { credentials: 'same-origin', cache: 'no-store' });
                const data = await res.json();
                if (this._panelDeviceId !== id) return; // a newer panel was opened
                const host = document.getElementById('tcMileageBody');
                if (host) host.innerHTML = this.mileageBarsHtml(res.ok && data.success ? data.mileage : []);
            } catch (e) {
                const host = document.getElementById('tcMileageBody');
                if (host) host.innerHTML = `<div class="tc-empty">${escHtml(i.noDataFound || 'No data has been found.')}</div>`;
            }
        }

        renderPanelMessages(positions) {
            const el = document.getElementById('tcFooterMessages');
            if (!el) return;
            const i = this.cfg.i18n || {};
            const rows = positions.slice(-100).reverse();
            if (!rows.length) { el.innerHTML = `<div class="tc-empty">${escHtml(i.noData || 'No data')}</div>`; return; }
            el.innerHTML = `<table class="tc-msg-table"><thead><tr>
                <th>${escHtml(i.colTime || 'Time')}</th><th>${escHtml(i.lblSpeed || 'Speed')}</th><th>${escHtml(i.lblPosition || 'Position')}</th>
            </tr></thead><tbody>${rows.map((p) => `<tr>
                <td>${escHtml(String(p.time || '—'))}</td>
                <td>${Math.round(p.speed || 0)} ${escHtml(i.kmhUnit || 'km/h')}</td>
                <td>${p.lat != null ? `${Number(p.lat).toFixed(5)}, ${Number(p.lng).toFixed(5)}` : '—'}</td>
            </tr>`).join('')}</tbody></table>`;
        }

        async sendCommand(deviceId) {
            if (!this.cfg.commandsSendUrl) return;
            const type = document.getElementById('tcCmdType')?.value;
            const data = document.getElementById('tcCmdData')?.value || '';
            const btn = document.getElementById('tcCmdSend');
            if (!type) return;
            btn?.setAttribute('disabled', 'disabled');
            try {
                const res = await fetch(this.cfg.commandsSendUrl, {
                    method: 'POST',
                    credentials: 'same-origin',
                    headers: { 'Content-Type': 'application/json', 'Accept': 'application/json', 'X-CSRF-TOKEN': this.cfg.csrfToken || '' },
                    body: JSON.stringify({ device_id: deviceId, type, data }),
                });
                const out = await res.json().catch(() => ({}));
                const ok = res.ok && out.success;
                const msg = out.message || (ok ? (this.cfg.i18n?.cmdSent || 'Command queued') : (this.cfg.i18n?.loadFailed || 'Failed'));
                if (global.Swal) global.Swal.fire({ icon: ok ? 'success' : 'error', title: msg, timer: ok ? 2200 : undefined, showConfirmButton: !ok });
                else alert(msg);
            } catch (err) {
                if (global.Swal) global.Swal.fire({ icon: 'error', title: this.cfg.i18n?.loadFailed || 'Failed' });
                else alert(this.cfg.i18n?.loadFailed || 'Failed');
            } finally {
                btn?.removeAttribute('disabled');
            }
        }

        /* ---------- Module popups (Reports, Geofences, etc.) ---------- */
        bindModules() {
            const modalEl = document.getElementById('tcModuleModal');
            const frame = document.getElementById('tcModuleFrame');
            if (!modalEl || !frame || !global.bootstrap) return;
            this._moduleModal = global.bootstrap.Modal.getOrCreateInstance(modalEl);
            const loader = document.getElementById('tcModuleLoader');

            document.querySelectorAll('[data-tc-module]').forEach((link) => {
                link.addEventListener('click', (e) => {
                    e.preventDefault();
                    const url = link.dataset.tcModule;
                    const title = link.dataset.tcModuleTitle || '';
                    const icon = link.dataset.tcModuleIcon || 'fa-satellite-dish';
                    const titleEl = document.getElementById('tcModuleTitle');
                    if (titleEl) titleEl.innerHTML = `<i class="fas ${escHtml(icon)} me-2"></i>${escHtml(title)}`;
                    frame.title = title;
                    if (loader) loader.hidden = false;
                    frame.src = url + (url.includes('?') ? '&' : '?') + 'embed=1';
                    this._moduleModal.show();
                });
            });

            frame.addEventListener('load', () => { if (loader && frame.src) loader.hidden = true; });
            modalEl.addEventListener('hidden.bs.modal', () => {
                frame.src = 'about:blank';
                if (loader) loader.hidden = false;
            });
        }

        addEndpoint(point, type) {
            if (!point || !hasGeo(point.lat, point.lng)) return;
            const url = type === 'start' ? (this.cfg.startIconUrl || '/images/map/marker-start.svg') : (this.cfg.endIconUrl || '/images/map/marker-end.svg');
            const marker = new google.maps.Marker({
                position: { lat: point.lat, lng: point.lng }, map: this.map,
                icon: { url, scaledSize: new google.maps.Size(40, 40), anchor: new google.maps.Point(20, 20) },
                zIndex: type === 'start' ? 600 : 601,
                title: type === 'start' ? (this.cfg.i18n?.routeStart || 'Start') : (this.cfg.i18n?.routeEnd || 'End'),
            });
            this.historyLayers.push(marker);
        }

        /**
         * Pulsing overlay at the route end (the vehicle's current location),
         * mirroring the live device page. Colored by the vehicle's last status.
         */
        showHistoryPulse(point, vehicle) {
            if (!point || !hasGeo(point.lat, point.lng)) return;
            const VM = global.VehicleMarker;
            if (!VM || typeof VM.createPulseController !== 'function') return;
            if (!this.historyPulse) {
                this.historyPulse = VM.createPulseController({
                    googleMaps: global.google,
                    stateColors: this.stateColors,
                    getColor: (p) => colorForPoint(p, this.stateColors),
                });
            }
            this.historyPulse.attachMap(this.map);
            const statusKey = point.status_key || vehicle?.status_key || 'moving';
            this.historyPulse.update({
                lat: point.lat,
                lng: point.lng,
                status_key: statusKey,
                color: point.color || vehicle?.color || this.stateColors[statusKey],
            });
        }

        addEventDot(ev) {
            const marker = new google.maps.Marker({
                position: { lat: ev.lat, lng: ev.lng }, map: this.map,
                icon: { path: google.maps.SymbolPath.CIRCLE, fillColor: ev.type === 'stop' ? '#f97316' : '#ef4444', fillOpacity: 0.95, strokeColor: '#fff', strokeWeight: 2, scale: 6 },
                title: ev.title || ev.type, zIndex: 550,
            });
            this.historyLayers.push(marker);
        }

        renderSpeedLegend(name) {
            if (!this.legendEl) return;
            const rows = [
                { c: '#64748b', t: '0' }, { c: '#22c55e', t: `≤ ${MEDIUM_SPEED}` },
                { c: '#eab308', t: `≤ ${OVER_SPEED}` }, { c: '#ef4444', t: `> ${OVER_SPEED}` },
            ];
            this.legendEl.innerHTML = (name ? `<div class="tc-legend-item"><strong>${escHtml(name)}</strong></div>` : '') +
                rows.map((r) => `<div class="tc-legend-item"><span class="tc-legend-swatch" style="background:${r.c}"></span>${escHtml(r.t)} ${this.cfg.i18n?.kmhUnit || 'km/h'}</div>`).join('');
        }

        fitLayers(layers) {
            const bounds = new google.maps.LatLngBounds();
            let count = 0;
            layers.forEach((l) => {
                if (l.getPath) l.getPath().forEach((ll) => { bounds.extend(ll); count++; });
                else if (l.getPosition) { bounds.extend(l.getPosition()); count++; }
            });
            if (count > 0) this.map.fitBounds(bounds, 60);
        }

        /* ---------- Events tab ---------- */
        bindEventsTab() {
            document.getElementById('tcEventsReload')?.addEventListener('click', () => this.loadEvents());
        }

        async loadEvents() {
            const listEl = document.getElementById('tcEventsList');
            if (!listEl || !this.cfg.eventsJsonUrl) {
                if (listEl) listEl.innerHTML = `<div class="tc-empty">—</div>`;
                return;
            }
            listEl.innerHTML = `<div class="tc-empty">…</div>`;
            try {
                const res = await fetch(`${this.cfg.eventsJsonUrl}?per_page=50&_=${Date.now()}`, { credentials: 'same-origin', cache: 'no-store' });
                const data = await res.json();
                this._eventsLoaded = true;
                const events = data.events || [];
                if (events.length === 0) { listEl.innerHTML = `<div class="tc-empty">${escHtml(this.cfg.i18n?.noData || 'No data')}</div>`; return; }
                listEl.innerHTML = events.map((ev, i) => {
                    const title = escHtml(ev.title || ev.message || ev.type || ev.event_type || 'Event');
                    const sub = escHtml(`${ev.device_name || ''} · ${ev.time || ev.recorded_at || ''}`);
                    const loc = hasGeo(ev.lat, ev.lng) ? `data-lat="${ev.lat}" data-lng="${ev.lng}"` : '';
                    return `<div class="tc-row" data-ev="${i}" ${loc}>
                        <span class="tc-dot" style="background:#ef4444"></span>
                        <span class="tc-row-info">
                            <span class="tc-row-title">${title}</span>
                            <span class="tc-row-meta">${sub}</span>
                        </span></div>`;
                }).join('');
                listEl.querySelectorAll('[data-ev]').forEach((row) => {
                    row.addEventListener('click', () => {
                        const lat = parseFloat(row.dataset.lat), lng = parseFloat(row.dataset.lng);
                        if (hasGeo(lat, lng)) this.locateEvent(lat, lng);
                    });
                });
            } catch (err) {
                console.error('[traccar-ui] events', err);
                listEl.innerHTML = `<div class="tc-empty">${escHtml(this.cfg.i18n?.loadFailed || 'Failed')}</div>`;
            }
        }

        locateEvent(lat, lng) {
            this.eventMarker?.setMap(null);
            this.eventMarker = new google.maps.Marker({
                position: { lat, lng }, map: this.map,
                icon: { path: google.maps.SymbolPath.CIRCLE, fillColor: '#ef4444', fillOpacity: 1, strokeColor: '#fff', strokeWeight: 3, scale: 9 },
                zIndex: 999,
            });
            this.map.panTo({ lat, lng });
            if (this.map.getZoom() < 14) this.map.setZoom(15);
        }

        /* ---------- Places (geofences) ---------- */
        bindPlacesTab() {
            document.getElementById('tcPlacesReload')?.addEventListener('click', () => this.loadPlaces());
        }

        clearPlaces() {
            this.placeLayers.forEach((l) => l.setMap(null));
            this.placeLayers = [];
        }

        async loadPlaces() {
            const listEl = document.getElementById('tcPlacesList');
            if (!listEl || !this.cfg.geofencesJsonUrl) {
                if (listEl) listEl.innerHTML = `<div class="tc-empty">—</div>`;
                return;
            }
            listEl.innerHTML = `<div class="tc-empty">…</div>`;
            try {
                const res = await fetch(`${this.cfg.geofencesJsonUrl}?_=${Date.now()}`, { credentials: 'same-origin', cache: 'no-store' });
                const data = await res.json();
                this._placesLoaded = true;
                this.clearPlaces();
                const fences = data.geofences || [];
                if (fences.length === 0) { listEl.innerHTML = `<div class="tc-empty">${escHtml(this.cfg.i18n?.noData || 'No data')}</div>`; return; }
                fences.forEach((g) => this.drawGeofence(g));
                listEl.innerHTML = fences.map((g, i) => `<div class="tc-row" data-place="${i}">
                    <span class="tc-dot" style="background:#0891b2"></span>
                    <span class="tc-row-info">
                        <span class="tc-row-title">${escHtml(g.name || ('#' + g.id))}</span>
                        <span class="tc-row-meta">${escHtml(g.device_name || '')} · ${escHtml(g.type || '')}</span>
                    </span></div>`).join('');
                listEl.querySelectorAll('[data-place]').forEach((row, i) => {
                    row.addEventListener('click', () => this.focusGeofence(fences[i]));
                });
            } catch (err) {
                console.error('[traccar-ui] places', err);
                listEl.innerHTML = `<div class="tc-empty">${escHtml(this.cfg.i18n?.loadFailed || 'Failed')}</div>`;
            }
        }

        drawGeofence(g) {
            // Match Traccar: use the geofence's own attributes.color, fall back to a default.
            const color = (g.color && String(g.color).trim()) || '#0891b2';
            if (g.type === 'circle' && g.center && g.radius) {
                const c = new google.maps.Circle({
                    center: { lat: parseFloat(g.center.lat ?? g.center[0]), lng: parseFloat(g.center.lng ?? g.center[1]) },
                    radius: parseFloat(g.radius), map: this.map,
                    strokeColor: color, strokeWeight: 2, fillColor: color, fillOpacity: 0.12,
                });
                this.placeLayers.push(c);
            } else if (Array.isArray(g.coords) && g.coords.length >= 3) {
                const path = g.coords.map((p) => ({ lat: parseFloat(p.lat ?? p[0]), lng: parseFloat(p.lng ?? p[1]) }));
                const poly = new google.maps.Polygon({
                    paths: path, map: this.map,
                    strokeColor: color, strokeWeight: 2, fillColor: color, fillOpacity: 0.12,
                });
                this.placeLayers.push(poly);
            }
        }

        focusGeofence(g) {
            const bounds = new google.maps.LatLngBounds();
            if (g.type === 'circle' && g.center) {
                bounds.extend({ lat: parseFloat(g.center.lat ?? g.center[0]), lng: parseFloat(g.center.lng ?? g.center[1]) });
            } else if (Array.isArray(g.coords)) {
                g.coords.forEach((p) => bounds.extend({ lat: parseFloat(p.lat ?? p[0]), lng: parseFloat(p.lng ?? p[1]) }));
            }
            if (!bounds.isEmpty()) this.map.fitBounds(bounds, 80);
        }
    }

    function init() {
        const cfg = global.TRACCAR_UI_CONFIG;
        if (!cfg) return;
        new TraccarUi(cfg).boot();
    }

    if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', init);
    else init();
})(typeof window !== 'undefined' ? window : globalThis);
