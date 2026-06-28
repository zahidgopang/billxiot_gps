/**
 * Global Tracking — multi-vehicle live map with default pins, pulse, short trails, Pusher + poll.
 */
(function (global) {
    'use strict';

    const DEFAULT_CENTER = { lat: 25.276987, lng: 55.296249 };
    const TRAIL_MAX = 20;
    const MOVING_KEYS = new Set(['running', 'moving']);
    const CLEAR_TRAIL_KEYS = new Set(['stopped', 'idle', 'offline', 'parked']);

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

    function shouldKeepTrail(statusKey) {
        if (CLEAR_TRAIL_KEYS.has(statusKey)) {
            return false;
        }
        return MOVING_KEYS.has(statusKey);
    }

    function samePosition(a, b) {
        if (!a || !b) return false;
        return Math.abs(a.lat - b.lat) < 1e-6 && Math.abs(a.lng - b.lng) < 1e-6;
    }

    class GlobalTrackingLive {
        constructor(cfg) {
            this.cfg = cfg;
            this.stateColors = cfg.stateColors || {};
            this.map = null;
            this.vehicles = new Map();
            this.selected = new Set();
            this.followId = null;
            this.pollTimer = null;
            this.pollInFlight = false;
            this.echoChannels = new Map();
            this.initialFitDone = false;
        }

        showError(message) {
            const el = document.getElementById('gtMapError');
            const mapEl = document.getElementById('gtMap');
            if (el) {
                el.hidden = false;
                const text = el.querySelector('[data-gt-error-text]');
                if (text) text.textContent = message;
            }
            if (mapEl) mapEl.setAttribute('aria-hidden', 'true');
        }

        boot() {
            const cfg = this.cfg;
            if (!cfg.googleMapsKey) {
                this.showError(cfg.i18n?.mapApiKeyMissing || 'Google Maps API key is missing.');
                return;
            }

            (cfg.vehicles || []).forEach((v) => this.vehicles.set(v.id, { ...v }));

            const callbackName = '__globalTrackingGoogleReady';
            global[callbackName] = () => {
                try { delete global[callbackName]; } catch (_) { global[callbackName] = undefined; }
                if (!global.google?.maps?.Map) {
                    this.showError(cfg.i18n?.loadingMapFailed || 'Google Maps failed to initialize.');
                    return;
                }
                this.initMap();
            };

            const existing = document.querySelector('script[data-global-tracking-maps]');
            if (existing) {
                if (global.google?.maps?.Map) {
                    this.initMap();
                } else {
                    existing.addEventListener('load', () => global[callbackName]?.(), { once: true });
                }
                return;
            }

            const script = document.createElement('script');
            script.dataset.globalTrackingMaps = '1';
            script.async = true;
            script.defer = true;
            script.onerror = () => this.showError(cfg.i18n?.loadingMapFailed || 'Could not load Google Maps.');
            script.src = `https://maps.googleapis.com/maps/api/js?key=${encodeURIComponent(cfg.googleMapsKey)}&callback=${callbackName}`;
            document.head.appendChild(script);
        }

        initMap() {
            const VM = global.VehicleMarker;
            if (!VM) {
                this.showError('Map marker module failed to load.');
                return;
            }

            const mapEl = document.getElementById('gtMap');
            if (!mapEl) return;

            this.map = new google.maps.Map(mapEl, {
                center: DEFAULT_CENTER,
                zoom: 11,
                mapTypeControl: false,
                streetViewControl: false,
                fullscreenControl: true,
            });

            this.bindUi();
            this.renderList();
        }

        bindUi() {
            const search = document.getElementById('gtSearch');
            search?.addEventListener('input', () => this.renderList());

            document.getElementById('gtSelectAll')?.addEventListener('click', () => {
                this.vehicles.forEach((_, id) => this.setSelected(id, true));
            });
            document.getElementById('gtSelectNone')?.addEventListener('click', () => {
                this.selected.forEach((id) => this.setSelected(id, false));
            });

            document.getElementById('gtBtnFit')?.addEventListener('click', () => this.fitAll());
            document.getElementById('gtBtnFollow')?.addEventListener('click', () => this.toggleFollow());
            document.getElementById('gtBtnRefresh')?.addEventListener('click', () => this.pollLive(true));
        }

        renderList() {
            const listEl = document.getElementById('gtVehicleList');
            if (!listEl) return;

            const q = (document.getElementById('gtSearch')?.value || '').trim().toLowerCase();
            const items = [...this.vehicles.values()].filter((v) => {
                if (!q) return true;
                const hay = `${v.title || ''} ${v.plate || ''} ${v.imei || ''}`.toLowerCase();
                return hay.includes(q);
            });

            if (items.length === 0) {
                listEl.innerHTML = `<div class="gt-empty-list text-muted small p-3">${escHtml(this.cfg.i18n?.noVehicles || 'No vehicles found')}</div>`;
                return;
            }

            listEl.innerHTML = items.map((v) => {
                const checked = this.selected.has(v.id) ? 'checked' : '';
                const dotColor = v.color || colorForPoint(v, this.stateColors);
                return `<label class="gt-vehicle-row" data-vehicle-id="${v.id}">
                    <input type="checkbox" class="form-check-input gt-vehicle-check" data-id="${v.id}" ${checked}>
                    <span class="gt-status-dot" style="background:${escHtml(dotColor)}"></span>
                    <span class="gt-vehicle-info">
                        <span class="gt-vehicle-title">${escHtml(v.title || v.plate || ('#' + v.id))}</span>
                        <span class="gt-vehicle-meta" data-meta-id="${v.id}">
                            ${this.metaHtml(v)}
                        </span>
                    </span>
                </label>`;
            }).join('');

            listEl.querySelectorAll('.gt-vehicle-check').forEach((cb) => {
                cb.addEventListener('change', (e) => {
                    const id = parseInt(e.target.dataset.id, 10);
                    this.setSelected(id, e.target.checked);
                });
            });
        }

        metaHtml(v) {
            const i18n = this.cfg.i18n || {};
            const status = escHtml(v.status_label || v.status_key || '—');
            const speed = v.speed != null ? `${v.speed} ${i18n.kmhUnit || 'km/h'}` : '—';
            const updated = escHtml(v.recorded_at_human || '—');
            const parts = [
                `<span>${status}</span>`,
                `<span>${speed}</span>`,
                `<span>${updated}</span>`,
            ];
            if (v.gsm_signal != null) parts.push(`<span>GSM ${v.gsm_signal}</span>`);
            if (v.satellites != null) parts.push(`<span>SAT ${v.satellites}</span>`);
            if (v.battery_level != null) parts.push(`<span>${v.battery_level}%</span>`);
            if (v.ignition != null) parts.push(`<span>${v.ignition ? (i18n.ignitionOn || 'Ign ON') : (i18n.ignitionOff || 'Ign OFF')}</span>`);
            return parts.join(' · ');
        }

        updateRowMeta(id, point) {
            const meta = document.querySelector(`[data-meta-id="${id}"]`);
            if (!meta) return;
            const merged = { ...this.vehicles.get(id), ...point };
            meta.innerHTML = this.metaHtml(merged);
            const dot = meta.closest('.gt-vehicle-row')?.querySelector('.gt-status-dot');
            if (dot) dot.style.background = colorForPoint(merged, this.stateColors);
        }

        vehicleState(id) {
            if (!this._states) this._states = new Map();
            if (!this._states.has(id)) {
                this._states.set(id, {
                    marker: null,
                    pulse: null,
                    trail: [],
                    trailPolylines: [],
                    lastPoint: null,
                    animFrame: null,
                });
            }
            return this._states.get(id);
        }

        setSelected(id, on) {
            if (on) {
                this.selected.add(id);
                this.ensureMarker(id);
                this.subscribePusher(id);
                if (!this.initialFitDone) {
                    this.initialFitDone = true;
                    this.fitAll();
                }
            } else {
                this.selected.delete(id);
                this.teardownVehicle(id);
                if (this.followId === id) {
                    this.followId = null;
                    this.updateFollowBtn();
                }
            }
            this.renderList();
            this.startPolling();
        }

        ensureMarker(id) {
            const v = this.vehicles.get(id);
            const st = this.vehicleState(id);
            if (!this.map || !v || st.marker) return;

            const VM = global.VehicleMarker;
            const color = colorForPoint(v, this.stateColors);
            const pos = v.lat != null && v.lng != null ? { lat: v.lat, lng: v.lng } : null;

            st.marker = new google.maps.Marker({
                map: pos ? this.map : null,
                position: pos || DEFAULT_CENTER,
                icon: VM.pinIconFor(color, google.maps),
                zIndex: 500 + id,
                optimized: false,
            });

            st.pulse = VM.createPulseController({
                googleMaps: google.maps,
                stateColors: this.stateColors,
                getColor: (p) => colorForPoint(p, this.stateColors),
                isHidden: (p) => !p || p.lat == null || p.lng == null,
            });
            st.pulse.attachMap(this.map);

            if (v.lat != null && v.lng != null) {
                st.lastPoint = { ...v, lat: v.lat, lng: v.lng };
                st.pulse.update(st.lastPoint);
            }
        }

        teardownVehicle(id) {
            const st = this.vehicleState(id);
            if (st.animFrame) {
                cancelAnimationFrame(st.animFrame);
                st.animFrame = null;
            }
            st.marker?.setMap(null);
            st.pulse?.hide();
            this.clearTrail(st);
            st.marker = null;
            st.pulse = null;
            st.lastPoint = null;
            this.unsubscribePusher(id);
        }

        clearTrail(st) {
            st.trail = [];
            st.trailPolylines.forEach((p) => p.setMap(null));
            st.trailPolylines = [];
        }

        pushTrailPoint(st, point) {
            const key = point.status_key || 'offline';
            if (!shouldKeepTrail(key)) {
                this.clearTrail(st);
                return;
            }
            if (st.trail.length === 0 || !samePosition(st.trail[st.trail.length - 1], point)) {
                st.trail.push({ lat: point.lat, lng: point.lng });
            }
            while (st.trail.length > TRAIL_MAX) {
                st.trail.shift();
                const old = st.trailPolylines.shift();
                old?.setMap(null);
            }
            if (st.trail.length >= 2) {
                const from = st.trail[st.trail.length - 2];
                const to = st.trail[st.trail.length - 1];
                const color = colorForPoint(point, this.stateColors);
                const line = new google.maps.Polyline({
                    path: [from, to],
                    strokeColor: color,
                    strokeOpacity: 0.85,
                    strokeWeight: 4,
                    map: this.map,
                    zIndex: 100,
                    clickable: false,
                });
                st.trailPolylines.push(line);
            }
        }

        applyPoint(id, point) {
            if (!this.selected.has(id) || !point || point.lat == null || point.lng == null) return;

            const st = this.vehicleState(id);
            this.ensureMarker(id);

            const merged = {
                ...this.vehicles.get(id),
                ...point,
                id,
            };
            this.vehicles.set(id, merged);
            this.updateRowMeta(id, merged);

            const prev = st.lastPoint;
            const key = merged.status_key || 'offline';
            if (!shouldKeepTrail(key)) {
                this.clearTrail(st);
            }

            if (!prev || samePosition(prev, merged)) {
                st.marker.setMap(this.map);
                st.marker.setPosition({ lat: merged.lat, lng: merged.lng });
                st.marker.setIcon(global.VehicleMarker.pinIconFor(colorForPoint(merged, this.stateColors), google.maps));
                st.pulse.update(merged);
                st.lastPoint = merged;
                if (shouldKeepTrail(key)) this.pushTrailPoint(st, merged);
                if (this.followId === id) this.map.panTo({ lat: merged.lat, lng: merged.lng });
                return;
            }

            this.animateTo(id, prev, merged);
        }

        animateTo(id, from, to) {
            const st = this.vehicleState(id);
            if (st.animFrame) cancelAnimationFrame(st.animFrame);

            const duration = this.cfg.animDurationMs || 1200;
            const start = performance.now();
            const fromHeading = parseFloat(from.heading || 0);
            const toHeading = parseFloat(to.heading || 0);

            const step = (now) => {
                const t = Math.min(1, (now - start) / duration);
                const eased = 1 - Math.pow(1 - t, 3);
                const lat = from.lat + (to.lat - from.lat) * eased;
                const lng = from.lng + (to.lng - from.lng) * eased;
                let delta = ((toHeading - fromHeading + 540) % 360) - 180;
                const heading = (fromHeading + delta * eased + 360) % 360;
                const frame = { ...to, lat, lng, heading };

                st.marker.setMap(this.map);
                st.marker.setPosition({ lat, lng });
                st.marker.setIcon(global.VehicleMarker.pinIconFor(colorForPoint(frame, this.stateColors), google.maps));
                st.pulse.update(frame);

                if (this.followId === id && t > 0.4) {
                    this.map.panTo({ lat, lng });
                }

                if (t < 1) {
                    st.animFrame = requestAnimationFrame(step);
                } else {
                    st.animFrame = null;
                    st.lastPoint = to;
                    if (shouldKeepTrail(to.status_key || 'offline')) {
                        this.pushTrailPoint(st, to);
                    }
                }
            };

            st.animFrame = requestAnimationFrame(step);
        }

        selectedIds() {
            return [...this.selected];
        }

        startPolling() {
            if (this.pollTimer) {
                clearInterval(this.pollTimer);
                this.pollTimer = null;
            }
            if (this.selected.size === 0) return;

            this.pollLive(true);
            const ms = this.cfg.pollIntervalMs || 4000;
            this.pollTimer = setInterval(() => this.pollLive(false), ms);
        }

        async pollLive(force) {
            if (this.pollInFlight && !force) return;
            const ids = this.selectedIds();
            if (ids.length === 0) return;

            const url = `${this.cfg.liveJsonUrl}?ids=${ids.join(',')}&_=${Date.now()}`;
            this.pollInFlight = true;
            try {
                const res = await fetch(url, { credentials: 'same-origin', cache: 'no-store' });
                if (!res.ok) return;
                const data = await res.json();
                (data.devices || []).forEach((d) => this.applyPoint(d.id, d));
            } catch (err) {
                console.warn('[global-tracking] poll failed', err);
            } finally {
                this.pollInFlight = false;
            }
        }

        subscribePusher(id) {
            if (!global.Echo || typeof global.Echo.private !== 'function') return;
            if (this.echoChannels.has(id)) return;

            try {
                const channel = global.Echo.private(`device.${id}`);
                channel.listen('.DeviceLocationUpdated', (payload) => {
                    const loc = payload.location || payload;
                    if (loc && loc.id == null) loc.id = id;
                    this.applyPoint(id, loc);
                });
                this.echoChannels.set(id, channel);
            } catch (err) {
                console.warn('[global-tracking] Echo subscribe failed for', id, err);
            }
        }

        unsubscribePusher(id) {
            const ch = this.echoChannels.get(id);
            if (!ch) return;
            try {
                global.Echo.leave(`device.${id}`);
            } catch (_) { /* ignore */ }
            this.echoChannels.delete(id);
        }

        fitAll() {
            if (!this.map) return;
            const bounds = new google.maps.LatLngBounds();
            let count = 0;
            this.selected.forEach((id) => {
                const v = this.vehicles.get(id);
                if (v?.lat != null && v?.lng != null) {
                    bounds.extend({ lat: v.lat, lng: v.lng });
                    count++;
                }
            });
            if (count === 0) return;
            if (count === 1) {
                this.map.setCenter(bounds.getCenter());
                this.map.setZoom(14);
            } else {
                this.map.fitBounds(bounds, 48);
            }
        }

        toggleFollow() {
            const ids = this.selectedIds();
            if (ids.length === 0) return;
            if (this.followId && ids.includes(this.followId)) {
                this.followId = null;
            } else {
                this.followId = ids[0];
                const v = this.vehicles.get(this.followId);
                if (v?.lat != null) this.map.panTo({ lat: v.lat, lng: v.lng });
            }
            this.updateFollowBtn();
        }

        updateFollowBtn() {
            const btn = document.getElementById('gtBtnFollow');
            if (!btn) return;
            btn.classList.toggle('active', !!this.followId);
            btn.setAttribute('aria-pressed', this.followId ? 'true' : 'false');
        }
    }

    function init() {
        const cfg = global.GLOBAL_TRACKING_CONFIG;
        if (!cfg) return;
        const app = new GlobalTrackingLive(cfg);
        app.boot();
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init);
    } else {
        init();
    }
})(typeof window !== 'undefined' ? window : globalThis);
