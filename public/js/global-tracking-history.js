/**
 * Global Tracking — single-vehicle history map (Traccar-style "Object" filter).
 *
 * Only one vehicle is rendered at a time. Loading a single route keeps the map
 * responsive; rendering many vehicles at once could hang the browser.
 */
(function (global) {
    'use strict';

    const DEFAULT_CENTER = { lat: 25.276987, lng: 55.296249 };
    const MEDIUM_SPEED = 60;
    const OVER_SPEED = 80;
    const STOP_MIN_SEC = 120;

    function escHtml(s) {
        return String(s ?? '')
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/"/g, '&quot;');
    }

    function speedToColor(speed) {
        const spd = parseFloat(speed || 0);
        if (spd <= 0) return '#64748b';
        if (spd <= MEDIUM_SPEED) return '#22c55e';
        if (spd <= OVER_SPEED) return '#eab308';
        return '#ef4444';
    }

    function detectStops(points) {
        if (!points || points.length < 2) return [];
        const events = [];
        let stopRun = [];

        const flush = () => {
            if (stopRun.length < 2) {
                stopRun = [];
                return;
            }
            const t0 = stopRun[0].recorded_at ? new Date(stopRun[0].recorded_at).getTime() : null;
            const t1 = stopRun[stopRun.length - 1].recorded_at ? new Date(stopRun[stopRun.length - 1].recorded_at).getTime() : null;
            if (t0 && t1 && (t1 - t0) / 1000 >= STOP_MIN_SEC) {
                const mid = stopRun[Math.floor(stopRun.length / 2)];
                events.push({
                    type: 'stop',
                    lat: mid.lat,
                    lng: mid.lng,
                    title: 'Long stop',
                });
            }
            stopRun = [];
        };

        points.forEach((p) => {
            if (parseFloat(p.speed || 0) <= 1) {
                stopRun.push(p);
            } else {
                flush();
            }
        });
        flush();
        return events;
    }

    class GlobalTrackingHistory {
        constructor(cfg) {
            this.cfg = cfg;
            this.map = null;
            this.vehicles = new Map();
            this.selectedId = null;
            this.layers = [];
            this.legendEl = null;
        }

        showError(message) {
            const el = document.getElementById('gtHistoryError');
            if (el) {
                el.hidden = false;
                el.querySelector('[data-gt-error-text]')?.replaceChildren(document.createTextNode(message));
            }
        }

        toggleEmpty(show) {
            const el = document.getElementById('gtHistoryEmpty');
            if (el) el.hidden = !show;
        }

        boot() {
            const cfg = this.cfg;
            if (!cfg.googleMapsKey) {
                this.showError(cfg.i18n?.mapApiKeyMissing || 'Google Maps API key is missing.');
                return;
            }

            (cfg.vehicles || []).forEach((v) => this.vehicles.set(v.id, { ...v }));

            const callbackName = '__globalTrackingHistoryReady';
            global[callbackName] = () => {
                try { delete global[callbackName]; } catch (_) { global[callbackName] = undefined; }
                if (!global.google?.maps?.Map) {
                    this.showError(cfg.i18n?.loadingMapFailed || 'Google Maps failed to initialize.');
                    return;
                }
                this.initMap();
            };

            const script = document.createElement('script');
            script.dataset.globalTrackingHistoryMaps = '1';
            script.async = true;
            script.defer = true;
            script.onerror = () => this.showError(cfg.i18n?.loadingMapFailed || 'Could not load Google Maps.');
            script.src = `https://maps.googleapis.com/maps/api/js?key=${encodeURIComponent(cfg.googleMapsKey)}&callback=${callbackName}`;
            document.head.appendChild(script);
        }

        initMap() {
            const mapEl = document.getElementById('gtHistoryMap');
            if (!mapEl) return;

            this.map = new google.maps.Map(mapEl, {
                center: DEFAULT_CENTER,
                zoom: 11,
                mapTypeControl: false,
                streetViewControl: false,
                fullscreenControl: true,
            });

            this.legendEl = document.getElementById('gtHistoryLegend');
            this.bindUi();
            this.setDefaultDates();
            this.toggleEmpty(true);
        }

        setDefaultDates() {
            const now = new Date();
            const fmt = (d) => d.toISOString().slice(0, 10);
            const fromEl = document.getElementById('gtDateFrom');
            const toEl = document.getElementById('gtDateTo');
            if (fromEl && !fromEl.value) fromEl.value = fmt(now);
            if (toEl && !toEl.value) toEl.value = fmt(now);
        }

        bindUi() {
            const selectEl = document.getElementById('gtHistoryVehicle');
            if (selectEl) {
                this.selectedId = parseInt(selectEl.value, 10) || null;

                const $ = global.jQuery;
                if ($ && typeof $.fn?.select2 === 'function') {
                    const $sel = $(selectEl);
                    $sel.select2({
                        width: '100%',
                        placeholder: this.cfg.i18n?.selectVehicle || 'Select a vehicle',
                        dir: document.documentElement.getAttribute('dir') || 'ltr',
                    });
                    $sel.on('change', () => {
                        this.selectedId = parseInt($sel.val(), 10) || null;
                    });
                } else {
                    selectEl.addEventListener('change', (e) => {
                        this.selectedId = parseInt(e.target.value, 10) || null;
                    });
                }
            }

            document.getElementById('gtHistoryLoad')?.addEventListener('click', () => this.loadHistory());
            document.getElementById('gtHistoryFit')?.addEventListener('click', () => this.fitRoutes(true));
            document.getElementById('gtHistoryClear')?.addEventListener('click', () => {
                this.clearMap();
                this.toggleEmpty(true);
            });
        }

        buildQuery() {
            const fromDate = document.getElementById('gtDateFrom')?.value || '';
            const toDate = document.getElementById('gtDateTo')?.value || '';
            const timeFrom = document.getElementById('gtTimeFrom')?.value || '';
            const timeTo = document.getElementById('gtTimeTo')?.value || '';

            let from = fromDate;
            let to = toDate;
            if (fromDate && timeFrom) from = `${fromDate} ${timeFrom}`;
            if (toDate && timeTo) to = `${toDate} ${timeTo}`;

            const params = new URLSearchParams();
            params.set('ids', String(this.selectedId));
            if (from) params.set('from', from);
            if (to) params.set('to', to);
            return params;
        }

        async loadHistory() {
            if (!this.selectedId) {
                alert(this.cfg.i18n?.selectVehicle || 'Select a vehicle.');
                return;
            }

            const btn = document.getElementById('gtHistoryLoad');
            btn?.setAttribute('disabled', 'disabled');

            try {
                const params = this.buildQuery();
                const url = `${this.cfg.historyJsonUrl}?${params.toString()}&_=${Date.now()}`;
                const res = await fetch(url, { credentials: 'same-origin', cache: 'no-store' });
                const data = await res.json();
                if (!res.ok) throw new Error(data.message || 'Request failed');
                this.drawHistory((data.vehicles || [])[0] || null);
            } catch (err) {
                console.error('[global-tracking-history]', err);
                alert(this.cfg.i18n?.loadFailed || 'Failed to load history.');
            } finally {
                btn?.removeAttribute('disabled');
            }
        }

        drawHistory(vehicle) {
            this.clearMap();

            const points = vehicle?.points || [];
            if (points.length < 2) {
                this.toggleEmpty(true);
                if (this.legendEl) this.legendEl.innerHTML = '';
                if (points.length === 1) {
                    this.addEndpoint(points[0], 'start');
                    this.toggleEmpty(false);
                    this.fitRoutes(true);
                }
                return;
            }

            this.toggleEmpty(false);
            this.drawSpeedRoute(points);
            this.addEndpoint(points[0], 'start');
            this.addEndpoint(points[points.length - 1], 'end');

            const routeEvents = detectStops(points);
            (vehicle.events || []).forEach((ev) => {
                if (ev.lat != null && ev.lng != null) {
                    routeEvents.push({
                        type: ev.event_type || ev.type || 'event',
                        lat: ev.lat,
                        lng: ev.lng,
                        title: ev.title || ev.message || 'Event',
                    });
                }
            });
            routeEvents.forEach((ev) => this.addEventMarker(ev));

            this.renderSpeedLegend(vehicle.name);
            this.fitRoutes(true);
        }

        renderSpeedLegend(name) {
            if (!this.legendEl) return;
            const rows = [
                { c: '#64748b', t: '0' },
                { c: '#22c55e', t: `≤ ${MEDIUM_SPEED}` },
                { c: '#eab308', t: `≤ ${OVER_SPEED}` },
                { c: '#ef4444', t: `> ${OVER_SPEED}` },
            ];
            this.legendEl.innerHTML =
                (name ? `<div class="gt-legend-item"><strong>${escHtml(name)}</strong></div>` : '') +
                rows.map((r) =>
                    `<div class="gt-legend-item"><span class="gt-legend-swatch" style="background:${r.c}"></span>${escHtml(r.t)} km/h</div>`
                ).join('');
        }

        clearMap() {
            this.layers.forEach((layer) => {
                if (layer.setMap) layer.setMap(null);
                else if (Array.isArray(layer)) layer.forEach((l) => l.setMap?.(null));
            });
            this.layers = [];
            if (this.legendEl) this.legendEl.innerHTML = '';
        }

        drawSpeedRoute(points) {
            for (let i = 1; i < points.length; i++) {
                const a = points[i - 1];
                const b = points[i];
                const speed = Math.max(parseFloat(a.speed || 0), parseFloat(b.speed || 0));
                const line = new google.maps.Polyline({
                    path: [{ lat: a.lat, lng: a.lng }, { lat: b.lat, lng: b.lng }],
                    strokeColor: speedToColor(speed),
                    strokeOpacity: 0.92,
                    strokeWeight: speed <= 0 ? 6 : speed <= MEDIUM_SPEED ? 7 : speed <= OVER_SPEED ? 8 : 9,
                    map: this.map,
                    zIndex: 2,
                    clickable: false,
                });
                this.layers.push(line);
            }
        }

        endpointIcon(type) {
            const url = type === 'start'
                ? (this.cfg.startIconUrl || '/images/map/marker-start.svg')
                : (this.cfg.endIconUrl || '/images/map/marker-end.svg');
            return {
                url,
                scaledSize: new google.maps.Size(40, 40),
                anchor: new google.maps.Point(20, 20),
            };
        }

        addEndpoint(point, type) {
            const marker = new google.maps.Marker({
                position: { lat: point.lat, lng: point.lng },
                map: this.map,
                icon: this.endpointIcon(type),
                zIndex: type === 'start' ? 600 : 601,
                title: type === 'start' ? (this.cfg.i18n?.routeStart || 'Start') : (this.cfg.i18n?.routeEnd || 'End'),
            });
            this.layers.push(marker);
        }

        addEventMarker(ev) {
            const marker = new google.maps.Marker({
                position: { lat: ev.lat, lng: ev.lng },
                map: this.map,
                icon: {
                    path: google.maps.SymbolPath.CIRCLE,
                    fillColor: ev.type === 'stop' ? '#f97316' : '#ef4444',
                    fillOpacity: 0.95,
                    strokeColor: '#ffffff',
                    strokeWeight: 2,
                    scale: 7,
                },
                title: ev.title || ev.type,
                zIndex: 550,
            });
            this.layers.push(marker);
        }

        fitRoutes(smooth) {
            const bounds = new google.maps.LatLngBounds();
            let count = 0;
            this.layers.forEach((layer) => {
                if (layer.getPath) {
                    layer.getPath().forEach((ll) => { bounds.extend(ll); count++; });
                } else if (layer.getPosition) {
                    bounds.extend(layer.getPosition());
                    count++;
                }
            });
            if (count === 0) return;
            this.map.fitBounds(bounds, 48);
            if (smooth && this.map.panToBounds) {
                this.map.panToBounds(bounds, 48);
            }
        }
    }

    function init() {
        const cfg = global.GLOBAL_TRACKING_HISTORY_CONFIG;
        if (!cfg) return;
        const app = new GlobalTrackingHistory(cfg);
        app.boot();
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init);
    } else {
        init();
    }
})(typeof window !== 'undefined' ? window : globalThis);
