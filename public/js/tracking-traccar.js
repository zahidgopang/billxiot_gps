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
    const STOPPED_KEYS = new Set(['stopped', 'idle', 'parked', 'parking', 'ignition_off']);
    const OFFLINE_KEYS = new Set(['offline', 'stale', 'delayed', 'blocked']);

    function escHtml(s) {
        return String(s ?? '')
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/"/g, '&quot;');
    }

    function colorForPoint(point, stateColors) {
        const key = point?.status_key || 'offline';
        const normalized = key === 'parking' ? 'parked' : key;
        return point?.color || stateColors[key] || stateColors[normalized] || stateColors.offline || '#94a3b8';
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
        const bucket = Math.round((((heading || 0) % 360) + 360) % 360 / 3) * 3;
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

    function svgDataUrl(svg) {
        return 'data:image/svg+xml;charset=UTF-8,' + encodeURIComponent(svg);
    }

    function liveClusterIcon(count) {
        const g = global.google;
        if (!g?.maps) return null;
        const size = 44;
        const svg = `<svg xmlns="http://www.w3.org/2000/svg" width="${size}" height="${size}" viewBox="0 0 ${size} ${size}">
            <circle cx="22" cy="22" r="20" fill="#1976D2" stroke="#fff" stroke-width="3"/>
            <text x="22" y="27" text-anchor="middle" fill="#fff" font-family="system-ui,sans-serif" font-size="14" font-weight="700">${count}</text>
        </svg>`;
        return {
            url: svgDataUrl(svg),
            scaledSize: new g.maps.Size(size, size),
            anchor: new g.maps.Point(size / 2, size / 2),
        };
    }

    function applyMarkerIcon(marker, icon) {
        if (global.VehicleMarker?.applyMarkerIcon) {
            global.VehicleMarker.applyMarkerIcon(marker, icon);
            return;
        }
        if (!marker || !icon || typeof marker.setIcon !== 'function') return;
        marker.setIcon(icon);
    }

    function fmtTime(point) {
        if (!point) return '—';
        if (point.timestamp) return String(point.timestamp);
        if (point.recorded_at) return String(point.recorded_at).replace('T', ' ').slice(0, 19);
        return '—';
    }

    function formatDuration(sec) {
        sec = Math.max(0, Math.round(sec || 0));
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
        if (window.HistoryAnalytics?.analyze) {
            const stats = window.HistoryAnalytics.analyze(points || []);
            return {
                distance_km: Math.round(stats.dist * 10) / 10,
                move_seconds: Math.round(stats.movingSec),
                stop_seconds: Math.round(stats.stoppedSec),
                idle_seconds: Math.round(stats.idleSec),
                parking_seconds: Math.round(stats.parkingSec),
                top_speed: Math.round(stats.maxSpeed),
                avg_speed: stats.movingSec > 0
                    ? Math.round(stats.dist / (stats.movingSec / 3600))
                    : 0,
                stop_count: (stops || []).length,
                point_count: (points || []).length,
            };
        }

        let distKm = 0;
        let moveSec = 0;
        let idleSec = 0;
        let parkingSec = 0;
        let topSpeed = 0;
        let speedSum = 0;
        let speedCount = 0;

        for (let i = 1; i < (points || []).length; i++) {
            const a = points[i - 1];
            const b = points[i];
            if (a.lat != null && a.lng != null && b.lat != null && b.lng != null) {
                distKm += haversineKm(a.lat, a.lng, b.lat, b.lng);
            }
            const t0 = a.recorded_at ? new Date(a.recorded_at).getTime() : null;
            const t1 = b.recorded_at ? new Date(b.recorded_at).getTime() : null;
            if (!t0 || !t1 || t1 <= t0) continue;

            const dt = (t1 - t0) / 1000;
            const spd = parseFloat(b.speed || 0);
            const ignition = b.ignition === true || b.ignition === 1 || b.ignition === '1';
            if (spd > topSpeed) topSpeed = spd;

            if (ignition) {
                if (spd > 1) {
                    moveSec += dt;
                    speedSum += spd;
                    speedCount++;
                } else {
                    idleSec += dt;
                }
            } else if (spd > 1) {
                moveSec += dt;
                speedSum += spd;
                speedCount++;
            } else {
                parkingSec += dt;
            }
        }

        const stopSec = Math.max(
            (stops || []).reduce((s, x) => s + Math.max(0, x.durationSec || 0), 0),
            idleSec + parkingSec,
        );
        const avgSpeed = speedCount ? speedSum / speedCount : 0;

        return {
            distance_km: Math.round(distKm * 10) / 10,
            move_seconds: Math.round(Math.max(0, moveSec)),
            stop_seconds: Math.round(Math.max(0, stopSec)),
            idle_seconds: Math.round(Math.max(0, idleSec)),
            parking_seconds: Math.round(Math.max(0, parkingSec)),
            top_speed: Math.round(topSpeed),
            avg_speed: Math.round(avgSpeed),
            stop_count: (stops || []).length,
            point_count: (points || []).length,
        };
    }

    function normalizeHistoryStats(stats, points, stops) {
        if (!stats || typeof stats !== 'object') {
            return computeHistoryStats(points, stops);
        }

        const distance = parseFloat(stats.total_distance_km ?? stats.distance_km ?? 0);
        const maxSpeed = parseFloat(stats.max_speed_kmh ?? stats.top_speed ?? 0);
        const avgSpeed = parseFloat(stats.average_speed_kmh ?? stats.avg_speed ?? 0);
        return {
            distance_km: Math.round((Number.isFinite(distance) ? distance : 0) * 10) / 10,
            move_seconds: Math.max(0, parseInt(stats.moving_time_seconds ?? stats.move_seconds ?? 0, 10) || 0),
            stop_seconds: Math.max(0, parseInt(stats.stopped_time_seconds ?? stats.stop_seconds ?? 0, 10) || 0),
            idle_seconds: Math.max(0, parseInt(stats.idle_time_seconds ?? stats.idle_seconds ?? 0, 10) || 0),
            parking_seconds: Math.max(0, parseInt(stats.parking_time_seconds ?? stats.parking_seconds ?? 0, 10) || 0),
            offline_seconds: Math.max(0, parseInt(stats.offline_time_seconds ?? stats.offline_seconds ?? 0, 10) || 0),
            top_speed: Math.round(Number.isFinite(maxSpeed) ? maxSpeed : 0),
            avg_speed: Math.round(Number.isFinite(avgSpeed) ? avgSpeed : 0),
            stop_count: parseInt(stats.stop_count ?? (stops || []).length, 10) || 0,
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

    function distMeters(a, b) {
        if (!a || !b) return 0;
        return haversineKm(a.lat, a.lng, b.lat, b.lng) * 1000;
    }

    // Split a Date into local { date: 'YYYY-MM-DD', time: 'HH:MM' } for the history inputs.
    function splitLocal(d) {
        const p = (n) => String(n).padStart(2, '0');
        return {
            date: `${d.getFullYear()}-${p(d.getMonth() + 1)}-${p(d.getDate())}`,
            time: `${p(d.getHours())}:${p(d.getMinutes())}`,
        };
    }

    // Traccar "Show history" presets → concrete local from/to range.
    function computePresetRange(preset) {
        const now = new Date();
        const startOfDay = (d) => new Date(d.getFullYear(), d.getMonth(), d.getDate(), 0, 0, 0);
        const endOfDay = (d) => new Date(d.getFullYear(), d.getMonth(), d.getDate(), 23, 59, 0);
        const addDays = (d, n) => { const x = new Date(d); x.setDate(x.getDate() + n); return x; };
        // Week starts Monday.
        const startOfWeek = (d) => { const x = startOfDay(d); const day = (x.getDay() + 6) % 7; return addDays(x, -day); };

        let from;
        let to;
        switch (preset) {
            case 'last_hour': from = new Date(now.getTime() - 3600 * 1000); to = now; break;
            case 'today': from = startOfDay(now); to = now; break;
            case 'yesterday': { const y = addDays(now, -1); from = startOfDay(y); to = endOfDay(y); break; }
            case 'before_2': { const y = addDays(now, -2); from = startOfDay(y); to = endOfDay(y); break; }
            case 'before_3': { const y = addDays(now, -3); from = startOfDay(y); to = endOfDay(y); break; }
            case 'this_week': from = startOfWeek(now); to = now; break;
            case 'last_week': { const sw = startOfWeek(now); const lwStart = addDays(sw, -7); from = lwStart; to = endOfDay(addDays(sw, -1)); break; }
            case 'this_month': from = new Date(now.getFullYear(), now.getMonth(), 1, 0, 0, 0); to = now; break;
            case 'last_month': from = new Date(now.getFullYear(), now.getMonth() - 1, 1, 0, 0, 0); to = endOfDay(new Date(now.getFullYear(), now.getMonth(), 0)); break;
            default: return { from: null, to: null };
        }
        return { from: splitLocal(from), to: splitLocal(to) };
    }

    // Shortest-arc angular interpolation (degrees), so the arrow never spins the long way.
    function lerpHeading(from, to, t) {
        const delta = ((to - from + 540) % 360) - 180;
        return (from + delta * t + 360) % 360;
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
            this.mapZoom = 11;
            this.vehicles = new Map();
            this.visible = new Set();
            this.states = new Map();
            this.clusterMarkers = new Map();
            this.clusterIconCache = new Map();
            this.clusteredDeviceIds = new Set();
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
            this.iconBuilder = null;

            // Planned route trip overlay (fallback if RouteTripProgress fails).
            this._assignedRoutePolyline = null;
            /** @type {Map<number, google.maps.Polyline>} */
            this._vehicleRoutePolylines = new Map();
            this.routeTripKit = null;
            this._routeTripDeviceId = null;
            this._routeBoundsFitted = false;

            // vehicle marker popup
            this.vehiclePopup = null;
            this.ui = cfg.trackingUi || {};
            this.companyMapCard = cfg.companyMapCard || { enabled: false };
            this._companyCardOpen = true;
            this._driverCardOpen = true;
        }

        uiOn(flag) {
            return this.ui[flag] !== false && this.ui[flag] !== undefined ? !!this.ui[flag] : true;
        }

        /** Route-level "Show polyline on map" (admin → Routes edit). */
        routeAllowsPolyline(route) {
            return !!route && route.show_polyline !== false;
        }

        /** Strip route geometry from payloads when the user cannot view polylines. */
        sanitizeRouteTrip(raw) {
            if (!raw || this.uiOn('polyline')) {
                return raw;
            }
            const trip = raw.route_trip ?? raw;
            if (!trip?.route || typeof trip.route !== 'object') {
                return raw;
            }
            const polylineKeys = [
                'assigned_polyline', 'guided_polyline', 'polyline', 'admin_polyline',
                'actual_polyline', 'navigation_polyline', 'join_polyline',
                'dynamic_polyline', 'display_polyline', 'encoded_polyline',
            ];
            const route = { ...trip.route };
            polylineKeys.forEach((key) => { delete route[key]; });
            route.has_stored_polyline = false;
            route.is_road_polyline = false;
            const nextTrip = { ...trip, route };
            return raw.route_trip !== undefined ? { ...raw, route_trip: nextTrip } : nextTrip;
        }

        clearSelectedRouteOverlays() {
            this._assignedRoutePolyline?.setMap(null);
            this.routeTripKit?.clearPolylines?.();
            if (this.routeTripKit && typeof this.routeTripKit.clearPolylines !== 'function') {
                this.routeTripKit.clear();
            }
        }

        clearAllRouteMapOverlays() {
            this.clearSelectedRouteOverlays();
            this._vehicleRoutePolylines.forEach((line) => line.setMap(null));
            this._vehicleRoutePolylines.clear();
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
            (cfg.vehicles || []).forEach((v) => {
                const clean = { ...v };
                if (clean.route_trip) {
                    clean.route_trip = this.sanitizeRouteTrip(clean.route_trip);
                }
                this.vehicles.set(v.id, clean);
            });

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
                mapTypeControl: false,
                streetViewControl: false,
                fullscreenControl: false,
                zoomControl: false,
                gestureHandling: 'greedy',
            });
            this.mapZoom = this.map.getZoom() || 11;
            this.map.addListener('idle', () => {
                this.mapZoom = this.map.getZoom() || this.mapZoom || 11;
                this.renderLiveClusters();
            });
            this.legendEl = document.getElementById('tcLegend');
            this.trafficLayer = new google.maps.TrafficLayer();

            const VM = global.VehicleMarker;
            if (VM) {
                this.iconBuilder = VM.createIconBuilder({
                    googleMaps: google,
                    getIdentity: (p) => ({ title: p.title || p.name || '', plate: p.plate || '' }),
                    getState: (p) => p.status_key || 'offline',
                    getColor: (state) => this.stateColors[state] || this.stateColors.offline || '#94a3b8',
                    getVehicleType: (p) => p.vehicle_type || 'car',
                    getMarkerStyle: (p) => {
                        if (VM.resolveCustomIconUrl(p)) return 'body';
                        return VM.resolveMarkerStyle(p);
                    },
                    getMarkerSizeScale: (p) => VM.resolveMarkerSizeScale(p),
                    getCustomIconUrl: (p) => VM.resolveCustomIconUrl(p),
                    getRotationEnabled: (p) => VM.resolveRotationEnabled(p),
                    shouldShowDirection: (_, state) => MOVING_KEYS.has(state),
                });
            }

            if (global.VehicleMapPopup) {
                const i = this.cfg.i18n || {};
                this.vehiclePopup = new global.VehicleMapPopup({
                    getMap: () => this.map,
                    googleMaps: google,
                    stateColors: this.stateColors,
                    commandsSendUrl: this.ui.hub?.commands ? this.cfg.commandsSendUrl : null,
                    commandTypes: this.cfg.commandTypes,
                    csrfToken: this.cfg.csrfToken,
                    i18n: {
                        dash: '—',
                        plate: i.lblPlate || 'Plate',
                        odometer: i.lblOdometer || 'Odometer',
                        status: i.lblStatus || 'Status',
                        altitude: i.lblAltitude || 'Altitude',
                        angle: i.lblAngle || 'Angle',
                        position: i.lblPosition || 'Position',
                        engine: i.lblEngine || 'Engine',
                        statusFor: i.lblStatusDuration || 'for',
                        ignitionOn: i.ignitionOn || 'On',
                        ignitionOff: i.ignitionOff || 'Off',
                        sendCommand: i.cmdSend || 'Send',
                        cmdSent: i.cmdSent || 'Command queued',
                        cmdFailed: i.loadFailed || 'Failed',
                        close: i.hide || 'Close',
                    },
                    onSendCommand: (deviceId, type, _data, btn) => {
                        this.sendCommandFromPopup(deviceId, type, btn);
                    },
                });
                this.map.addListener('click', () => this.vehiclePopup?.close());
            }

            if (document.querySelector('.tc-tab')) {
                this.bindTabs();
            }
            if (document.getElementById('tcVehicleList')) {
                this.bindObjects();
            }
            if (document.querySelector('[data-tab-body="history"]')) {
                this.bindHistory();
            }
            if (document.querySelector('[data-tab-body="events"]')) {
                this.bindEventsTab();
            }
            if (document.querySelector('[data-tab-body="places"]')) {
                this.bindPlacesTab();
            }
            this.bindMapControls();
            if (document.getElementById('tcLayerMenu')) {
                this.bindMapLayers();
            }
            if (document.getElementById('tcFooter')) {
                this.bindFooterTabs();
            }
            this.bindModules();
            if (document.getElementById('tcPanelToggle')) {
                this.bindPanelToggle();
            }
            if (document.getElementById('tcTopbarToggle')) {
                this.bindTopbarToggle();
            }
            this.fitAppHeight();
            if (this.ui.alert_controls || this.ui.sidebar_tabs?.events) {
                this.initAlerts();
            }
            this.setDefaultDates();

            // Show all vehicles by default (Traccar behavior).
            this.vehicles.forEach((_, id) => this.visible.add(id));
            if (document.getElementById('tcVehicleList')) {
                this.renderList();
                this.updateCounts();
            }
            this.visible.forEach((id) => { this.ensureMarker(id); this.subscribePusher(id); });
            this.renderLiveClusters();

            // "Follow (new window)" deep-link: ?follow=<deviceId>
            const followId = parseInt(new URLSearchParams(global.location.search).get('follow'), 10);
            if (followId && this.vehicles.has(followId)) {
                this.setFollow(followId, true);
            }
            if (this.uiOn('polyline') || this.uiOn('route_progress')) {
                this.pickRouteTripVehicle();
            } else {
                this.clearAllRouteMapOverlays();
            }
            this.syncVisibleVehicleRoutePolylines();
            if (!this._routeTripDeviceId) {
                this.fitAll();
                this.renderLiveClusters();
            }
            this.initCompanyMapCard();
            this.initDriverMapCard();
            this.startPolling();
        }

        initCompanyMapCard() {
            const card = document.getElementById('tcCompanyMapCard');
            if (!card || !this.companyMapCard?.enabled) {
                card?.setAttribute('hidden', '');
                return;
            }
            card.removeAttribute('hidden');
            card.classList.toggle('is-open', this._companyCardOpen);
            const btn = document.getElementById('tcCompanyMapCardToggle');
            btn?.addEventListener('click', (e) => {
                e.stopPropagation();
                this.toggleCompanyMapCard();
            });
            this.syncCompanyMapCard();
        }

        toggleCompanyMapCard() {
            const card = document.getElementById('tcCompanyMapCard');
            if (!card) return;
            this._companyCardOpen = !this._companyCardOpen;
            card.classList.toggle('is-open', this._companyCardOpen);
            const btn = document.getElementById('tcCompanyMapCardToggle');
            const icon = btn?.querySelector('i');
            if (btn) {
                btn.setAttribute('aria-expanded', this._companyCardOpen ? 'true' : 'false');
                btn.setAttribute('aria-label', this._companyCardOpen
                    ? (this.cfg.i18n?.companyMapCollapse || 'Hide details')
                    : (this.cfg.i18n?.companyMapExpand || 'Show details'));
            }
            if (icon) icon.className = this._companyCardOpen ? 'fas fa-chevron-down' : 'fas fa-chevron-up';
        }

        resolveActiveVehicleId() {
            if (this._panelDeviceId != null && this.visible.has(this._panelDeviceId)) {
                return this._panelDeviceId;
            }
            if (this.followId != null && this.visible.has(this.followId)) {
                return this.followId;
            }
            if (this._routeTripDeviceId != null && this.visible.has(this._routeTripDeviceId)) {
                return this._routeTripDeviceId;
            }
            const first = [...this.visible][0];
            return first != null ? first : null;
        }

        syncCompanyMapCard() {
            const card = document.getElementById('tcCompanyMapCard');
            if (!card || !this.companyMapCard?.enabled) {
                card?.setAttribute('hidden', '');
                return;
            }
            card.removeAttribute('hidden');
            const data = this.companyMapCard;
            ['company_name', 'company_number', 'operation_card', 'support'].forEach((key) => {
                const val = (data[key] || '').trim();
                const cell = card.querySelector(`[data-company-value="${key}"]`);
                const row = card.querySelector(`[data-company-row="${key}"]`);
                if (cell) cell.textContent = val || '—';
                if (row) row.hidden = val === '';
            });
            const id = this.resolveActiveVehicleId();
            const vehicle = id != null ? this.vehicles.get(id) : null;
            const busNameCell = card.querySelector('[data-company-value="bus_name"]');
            const busPlateCell = card.querySelector('[data-company-value="bus_plate"]');
            if (busNameCell) {
                busNameCell.textContent = vehicle?.title?.trim() || vehicle?.plate?.trim() || '—';
            }
            if (busPlateCell) {
                busPlateCell.textContent = vehicle?.plate?.trim() || '—';
            }
        }

        initDriverMapCard() {
            const card = document.getElementById('tcDriverMapCard');
            if (!card || !this.uiOn('driver')) {
                card?.setAttribute('hidden', '');
                return;
            }
            card.classList.toggle('is-open', this._driverCardOpen);
            const btn = document.getElementById('tcDriverMapCardToggle');
            btn?.addEventListener('click', (e) => {
                e.stopPropagation();
                this.toggleDriverMapCard();
            });
            this.syncDriverMapCard();
        }

        toggleDriverMapCard() {
            const card = document.getElementById('tcDriverMapCard');
            if (!card) return;
            this._driverCardOpen = !this._driverCardOpen;
            card.classList.toggle('is-open', this._driverCardOpen);
            const btn = document.getElementById('tcDriverMapCardToggle');
            const icon = btn?.querySelector('i');
            if (btn) {
                btn.setAttribute('aria-expanded', this._driverCardOpen ? 'true' : 'false');
                btn.setAttribute('aria-label', this._driverCardOpen
                    ? (this.cfg.i18n?.driverMapCollapse || 'Hide details')
                    : (this.cfg.i18n?.driverMapExpand || 'Show details'));
            }
            if (icon) icon.className = this._driverCardOpen ? 'fas fa-chevron-down' : 'fas fa-chevron-up';
        }

        syncDriverMapCard() {
            const card = document.getElementById('tcDriverMapCard');
            if (!card || !this.uiOn('driver')) {
                card?.setAttribute('hidden', '');
                return;
            }
            const id = this.resolveActiveVehicleId();
            const driver = id != null ? this.vehicles.get(id)?.driver : null;
            const hasDriver = driver && (
                (driver.name && driver.name !== '—')
                || driver.phone
                || driver.email
            );
            const layoutEl = document.getElementById('tcDriverLayout');
            const emptyEl = document.getElementById('tcDriverEmpty');
            card.removeAttribute('hidden');
            if (!hasDriver) {
                if (layoutEl) layoutEl.hidden = true;
                if (emptyEl) emptyEl.hidden = false;
                return;
            }
            if (layoutEl) layoutEl.hidden = false;
            if (emptyEl) emptyEl.hidden = true;

            const nameEl = document.getElementById('tcDriverName');
            if (nameEl) nameEl.textContent = driver.name || '—';

            const photoEl = document.getElementById('tcDriverPhoto');
            const photoDefault = document.getElementById('tcDriverPhotoDefault');
            const photo = (driver.photo || '').trim();
            if (photoEl && photoDefault) {
                if (photo) {
                    photoEl.src = photo;
                    photoEl.alt = driver.name || '';
                    photoEl.hidden = false;
                    photoDefault.hidden = true;
                } else {
                    photoEl.removeAttribute('src');
                    photoEl.hidden = true;
                    photoDefault.hidden = false;
                }
            }

            const phoneRow = document.getElementById('tcDriverPhoneRow');
            const phoneEl = document.getElementById('tcDriverPhone');
            const phone = (driver.phone || '').trim();
            if (phoneRow && phoneEl) {
                if (phone) {
                    phoneRow.hidden = false;
                    phoneEl.textContent = phone;
                    phoneEl.href = driver.phone_tel ? `tel:${driver.phone_tel}` : '#';
                    phoneEl.title = this.cfg.i18n?.callDriver || 'Call driver';
                } else {
                    phoneRow.hidden = true;
                }
            }

            const emailRow = document.getElementById('tcDriverEmailRow');
            const emailEl = document.getElementById('tcDriverEmail');
            const email = (driver.email || '').trim();
            if (emailRow && emailEl) {
                if (email) {
                    emailRow.hidden = false;
                    emailEl.textContent = email;
                    emailEl.href = `mailto:${email}`;
                } else {
                    emailRow.hidden = true;
                }
            }
        }

        /* ---------- Tabs ---------- */
        bindTabs() {
            document.querySelectorAll('.tc-tab').forEach((btn) => {
                btn.addEventListener('click', () => this.switchTab(btn.dataset.tab));
            });
        }

        switchTab(tab) {
            if (!document.querySelector(`[data-tab="${tab}"]`)) return;
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

            if (tab === 'events') { this.clearAlertBadge(); if (!this._eventsLoaded) this.loadEvents(); }
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
                    this.pickRouteTripVehicle();
                } else {
                    [...this.visible].forEach((id) => { this.visible.delete(id); this.teardownVehicle(id); });
                    this.followId = null;
                    this.updateFollowBtn();
                    this.clearRouteTripSelection();
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
            this.closeRowMenu();
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
                    <button type="button" class="tc-row-menu-btn" data-menu="${v.id}" title="${escHtml(i18n.menuActions || 'Actions')}" aria-label="${escHtml(i18n.menuActions || 'Actions')}"><i class="fas fa-ellipsis-v"></i></button>
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
            listEl.querySelectorAll('.tc-row-menu-btn').forEach((btn) => {
                btn.addEventListener('click', (e) => {
                    e.stopPropagation();
                    this.openRowMenu(parseInt(btn.dataset.menu, 10), btn);
                });
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
            const i = this.cfg.i18n || {};
            const kmh = i.kmhUnit || 'km/h';
            const chips = [];
            const spd = v.speed != null ? Math.round(parseFloat(v.speed) || 0) : null;
            chips.push(`<span class="tc-meta-chip" title="${escHtml(i.lblSpeed || 'Speed')}"><i class="fas fa-gauge-high"></i>${spd != null ? spd + ' ' + escHtml(kmh) : '—'}</span>`);
            chips.push(`<span class="tc-meta-chip" title="${escHtml(i.lblTimePosition || 'Time')}"><i class="fas fa-clock"></i>${escHtml(v.recorded_at_human || '—')}</span>`);
            if (v.ignition != null) {
                chips.push(`<span class="tc-meta-chip ${v.ignition ? 'tc-ign-on' : 'tc-ign-off'}" title="${escHtml(i.lblIgnition || 'Ignition')}"><i class="fas fa-key"></i>${v.ignition ? (i.ignitionOn || 'ON') : (i.ignitionOff || 'OFF')}</span>`);
            }
            const sat = v.satellites != null ? parseInt(v.satellites, 10) : null;
            if (sat != null && !Number.isNaN(sat)) {
                const cls = sat >= 8 ? 'tc-sig-strong' : sat >= 4 ? 'tc-sig-mid' : 'tc-sig-weak';
                chips.push(`<span class="tc-meta-chip ${cls}" title="${escHtml(i.satellitesLabel || 'Satellites')}"><i class="fas fa-satellite-dish"></i>${sat}</span>`);
            } else if (v.gsm_signal != null) {
                const g = parseInt(v.gsm_signal, 10);
                if (!Number.isNaN(g)) {
                    const cls = g >= 60 ? 'tc-sig-strong' : g >= 30 ? 'tc-sig-mid' : 'tc-sig-weak';
                    chips.push(`<span class="tc-meta-chip ${cls}" title="${escHtml(i.gpsSignal || 'Signal')}"><i class="fas fa-signal"></i>${g}%</span>`);
                }
            }
            if (v.battery_level != null) {
                const b = parseInt(v.battery_level, 10);
                if (!Number.isNaN(b)) {
                    const cls = b >= 50 ? 'tc-batt-ok' : b >= 20 ? 'tc-batt-mid' : 'tc-batt-low';
                    const icon = b >= 75 ? 'fa-battery-full' : b >= 50 ? 'fa-battery-three-quarters' : b >= 25 ? 'fa-battery-half' : b > 10 ? 'fa-battery-quarter' : 'fa-battery-empty';
                    chips.push(`<span class="tc-meta-chip ${cls}" title="${escHtml(i.lblBattery || 'Battery')}"><i class="fas ${icon}"></i>${b}%</span>`);
                }
            }
            return `<span class="tc-meta-chips">${chips.join('')}</span>`;
        }

        updateCounts() {
            const counts = { all: this.vehicles.size, moving: 0, stopped: 0, offline: 0 };
            this.vehicles.forEach((v) => { counts[filterBucket(v.status_key)] = (counts[filterBucket(v.status_key)] || 0) + 1; });
            document.querySelectorAll('#tcChips [data-count]').forEach((el) => {
                el.textContent = counts[el.dataset.count] ?? 0;
            });
            const allLabel = document.getElementById('tcAllCount');
            if (allLabel) allLabel.textContent = `(${this.vehicles.size})`;
            const badge = document.getElementById('tcToggleBadge');
            if (badge) {
                const n = this.vehicles.size;
                badge.textContent = n > 99 ? '99+' : String(n);
                badge.hidden = n === 0;
            }
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
                if (Number(this._routeTripDeviceId) === Number(id)) {
                    if (this.followId) {
                        this.selectRouteTripVehicle(this.followId);
                    } else if (this._panelDeviceId) {
                        this.selectRouteTripVehicle(this._panelDeviceId);
                    } else {
                        this.clearRouteTripSelection();
                    }
                }
            } else {
                this.visible.add(id);
                this.ensureMarker(id);
                this.subscribePusher(id);
                this.selectRouteTripVehicle(id);
            }
            this.renderList();
            this.syncVisibleVehicleRoutePolylines();
            this.renderLiveClusters();
            this.startPolling();
        }

        locateVehicle(id) {
            const v = this.vehicles.get(id);
            if (!this.visible.has(id)) this.toggleVisible(id);
            this.closeNavigationOnVehicleSelect();
            this._routeBoundsFitted = false;
            this.selectRouteTripVehicle(id);
            this.renderLiveClusters();
            const rt = this.vehicles.get(id)?.route_trip;
            if (rt?.route) {
                this.applyRouteTrip(id, rt);
            } else if (v?.lat != null && v?.lng != null) {
                this.map.panTo({ lat: v.lat, lng: v.lng });
                if (this.map.getZoom() < 14) this.map.setZoom(15);
            }
            this.openDevicePanel(id);
        }

        openVehiclePopup(id) {
            const v = this.vehicles.get(id);
            const st = this.vehicleState(id);
            if (!v || v.lat == null || v.lng == null || !this.vehiclePopup) return;
            if (!this.visible.has(id)) {
                this.visible.add(id);
                this.ensureMarker(id);
                this.subscribePusher(id);
                this.startPolling();
            }
            this.selectRouteTripVehicle(id);
            this.renderLiveClusters();
            this.closeNavigationOnVehicleSelect();
            this.vehiclePopup.open({ ...v, id }, st.marker);
        }

        async sendCommandFromPopup(deviceId, type, btn) {
            if (!this.cfg.commandsSendUrl || !type) return;
            btn?.setAttribute('disabled', 'disabled');
            try {
                const res = await fetch(this.cfg.commandsSendUrl, {
                    method: 'POST',
                    credentials: 'same-origin',
                    headers: {
                        'Content-Type': 'application/json',
                        Accept: 'application/json',
                        'X-CSRF-TOKEN': this.cfg.csrfToken || '',
                    },
                    body: JSON.stringify({ device_id: deviceId, type, data: '' }),
                });
                const out = await res.json().catch(() => ({}));
                const ok = res.ok && out.success;
                const msg = out.message || (ok ? (this.cfg.i18n?.cmdSent || 'Command queued') : (this.cfg.i18n?.loadFailed || 'Failed'));
                if (global.Swal) {
                    global.Swal.fire({ icon: ok ? 'success' : 'error', title: msg, timer: ok ? 2200 : undefined, showConfirmButton: !ok });
                } else {
                    alert(msg);
                }
            } catch (err) {
                if (global.Swal) global.Swal.fire({ icon: 'error', title: this.cfg.i18n?.loadFailed || 'Failed' });
                else alert(this.cfg.i18n?.loadFailed || 'Failed');
            } finally {
                btn?.removeAttribute('disabled');
            }
        }

        // Footprints column: one-shot zoom to vehicle (no continuous map follow).
        setFollow(id, on) {
            if (on) {
                if (!this.visible.has(id)) {
                    this.visible.add(id);
                    this.ensureMarker(id);
                    this.subscribePusher(id);
                    this.startPolling();
                }
                this.followId = id;
                this.closeNavigationOnVehicleSelect();
                this.selectRouteTripVehicle(id);
                const v = this.vehicles.get(id);
                if (v?.lat != null && v?.lng != null) {
                    this.map.panTo({ lat: v.lat, lng: v.lng });
                    this.map.setZoom(17);
                }
            } else if (this.followId === id) {
                this.followId = null;
                if (this._panelDeviceId) {
                    this.selectRouteTripVehicle(this._panelDeviceId);
                } else {
                    this.clearRouteTripSelection();
                }
            }
            this.updateFollowBtn();
            this.renderList();
            this.renderLiveClusters();
        }

        /* ---------- Per-vehicle action menu (Traccar-style kebab) ---------- */
        closeRowMenu() {
            if (this._rowMenu) { this._rowMenu.remove(); this._rowMenu = null; }
            if (this._rowMenuCleanup) { this._rowMenuCleanup(); this._rowMenuCleanup = null; }
        }

        openRowMenu(id, anchorEl) {
            if (this._rowMenu && this._rowMenuId === id) { this.closeRowMenu(); return; }
            this.closeRowMenu();
            this._rowMenuId = id;

            const i = this.cfg.i18n || {};
            const v = this.vehicles.get(id);
            const menu = document.createElement('div');
            menu.className = 'tc-veh-menu';
            menu.setAttribute('role', 'menu');

            const item = (icon, label, opts = {}) => {
                const caret = opts.caret ? '<i class="fas fa-chevron-right tc-mi-caret"></i>' : '';
                return `<button type="button" class="tc-veh-menu-item" data-act="${opts.act || ''}"><i class="fas ${icon} tc-mi-icon"></i><span>${escHtml(label)}</span>${caret}</button>`;
            };

            const parts = [
                item('fa-clock', i.menuShowHistory || 'Show history', { act: 'history', caret: true }),
                item('fa-shoe-prints', i.menuFollow || 'Follow', { act: 'follow' }),
                item('fa-up-right-from-square', i.menuFollowNew || 'Follow (new window)', { act: 'follow-new' }),
                item('fa-street-view', i.menuStreetView || 'Street View (new window)', { act: 'street' }),
                item('fa-share-nodes', i.menuShare || 'Share position', { act: 'share' }),
            ];
            if (this.cfg.commandsSendUrl) parts.push(item('fa-paper-plane', i.menuSendCommand || 'Send command', { act: 'command' }));
            if (this.cfg.deviceEditUrl) {
                parts.push('<div class="tc-veh-menu-sep"></div>');
                parts.push(item('fa-pen', i.menuEdit || 'Edit', { act: 'edit' }));
            }
            menu.innerHTML = parts.join('');
            document.body.appendChild(menu);
            this._rowMenu = menu;

            this.positionMenu(menu, anchorEl);

            menu.querySelectorAll('.tc-veh-menu-item').forEach((btn) => {
                const act = btn.dataset.act;
                if (act === 'history') {
                    btn.addEventListener('click', (e) => { e.stopPropagation(); this.openHistorySubmenu(id, btn); });
                } else {
                    btn.addEventListener('click', (e) => { e.stopPropagation(); this.runRowAction(act, id, v); this.closeRowMenu(); });
                }
            });

            const onDocClick = (e) => { if (!menu.contains(e.target) && e.target !== anchorEl) this.closeRowMenu(); };
            const onKey = (e) => { if (e.key === 'Escape') this.closeRowMenu(); };
            const onScroll = () => this.closeRowMenu();
            setTimeout(() => document.addEventListener('click', onDocClick), 0);
            document.addEventListener('keydown', onKey);
            global.addEventListener('resize', onScroll);
            document.getElementById('tcVehicleList')?.addEventListener('scroll', onScroll, { passive: true });
            this._rowMenuCleanup = () => {
                document.removeEventListener('click', onDocClick);
                document.removeEventListener('keydown', onKey);
                global.removeEventListener('resize', onScroll);
                document.getElementById('tcVehicleList')?.removeEventListener('scroll', onScroll);
            };
        }

        positionMenu(menu, anchorEl) {
            const r = anchorEl.getBoundingClientRect();
            const mw = menu.offsetWidth || 220;
            const mh = menu.offsetHeight || 280;
            const rtl = document.documentElement.getAttribute('dir') === 'rtl';
            let left = rtl ? r.left - mw + r.width : r.right + 6;
            if (left + mw > window.innerWidth - 8) left = r.left - mw - 6;
            if (left < 8) left = 8;
            let top = r.top;
            if (top + mh > window.innerHeight - 8) top = Math.max(8, window.innerHeight - mh - 8);
            menu.style.left = `${Math.round(left)}px`;
            menu.style.top = `${Math.round(top)}px`;
        }

        openHistorySubmenu(id, anchorItem) {
            // Replace the main menu content with the preset list (keeps it simple + mobile friendly).
            const i = this.cfg.i18n || {};
            const menu = this._rowMenu;
            if (!menu) return;
            const presets = [
                ['last_hour', i.rangeLastHour || 'Last hour'],
                ['today', i.rangeToday || 'Today'],
                ['yesterday', i.rangeYesterday || 'Yesterday'],
                ['before_2', i.rangeBefore2 || 'Before 2 days'],
                ['before_3', i.rangeBefore3 || 'Before 3 days'],
                ['this_week', i.rangeThisWeek || 'This week'],
                ['last_week', i.rangeLastWeek || 'Last week'],
                ['this_month', i.rangeThisMonth || 'This month'],
                ['last_month', i.rangeLastMonth || 'Last month'],
            ];
            menu.classList.add('tc-veh-submenu');
            menu.innerHTML = `<button type="button" class="tc-veh-menu-item" data-back="1"><i class="fas fa-chevron-left tc-mi-icon"></i><span>${escHtml(i.menuShowHistory || 'Show history')}</span></button>
                <div class="tc-veh-menu-sep"></div>`
                + presets.map(([k, label]) => `<button type="button" class="tc-veh-menu-item" data-range="${k}"><i class="fas fa-calendar-day tc-mi-icon"></i><span>${escHtml(label)}</span></button>`).join('');

            menu.querySelector('[data-back]')?.addEventListener('click', (e) => { e.stopPropagation(); this.closeRowMenu(); this.openRowMenu(id, document.querySelector(`[data-menu="${id}"]`)); });
            menu.querySelectorAll('[data-range]').forEach((btn) => {
                btn.addEventListener('click', (e) => {
                    e.stopPropagation();
                    this.showHistoryPreset(id, btn.dataset.range);
                    this.closeRowMenu();
                });
            });
        }

        deviceEditUrlFor(id) {
            if (!this.cfg.deviceEditUrl || id == null || id === '') return null;
            return this.cfg.deviceEditUrl.replace('__DEVICE_ID__', encodeURIComponent(String(id)));
        }

        runRowAction(act, id, v) {
            switch (act) {
                case 'follow':
                    this.setFollow(id, true);
                    break;
                case 'follow-new':
                    if (this.cfg.liveUrl) global.open(`${this.cfg.liveUrl}?follow=${encodeURIComponent(id)}`, '_blank');
                    break;
                case 'street':
                    this.openStreetView(v);
                    break;
                case 'share':
                    this.sharePosition(v);
                    break;
                case 'command':
                    this.locateVehicle(id);
                    setTimeout(() => document.getElementById('tcCmdType')?.focus(), 400);
                    break;
                case 'edit': {
                    const url = this.deviceEditUrlFor(id);
                    if (url) global.open(url, '_blank');
                    break;
                }
            }
        }

        openStreetView(v) {
            if (!v || v.lat == null || v.lng == null) { this.toast(this.cfg.i18n?.noPosition || 'No position'); return; }
            global.open(`https://www.google.com/maps?q=&layer=c&cbll=${v.lat},${v.lng}&cbp=11,0,0,0,0`, '_blank');
        }

        async sharePosition(v) {
            const i = this.cfg.i18n || {};
            if (!v || v.lat == null || v.lng == null) { this.toast(i.noPosition || 'No position'); return; }
            const url = `https://www.google.com/maps?q=${v.lat},${v.lng}`;
            const title = `${v.title || v.plate || ('#' + v.id)}`;
            try {
                if (navigator.share) {
                    await navigator.share({ title, text: title, url });
                    return;
                }
            } catch (_) { /* user cancelled or unsupported */ }
            try {
                await navigator.clipboard.writeText(url);
                this.toast(i.shareCopied || 'Position link copied');
            } catch (_) {
                global.prompt(i.sharePositionTitle || 'Share position', url);
            }
        }

        toast(message, type) {
            const icon = ['success', 'error', 'warning', 'info', 'question'].includes(type) ? type : 'success';
            if (global.Swal) {
                const timer = (icon === 'error' || icon === 'warning') ? 4000 : 2200;
                global.Swal.fire({ toast: true, position: 'top-end', timer, showConfirmButton: false, icon, title: message });
            } else {
                global.alert(message);
            }
        }

        showHistoryPreset(id, preset) {
            const { from, to } = computePresetRange(preset);
            if (!from) return;
            this.switchTab('history');
            const sel = document.getElementById('tcHistVehicle');
            if (sel) {
                sel.value = String(id);
                const $ = global.jQuery;
                if ($ && $(sel).hasClass('select2-hidden-accessible')) $(sel).trigger('change');
            }
            const setVal = (elId, val) => { const el = document.getElementById(elId); if (el) el.value = val; };
            setVal('tcHistDateFrom', from.date);
            setVal('tcHistTimeFrom', from.time);
            setVal('tcHistDateTo', to.date);
            setVal('tcHistTimeTo', to.time);
            this.loadHistory();
        }

        setVehicleMarkerIcon(st, v, heading) {
            const VM = global.VehicleMarker;
            const color = colorForPoint(v, this.stateColors);
            const h = heading != null ? heading : parseFloat(v.heading || 0);
            const point = { ...v, heading: h };
            const hasCustom = VM?.resolveCustomIconUrl?.(point);
            st.marker.setLabel(null);
            const icon = this.iconBuilder?.iconFor(point);
            applyMarkerIcon(st.marker, icon || arrowIcon(color, h));
        }

        activeClusterBreakId() {
            const candidates = [this._panelDeviceId, this.followId, this._routeTripDeviceId];
            for (const raw of candidates) {
                const id = raw == null ? null : Number(raw);
                if (id != null && this.visible.has(id)) return id;
            }
            return null;
        }

        livePositionMap(skipId = null) {
            const positions = {};
            this.visible.forEach((id) => {
                if (skipId != null && Number(id) === Number(skipId)) return;
                const v = this.vehicles.get(id);
                if (!hasGeo(v?.lat, v?.lng)) return;
                positions[id] = { lat: parseFloat(v.lat), lng: parseFloat(v.lng) };
            });
            return positions;
        }

        clearLiveClusters() {
            this.clusterMarkers.forEach((marker) => marker.setMap(null));
            this.clusterMarkers.clear();
            this.clusteredDeviceIds.clear();
        }

        showAllLiveMarkers() {
            if (!this.map || this.historyActive) return;
            this.visible.forEach((id) => {
                const st = this.states.get(id);
                if (!st?.marker || !st.lastPoint) return;
                st.marker.setMap(this.map);
                st.trailPolylines.forEach((line) => line.setMap(this.map));
            });
        }

        renderLiveClusters() {
            if (!this.map) return;
            this.clearLiveClusters();

            if (this.historyActive || !global.FleetMapCluster) {
                this.showAllLiveMarkers();
                return;
            }

            const breakId = this.activeClusterBreakId();
            const positions = this.livePositionMap();
            const clusterPositions = this.livePositionMap(breakId);
            const items = global.FleetMapCluster.group(clusterPositions, this.mapZoom || this.map.getZoom() || 11);
            const clusteredIds = new Set();

            items.forEach((item) => {
                if (!item.isCluster) return;
                item.memberIds.forEach((id) => clusteredIds.add(Number(id)));
                const count = item.count || item.memberIds.length;
                let icon = this.clusterIconCache.get(count);
                if (!icon) {
                    icon = liveClusterIcon(count);
                    this.clusterIconCache.set(count, icon);
                }
                const marker = new google.maps.Marker({
                    map: this.map,
                    position: item.position,
                    icon: icon || undefined,
                    zIndex: 900,
                    title: `${count} vehicles`,
                });
                marker.addListener('click', () => {
                    const bounds = global.FleetMapCluster.boundsFor(item, positions);
                    if (bounds) {
                        this.map.fitBounds(new google.maps.LatLngBounds(
                            { lat: bounds.minLat, lng: bounds.minLng },
                            { lat: bounds.maxLat, lng: bounds.maxLng },
                        ), 64);
                    } else {
                        this.map.setCenter(item.position);
                        this.map.setZoom(global.FleetMapCluster.expandZoom(this.mapZoom || this.map.getZoom() || 11));
                    }
                });
                this.clusterMarkers.set(global.FleetMapCluster.stableMarkerId(item), marker);
            });

            this.visible.forEach((id) => {
                const st = this.states.get(id);
                const hasPosition = !!positions[id];
                const clustered = clusteredIds.has(Number(id));
                const shouldShow = hasPosition && (!clustered || Number(id) === Number(breakId));
                if (st?.marker) st.marker.setMap(shouldShow ? this.map : null);
                st?.trailPolylines?.forEach((line) => line.setMap(shouldShow ? this.map : null));
            });
            this.clusteredDeviceIds = clusteredIds;
        }

        ensureMarker(id) {
            const v = this.vehicles.get(id);
            const st = this.vehicleState(id);
            if (!this.map || !v || st.marker) return;
            const pos = v.lat != null && v.lng != null ? { lat: v.lat, lng: v.lng } : null;
            st.marker = new google.maps.Marker({
                map: pos && !this.historyActive ? this.map : null,
                position: pos || DEFAULT_CENTER,
                title: this.labelFor(v),
                zIndex: 500 + id,
                optimized: false,
            });
            this.setVehicleMarkerIcon(st, v, v.heading || 0);
            st.marker.addListener('click', () => this.openVehiclePopup(id));
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
            const routeLine = this._vehicleRoutePolylines.get(id);
            if (routeLine) {
                routeLine.setMap(null);
                this._vehicleRoutePolylines.delete(id);
            }
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
            if (merged.route_trip) {
                merged.route_trip = this.sanitizeRouteTrip(merged.route_trip);
            }
            // Live payload carries the label as `status` (not `status_label`) and a
            // fresh `color` / `status_key`. Normalize so the list badge, marker icon
            // and footer all read the up-to-date status text — not the stale initial one.
            const liveLabel = point.status ?? point.status_label;
            if (liveLabel != null) merged.status_label = liveLabel;
            if (point.color != null) merged.color = point.color;
            this.vehicles.set(id, merged);

            const statusText = merged.status_label || merged.status || merged.status_key || '—';
            const metaEl = document.querySelector(`[data-meta="${id}"]`);
            if (metaEl) metaEl.innerHTML = this.metaHtml(merged);
            const liveColor = colorForPoint(merged, this.stateColors);
            const iconEl = document.querySelector(`[data-veh-icon="${id}"]`);
            if (iconEl) {
                iconEl.style.color = liveColor;
                iconEl.title = statusText;
                if (merged.icon) iconEl.innerHTML = `<i class="fas ${merged.icon}"></i>`;
            }
            const statusEl = document.querySelector(`[data-status="${id}"]`);
            if (statusEl) {
                statusEl.style.setProperty('--st', liveColor);
                statusEl.textContent = statusText;
            }
            this.updateCounts();
            this.updatePanelLive(merged);
            if (this.vehiclePopup?.isOpenFor(id)) {
                this.vehiclePopup.update({ ...merged, id });
            }
            if (this._routeTripDeviceId != null
                && Number(this._routeTripDeviceId) === Number(id)
                && merged.route_trip) {
                this.applyRouteTrip(id, merged.route_trip);
            }
            this.syncCompanyMapCard();
            this.syncDriverMapCard();

            const prev = st.lastPoint;
            const key = merged.status_key || 'offline';
            if (!MOVING_KEYS.has(key)) this.clearTrail(st);

            if (this.historyActive) { st.lastPoint = merged; return; }

            st.marker.setTitle(this.labelFor(merged));

            const moving = MOVING_KEYS.has(key);
            const spd = Math.max(0, parseFloat(merged.speed) || 0);
            const isDup = prev && samePosition(prev, merged);

            // Stale duplicate fix for a moving vehicle: the GPS unit reports slower
            // than we poll, so the server keeps returning the same coordinates.
            // Keep dead-reckoning forward instead of freezing, so the marker glides
            // continuously along the road until the next distinct fix arrives.
            if (isDup && moving && spd > 1) {
                this.continueCruise(id, merged);
                this.renderLiveClusters();
                return;
            }

            // First fix, or a duplicate while stopped/idle: snap into place and hold.
            if (!prev || isDup) {
                st.dupSince = null;
                let h = parseFloat(merged.heading);
                // Keep the last heading when stopped (noisy GPS heading at rest).
                if (!Number.isFinite(h) || (!moving && spd < 3)) {
                    h = st.renderHeading != null ? st.renderHeading : 0;
                }
                st.marker.setMap(this.map);
                st.marker.setPosition({ lat: merged.lat, lng: merged.lng });
                this.setVehicleMarkerIcon(st, merged, h);
                st.renderPos = { lat: merged.lat, lng: merged.lng };
                st.renderHeading = h;
                st.lastPoint = merged;
                st.motion = null;
                if (moving) this.appendTrail(st, merged.lat, merged.lng, liveColor);
                this.renderLiveClusters();
                return;
            }
            this.startMotion(id, merged);
            this.renderLiveClusters();
        }

        /**
         * Premium continuous motion. The marker glides from its current rendered
         * position to the latest fix at constant velocity over slightly longer
         * than the poll cadence, so the next fix almost always arrives before we
         * reach the target — eliminating the snap-back/overshoot that makes
         * dead-reckoning feel jittery. A short, capped dead-reckon bridges a late
         * poll so genuinely-moving markers never freeze mid-street.
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
            const segMeters = distMeters(from, toLL);

            // GPS heading is noisy at rest — keep the previous heading when the
            // vehicle is effectively stopped so the arrow doesn't twitch/spin.
            let toH = parseFloat(to.heading);
            if (!Number.isFinite(toH) || (!moving && speedKmh < 3)) toH = fromH;

            // Glide ~15% slower than the poll cadence to stay a hair behind
            // real-time (buttery, no overshoot). Tighten the glide for big jumps
            // (reconnect/gap) so the marker doesn't crawl across the map.
            let catchupMs = interval * 1.15;
            if (segMeters > 400) catchupMs = Math.min(catchupMs, 1200);

            st.dupSince = null;
            st.motion = {
                from,
                to: toLL,
                fromH,
                toH,
                speedKmh,
                color: colorForPoint(to, this.stateColors),
                catchupMs: Math.max(250, catchupMs),
                cruise: moving && speedKmh > 1,
                // Bridge a slightly late/dropped poll so a moving marker keeps
                // gliding past the target until the next fix instead of stalling.
                maxCruiseSec: Math.min(3, interval / 1000 + 1),
                start: performance.now(),
            };
            st.lastPoint = to;
            if (!moving) this.clearTrail(st);
            this.startMotionLoop();
        }

        /**
         * Keep a moving marker gliding forward when the server returns the same
         * coordinates on consecutive polls (the GPS unit reports slower than we
         * poll). Dead-reckons from the current rendered position along the latest
         * heading at the reported speed. Bounded by a safety budget so a stale
         * "moving" status can't drift the marker far off-road before it holds.
         */
        continueCruise(id, to) {
            const st = this.vehicleState(id);
            const now = performance.now();
            const MAX_BRIDGE_SEC = 8;
            if (st.dupSince == null) st.dupSince = now;
            const bridged = (now - st.dupSince) / 1000;
            const speedKmh = Math.max(0, parseFloat(to.speed) || 0);

            // Past the safety budget (or effectively stopped): hold position.
            if (bridged >= MAX_BRIDGE_SEC || speedKmh <= 1) {
                st.motion = null;
                st.lastPoint = to;
                return;
            }

            const base = st.renderPos || { lat: to.lat, lng: to.lng };
            let toH = parseFloat(to.heading);
            const fromH = st.renderHeading != null
                ? st.renderHeading
                : (Number.isFinite(toH) ? toH : 0);
            if (!Number.isFinite(toH)) toH = fromH;

            const interval = this.cfg.pollIntervalMs || 2000;
            const remainingSec = Math.max(0, MAX_BRIDGE_SEC - bridged);

            st.motion = {
                from: base,
                to: base, // no glide target; cruise straight out from where we are
                fromH,
                toH,
                speedKmh,
                color: colorForPoint(to, this.stateColors),
                catchupMs: 200, // brief heading ease only
                cruise: true,
                maxCruiseSec: Math.min(remainingSec, interval / 1000 + 1),
                start: now,
            };
            st.lastPoint = to;
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
                    // Constant-velocity (linear) position so consecutive segments
                    // join seamlessly — ease-out would brake the marker at every
                    // fix and read as a stutter. Heading eases slightly for a
                    // natural turn-in.
                    lat = m.from.lat + (m.to.lat - m.from.lat) * t;
                    lng = m.from.lng + (m.to.lng - m.from.lng) * t;
                    const hT = t < 0.5 ? 2 * t * t : 1 - Math.pow(-2 * t + 2, 2) / 2;
                    heading = lerpHeading(m.fromH, m.toH, hT);
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
                st.marker.setPosition({ lat, lng });
                const vehicle = this.vehicles.get(id);
                if (vehicle) {
                    this.setVehicleMarkerIcon(st, { ...vehicle, color: m.color }, heading);
                } else {
                    st.marker.setIcon(arrowIcon(m.color, heading));
                }
                const clustered = this.clusteredDeviceIds?.has(Number(id));
                st.marker.setMap(clustered ? null : this.map);
                if (!clustered && MOVING_KEYS.has(st.lastPoint?.status_key || '')) this.appendTrail(st, lat, lng, m.color);

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
            if (show && !this.historyActive) this.renderLiveClusters();
            else this.clearLiveClusters();
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
                this.syncVisibleVehicleRoutePolylines();
                this.renderLiveClusters();
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
            document.getElementById('tcZoomIn')?.addEventListener('click', () => {
                if (this.map) this.map.setZoom((this.map.getZoom() || 11) + 1);
            });
            document.getElementById('tcZoomOut')?.addEventListener('click', () => {
                if (this.map) this.map.setZoom((this.map.getZoom() || 11) - 1);
            });
            document.getElementById('tcFit')?.addEventListener('click', () => this.fitAll());
            document.getElementById('tcFollow')?.addEventListener('click', () => this.toggleFollow());
            document.getElementById('tcRefresh')?.addEventListener('click', () => this.pollLive(true));
            document.getElementById('tcCapture')?.addEventListener('click', () => this.captureMap());
        }

        /**
         * Capture the current map view as a PNG via the Google Static Maps API
         * (reliable for Google tiles; html2canvas can't capture WebGL map layers).
         * Falls back to opening the image in a new tab if a direct download fails.
         */
        async captureMap() {
            if (!this.map || !this.cfg.googleMapsKey) return;
            const center = this.map.getCenter();
            if (!center) return;

            const btn = document.getElementById('tcCapture');
            btn?.classList.add('active');

            const params = new URLSearchParams();
            params.set('center', `${center.lat()},${center.lng()}`);
            params.set('zoom', String(this.map.getZoom() || 14));
            params.set('size', '640x640');
            params.set('scale', '2');
            params.set('maptype', this.map.getMapTypeId() || 'roadmap');
            params.set('key', this.cfg.googleMapsKey);

            let markerCount = 0;
            this.visible.forEach((id) => {
                if (markerCount >= 40) return;
                const v = this.vehicles.get(id);
                if (v?.lat != null && v?.lng != null) {
                    params.append('markers', `color:red|${v.lat},${v.lng}`);
                    markerCount++;
                }
            });

            const url = `https://maps.googleapis.com/maps/api/staticmap?${params.toString()}`;
            try {
                const res = await fetch(url);
                if (!res.ok) throw new Error('static map failed');
                const blob = await res.blob();
                const objUrl = URL.createObjectURL(blob);
                const a = document.createElement('a');
                a.href = objUrl;
                a.download = `tracking-map-${Date.now()}.png`;
                document.body.appendChild(a);
                a.click();
                a.remove();
                setTimeout(() => URL.revokeObjectURL(objUrl), 5000);
            } catch (err) {
                global.open(url, '_blank');
            } finally {
                btn?.classList.remove('active');
            }
        }

        /* ---------- Map layers (Map / Satellite / Hybrid / Terrain + Traffic) ---------- */
        bindMapLayers() {
            const toggleBtn = document.getElementById('tcLayers');
            const menu = document.getElementById('tcLayerMenu');
            const trafficBtn = document.getElementById('tcTraffic');

            if (toggleBtn && menu) {
                toggleBtn.addEventListener('click', (e) => {
                    e.stopPropagation();
                    const open = menu.classList.toggle('open');
                    toggleBtn.setAttribute('aria-expanded', open ? 'true' : 'false');
                });
                document.addEventListener('click', (e) => {
                    if (!menu.contains(e.target) && e.target !== toggleBtn) {
                        menu.classList.remove('open');
                        toggleBtn.setAttribute('aria-expanded', 'false');
                    }
                });
                menu.querySelectorAll('.tc-layer-item').forEach((item) => {
                    item.addEventListener('click', () => {
                        const type = item.dataset.layer;
                        if (this.map && global.google?.maps?.MapTypeId) {
                            const map = {
                                roadmap: google.maps.MapTypeId.ROADMAP,
                                satellite: google.maps.MapTypeId.SATELLITE,
                                hybrid: google.maps.MapTypeId.HYBRID,
                                terrain: google.maps.MapTypeId.TERRAIN,
                            };
                            this.map.setMapTypeId(map[type] || google.maps.MapTypeId.ROADMAP);
                        }
                        menu.querySelectorAll('.tc-layer-item').forEach((b) => b.classList.toggle('active', b === item));
                        menu.classList.remove('open');
                        toggleBtn.setAttribute('aria-expanded', 'false');
                    });
                });
            }

            if (trafficBtn) {
                trafficBtn.addEventListener('click', () => {
                    if (!this.trafficLayer) return;
                    const on = this.trafficLayer.getMap() == null;
                    this.trafficLayer.setMap(on ? this.map : null);
                    trafficBtn.classList.toggle('active', on);
                    trafficBtn.setAttribute('aria-pressed', on ? 'true' : 'false');
                });
            }
        }

        /* ---------- Responsive drawer + sizing ---------- */
        isMobile() {
            return global.matchMedia && global.matchMedia('(max-width: 768px)').matches;
        }

        closeNavigationOnVehicleSelect() {
            this.closePanel();
            this.closeTopbar();
        }

        bindTopbarToggle() {
            const toggle = document.getElementById('tcTopbarToggle');
            const app = document.querySelector('.tc-app');
            toggle?.addEventListener('click', () => {
                const open = !app?.classList.contains('tc-app--topnav-open');
                this.setTopbarOpen(open);
            });
            document.addEventListener('keydown', (e) => {
                if (e.key === 'Escape') this.closeTopbar();
            });
        }

        setTopbarOpen(open) {
            const app = document.querySelector('.tc-app');
            if (!app) return;
            app.classList.toggle('tc-app--topnav-open', !!open);
            document.getElementById('tcTopbarToggle')?.setAttribute('aria-expanded', open ? 'true' : 'false');
            this.mapResize();
        }

        closeTopbar() {
            this.setTopbarOpen(false);
        }

        bindPanelToggle() {
            const toggle = document.getElementById('tcPanelToggle');
            const backdrop = document.getElementById('tcPanelBackdrop');
            toggle?.addEventListener('click', () => {
                const panel = document.getElementById('tcPanel');
                if (panel?.classList.contains('tc-panel--open')) this.closePanel();
                else this.openPanel();
            });
            backdrop?.addEventListener('click', () => this.closePanel());
            document.addEventListener('keydown', (e) => {
                if (e.key === 'Escape') this.closeNavigationOnVehicleSelect();
            });

            let raf = null;
            const onResize = () => {
                if (raf) cancelAnimationFrame(raf);
                raf = requestAnimationFrame(() => {
                    this.fitAppHeight();
                    this.mapResize();
                });
            };
            global.addEventListener('resize', onResize);
            global.addEventListener('orientationchange', onResize);
        }

        openPanel() {
            const panel = document.getElementById('tcPanel');
            if (!panel) return;
            panel.classList.add('tc-panel--open');
            document.getElementById('tcPanelBackdrop')?.classList.add('show');
            document.getElementById('tcPanelToggle')?.setAttribute('aria-expanded', 'true');
        }

        closePanel() {
            document.getElementById('tcPanel')?.classList.remove('tc-panel--open');
            document.getElementById('tcPanelBackdrop')?.classList.remove('show');
            document.getElementById('tcPanelToggle')?.setAttribute('aria-expanded', 'false');
        }

        fitAppHeight() {
            const app = document.querySelector('.tc-app');
            if (!app) return;
            const top = app.getBoundingClientRect().top + (global.scrollY || global.pageYOffset || 0);
            const h = Math.max(360, (global.innerHeight || document.documentElement.clientHeight) - top);
            app.style.height = h + 'px';
        }

        mapResize() {
            if (this.map && global.google?.maps?.event) {
                const c = this.map.getCenter();
                global.google.maps.event.trigger(this.map, 'resize');
                if (c) this.map.setCenter(c);
            }
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
            else {
                this.followId = ids[0];
                const v = this.vehicles.get(this.followId);
                if (v?.lat != null && v?.lng != null) {
                    this.map.panTo({ lat: v.lat, lng: v.lng });
                    this.map.setZoom(17);
                }
            }
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
                this._footerMode = null;
                this._panelDeviceId = null;
                if (this.followId) {
                    this.selectRouteTripVehicle(this.followId);
                } else {
                    this.clearRouteTripSelection();
                }
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
            const stats = normalizeHistoryStats(vehicle.stats, points, stops);
            stops.forEach((stop) => this.addStop(stop, name));
            this.renderStopList(stops, name, stats);
            this.renderSpeedLegend(name);
            this.renderHistoryFooter(vehicle, points, stops, stats);
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
                    <span>${escHtml(i.totalIdleTime || 'Idle')}: <strong>${escHtml(formatDuration(stats.idle_seconds))}</strong></span>
                    <span>${escHtml(i.parkingTime || 'Parking')}: <strong>${escHtml(formatDuration(stats.parking_seconds))}</strong></span>
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

        renderHistoryFooter(vehicle, points, stops, historyStats = null) {
            const dataEl = document.getElementById('tcFooterData');
            const msgEl = document.getElementById('tcFooterMessages');
            if (!dataEl) return;

            const i = this.cfg.i18n || {};
            const kmh = i.kmhUnit || 'km/h';
            const stats = historyStats || normalizeHistoryStats(vehicle?.stats, points, stops);
            const events = vehicle?.events || [];

            dataEl.classList.add('tc-fbody--hist');
            const statCard = (lbl, val) =>
                `<div class="tc-hist-stat"><div class="tc-hist-stat-lbl">${escHtml(lbl)}</div><div class="tc-hist-stat-val">${val}</div></div>`;

            dataEl.innerHTML = `<div class="tc-hist-stats">
                ${statCard(i.statRouteLength || 'Route length', `${stats.distance_km} km`)}
                ${statCard(i.statMoveDuration || 'Move duration', escHtml(formatDuration(stats.move_seconds)))}
                ${statCard(i.statStopDuration || 'Stop duration', escHtml(formatDuration(stats.stop_seconds)))}
                ${statCard(i.totalIdleTime || 'Idle time', escHtml(formatDuration(stats.idle_seconds)))}
                ${statCard(i.parkingTime || 'Parking time', escHtml(formatDuration(stats.parking_seconds)))}
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
            if (!this.cfg.devicePanelUrl || !document.getElementById('tcFooter')) return;
            const v = this.vehicles.get(id);
            this._footerMode = 'panel';
            this._panelDeviceId = id;
            this.closeNavigationOnVehicleSelect();
            this.selectRouteTripVehicle(id);
            this.showFooter(this.labelFor(v) || ('#' + id));
            this.switchFooterTab('data');
            const dataEl = document.getElementById('tcFooterData');
            const msgEl = document.getElementById('tcFooterMessages');
            if (dataEl) {
                dataEl.classList.remove('tc-fbody--hist');
                dataEl.innerHTML = this.panelSkeleton();
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
                if (data.panel?.route_trip) {
                    const sanitized = this.sanitizeRouteTrip(data.panel.route_trip);
                    const cur = this.vehicles.get(id);
                    if (cur) {
                        this.vehicles.set(id, { ...cur, route_trip: sanitized });
                    }
                    if (Number(this._routeTripDeviceId) === Number(id)) {
                        this.applyRouteTrip(id, sanitized);
                    }
                }
                if (data.panel?.driver) {
                    const cur = this.vehicles.get(id);
                    if (cur) {
                        this.vehicles.set(id, { ...cur, driver: data.panel.driver });
                    }
                }
                this.syncDriverMapCard();
            } catch (err) {
                if (dataEl) dataEl.innerHTML = `<div class="tc-empty">${escHtml(this.cfg.i18n?.panelLoadFailed || this.cfg.i18n?.loadFailed || 'Failed')}</div>`;
            }
        }

        panelSkeleton() {
            const row = `<div class="tc-skel-row"><div class="tc-skel tc-skel-dot"></div><div class="tc-skel-lines"><div class="tc-skel tc-skel-line"></div><div class="tc-skel tc-skel-line sm"></div></div></div>`;
            return `<div class="tc-fade-in" style="display:grid;grid-template-columns:repeat(auto-fill,minmax(200px,1fr));gap:0.5rem;padding:0.5rem">${row.repeat(6)}</div>`;
        }

        ensureRouteTripProgress() {
            if (!this.uiOn('route_progress')) return null;
            if (this.routeTripKit || !global.RouteTripProgress) return this.routeTripKit;
            if (!document.getElementById('routeTripProgressBar')) return null;
            const i = this.cfg.i18n || {};
            this.routeTripKit = new global.RouteTripProgress({
                containerId: 'routeTripProgressBar',
                compactFooter: true,
                showPolyline: () => this.uiOn('polyline'),
                getMap: () => this.map,
                googleMaps: global.google,
                shouldFitRouteBounds: () => !this._routeBoundsFitted,
                onRouteBoundsFitted: () => { this._routeBoundsFitted = true; },
                getVehiclePosition: () => {
                    const v = this.vehicles.get(this._routeTripDeviceId);
                    if (!v || v.lat == null || v.lng == null) return null;
                    return { lat: parseFloat(v.lat), lng: parseFloat(v.lng) };
                },
                manageRoutesUrl: this.cfg.manageRoutesUrl || null,
                routeGuidanceUrl: this.uiOn('polyline') ? (this.cfg.routeGuidanceUrl || null) : null,
                getDeviceId: () => this._routeTripDeviceId,
                i18n: {
                    remaining: i.routeRemaining || 'Remaining',
                    eta: i.routeEta || 'ETA',
                    duration: i.routeDuration || 'Duration',
                    complete: i.routeComplete || 'Mark trip completed',
                    startNew: i.routeStartNew || 'Start new trip',
                    offRoute: i.routeOffRoute || 'You are not on the assigned route',
                    elapsed: i.routeElapsed || 'Elapsed',
                    planned: i.routePlanned || 'Planned total',
                    checkpointTotal: i.routeCheckpointTotal || 'Checkpoint legs',
                    minAbbr: i.routeMinAbbr || 'min',
                    pending: i.routePending || '—',
                    toggleDetails: i.routeToggleDetails || 'Show trip details',
                    progressOffRoute: i.routeProgressOffRoute || 'On route only',
                    progressFrozen: i.routeProgressFrozen || 'Last on-route',
                    progressStale: i.routeProgressStale || 'Last known position',
                    waitingForStart: i.routeWaitingForStart || 'Waiting for start area',
                    waitingForStartHint: i.routeWaitingForStartHint || 'Progress will start automatically when the vehicle enters the start area.',
                    manageRoutes: i.routeManage || 'Manage routes',
                    assignedHint: i.routeAssignedHint || '',
                    progressTitle: i.routeProgressTitle || 'Route progress',
                    reachedStart: i.routeReachedStart || 'Trip started — departed from {city}',
                    reachedCheckpoint: i.routeReachedCheckpoint || 'Reached checkpoint: {city}',
                    reachedDestination: i.routeReachedDestination || 'Reached destination: {city}',
                    traveled: i.routeTraveled || 'Traveled',
                    currentSpeed: i.routeCurrentSpeed || 'Speed',
                    currentCheckpoint: i.routeCurrentCheckpoint || 'Current',
                    nextCheckpoint: i.routeNextCheckpoint || 'Next',
                    currentCity: i.routeCurrentCity || 'Current city',
                    nextCity: i.routeNextCity || 'Next city',
                    arrivalTime: i.routeArrivalTime || 'Arrival',
                    navOnAssigned: i.routeNavOnAssigned || 'On assigned route',
                    suggestedRoute: i.routeSuggestedRoute || 'Suggested route to destination',
                    navJoining: i.routeNavJoining || 'Joining route',
                    navRecalculated: i.routeNavRecalculated || 'Route recalculated',
                    navSlightDeviation: i.routeNavSlightDeviation || 'Slight deviation',
                    navDestinationReached: i.routeNavDestinationReached || 'Destination reached',
                    offRouteBadge: i.routeOffRouteBadge || 'Off route',
                    kmhUnit: i.kmhUnit || 'km/h',
                },
                onMilestoneReached: (_milestone, message) => this.toast(message, 'success'),
                onComplete: () => this.completeAssignedTrip(),
                onStartNew: () => this.startNewAssignedTrip(),
                onRestart: () => this.restartAssignedTrip(),
            });
            return this.routeTripKit;
        }

        /** Pick which vehicle's assigned route to show on the map. */
        pickRouteTripVehicle() {
            if (this._panelDeviceId != null && this.visible.has(this._panelDeviceId)) {
                this.selectRouteTripVehicle(this._panelDeviceId);
                return;
            }
            if (this.followId != null && this.visible.has(this.followId)) {
                this.selectRouteTripVehicle(this.followId);
                return;
            }
            for (const id of this.visible) {
                if (this.vehicles.get(id)?.route_trip?.route) {
                    this.selectRouteTripVehicle(id);
                    return;
                }
            }
            const first = [...this.visible][0];
            if (first != null) {
                this.selectRouteTripVehicle(first);
            }
        }

        routePathPoints(route) {
            if (!this.routeAllowsPolyline(route)) return [];
            return (route.assigned_polyline || route.guided_polyline || route.polyline || route.admin_polyline || [])
                .map((p) => ({ lat: parseFloat(p.lat), lng: parseFloat(p.lng) }))
                .filter((p) => Number.isFinite(p.lat) && Number.isFinite(p.lng));
        }

        drawAssignedRouteOverlay(payload) {
            if (!this.uiOn('polyline') || !this.routeAllowsPolyline(payload?.route)) return;
            const map = this.map;
            const google = global.google;
            if (!map || !google?.maps || !payload?.route) return;

            const pts = this.routePathPoints(payload.route);
            if (pts.length < 2) return;

            if (!this._assignedRoutePolyline) {
                this._assignedRoutePolyline = new google.maps.Polyline({
                    map,
                    strokeColor: '#0ea5e9',
                    strokeOpacity: 0.95,
                    strokeWeight: 6,
                    zIndex: 250,
                    clickable: false,
                });
            }
            this._assignedRoutePolyline.setPath(pts);
            this._assignedRoutePolyline.setMap(map);
        }

        /**
         * Draw assigned-route polylines for every visible vehicle that has a route.
         * The selected vehicle is handled by applyRouteTrip / RouteTripProgress.
         */
        syncVisibleVehicleRoutePolylines() {
            const google = global.google;
            if (!this.map || !google?.maps) return;

            for (const [id, line] of this._vehicleRoutePolylines) {
                if (!this.visible.has(id)) {
                    line.setMap(null);
                    this._vehicleRoutePolylines.delete(id);
                }
            }

            if (!this.uiOn('polyline')) {
                this._vehicleRoutePolylines.forEach((line) => line.setMap(null));
                this._vehicleRoutePolylines.clear();
                this._assignedRoutePolyline?.setMap(null);
                return;
            }

            const selectedId = this._routeTripDeviceId != null ? Number(this._routeTripDeviceId) : null;

            this.visible.forEach((id) => {
                const numId = Number(id);
                if (selectedId != null && numId === selectedId) {
                    const existing = this._vehicleRoutePolylines.get(id);
                    if (existing) {
                        existing.setMap(null);
                        this._vehicleRoutePolylines.delete(id);
                    }
                    return;
                }

                const pts = this.routePathPoints(this.vehicles.get(id)?.route_trip?.route);
                if (pts.length < 2 || !this.routeAllowsPolyline(this.vehicles.get(id)?.route_trip?.route)) {
                    const existing = this._vehicleRoutePolylines.get(id);
                    if (existing) {
                        existing.setMap(null);
                        this._vehicleRoutePolylines.delete(id);
                    }
                    return;
                }

                let line = this._vehicleRoutePolylines.get(id);
                if (!line) {
                    line = new google.maps.Polyline({
                        map: this.map,
                        strokeColor: '#0ea5e9',
                        strokeOpacity: 0.55,
                        strokeWeight: 4,
                        zIndex: 200,
                        clickable: false,
                    });
                    this._vehicleRoutePolylines.set(id, line);
                }
                line.setPath(pts);
                line.setMap(this.map);
            });
        }

        /** Show route progress only for the vehicle the user explicitly selected. */
        selectRouteTripVehicle(id) {
            if (this.uiOn('polyline') || this.uiOn('route_progress')) {
                if (Number(this._routeTripDeviceId) !== Number(id)) {
                    this._routeBoundsFitted = false;
                    this.clearSelectedRouteOverlays();
                }
                this._routeTripDeviceId = id;
                const cached = this.vehicles.get(id)?.route_trip;
                if (cached?.route) {
                    this.applyRouteTrip(id, this.sanitizeRouteTrip(cached), { forceFitBounds: true });
                } else {
                    this.clearSelectedRouteOverlays();
                    this.routeTripKit?.clear();
                }
                this.syncVisibleVehicleRoutePolylines();
                this.loadRouteTripForDevice(id);
            }
            this.syncCompanyMapCard();
            this.syncDriverMapCard();
        }

        async loadRouteTripForDevice(id) {
            const url = this.cfg.devicePanelUrl;
            if (!url || Number(this._routeTripDeviceId) !== Number(id)) return;
            try {
                const res = await fetch(`${url}?device_id=${encodeURIComponent(id)}&_=${Date.now()}`, {
                    credentials: 'same-origin',
                    cache: 'no-store',
                    headers: { Accept: 'application/json' },
                });
                const data = await res.json().catch(() => ({}));
                const routeTrip = data.panel?.route_trip || data.route_trip;
                if (routeTrip?.route && Number(this._routeTripDeviceId) === Number(id)) {
                    const cur = this.vehicles.get(id) || { id };
                    const sanitized = this.sanitizeRouteTrip(routeTrip);
                    this.vehicles.set(id, { ...cur, route_trip: sanitized });
                    this.applyRouteTrip(id, sanitized, { forceFitBounds: !this._routeBoundsFitted });
                }
            } catch (_) { /* ignore */ }
        }

        clearRouteTripSelection() {
            this._routeTripDeviceId = null;
            this._routeBoundsFitted = false;
            this.clearAllRouteMapOverlays();
            this.routeTripKit?.clear();
            this.syncVisibleVehicleRoutePolylines();
        }

        fitMapToAssignedRoute(payload) {
            const map = this.map;
            const google = global.google;
            if (!map || !google?.maps || !payload?.route) return;

            const pts = this.routePathPoints(payload.route);
            if (pts.length < 2) return;

            const bounds = new google.maps.LatLngBounds();
            pts.forEach((p) => bounds.extend(p));

            const v = this.vehicles.get(this._routeTripDeviceId);
            if (v?.lat != null && v?.lng != null) {
                bounds.extend({ lat: parseFloat(v.lat), lng: parseFloat(v.lng) });
            }

            map.fitBounds(bounds, { top: 72, right: 40, bottom: 140, left: 40 });
            this._routeBoundsFitted = true;
        }

        applyRouteTrip(id, payload, options = {}) {
            if (!this.uiOn('polyline') && !this.uiOn('route_progress')) return;
            if (this._routeTripDeviceId == null || Number(this._routeTripDeviceId) !== Number(id)) {
                return;
            }
            payload = this.sanitizeRouteTrip(payload);
            if (!payload?.route) {
                this.clearRouteTripSelection();
                return;
            }
            const kit = this.ensureRouteTripProgress();
            if (this.uiOn('polyline') && this.routeAllowsPolyline(payload.route)) {
                if (kit) {
                    this._assignedRoutePolyline?.setMap(null);
                } else {
                    this.drawAssignedRouteOverlay(payload);
                }
            } else {
                this._assignedRoutePolyline?.setMap(null);
            }
            if (kit) {
                kit.update({ route_trip: payload });
            } else if (!this.uiOn('polyline')) {
                this.routeTripKit?.clear();
            }
            this.syncVisibleVehicleRoutePolylines();
            if (this.uiOn('polyline') && this.routeAllowsPolyline(payload.route)
                && (options.forceFitBounds || !this._routeBoundsFitted)) {
                this.fitMapToAssignedRoute(payload);
            }
        }

        async completeAssignedTrip() {
            const url = this.cfg.completeTripUrl;
            const id = this._routeTripDeviceId;
            if (!url || !id) return;
            try {
                const res = await fetch(url, {
                    method: 'POST',
                    credentials: 'same-origin',
                    headers: {
                        Accept: 'application/json',
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': this.cfg.csrfToken || '',
                    },
                    body: JSON.stringify({ device_id: id }),
                });
                const data = await res.json().catch(() => ({}));
                if (data.success) {
                    if (data.route_trip) this.applyRouteTrip(id, data.route_trip);
                    await this.pollLive(true);
                }
            } catch (_) { /* ignore */ }
        }

        async startNewAssignedTrip() {
            const url = this.cfg.startNewTripUrl;
            const id = this._routeTripDeviceId;
            if (!url || !id) return;
            try {
                const res = await fetch(url, {
                    method: 'POST',
                    credentials: 'same-origin',
                    headers: {
                        Accept: 'application/json',
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': this.cfg.csrfToken || '',
                    },
                    body: JSON.stringify({ device_id: id }),
                });
                const data = await res.json().catch(() => ({}));
                if (data.success) {
                    if (data.route_trip) this.applyRouteTrip(id, data.route_trip);
                    await this.pollLive(true);
                } else if (data.message && global.Swal) {
                    global.Swal.fire({ icon: 'error', title: data.message });
                }
            } catch (_) { /* ignore */ }
        }

        async restartAssignedTrip() {
            const url = this.cfg.restartTripUrl;
            const id = this._routeTripDeviceId;
            if (!url || !id) return;
            if (global.Swal) {
                const confirm = await global.Swal.fire({
                    icon: 'warning',
                    title: this.cfg.i18n?.restartTripConfirm || 'Restart trip?',
                    text: this.cfg.i18n?.restartTripConfirmText || 'This clears current trip progress and starts again from the vehicle position.',
                    showCancelButton: true,
                    confirmButtonText: this.cfg.i18n?.restartTrip || 'Restart trip',
                });
                if (!confirm.isConfirmed) return;
            }
            try {
                const res = await fetch(url, {
                    method: 'POST',
                    credentials: 'same-origin',
                    headers: {
                        Accept: 'application/json',
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': this.cfg.csrfToken || '',
                    },
                    body: JSON.stringify({ device_id: id }),
                });
                const data = await res.json().catch(() => ({}));
                if (data.success) {
                    if (data.route_trip) this.applyRouteTrip(id, data.route_trip);
                    await this.pollLive(true);
                } else if (data.message && global.Swal) {
                    global.Swal.fire({ icon: 'error', title: data.message });
                }
            } catch (_) { /* ignore */ }
        }

        /** Connectivity-aware status label; last-known motion when delayed/stale/offline. */
        panelStatusHtml(item, i) {
            const dash = '—';
            const statusLabel = item.status || item.status_label || dash;
            const tier = item.connectivity_tier || 'live';
            const lastKnown = item.last_known_status;
            let html = escHtml(statusLabel);
            if (tier !== 'live' && lastKnown && lastKnown !== statusLabel) {
                const lastLbl = i.lblLastKnown || 'last known';
                html += ` <span class="tc-muted">(${escHtml(lastLbl)}: ${escHtml(lastKnown)})</span>`;
            }
            return html;
        }

        renderPanelData(panel) {
            const el = document.getElementById('tcFooterData');
            if (!el) return;
            const i = this.cfg.i18n || {};
            const kmh = i.kmhUnit || 'km/h';
            const dash = '—';
            const kv = (k, v, icon, id) => `<div class="tc-kv"><span class="tc-kv-k">${icon ? `<i class="fas ${icon}"></i>` : ''}${escHtml(k)}</span><span class="tc-kv-v"${id ? ` id="${id}"` : ''}>${v}</span></div>`;
            const pos = (panel.lat != null && panel.lng != null)
                ? `<a href="#" data-tc-locate="${panel.lat},${panel.lng}">${Number(panel.lat).toFixed(6)}, ${Number(panel.lng).toFixed(6)}</a>`
                : dash;
            const statusLabelHtml = this.panelStatusHtml(panel, i);
            const statusDuration = panel.status_duration_seconds != null
                ? ` <span id="tcPanelStatusDuration" class="tc-muted">${escHtml(i.lblStatusDuration || 'for')} ${formatDuration(panel.status_duration_seconds)}</span>`
                : '';
            const colA = [
                kv(i.lblObject || 'Object', escHtml(panel.name || dash), panel.icon),
                kv(i.lblPlate || 'Plate', escHtml(panel.plate || dash), 'fa-id-card'),
                kv(i.lblStatus || 'Status', `<span id="tcPanelStatus" style="color:${escHtml(panel.color || '#475569')}">${statusLabelHtml}</span>${statusDuration}`, 'fa-circle-info'),
                kv(i.lblOdometer || 'Odometer', panel.odometer != null ? `${panel.odometer} km` : dash, 'fa-gauge', 'tcPanelOdometer'),
                kv(i.lblAltitude || 'Altitude', panel.altitude != null ? `${panel.altitude} m` : dash, 'fa-mountain', 'tcPanelAltitude'),
                kv(i.lblAngle || 'Angle', panel.angle != null ? `${panel.angle}\u00b0` : dash, 'fa-compass', 'tcPanelAngle'),
            ].join('');
            const colB = [
                kv(i.lblPosition || 'Position', pos, 'fa-location-dot', 'tcPanelPos'),
                kv(i.lblSpeed || 'Speed', `${panel.speed != null ? Math.round(panel.speed) : 0} ${escHtml(kmh)}`, 'fa-gauge-high', 'tcPanelSpeed'),
                kv(i.lblTimePosition || 'Time (position)', escHtml(panel.time_position || dash), 'fa-clock', 'tcPanelTimePos'),
                kv(i.lblTimeServer || 'Time (server)', escHtml(panel.time_server || dash), 'fa-server', 'tcPanelTimeServer'),
                kv(i.lblEngine || 'Engine', panel.ignition == null ? dash : (panel.ignition ? (i.ignitionOn || 'On') : (i.ignitionOff || 'Off')), 'fa-key', 'tcPanelIgnition'),
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

            // Speedometer gauge (SVG semicircle) — wrapped so live polls can re-render it in place.
            this._panelSpeedMax = parseFloat(panel.speed_max) || 160;
            const speedoHtml = `<div id="tcPanelSpeedo">${this.speedoSvg(panel.speed, this._panelSpeedMax)}</div>`;

            // Notes / Photo
            const notesHtml = panel.notes
                ? `<div class="tc-notes">${escHtml(panel.notes)}</div>`
                : `<div class="tc-empty">${escHtml(i.noNotes || 'No notes.')}</div>`;
            const photoHtml = panel.photo
                ? `<img src="${escHtml(panel.photo)}" alt="" class="tc-photo">`
                : `<div class="tc-empty">${escHtml(i.noPhoto || 'No photo.')}</div>`;

            el.innerHTML = `<div class="tc-data-grid tc-fade-in">
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

        speedoSvg(speed, maxSpd) {
            const kmh = this.cfg.i18n?.kmhUnit || 'km/h';
            const spd = Math.max(0, Math.round(parseFloat(speed) || 0));
            const max = parseFloat(maxSpd) || 160;
            const frac = Math.min(1, spd / max);
            const gaugeColor = frac > 0.8 ? '#d6394d' : frac > 0.5 ? '#d99a16' : '#1f9d57';
            return `<svg class="tc-gauge" viewBox="0 0 120 72">
                <path d="M10 62 A 50 50 0 0 1 110 62" fill="none" stroke="#e2e8f0" stroke-width="10" stroke-linecap="round" pathLength="100"></path>
                <path d="M10 62 A 50 50 0 0 1 110 62" fill="none" stroke="${gaugeColor}" stroke-width="10" stroke-linecap="round" pathLength="100" stroke-dasharray="${(frac * 100).toFixed(1)} 100"></path>
                <text x="60" y="52" text-anchor="middle" class="tc-gauge-val">${spd}</text>
                <text x="60" y="66" text-anchor="middle" class="tc-gauge-unit">${escHtml(kmh)}</text>
            </svg>`;
        }

        /**
         * Live-refresh the open footer panel's volatile fields (speedometer,
         * speed, status, ignition, position, time) when a new fix arrives for the
         * device whose panel is currently shown — no full re-fetch needed.
         */
        updatePanelLive(v) {
            if (this._footerMode !== 'panel' || this._panelDeviceId == null) return;
            if (Number(this._panelDeviceId) !== Number(v.id)) return;

            const i = this.cfg.i18n || {};
            const kmh = i.kmhUnit || 'km/h';
            const dash = '—';

            const footerTitle = document.getElementById('tcFooterTitle');
            if (footerTitle) footerTitle.textContent = this.labelFor(v);

            const speedo = document.getElementById('tcPanelSpeedo');
            if (speedo) speedo.innerHTML = this.speedoSvg(v.speed, this._panelSpeedMax || 160);

            const spdEl = document.getElementById('tcPanelSpeed');
            if (spdEl) spdEl.textContent = `${v.speed != null ? Math.round(parseFloat(v.speed) || 0) : 0} ${kmh}`;

            const statusEl = document.getElementById('tcPanelStatus');
            if (statusEl) {
                statusEl.innerHTML = this.panelStatusHtml(v, i);
                statusEl.style.color = colorForPoint(v, this.stateColors);
            }

            const statusDurEl = document.getElementById('tcPanelStatusDuration');
            if (statusDurEl) {
                if (v.status_duration_seconds != null) {
                    statusDurEl.textContent = `${i.lblStatusDuration || 'for'} ${formatDuration(v.status_duration_seconds)}`;
                    statusDurEl.style.display = '';
                } else {
                    statusDurEl.style.display = 'none';
                }
            }

            const ignEl = document.getElementById('tcPanelIgnition');
            if (ignEl) ignEl.textContent = v.ignition == null ? dash : (v.ignition ? (i.ignitionOn || 'On') : (i.ignitionOff || 'Off'));

            const odoKm = v.odometer_km != null
                ? v.odometer_km
                : (v.odometer != null ? Math.round((parseFloat(v.odometer) || 0) / 1000) : null);
            const odoEl = document.getElementById('tcPanelOdometer');
            if (odoEl) odoEl.textContent = odoKm != null ? `${odoKm} km` : dash;

            const altEl = document.getElementById('tcPanelAltitude');
            if (altEl && v.altitude != null) {
                altEl.textContent = `${Math.round(parseFloat(v.altitude) || 0)} m`;
            }

            if (v.heading != null) {
                const angleEl = document.getElementById('tcPanelAngle');
                if (angleEl) angleEl.textContent = `${Math.round(parseFloat(v.heading) || 0)}\u00b0`;
            }

            const timePosEl = document.getElementById('tcPanelTimePos');
            if (timePosEl && (v.recorded_at_human || v.last_update)) {
                timePosEl.textContent = v.recorded_at_human || v.last_update;
            }

            const posEl = document.getElementById('tcPanelPos');
            if (posEl && v.lat != null && v.lng != null) {
                posEl.innerHTML = `<a href="#" data-tc-locate="${v.lat},${v.lng}">${Number(v.lat).toFixed(6)}, ${Number(v.lng).toFixed(6)}</a>`;
                posEl.querySelector('[data-tc-locate]')?.addEventListener('click', (e) => {
                    e.preventDefault();
                    if (hasGeo(v.lat, v.lng)) {
                        this.map.panTo({ lat: Number(v.lat), lng: Number(v.lng) });
                        if (this.map.getZoom() < 15) this.map.setZoom(16);
                    }
                });
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
                    this.closeTopbar();
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

        /* ---------- Alerts: sound chime + desktop notifications ---------- */
        alertPrefsKey() {
            return `tc_alert_prefs_${this.cfg.userId || 'guest'}`;
        }

        loadAlertPrefs() {
            const def = { sound: true, desktop: false };
            try {
                const raw = localStorage.getItem(this.alertPrefsKey());
                if (!raw) return def;
                const saved = JSON.parse(raw);
                return { sound: saved.sound !== false, desktop: saved.desktop === true };
            } catch (_) { return def; }
        }

        saveAlertPrefs() {
            try { localStorage.setItem(this.alertPrefsKey(), JSON.stringify(this.alertPrefs)); } catch (_) { /* ignore */ }
        }

        initAlerts() {
            this.alertPrefs = this.loadAlertPrefs();
            this._lastEventId = null;
            this._alertBadge = 0;
            this._seenEventIds = new Set();

            this.bindSoundToggle();
            this.bindDesktopToggle();
            this.syncAlertButtons();

            // Resume the audio context on the first user gesture (autoplay policy).
            const unlock = () => {
                this.ensureAudioCtx();
                if (this._audioCtx && this._audioCtx.state === 'suspended') this._audioCtx.resume().catch(() => {});
                document.removeEventListener('pointerdown', unlock);
                document.removeEventListener('keydown', unlock);
            };
            document.addEventListener('pointerdown', unlock, { once: false });
            document.addEventListener('keydown', unlock, { once: false });

            if (!this.cfg.eventsJsonUrl) return;
            // Baseline first, then poll for new alerts.
            this.pollAlerts(true);
            const interval = this.cfg.alertPollIntervalMs || 15000;
            this.alertTimer = setInterval(() => this.pollAlerts(false), interval);
        }

        bindSoundToggle() {
            const btn = document.getElementById('tcSoundToggle');
            if (!btn) return;
            btn.addEventListener('click', () => {
                this.alertPrefs.sound = !this.alertPrefs.sound;
                this.saveAlertPrefs();
                this.syncAlertButtons();
                if (this.alertPrefs.sound) {
                    this.ensureAudioCtx();
                    if (this._audioCtx && this._audioCtx.state === 'suspended') this._audioCtx.resume().catch(() => {});
                    this.playChime();
                } else {
                    this.toast(this.cfg.i18n?.alertSoundOff || 'Alert sound: off', 'info');
                }
            });
        }

        bindDesktopToggle() {
            const btn = document.getElementById('tcDesktopToggle');
            if (!btn) return;
            btn.addEventListener('click', async () => {
                const supported = 'Notification' in window;
                if (!supported) {
                    this.toast(this.cfg.i18n?.alertDesktopBlocked || 'Desktop notifications not supported', 'error');
                    return;
                }
                if (this.alertPrefs.desktop) {
                    this.alertPrefs.desktop = false;
                    this.saveAlertPrefs();
                    this.syncAlertButtons();
                    this.toast(this.cfg.i18n?.alertDesktopOff || 'Desktop notifications disabled', 'info');
                    return;
                }
                let perm = Notification.permission;
                if (perm === 'default') {
                    try { perm = await Notification.requestPermission(); } catch (_) { perm = Notification.permission; }
                }
                if (perm === 'granted') {
                    this.alertPrefs.desktop = true;
                    this.saveAlertPrefs();
                    this.syncAlertButtons();
                    this.toast(this.cfg.i18n?.alertDesktopOn || 'Desktop notifications enabled', 'success');
                } else {
                    this.toast(this.cfg.i18n?.alertDesktopBlocked || 'Desktop notifications are blocked', 'error');
                }
            });
        }

        syncAlertButtons() {
            const sBtn = document.getElementById('tcSoundToggle');
            if (sBtn) {
                const on = !!this.alertPrefs.sound;
                sBtn.classList.toggle('on', on);
                sBtn.classList.toggle('muted', !on);
                sBtn.setAttribute('aria-pressed', on ? 'true' : 'false');
                sBtn.title = on ? (this.cfg.i18n?.alertSound || 'Alert sound: on') : (this.cfg.i18n?.alertSoundOff || 'Alert sound: off');
                const ic = sBtn.querySelector('i');
                if (ic) ic.className = on ? 'fas fa-volume-high' : 'fas fa-volume-xmark';
            }
            const dBtn = document.getElementById('tcDesktopToggle');
            if (dBtn) {
                const on = !!this.alertPrefs.desktop && ('Notification' in window) && Notification.permission === 'granted';
                dBtn.classList.toggle('on', on);
                dBtn.setAttribute('aria-pressed', on ? 'true' : 'false');
                const ic = dBtn.querySelector('i');
                if (ic) ic.className = on ? 'fas fa-bell' : 'fas fa-bell-slash';
            }
        }

        async pollAlerts(baseline) {
            if (!this.cfg.eventsJsonUrl) return;
            // Narrow window keeps the scan + payload light.
            const since = new Date(Date.now() - 30 * 60 * 1000).toISOString();
            const url = `${this.cfg.eventsJsonUrl}?per_page=25&page=1&from=${encodeURIComponent(since)}&_=${Date.now()}`;
            try {
                const res = await fetch(url, { credentials: 'same-origin', cache: 'no-store' });
                if (!res.ok) return;
                const data = await res.json();
                const events = (data.events || []).filter((e) => e && e.id != null);
                if (events.length === 0) return;

                // Events come newest-first; compute the highest id.
                const maxId = events.reduce((m, e) => Math.max(m, Number(e.id) || 0), 0);

                if (baseline || this._lastEventId == null) {
                    this._lastEventId = maxId;
                    events.forEach((e) => this._seenEventIds.add(Number(e.id)));
                    return;
                }

                // New events = id greater than last seen, oldest first for natural ordering.
                const fresh = events
                    .filter((e) => Number(e.id) > this._lastEventId && !this._seenEventIds.has(Number(e.id)))
                    .sort((a, b) => Number(a.id) - Number(b.id));

                fresh.forEach((e) => {
                    this._seenEventIds.add(Number(e.id));
                    this.onNewAlert(e);
                });
                this._lastEventId = Math.max(this._lastEventId, maxId);

                if (fresh.length) {
                    this.bumpAlertBadge(fresh.length);
                    if (this._eventsLoaded) this.loadEvents();
                }
            } catch (err) {
                console.warn('[traccar-ui] alert poll failed', err);
            }
        }

        onNewAlert(ev) {
            const sev = (ev.type || '').toLowerCase();
            const toastType = sev === 'critical' ? 'error' : (sev === 'warning' ? 'warning' : 'info');
            const device = ev.device_name || '';
            const title = ev.title || ev.message || this.cfg.i18n?.newAlertTitle || 'New alert';
            const body = [device, ev.message && ev.message !== title ? ev.message : (ev.time || '')]
                .filter(Boolean).join(' · ');

            if (this.alertPrefs.sound) this.playChime(sev);

            // In-app toast (visible while the map is open).
            this.toast(device ? `${device} — ${title}` : title, toastType);

            // Desktop notification when the tab is hidden or the window is not focused.
            const notFocused = document.visibilityState === 'hidden' || (typeof document.hasFocus === 'function' && !document.hasFocus());
            if (this.alertPrefs.desktop && ('Notification' in window) && Notification.permission === 'granted' && notFocused) {
                try {
                    const n = new Notification(title, {
                        body: body || title,
                        tag: `tc-evt-${ev.id}`,
                        renotify: false,
                    });
                    n.onclick = () => {
                        window.focus();
                        if (hasGeo(ev.lat, ev.lng)) this.locateEvent(parseFloat(ev.lat), parseFloat(ev.lng));
                        n.close();
                    };
                } catch (_) { /* ignore */ }
            }
        }

        bumpAlertBadge(n) {
            this._alertBadge = (this._alertBadge || 0) + n;
            const badge = document.getElementById('tcEventsBadge');
            if (badge) {
                badge.textContent = this._alertBadge > 99 ? '99+' : String(this._alertBadge);
                badge.hidden = false;
            }
        }

        clearAlertBadge() {
            this._alertBadge = 0;
            const badge = document.getElementById('tcEventsBadge');
            if (badge) { badge.hidden = true; badge.textContent = '0'; }
        }

        ensureAudioCtx() {
            if (this._audioCtx) return this._audioCtx;
            const Ctx = window.AudioContext || window.webkitAudioContext;
            if (!Ctx) return null;
            try { this._audioCtx = new Ctx(); } catch (_) { this._audioCtx = null; }
            return this._audioCtx;
        }

        /** Synthesize a short two-note chime via Web Audio (no asset file needed). */
        playChime(severity) {
            const ctx = this.ensureAudioCtx();
            if (!ctx) return;
            if (ctx.state === 'suspended') ctx.resume().catch(() => {});
            const now = ctx.currentTime;
            // Critical alerts get a slightly more urgent, lower/triple tone.
            const notes = severity === 'critical' ? [988, 740, 988] : [880, 1175];
            const master = ctx.createGain();
            master.gain.value = 0.0001;
            master.connect(ctx.destination);
            notes.forEach((freq, i) => {
                const t = now + i * 0.16;
                const osc = ctx.createOscillator();
                const gain = ctx.createGain();
                osc.type = 'sine';
                osc.frequency.setValueAtTime(freq, t);
                gain.gain.setValueAtTime(0.0001, t);
                gain.gain.exponentialRampToValueAtTime(0.22, t + 0.02);
                gain.gain.exponentialRampToValueAtTime(0.0001, t + 0.32);
                osc.connect(gain);
                gain.connect(master);
                osc.start(t);
                osc.stop(t + 0.34);
            });
            master.gain.setValueAtTime(1, now);
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
