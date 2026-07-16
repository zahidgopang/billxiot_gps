/**
 * GPS device map tracker — live updates, history, alerts
 */
(function () {
    'use strict';

    const global = window;
    const cfg = window.DEVICE_MAP_CONFIG || {};
    const i18n = cfg.i18n || {};
    const mi = (key, fallback) => (i18n[key] != null && i18n[key] !== '' ? i18n[key] : fallback);
    const dash = () => mi('dash', '—');
    const deviceId = cfg.deviceId;
    const baseUrl = cfg.baseUrl || '';
    const csrfToken = cfg.csrfToken || '';
    const api = cfg.apiRoutes || {};
    const canManageGeofences = cfg.canManageGeofences !== false;
    const mapToken = cfg.mapToken || '';
    const liveUrl = api.live || (mapToken ? `${baseUrl}/user/device/${mapToken}/live-json` : '');
    const historyUrl = api.history || (mapToken ? `${baseUrl}/user/device/${mapToken}/history-json` : '');
    const historyPointsUrl = api.historyPoints || historyUrl.replace('history-json', 'history-points-json');
    const historyAnalyticsUrl = api.historyAnalytics || historyUrl.replace('history-json', 'history-analytics-json');
    const historyWorkerUrl = cfg.historyWorkerUrl || '/js/history-map-worker.js';
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
        ?? cfg.mapRendering?.connectivity?.moving_speed_kmh ?? 1;
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
    // Live poll cadence. MUST be declared: setupRealtime() reads this when
    // creating the recurring poll interval. Under 'use strict' an undeclared
    // reference throws and silently aborts live polling (marker freezes until
    // a full page reload).
    const pollIntervalMs = Number(cfg.pollIntervalMs) > 0 ? Number(cfg.pollIntervalMs) : 10000;
    const reverbStaleMs = Number(cfg.reverbStaleMs) > 0 ? Number(cfg.reverbStaleMs) : 45000;
    const alertPollIntervalMs = Number(cfg.alertPollIntervalMs) > 0
        ? Number(cfg.alertPollIntervalMs)
        : 30000;
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

    let map, geofenceDrawer, trafficLayer, customInfoWindow;
    let vehicleMapPopup = null;
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
    let followVehicle = false;
    let playbackFollow = false;
    let flatpickrFrom;
    let flatpickrTo;
    let historyData = [];
    let historyTimeline = [];
    let historyStats = null;
    let historyLoadSeq = 0;
    const historyResponseCache = new Map();
    const HISTORY_CACHE_TTL_MS = 5 * 60 * 1000;
    const HISTORY_CACHE_MAX_ENTRIES = 16;
    let lazyStopMarkerByIndex = new Map();
    let stopBoundsListener = null;
    let virtualTimelineScrollEl = null;
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
    const HISTORY_RENDERER_RETRY_MAX = 8;
    let historyRendererRetries = 0;
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
    let userViewportLocked = false;
    let suppressViewportLock = false;
    let suppressViewportLockTimer = null;
    let historyAutoFitDone = false;
    let routeTripAutoFitDone = false;
    let vehiclePopupPinned = false;
    let livePopupAddress = '';
    let livePollTimer = null;
    let alertsPollTimer = null;
    let realtimeHealthy = false;
    let lastReverbActivityAt = 0;
    let lastHttpHeartbeatAt = 0;
    let pollInFlight = false;
    let echoHooksBound = false;
    let reverbWatchTimer = null;
    let echoChannel = null;
    let statusTicker = null;
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

    const mapAppearance = {
        vehicle_type: cfg.vehicleType || 'car',
        map_builtin_icon_path: cfg.mapBuiltinIconPath || null,
        map_builtin_icon_url: cfg.mapBuiltinIconUrl || null,
        map_marker_style: cfg.mapMarkerStyle || 'body',
        map_marker_size: cfg.mapMarkerSize || '100',
        map_marker_size_scale: cfg.mapMarkerSizeScale || 1,
        // Orphaned DB "custom" without a resolvable file must not block library icons.
        map_icon_source: (cfg.mapIconSource === 'custom' && cfg.mapCustomIconUrl) ? 'custom' : 'default',
        map_custom_icon_url: (cfg.mapIconSource === 'custom' && cfg.mapCustomIconUrl) ? cfg.mapCustomIconUrl : null,
        map_icon_rotation_enabled: cfg.mapIconRotationEnabled !== false,
        map_icon_rotation_offset: cfg.mapIconRotationOffset ?? 0,
        map_fallback_icon_url: cfg.mapFallbackIconUrl || '/icons/builtin/Vehicles/car.svg',
    };

    /**
     * Live GPS points do not carry icon fields. Overlay the saved appearance so
     * uploads / library picks stay visible after poll/Pusher updates.
     */
    function withMapAppearance(source) {
        return {
            ...(source || {}),
            vehicle_type: mapAppearance.vehicle_type,
            map_builtin_icon_path: mapAppearance.map_builtin_icon_path,
            map_builtin_icon_url: mapAppearance.map_builtin_icon_url,
            map_marker_style: mapAppearance.map_marker_style,
            map_marker_size: mapAppearance.map_marker_size,
            map_marker_size_scale: mapAppearance.map_marker_size_scale,
            map_icon_source: mapAppearance.map_icon_source,
            map_custom_icon_url: mapAppearance.map_custom_icon_url,
            map_icon_rotation_enabled: mapAppearance.map_icon_rotation_enabled,
            map_icon_rotation_offset: mapAppearance.map_icon_rotation_offset,
            map_fallback_icon_url: mapAppearance.map_fallback_icon_url,
        };
    }

    function resolveMarkerStyle(source) {
        const appearance = withMapAppearance(source);
        if (window.VehicleMarker?.resolveMapIconUrl?.(appearance)) {
            return 'body';
        }
        return window.VehicleMarker?.resolveMarkerStyle?.(appearance) || 'body';
    }

    function resolveMarkerSizeScale(source) {
        return window.VehicleMarker?.resolveMarkerSizeScale?.(
            withMapAppearance(source),
            cfg.mapRendering
        ) || 1;
    }

    function resolveMapIconUrl(source) {
        const VM = window.VehicleMarker;
        const appearance = withMapAppearance(source);
        return VM?.resolveMapIconUrl?.(appearance)
            || VM?.resolveFallbackIconUrl?.(appearance)
            || null;
    }

    function resolveFallbackIconUrl(source) {
        return window.VehicleMarker?.resolveFallbackIconUrl?.(withMapAppearance(source))
            || '/icons/builtin/Vehicles/car.svg';
    }

    function resolveCustomIconUrl(source) {
        return window.VehicleMarker?.resolveCustomIconUrl?.(withMapAppearance(source)) || null;
    }

    function resolveRotationEnabled(source) {
        return window.VehicleMarker?.resolveRotationEnabled?.(withMapAppearance(source)) !== false;
    }

    function resolveRotationOffset(source) {
        return window.VehicleMarker?.resolveIconRotationOffset?.(withMapAppearance(source)) ?? 0;
    }

    function applyMapAppearance(next) {
        Object.assign(mapAppearance, next || {});
        if (mapAppearance.map_icon_source !== 'custom' || !mapAppearance.map_custom_icon_url) {
            mapAppearance.map_icon_source = mapAppearance.map_custom_icon_url ? 'custom' : 'default';
            if (mapAppearance.map_icon_source !== 'custom') {
                mapAppearance.map_custom_icon_url = null;
            }
        }
        // Refresh builtin URL from path/type so a prior selection cannot stick on the map.
        if (mapAppearance.map_icon_source !== 'custom') {
            const fromPath = mapAppearance.map_builtin_icon_path
                && window.BuiltinMapIcons?.urlForPath?.(mapAppearance.map_builtin_icon_path);
            const fromType = mapAppearance.vehicle_type
                && window.BuiltinMapIcons?.urlForType?.(mapAppearance.vehicle_type);
            mapAppearance.map_builtin_icon_url = fromPath || fromType || mapAppearance.map_builtin_icon_url || null;
        }
        // Always cache-bust custom URLs so a re-upload never reuses a cached image.
        if (mapAppearance.map_custom_icon_url) {
            const url = String(mapAppearance.map_custom_icon_url);
            if (/([?&])_=\d+/.test(url)) {
                mapAppearance.map_custom_icon_url = url.replace(/([?&])_=\d+/, `$1_=${Date.now()}`);
            } else {
                mapAppearance.map_custom_icon_url = `${url}${url.includes('?') ? '&' : '?'}_=${Date.now()}`;
            }
        }
        cfg.vehicleType = mapAppearance.vehicle_type;
        cfg.mapBuiltinIconPath = mapAppearance.map_builtin_icon_path;
        cfg.mapBuiltinIconUrl = mapAppearance.map_builtin_icon_url;
        cfg.mapIconSource = mapAppearance.map_icon_source;
        cfg.mapCustomIconUrl = mapAppearance.map_custom_icon_url;
        cfg.mapIconRotationEnabled = mapAppearance.map_icon_rotation_enabled;
        cfg.mapIconRotationOffset = mapAppearance.map_icon_rotation_offset ?? 0;
        if (lastTelemetry) {
            Object.assign(lastTelemetry, {
                vehicle_type: mapAppearance.vehicle_type,
                map_builtin_icon_path: mapAppearance.map_builtin_icon_path,
                map_builtin_icon_url: mapAppearance.map_builtin_icon_url,
                map_marker_style: mapAppearance.map_marker_style,
                map_marker_size: mapAppearance.map_marker_size,
                map_marker_size_scale: mapAppearance.map_marker_size_scale,
                map_icon_source: mapAppearance.map_icon_source,
                map_custom_icon_url: mapAppearance.map_custom_icon_url,
                map_icon_rotation_enabled: mapAppearance.map_icon_rotation_enabled,
                map_icon_rotation_offset: mapAppearance.map_icon_rotation_offset,
                map_fallback_icon_url: mapAppearance.map_fallback_icon_url,
            });
        }
        window.VehicleMarker?.clearIconLoadCache?.();
        if (fleetRenderer) {
            fleetRenderer.refreshIconKit();
            if (lastTelemetry) {
                fleetRenderer.setCurrentVehicle(lastTelemetry, { animate: false });
                fleetRenderer.updateVehicleIcon(lastTelemetry);
            }
        }
    }

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
                getMarkerStyle: resolveMarkerStyle,
                getMarkerSizeScale: resolveMarkerSizeScale,
                getMapIconUrl: resolveMapIconUrl,
                getFallbackIconUrl: resolveFallbackIconUrl,
                getCustomIconUrl: resolveCustomIconUrl,
                getRotationEnabled: resolveRotationEnabled,
                getRotationOffset: resolveRotationOffset,
                mapRendering: cfg.mapRendering,
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
                    global.GoogleMapsPlatform?.runAfterMarkerClick?.(() => {
                        if (lastTelemetry) openLiveVehiclePopup(lastTelemetry);
                    });
                },
            });
            fleetRenderer.attachMap(map);
            fleetRenderer.setFollowVehicle(followVehicle);
        }
        currentPositionMarker = fleetRenderer.getMarker();
        return fleetRenderer;
    }

    function markProgrammaticViewportMove(callback) {
        if (suppressViewportLockTimer) {
            clearTimeout(suppressViewportLockTimer);
        }
        suppressViewportLock = true;
        try {
            callback?.();
        } finally {
            suppressViewportLockTimer = setTimeout(() => {
                suppressViewportLock = false;
                suppressViewportLockTimer = null;
            }, 700);
        }
    }

    function lockViewportFromUser() {
        if (suppressViewportLock) {
            return;
        }
        userViewportLocked = true;
        followVehicle = false;
        playbackFollow = false;
        ensureFleetRenderer()?.setFollowVehicle(false);
        document.getElementById('btnFollow')?.classList.remove('active');
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

    function isValidCoord(value) {
        const n = parseFloat(value);
        return Number.isFinite(n);
    }

    function normalizePoint(raw) {
        if (!raw) return null;
        const lat = parseFloat(raw.lat ?? raw.latitude ?? NaN);
        const lng = parseFloat(raw.lng ?? raw.longitude ?? NaN);
        if (!isValidCoord(lat) || !isValidCoord(lng)) return null;

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
            odometer_km: raw.odometer_km ?? null,
            altitude: raw.altitude != null ? parseFloat(raw.altitude) : null,
            power_cut: raw.power_cut === true || raw.power_cut === 1,
            panic: raw.panic === true || raw.panic === 1,
            recorded_at: ts,
            recorded_at_ms: tsMs,
            position_id: raw.position_id != null ? Number(raw.position_id) : null,
            status: raw.status ?? null,
            status_key: raw.status_key ?? null,
            status_label: raw.status_label ?? null,
            status_duration_seconds: raw.status_duration_seconds != null
                ? parseFloat(raw.status_duration_seconds)
                : null,
            motion_status: raw.motion_status ?? null,
            motion_status_key: raw.motion_status_key ?? null,
            trip_status_key: raw.trip_status_key ?? raw.status_key ?? null,
            trip_status_label: raw.trip_status_label ?? raw.status_label ?? null,
            connectivity_tier: raw.connectivity_tier ?? null,
            last_known_status: raw.last_known_status ?? null,
            last_known_status_key: raw.last_known_status_key ?? null,
            last_known_speed: raw.last_known_speed != null ? parseFloat(raw.last_known_speed) : null,
            last_known_ignition: raw.last_known_ignition === true || raw.last_known_ignition === 1 || raw.last_known_ignition === '1'
                ? true
                : (raw.last_known_ignition === false || raw.last_known_ignition === 0 || raw.last_known_ignition === '0'
                    ? false
                    : null),
            plate: raw.plate ?? raw.map_marker_plate ?? raw.vehicle_number ?? null,
            name: raw.name ?? raw.map_marker_title ?? null,
            is_online: raw.is_online,
            // Keep map appearance on live/history points so uploads are not lost after poll.
            vehicle_type: raw.vehicle_type ?? raw.vehicleType ?? null,
            map_builtin_icon_path: raw.map_builtin_icon_path ?? raw.mapBuiltinIconPath ?? null,
            map_builtin_icon_url: raw.map_builtin_icon_url ?? raw.mapBuiltinIconUrl ?? null,
            map_marker_style: raw.map_marker_style ?? raw.mapMarkerStyle ?? null,
            map_marker_size: raw.map_marker_size ?? raw.mapMarkerSize ?? null,
            map_marker_size_scale: raw.map_marker_size_scale ?? raw.mapMarkerSizeScale ?? null,
            map_icon_source: raw.map_icon_source ?? raw.mapIconSource ?? null,
            map_custom_icon_url: raw.map_custom_icon_url ?? raw.mapCustomIconUrl ?? null,
            map_icon_rotation_enabled: raw.map_icon_rotation_enabled ?? raw.mapIconRotationEnabled ?? null,
            map_icon_rotation_offset: raw.map_icon_rotation_offset ?? raw.mapIconRotationOffset ?? null,
            map_fallback_icon_url: raw.map_fallback_icon_url ?? raw.mapFallbackIconUrl ?? null,
        };
    }

    function syncAppearanceFromPoint(point) {
        if (!point) return;
        const next = {};
        if (point.vehicle_type != null) next.vehicle_type = point.vehicle_type;
        if (point.map_builtin_icon_path != null) next.map_builtin_icon_path = point.map_builtin_icon_path;
        if (point.map_builtin_icon_url != null) next.map_builtin_icon_url = point.map_builtin_icon_url;
        if (point.map_marker_style != null) next.map_marker_style = point.map_marker_style;
        if (point.map_marker_size != null) next.map_marker_size = point.map_marker_size;
        if (point.map_marker_size_scale != null) next.map_marker_size_scale = point.map_marker_size_scale;
        if (point.map_custom_icon_url != null) next.map_custom_icon_url = point.map_custom_icon_url;
        if (point.map_icon_source != null) {
            // Ignore orphaned custom flags that have no file URL.
            next.map_icon_source = (point.map_icon_source === 'custom' && !point.map_custom_icon_url)
                ? 'default'
                : point.map_icon_source;
            if (next.map_icon_source !== 'custom') {
                next.map_custom_icon_url = null;
            }
        }
        if (point.map_icon_rotation_enabled != null) {
            next.map_icon_rotation_enabled = point.map_icon_rotation_enabled;
        }
        if (point.map_icon_rotation_offset != null) {
            next.map_icon_rotation_offset = point.map_icon_rotation_offset;
        }
        if (point.map_fallback_icon_url != null) next.map_fallback_icon_url = point.map_fallback_icon_url;
        if (Object.keys(next).length) {
            Object.assign(mapAppearance, next);
            cfg.vehicleType = mapAppearance.vehicle_type;
            cfg.mapBuiltinIconPath = mapAppearance.map_builtin_icon_path;
            cfg.mapBuiltinIconUrl = mapAppearance.map_builtin_icon_url;
            cfg.mapIconSource = mapAppearance.map_icon_source;
            cfg.mapCustomIconUrl = mapAppearance.map_custom_icon_url;
            cfg.mapIconRotationEnabled = mapAppearance.map_icon_rotation_enabled;
            cfg.mapIconRotationOffset = mapAppearance.map_icon_rotation_offset ?? 0;
        }
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

    function vehiclePopupI18n() {
        return {
            dash: mi('dash', '—'),
            plate: mi('vehicleNumber', 'Plate Number'),
            odometer: mi('odometer', 'Odometer'),
            status: mi('currentStatus', 'Status'),
            altitude: mi('altitude', 'Altitude'),
            angle: mi('heading', 'Angle'),
            position: mi('coords', 'Position'),
            engine: mi('ignition', 'Engine'),
            statusFor: mi('statusDuration', 'for'),
            ignitionOn: mi('ignitionOn', 'ON'),
            ignitionOff: mi('ignitionOff', 'OFF'),
            close: mi('closePanel', 'Close'),
        };
    }

    function ensureVehicleMapPopup() {
        if (!vehicleMapPopup && window.VehicleMapPopup) {
            vehicleMapPopup = new window.VehicleMapPopup({
                getMap: () => map,
                googleMaps: google,
                hostElement: '#vehicleMapPopupHost',
                mapOverlay: false,
                stateColors: cfg.stateColors,
                i18n: vehiclePopupI18n(),
                onClose: () => {
                    vehiclePopupPinned = false;
                    if (lastTelemetry) {
                        updateVehiclePulseOverlay(lastTelemetry);
                    }
                },
            });
        }
        return vehicleMapPopup;
    }

    function updateLiveVehiclePopup(point) {
        const kit = ensureVehicleMapPopup();
        if (!vehiclePopupPinned || !kit || !currentPositionMarker || !point) {
            return;
        }
        const identity = markerIdentityForPoint(point);
        kit.update({
            ...point,
            title: identity.title,
            plate: identity.plate || point.plate,
            id: deviceId,
        });
        const pos = currentPositionMarker.getPosition();
        if (pos && kit.infoWindow && !kit.infoWindow.getMap()) {
            kit.open({
                ...point,
                title: identity.title,
                plate: identity.plate || point.plate,
                id: deviceId,
            }, currentPositionMarker.getAnchor?.() || currentPositionMarker);
        }
    }

    function closeLiveVehiclePopup() {
        vehiclePopupPinned = false;
        vehicleMapPopup?.close();
        customInfoWindow?.close();
        if (lastTelemetry) {
            updateVehiclePulseOverlay(lastTelemetry);
        }
    }

    function openLiveVehiclePopup(point) {
        const kit = ensureVehicleMapPopup();
        if (!kit || !currentPositionMarker || !point) {
            return;
        }
        vehiclePopupPinned = true;
        const identity = markerIdentityForPoint(point);
        kit.open({
            ...point,
            title: identity.title,
            plate: identity.plate || point.plate,
            id: deviceId,
        }, currentPositionMarker.getAnchor?.() || currentPositionMarker);
        updateVehiclePulseOverlay(point);
    }

    function formatIgnitionLabel(point) {
        if (point?.ignition == null) return dash();
        return point.ignition ? mi('ignitionOn', 'ON') : mi('ignitionOff', 'OFF');
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

    function normalizeResponse(j) {
        if (!j) return [];
        if (Array.isArray(j)) return j;
        if (Array.isArray(j.points)) return j.points;
        if (Array.isArray(j.locations)) return j.locations;
        if (Array.isArray(j.data)) return j.data;
        if (Array.isArray(j.polyline)) return j.polyline;
        if (j.lat && j.lng) return [j];
        return [];
    }

    function extractHistoryMeta(json) {
        if (!json || Array.isArray(json)) {
            return { timeline: [], stats: null };
        }
        return {
            timeline: Array.isArray(json.timeline) ? json.timeline : [],
            stats: json.stats && typeof json.stats === 'object' ? json.stats : null,
        };
    }

    function statusDurationAtPoint(index, points) {
        if (window.HistoryAnalytics?.statusDurationAtIndex) {
            return window.HistoryAnalytics.statusDurationAtIndex(points || playbackPoints, index);
        }
        const data = points || playbackPoints;
        const point = data[index];
        if (!point) return 0;
        const target = vehicleStateKeyFromMetrics(point);
        const endMs = parseRouteTimestampMs(point.recorded_at);
        if (endMs == null) return 0;
        let sinceMs = endMs;
        for (let i = index - 1; i >= 0; i--) {
            if (vehicleStateKeyFromMetrics(data[i]) !== target) break;
            const ms = parseRouteTimestampMs(data[i].recorded_at);
            if (ms != null) sinceMs = ms;
        }
        return Math.max(0, (endMs - sinceMs) / 1000);
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
        if (!point) {
            return null;
        }
        // Prefer the timezone-aware parsed timestamp so the connectivity tier
        // (live/delayed/stale/offline) is correct regardless of app timezone.
        const ms = point.recorded_at_ms
            ?? parseRouteTimestampMs(point.recorded_at);
        if (ms == null) {
            return null;
        }
        return Date.now() - ms;
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
            parking: mi('statusParked', 'Parking'),
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
        if (k === 'parking') return 'map-status-chip--parked';
        if (k === 'ignition_off') return 'map-status-chip--parked';
        if (k === 'blocked') return 'map-status-chip--offline';
        return 'map-status-chip--' + k;
    }

    function vehicleStateKeyFromMetrics(point) {
        if (point.power_cut || point.panic) {
            return 'alert';
        }
        const speed = parseFloat(point.speed || 0);
        const movingThreshold = typeof movingSpeedKmh === 'number' ? movingSpeedKmh : 1;
        if (point.ignition === true) {
            return speed > movingThreshold ? 'running' : 'idle';
        }
        return speed > movingThreshold ? 'moving' : 'parked';
    }

    function normalizeVehicleStateKey(key) {
        if (!key) return key;
        const k = String(key).toLowerCase();
        if (k === 'parking') return 'parked';
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
        const appearance = withMapAppearance(source);
        const raw = String(
            appearance.vehicle_type || appearance.vehicleType || cfg.vehicleType || 'car'
        ).toLowerCase().trim();
        // Shared / library ids (e.g. shared_car_svgrepo_com) must pass through unchanged.
        if (raw.startsWith('shared_') || raw.includes('_')) {
            return raw;
        }
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

    window.deviceMapApplyAppearance = applyMapAppearance;

    function initMapMarkerAppearance() {
        const form = document.getElementById('mapMarkerAppearanceForm');
        if (!form || !window.MapMarkerAppearance || !cfg.mapAppearanceSaveUrl) {
            return;
        }
        window.MapMarkerAppearance.bindForm({
            form,
            saveUrl: cfg.mapAppearanceSaveUrl,
            uploadUrl: cfg.mapCustomIconUploadUrl,
            deleteUrl: cfg.mapCustomIconDeleteUrl,
            csrf: cfg.csrfToken,
            initial: mapAppearance,
            mapRendering: cfg.mapRendering,
            previewTitle: cfg.markerTitle,
            previewPlate: cfg.markerPlate,
            sizeOrder: window.MapMarkerAppearance.DEFAULT_SIZE_ORDER,
            i18n: cfg.mapAppearanceI18n || {},
            onSaved(appearance) {
                applyMapAppearance(appearance);
                window.MapMarkerAppearance.renderPreview?.(form, {
                    initial: appearance,
                    mapRendering: cfg.mapRendering,
                    sizeOrder: window.MapMarkerAppearance.DEFAULT_SIZE_ORDER,
                    i18n: cfg.mapAppearanceI18n || {},
                    previewTitle: cfg.markerTitle,
                    previewPlate: cfg.markerPlate,
                });
            },
            onPreviewChange(appearance) {
                applyMapAppearance(appearance);
            },
        });
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
        const bodyH = expanded ? (parseInt(panel.style.getPropertyValue('--map-live-panel-body-h'), 10) || 340) : 0;
        setMapLivePanelBodyHeight(bodyH, persist);
        toggle?.setAttribute('aria-expanded', expanded ? 'true' : 'false');
    }

    function setMapLivePanelBodyHeight(bodyH, persist) {
        const panel = document.getElementById('mapLivePanel');
        const toggle = document.getElementById('mapLivePanelToggle');
        if (!panel) return;
        const minH = 0;
        const maxH = 420;
        const h = Math.min(Math.max(bodyH, minH), maxH);
        const expanded = h > 48;
        panel.classList.toggle('is-expanded', expanded);
        panel.classList.toggle('is-collapsed', !expanded);
        panel.style.setProperty('--map-live-panel-body-h', `${h}px`);
        toggle?.setAttribute('aria-expanded', expanded ? 'true' : 'false');
        if (persist) {
            try {
                localStorage.setItem('mapLivePanelBodyH.' + deviceId, String(h));
                localStorage.setItem('mapLivePanelExpanded.' + deviceId, expanded ? '1' : '0');
            } catch (e) { /* ignore */ }
        }
        if (typeof window.deviceMapResize === 'function') {
            window.deviceMapResize();
        }
    }

    function initMapLivePanelToggle() {
        const panel = document.getElementById('mapLivePanel');
        const toggle = document.getElementById('mapLivePanelToggle');
        const handle = document.getElementById('mapLivePanelHandle');
        const header = panel?.querySelector('.map-live-panel__header-main');
        if (!panel) return;

        let restoredH = null;
        try {
            const storedH = parseInt(localStorage.getItem('mapLivePanelBodyH.' + deviceId), 10);
            if (Number.isFinite(storedH)) {
                restoredH = storedH;
            } else if (localStorage.getItem('mapLivePanelExpanded.' + deviceId) === '1') {
                restoredH = 340;
            }
        } catch (e) { /* ignore */ }
        if (restoredH != null) {
            setMapLivePanelBodyHeight(restoredH, false);
        } else {
            setMapLivePanelBodyHeight(0, false);
        }

        const flip = (e) => {
            e?.preventDefault();
            e?.stopPropagation();
            const expanded = panel.classList.contains('is-expanded');
            setMapLivePanelBodyHeight(expanded ? 0 : (parseInt(panel.style.getPropertyValue('--map-live-panel-body-h'), 10) || 340), true);
        };

        toggle?.addEventListener('click', flip);
        header?.addEventListener('click', flip);

        let dragStartY = null;
        let startBodyH = 0;

        handle?.addEventListener('pointerdown', (e) => {
            dragStartY = e.clientY;
            startBodyH = parseInt(panel.style.getPropertyValue('--map-live-panel-body-h'), 10)
                || (panel.classList.contains('is-expanded') ? 340 : 0);
            handle.setPointerCapture?.(e.pointerId);
            e.preventDefault();
        });

        handle?.addEventListener('pointermove', (e) => {
            if (dragStartY == null) return;
            setMapLivePanelBodyHeight(startBodyH + (dragStartY - e.clientY), false);
        });

        const finishDrag = (e) => {
            if (dragStartY == null) return;
            const delta = dragStartY - e.clientY;
            dragStartY = null;
            if (Math.abs(delta) < 12) {
                flip(e);
                return;
            }
            const h = parseInt(panel.style.getPropertyValue('--map-live-panel-body-h'), 10)
                || (panel.classList.contains('is-expanded') ? 340 : 0);
            setMapLivePanelBodyHeight(h, true);
        };

        handle?.addEventListener('pointerup', finishDrag);
        handle?.addEventListener('pointercancel', finishDrag);
    }

    function initMapPanelPositions() {
        if (!global.MapPanelPosition) return;
        const bounds = document.getElementById('mapArea');
        if (!bounds) return;

        const mount = (panelId, key) => {
            global.MapPanelPosition.mount({
                panel: panelId,
                bounds,
                storageKey: `mapPanelPos.${deviceId}.${key}`,
            });
        };

        mount('#mapLivePanel', 'livePanel');
        mount('#mapHud', 'mapHud');
        mount('#routeTripProgressBar', 'routeProgress');

        if (!initMapPanelPositions.resizeBound) {
            initMapPanelPositions.resizeBound = true;
            window.addEventListener('resize', () => global.MapPanelPosition?.reclampAll?.());
        }
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
        markProgrammaticViewportMove(() => {
            ensureFleetRenderer()?.focusOnVehicle(16);
        });
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
        if (window.HistoryAnalytics?.analyze) {
            const stats = window.HistoryAnalytics.analyze(data);
            return {
                dist: stats.dist,
                maxSpeed: stats.maxSpeed,
                overspeedEvents: stats.overspeedEvents,
                movingSec: stats.movingSec,
                stoppedSec: stats.stoppedSec,
                idleSec: stats.idleSec,
                parkingSec: stats.parkingSec,
                totalSec: stats.totalSec,
                stops: stats.stops,
            };
        }
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
            const dur = t0 != null && t1 != null && t1 > t0 ? (t1 - t0) / 1000 : 0;
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
            const createMarker = global.VehicleMarker?.createMarker || global.GoogleMapsPlatform?.createMarker;
            const m = createMarker({
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
        lazyStopMarkerByIndex.forEach((m) => m.setMap(null));
        lazyStopMarkerByIndex.clear();
        stopMarkers = [];
    }

    function unbindLazyStopMarkers() {
        if (stopBoundsListener) {
            google.maps.event.removeListener(stopBoundsListener);
            stopBoundsListener = null;
        }
    }

    function createStopMarker(stop, index) {
        const createMarker = global.VehicleMarker?.createMarker || global.GoogleMapsPlatform?.createMarker;
        const m = createMarker({
            position: { lat: stop.lat, lng: stop.lng },
            map,
            title: `Stop ${index + 1} (${formatDurationLong(stop.duration)})`,
            icon: {
                url: cfg.parkingIcon || cfg.stopIcon || '/images/stop.svg',
                scaledSize: new google.maps.Size(28, 28),
                anchor: new google.maps.Point(14, 14),
            },
            zIndex: 500 + index,
        });
        m.addListener('click', () => {
            customInfoWindow.setContent(`
                <div style="padding:8px;min-width:160px;">
                    <strong>Parking stop</strong><br>
                    <small>Duration: ${formatDurationLong(stop.duration)}</small><br>
                    <small>${stop.start ? (window.AppDateTime?.formatDateTimeShort(stop.start) ?? stop.start) : ''}</small>
                </div>`);
            customInfoWindow.setPosition({ lat: stop.lat, lng: stop.lng });
            customInfoWindow.open(map);
        });
        return m;
    }

    function syncLazyStopMarkers() {
        if (!showsStops || !map || !routeStops.length) {
            clearStopMarkers();
            return;
        }

        const bounds = map.getBounds();
        if (!bounds) return;

        const ne = bounds.getNorthEast();
        const sw = bounds.getSouthWest();
        const padLat = Math.max(0.01, (ne.lat() - sw.lat()) * 0.15);
        const padLng = Math.max(0.01, (ne.lng() - sw.lng()) * 0.15);
        const minLat = sw.lat() - padLat;
        const maxLat = ne.lat() + padLat;
        const minLng = sw.lng() - padLng;
        const maxLng = ne.lng() + padLng;

        const visible = new Set();
        routeStops.forEach((s, i) => {
            if (s.lat >= minLat && s.lat <= maxLat && s.lng >= minLng && s.lng <= maxLng) {
                visible.add(i);
            }
        });

        lazyStopMarkerByIndex.forEach((marker, i) => {
            if (!visible.has(i)) {
                marker.setMap(null);
                lazyStopMarkerByIndex.delete(i);
            }
        });

        visible.forEach((i) => {
            if (!lazyStopMarkerByIndex.has(i)) {
                lazyStopMarkerByIndex.set(i, createStopMarker(routeStops[i], i));
            }
        });

        stopMarkers = Array.from(lazyStopMarkerByIndex.values());
    }

    function bindLazyStopMarkers() {
        if (!map || stopBoundsListener) return;
        stopBoundsListener = map.addListener('idle', syncLazyStopMarkers);
    }

    function renderStopMarkers(stops) {
        routeStops = stops || [];
        clearStopMarkers();
        if (!showsStops || !routeStops.length) return;

        if (routeStops.length <= 40) {
            routeStops.forEach((s, i) => {
                lazyStopMarkerByIndex.set(i, createStopMarker(s, i));
            });
            stopMarkers = Array.from(lazyStopMarkerByIndex.values());
            return;
        }

        bindLazyStopMarkers();
        syncLazyStopMarkers();
    }

    let routeStops = [];

    function toggleStopMarkers() {
        showsStops = !showsStops;
        document.getElementById('btnStops')?.classList.toggle('active', showsStops);
        if (showsStops) {
            setHistoryLoadBanner('stops', 'loading', mi('loadingStops', 'Loading stops…'));
            renderStopMarkers(routeStops);
            setHistoryLoadBanner('stops', 'done', mi('stopsLoaded', '✓ Stops loaded'));
        } else {
            unbindLazyStopMarkers();
            clearStopMarkers();
        }
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
        markProgrammaticViewportMove(() => {
            map.fitBounds(bounds, { top: 120, right: 80, bottom: 140, left: 320 });
        });
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
<gpx version="1.1" creator="BillX GPS">
  <trk><name>${cfg.deviceName || 'Route'}</name><trkseg>
${pts}
  </trkseg></trk>
</gpx>`;
        downloadFile(`route-${deviceId}-${Date.now()}.gpx`, gpx, 'application/gpx+xml');
        showNotification('GPX exported', 'success');
    }

    function updateTelemetryUI(point, options) {
        if (!point) return;
        options = options || {};

        const playbackMode = options.playback === true || playbackActive || routeScrubUsesMotion;
        const tier = playbackMode ? 'live' : connectivityTier(point);
        const isRecent = playbackMode || tier === 'live';
        const isOffline = !playbackMode && tier === 'offline';
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
        setText('livePanelHeading', point.heading != null && point.heading !== '' ? point.heading + '°' : dash());
        setText('livePanelOdometer', point.odometer != null ? Number(point.odometer).toLocaleString() + ' ' + mi('km', 'km') : dash());
        setText('livePanelLat', isValidCoord(point.lat) ? Number(point.lat).toFixed(6) : dash());
        setText('livePanelLng', isValidCoord(point.lng) ? Number(point.lng).toFixed(6) : dash());
        setText('livePanelGpsTime', point.recorded_at ? (window.AppDateTime?.formatDateTime(point.recorded_at) ?? point.recorded_at) : dash());
        setText('livePanelServerTime', window.AppDateTime?.formatDateTime
            ? window.AppDateTime.formatDateTime(new Date().toISOString())
            : new Date().toLocaleString());

        const durationSec = options.statusDurationSec ?? (playbackMode ? statusDurationAtPoint(playbackIndex) : null);
        if (durationSec != null) {
            setText('livePanelStatusDuration', formatDurationLong(durationSec));
        }

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
            if (isVehicleOffline(lastTelemetry)) {
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

    let routeTripProgress = null;

    function ensureRouteTripProgress() {
        if (routeTripProgress || !window.RouteTripProgress) return routeTripProgress;
        routeTripProgress = new window.RouteTripProgress({
            containerId: 'routeTripProgressBar',
            getMap: () => map,
            googleMaps: google,
            i18n: cfg.routeTripI18n || {},
            shouldFitRouteBounds: () => !userViewportLocked && !routeTripAutoFitDone,
            onBeforeRouteBoundsFit: () => markProgrammaticViewportMove(() => {}),
            onRouteBoundsFitted: () => {
                routeTripAutoFitDone = true;
            },
            onComplete: completeAssignedTrip,
            onStartNew: startNewAssignedTrip,
            onRestart: restartAssignedTrip,
            onMilestoneReached: (_milestone, message) => showNotification(message, 'success'),
        });
        return routeTripProgress;
    }

    async function postTripAction(url, fallbackError) {
        if (!url) {
            showNotification(fallbackError || 'Trip action is not available on this page.', 'error');
            return false;
        }

        try {
            const res = await fetch(url, {
                method: 'POST',
                credentials: 'same-origin',
                headers: {
                    Accept: 'application/json',
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': csrfToken || cfg.csrfToken || '',
                    'X-Requested-With': 'XMLHttpRequest',
                },
                body: '{}',
            });
            const data = await res.json().catch(() => ({}));
            if (data.success) {
                if (data.message) {
                    showNotification(data.message, 'success');
                }
                if (data.route_trip) {
                    applyRouteTripPayload({ route_trip: data.route_trip });
                } else {
                    await pollLive(true);
                }
                return true;
            }

            showNotification(
                data.message || fallbackError || `Request failed (${res.status})`,
                'error'
            );
            return false;
        } catch (err) {
            showNotification(fallbackError || ('Trip action failed: ' + (err?.message || 'network error')), 'error');
            return false;
        }
    }

    async function completeAssignedTrip() {
        await postTripAction(cfg.completeTripUrl || api.completeTrip, mi('tripCompleteFailed', 'Could not complete trip'));
    }

    async function startNewAssignedTrip() {
        await postTripAction(cfg.startNewTripUrl || api.startNewTrip, mi('tripStartFailed', 'Could not start trip'));
    }

    async function restartAssignedTrip() {
        const tripI18n = cfg.routeTripI18n || {};
        const confirmed = global.confirm(
            `${tripI18n.restartTripConfirm || 'Restart trip?'}\n\n${tripI18n.restartTripConfirmText || 'This clears current trip progress and starts again from the vehicle position.'}`
        );
        if (!confirmed) return;
        await postTripAction(cfg.restartTripUrl || api.restartTrip, mi('tripRestartFailed', 'Could not restart trip'));
    }

    function applyRouteTripPayload(raw) {
        const kit = ensureRouteTripProgress();
        if (!kit) return;
        if (raw?.route_trip) {
            kit.update(raw);
        } else if (raw && raw.route) {
            kit.update({ route_trip: raw });
        }
    }

    function applyLivePoint(raw) {
        let point = normalizePoint(raw);
        if (!point) return;

        // Live JSON now carries appearance; keep local overlay in sync after uploads.
        if (raw?.map_icon_source != null || raw?.map_custom_icon_url != null || raw?.map_builtin_icon_url != null) {
            syncAppearanceFromPoint(point);
        }
        point = withMapAppearance(point);
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

        if (!playbackActive) {
            updateTelemetryUI(point);
            updateLiveVehiclePopup(point);
        }
        evaluateAlerts(point, prev);
        lastTelemetry = point;
        if (raw?.route_trip) {
            applyRouteTripPayload(raw);
        }
    }

    async function pollLive(force) {
        if (!force && realtimeHealthy) return;
        if (pollInFlight) return;
        if (!liveUrl) return;
        pollInFlight = true;
        try {
            const sep = liveUrl.includes('?') ? '&' : '?';
            const res = await fetch(`${liveUrl}${sep}_=${Date.now()}`, {
                cache: 'no-store',
                credentials: 'same-origin',
                headers: { Accept: 'application/json' },
            });
            const data = await parseJsonResponse(res);
            if (handleMapAccessDenied(res, data)) return;
            if (!res.ok) return;
            if (data && isValidCoord(data.lat) && isValidCoord(data.lng)) applyLivePoint(data);
        } catch (e) {
            console.warn('Live poll failed', e);
        } finally {
            pollInFlight = false;
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

    /**
     * Re-evaluate the displayed status from the last known telemetry every second
     * so the status chip transitions in real time (Live → Delayed → Stale →
     * Offline) and the "last seen" stays current between live polls.
     */
    function tickStatus() {
        if (!lastTelemetry || playbackActive) {
            return;
        }
        updateTelemetryUI(lastTelemetry);
        if (vehiclePopupPinned) {
            updateLiveVehiclePopup(lastTelemetry);
        }
        // Refresh marker color so it reflects the aging status tier.
        ensureFleetRenderer()?.updateVehicleIcon(lastTelemetry);
    }

    function isEchoConnected() {
        return global.Echo?.connector?.pusher?.connection?.state === 'connected';
    }

    function reverbEventsRecent(maxMs) {
        const limit = maxMs ?? reverbStaleMs;
        return lastReverbActivityAt > 0
            && (Date.now() - lastReverbActivityAt) < limit;
    }

    function needsHttpLivePoll(force = false) {
        if (force) return true;
        if (document.hidden) return false;
        if (realtimeHealthy) return false;
        return !!liveUrl;
    }

    function needsHttpAlertPoll() {
        return !realtimeHealthy && !document.hidden && !!alertsUrl;
    }

    function stopLivePolling() {
        if (livePollTimer) {
            clearInterval(livePollTimer);
            livePollTimer = null;
        }
    }

    function stopAlertsPolling() {
        if (alertsPollTimer) {
            clearInterval(alertsPollTimer);
            alertsPollTimer = null;
        }
    }

    function pauseRealtimePolling() {
        stopLivePolling();
        stopAlertsPolling();
    }

    function startLivePolling() {
        if (!needsHttpLivePoll()) {
            stopLivePolling();
            return;
        }
        const hadTimer = !!livePollTimer;
        stopLivePolling();
        if (!hadTimer) {
            pollLive(true);
        }
        livePollTimer = setInterval(() => pollLive(false), pollIntervalMs);
    }

    function startAlertsPolling() {
        if (!needsHttpAlertPoll()) {
            stopAlertsPolling();
            return;
        }
        const hadTimer = !!alertsPollTimer;
        stopAlertsPolling();
        if (!hadTimer && !alertsBootstrapped) {
            loadRecentAlerts();
        }
        alertsPollTimer = setInterval(pollNewAlerts, alertPollIntervalMs);
    }

    function maybeStaleReverbHeartbeat() {
        if (!realtimeHealthy || document.hidden) return;
        if (reverbEventsRecent()) return;
        const since = Date.now() - (lastHttpHeartbeatAt || 0);
        if (lastHttpHeartbeatAt && since < reverbStaleMs) return;
        lastHttpHeartbeatAt = Date.now();
        pollLive(true);
    }

    function syncRealtimePolling() {
        const wsConnected = isEchoConnected();
        if (wsConnected !== realtimeHealthy) {
            realtimeHealthy = wsConnected;
            console.info(
                '[device-map] Reverb',
                wsConnected
                    ? 'connected — live-json / alerts-json polling stopped'
                    : 'unavailable — HTTP fallback active',
            );
        }
        if (needsHttpLivePoll()) {
            startLivePolling();
        } else {
            stopLivePolling();
        }
        if (needsHttpAlertPoll()) {
            startAlertsPolling();
        } else {
            stopAlertsPolling();
        }
    }

    function applyReverbLocation(payload) {
        const loc = payload?.location && payload.location.lat != null
            ? payload.location
            : payload;
        if (!loc || !isValidCoord(loc.lat) || !isValidCoord(loc.lng)) return;
        lastReverbActivityAt = Date.now();
        applyLivePoint(loc);
        stopLivePolling();
    }

    function subscribeDeviceEcho() {
        if (!global.Echo || typeof global.Echo.private !== 'function' || !deviceId || echoChannel) {
            return;
        }
        try {
            echoChannel = global.Echo.private(`device.${deviceId}`);
            echoChannel.listen('.DeviceLocationUpdated', applyReverbLocation);
            if (isEchoConnected()) {
                realtimeHealthy = true;
                stopLivePolling();
                stopAlertsPolling();
            }
        } catch (err) {
            console.warn('[device-map] Echo subscribe failed — using HTTP fallback', err);
        }
    }

    function bindEchoRealtime() {
        if (echoHooksBound) return;
        echoHooksBound = true;

        const sync = () => syncRealtimePolling();
        global.addEventListener('reverb:connected', sync);
        global.addEventListener('reverb:disconnected', sync);
        global.addEventListener('focus', sync);

        document.addEventListener('visibilitychange', () => {
            if (document.hidden) {
                pauseRealtimePolling();
            } else {
                syncRealtimePolling();
            }
        });

        if (!global.Echo) {
            realtimeHealthy = false;
            return;
        }

        reverbWatchTimer = setInterval(() => {
            if (!isEchoConnected()) {
                realtimeHealthy = false;
                syncRealtimePolling();
                return;
            }
            realtimeHealthy = true;
            stopLivePolling();
            stopAlertsPolling();
            maybeStaleReverbHeartbeat();
        }, 20000);

        setTimeout(sync, 800);
        setTimeout(sync, 2500);
    }

    function setupRealtime() {
        if (statusTicker) {
            clearInterval(statusTicker);
        }
        bindEchoRealtime();
        subscribeDeviceEcho();
        syncRealtimePolling();
        statusTicker = setInterval(tickStatus, 1000);
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
            libraries: 'geometry,visualization,places,marker',
            v: 'weekly',
            loading: 'async',
        });
        return `https://maps.googleapis.com/maps/api/js?${params.toString()}`;
    }

    function ensureMapDrawer() {
        if (!map || !window.GeofenceMapDrawer) {
            return null;
        }
        if (!geofenceDrawer) {
            geofenceDrawer = new window.GeofenceMapDrawer({
                map,
                onComplete: (overlay) => {
                    currentDrawing = overlay;
                    const saveBtn = document.getElementById('btnSaveGeofence');
                    if (saveBtn) saveBtn.disabled = false;
                },
                onChange: (state) => {
                    const saveBtn = document.getElementById('btnSaveGeofence');
                    if (!saveBtn || state.ready) {
                        return;
                    }
                    if (state.mode === 'polygon' && (state.pointCount || 0) >= 3) {
                        saveBtn.disabled = false;
                    }
                },
            });
        }
        return geofenceDrawer;
    }

    function startGeofenceDraw(kind) {
        if (!window.GeofenceMapDrawer) {
            showNotification('Drawing tools unavailable', 'error');
            return;
        }
        const drawer = ensureMapDrawer();
        if (!drawer) {
            showNotification('Drawing tools unavailable', 'error');
            return;
        }
        currentDrawing?.setMap(null);
        currentDrawing = null;
        const saveBtn = document.getElementById('btnSaveGeofence');
        if (saveBtn) saveBtn.disabled = true;
        if (kind === 'circle') {
            drawer.startCircle();
        } else {
            drawer.startPolygon();
        }
    }

    function cancelGeofenceDraw() {
        geofenceDrawer?.cancel();
        currentDrawing?.setMap(null);
        currentDrawing = null;
        const saveBtn = document.getElementById('btnSaveGeofence');
        if (saveBtn) saveBtn.disabled = true;
        document.getElementById('geofencePanel')?.classList.remove('active');
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
            if (!alertsBootstrapped) {
                await loadRecentAlerts();
            }
            setupRealtime();
            if (needsHttpLivePoll()) {
                await pollLive(true);
            }
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
        kickOffMapData();
        window.dispatchEvent(new CustomEvent('device-map-ready'));
    }

    // Fetch route/live/alert data as soon as the map instance exists, decoupled
    // from Google tile painting. The route + 24h history render onto the map even
    // before tiles finish loading, so data appears at a consistent speed instead
    // of waiting on variable tile-server / network latency.
    function kickOffMapData() {
        if (mapDataStarted) {
            return;
        }
        mapDataStarted = true;
        hydrateAlertsFromConfig();
        loadGeofences();
        loadHistory();
        startMapDataServices();
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

        markProgrammaticViewportMove(() => {
            const baseMapOpts = {
                center,
                zoom: initial ? 15 : 13,
                mapTypeId: 'roadmap',
                gestureHandling: 'greedy',
                fullscreenControl: true,
                zoomControl: true,
                streetViewControl: false,
                minZoom: 3,
                maxZoom: 21,
            };
            map = new google.maps.Map(
                mapEl,
                global.GoogleMapsPlatform?.mapOptions
                    ? global.GoogleMapsPlatform.mapOptions(baseMapOpts, cfg.googleMapsMapId)
                    : baseMapOpts,
            );
        });

        ensureMapDrawer();
        trafficLayer = new google.maps.TrafficLayer();
        customInfoWindow = new google.maps.InfoWindow({ maxWidth: 320, pixelOffset: new google.maps.Size(0, -8) });
        customInfoWindow.addListener('closeclick', () => {
            if (!vehiclePopupPinned) return;
            closeLiveVehiclePopup();
        });
        map.addListener('click', () => {
            if (global.GoogleMapsPlatform?.shouldSuppressMapClick?.()) return;
            if (vehiclePopupPinned) {
                closeLiveVehiclePopup();
            }
        });
        map.addListener('dragstart', lockViewportFromUser);
        map.addListener('zoom_changed', lockViewportFromUser);

        if (!mapControlsBound) {
            bindControls();
            initNavAlerts();
            initMapHudToggle();
            initMapLivePanelToggle();
            initMapPanelPositions();
            initHudRouteSummary();
            initMapMarkerAppearance();
            initMapBackNavigation();
            updatePlaybackFab();
            initDateFilter();
            mapControlsBound = true;
        }

        ensureFleetRenderer();

        if (initial) {
            applyLivePoint(cfg.initialPoint ?? initial);
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

            if (!map) {
                createMapInstance();
            } else {
                google.maps.event.trigger(map, 'resize');
            }

            bindMapWatchers();
            // Start fetching route history + live data immediately, in parallel
            // with tile painting, so 24h data loads at a consistent speed.
            kickOffMapData();
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
                await sleep(Math.min(800 * mapBootAttempts, 2500));
                mapBootRunning = false;
                return bootDeviceMap();
            }

            const retryMsg = mi('loadingMapFailed', 'Map failed to load. Retrying…');
            const detail = cfg.appDebug && lastMapBootError
                ? `${retryMsg}\n(${lastMapBootError})`
                : retryMsg;
            showLoading(detail);
            mapBootAttempts = 0;
            await sleep(3000);
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
        if (!canManageGeofences) {
            return;
        }
        const drawIds = ['btnDrawPolygon', 'btnDrawCircle', 'btnSaveGeofence', 'btnCancelGeofence'];
        if (!window.GeofenceMapDrawer) {
            drawIds.forEach((id) => {
                const el = document.getElementById(id);
                if (el) {
                    el.disabled = true;
                }
            });
            return;
        }

        document.getElementById('btnDrawPolygon')?.addEventListener('click', () => startGeofenceDraw('polygon'));
        document.getElementById('btnDrawCircle')?.addEventListener('click', () => startGeofenceDraw('circle'));
        document.getElementById('btnSaveGeofence')?.addEventListener('click', saveGeofence);
        document.getElementById('btnCancelGeofence')?.addEventListener('click', cancelGeofenceDraw);
    }

    function bindControls() {
        document.getElementById('btnRecenter')?.addEventListener('click', () => {
            centerOnVehicle();
        });
        document.getElementById('btnCenterVehicle')?.addEventListener('click', centerOnVehicle);
        document.getElementById('btnFollow')?.addEventListener('click', () => {
            if (playbackPoints.length && document.getElementById('playbackPanel')?.classList.contains('active')) {
                playbackFollow = !playbackFollow;
                document.getElementById('btnFollow')?.classList.toggle('active', playbackFollow);
                if (playbackFollow && playbackPoints[playbackIndex]) {
                    const p = playbackPoints[playbackIndex];
                    map?.panTo({ lat: p.lat, lng: p.lng });
                }
                return;
            }
            centerOnVehicle();
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

    function renderHistoryTimeline(timeline) {
        const list = document.getElementById('historyTimelineList');
        const countEl = document.getElementById('historyTimelineCount');
        if (!list) return;

        const items = (timeline || []).filter((seg) => (seg.duration_seconds ?? 0) > 0 || seg.is_transition);
        if (countEl) countEl.textContent = String(items.length);

        if (!items.length) {
            list.classList.remove('timeline-virtual-host');
            list.innerHTML = `<div class="trip-event-empty text-muted small text-center py-3">${escapeHtml(mi('load_history_timeline', 'Load history to see the status timeline'))}</div>`;
            virtualTimelineScrollEl = null;
            return;
        }

        if (items.length <= 48) {
            list.classList.remove('timeline-virtual-host');
            virtualTimelineScrollEl = null;
            list.innerHTML = items.map((seg) => renderTimelineRowHtml(seg)).join('');
            return;
        }

        mountVirtualTimeline(list, items);
    }

    function renderTimelineRowHtml(seg) {
        const time = seg.start_display || formatRouteTimestamp(seg.start);
        const dur = formatDurationLong(seg.duration_seconds || 0);
        const speed = seg.speed_kmh != null ? `${Number(seg.speed_kmh).toFixed(0)} ${mi('kmh', 'km/h')}` : '';
        const coords = seg.end_lat != null && seg.end_lng != null
            ? `${Number(seg.end_lat).toFixed(5)}, ${Number(seg.end_lng).toFixed(5)}`
            : '';
        return `
            <div class="trip-event-item trip-event-item--${escapeHtml(String(seg.status_key || 'stop'))}">
                <strong>${escapeHtml(time)} — ${escapeHtml(seg.status_label || seg.status_key || '—')}${dur ? ` (${dur})` : ''}</strong>
                <span>${escapeHtml([speed, coords].filter(Boolean).join(' · '))}</span>
            </div>`;
    }

    function mountVirtualTimeline(list, items) {
        const rowHeight = 52;
        list.classList.add('timeline-virtual-host');
        list.innerHTML = '';

        const spacer = document.createElement('div');
        spacer.className = 'timeline-virtual__spacer';
        spacer.style.height = `${items.length * rowHeight}px`;

        const viewport = document.createElement('div');
        viewport.className = 'timeline-virtual__viewport';
        spacer.appendChild(viewport);
        list.appendChild(spacer);
        virtualTimelineScrollEl = list;

        const paint = () => {
            const scrollTop = list.scrollTop;
            const viewHeight = list.clientHeight || 240;
            const start = Math.max(0, Math.floor(scrollTop / rowHeight) - 4);
            const end = Math.min(items.length, Math.ceil((scrollTop + viewHeight) / rowHeight) + 4);
            viewport.style.top = `${start * rowHeight}px`;
            viewport.innerHTML = items.slice(start, end).map((seg) => renderTimelineRowHtml(seg)).join('');
        };

        list.onscroll = paint;
        paint();
    }

    function routeStatsFromHistory(data) {
        if (historyStats) {
            return {
                dist: parseFloat(historyStats.total_distance_km || 0),
                maxSpeed: parseFloat(historyStats.max_speed_kmh || 0),
                overspeedEvents: parseInt(historyStats.overspeed_events || 0, 10) || 0,
                movingSec: Math.max(0, parseInt(historyStats.moving_time_seconds || 0, 10) || 0),
                stoppedSec: Math.max(0, parseInt(historyStats.stopped_time_seconds || 0, 10) || 0),
                idleSec: Math.max(0, parseInt(historyStats.idle_time_seconds || 0, 10) || 0),
                parkingSec: Math.max(0, parseInt(historyStats.parking_time_seconds || 0, 10) || 0),
                totalSec: Math.max(0, parseInt(historyStats.total_duration_seconds || 0, 10) || 0),
                stops: (historyStats.stops || []).map((s) => ({
                    lat: s.lat,
                    lng: s.lng,
                    duration: s.duration_seconds ?? s.duration ?? 0,
                    start: s.start,
                    end: s.end,
                })),
                startTs: historyStats.start_time || null,
                endTs: historyStats.end_time || null,
                avgSpeed: parseFloat(historyStats.average_speed_kmh || 0),
            };
        }

        const baseStats = analyzeRoute(data);
        if (!baseStats) return null;
        return enrichRouteStats(data, baseStats);
    }

    function updatePlaybackAtIndex(index) {
        const p = playbackPoints[index];
        if (!p) return;
        routeScrubUsesMotion = true;
        updateCurrentMarker(p, true);
        updateTelemetryUI(p, {
            playback: true,
            statusDurationSec: statusDurationAtPoint(index),
        });
        updateRouteSummaryLive(p);
        setText('pbLiveSpeed', parseFloat(p.speed || 0).toFixed(0));
        setText('pbPointIndex', String(index + 1));
        updatePlaybackProgress();
        if (playbackFollow) map.panTo({ lat: p.lat, lng: p.lng });
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

    function scheduleIdleWork(fn) {
        if (typeof requestIdleCallback === 'function') {
            requestIdleCallback(() => fn(), { timeout: 120 });
        } else {
            setTimeout(fn, 0);
        }
    }

    function buildHistoryRequestUrl(baseUrl, fromParam, toParam) {
        let url = baseUrl;
        if (fromParam) {
            url += `?from=${encodeURIComponent(fromParam)}&to=${encodeURIComponent(toParam || fromParam)}`;
            if (debugGps) {
                url += '&debug_gps=1';
            }
        }
        return url;
    }

    function historyBannerLabel(key, state) {
        const map = {
            route: { loading: ['loadingRoute', 'Loading route…'], done: ['routeLoaded', '✓ Route loaded'] },
            stats: { loading: ['loadingStatistics', 'Loading statistics…'], done: ['statisticsLoaded', '✓ Statistics loaded'] },
            timeline: { loading: ['loadingTimeline', 'Loading timeline…'], done: ['timelineLoaded', '✓ Timeline loaded'] },
            events: { loading: ['loadingEvents', 'Loading events…'], done: ['eventsLoaded', '✓ Events loaded'] },
            stops: { loading: ['loadingStops', 'Loading stops…'], done: ['stopsLoaded', '✓ Stops loaded'] },
        };
        const entry = map[key]?.[state] || map[key]?.loading;
        return entry ? mi(entry[0], entry[1]) : '';
    }

    function setHistoryLoadBanner(key, state, message) {
        const banner = document.getElementById('mapHistoryLoadBanner');
        if (!banner) return;
        banner.hidden = false;
        const item = banner.querySelector(`[data-load="${key}"]`);
        if (!item) return;
        item.dataset.state = state;
        const label = item.querySelector('.map-history-load-banner__label');
        if (label) {
            label.textContent = message || historyBannerLabel(key, state);
        }
        const icon = item.querySelector('.map-history-load-banner__icon');
        if (icon) {
            icon.classList.toggle('is-spinning', state === 'loading');
            icon.classList.toggle('is-done', state === 'done');
        }
        if (!banner.querySelector('[data-state="loading"]')) {
            window.setTimeout(() => {
                if (!banner.querySelector('[data-state="loading"]')) {
                    banner.hidden = true;
                }
            }, 1400);
        }
    }

    function beginHistoryLoadBanner() {
        ['route', 'timeline', 'events', 'stops', 'stats'].forEach((key) => {
            setHistoryLoadBanner(key, 'loading', historyBannerLabel(key, 'loading'));
        });

        const skeleton = (msg) => `<div class="sidebar-loading"><i class="fas fa-circle-notch fa-spin" aria-hidden="true"></i> ${escapeHtml(msg)}</div>`;
        const timelineList = document.getElementById('historyTimelineList');
        const eventsList = document.getElementById('tripEventsList');
        if (timelineList) {
            timelineList.innerHTML = skeleton(historyBannerLabel('timeline', 'loading'));
        }
        if (eventsList) {
            eventsList.innerHTML = skeleton(historyBannerLabel('events', 'loading'));
        }
    }

    function pruneHistoryCache() {
        if (historyResponseCache.size <= HISTORY_CACHE_MAX_ENTRIES) return;
        const oldest = [...historyResponseCache.entries()].sort((a, b) => a[1].ts - b[1].ts)[0];
        if (oldest) {
            historyResponseCache.delete(oldest[0]);
        }
    }

    async function fetchHistoryJson(url) {
        const cached = historyResponseCache.get(url);
        if (cached && (Date.now() - cached.ts) < HISTORY_CACHE_TTL_MS) {
            return cached.data;
        }

        const response = await fetch(url);
        const json = await parseJsonResponse(response);
        if (handleMapAccessDenied(response, json)) {
            throw new Error('access_denied');
        }
        if (!response.ok) {
            throw new Error('HTTP ' + response.status);
        }

        const data = { response, json };
        historyResponseCache.set(url, { ts: Date.now(), data });
        pruneHistoryCache();
        return data;
    }

    function prepareHistoryRenderer() {
        const renderer = ensureFleetRenderer();
        if (!renderer) {
            return null;
        }
        renderer.cancelProgressiveDraw();
        unbindLazyStopMarkers();
        renderer.clearRoute({ keepVehicle: true, keepRealtime: true });
        polylines = [];
        routeGlowPolylines = [];
        clearEventMarkers();
        renderer.clearExtraMarkers();
        markers = currentPositionMarker ? [currentPositionMarker] : [];
        return renderer;
    }

    async function renderHistoryRouteProgressive(data, renderer, seq) {
        const processRoute = global.HistoryMapProcessor?.processRoute;
        let processed = null;

        if (processRoute) {
            processed = await processRoute(data, {
                maxPoints: 2500,
                mediumSpeedKmh,
                overSpeedLimit,
            }, historyWorkerUrl);
        }

        if (seq !== historyLoadSeq) {
            return null;
        }

        const simplified = processed?.simplified?.length ? processed.simplified : data;
        const chunks = processed?.chunks || [];
        const mapPointCount = processed?.mapPointCount ?? simplified.length;

        let bounds = null;
        if (processed?.bounds) {
            const b = processed.bounds;
            bounds = new google.maps.LatLngBounds(
                { lat: b.south, lng: b.west },
                { lat: b.north, lng: b.east }
            );
        }

        const endpoints = {
            start: simplified[0],
            end: simplified[simplified.length - 1],
        };

        const syncMarkersFromRenderer = () => {
            startMarker = renderer.startMarker;
            endMarker = renderer.endMarker;
            if (startMarker && !markers.includes(startMarker)) markers.push(startMarker);
            if (endMarker && !markers.includes(endMarker)) markers.push(endMarker);
        };

        if (!chunks.length) {
            renderer.setRouteEndpoints(endpoints.start, endpoints.end, {
                startTitle: mi('routeStart', 'Route start'),
                endTitle: mi('routeEnd', 'Route end'),
                updateCurrent: false,
                focusZoom: null,
            });
            syncMarkersFromRenderer();
            setHistoryLoadBanner('route', 'done');
        } else {
            renderer.drawRouteChunksProgressive(chunks, endpoints, {
                clickable: true,
                mapPointCount,
                onSegmentClick: (line, latLng) => showPolylineInfo(line, latLng),
                startTitle: mi('routeStart', 'Route start'),
                endTitle: mi('routeEnd', 'Route end'),
                onEndpointsPlaced: () => {
                    if (seq !== historyLoadSeq) return;
                    syncMarkersFromRenderer();
                },
                onComplete: () => {
                    if (seq !== historyLoadSeq) return;
                    polylines = renderer.polylines;
                    routeGlowPolylines = renderer.glowPolylines;
                    setHistoryLoadBanner('route', 'done');
                },
            });
            polylines = renderer.polylines;
            routeGlowPolylines = renderer.glowPolylines;
        }

        lastRealtimePoint = { lat: data[data.length - 1].lat, lng: data[data.length - 1].lng };
        routeScrubUsesMotion = true;
        const lastPoint = data[data.length - 1];
        updateCurrentMarker(lastPoint, true);
        updateTelemetryUI(lastPoint, {
            playback: true,
            statusDurationSec: statusDurationAtPoint(data.length - 1, data),
        });
        updateRouteSummaryLive(lastPoint);

        const shouldAutoFitHistory = !historyAutoFitDone && !userViewportLocked;
        if (shouldAutoFitHistory) {
            markProgrammaticViewportMove(() => {
                if (bounds && data.length < 100) {
                    renderer.fitBounds(bounds, 56);
                } else {
                    renderer.focusOnVehicle(16);
                }
            });
            historyAutoFitDone = true;
        }

        playbackPoints = data;
        playbackIndex = 0;
        updatePlaybackMeta();
        updatePlaybackProgress();
        updatePlaybackFab();
        setPlaybackPanelOpen(false);

        return bounds;
    }

    function renderHistoryEventsChunked(data, onComplete) {
        const events = detectRouteEvents(data);
        clearEventMarkers();
        if (!showsEventMarkers || !events.length) {
            if (typeof onComplete === 'function') onComplete();
            return;
        }

        let index = 0;
        const batchSize = 30;
        const createMarker = global.VehicleMarker?.createMarker || global.GoogleMapsPlatform?.createMarker;

        const step = () => {
            const end = Math.min(index + batchSize, events.length);
            for (; index < end; index++) {
                const ev = events[index];
                const m = createMarker({
                    position: { lat: ev.lat, lng: ev.lng },
                    map,
                    title: ev.title + (ev.detail ? ': ' + ev.detail : ''),
                    icon: eventMarkerIcon(ev.type),
                    zIndex: 550 + index,
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
            }
            if (index < events.length) {
                requestAnimationFrame(step);
            } else {
                if (typeof onComplete === 'function') onComplete();
            }
        };
        requestAnimationFrame(step);
        document.getElementById('btnEventMarkers')?.classList.add('active');
    }

    function applyHistoryAnalyticsPayload(analyticsJson, seq) {
        if (seq !== historyLoadSeq) return;

        const meta = extractHistoryMeta(analyticsJson);
        historyTimeline = meta.timeline;
        historyStats = meta.stats;

        setHistoryLoadBanner('timeline', 'loading');
        renderHistoryTimeline(historyTimeline);
        setHistoryLoadBanner('timeline', 'done');

        if (historyData.length) {
            setHistoryLoadBanner('stats', 'loading');
            updateRouteSummary(historyData);
            setHistoryLoadBanner('stats', 'done');

            const stops = (historyStats?.stops || []).map((s) => ({
                lat: s.lat,
                lng: s.lng,
                duration: s.duration_seconds ?? s.duration ?? 0,
                start: s.start,
                end: s.end,
            }));
            routeStops = stops;

            if (stops.length) {
                setHistoryLoadBanner('stops', 'loading');
                if (showsStops) {
                    renderStopMarkers(routeStops);
                }
                setHistoryLoadBanner('stops', 'done');
            } else {
                setHistoryLoadBanner('stops', 'done');
            }
        }
    }

    async function applyHistoryPointsPayload(pointsJson, response, seq, opts) {
        const {
            explicitRange,
            useLast24Hours,
            historyFallbackHeader,
        } = opts;

        const data = sortHistoryPoints(
            normalizeResponse(pointsJson).map(normalizePoint).filter(Boolean)
        );

        if (seq !== historyLoadSeq) {
            return false;
        }

        if (!data.length) {
            if (explicitRange) {
                clearRoute();
            }
            const emptyMsg = historyFallbackHeader === 'selected_period_empty' || explicitRange
                ? mi('historyFallbackSelectedPeriod', 'No GPS data for the selected date range')
                : (useLast24Hours ? mi('noGps24h', 'No GPS data in the last 24 hours') : 'No history for selected period');
            showNotification(emptyMsg, 'info');
            if (useLast24Hours && lastTelemetry) {
                applyLivePoint(lastTelemetry);
            }
            renderHistoryTimeline([]);
            ['route', 'timeline', 'events', 'stops', 'stats'].forEach((k) => setHistoryLoadBanner(k, 'done'));
            return false;
        }

        historyData = data;
        setHistoryLoadBanner('route', 'loading');

        const renderer = prepareHistoryRenderer();
        if (!renderer) {
            return false;
        }

        await renderHistoryRouteProgressive(data, renderer, seq);
        if (seq !== historyLoadSeq) {
            return false;
        }

        scheduleIdleWork(() => {
            if (seq !== historyLoadSeq) return;
            setHistoryLoadBanner('events', 'loading');
            renderHistoryEventsChunked(data, () => {
                if (seq !== historyLoadSeq) return;
                setHistoryLoadBanner('events', 'done');
            });
        });

        const historyFallback = historyFallbackHeader || pointsJson?.history_fallback || '';
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

        return true;
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
        const seq = ++historyLoadSeq;

        debugGpsLog('loadHistory', {
            from: fromParam,
            to: toParam,
            useLast24Hours,
            explicitRange,
            seq,
        });

        setHistorySearchLoading(true);
        beginHistoryLoadBanner();

        const pointsUrl = buildHistoryRequestUrl(historyPointsUrl, fromParam, toParam);
        const analyticsUrl = buildHistoryRequestUrl(historyAnalyticsUrl, fromParam, toParam);
        const legacyUrl = buildHistoryRequestUrl(historyUrl, fromParam, toParam);

        try {
            let pointsResult = null;
            let analyticsResult = null;
            const canParallel = Boolean(historyPointsUrl && historyAnalyticsUrl);

            if (canParallel) {
                const [pointsSettled, analyticsSettled] = await Promise.allSettled([
                    fetchHistoryJson(pointsUrl),
                    fetchHistoryJson(analyticsUrl),
                ]);
                if (seq !== historyLoadSeq) return;

                if (pointsSettled.status === 'fulfilled') {
                    pointsResult = pointsSettled.value;
                }
                if (analyticsSettled.status === 'fulfilled') {
                    analyticsResult = analyticsSettled.value;
                }
            }

            if (!pointsResult) {
                pointsResult = await fetchHistoryJson(legacyUrl);
                if (seq !== historyLoadSeq) return;
                if (!analyticsResult) {
                    analyticsResult = pointsResult;
                }
            }

            const historyFallbackHeader = pointsResult.response.headers.get('X-History-Fallback')
                || pointsResult.json?.history_fallback
                || '';

            const rendererReady = ensureFleetRenderer();
            if (!rendererReady) {
                if (historyRendererRetries < HISTORY_RENDERER_RETRY_MAX) {
                    historyRendererRetries++;
                    setTimeout(() => loadHistory(from, to), 400);
                    return;
                }
                historyRendererRetries = 0;
                throw new Error(mi('mapNotReady', 'Map is still loading, please try again'));
            }
            historyRendererRetries = 0;

            const pointsApplied = await applyHistoryPointsPayload(pointsResult.json, pointsResult.response, seq, {
                explicitRange,
                useLast24Hours,
                historyFallbackHeader,
            });

            if (pointsApplied && analyticsResult) {
                scheduleIdleWork(() => applyHistoryAnalyticsPayload(analyticsResult.json, seq));
            } else if (pointsApplied && !analyticsResult) {
                scheduleIdleWork(() => {
                    if (seq !== historyLoadSeq || !historyData.length) return;
                    updateRouteSummary(historyData);
                    renderHistoryTimeline([]);
                    ['timeline', 'stats', 'stops', 'events'].forEach((k) => setHistoryLoadBanner(k, 'done'));
                });
            }
        } catch (err) {
            if (err?.message !== 'access_denied') {
                showNotification('Failed to load history: ' + err.message, 'error');
            }
            ['route', 'timeline', 'events', 'stops', 'stats'].forEach((k) => setHistoryLoadBanner(k, 'done'));
        } finally {
            setHistorySearchLoading(false);
        }
    }

    function segmentSpeedStatus(speed) {
        const spd = parseFloat(speed || 0);
        if (spd <= 0) {
            return {
                label: mi('statusStopped', 'Stopped'),
                range: '0',
                tone: 'stopped',
            };
        }
        if (spd <= mediumSpeedKmh) {
            return {
                label: mi('speedNormal', 'Normal'),
                range: `0–${mediumSpeedKmh}`,
                tone: 'normal',
            };
        }
        if (spd <= overSpeedLimit) {
            return {
                label: mi('statusMoving', 'Moving'),
                range: `${mediumSpeedKmh + 1}–${overSpeedLimit}`,
                tone: 'medium',
            };
        }
        return {
            label: mi('statusOverspeed', 'Overspeed'),
            range: `${overSpeedLimit}+`,
            tone: 'overspeed',
        };
    }

    function showPolylineInfo(polyline, latLng) {
        const d = polyline?._segmentData;
        if (!d) return;
        const content = document.getElementById('polylineInfoTemplate')?.cloneNode(true);
        if (!content) return;
        content.style.display = 'block';
        content.removeAttribute('id');

        const q = (sel) => content.querySelector(sel);
        const speed = Number(d.speed || 0);

        q('#statSpeed').textContent = speed.toFixed(1);
        q('#statDistance').textContent = `${(d.distance * 1000).toFixed(0)} m`;
        q('#detailStartTime').textContent = d.startTime ? new Date(d.startTime).toLocaleTimeString() : dash();
        q('#detailEndTime').textContent = d.endTime ? new Date(d.endTime).toLocaleTimeString() : dash();

        const point = d.end || d.start;
        if (point) {
            q('#detailCoords').textContent = `${Number(point.lat).toFixed(5)}, ${Number(point.lng).toFixed(5)}`;
        }

        const status = segmentSpeedStatus(speed);
        const statusEl = q('#detailSpeedStatus');
        const indicatorEl = q('#speedIndicator');
        if (statusEl) {
            statusEl.textContent = status.label;
            statusEl.className = `route-segment-popup__status route-segment-popup__status--${status.tone}`;
        }
        if (indicatorEl) {
            indicatorEl.textContent = status.range;
            indicatorEl.className = `route-segment-popup__speed-pill route-segment-popup__speed-pill--${status.tone}`;
        }

        content.querySelector('.js-polyline-info-close')?.addEventListener('click', (e) => {
            e.preventDefault();
            e.stopPropagation();
            customInfoWindow?.close();
        });

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
        const renderer = ensureFleetRenderer();
        renderer?.cancelProgressiveDraw();
        unbindLazyStopMarkers();
        renderer?.clearRoute({ keepVehicle: true });
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
            renderHistoryTimeline([]);
            return;
        }
        const stats = routeStatsFromHistory(data);
        if (!stats) return;

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
        const deleteBtn = canManageGeofences
            ? `<button type="button" class="geofence-action-btn geofence-action-btn--danger geofence-delete-btn" data-id="${g.id}" title="Remove geofence" aria-label="Remove geofence">
                        <i class="fas fa-trash-alt"></i>
                    </button>`
            : '';
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
                    ${deleteBtn}
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
        const extracted = window.GeofenceDraw?.resolvePayload(geofenceDrawer, currentDrawing);
        if (!extracted) return showNotification('Draw a shape first', 'error');
        if (extracted.type === 'polygon') {
            currentDrawing = geofenceDrawer?.getOverlay?.() || currentDrawing;
        }
        const name = prompt('Geofence name:', 'New Geofence');
        if (!name) return;
        const payload = { name, device_id: deviceId, ...extracted };
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
