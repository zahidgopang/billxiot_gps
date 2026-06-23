/**
 * GPS device map tracker — live updates, history, alerts
 */
(function () {
    'use strict';

    const cfg = window.DEVICE_MAP_CONFIG || {};
    const i18n = cfg.i18n || {};
    const mi = (key, fallback) => (i18n[key] != null && i18n[key] !== '' ? i18n[key] : fallback);
    const dash = () => mi('dash', '—');
    const deviceId = cfg.deviceId;
    const baseUrl = cfg.baseUrl || '';
    const csrfToken = cfg.csrfToken || '';
    const api = cfg.apiRoutes || {};
    const mapToken = cfg.mapToken || '';
    const liveUrl = api.live || (mapToken ? `${baseUrl}/user/device/${mapToken}/live-json` : '');
    const historyUrl = api.history || (mapToken ? `${baseUrl}/user/device/${mapToken}/history-json` : '');
    const alertsUrl = cfg.alertsUrl || api.alerts || (mapToken ? `${baseUrl}/user/device/${mapToken}/alerts-json` : '');
    const reverseGeocodeUrl = api.reverseGeocode || (mapToken ? `${baseUrl}/user/device/${mapToken}/reverse-geocode` : '');
    const geofencesUrl = api.geofences || (mapToken ? `${baseUrl}/user/device/${mapToken}/geofences-json` : '');
    const geofencesSaveUrl = api.geofencesSave || (mapToken ? `${baseUrl}/user/device/${mapToken}/geofences-save` : '');
    const geofenceDestroyBase = api.geofenceDestroy || `${baseUrl}/user/geofence`;
    const geofenceUpdateBase = api.geofenceUpdate || `${baseUrl}/user/geofence`;
    const accessDeniedRedirect = api.accessDeniedRedirect || `${baseUrl}/user/devices`;
    const overSpeedLimit = cfg.overSpeedLimit || 80;
    const lowBatteryThreshold = cfg.lowBatteryThreshold || 20;
    const movingSpeedKmh = cfg.movingSpeedKmh
        ?? cfg.mapRendering?.connectivity?.moving_speed_kmh ?? 5;
    const idleSpeedKmh = cfg.idleSpeedKmh ?? 0.5;
    const parkedIconSpeedKmh = cfg.parkedIconSpeedKmh ?? 0.1;
    const motionDetectKm = cfg.motionDetectKm ?? 0.004;
    const delayedMinSeconds = cfg.delayedMinSeconds
        ?? cfg.mapRendering?.connectivity?.delayed_min_seconds ?? 120;
    const staleMinSeconds = cfg.staleMinSeconds
        ?? cfg.mapRendering?.connectivity?.stale_min_seconds ?? 600;
    const offlineSeconds = cfg.offlineSeconds
        ?? cfg.mapRendering?.connectivity?.offline_seconds ?? 1800;
    /** @deprecated use delayedMinSeconds */
    const recentSeconds = cfg.recentSeconds ?? delayedMinSeconds;
    const delayedTimeoutMs = delayedMinSeconds * 1000;
    const staleTimeoutMs = staleMinSeconds * 1000;
    const offlineTimeoutMs = offlineSeconds * 1000;
    const onlineTimeoutMs = offlineTimeoutMs;
    const debugGps = cfg.debugGps === true
        || (typeof URLSearchParams !== 'undefined'
            && new URLSearchParams(window.location.search).get('debug_gps') === '1');

    function debugGpsLog(...args) {
        if (debugGps) console.log('[GPS Debug]', ...args);
    }

    function formatGsmDisplay(value) {
        if (value == null || value === '') return dash();
        const n = parseInt(value, 10);
        if (Number.isNaN(n)) return dash();
        if (n === 0) return mi('gsmNoSignal', 'No signal');
        if (n <= 5) return `${n}/5`;
        if (n <= 33) return `${n}% (${mi('gsmWeak', 'Weak')})`;
        if (n <= 66) return `${n}% (${mi('gsmGood', 'Good')})`;
        return `${n}% (${mi('gsmExcellent', 'Excellent')})`;
    }

    let map, drawingManager, trafficLayer, customInfoWindow;
    let geofences = [];
    let polylines = [];
    let realtimePolylines = [];
    let markers = [];
    let currentPositionMarker = null;
    let fleetRenderer = null;
    let startMarker = null;
    let currentDrawing = null;
    let playbackPoints = [];
    let playbackTimer, playbackIndex = 0, isPlaying = false, playbackSpeed = 1;
    let playbackAnimFrame = null;
    let playbackActive = false;
    /** When true, marker/pulse color uses fix-time motion, not wall-clock offline tier. */
    let routeScrubUsesMotion = false;
    let followVehicle = true;
    let flatpickrFrom;
    let flatpickrTo;
    let historyData = [];
    let lastRealtimePoint = null;
    let lastTelemetry = null;
    let pollTimer = null;
    let alertState = {};
    let offlineTimer = null;
    let heatmapLayer = null;
    let stopMarkers = [];
    let showsStops = false;
    let nightModeOn = false;
    let addressFetchTimer = null;
    let lastAddressKey = '';
    const navAlertStore = [];
    const NAV_ALERT_LIMIT = 12;
    let lastAlertEventId = 0;
    const seenAlertIds = new Set();
    let alertsBootstrapped = false;
    let unreadAlertCount = 0;

    const MAP_BOOT_MAX = 4;
    const MAP_READY_TIMEOUT_MS = 20000;
    const MAP_HEALTH_INTERVAL_MS = 30000;
    const MAP_HEALTH_MISS_MAX = 2;
    let mapReady = false;
    let mapBootAttempts = 0;
    let mapBootRunning = false;
    let mapControlsBound = false;
    let mapDataStarted = false;
    let mapHealthTimer = null;
    let mapHealthMisses = 0;
    let mapResizeObserver = null;
    let lastMapBootError = '';
    let lastAppliedPositionKey = '';
    let vehiclePopupPinned = false;
    let livePopupAddress = '';
    let livePollTimer = null;
    let alertsPollTimer = null;
    let markerAnimationFrame = null;
    let animFromPos = null;
    let animFromHeading = 0;
    let animStartTime = 0;
    let endMarker = null;
    let eventMarkers = [];
    let routeGlowPolylines = [];
    let showsEventMarkers = cfg.showEventMarkers !== false;
    let routeGlowEnabled = cfg.routeGlowEnabled !== false;
    const mediumSpeedKmh = cfg.mediumSpeedKmh ?? 60;
    const ANIM_DURATION_MS = cfg.markerAnimMs ?? 1200;

    function ensureFleetRenderer() {
        if (!map) return null;
        if (!window.FleetMapRenderer) {
            console.warn('[device-map] FleetMapRenderer module missing');
            return null;
        }
        if (!fleetRenderer) {
            fleetRenderer = new window.FleetMapRenderer({
                googleMaps: google,
                getIdentity: markerIdentityForPoint,
                getState: vehicleStateKey,
                getColor: vehicleStateColor,
                getVehicleType: resolveVehicleType,
                shouldShowDirection: shouldShowVehicleDirection,
                isHidden: hasNoGpsData,
                speedToColor,
                mediumSpeedKmh,
                overSpeedLimit,
                routeGlowEnabled,
                getNightMode: () => nightModeOn,
                animDurationMs: ANIM_DURATION_MS,
                startIconUrl: cfg.startIcon,
                endIconUrl: cfg.endIcon,
                onVehicleClick: () => {
                    if (lastTelemetry) openLiveVehiclePopup(lastTelemetry);
                },
            });
            fleetRenderer.attachMap(map);
            fleetRenderer.setFollowVehicle(followVehicle);
        }
        currentPositionMarker = fleetRenderer.getMarker();
        return fleetRenderer;
    }

    function updateVehiclePulseOverlay(point) {
        ensureFleetRenderer()?.syncPulse(point);
    }

    const NIGHT_MAP_STYLES = [
        { elementType: 'geometry', stylers: [{ color: '#0f172a' }] },
        { elementType: 'labels.text.fill', stylers: [{ color: '#94a3b8' }] },
        { elementType: 'labels.text.stroke', stylers: [{ color: '#0f172a' }] },
        { featureType: 'administrative', elementType: 'geometry', stylers: [{ color: '#1e293b' }] },
        { featureType: 'poi', elementType: 'labels.text.fill', stylers: [{ color: '#64748b' }] },
        { featureType: 'poi.park', elementType: 'geometry', stylers: [{ color: '#1a2e1a' }] },
        { featureType: 'road', elementType: 'geometry', stylers: [{ color: '#334155' }] },
        { featureType: 'road', elementType: 'geometry.stroke', stylers: [{ color: '#1e293b' }] },
        { featureType: 'road.highway', elementType: 'geometry', stylers: [{ color: '#475569' }] },
        { featureType: 'road.highway', elementType: 'geometry.stroke', stylers: [{ color: '#334155' }] },
        { featureType: 'road.arterial', elementType: 'geometry', stylers: [{ color: '#3f4f63' }] },
        { featureType: 'road.local', elementType: 'geometry', stylers: [{ color: '#2d3748' }] },
        { featureType: 'transit', elementType: 'geometry', stylers: [{ color: '#1e293b' }] },
        { featureType: 'water', elementType: 'geometry', stylers: [{ color: '#0c1929' }] },
        { featureType: 'water', elementType: 'labels.text.fill', stylers: [{ color: '#475569' }] },
    ];

    function parseRouteTimestampMs(ts) {
        if (window.AppDateTime?.parseTimestampMs) {
            return window.AppDateTime.parseTimestampMs(ts);
        }
        if (!ts) return null;
        const parsed = Date.parse(String(ts));
        return Number.isNaN(parsed) ? null : parsed;
    }

    function resolvePointTimestamp(raw) {
        for (const key of ['recorded_at', 'time', 'timestamp', 'fixtime', 'deviceTime', 'device_time']) {
            const value = raw[key];
            if (value != null && String(value).trim() !== '') {
                return String(value).trim();
            }
        }
        return null;
    }

    function normalizePoint(raw) {
        if (!raw) return null;
        const lat = parseFloat(raw.lat ?? raw.latitude ?? 0);
        const lng = parseFloat(raw.lng ?? raw.longitude ?? 0);
        if (!lat || !lng || Number.isNaN(lat) || Number.isNaN(lng)) return null;

        const ts = resolvePointTimestamp(raw);
        const tsMs = parseRouteTimestampMs(ts);

        return {
            lat,
            lng,
            speed: parseFloat(raw.speed ?? 0),
            heading: parseFloat(raw.heading ?? 0),
            battery: raw.battery ?? raw.battery_level ?? null,
            ignition: raw.ignition === true || raw.ignition === 1 || raw.ignition === '1',
            gsm_signal: raw.gsm_signal ?? null,
            gps_signal: raw.gps_signal ?? raw.gps ?? null,
            satellites: raw.satellites ?? null,
            fuel: raw.fuel ?? raw.fuel_level ?? null,
            odometer: raw.odometer ?? null,
            power_cut: raw.power_cut === true || raw.power_cut === 1,
            panic: raw.panic === true || raw.panic === 1,
            recorded_at: ts,
            recorded_at_ms: tsMs,
            position_id: raw.position_id != null ? Number(raw.position_id) : null,
            status: raw.status ?? null,
            status_key: raw.status_key ?? null,
            connectivity_tier: raw.connectivity_tier ?? null,
            last_known_status: raw.last_known_status ?? null,
            last_known_status_key: raw.last_known_status_key ?? null,
            last_known_speed: raw.last_known_speed != null ? parseFloat(raw.last_known_speed) : null,
            last_known_ignition: raw.last_known_ignition === true || raw.last_known_ignition === 1 || raw.last_known_ignition === '1'
                ? true
                : (raw.last_known_ignition === false || raw.last_known_ignition === 0 || raw.last_known_ignition === '0'
                    ? false
                    : null),
            is_online: raw.is_online,
        };
    }

    function sortHistoryPoints(data) {
        return [...data].sort((a, b) => {
            const ta = a.recorded_at_ms ?? parseRouteTimestampMs(a.recorded_at) ?? 0;
            const tb = b.recorded_at_ms ?? parseRouteTimestampMs(b.recorded_at) ?? 0;
            if (ta !== tb) return ta - tb;
            return (a.position_id || 0) - (b.position_id || 0);
        });
    }

    function routeTimeBounds(data) {
        let minMs = null;
        let maxMs = null;
        let startTs = null;
        let endTs = null;

        for (const point of data) {
            const ms = point.recorded_at_ms ?? parseRouteTimestampMs(point.recorded_at);
            if (ms == null) continue;
            if (minMs == null || ms < minMs) {
                minMs = ms;
                startTs = point.recorded_at;
            }
            if (maxMs == null || ms > maxMs) {
                maxMs = ms;
                endTs = point.recorded_at;
            }
        }

        const totalSec = minMs != null && maxMs != null && maxMs > minMs
            ? (maxMs - minMs) / 1000
            : 0;

        return { startTs, endTs, totalSec, minMs, maxMs };
    }

    function sumSegmentDurationSeconds(data) {
        let total = 0;
        for (let i = 1; i < data.length; i++) {
            const t0 = data[i - 1].recorded_at_ms ?? parseRouteTimestampMs(data[i - 1].recorded_at);
            const t1 = data[i].recorded_at_ms ?? parseRouteTimestampMs(data[i].recorded_at);
            if (t0 != null && t1 != null && t1 > t0) {
                total += (t1 - t0) / 1000;
            }
        }
        return total;
    }

    /** Estimate trip duration from distance/speed when timestamps are missing or identical. */
    function estimateDurationFromMotion(data) {
        let totalSec = 0;
        for (let i = 1; i < data.length; i++) {
            const a = data[i - 1];
            const b = data[i];
            const distKm = haversineDistance(a.lat, a.lng, b.lat, b.lng);
            if (distKm < 0.00001) continue;
            const spd = Math.max(parseFloat(a.speed || 0), parseFloat(b.speed || 0), movingSpeedKmh);
            totalSec += (distKm / spd) * 3600;
        }
        return totalSec;
    }

    function enrichRouteStats(data, stats) {
        const bounds = routeTimeBounds(data);
        let startTs = bounds.startTs ?? data[0]?.recorded_at ?? null;
        let endTs = bounds.endTs ?? data[data.length - 1]?.recorded_at ?? null;
        let totalSec = bounds.totalSec > 0 ? bounds.totalSec : stats.totalSec;

        if (totalSec <= 0) {
            const segmentSec = sumSegmentDurationSeconds(data);
            if (segmentSec > 0) totalSec = segmentSec;
        }

        if (totalSec <= 0 && stats.dist > 0 && data.length >= 2) {
            totalSec = estimateDurationFromMotion(data);
        }

        let avgSpeed = 0;
        if (totalSec > 0 && stats.dist > 0) {
            avgSpeed = stats.dist / (totalSec / 3600);
        } else if (stats.movingSec > 0 && stats.dist > 0) {
            avgSpeed = stats.dist / (stats.movingSec / 3600);
        } else if (stats.dist > 0 && data.length >= 2) {
            const speeds = data.map((p) => parseFloat(p.speed || 0)).filter((s) => s > 0);
            if (speeds.length) {
                avgSpeed = speeds.reduce((a, b) => a + b, 0) / speeds.length;
            }
        }

        if (stats.dist > 0 && totalSec <= 0) {
            debugGpsLog('route summary: distance without duration', {
                points: data.length,
                dist: stats.dist,
                first: data[0]?.recorded_at,
                last: data[data.length - 1]?.recorded_at,
                bounds,
            });
        }

        return {
            ...stats,
            startTs,
            endTs,
            totalSec,
            avgSpeed,
        };
    }

    function positionKey(point) {
        if (!point) {
            return '';
        }
        if (point.position_id) {
            return `id:${point.position_id}`;
        }
        return `${point.recorded_at || ''}|${point.lat}|${point.lng}`;
    }

    function hasSignificantPositionChange(point, prev) {
        if (!prev) {
            return true;
        }
        if (point.position_id && prev.position_id && point.position_id !== prev.position_id) {
            return true;
        }
        if ((point.recorded_at || '') !== (prev.recorded_at || '')) {
            return true;
        }
        return haversineDistance(prev.lat, prev.lng, point.lat, point.lng) * 1000 >= 2;
    }

    function markerIdentityFromConfig(point) {
        return markerIdentityForPoint(point || {});
    }

    function formatIgnitionLabel(point) {
        if (point?.ignition == null) return dash();
        return point.ignition ? mi('ignitionOn', 'ON') : mi('ignitionOff', 'OFF');
    }

    function buildLiveVehiclePopupHtml(point) {
        const identity = markerIdentityForPoint(point);
        const status = resolveVehicleStatus(point);
        const speed = parseFloat(point.speed || 0).toFixed(0);
        const updated = point.recorded_at
            ? (window.AppDateTime?.formatDateTime(point.recorded_at) ?? new Date(point.recorded_at).toLocaleString())
            : dash();

        const plateRow = identity.plate
            ? `<div class="vehicle-map-popup__row"><strong>${mi('vehicleNumber', 'Vehicle Number')}</strong><span>${escapeHtml(identity.plate)}</span></div>`
            : '';

        return `<div class="vehicle-map-popup">
            <div class="vehicle-map-popup__head">
                <div>
                    <div class="vehicle-map-popup__title">${escapeHtml(identity.title)}</div>
                </div>
                <button type="button" class="vehicle-map-popup__close" id="liveVehiclePopupClose" aria-label="Close">&times;</button>
            </div>
            <div class="vehicle-map-popup__body">
                ${plateRow}
                <div class="vehicle-map-popup__row vehicle-map-popup__row--status"><strong>${mi('currentStatus', 'Status')}</strong><span class="map-status-chip ${status.cls}">${escapeHtml(status.label)}</span></div>
                <div class="vehicle-map-popup__row"><strong>${mi('speed', 'Speed')}</strong><span>${speed} ${mi('kmh', 'km/h')}</span></div>
                <div class="vehicle-map-popup__row"><strong>${mi('ignition', 'Ignition')}</strong><span>${escapeHtml(formatIgnitionLabel(point))}</span></div>
                <div class="vehicle-map-popup__row"><strong>${mi('updated', 'Updated')}</strong><span>${escapeHtml(updated)}</span></div>
            </div>
        </div>`;
    }

    function updateRouteSummaryLive(point) {
        if (!point) return;

        const status = resolveVehicleStatus(point);
        const identity = markerIdentityForPoint(point);
        const speedLabel = `${parseFloat(point.speed || 0).toFixed(0)} ${mi('kmh', 'km/h')}`;
        const updated = formatRouteTimestamp(point.recorded_at);

        setText('rssCollapsedName', identity.title || 'Vehicle');
        const collapsedPlate = document.getElementById('rssCollapsedPlate');
        if (collapsedPlate) {
            if (identity.plate) {
                collapsedPlate.textContent = identity.plate;
                collapsedPlate.hidden = false;
            } else {
                collapsedPlate.hidden = true;
            }
        }

        const collapsedStatus = document.getElementById('rssCollapsedStatus');
        if (collapsedStatus) {
            collapsedStatus.innerHTML = `<span class="map-status-chip ${status.cls}">${escapeHtml(status.label)}</span>`;
        }
        setText('rssCollapsedSpeed', speedLabel);

        const statusEl = document.getElementById('rssCurrentStatus');
        if (statusEl) {
            statusEl.innerHTML = `<span class="map-status-chip ${status.cls}">${escapeHtml(status.label)}</span>`;
        }

        setText('rssCurrentSpeed', speedLabel);
        setText('rssIgnition', formatIgnitionLabel(point));
        setText('rssLastUpdate', updated);
    }

    function updateLiveVehiclePopup(point) {
        if (!vehiclePopupPinned || !customInfoWindow || !currentPositionMarker || !point) {
            return;
        }
        customInfoWindow.setContent(buildLiveVehiclePopupHtml(point));
        const pos = currentPositionMarker.getPosition();
        if (pos) {
            customInfoWindow.setPosition(pos);
        }
        if (!customInfoWindow.getMap()) {
            customInfoWindow.open(map);
        }
    }

    function closeLiveVehiclePopup() {
        vehiclePopupPinned = false;
        customInfoWindow?.close();
        if (lastTelemetry) {
            updateVehiclePulseOverlay(lastTelemetry);
        }
    }

    function openLiveVehiclePopup(point) {
        if (!customInfoWindow || !currentPositionMarker || !point) {
            return;
        }
        vehiclePopupPinned = true;
        updateLiveVehiclePopup(point);
        updateVehiclePulseOverlay(point);
    }

    function normalizeResponse(j) {
        if (!j) return [];
        if (Array.isArray(j)) return j;
        if (Array.isArray(j.locations)) return j.locations;
        if (Array.isArray(j.data)) return j.data;
        if (j.lat && j.lng) return [j];
        return [];
    }

    function haversineDistance(lat1, lng1, lat2, lng2) {
        const R = 6371, toRad = Math.PI / 180;
        const dLat = (lat2 - lat1) * toRad, dLng = (lng2 - lng1) * toRad;
        const a = Math.sin(dLat / 2) ** 2 + Math.cos(lat1 * toRad) * Math.cos(lat2 * toRad) * Math.sin(dLng / 2) ** 2;
        return 2 * R * Math.asin(Math.sqrt(a));
    }

    function bearingFromPoints(from, to) {
        const lat1 = from.lat * Math.PI / 180;
        const lat2 = to.lat * Math.PI / 180;
        const dLng = (to.lng - from.lng) * Math.PI / 180;
        const y = Math.sin(dLng) * Math.cos(lat2);
        const x = Math.cos(lat1) * Math.sin(lat2) - Math.sin(lat1) * Math.cos(lat2) * Math.cos(dLng);
        return (Math.atan2(y, x) * 180 / Math.PI + 360) % 360;
    }

    function sleep(ms) {
        return new Promise((resolve) => setTimeout(resolve, ms));
    }

    function speedToColor(speed) {
        const spd = parseFloat(speed || 0);
        if (spd <= 0) return '#64748b';
        if (spd <= mediumSpeedKmh) return '#22c55e';
        if (spd <= overSpeedLimit) return '#eab308';
        return '#ef4444';
    }

    /** Age of last GPS fix in milliseconds, or null when unknown. */
    function gpsAgeMs(point) {
        if (!point?.recorded_at) {
            return null;
        }
        return Date.now() - new Date(point.recorded_at).getTime();
    }

    function connectivityTier(point) {
        const age = gpsAgeMs(point);
        if (age != null) {
            const secs = age / 1000;
            if (secs > offlineSeconds) {
                return 'offline';
            }
            if (secs >= staleMinSeconds) {
                return 'stale';
            }
            if (secs >= delayedMinSeconds) {
                return 'delayed';
            }
            return 'live';
        }
        if (point?.connectivity_tier) {
            return point.connectivity_tier;
        }
        return 'offline';
    }

    /** True when communication exceeded offline timeout (> 30 min). */
    function isVehicleOffline(point) {
        return connectivityTier(point) === 'offline';
    }

    function isVehicleStale(point) {
        return connectivityTier(point) === 'stale';
    }

    function isVehicleDelayed(point) {
        return connectivityTier(point) === 'delayed';
    }

    function isVehicleLive(point) {
        return connectivityTier(point) === 'live';
    }

    /** True when there is no GPS fix timestamp. */
    function hasNoGpsData(point) {
        return !point || !point.recorded_at;
    }

    /** @deprecated use isVehicleOffline */
    function isConnectivityStale(point) {
        return isVehicleStale(point);
    }

    /** @deprecated use hasNoGpsData — kept for minimal diff in call sites */
    function isPointOffline(point) {
        return hasNoGpsData(point);
    }

    function statusLabelForKey(key, point) {
        const labels = {
            running: mi('statusRunning', 'Running'),
            stopped: mi('statusStopped', 'Stopped'),
            parked: mi('statusParked', 'Parked'),
            moving: mi('statusMoving', 'Moving'),
            idle: mi('statusIdle', 'Idle'),
            ignition_off: mi('statusParked', 'Parked'),
            offline: mi('statusOffline', 'Offline'),
            delayed: mi('statusDelayed', 'Delayed'),
            stale: mi('statusStale', 'Weak Signal / Stale'),
            alert: point?.panic
                ? mi('statusSos', 'SOS')
                : (point?.power_cut ? mi('statusPowerCut', 'Power cut') : mi('statusOverspeed', 'Overspeed')),
            blocked: mi('statusOffline', 'Offline'),
        };

        return labels[key] || labels.stopped;
    }

    function statusClassForKey(key) {
        const k = String(key || '').toLowerCase();
        if (k === 'idle') return 'map-status-chip--stopped';
        if (k === 'ignition_off') return 'map-status-chip--parked';
        if (k === 'blocked') return 'map-status-chip--offline';
        return 'map-status-chip--' + k;
    }

    function vehicleStateKeyFromMetrics(point) {
        if (point.power_cut || point.panic) {
            return 'alert';
        }
        const speed = parseFloat(point.speed || 0);
        const movingThreshold = typeof movingSpeedKmh === 'number' ? movingSpeedKmh : 5;
        if (point.ignition === true) {
            return speed > movingThreshold ? 'running' : 'stopped';
        }
        return speed > movingThreshold ? 'moving' : 'parked';
    }

    function normalizeVehicleStateKey(key) {
        if (!key) return key;
        const k = String(key).toLowerCase();
        if (k === 'idle') return 'stopped';
        if (k === 'ignition_off') return 'parked';
        return k;
    }

    function vehicleStateKey(point) {
        if (hasNoGpsData(point)) {
            return 'offline';
        }

        // Playback / historical scrub: motion at that GPS fix, not "offline" due to age.
        if (playbackActive || routeScrubUsesMotion) {
            if (point?.status_key) {
                return normalizeVehicleStateKey(point.status_key);
            }
            return vehicleStateKeyFromMetrics(point);
        }

        const tier = connectivityTier(point);
        if (tier === 'offline') {
            return 'offline';
        }
        if (tier === 'stale') {
            return 'stale';
        }
        if (tier === 'delayed') {
            return 'delayed';
        }

        if (point?.status_key) {
            return normalizeVehicleStateKey(point.status_key);
        }

        return vehicleStateKeyFromMetrics(point);
    }

    function lastKnownMotionKey(point) {
        if (point?.last_known_status_key) {
            return normalizeVehicleStateKey(point.last_known_status_key);
        }
        return vehicleStateKeyFromMetrics(point);
    }

    function lastKnownMotionLabel(point) {
        if (point?.last_known_status) {
            return point.last_known_status;
        }
        return statusLabelForKey(lastKnownMotionKey(point), point);
    }

    function lastKnownSpeed(point) {
        if (point?.last_known_speed != null && !Number.isNaN(parseFloat(point.last_known_speed))) {
            return parseFloat(point.last_known_speed);
        }
        return parseFloat(point?.speed || 0);
    }

    function lastKnownIgnition(point) {
        if (point?.last_known_ignition != null) {
            return point.last_known_ignition === true;
        }
        return point?.ignition === true;
    }

    function formatLastSeen(point) {
        if (!point?.recorded_at) {
            return dash();
        }
        return window.AppDateTime?.formatDateTime
            ? window.AppDateTime.formatDateTime(point.recorded_at)
            : new Date(point.recorded_at).toLocaleString();
    }

    function vehicleStateColor(state) {
        return window.VehicleMarker?.stateColor(state) || '#3b82f6';
    }

    function resolveVehicleType(source) {
        const raw = String(
            source?.vehicle_type || source?.vehicleType || cfg.vehicleType || 'car'
        ).toLowerCase().trim();
        const known = ['car', 'suv', 'truck', 'van', 'bus', 'pickup', 'motorcycle', 'trailer', 'other'];
        if (known.includes(raw)) {
            return raw;
        }
        if (raw.includes('motor')) return 'motorcycle';
        if (raw.includes('truck')) return 'truck';
        if (raw.includes('bus')) return 'bus';
        if (raw.includes('van')) return 'van';
        if (raw.includes('pickup')) return 'pickup';
        if (raw.includes('trailer')) return 'trailer';
        if (raw.includes('suv')) return 'suv';
        if (raw.includes('equip') || raw.includes('machin')) return 'other';
        return 'car';
    }

    function shouldShowVehicleDirection(point, state) {
        if (state === 'offline' || state === 'ignition_off' || state === 'parked') {
            return false;
        }
        const heading = parseFloat(point?.heading);
        return Number.isFinite(heading);
    }

    function truncateMarkerText(text, maxLen) {
        const t = String(text || '').trim();
        if (!t) {
            return '';
        }
        const limit = maxLen || 16;
        return t.length > limit ? t.slice(0, limit - 1) + '…' : t;
    }

    function markerIdentityForPoint(point) {
        const rawName = String(point?.vehicle_name || cfg.vehicleName || '').trim();
        const rawPlate = String(
            point?.markerPlate || point?.vehicle_number || cfg.markerPlate || cfg.vehicleNumber || ''
        ).trim();

        let title = rawName || truncateMarkerText(
            point?.markerTitle || point?.markerLabel || cfg.markerTitle || cfg.markerLabel,
            22
        );
        let plate = null;

        if (!title && rawPlate) {
            title = truncateMarkerText(rawPlate, 22);
        } else if (rawName && rawPlate) {
            plate = rawPlate;
        } else if (title && rawPlate && title !== rawPlate) {
            plate = rawPlate;
        }

        if (!title) {
            title = truncateMarkerText(cfg.mapDisplayTitle, 22) || 'Vehicle';
        }

        return { title, plate };
    }

    function vehicleIcon(point) {
        ensureFleetRenderer();
        return fleetRenderer?.iconBuilder?.iconFor(point, {
            showLiveBadge: !playbackActive,
        }) || null;
    }

    function routeMarkerIcon(type) {
        const url = type === 'start'
            ? (cfg.startIcon || '/images/map/marker-start.svg')
            : (cfg.endIcon || '/images/map/marker-end.svg');
        return {
            url,
            scaledSize: new google.maps.Size(36, 36),
            anchor: new google.maps.Point(18, 18),
        };
    }

    function eventMarkerIcon(type) {
        const palette = {
            overspeed: '#ef4444',
            stop: '#3b82f6',
            harsh_brake: '#f97316',
            harsh_accel: '#eab308',
            low_battery: '#a855f7',
            fuel: '#14b8a6',
        };
        const color = palette[type] || '#64748b';
        const svg = `<svg xmlns="http://www.w3.org/2000/svg" width="32" height="32" viewBox="0 0 32 32">
            <circle cx="16" cy="16" r="14" fill="${color}" stroke="#fff" stroke-width="2.5"/>
            <circle cx="16" cy="16" r="5" fill="#fff"/>
        </svg>`;
        return {
            url: 'data:image/svg+xml;charset=UTF-8,' + encodeURIComponent(svg),
            scaledSize: new google.maps.Size(28, 28),
            anchor: new google.maps.Point(14, 14),
        };
    }

    function interpolateHeading(from, to, t) {
        let delta = ((to - from + 540) % 360) - 180;
        return (from + delta * t + 360) % 360;
    }

    function setText(id, value) {
        const el = document.getElementById(id);
        if (el) el.textContent = value;
    }

    function formatDurationLong(seconds) {
        const s = Math.max(0, Math.floor(seconds));
        const h = Math.floor(s / 3600);
        const m = Math.floor((s % 3600) / 60);
        const sec = s % 60;
        if (h > 0) return `${h}h ${m}m`;
        if (m > 0) return `${m}m`;
        return `${sec}s`;
    }

    function effectiveSpeedKmh(point, previous) {
        const reported = parseFloat(point?.speed ?? 0);
        if (!previous || !point?.recorded_at || !previous.recorded_at) {
            return reported;
        }

        const distKm = haversineDistance(previous.lat, previous.lng, point.lat, point.lng);
        const elapsedMs = (parseRouteTimestampMs(point.recorded_at) ?? 0) - (parseRouteTimestampMs(previous.recorded_at) ?? 0);
        if (elapsedMs <= 0) {
            return reported;
        }

        const computed = (distKm / (elapsedMs / 3600000));
        if (!Number.isFinite(computed) || computed < 0) {
            return reported;
        }

        // Traccar often reports speed=0 while coordinates change — trust GPS motion.
        if (distKm >= motionDetectKm && (reported < idleSpeedKmh || computed > reported * 1.5)) {
            return Math.max(reported, computed);
        }

        return reported;
    }

    function enrichPointWithMotion(point, previous) {
        if (!previous) {
            return point;
        }

        const distKm = haversineDistance(previous.lat, previous.lng, point.lat, point.lng);
        const speed = effectiveSpeedKmh(point, previous);
        let heading = parseFloat(point.heading ?? 0);

        if (distKm >= motionDetectKm) {
            const travelBearing = bearingFromPoints(
                { lat: previous.lat, lng: previous.lng },
                { lat: point.lat, lng: point.lng }
            );
            if (!Number.isFinite(heading)) {
                heading = travelBearing;
            }
        }

        if (speed === point.speed && heading === parseFloat(point.heading ?? 0)) {
            return point;
        }

        return { ...point, speed, heading };
    }

    function resolveVehicleStatus(point) {
        const key = vehicleStateKey(point);
        const label = (point?.status && String(point.status).trim() && ['offline', 'delayed'].includes(key))
            ? String(point.status).trim()
            : statusLabelForKey(key, point);

        return {
            key,
            label,
            cls: statusClassForKey(key),
            tier: connectivityTier(point),
        };
    }

    function getVehicleStatusKey(point) {
        return resolveVehicleStatus(point).key;
    }

    function setMapHudCollapsed(collapsed, persist) {
        const hud = document.getElementById('mapHud');
        const toggle = document.getElementById('mapHudToggle');
        if (!hud) return;
        hud.classList.toggle('is-collapsed', collapsed);
        toggle?.setAttribute('aria-expanded', collapsed ? 'false' : 'true');
        if (persist) {
            try {
                localStorage.setItem('mapHudCollapsed.' + deviceId, collapsed ? '1' : '0');
            } catch (e) { /* ignore */ }
        }
    }

    function initMapBackNavigation() {
        const backUrl = cfg.backUrl || document.querySelector('.sidebar-back-link')?.href;
        if (!backUrl) {
            return;
        }

        try {
            history.replaceState({ fegMapPage: true }, '', location.href);
            history.pushState({ fegMapGuard: true }, '', location.href);
        } catch (e) { /* ignore */ }

        window.addEventListener('popstate', () => {
            if (/\/device\/[^/]+\/map/i.test(window.location.pathname)) {
                window.location.replace(backUrl);
            }
        });
    }

    function setMapLivePanelExpanded(expanded, persist) {
        const panel = document.getElementById('mapLivePanel');
        const toggle = document.getElementById('mapLivePanelToggle');
        if (!panel) return;
        panel.classList.toggle('is-expanded', expanded);
        panel.classList.toggle('is-collapsed', !expanded);
        toggle?.setAttribute('aria-expanded', expanded ? 'true' : 'false');
        if (persist) {
            try {
                localStorage.setItem('mapLivePanelExpanded.' + deviceId, expanded ? '1' : '0');
            } catch (e) { /* ignore */ }
        }
    }

    function initMapLivePanelToggle() {
        const panel = document.getElementById('mapLivePanel');
        const toggle = document.getElementById('mapLivePanelToggle');
        const handle = document.getElementById('mapLivePanelHandle');
        const header = panel?.querySelector('.map-live-panel__header-main');
        if (!panel) return;

        let startExpanded = false;
        try {
            startExpanded = localStorage.getItem('mapLivePanelExpanded.' + deviceId) === '1';
        } catch (e) { /* ignore */ }
        setMapLivePanelExpanded(startExpanded, false);

        const flip = (e) => {
            e?.preventDefault();
            e?.stopPropagation();
            setMapLivePanelExpanded(!panel.classList.contains('is-expanded'), true);
        };

        toggle?.addEventListener('click', flip);
        handle?.addEventListener('click', flip);
        header?.addEventListener('click', flip);

        let dragStartY = null;
        handle?.addEventListener('pointerdown', (e) => {
            dragStartY = e.clientY;
            handle.setPointerCapture?.(e.pointerId);
        });
        handle?.addEventListener('pointerup', (e) => {
            if (dragStartY == null) return;
            const delta = dragStartY - e.clientY;
            dragStartY = null;
            if (Math.abs(delta) < 24) return;
            setMapLivePanelExpanded(delta > 0, true);
        });
    }

    function initMapHudToggle() {
        const toggle = document.getElementById('mapHudToggle');
        if (!toggle) return;
        let startCollapsed = false;
        try {
            startCollapsed = localStorage.getItem('mapHudCollapsed.' + deviceId) === '1';
        } catch (e) { /* ignore */ }
        setMapHudCollapsed(startCollapsed, false);
        toggle.addEventListener('click', (e) => {
            e.preventDefault();
            e.stopPropagation();
            const hud = document.getElementById('mapHud');
            setMapHudCollapsed(!hud?.classList.contains('is-collapsed'), true);
        });
    }

    function updateMapHud(point) {
        if (!point) return;
        const status = resolveVehicleStatus(point);
        const tier = status.tier;
        const isRecent = tier === 'live';
        const isDelayed = tier === 'delayed';
        const isOffline = tier === 'offline';
        const speed = isRecent ? parseFloat(point.speed || 0) : lastKnownSpeed(point);
        const speedText = speed.toFixed(0) + ' ' + mi('kmh', 'km/h');
        const lastSeenText = formatLastSeen(point);

        setText('hudSpeed', speedText);
        setText('hudMiniSpeed', isRecent ? speedText : lastSeenText);
        setText('hudHeading', (point.heading ?? 0) + '°');
        const updatedText = point.recorded_at
            ? (window.AppDateTime?.formatTime(point.recorded_at) ?? new Date(point.recorded_at).toLocaleTimeString())
            : dash();
        setText('hudUpdated', updatedText);

        const ignitionVal = isRecent ? point.ignition : lastKnownIgnition(point);
        const ignitionText = ignitionVal != null
            ? (ignitionVal ? mi('ignitionOn', 'ON') : mi('ignitionOff', 'OFF'))
            : dash();
        setText('hudIgnition', ignitionText);

        const gpsText = point.gps_signal != null
            ? point.gps_signal + '%'
            : dash();
        setText('hudGps', gpsText);

        setText('hudGsm', formatGsmDisplay(point.gsm_signal));

        setText('hudSatellites', point.satellites != null ? String(point.satellites) : dash());

        const battery = point.battery != null ? parseInt(point.battery, 10) : null;
        setText('hudBattery', battery != null ? battery + '%' : dash());

        const statusChipHtml = `<span class="map-status-chip ${status.cls}">${escapeHtml(status.label)}</span>`;
        const miniStatus = document.getElementById('hudMiniStatus');
        if (miniStatus) miniStatus.innerHTML = statusChipHtml;

        const dot = document.getElementById('hudStatusDot');
        if (dot) {
            dot.className = 'map-hud__status-dot is-' + status.key;
        }

        const delayedSection = document.getElementById('hudDelayedInfo');
        if (delayedSection) {
            delayedSection.hidden = !isDelayed;
        }
        if (isDelayed) {
            setText('hudDelayedLastSeen', lastSeenText);
        }

        const lastKnownSection = document.getElementById('hudLastKnown');
        if (lastKnownSection) {
            lastKnownSection.hidden = !isOffline;
        }
        if (isOffline) {
            setText('hudLastSeen', lastSeenText);
            setText('hudLastKnownStatus', lastKnownMotionLabel(point));
            setText('hudLastKnownSpeed', speedText);
            const lkIgnition = lastKnownIgnition(point);
            setText(
                'hudLastKnownIgnition',
                lkIgnition != null
                    ? (lkIgnition ? mi('ignitionOn', 'ON') : mi('ignitionOff', 'OFF'))
                    : dash()
            );
        }

        const telemetryGrid = document.getElementById('hudTelemetryGrid');
        if (telemetryGrid) {
            telemetryGrid.hidden = !isRecent;
        }

        scheduleAddressLookup(point.lat, point.lng);
    }

    function scheduleAddressLookup(lat, lng) {
        const key = lat.toFixed(4) + ',' + lng.toFixed(4);
        if (key === lastAddressKey) return;
        clearTimeout(addressFetchTimer);
        addressFetchTimer = setTimeout(() => fetchAddressForHud(lat, lng, key), 1200);
    }

    async function fetchAddressForHud(lat, lng, key) {
        try {
            const res = await fetch(`${reverseGeocodeUrl}?lat=${lat}&lng=${lng}`);
            if (!res.ok) return;
            const data = await res.json();
            lastAddressKey = key;
            const addr = data.address || 'Address unavailable';
            setText('hudAddress', addr);
            setText('addressBox', addr);
            livePopupAddress = addr;
            if (vehiclePopupPinned && lastTelemetry) {
                updateLiveVehiclePopup(lastTelemetry);
            }
        } catch (e) {
            setText('hudAddress', 'Could not load address');
        }
    }

    function getLivePosition() {
        if (currentPositionMarker) {
            const p = currentPositionMarker.getPosition();
            return { lat: p.lat(), lng: p.lng() };
        }
        if (lastTelemetry) return { lat: lastTelemetry.lat, lng: lastTelemetry.lng };
        return null;
    }

    function centerOnVehicle() {
        const pos = getLivePosition();
        if (!pos || !map) {
            showNotification(mi('noPosition', 'No position available'), 'info');
            return;
        }
        ensureFleetRenderer()?.focusOnVehicle(16);
        followVehicle = true;
        fleetRenderer?.setFollowVehicle(true);
        document.getElementById('btnFollow')?.classList.add('active');
    }

    function copyLiveCoords() {
        const pos = getLivePosition();
        if (!pos) return showNotification('No position available', 'error');
        const text = `${pos.lat.toFixed(6)}, ${pos.lng.toFixed(6)}`;
        navigator.clipboard?.writeText(text).then(() => showNotification('Coordinates copied', 'success'))
            .catch(() => showNotification(text, 'info', 'Coordinates'));
    }

    function openInGoogleMaps() {
        const pos = getLivePosition();
        if (!pos) return showNotification('No position available', 'error');
        window.open(`https://www.google.com/maps?q=${pos.lat},${pos.lng}`, '_blank');
    }

    function openStreetView() {
        const pos = getLivePosition();
        if (!pos) return showNotification('No position available', 'error');
        window.open(`https://www.google.com/maps/@?api=1&map_action=pano&viewpoint=${pos.lat},${pos.lng}`, '_blank');
    }

    function analyzeRoute(data) {
        if (!data.length) return null;
        const stopMinSec = (cfg.stopMinMinutes || 2) * 60;
        let dist = 0, maxSpeed = 0, overspeedEvents = 0, movingSec = 0, stoppedSec = 0;
        const stops = [];
        let stopRun = [];

        const flushStop = () => {
            if (stopRun.length < 2) { stopRun = []; return; }
            const t0 = parseRouteTimestampMs(stopRun[0].recorded_at);
            const t1 = parseRouteTimestampMs(stopRun[stopRun.length - 1].recorded_at);
            const dur = t0 != null && t1 != null && t1 > t0 ? (t1 - t0) / 1000 : 0;
            if (dur >= stopMinSec) {
                const mid = stopRun[Math.floor(stopRun.length / 2)];
                stops.push({
                    lat: mid.lat, lng: mid.lng, duration: dur,
                    start: stopRun[0].recorded_at, end: stopRun[stopRun.length - 1].recorded_at,
                });
            }
            stopRun = [];
        };

        for (let i = 1; i < data.length; i++) {
            const a = data[i - 1], b = data[i];
            dist += haversineDistance(a.lat, a.lng, b.lat, b.lng);
            const spd = parseFloat(b.speed || 0);
            if (spd > maxSpeed) maxSpeed = spd;
            if (spd > overSpeedLimit) overspeedEvents++;

            const t0 = parseRouteTimestampMs(a.recorded_at);
            const t1 = parseRouteTimestampMs(b.recorded_at);
            const dt = t0 != null && t1 != null && t1 > t0 ? (t1 - t0) / 1000 : 0;

            if (spd < 2) {
                stoppedSec += dt;
                stopRun.push(b);
            } else {
                movingSec += dt;
                flushStop();
            }
        }
        flushStop();

        const bounds = routeTimeBounds(data);
        const totalSec = bounds.totalSec;

        return { dist, maxSpeed, overspeedEvents, movingSec, stoppedSec, totalSec, stops };
    }

    function detectRouteEvents(data) {
        if (!data || data.length < 2) {
            return [];
        }

        const events = [];
        const stopMinSec = (cfg.stopMinMinutes || 2) * 60;
        let stopRun = [];

        const flushStop = () => {
            if (stopRun.length < 2) {
                stopRun = [];
                return;
            }
            const t0 = new Date(stopRun[0].recorded_at).getTime();
            const t1 = new Date(stopRun[stopRun.length - 1].recorded_at).getTime();
            const dur = (t1 - t0) / 1000;
            if (dur >= stopMinSec) {
                const mid = stopRun[Math.floor(stopRun.length / 2)];
                events.push({
                    type: 'stop',
                    lat: mid.lat,
                    lng: mid.lng,
                    title: mi('eventLongStop', 'Long stop'),
                    detail: formatDurationLong(dur),
                });
            }
            stopRun = [];
        };

        for (let i = 1; i < data.length; i++) {
            const a = data[i - 1];
            const b = data[i];
            const spdA = parseFloat(a.speed || 0);
            const spdB = parseFloat(b.speed || 0);
            const t0 = a.recorded_at ? new Date(a.recorded_at).getTime() : null;
            const t1 = b.recorded_at ? new Date(b.recorded_at).getTime() : null;
            const dtSec = t0 && t1 && t1 > t0 ? (t1 - t0) / 1000 : 0;

            if (spdB > overSpeedLimit && spdA <= overSpeedLimit) {
                events.push({
                    type: 'overspeed',
                    lat: b.lat,
                    lng: b.lng,
                    title: mi('eventOverspeed', 'Overspeed'),
                    detail: spdB.toFixed(0) + ' km/h',
                });
            }

            if (dtSec > 0 && dtSec <= 5) {
                const delta = spdB - spdA;
                if (delta <= -18) {
                    events.push({
                        type: 'harsh_brake',
                        lat: b.lat,
                        lng: b.lng,
                        title: mi('eventHarshBrake', 'Harsh braking'),
                        detail: Math.abs(delta).toFixed(0) + ' km/h',
                    });
                } else if (delta >= 18) {
                    events.push({
                        type: 'harsh_accel',
                        lat: b.lat,
                        lng: b.lng,
                        title: mi('eventHarshAccel', 'Harsh acceleration'),
                        detail: delta.toFixed(0) + ' km/h',
                    });
                }
            }

            if (b.battery != null && parseInt(b.battery, 10) <= lowBatteryThreshold) {
                const prevBat = a.battery != null ? parseInt(a.battery, 10) : 100;
                if (prevBat > lowBatteryThreshold) {
                    events.push({
                        type: 'low_battery',
                        lat: b.lat,
                        lng: b.lng,
                        title: mi('eventLowBattery', 'Low battery'),
                        detail: b.battery + '%',
                    });
                }
            }

            if (b.fuel != null && a.fuel != null) {
                const fuelDelta = parseFloat(b.fuel) - parseFloat(a.fuel);
                if (Math.abs(fuelDelta) >= 5) {
                    events.push({
                        type: 'fuel',
                        lat: b.lat,
                        lng: b.lng,
                        title: mi('eventFuel', 'Fuel event'),
                        detail: (fuelDelta > 0 ? '+' : '') + fuelDelta.toFixed(1) + '%',
                    });
                }
            }

            if (spdB < 2) {
                stopRun.push(b);
            } else {
                flushStop();
            }
        }
        flushStop();

        return events;
    }

    function clearEventMarkers() {
        eventMarkers.forEach((m) => m.setMap(null));
        eventMarkers = [];
    }

    function renderEventMarkers(events) {
        clearEventMarkers();
        if (!showsEventMarkers || !events.length) {
            return;
        }

        events.forEach((ev, i) => {
            const m = new google.maps.Marker({
                position: { lat: ev.lat, lng: ev.lng },
                map,
                title: ev.title + (ev.detail ? ': ' + ev.detail : ''),
                icon: eventMarkerIcon(ev.type),
                zIndex: 550 + i,
            });
            m.addListener('click', () => {
                customInfoWindow.setContent(`
                    <div style="padding:10px;min-width:160px;font-family:system-ui,sans-serif;">
                        <strong>${escapeHtml(ev.title)}</strong><br>
                        <small>${escapeHtml(ev.detail || '')}</small>
                    </div>`);
                customInfoWindow.setPosition({ lat: ev.lat, lng: ev.lng });
                customInfoWindow.open(map);
            });
            eventMarkers.push(m);
        });
    }

    function renderTripEvents(stops, overspeedEvents) {
        const list = document.getElementById('tripEventsList');
        const countEl = document.getElementById('tripEventsCount');
        if (!list) return;

        const items = [];
        stops.forEach((s, i) => {
            items.push({
                type: 'stop',
                title: `Parking stop #${i + 1}`,
                detail: formatDurationLong(s.duration) + ' · ' + (s.start ? new Date(s.start).toLocaleTimeString() : ''),
                lat: s.lat, lng: s.lng,
            });
        });
        if (overspeedEvents > 0) {
            items.unshift({
                type: 'overspeed',
                title: 'Overspeed detected',
                detail: `${overspeedEvents} segment(s) above ${overSpeedLimit} km/h`,
            });
        }

        if (countEl) countEl.textContent = String(items.length);
        if (!items.length) {
            list.innerHTML = '<div class="trip-event-empty text-muted small text-center py-3">No stops or events in this period</div>';
            return;
        }

        list.innerHTML = items.map((ev) => `
            <div class="trip-event-item trip-event-item--${ev.type}" data-lat="${ev.lat ?? ''}" data-lng="${ev.lng ?? ''}">
                <strong>${escapeHtml(ev.title)}</strong>
                <span>${escapeHtml(ev.detail)}</span>
            </div>`).join('');

        list.querySelectorAll('.trip-event-item[data-lat]').forEach((el) => {
            el.addEventListener('click', () => {
                const lat = parseFloat(el.dataset.lat);
                const lng = parseFloat(el.dataset.lng);
                if (!Number.isNaN(lat) && !Number.isNaN(lng)) {
                    map.panTo({ lat, lng });
                    map.setZoom(16);
                }
            });
        });
    }

    function clearStopMarkers() {
        stopMarkers.forEach((m) => m.setMap(null));
        stopMarkers = [];
    }

    function renderStopMarkers(stops) {
        clearStopMarkers();
        if (!showsStops || !stops.length) return;
        stops.forEach((s, i) => {
            const m = new google.maps.Marker({
                position: { lat: s.lat, lng: s.lng },
                map,
                title: `Stop ${i + 1} (${formatDurationLong(s.duration)})`,
                icon: {
                    url: cfg.parkingIcon || cfg.stopIcon || '/images/stop.svg',
                    scaledSize: new google.maps.Size(28, 28),
                    anchor: new google.maps.Point(14, 14),
                },
                zIndex: 500 + i,
            });
            m.addListener('click', () => {
                customInfoWindow.setContent(`
                    <div style="padding:8px;min-width:160px;">
                        <strong>Parking stop</strong><br>
                        <small>Duration: ${formatDurationLong(s.duration)}</small><br>
                        <small>${s.start ? (window.AppDateTime?.formatDateTimeShort(s.start) ?? s.start) : ''}</small>
                    </div>`);
                customInfoWindow.setPosition({ lat: s.lat, lng: s.lng });
                customInfoWindow.open(map);
            });
            stopMarkers.push(m);
        });
    }

    let routeStops = [];

    function toggleStopMarkers() {
        showsStops = !showsStops;
        document.getElementById('btnStops')?.classList.toggle('active', showsStops);
        if (showsStops) renderStopMarkers(routeStops);
        else clearStopMarkers();
        showNotification(showsStops ? 'Parking stops shown' : 'Parking stops hidden', 'info');
    }

    function toggleHeatmap() {
        const btn = document.getElementById('btnHeatmap');
        const legend = document.getElementById('heatmapLegend');
        if (heatmapLayer) {
            heatmapLayer.setMap(null);
            heatmapLayer = null;
            btn?.classList.remove('active');
            if (legend) legend.style.display = 'none';
            return;
        }
        if (!historyData.length || !google.maps.visualization) {
            return showNotification('Load route history first', 'info');
        }
        const weighted = historyData.map((p) => ({
            location: new google.maps.LatLng(p.lat, p.lng),
            weight: Math.max(1, parseFloat(p.speed || 0)),
        }));
        heatmapLayer = new google.maps.visualization.HeatmapLayer({
            data: weighted,
            map,
            radius: 22,
            opacity: 0.65,
        });
        btn?.classList.add('active');
        if (legend) legend.style.display = 'block';
        showNotification('Speed-weighted heatmap enabled', 'info');
    }

    function toggleNightMode() {
        nightModeOn = !nightModeOn;
        document.getElementById('btnNightMode')?.classList.toggle('active', nightModeOn);
        document.body.classList.toggle('map-night-mode', nightModeOn);
        map.setOptions({ styles: nightModeOn ? NIGHT_MAP_STYLES : [] });
    }

    function fitRouteBounds() {
        if (!historyData.length) return showNotification('Load route history first', 'info');
        const bounds = new google.maps.LatLngBounds();
        historyData.forEach((p) => bounds.extend({ lat: p.lat, lng: p.lng }));
        map.fitBounds(bounds, { top: 120, right: 80, bottom: 140, left: 320 });
    }

    function downloadFile(filename, content, mime) {
        const blob = new Blob([content], { type: mime });
        const a = document.createElement('a');
        a.href = URL.createObjectURL(blob);
        a.download = filename;
        a.click();
        URL.revokeObjectURL(a.href);
    }

    function exportRouteCsv() {
        if (!historyData.length) return showNotification('No route data to export', 'info');
        const header = 'lat,lng,speed,heading,recorded_at,battery,ignition,odometer';
        const rows = historyData.map((p) => [
            p.lat, p.lng, p.speed, p.heading, p.recorded_at || '',
            p.battery ?? '', p.ignition ? 1 : 0, p.odometer ?? '',
        ].join(','));
        downloadFile(`route-${deviceId}-${Date.now()}.csv`, [header, ...rows].join('\n'), 'text/csv');
        showNotification('CSV exported', 'success');
    }

    function exportRouteGpx() {
        if (!historyData.length) return showNotification('No route data to export', 'info');
        const pts = historyData.map((p) => {
            const t = p.recorded_at ? new Date(p.recorded_at).toISOString() : new Date().toISOString();
            return `      <trkpt lat="${p.lat}" lon="${p.lng}"><time>${t}</time><speed>${p.speed || 0}</speed></trkpt>`;
        }).join('\n');
        const gpx = `<?xml version="1.0" encoding="UTF-8"?>
<gpx version="1.1" creator="FalconEyeGPS">
  <trk><name>${cfg.deviceName || 'Route'}</name><trkseg>
${pts}
  </trkseg></trk>
</gpx>`;
        downloadFile(`route-${deviceId}-${Date.now()}.gpx`, gpx, 'application/gpx+xml');
        showNotification('GPX exported', 'success');
    }

    function updateTelemetryUI(point) {
        if (!point) return;

        const tier = connectivityTier(point);
        const isRecent = tier === 'live';
        const isOffline = tier === 'offline';
        const speed = isRecent ? parseFloat(point.speed || 0) : lastKnownSpeed(point);
        const battery = point.battery != null ? parseInt(point.battery, 10) : null;
        const status = resolveVehicleStatus(point);
        const ignitionVal = isRecent ? point.ignition : lastKnownIgnition(point);
        const ignitionText = ignitionVal != null
            ? (ignitionVal ? mi('ignitionOn', 'ON') : mi('ignitionOff', 'OFF'))
            : dash();

        setText('lastSeen', point.recorded_at ? (window.AppDateTime?.formatDateTime(point.recorded_at) ?? point.recorded_at) : dash());
        setText('telemetrySpeed', speed.toFixed(0) + ' ' + mi('kmh', 'km/h'));
        setText('telemetryHeading', point.heading != null && point.heading !== '' ? point.heading + '°' : dash());
        setText('telemetryBattery', battery != null ? battery + '%' : dash());
        setText('telemetryIgnition', ignitionText);
        setText('telemetryGsm', formatGsmDisplay(point.gsm_signal));
        setText('telemetrySatellites', point.satellites != null ? String(point.satellites) : dash());
        setText('telemetryOdometer', point.odometer != null ? Number(point.odometer).toLocaleString() + ' ' + mi('km', 'km') : dash());

        setText('livePanelSpeed', speed.toFixed(0) + ' ' + mi('kmh', 'km/h'));
        setText('livePanelIgnition', ignitionText);
        setText('livePanelGps', point.gps_fix != null ? String(point.gps_fix) : dash());
        setText('livePanelGsm', formatGsmDisplay(point.gsm_signal));
        setText('livePanelSatellites', point.satellites != null ? String(point.satellites) : dash());
        setText('livePanelBattery', battery != null ? battery + '%' : dash());
        setText('livePanelUpdated', point.recorded_at ? (window.AppDateTime?.formatDateTime(point.recorded_at) ?? point.recorded_at) : dash());

        const liveChip = document.getElementById('liveStatusChip');
        if (liveChip) {
            liveChip.className = 'map-status-chip ' + status.cls;
            liveChip.textContent = status.label;
        }

        const statusEl = document.getElementById('curStatus');
        if (statusEl) {
            statusEl.className = 'map-status-chip ' + status.cls;
            statusEl.textContent = status.label;
        }

        const navStatus = document.getElementById('navLiveStatus');
        if (navStatus) {
            navStatus.innerHTML = liveChip
                ? liveChip.outerHTML
                : (statusEl ? statusEl.innerHTML : '<span class="badge bg-success">Live</span>');
        }

        updateMapHud(point);
        updateRouteSummaryLive(point);
    }

    let mapAccessDeniedHandled = false;

    async function parseJsonResponse(res) {
        try {
            return await res.json();
        } catch {
            return {};
        }
    }

    function handleMapAccessDenied(res, data) {
        if (cfg.isAdminMap) {
            return false;
        }
        if (res.status !== 403) {
            return false;
        }
        if (mapAccessDeniedHandled) {
            return true;
        }
        mapAccessDeniedHandled = true;
        const payload = data || {};
        const title = payload.title || 'Access restricted';
        const msg = payload.message || 'Map access is not available. Please resubscribe or contact support.';
        showNotification(msg, 'warning', title);
        const redirect = payload.redirect || accessDeniedRedirect;
        setTimeout(() => {
            window.location.href = redirect;
        }, 3500);
        return true;
    }

    function showNotification(msg, type = 'info', title = null) {
        const cont = document.getElementById('notificationContainer');
        if (!cont) return;

        const icons = { success: 'fa-check-circle', error: 'fa-exclamation-circle', warning: 'fa-exclamation-triangle', info: 'fa-info-circle' };
        const notif = document.createElement('div');
        notif.className = 'notification ' + type;
        notif.innerHTML = `
            <div class="notification-icon"><i class="fas ${icons[type] || icons.info}"></i></div>
            <div class="notification-content">
                <div class="notification-title">${title || type.toUpperCase()}</div>
                <div class="notification-message">${msg}</div>
            </div>
            <button type="button" class="notification-close"><i class="fas fa-times"></i></button>`;
        notif.className = 'notification ' + type;
        cont.prepend(notif);
        notif.querySelector('.notification-close')?.addEventListener('click', () => notif.remove());
        setTimeout(() => notif.remove(), 8000);
    }

    function updateNavAlertBadge(count) {
        unreadAlertCount = Math.max(0, count);
        const badge = document.getElementById('navAlertBadge');
        const btn = document.getElementById('navAlertsBtn');
        const summary = document.getElementById('navAlertsSummary');
        if (badge) {
            badge.textContent = unreadAlertCount > 99 ? '99+' : String(unreadAlertCount);
            badge.hidden = unreadAlertCount <= 0;
        }
        if (btn) btn.classList.toggle('has-unread', unreadAlertCount > 0);
        if (summary) {
            summary.textContent = unreadAlertCount > 0
                ? mi('alertsNewCount', `${unreadAlertCount} new`).replace(':count', String(unreadAlertCount))
                : mi('noNewAlerts', 'No new alerts');
        }
    }

    function geofenceLabelFromAlert(alert) {
        if (alert?.geofence) {
            return alert.geofence;
        }
        const m = (alert?.message || '').match(/geofence\s+"([^"]+)"/i);

        return m ? m[1] : '';
    }

    function isGeofenceAlert(alert) {
        return alert?.event_type === 'geofence_enter'
            || alert?.event_type === 'geofence_exit'
            || /geofenceEnter|geofenceExit/i.test(alert?.event_type || '');
    }

    function renderNavAlertsList() {
        const list = document.getElementById('navAlertsList');
        if (!list) return;
        if (!navAlertStore.length) {
            list.innerHTML = `<div class="nav-alerts-empty">${escapeHtml(mi('noAlertsYet', 'No alerts yet'))}</div>`;
            return;
        }
        list.innerHTML = navAlertStore.slice(0, NAV_ALERT_LIMIT).map((a) => {
            const d = a.time instanceof Date ? a.time : (a.time ? new Date(a.time) : null);
            const dateStr = d && !Number.isNaN(d.getTime()) ? d.toLocaleDateString(undefined, { month: 'short', day: 'numeric', year: 'numeric' }) : '';
            const timeStr = d && !Number.isNaN(d.getTime()) ? d.toLocaleTimeString(undefined, { hour: '2-digit', minute: '2-digit', second: '2-digit' }) : '';
            const time = [dateStr, timeStr].filter(Boolean).join(' · ');
            const isGeofence = isGeofenceAlert(a);
            const zoneName = geofenceLabelFromAlert(a);
            const detail = zoneName
                ? `<span class="nav-alert-detail"><i class="fas fa-draw-polygon"></i> ${escapeHtml(zoneName)}</span>`
                : '';
            const coords = a.lat && a.lng
                ? `<span class="nav-alert-detail text-muted">${Number(a.lat).toFixed(5)}, ${Number(a.lng).toFixed(5)}</span>`
                : '';
            const clickable = a.lat && a.lng ? ' nav-alert-item--clickable' : '';
            return `<div class="nav-alert-item nav-alert-item--${escapeHtml(a.type || 'info')}${isGeofence ? ' nav-alert-item--geofence' : ''}${clickable}" data-alert-lat="${a.lat || ''}" data-alert-lng="${a.lng || ''}">
                <strong>${escapeHtml(a.title || 'Alert')}</strong>
                <span>${escapeHtml(a.message || '')}</span>
                ${detail}
                ${coords}
                <small>${escapeHtml(time)}</small>
            </div>`;
        }).join('');
        list.querySelectorAll('.nav-alert-item--clickable').forEach((el) => {
            el.addEventListener('click', () => {
                const lat = parseFloat(el.getAttribute('data-alert-lat'));
                const lng = parseFloat(el.getAttribute('data-alert-lng'));
                if (!map || Number.isNaN(lat) || Number.isNaN(lng)) return;
                followVehicle = true;
                document.getElementById('btnFollow')?.classList.add('active');
                map.panTo({ lat, lng });
                if ((map.getZoom() || 0) < 15) map.setZoom(15);
            });
        });
    }

    function pushNavAlert(title, message, type, options = {}) {
        const countAsUnread = options.countAsUnread !== false;
        const id = options.id || 0;
        if (id && seenAlertIds.has(id)) {
            return;
        }
        if (id) {
            seenAlertIds.add(id);
            if (id > lastAlertEventId) {
                lastAlertEventId = id;
            }
        }
        navAlertStore.unshift({
            id,
            title: title || 'Alert',
            message: message || '',
            type: type || 'info',
            event_type: options.event_type || '',
            geofence: options.geofence || '',
            lat: options.lat,
            lng: options.lng,
            time: options.time ? new Date(options.time) : new Date(),
        });
        if (navAlertStore.length > 50) navAlertStore.pop();
        renderNavAlertsList();
        if (countAsUnread) updateNavAlertBadge(unreadAlertCount + 1);
    }

    function ingestAlertPayload(e, options = {}) {
        const notify = options.notify === true;
        const countAsUnread = options.countAsUnread !== false;
        const geofence = geofenceLabelFromAlert(e);
        pushNavAlert(e.title || 'Alert', e.message || '', e.type || 'info', {
            id: e.id,
            time: e.time,
            event_type: e.event_type,
            geofence,
            lat: e.lat,
            lng: e.lng,
            countAsUnread,
        });
        if (notify) {
            const gf = isGeofenceAlert(e);
            showNotification(e.message || e.title, gf ? 'warning' : (e.type || 'info'), e.title);
        }
    }

    function initNavAlerts() {
        const btn = document.getElementById('navAlertsBtn');
        const dropdown = document.getElementById('navAlertsDropdown');
        if (!btn || !dropdown) return;

        btn.addEventListener('click', (e) => {
            e.preventDefault();
            e.stopPropagation();
            const open = !dropdown.classList.contains('show');
            dropdown.classList.toggle('show', open);
            btn.setAttribute('aria-expanded', open ? 'true' : 'false');
            if (open) {
                updateNavAlertBadge(0);
                loadRecentAlerts();
            }
        });

        document.addEventListener('click', (e) => {
            if (!e.target.closest('.nav-alerts-wrap')) {
                dropdown.classList.remove('show');
                btn.setAttribute('aria-expanded', 'false');
            }
        });
    }

    function triggerAlert(key, msg, type, title) {
        if (alertState[key]) return;
        alertState[key] = true;
        pushNavAlert(title, msg, type);
        showNotification(msg, type, title);
        setTimeout(() => { alertState[key] = false; }, 60000);
    }

    function evaluateAlerts(point, prev) {
        if (!point) return;

        if (point.panic) triggerAlert('panic', 'Emergency panic button activated!', 'error', 'SOS Alert');
        if (point.power_cut) triggerAlert('power', 'Device power has been cut.', 'error', 'Power Cut');
        if (point.battery != null && point.battery <= lowBatteryThreshold) {
            triggerAlert('battery', `Battery low: ${point.battery}%`, 'warning', 'Low Battery');
        }
        if (point.speed > overSpeedLimit) {
            triggerAlert('overspeed', `Speed ${point.speed.toFixed(0)} km/h exceeds ${overSpeedLimit} km/h`, 'warning', 'Overspeed');
        }
        if (prev && point.speed > 10 && !point.ignition) {
            triggerAlert('ignition', 'Vehicle moving with ignition OFF', 'warning', 'Ignition Alert');
        }
        if (prev) {
            const dist = haversineDistance(prev.lat, prev.lng, point.lat, point.lng);
            if (dist > 0.5 && point.speed < 2) {
                triggerAlert('jump', 'Possible GPS jump detected', 'info', 'GPS Anomaly');
            }
        }

        resetOfflineWatch(point);
    }

    function resetOfflineWatch(point) {
        if (offlineTimer) clearTimeout(offlineTimer);
        if (!point || hasNoGpsData(point)) {
            return;
        }
        offlineTimer = setTimeout(() => {
            if (isConnectivityStale(lastTelemetry)) {
                triggerAlert('offline', mi('noGpsRecently', 'No GPS update received recently'), 'warning', mi('deviceOffline', 'Device Offline'));
            }
        }, onlineTimeoutMs);
    }

    function updateCurrentMarker(point, skipAnimation) {
        const renderer = ensureFleetRenderer();
        if (!renderer) return;

        renderer.setFollowVehicle(followVehicle);
        renderer.setPlaybackActive(playbackActive);
        renderer.setCurrentVehicle(point, {
            skipAnimation: !!skipAnimation,
            focusZoom: skipAnimation ? null : 16,
            onComplete: () => {
                currentPositionMarker = renderer.getMarker();
                if (vehiclePopupPinned && lastTelemetry) {
                    updateLiveVehiclePopup(lastTelemetry);
                }
            },
        });
        currentPositionMarker = renderer.getMarker();
        if (currentPositionMarker && !markers.includes(currentPositionMarker)) {
            markers.push(currentPositionMarker);
        }
    }

    function animateMarkerTo(point) {
        updateCurrentMarker(point, false);
    }

    function createRouteSegment(from, to, speed, options) {
        const renderer = ensureFleetRenderer();
        if (!renderer) return [];
        return renderer._createSegment(from, to, speed, {
            clickable: options?.clickable !== false,
            night: nightModeOn,
            glowOn: routeGlowEnabled,
            onClick: options?.onClick,
        });
    }

    function drawRealtimeSegment(from, to, speed) {
        ensureFleetRenderer()?.drawRealtimeSegment(from, to, speed);
    }

    function applyLivePoint(raw) {
        let point = normalizePoint(raw);
        if (!point) return;

        point = enrichPointWithMotion(point, lastTelemetry);
        debugGpsLog('live point', {
            gsm: point.gsm_signal,
            satellites: point.satellites,
            battery: point.battery,
            recorded_at: point.recorded_at,
        });

        const prev = lastTelemetry;
        const positionChanged = hasSignificantPositionChange(point, prev);
        const key = positionKey(point);

        if (positionChanged && !playbackActive) {
            lastAppliedPositionKey = key;
            updateCurrentMarker(point, !prev);
            if (prev && lastRealtimePoint) {
                drawRealtimeSegment(lastRealtimePoint, point, point.speed);
            }
            lastRealtimePoint = { lat: point.lat, lng: point.lng };
        } else if (!playbackActive) {
            ensureFleetRenderer()?.updateVehicleIcon(point);
        }

        if (connectivityTier(point) === 'live') {
            routeScrubUsesMotion = false;
        }

        updateTelemetryUI(point);
        updateLiveVehiclePopup(point);
        evaluateAlerts(point, prev);
        lastTelemetry = point;
    }

    async function pollLive() {
        try {
            const res = await fetch(liveUrl, { headers: { Accept: 'application/json' } });
            const data = await parseJsonResponse(res);
            if (handleMapAccessDenied(res, data)) return;
            if (!res.ok) return;
            if (data && data.lat) applyLivePoint(data);
            await pollNewAlerts();
        } catch (e) {
            console.warn('Live poll failed', e);
        }
    }

    function alertsFetchUrl(extraLimit, useAfterId) {
        const limit = extraLimit || NAV_ALERT_LIMIT;
        const params = new URLSearchParams({ limit: String(limit), bell: '1' });
        if (useAfterId !== false && lastAlertEventId > 0) {
            params.set('after_id', String(lastAlertEventId));
        }
        return `${alertsUrl}?${params.toString()}`;
    }

    function fetchAlerts(url) {
        return fetch(url, {
            credentials: 'same-origin',
            headers: {
                Accept: 'application/json',
                'X-Requested-With': 'XMLHttpRequest',
            },
        });
    }

    function hydrateAlertsFromConfig() {
        const items = Array.isArray(cfg.initialAlerts) ? cfg.initialAlerts : [];
        if (!items.length) {
            return false;
        }
        navAlertStore.length = 0;
        seenAlertIds.clear();
        lastAlertEventId = 0;
        items.forEach((e) => ingestAlertPayload(e, { countAsUnread: false }));
        sortNavAlertStore();
        renderNavAlertsList();
        const gfCount = navAlertStore.filter((a) => isGeofenceAlert(a)).length;
        if (gfCount > 0) {
            updateNavAlertBadge(gfCount);
        }
        alertsBootstrapped = true;
        return true;
    }

    function setupRealtime() {
        if (livePollTimer) {
            clearInterval(livePollTimer);
        }
        if (alertsPollTimer) {
            clearInterval(alertsPollTimer);
        }

        pollLive();
        livePollTimer = setInterval(() => pollLive(), pollIntervalMs);
        alertsPollTimer = setInterval(pollNewAlerts, pollIntervalMs);

        if (window.Echo && typeof window.Echo.private === 'function') {
            try {
                window.Echo.private(`device.${deviceId}`)
                    .listen('.DeviceLocationUpdated', (payload) => {
                        applyLivePoint(payload.location || payload);
                        pollNewAlerts();
                    });
                console.info('[device-map] Live updates via Pusher + polling every', pollIntervalMs, 'ms');
            } catch (err) {
                console.warn('[device-map] Echo subscribe failed — using polling only', err);
            }
        } else {
            console.info('[device-map] Live updates via polling every', pollIntervalMs, 'ms');
        }
    }

    function showLoading(msg) {
        const el = document.getElementById('loadingOverlay');
        if (el) {
            document.getElementById('loadingText').textContent = msg;
            el.classList.add('active');
        }
    }

    function hideLoading() {
        document.getElementById('loadingOverlay')?.classList.remove('active');
    }

    function hasGoogleMapDom(el) {
        if (!el) {
            return false;
        }
        if (el.querySelector('.gm-style, .gm-style-cc, .gm-err-container')) {
            return !el.querySelector('.gm-err-container');
        }
        const hasMapSurface = !!(
            el.querySelector('iframe')
            || el.querySelector('canvas')
            || el.querySelector('[role="region"]')
        );
        return hasMapSurface && el.children.length > 0;
    }

    function waitForMapContainerSize(maxMs = 8000) {
        return new Promise((resolve, reject) => {
            const start = Date.now();
            const check = () => {
                const el = document.getElementById('map');
                const area = document.getElementById('mapArea');
                const target = el || area;
                if (target && target.offsetWidth >= 20 && target.offsetHeight >= 20) {
                    resolve();
                    return;
                }
                if (Date.now() - start > maxMs) {
                    reject(new Error('Map container not sized'));
                    return;
                }
                requestAnimationFrame(check);
            };
            check();
        });
    }

    let mapsAuthFailed = false;

    function currentMapsReferrerPattern() {
        return `${window.location.protocol}//${window.location.host}/*`;
    }

    function buildMapsReferrerErrorMessage() {
        const referrer = currentMapsReferrerPattern();
        const template = mi(
            'mapReferrerDenied',
            'Google Maps blocked this site. Add this referrer in Google Cloud Console: :referrer'
        );
        return template.replace(':referrer', referrer);
    }

    function installGoogleMapsAuthFailureHandler() {
        if (window.__deviceMapGmapsAuthHook) {
            return;
        }
        window.__deviceMapGmapsAuthHook = true;
        window.gm_authFailure = function () {
            mapsAuthFailed = true;
            mapBootRunning = false;
            map = null;
            mapReady = false;
            showMapBootError(buildMapsReferrerErrorMessage());
        };
    }

    function hasGoogleMapsErrorOverlay(container) {
        if (!container) {
            return false;
        }
        return !!container.querySelector('.gm-err-container, .gm-err-message, .gm-style-pbc');
    }

    function isMapBootFatalError(err) {
        if (mapsAuthFailed) {
            return true;
        }
        const msg = String(err?.message || err || '').toLowerCase();
        return msg.includes('missing google maps api key')
            || msg.includes('api key')
            || msg.includes('invalidkey')
            || msg.includes('referernotallowed')
            || msg.includes('referer not allowed');
    }

    function mapBootFatalMessage(err) {
        if (mapsAuthFailed || String(err?.message || '').toLowerCase().includes('referernotallowed')) {
            return buildMapsReferrerErrorMessage();
        }
        return mi(
            'mapApiKeyMissing',
            'Google Maps API key is missing or invalid. Set GOOGLE_MAPS_API_KEY in .env.'
        );
    }

    function showMapBootError(message) {
        const el = document.getElementById('loadingOverlay');
        const text = document.getElementById('loadingText');
        if (text) {
            text.textContent = message;
            text.style.maxWidth = '420px';
            text.style.lineHeight = '1.5';
            text.style.textAlign = 'center';
        }
        if (el) {
            el.classList.add('active');
        }
    }

    function buildGoogleMapsScriptUrl(key) {
        const params = new URLSearchParams({
            key,
            libraries: 'drawing,geometry,visualization,places',
            v: 'weekly',
        });
        return `https://maps.googleapis.com/maps/api/js?${params.toString()}`;
    }

    /** drawing library must be ready before DrawingManager. */
    async function ensureDrawingLibrary(maxMs = 12000) {
        const start = Date.now();
        while (Date.now() - start < maxMs) {
            if (mapsAuthFailed) {
                return false;
            }
            if (google?.maps?.drawing?.DrawingManager) {
                return true;
            }
            if (typeof google?.maps?.importLibrary === 'function') {
                try {
                    await google.maps.importLibrary('drawing');
                    if (google?.maps?.drawing?.DrawingManager) {
                        return true;
                    }
                } catch (_) {
                    /* retry */
                }
            }
            await sleep(50);
        }
        return !!google?.maps?.drawing?.DrawingManager;
    }

    function initDrawingManager() {
        if (drawingManager || !map) {
            return drawingManager;
        }
        if (!google?.maps?.drawing?.DrawingManager) {
            return null;
        }
        try {
            drawingManager = new google.maps.drawing.DrawingManager({ drawingControl: false });
            drawingManager.setMap(map);
        } catch (drawErr) {
            console.warn('[device-map] DrawingManager init failed', drawErr);
            drawingManager = null;
        }
        return drawingManager;
    }

    function waitForGoogleMaps(maxMs = 15000) {
        return new Promise((resolve, reject) => {
            const start = Date.now();
            (function poll() {
                if (mapsAuthFailed) {
                    reject(new Error('RefererNotAllowedMapError'));
                    return;
                }
                if (window.google?.maps?.Map) {
                    resolve();
                    return;
                }
                if (Date.now() - start > maxMs) {
                    reject(new Error('Google Maps API unavailable'));
                    return;
                }
                setTimeout(poll, 50);
            })();
        });
    }

    function loadGoogleMapsApi() {
        return new Promise((resolve, reject) => {
            if (window.google?.maps?.Map) {
                resolve();
                return;
            }

            const key = (cfg.googleMapsKey || '').trim();
            if (!key) {
                reject(new Error('Missing Google Maps API key'));
                return;
            }

            installGoogleMapsAuthFailureHandler();

            const existing = document.querySelector('script[data-device-map-gmaps]');
            if (existing) {
                waitForGoogleMaps().then(resolve).catch(reject);
                return;
            }

            const script = document.createElement('script');
            script.dataset.deviceMapGmaps = '1';
            script.async = true;
            script.defer = true;
            script.src = buildGoogleMapsScriptUrl(key);
            script.onerror = () => reject(new Error('Google Maps script failed to load'));
            script.onload = () => {
                waitForGoogleMaps().then(resolve).catch(reject);
            };
            document.head.appendChild(script);
        });
    }

    function verifyMapRendered() {
        return new Promise((resolve, reject) => {
            if (!map) {
                reject(new Error('Map not initialized'));
                return;
            }

            const div = document.getElementById('map');
            if (!div || div.offsetWidth < 20 || div.offsetHeight < 20) {
                reject(new Error('Map container too small'));
                return;
            }

            if (hasGoogleMapDom(div)) {
                resolve();
                return;
            }

            let settled = false;
            const finish = (ok) => {
                if (settled) {
                    return;
                }
                settled = true;
                clearTimeout(timer);
                if (mapsAuthFailed || hasGoogleMapsErrorOverlay(div)) {
                    reject(new Error('RefererNotAllowedMapError'));
                    return;
                }
                if (ok || hasGoogleMapDom(div)) {
                    resolve();
                } else {
                    reject(new Error('Map tiles did not render'));
                }
            };

            const timer = setTimeout(() => finish(hasGoogleMapDom(div)), MAP_READY_TIMEOUT_MS);

            google.maps.event.addListenerOnce(map, 'tilesloaded', () => finish(true));
            google.maps.event.addListenerOnce(map, 'idle', () => {
                setTimeout(() => finish(true), 250);
            });
            google.maps.event.addListenerOnce(map, 'projection_changed', () => {
                setTimeout(() => finish(true), 120);
            });
            google.maps.event.trigger(map, 'resize');
            setTimeout(() => {
                if (!settled && hasGoogleMapDom(div)) {
                    finish(true);
                }
            }, 600);
        });
    }

    async function startMapDataServices() {
        try {
            await loadRecentAlerts();
            setupRealtime();
            await pollLive();
        } catch (err) {
            console.warn('[device-map] data services failed', err);
        }
    }

    function onMapTilesReady() {
        mapReady = true;
        mapHealthMisses = 0;
        lastMapBootError = '';
        hideLoading();
        resizeMap();
        if (!mapDataStarted) {
            mapDataStarted = true;
            hydrateAlertsFromConfig();
            loadGeofences();
            loadHistory();
            startMapDataServices();
        }
        window.dispatchEvent(new CustomEvent('device-map-ready'));
    }

    function bindMapWatchers() {
        if (mapResizeObserver || typeof ResizeObserver === 'undefined') {
            return;
        }

        const area = document.getElementById('mapArea');
        if (!area) {
            return;
        }

        let resizeTimer;
        mapResizeObserver = new ResizeObserver(() => {
            clearTimeout(resizeTimer);
            resizeTimer = setTimeout(() => {
                resizeMap();
                if (map && !mapReady) {
                    verifyMapRendered().then(onMapTilesReady).catch(() => {});
                }
            }, 150);
        });
        mapResizeObserver.observe(area);

        window.addEventListener('map-sidebar-toggled', () => {
            setTimeout(resizeMap, 400);
        });

        document.addEventListener('visibilitychange', () => {
            if (document.visibilityState !== 'visible' || !map) {
                return;
            }
            resizeMap();
            const div = document.getElementById('map');
            if (mapReady && div && !hasGoogleMapDom(div)) {
                mapReady = false;
                bootDeviceMap();
            }
        });

        if (mapHealthTimer) {
            clearInterval(mapHealthTimer);
        }
        mapHealthTimer = setInterval(() => {
            if (!map || mapBootRunning) {
                return;
            }
            const div = document.getElementById('map');
            if (mapReady && div && !hasGoogleMapDom(div)) {
                mapHealthMisses += 1;
                if (mapHealthMisses < MAP_HEALTH_MISS_MAX) {
                    return;
                }
                console.warn('[device-map] render health check failed — reloading map');
                mapHealthMisses = 0;
                mapReady = false;
                map = null;
                bootDeviceMap();
                return;
            }
            mapHealthMisses = 0;
        }, MAP_HEALTH_INTERVAL_MS);
    }

    function createMapInstance() {
        const mapEl = document.getElementById('map');
        if (!mapEl || mapEl.offsetWidth < 20 || mapEl.offsetHeight < 20) {
            throw new Error('Map container not ready');
        }

        const initial = normalizePoint(cfg.initialPoint);
        const center = initial
            ? { lat: initial.lat, lng: initial.lng }
            : { lat: cfg.defaultLat || 24.8607, lng: cfg.defaultLng || 67.0011 };

        map = new google.maps.Map(mapEl, {
            center,
            zoom: initial ? 15 : 13,
            mapTypeId: 'roadmap',
            gestureHandling: 'greedy',
            fullscreenControl: true,
            zoomControl: true,
            streetViewControl: false,
            minZoom: 3,
            maxZoom: 21,
        });

        initDrawingManager();
        trafficLayer = new google.maps.TrafficLayer();
        customInfoWindow = new google.maps.InfoWindow({ maxWidth: 320, pixelOffset: new google.maps.Size(0, -8) });
        customInfoWindow.addListener('closeclick', () => {
            closeLiveVehiclePopup();
        });
        map.addListener('click', () => {
            if (vehiclePopupPinned) {
                closeLiveVehiclePopup();
            }
        });
        google.maps.event.addListener(customInfoWindow, 'domready', () => {
            document.getElementById('liveVehiclePopupClose')?.addEventListener('click', (e) => {
                e.preventDefault();
                e.stopPropagation();
                closeLiveVehiclePopup();
            });
        });

        if (!mapControlsBound) {
            bindControls();
            initNavAlerts();
            initMapHudToggle();
            initMapLivePanelToggle();
            initHudRouteSummary();
            initMapBackNavigation();
            updatePlaybackFab();
            initDateFilter();
            mapControlsBound = true;
        }

        ensureFleetRenderer();

        if (initial) {
            applyLivePoint(initial);
            lastRealtimePoint = { lat: initial.lat, lng: initial.lng };
        }
    }

    async function bootDeviceMap() {
        if (mapBootRunning) {
            return;
        }
        if (mapReady && map && hasGoogleMapDom(document.getElementById('map'))) {
            resizeMap();
            return;
        }

        mapBootRunning = true;
        mapBootAttempts += 1;

        const attemptLabel = mapBootAttempts > 1
            ? mi('loadingMapRetry', 'Reloading map…').replace(':attempt', String(mapBootAttempts))
            : mi('loadingMap', 'Loading map…');
        showLoading(attemptLabel);

        try {
            await waitForMapContainerSize();
            await loadGoogleMapsApi();
            await ensureDrawingLibrary();

            if (!map) {
                createMapInstance();
            } else {
                google.maps.event.trigger(map, 'resize');
            }

            bindMapWatchers();
            await verifyMapRendered();
            onMapTilesReady();
            mapBootAttempts = 0;
        } catch (err) {
            lastMapBootError = String(err?.message || err || 'unknown');
            console.error('[device-map] boot failed', mapBootAttempts, lastMapBootError, err);
            window.__deviceMapLastError = lastMapBootError;
            map = null;
            mapReady = false;

            if (isMapBootFatalError(err)) {
                mapBootRunning = false;
                showMapBootError(mapBootFatalMessage(err));
                return;
            }

            if (mapBootAttempts < MAP_BOOT_MAX) {
                await sleep(Math.min(1500 * mapBootAttempts, 6000));
                mapBootRunning = false;
                return bootDeviceMap();
            }

            const retryMsg = mi('loadingMapFailed', 'Map failed to load. Retrying…');
            const detail = cfg.appDebug && lastMapBootError
                ? `${retryMsg}\n(${lastMapBootError})`
                : retryMsg;
            showLoading(detail);
            mapBootAttempts = 0;
            await sleep(8000);
            mapBootRunning = false;
            return bootDeviceMap();
        } finally {
            mapBootRunning = false;
        }
    }

    function resizeMap() {
        if (map && google?.maps?.event) {
            setTimeout(() => google.maps.event.trigger(map, 'resize'), 350);
        }
    }

    async function loadRecentAlerts() {
        if (!alertsUrl) {
            if (!alertsBootstrapped) {
                hydrateAlertsFromConfig();
            }
            return;
        }
        try {
            const res = await fetchAlerts(alertsFetchUrl(NAV_ALERT_LIMIT, false));
            const events = await parseJsonResponse(res);
            if (handleMapAccessDenied(res, events)) {
                if (!alertsBootstrapped) {
                    hydrateAlertsFromConfig();
                }
                return;
            }
            if (!res.ok) {
                console.warn('Alerts load HTTP', res.status);
                if (!alertsBootstrapped) {
                    hydrateAlertsFromConfig();
                }
                return;
            }
            const items = Array.isArray(events) ? events : (Array.isArray(events?.data) ? events.data : []);
            if (!items.length && !alertsBootstrapped) {
                hydrateAlertsFromConfig();
                return;
            }
            navAlertStore.length = 0;
            seenAlertIds.clear();
            lastAlertEventId = 0;
            items.forEach((e) => {
                ingestAlertPayload(e, { countAsUnread: false });
            });
            sortNavAlertStore();
            renderNavAlertsList();
            const unreadGeofence = navAlertStore.filter((a) => isGeofenceAlert(a)).length;
            if (unreadGeofence > 0) {
                updateNavAlertBadge(unreadGeofence);
            }
            alertsBootstrapped = true;
        } catch (err) {
            console.warn('Alerts load failed', err);
            if (!alertsBootstrapped) {
                hydrateAlertsFromConfig();
            }
        }
    }

    function sortNavAlertStore() {
        navAlertStore.sort((a, b) => {
            const score = (x) => {
                if (isGeofenceAlert(x)) return 100;
                if (x.event_type === 'panic' || x.event_type === 'power_cut') return 90;
                return 10;
            };
            return score(b) - score(a) || (b.id || 0) - (a.id || 0);
        });
    }

    async function pollNewAlerts() {
        if (!alertsUrl) return;
        try {
            const res = await fetchAlerts(alertsFetchUrl(lastAlertEventId > 0 ? 15 : NAV_ALERT_LIMIT));
            const events = await parseJsonResponse(res);
            if (handleMapAccessDenied(res, events)) return;
            if (!res.ok) return;
            const items = Array.isArray(events) ? events : [];
            if (!items.length) return;

            if (lastAlertEventId > 0) {
                items.forEach((e) => {
                    ingestAlertPayload(e, {
                        notify: true,
                        countAsUnread: true,
                    });
                });
            } else {
                items.forEach((e) => ingestAlertPayload(e, { countAsUnread: false }));
            }

            sortNavAlertStore();
            renderNavAlertsList();
        } catch (err) {
            console.warn('Alert poll failed', err);
        }
    }

    function bindDrawingControls() {
        const drawIds = ['btnDrawPolygon', 'btnDrawCircle', 'btnSaveGeofence', 'btnCancelGeofence'];
        const dm = drawingManager || initDrawingManager();
        if (!dm || !google?.maps?.drawing) {
            drawIds.forEach((id) => {
                const el = document.getElementById(id);
                if (el) {
                    el.disabled = true;
                }
            });
            return;
        }

        google.maps.event.addListener(dm, 'overlaycomplete', (e) => {
            currentDrawing = e.overlay;
            document.getElementById('btnSaveGeofence').disabled = false;
            dm.setDrawingMode(null);
        });
        document.getElementById('btnDrawPolygon')?.addEventListener('click', () => {
            if (!initDrawingManager()) {
                showNotification('Drawing tools unavailable', 'error');
                return;
            }
            currentDrawing?.setMap(null);
            drawingManager.setDrawingMode(google.maps.drawing.OverlayType.POLYGON);
        });
        document.getElementById('btnDrawCircle')?.addEventListener('click', () => {
            if (!initDrawingManager()) {
                showNotification('Drawing tools unavailable', 'error');
                return;
            }
            currentDrawing?.setMap(null);
            drawingManager.setDrawingMode(google.maps.drawing.OverlayType.CIRCLE);
        });
        document.getElementById('btnSaveGeofence')?.addEventListener('click', saveGeofence);
        document.getElementById('btnCancelGeofence')?.addEventListener('click', () => {
            currentDrawing?.setMap(null);
            drawingManager?.setDrawingMode(null);
            document.getElementById('btnSaveGeofence').disabled = true;
            document.getElementById('geofencePanel')?.classList.remove('active');
        });
    }

    function bindControls() {
        document.getElementById('btnRecenter')?.addEventListener('click', () => {
            centerOnVehicle();
        });
        document.getElementById('btnCenterVehicle')?.addEventListener('click', centerOnVehicle);
        document.getElementById('btnFollow')?.addEventListener('click', () => {
            followVehicle = !followVehicle;
            fleetRenderer?.setFollowVehicle(followVehicle);
            document.getElementById('btnFollow')?.classList.toggle('active', followVehicle);
            showNotification(followVehicle ? 'Follow mode on' : 'Follow mode off', 'info');
        });
        document.getElementById('btnTraffic')?.addEventListener('click', () => {
            trafficLayer.setMap(trafficLayer.getMap() ? null : map);
        });
        document.querySelectorAll('[data-playback-fab]').forEach((el) => {
            el.addEventListener('click', (e) => {
                e.stopPropagation();
                if (!playbackPoints.length) {
                    showNotification('Load route history first', 'info');
                    return;
                }
                setPlaybackPanelOpen(true);
                if (!isPlaying) {
                    startPlayback();
                }
            });
        });
        document.getElementById('btnGeofence')?.addEventListener('click', () => {
            document.getElementById('geofencePanel')?.classList.toggle('active');
        });
        document.getElementById('btnClear')?.addEventListener('click', clearRoute);
        document.getElementById('playbackClose')?.addEventListener('click', () => {
            if (isPlaying) pausePlayback();
            setPlaybackPanelOpen(false);
        });
        document.getElementById('geofenceClose')?.addEventListener('click', () => {
            document.getElementById('geofencePanel')?.classList.remove('active');
        });
        document.getElementById('applyFilter')?.addEventListener('click', applyDateFilter);
        document.getElementById('reverseBtn')?.addEventListener('click', reverseGeocode);
        document.getElementById('btnCopyCoords')?.addEventListener('click', copyLiveCoords);
        document.getElementById('btnOpenMaps')?.addEventListener('click', openInGoogleMaps);
        document.getElementById('btnStreetView')?.addEventListener('click', openStreetView);
        document.getElementById('btnFitRoute')?.addEventListener('click', fitRouteBounds);
        document.getElementById('btnHeatmap')?.addEventListener('click', toggleHeatmap);
        document.getElementById('btnNightMode')?.addEventListener('click', toggleNightMode);
        document.getElementById('btnExportRoute')?.addEventListener('click', exportRouteCsv);
        document.getElementById('btnExportCsv')?.addEventListener('click', exportRouteCsv);
        document.getElementById('btnExportGpx')?.addEventListener('click', exportRouteGpx);
        document.getElementById('btnStops')?.addEventListener('click', toggleStopMarkers);
        document.getElementById('btnEventMarkers')?.addEventListener('click', () => {
            showsEventMarkers = !showsEventMarkers;
            document.getElementById('btnEventMarkers')?.classList.toggle('active', showsEventMarkers);
            if (showsEventMarkers && historyData.length) {
                renderEventMarkers(detectRouteEvents(historyData));
            } else {
                clearEventMarkers();
            }
            showNotification(showsEventMarkers ? mi('eventsShown', 'Route events shown') : mi('eventsHidden', 'Route events hidden'), 'info');
        });

        document.querySelectorAll('#layerControls [data-layer]').forEach((btn) => {
            btn.addEventListener('click', () => {
                const layer = btn.dataset.layer;
                if (!layer) return;
                map.setMapTypeId(layer);
                document.querySelectorAll('#layerControls [data-layer]').forEach((b) => b.classList.remove('active'));
                btn.classList.add('active');
            });
        });

        window.addEventListener('map-sidebar-toggled', resizeMap);

        bindDrawingControls();

        document.getElementById('pbPlayPause')?.addEventListener('click', togglePlayPause);
        document.getElementById('pbStop')?.addEventListener('click', stopPlayback);
        document.getElementById('pbStepBack')?.addEventListener('click', () => {
            if (!playbackPoints.length) return;
            pausePlayback();
            playbackIndex = Math.max(0, playbackIndex - 1);
            updatePlaybackAtIndex(playbackIndex);
        });
        document.getElementById('pbRewind')?.addEventListener('click', rewindPlayback);
        document.getElementById('pbStepForward')?.addEventListener('click', () => {
            if (!playbackPoints.length) return;
            pausePlayback();
            playbackIndex = Math.min(playbackPoints.length - 1, playbackIndex + 1);
            updatePlaybackAtIndex(playbackIndex);
        });
        document.getElementById('playbackProgress')?.addEventListener('click', scrubPlayback);
        document.querySelectorAll('.speed-btn').forEach((btn) => {
            btn.addEventListener('click', () => {
                playbackSpeed = parseFloat(btn.dataset.speed);
                document.querySelectorAll('.speed-btn').forEach((b) => b.classList.remove('active'));
                btn.classList.add('active');
                if (isPlaying) { pausePlayback(); startPlayback(); }
            });
        });
    }

    function getPlaybackFabEls() {
        return document.querySelectorAll('[data-playback-fab]');
    }

    function updatePlaybackFab() {
        const hasRoute = playbackPoints.length > 0;
        getPlaybackFabEls().forEach((fab) => {
            fab.disabled = !hasRoute;
        });
    }

    function setPlaybackPanelOpen(open) {
        const panel = document.getElementById('playbackPanel');
        const mapArea = document.getElementById('mapArea');
        if (!panel) return;
        panel.classList.toggle('active', open);
        mapArea?.classList.toggle('playback-open', open);
        getPlaybackFabEls().forEach((fab) => fab.classList.toggle('smart-btn--hidden', open));
        if (open) setTimeout(resizeMap, 400);
    }

    function formatClock(date) {
        if (!date || Number.isNaN(date.getTime())) return '00:00';
        return date.toLocaleTimeString([], { hour: '2-digit', minute: '2-digit', second: '2-digit' });
    }

    function formatDuration(seconds) {
        const s = Math.max(0, Math.floor(seconds));
        const h = Math.floor(s / 3600);
        const m = Math.floor((s % 3600) / 60);
        const sec = s % 60;
        if (h > 0) return `${h}:${String(m).padStart(2, '0')}:${String(sec).padStart(2, '0')}`;
        return `${String(m).padStart(2, '0')}:${String(sec).padStart(2, '0')}`;
    }

    function getPlaybackDurationSec() {
        if (playbackPoints.length < 2) return 0;
        const start = new Date(playbackPoints[0].recorded_at).getTime();
        const end = new Date(playbackPoints[playbackPoints.length - 1].recorded_at).getTime();
        if (Number.isNaN(start) || Number.isNaN(end) || end <= start) return playbackPoints.length;
        return (end - start) / 1000;
    }

    function updatePlaybackMeta() {
        const total = playbackPoints.length;
        const subtitle = document.getElementById('playbackSubtitle');
        if (subtitle) {
            subtitle.textContent = total
                ? `${total} points · ${isPlaying ? 'Playing' : 'Ready'}`
                : 'Load history to start';
        }
        setText('pbPointTotal', String(total));
    }

    function setPlayPauseUi(playing) {
        const btn = document.getElementById('pbPlayPause');
        const icon = document.getElementById('pbPlayPauseIcon');
        if (btn) btn.classList.toggle('is-playing', playing);
        if (icon) icon.className = playing ? 'fas fa-pause' : 'fas fa-play';
    }

    function togglePlayPause() {
        if (!playbackPoints.length) return showNotification('Load route history first', 'info');
        if (isPlaying) pausePlayback();
        else startPlayback();
    }

    function rewindPlayback() {
        if (!playbackPoints.length) return;
        pausePlayback();
        playbackIndex = 0;
        updatePlaybackAtIndex(0);
    }

    function scrubPlayback(e) {
        if (!playbackPoints.length) return;
        const rect = e.currentTarget.getBoundingClientRect();
        const percent = Math.min(1, Math.max(0, (e.clientX - rect.left) / rect.width));
        playbackIndex = Math.min(playbackPoints.length - 1, Math.floor(percent * playbackPoints.length));
        pausePlayback();
        updatePlaybackAtIndex(playbackIndex);
    }

    function updatePlaybackAtIndex(index) {
        const p = playbackPoints[index];
        if (!p) return;
        routeScrubUsesMotion = true;
        updateCurrentMarker(p, true);
        setText('pbLiveSpeed', parseFloat(p.speed || 0).toFixed(0));
        setText('pbPointIndex', String(index + 1));
        updatePlaybackProgress();
        if (followVehicle) map.panTo({ lat: p.lat, lng: p.lng });
    }

    function animatePlaybackToIndex(nextIndex, onDone) {
        const from = playbackPoints[playbackIndex];
        const to = playbackPoints[nextIndex];
        if (!from || !to || playbackIndex === nextIndex) {
            updatePlaybackAtIndex(nextIndex);
            onDone?.();
            return;
        }
        if (playbackAnimFrame) cancelAnimationFrame(playbackAnimFrame);
        playbackAnimFrame = null;
        const fromH = parseFloat(from.heading || 0);
        const renderer = ensureFleetRenderer();
        renderer?.setPlaybackActive(true);
        renderer?.cancelAnimation();
        const animPoint = {
            ...to,
            _fromHeading: fromH,
        };
        renderer?.setCurrentVehicle(animPoint, {
            skipAnimation: false,
            animDurationMs: Math.max(200, 1000 / playbackSpeed),
            onComplete: () => {
                playbackAnimFrame = null;
                playbackIndex = nextIndex;
                setText('pbLiveSpeed', parseFloat(to.speed || 0).toFixed(0));
                updatePlaybackAtIndex(nextIndex);
                onDone?.();
            },
        });
    }

    function advancePlaybackStep() {
        if (!isPlaying) return;
        if (playbackIndex >= playbackPoints.length - 1) {
            stopPlayback();
            showNotification('Playback finished', 'success');
            return;
        }
        const nextIndex = playbackIndex + 1;
        animatePlaybackToIndex(nextIndex, () => {
            if (isPlaying) {
                playbackTimer = setTimeout(advancePlaybackStep, 80);
            }
        });
    }

    /** History search button loading state. */
    function setHistorySearchLoading(loading) {
        const btn = document.getElementById('applyFilter');
        if (!btn) return;
        btn.disabled = loading;
        btn.classList.toggle('is-loading', loading);
        const spinner = btn.querySelector('.search-btn__spinner');
        const label = btn.querySelector('.search-btn__label');
        if (spinner) spinner.hidden = !loading;
        if (label) label.style.visibility = loading ? 'hidden' : '';
    }

    function setActiveDatePreset(preset) {
        document.querySelectorAll('[data-date-preset]').forEach((btn) => {
            btn.classList.toggle('is-active', preset && btn.getAttribute('data-date-preset') === preset);
        });
    }

    function clearActiveDatePreset() {
        setActiveDatePreset(null);
    }

    function formatAppDateYmd(d) {
        if (window.AppDateTime?.formatDateYmd) {
            return window.AppDateTime.formatDateYmd(d);
        }
        const y = d.getFullYear();
        const m = String(d.getMonth() + 1).padStart(2, '0');
        const day = String(d.getDate()).padStart(2, '0');
        return `${y}-${m}-${day}`;
    }

    function appTodayYmd() {
        return window.AppDateTime?.todayYmd?.() ?? formatAppDateYmd(new Date());
    }

    function shiftAppYmd(ymd, deltaDays) {
        if (window.AppDateTime?.shiftYmd) {
            return window.AppDateTime.shiftYmd(ymd, deltaDays);
        }
        const d = new Date(ymd);
        d.setDate(d.getDate() + deltaDays);
        return formatAppDateYmd(d);
    }

    function getFlatpickrYmd(fp) {
        if (!fp?.selectedDates?.length) {
            return (fp?.input?.value || '').trim();
        }
        return fp.formatDate(fp.selectedDates[0], 'Y-m-d');
    }

    function normalizeDateRange(from, to) {
        if (!from) return null;
        const start = ensureYmdDate(from);
        const end = ensureYmdDate(to || from);
        if (!start || !end) return null;
        if (start <= end) {
            return { from: start, to: end };
        }
        return { from: end, to: start };
    }

    function ensureYmdDate(value) {
        if (!value) return null;
        const s = String(value).trim();
        if (/^\d{4}-\d{2}-\d{2}$/.test(s)) return s;
        const ms = parseRouteTimestampMs(s);
        if (ms == null) return null;
        if (window.AppDateTime?.formatDateYmd) {
            return window.AppDateTime.formatDateYmd(new Date(ms));
        }
        const d = new Date(ms);
        const y = d.getFullYear();
        const m = String(d.getMonth() + 1).padStart(2, '0');
        const day = String(d.getDate()).padStart(2, '0');
        return `${y}-${m}-${day}`;
    }

    function parseDateRangeInput() {
        const raw = document.getElementById('dateRange')?.value?.trim() || '';
        if (!raw) return null;

        const rangeMatch = raw.match(/^(\d{4}-\d{2}-\d{2})\s*(?:to|-)\s*(\d{4}-\d{2}-\d{2})$/i);
        if (rangeMatch) return { from: rangeMatch[1], to: rangeMatch[2] };

        if (/^\d{4}-\d{2}-\d{2}$/.test(raw)) return { from: raw, to: raw };

        return null;
    }

    function resolveFilterDates() {
        const from = getFlatpickrYmd(flatpickrFrom);
        const to = getFlatpickrYmd(flatpickrTo);
        return normalizeDateRange(from, to);
    }

    function isLast24HoursPresetActive() {
        return document.querySelector('[data-date-preset="24h"]')?.classList.contains('is-active') === true;
    }

    function applyDatePreset(preset, triggerSearch = false) {
        const today = appTodayYmd();
        let from = today;
        let to = today;

        switch (preset) {
            case '24h':
                if (flatpickrFrom) flatpickrFrom.clear();
                if (flatpickrTo) flatpickrTo.clear();
                setActiveDatePreset('24h');
                if (triggerSearch) loadHistory();
                return;
            case 'today':
                break;
            case 'yesterday':
                from = shiftAppYmd(today, -1);
                to = from;
                break;
            case '7d':
                from = shiftAppYmd(today, -6);
                break;
            case '30d':
                from = shiftAppYmd(today, -29);
                break;
            default:
                return;
        }

        if (flatpickrFrom) flatpickrFrom.setDate(from, false);
        if (flatpickrTo) flatpickrTo.setDate(to, false);
        setActiveDatePreset(preset);

        if (triggerSearch) {
            loadHistory(from, to);
        }
    }

    async function loadHistory(from = null, to = null) {
        const explicitRange = !!(from && (to || from));
        const range = explicitRange ? normalizeDateRange(from, to || from) : null;
        const fromParam = range?.from ?? null;
        const toParam = range?.to ?? null;
        const useLast24Hours = !fromParam && !toParam;

        debugGpsLog('loadHistory', {
            from: fromParam,
            to: toParam,
            useLast24Hours,
            explicitRange,
        });
        setHistorySearchLoading(true);
        showLoading(useLast24Hours ? 'Loading last 24 hours...' : 'Loading route history...');
        try {
            let url = historyUrl;
            if (fromParam) {
                url += `?from=${encodeURIComponent(fromParam)}&to=${encodeURIComponent(toParam || fromParam)}`;
                if (debugGps) {
                    url += '&debug_gps=1';
                }
            }
            debugGpsLog('history request', { url, from: fromParam, to: toParam });
            const response = await fetch(url);
            const json = await parseJsonResponse(response);
            if (handleMapAccessDenied(response, json)) return;
            if (!response.ok) throw new Error('HTTP ' + response.status);
            const historyFallback = response.headers.get('X-History-Fallback') || '';
            const data = sortHistoryPoints(
                normalizeResponse(json).map(normalizePoint).filter(Boolean)
            );
            debugGpsLog('history response', {
                points: data.length,
                fallback: historyFallback,
                countHeader: response.headers.get('X-History-Count'),
                fromBound: response.headers.get('X-History-From-Bound'),
                toBound: response.headers.get('X-History-To-Bound'),
                first: data[0]?.recorded_at,
                last: data[data.length - 1]?.recorded_at,
                gsm: data[data.length - 1]?.gsm_signal,
                satellites: data[data.length - 1]?.satellites,
            });

            const renderer = ensureFleetRenderer();
            renderer?.clearRoute({ keepVehicle: true, keepRealtime: true });
            polylines = [];
            routeGlowPolylines = [];
            clearEventMarkers();
            renderer?.clearExtraMarkers();
            markers = currentPositionMarker ? [currentPositionMarker] : [];

            if (!data.length) {
                if (explicitRange) {
                    clearRoute();
                }
                const emptyMsg = historyFallback === 'selected_period_empty' || explicitRange
                    ? mi('historyFallbackSelectedPeriod', 'No GPS data for the selected date range')
                    : (useLast24Hours ? mi('noGps24h', 'No GPS data in the last 24 hours') : 'No history for selected period');
                showNotification(emptyMsg, 'info');
                if (useLast24Hours && lastTelemetry) {
                    applyLivePoint(lastTelemetry);
                }
                return;
            }

            historyData = data;

            const bounds = renderer.drawRoute(data, {
                clickable: true,
                haversineDistance,
                onSegmentClick: (line, latLng) => showPolylineInfo(line, latLng),
                startTitle: mi('routeStart', 'Route start'),
                endTitle: mi('routeEnd', 'Route end'),
                updateCurrent: false,
            });

            polylines = renderer.polylines;
            routeGlowPolylines = renderer.glowPolylines;
            startMarker = renderer.startMarker;
            endMarker = renderer.endMarker;
            if (startMarker) markers.push(startMarker);
            if (endMarker) markers.push(endMarker);

            renderEventMarkers(detectRouteEvents(data));
            if (showsEventMarkers) {
                document.getElementById('btnEventMarkers')?.classList.add('active');
            }
            lastRealtimePoint = { lat: data[data.length - 1].lat, lng: data[data.length - 1].lng };
            routeScrubUsesMotion = true;
            updateCurrentMarker(data[data.length - 1], true);

            if (bounds && data.length < 100) {
                renderer.fitBounds(bounds, 56);
            } else {
                renderer.focusOnVehicle(16);
            }

            playbackPoints = data;
            playbackIndex = 0;
            updatePlaybackMeta();
            updatePlaybackProgress();
            updatePlaybackFab();
            setPlaybackPanelOpen(false);
            updateRouteSummary(data);
            if (historyFallback) {
                const fallbackKeys = {
                    last_known_activity: 'historyFallbackLastKnownActivity',
                    last_activity_day: 'historyFallbackLastActivityDay',
                    '30_days': 'historyFallback30Days',
                    selected_period_empty: 'historyFallbackSelectedPeriod',
                };
                const i18nKey = fallbackKeys[historyFallback] || 'historyFallbackLastKnownActivity';
                const fallbackMsg = mi(i18nKey, `Loaded ${data.length} GPS points from last known activity`);
                showNotification(fallbackMsg.replace(':count', String(data.length)), 'info');
            } else {
                showNotification(
                    useLast24Hours
                        ? `Loaded ${data.length} GPS points (last 24 hours)`
                        : `Loaded ${data.length} GPS points`,
                    'success'
                );
            }
        } catch (err) {
            showNotification('Failed to load history: ' + err.message, 'error');
        } finally {
            setHistorySearchLoading(false);
            hideLoading();
        }
    }

    function showPolylineInfo(polyline, latLng) {
        const d = polyline?._segmentData;
        if (!d) return;
        const content = document.getElementById('polylineInfoTemplate')?.cloneNode(true);
        if (!content) return;
        content.style.display = 'block';
        content.querySelector('#statSpeed').textContent = d.speed.toFixed(1) + ' km/h';
        content.querySelector('#statDistance').textContent = (d.distance * 1000).toFixed(0) + ' m';
        content.querySelector('#detailStartTime').textContent = d.startTime ? new Date(d.startTime).toLocaleTimeString() : dash();
        content.querySelector('#detailEndTime').textContent = d.endTime ? new Date(d.endTime).toLocaleTimeString() : dash();
        customInfoWindow.setContent(content);
        customInfoWindow.setPosition(latLng);
        customInfoWindow.open(map);
    }

    function addStartEndMarkers(start, end) {
        const renderer = ensureFleetRenderer();
        renderer?.setRouteEndpoints(start, end, {
            startTitle: mi('routeStart', 'Route start'),
            endTitle: mi('routeEnd', 'Route end'),
            updateCurrent: true,
        });
        startMarker = renderer?.startMarker ?? null;
        endMarker = renderer?.endMarker ?? null;
        if (startMarker) markers.push(startMarker);
        if (endMarker) markers.push(endMarker);
        currentPositionMarker = renderer?.getMarker() ?? currentPositionMarker;
    }

    function clearRoute() {
        ensureFleetRenderer()?.clearRoute({ keepVehicle: true });
        polylines = [];
        routeGlowPolylines = [];
        realtimePolylines = [];
        clearEventMarkers();
        startMarker = null;
        endMarker = null;
        document.getElementById('btnEventMarkers')?.classList.remove('active');
        markers = currentPositionMarker ? [currentPositionMarker] : [];
        stopPlayback();
        historyData = [];
        playbackPoints = [];
        routeStops = [];
        if (heatmapLayer) { heatmapLayer.setMap(null); heatmapLayer = null; }
        document.getElementById('btnHeatmap')?.classList.remove('active');
        document.getElementById('heatmapLegend') && (document.getElementById('heatmapLegend').style.display = 'none');
        clearStopMarkers();
        showsStops = false;
        document.getElementById('btnStops')?.classList.remove('active');
        setPlaybackPanelOpen(false);
        setRouteSummarySheetVisible(false);
        updatePlaybackMeta();
        updatePlaybackFab();
        renderTripEvents([], 0);
        showNotification('Route cleared', 'info');
    }

    function startPlayback() {
        if (!playbackPoints.length) return showNotification('No playback data', 'error');
        if (playbackIndex >= playbackPoints.length) playbackIndex = 0;
        playbackActive = true;
        ensureFleetRenderer()?.setPlaybackActive(true);
        isPlaying = true;
        setPlayPauseUi(true);
        updatePlaybackMeta();
        clearInterval(playbackTimer);
        clearTimeout(playbackTimer);
        if (playbackAnimFrame) cancelAnimationFrame(playbackAnimFrame);
        advancePlaybackStep();
    }

    function pausePlayback() {
        clearInterval(playbackTimer);
        clearTimeout(playbackTimer);
        if (playbackAnimFrame) cancelAnimationFrame(playbackAnimFrame);
        playbackAnimFrame = null;
        isPlaying = false;
        setPlayPauseUi(false);
        updatePlaybackMeta();
    }

    function stopPlayback() {
        clearInterval(playbackTimer);
        clearTimeout(playbackTimer);
        if (playbackAnimFrame) cancelAnimationFrame(playbackAnimFrame);
        playbackAnimFrame = null;
        isPlaying = false;
        playbackActive = false;
        ensureFleetRenderer()?.setPlaybackActive(false);
        playbackIndex = 0;
        setPlayPauseUi(false);
        setText('pbLiveSpeed', '0');
        setText('pbPointIndex', '0');
        updatePlaybackProgress();
        updatePlaybackMeta();
        if (lastTelemetry) {
            updateCurrentMarker(lastTelemetry, true);
        }
    }

    function updatePlaybackProgress() {
        const total = playbackPoints.length;
        const idx = Math.min(playbackIndex, total);
        const percent = total > 1 ? (idx / (total - 1)) * 100 : 0;

        const bar = document.getElementById('playbackProgressBar');
        const thumb = document.getElementById('playbackProgressThumb');
        if (bar) bar.style.width = percent + '%';
        if (thumb) thumb.style.left = percent + '%';

        const durationSec = getPlaybackDurationSec();
        let currentSec = 0;
        if (total > 0 && playbackPoints[0]?.recorded_at) {
            const start = new Date(playbackPoints[0].recorded_at).getTime();
            const cur = playbackPoints[Math.min(idx, total - 1)]?.recorded_at;
            if (cur) currentSec = (new Date(cur).getTime() - start) / 1000;
        } else {
            currentSec = idx;
        }

        setText('playbackTimeCurrent', formatDuration(currentSec));
        setText('playbackTimeTotal', formatDuration(durationSec));
        setText('pbPointIndex', String(total ? Math.min(idx + 1, total) : 0));
        setText('pbPointTotal', String(total));
    }

    function setRouteSummarySheetExpanded(expanded) {
        const sheet = document.getElementById('routeSummarySheet');
        const toggle = document.getElementById('routeSummaryToggle');
        if (!sheet) return;
        sheet.classList.toggle('is-expanded', expanded);
        toggle?.setAttribute('aria-expanded', expanded ? 'true' : 'false');
    }

    function setRouteSummarySheetVisible(visible) {
        const sheet = document.getElementById('routeSummarySheet');
        if (!sheet) return;
        sheet.hidden = !visible;
        sheet.setAttribute('aria-hidden', visible ? 'false' : 'true');
        sheet.classList.toggle('is-visible', visible);
        if (!visible) {
            setRouteSummarySheetExpanded(false);
        }
    }

    function setHudRouteExpanded(expanded) {
        const section = document.getElementById('hudRouteSection');
        const toggle = document.getElementById('hudRouteToggle');
        if (!section) return;
        section.classList.toggle('is-expanded', expanded);
        toggle?.setAttribute('aria-expanded', expanded ? 'true' : 'false');
        const chevron = toggle?.querySelector('.map-hud__chevron');
        if (chevron) {
            chevron.className = 'fas ' + (expanded ? 'fa-chevron-up' : 'fa-chevron-down') + ' map-hud__chevron';
        }
    }

    function setHudRouteVisible(visible) {
        const section = document.getElementById('hudRouteSection');
        if (!section) return;
        section.hidden = !visible;
        if (!visible) setHudRouteExpanded(false);
    }

    function initHudRouteSummary() {
        const toggle = document.getElementById('hudRouteToggle');
        toggle?.addEventListener('click', (e) => {
            e.preventDefault();
            e.stopPropagation();
            const section = document.getElementById('hudRouteSection');
            setHudRouteExpanded(!section?.classList.contains('is-expanded'));
        });
    }

    function initRouteSummarySheet() {
        const sheet = document.getElementById('routeSummarySheet');
        const toggle = document.getElementById('routeSummaryToggle');
        const handle = document.getElementById('routeSummaryHandle');
        const header = document.getElementById('routeSummaryHeader');
        if (!sheet) return;

        setRouteSummarySheetExpanded(false);

        const flip = (e) => {
            e?.preventDefault();
            e?.stopPropagation();
            setRouteSummarySheetExpanded(!sheet.classList.contains('is-expanded'));
        };

        toggle?.addEventListener('click', flip);
        handle?.addEventListener('click', flip);
        header?.addEventListener('click', (e) => {
            if (e.target.closest('#routeSummaryToggle')) return;
            flip(e);
        });

        let dragStartY = null;
        handle?.addEventListener('pointerdown', (e) => {
            dragStartY = e.clientY;
            handle.setPointerCapture?.(e.pointerId);
        });
        handle?.addEventListener('pointerup', (e) => {
            if (dragStartY == null) return;
            const delta = dragStartY - e.clientY;
            dragStartY = null;
            if (Math.abs(delta) < 24) return;
            setRouteSummarySheetExpanded(delta > 0);
        });
    }

    function formatRouteTimestamp(ts) {
        if (!ts) return dash();
        return window.AppDateTime?.formatDateTime(ts) ?? new Date(ts).toLocaleString();
    }

    function updateRouteSummary(data) {
        if (!data.length) {
            setHudRouteVisible(false);
            setRouteSummarySheetVisible(false);
            return;
        }
        const baseStats = analyzeRoute(data);
        if (!baseStats) return;
        const stats = enrichRouteStats(data, baseStats);

        routeStops = stats.stops;
        const startTs = stats.startTs;
        const endTs = stats.endTs;
        const dur = stats.totalSec;
        const avg = stats.avgSpeed > 0 ? stats.avgSpeed.toFixed(1) : '0';

        const startLabel = formatRouteTimestamp(startTs);
        const endLabel = formatRouteTimestamp(endTs);
        const distLabel = stats.dist.toFixed(2) + ' km';
        const durLabel = formatDurationLong(dur);
        const avgLabel = avg + ' km/h';
        const maxLabel = stats.maxSpeed.toFixed(1) + ' km/h';
        const startPt = `${Number(data[0].lat).toFixed(5)}, ${Number(data[0].lng).toFixed(5)}`;
        const endPt = `${Number(data[data.length - 1].lat).toFixed(5)}, ${Number(data[data.length - 1].lng).toFixed(5)}`;
        const stopCount = String(stats.stops.length);
        const peek = distLabel;

        setText('totalDistance', distLabel);
        setText('routeStartTime', startLabel);
        setText('routeEndTime', endLabel);
        setText('routeDuration', durLabel);
        setText('avgSpeed', avgLabel);
        setText('maxSpeed', maxLabel);
        setText('movingTime', formatDurationLong(stats.movingSec));
        setText('stoppedTime', formatDurationLong(stats.stoppedSec));
        setText('overspeedCount', String(stats.overspeedEvents));
        setText('idleCount', stopCount);
        setText('idleTotal', formatDurationLong(stats.stoppedSec));

        setText('rssStartTime', startLabel);
        setText('rssEndTime', endLabel);
        setText('rssDistance', distLabel);
        setText('rssDuration', durLabel);
        setText('rssAvgSpeed', avgLabel);
        setText('rssMaxSpeed', maxLabel);
        setText('rssStartPoint', startPt);
        setText('rssEndPoint', endPt);
        setText('rssStopCount', stopCount);

        setText('hudRouteStart', startLabel);
        setText('hudRouteEnd', endLabel);
        setText('hudRouteDistance', distLabel);
        setText('hudRouteMaxSpeed', maxLabel);
        setText('hudRouteAvgSpeed', avgLabel);
        setText('hudRouteDuration', durLabel);
        setText('hudRoutePeek', peek);

        updateRouteSummaryLive(data[data.length - 1] || lastTelemetry);

        setHudRouteVisible(true);
        setHudRouteExpanded(false);
        setRouteSummarySheetVisible(false);

        renderTripEvents(stats.stops, stats.overspeedEvents);
        if (showsStops) renderStopMarkers(routeStops);
    }

    function initDateFilter() {
        try {
            const onFromChange = () => {
                clearActiveDatePreset();
                const fromDate = flatpickrFrom?.selectedDates?.[0];
                if (fromDate && flatpickrTo) {
                    flatpickrTo.set('minDate', fromDate);
                    const toDate = flatpickrTo.selectedDates[0];
                    if (toDate && toDate < fromDate) {
                        flatpickrTo.setDate(fromDate, false);
                    }
                }
            };
            const onToChange = () => clearActiveDatePreset();

            const baseOpts = {
                dateFormat: 'Y-m-d',
                altInput: true,
                altFormat: 'j M Y',
                allowInput: false,
                maxDate: 'today',
                disableMobile: true,
                altInputClass: 'date-field__input date-field__input--display',
            };

            flatpickrFrom = flatpickr('#dateFrom', { ...baseOpts, onChange: onFromChange });
            flatpickrTo = flatpickr('#dateTo', { ...baseOpts, onChange: onToChange });

            document.querySelectorAll('[data-date-preset]').forEach((btn) => {
                btn.addEventListener('click', () => {
                    applyDatePreset(btn.getAttribute('data-date-preset'), true);
                });
            });
            applyDatePreset('24h', false);
        } catch (e) { /* flatpickr optional */ }
    }

    function applyDateFilter() {
        if (isLast24HoursPresetActive()) {
            loadHistory();
            return;
        }
        const range = resolveFilterDates();
        if (range) {
            loadHistory(range.from, range.to);
            return;
        }
        clearActiveDatePreset();
        loadHistory();
    }

    function reverseGeocode() {
        const pos = getLivePosition();
        if (!pos) return showNotification('No vehicle position', 'error');
        setText('addressBox', mi('addressLoading', 'Loading address…'));
        fetch(`${reverseGeocodeUrl}?lat=${pos.lat}&lng=${pos.lng}`, { credentials: 'same-origin' })
            .then((r) => r.json())
            .then((data) => setText('addressBox', data.address || 'Address not found'))
            .catch(() => setText('addressBox', 'Error loading address'));
    }

    function escapeHtml(text) {
        const el = document.createElement('div');
        el.textContent = text ?? '';
        return el.innerHTML;
    }

    function geofenceListItemHtml(g) {
        const name = escapeHtml(g.name || 'Unnamed');
        const type = escapeHtml(g.type || 'zone');
        return `
            <div class="geofence-list-item" data-geofence-id="${g.id}">
                <div class="geofence-list-item__info">
                    <strong>${name}</strong>
                    <small>${type}</small>
                </div>
                <div class="geofence-list-item__actions">
                    <button type="button" class="geofence-action-btn geofence-zoom-btn" data-id="${g.id}" title="Zoom to zone" aria-label="Zoom to zone">
                        <i class="fas fa-search-plus"></i>
                    </button>
                    <button type="button" class="geofence-action-btn geofence-action-btn--danger geofence-delete-btn" data-id="${g.id}" title="Remove geofence" aria-label="Remove geofence">
                        <i class="fas fa-trash-alt"></i>
                    </button>
                </div>
            </div>`;
    }

    function removeGeofenceFromUi(id) {
        const layer = findGeofenceLayer(id);
        if (layer) {
            layer.setMap?.(null);
            geofences = geofences.filter((item) => item._geofenceId !== id);
        }
        document.querySelector(`#geofenceList [data-geofence-id="${id}"]`)?.remove();
        const listEl = document.getElementById('geofenceList');
        if (listEl && !listEl.querySelector('[data-geofence-id]')) {
            listEl.innerHTML = '<div class="geofence-list-empty">No geofences yet. Draw one on the map.</div>';
        }
    }

    function findGeofenceLayer(id) {
        return geofences.find((layer) => layer._geofenceId === id);
    }

    window.zoomToGeofence = function (id) {
        const layer = findGeofenceLayer(id);
        if (!layer) return showNotification('Geofence not found on map', 'error');
        if (layer.getBounds) map.fitBounds(layer.getBounds());
        else if (layer.getCenter) { map.setCenter(layer.getCenter()); map.setZoom(15); }
        showNotification('Zoomed to geofence', 'info');
    };

    window.deleteGeofence = async function (id) {
        const numericId = parseInt(id, 10);
        if (!numericId) return;

        const meta = findGeofenceLayer(numericId)?._geofenceMeta;
        const label = meta?.name ? `"${meta.name}"` : 'this geofence';
        if (!confirm(`Remove ${label}? This cannot be undone.`)) return;

        showLoading('Removing geofence...');
        try {
            const res = await fetch(`${geofenceDestroyBase}/${numericId}`, {
                method: 'DELETE',
                credentials: 'same-origin',
                headers: {
                    'X-CSRF-TOKEN': csrfToken,
                    Accept: 'application/json',
                    'X-Requested-With': 'XMLHttpRequest',
                },
            });
            const json = await res.json().catch(() => ({}));
            if (!res.ok) {
                throw new Error(json.message || json.error || `Delete failed (${res.status})`);
            }

            customInfoWindow?.close();
            removeGeofenceFromUi(numericId);
            showNotification(json.message || 'Geofence removed', 'success');

            await loadGeofences();
        } catch (e) {
            showNotification(e.message || 'Failed to remove geofence', 'error');
        } finally {
            hideLoading();
        }
    };

    function showGeofenceInfo(layer, position) {
        const meta = layer._geofenceMeta;
        if (!meta) return;
        customInfoWindow.setContent(`
            <div style="padding:12px;min-width:200px;">
                <strong>${escapeHtml(meta.name)}</strong><br>
                <small>Type: ${escapeHtml(meta.type)}</small>
                <div style="margin-top:10px;display:flex;gap:8px;">
                    <button type="button" class="btn btn-sm btn-outline-secondary" onclick="window.zoomToGeofence(${meta.id})">Zoom</button>
                    <button type="button" class="btn btn-sm btn-outline-danger" onclick="window.deleteGeofence(${meta.id})">Remove</button>
                </div>
            </div>`);
        customInfoWindow.setPosition(position);
        customInfoWindow.open(map);
    }

    function bindGeofenceListActions() {
        const el = document.getElementById('geofenceList');
        if (!el || el.dataset.bound) return;
        el.dataset.bound = '1';
        el.addEventListener('click', (e) => {
            const deleteBtn = e.target.closest('.geofence-delete-btn');
            const zoomBtn = e.target.closest('.geofence-zoom-btn');
            if (deleteBtn) {
                e.preventDefault();
                e.stopPropagation();
                window.deleteGeofence(parseInt(deleteBtn.dataset.id, 10));
            }
            if (zoomBtn) {
                e.preventDefault();
                e.stopPropagation();
                window.zoomToGeofence(parseInt(zoomBtn.dataset.id, 10));
            }
        });
    }

    async function loadGeofences() {
        try {
            const res = await fetch(geofencesUrl, { credentials: 'same-origin' });
            const list = await parseJsonResponse(res);
            if (handleMapAccessDenied(res, list)) return;
            geofences.forEach((g) => g.setMap?.(null));
            geofences = [];
            const listEl = document.getElementById('geofenceList');
            if (listEl) listEl.innerHTML = '';
            const items = Array.isArray(list) ? list : [];
            items.forEach((g) => {
                let layer = null;
                if (g.type === 'polygon' && g.coords?.length) {
                    layer = new google.maps.Polygon({
                        paths: g.coords.map((c) => ({ lat: parseFloat(c[0]), lng: parseFloat(c[1]) })),
                        strokeColor: '#8e44ad', fillColor: '#8e44ad', fillOpacity: 0.12, map,
                    });
                } else if (g.type === 'circle' && g.center) {
                    layer = new google.maps.Circle({
                        center: { lat: parseFloat(g.center[0]), lng: parseFloat(g.center[1]) },
                        radius: parseFloat(g.radius), strokeColor: '#2980b9', fillColor: '#2980b9', fillOpacity: 0.12, map,
                    });
                }
                if (layer) {
                    layer._geofenceId = g.id;
                    layer._geofenceMeta = g;
                    google.maps.event.addListener(layer, 'click', (e) => showGeofenceInfo(layer, e.latLng));
                    geofences.push(layer);
                }
                if (listEl) listEl.insertAdjacentHTML('beforeend', geofenceListItemHtml(g));
            });
            if (listEl && !items.length) {
                listEl.innerHTML = '<div class="geofence-list-empty">No geofences yet. Draw one on the map.</div>';
            }
            bindGeofenceListActions();
        } catch (e) {
            showNotification('Error loading geofences', 'error');
        }
    }

    async function saveGeofence() {
        if (!currentDrawing) return showNotification('Draw a shape first', 'error');
        const name = prompt('Geofence name:', 'New Geofence');
        if (!name) return;
        const payload = { name, device_id: deviceId };
        if (currentDrawing instanceof google.maps.Polygon) {
            payload.type = 'polygon';
            const path = currentDrawing.getPath();
            payload.coords = [];
            for (let i = 0; i < path.getLength(); i++) {
                const ll = path.getAt(i);
                payload.coords.push([ll.lat(), ll.lng()]);
            }
        } else if (currentDrawing instanceof google.maps.Circle) {
            payload.type = 'circle';
            const c = currentDrawing.getCenter();
            payload.center = [c.lat(), c.lng()];
            payload.radius = Math.round(currentDrawing.getRadius());
        }
        try {
            const res = await fetch(geofencesSaveUrl, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': csrfToken },
                body: JSON.stringify(payload),
            });
            const json = await res.json().catch(() => ({}));
            if (!res.ok || json.success === false) {
                showNotification(json.message || 'Save failed', 'error');
                return;
            }
            if (json.success || json.id) {
                currentDrawing.setMap(null);
                currentDrawing = null;
                document.getElementById('btnSaveGeofence').disabled = true;
                document.getElementById('geofencePanel')?.classList.remove('active');
                await loadGeofences();
                showNotification('Geofence saved', 'success');
            }
        } catch (e) {
            showNotification('Save failed', 'error');
        }
    }

    window.initDeviceMap = function () {
        bootDeviceMap();
    };

    window.initMap = window.initDeviceMap;
    window.deviceMapResize = resizeMap;

    function scheduleMapBoot() {
        if (!document.getElementById('map')) {
            return;
        }
        if (document.readyState === 'loading') {
            document.addEventListener('DOMContentLoaded', () => bootDeviceMap(), { once: true });
        } else {
            bootDeviceMap();
        }
    }

    scheduleMapBoot();
})();
