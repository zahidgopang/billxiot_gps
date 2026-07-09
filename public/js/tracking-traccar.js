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

    // Riyadh, Saudi Arabia — default when no vehicles are on the map.
    const DEFAULT_CENTER = { lat: 24.7136, lng: 46.6753 };
    const TRAIL_MAX = 120;
    // Minimum travelled distance (m) before a new GPS vertex is committed to the trail.
    const TRAIL_MIN_STEP_M = 2.0;
    const MEDIUM_SPEED = 60;
    const OVER_SPEED = 80;
    const STOP_MIN_SEC = 120;
    const STOPPED_MIN_SEC = 600;
    const OFFLINE_GAP_SEC = 600;
    const HISTORY_CACHE_TTL_MS = 5 * 60 * 1000;
    const HISTORY_CACHE_MAX_ENTRIES = 16;
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
            // Tip of the arrow sits on the GPS coordinate (not the rotation pivot).
            anchor: new g.maps.Point(20, 3),
            labelOrigin: new g.maps.Point(20, -2),
        };
        arrowIconCache[key] = icon;
        return icon;
    }

    function markerLabel(text, markerColor, focused) {
        return {
            text,
            color: '#ffffff',
            fontSize: '12px',
            fontWeight: '600',
            className: focused ? 'tc-mk-label tc-mk-label--focused' : 'tc-mk-label',
            backgroundColor: markerColor || '#64748b',
        };
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

    function createMapMarker(options) {
        if (global.VehicleMarker?.createMarker) {
            return global.VehicleMarker.createMarker(options);
        }
        if (global.GoogleMapsPlatform?.createMarker) {
            return global.GoogleMapsPlatform.createMarker(options);
        }
        return new google.maps.Marker(options);
    }

    function mapInitOptions(base) {
        if (global.GoogleMapsPlatform?.mapOptions) {
            return global.GoogleMapsPlatform.mapOptions(base, global.GOOGLE_MAPS_CONFIG?.mapId);
        }
        return base;
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

    function historyEventBadge(type) {
        const key = String(type || 'event').toLowerCase();
        if (key.includes('geofence') && key.includes('enter')) return { mark: 'G+', cls: 'tc-ev-geofence' };
        if (key.includes('geofence')) return { mark: 'G-', cls: 'tc-ev-geofence' };
        if (key.includes('overspeed') || key.includes('speed')) return { mark: '!', cls: 'tc-ev-overspeed' };
        if (key.includes('panic') || key.includes('sos')) return { mark: 'S', cls: 'tc-ev-panic' };
        if (key.includes('ignition')) return { mark: 'I', cls: 'tc-ev-ignition' };
        if (key.includes('moving') || key.includes('running')) return { mark: '>', cls: 'tc-ev-moving' };
        if (key.includes('offline')) return { mark: 'X', cls: 'tc-ev-offline' };
        if (key.includes('idle') || key.includes('stopped')) return { mark: '||', cls: 'tc-ev-idle' };
        if (key.includes('park')) return { mark: 'P', cls: 'tc-ev-park' };
        if (key.includes('power')) return { mark: 'Pwr', cls: 'tc-ev-alert' };
        if (key.includes('battery')) return { mark: 'Bat', cls: 'tc-ev-alert' };
        return { mark: '•', cls: 'tc-ev-default' };
    }

    function historyEventTime(ev) {
        const raw = ev.time || ev.recorded_at || ev.start || ev.start_display || '';
        return String(raw).replace('T', ' ').slice(0, 19);
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
    const STATUS_MARKER_STYLES = {
        parked: { letter: 'P', color: '#2563eb', zIndex: 572 },
        idle: { letter: 'I', color: '#f97316', zIndex: 571 },
        stopped: { letter: 'S', color: '#ef4444', zIndex: 570 },
        offline: { letter: 'X', color: '#64748b', zIndex: 569 },
    };
    const statusIconCache = Object.create(null);

    function statusMarkerTypeKey(statusKey) {
        const k = String(statusKey || '').toLowerCase();
        if (k === 'parking' || k === 'parked') return 'parked';
        if (k === 'idle') return 'idle';
        if (k === 'stopped' || k === 'ignition_off') return 'stopped';
        if (k === 'offline' || k === 'stale' || k === 'delayed') return 'offline';
        return null;
    }

    function statusSegmentIcon(letter, color) {
        const g = global.google;
        if (!g?.maps) return null;
        const cacheKey = `${letter}|${color}`;
        if (statusIconCache[cacheKey]) return statusIconCache[cacheKey];
        const svg = `<svg xmlns="http://www.w3.org/2000/svg" width="28" height="38" viewBox="0 0 28 38">
            <path d="M14 1 C7 1 2 6 2 13 C2 22 14 37 14 37 C14 37 26 22 26 13 C26 6 21 1 14 1 Z" fill="${color}" stroke="#ffffff" stroke-width="2"/>
            <text x="14" y="17.5" text-anchor="middle" font-size="${letter.length > 1 ? 9 : 12}" font-family="Arial, sans-serif" font-weight="bold" fill="#ffffff">${letter}</text>
        </svg>`;
        statusIconCache[cacheKey] = {
            url: 'data:image/svg+xml;charset=UTF-8,' + encodeURIComponent(svg),
            scaledSize: new g.maps.Size(28, 38),
            anchor: new g.maps.Point(14, 38),
        };
        return statusIconCache[cacheKey];
    }

    function pStopIcon() {
        return statusSegmentIcon('P', '#2563eb');
    }

    function segmentCoords(seg) {
        const startLat = parseFloat(seg.start_lat ?? seg.lat);
        const startLng = parseFloat(seg.start_lng ?? seg.lng);
        const endLat = parseFloat(seg.end_lat ?? seg.lat);
        const endLng = parseFloat(seg.end_lng ?? seg.lng);
        if (hasGeo(startLat, startLng) && hasGeo(endLat, endLng)) {
            return { lat: (startLat + endLat) / 2, lng: (startLng + endLng) / 2 };
        }
        if (hasGeo(startLat, startLng)) return { lat: startLat, lng: startLng };
        if (hasGeo(endLat, endLng)) return { lat: endLat, lng: endLng };
        return null;
    }

    function computeHistoryStatusMarkers(vehicle, points) {
        const markers = [];
        const ha = global.HistoryAnalytics;
        let timeline = Array.isArray(vehicle?.timeline) ? vehicle.timeline : [];
        if (timeline.length && ha?.refineIdleToStopped) {
            timeline = ha.refineIdleToStopped(timeline);
        }

        if (timeline.length) {
            timeline.forEach((seg) => {
                if (seg?.is_transition) return;
                let typeKey = statusMarkerTypeKey(seg.status_key);
                const durationSec = Math.max(0, parseInt(seg.duration_seconds, 10) || 0);
                // Long idle → Stopped (S) when backend/fallback still sends idle.
                if (typeKey === 'idle' && durationSec >= STOPPED_MIN_SEC) {
                    typeKey = 'stopped';
                }
                if (!typeKey) return;
                if (typeKey === 'offline') {
                    if (durationSec < 60) return;
                } else if (durationSec < STOP_MIN_SEC) {
                    return;
                }
                const coords = segmentCoords(seg);
                if (!coords) return;
                const style = STATUS_MARKER_STYLES[typeKey];
                markers.push({
                    typeKey,
                    letter: style.letter,
                    color: style.color,
                    zIndex: style.zIndex,
                    lat: coords.lat,
                    lng: coords.lng,
                    status_key: typeKey === 'stopped' ? 'stopped' : seg.status_key,
                    status_label: typeKey === 'stopped'
                        ? (ha?.timelineLabel?.('stopped') || 'Stopped')
                        : (seg.status_label || seg.status_key),
                    durationSec,
                    arrived: seg.start,
                    departed: seg.end,
                    arrivedDisplay: seg.start_display || fmtTime(seg.start),
                    departedDisplay: seg.end_display || fmtTime(seg.end),
                    speed_kmh: seg.speed_kmh,
                    max_speed_kmh: seg.max_speed_kmh,
                    heading: seg.heading,
                    ignition: seg.ignition,
                });
            });
            return markers;
        }

        // Prefer backend stop list when timeline was skipped (large week ranges).
        const backendStops = Array.isArray(vehicle?.stats?.stops) ? vehicle.stats.stops : [];
        if (backendStops.length) {
            backendStops.forEach((stop) => {
                const durationSec = Math.max(0, parseInt(stop.duration_seconds ?? stop.duration, 10) || 0);
                if (durationSec < STOP_MIN_SEC || !hasGeo(stop.lat, stop.lng)) return;
                const motion = String(stop.motion_key || stop.status_key || 'parked').toLowerCase();
                let typeKey = statusMarkerTypeKey(motion) || 'parked';
                if (typeKey === 'idle' && durationSec >= STOPPED_MIN_SEC) typeKey = 'stopped';
                const style = STATUS_MARKER_STYLES[typeKey] || STATUS_MARKER_STYLES.parked;
                markers.push({
                    typeKey,
                    letter: style.letter,
                    color: style.color,
                    zIndex: style.zIndex,
                    lat: parseFloat(stop.lat),
                    lng: parseFloat(stop.lng),
                    status_key: typeKey === 'parked' ? 'parked' : typeKey,
                    status_label: stop.status_label || ha?.timelineLabel?.(typeKey) || typeKey,
                    durationSec,
                    arrived: stop.start,
                    departed: stop.end,
                    arrivedDisplay: stop.start_display || fmtTime(stop.start),
                    departedDisplay: stop.end_display || fmtTime(stop.end),
                });
            });
            if (markers.length) return markers;
        }

        // Fallback when timeline is unavailable: derive runs from GPS points.
        if (!ha?.motionKey) {
            return computeStops(points).map((stop) => ({
                typeKey: 'parked',
                letter: 'P',
                color: STATUS_MARKER_STYLES.parked.color,
                zIndex: STATUS_MARKER_STYLES.parked.zIndex,
                lat: stop.lat,
                lng: stop.lng,
                status_key: 'parked',
                status_label: 'Parking',
                durationSec: stop.durationSec,
                arrived: stop.arrived?.recorded_at,
                departed: stop.departed?.recorded_at,
                arrivedDisplay: fmtTime(stop.arrived?.recorded_at),
                departedDisplay: fmtTime(stop.departed?.recorded_at),
                heading: stop.heading,
                altitude: stop.altitude,
            }));
        }

        const sorted = [...(points || [])].sort((a, b) => {
            const ta = ha.parseMs?.(a.recorded_at) ?? (Date.parse(a.recorded_at || '') || 0);
            const tb = ha.parseMs?.(b.recorded_at) ?? (Date.parse(b.recorded_at || '') || 0);
            return ta - tb;
        });

        let run = [];
        let runType = null;
        const flushRun = () => {
            if (run.length < 2 || !runType) {
                run = [];
                runType = null;
                return;
            }
            const t0 = ha.parseMs?.(run[0].recorded_at) ?? (Date.parse(run[0].recorded_at || '') || 0);
            const t1 = ha.parseMs?.(run[run.length - 1].recorded_at) ?? (Date.parse(run[run.length - 1].recorded_at || '') || 0);
            const durationSec = ha.segmentSeconds?.(t0, t1) ?? Math.max(0, (t1 - t0) / 1000);
            let finalType = runType;
            if (finalType === 'idle' && durationSec >= STOPPED_MIN_SEC) {
                finalType = 'stopped';
            }
            const minDur = finalType === 'offline' ? 60 : STOP_MIN_SEC;
            if (durationSec >= minDur) {
                const mid = run[Math.floor(run.length / 2)];
                const style = STATUS_MARKER_STYLES[finalType];
                markers.push({
                    typeKey: finalType,
                    letter: style.letter,
                    color: style.color,
                    zIndex: style.zIndex,
                    lat: mid.lat,
                    lng: mid.lng,
                    status_key: finalType === 'parked' ? 'parked' : finalType,
                    status_label: ha.timelineLabel?.(finalType) || finalType,
                    durationSec,
                    arrived: run[0].recorded_at,
                    departed: run[run.length - 1].recorded_at,
                    arrivedDisplay: fmtTime(run[0].recorded_at),
                    departedDisplay: fmtTime(run[run.length - 1].recorded_at),
                    heading: mid.heading,
                    ignition: mid.ignition,
                    speed_kmh: mid.speed,
                });
            }
            run = [];
            runType = null;
        };

        for (let i = 1; i < sorted.length; i++) {
            const a = sorted[i - 1];
            const b = sorted[i];
            const t0 = ha.parseMs?.(a.recorded_at) ?? (Date.parse(a.recorded_at || '') || 0);
            const t1 = ha.parseMs?.(b.recorded_at) ?? (Date.parse(b.recorded_at || '') || 0);
            const dt = ha.segmentSeconds?.(t0, t1) ?? Math.max(0, (t1 - t0) / 1000);
            if (dt > (ha.OFFLINE_GAP_SECONDS || OFFLINE_GAP_SEC)) {
                flushRun();
                const gapType = ha.gapMotionKey?.(a, b, dt) || 'offline';
                const typeKey = statusMarkerTypeKey(gapType) || (gapType === 'parked' ? 'parked' : 'offline');
                const style = STATUS_MARKER_STYLES[typeKey] || STATUS_MARKER_STYLES.offline;
                markers.push({
                    typeKey,
                    letter: style.letter,
                    color: style.color,
                    zIndex: style.zIndex,
                    lat: b.lat,
                    lng: b.lng,
                    status_key: typeKey === 'parked' ? 'parked' : typeKey,
                    status_label: ha.timelineLabel?.(typeKey) || typeKey,
                    durationSec: dt,
                    arrived: a.recorded_at,
                    departed: b.recorded_at,
                    arrivedDisplay: fmtTime(a.recorded_at),
                    departedDisplay: fmtTime(b.recorded_at),
                });
                continue;
            }
            const motion = ha.motionKey(b);
            const typeKey = statusMarkerTypeKey(motion === 'running' || motion === 'moving' ? null : motion);
            if (!typeKey) {
                flushRun();
                continue;
            }
            if (runType && runType !== typeKey) flushRun();
            runType = typeKey;
            run.push(b);
        }
        flushRun();
        return markers;
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

    /** Dead-reckon a point along heading (meters) — used between slow GPS reports. */
    function projectAlongHeading(lat, lng, headingDeg, meters) {
        const m = Number(meters) || 0;
        if (m <= 0) return normalizeGps(lat, lng);
        const R = 6371000;
        const brng = (Number(headingDeg) || 0) * Math.PI / 180;
        const lat1 = lat * Math.PI / 180;
        const lng1 = lng * Math.PI / 180;
        const d = m / R;
        const lat2 = Math.asin(
            Math.sin(lat1) * Math.cos(d) + Math.cos(lat1) * Math.sin(d) * Math.cos(brng),
        );
        const lng2 = lng1 + Math.atan2(
            Math.sin(brng) * Math.sin(d) * Math.cos(lat1),
            Math.cos(d) - Math.sin(lat1) * Math.sin(lat2),
        );
        return normalizeGps(lat2 * 180 / Math.PI, lng2 * 180 / Math.PI) || { lat, lng };
    }

    function normalizeGps(lat, lng) {
        const a = parseFloat(lat);
        const b = parseFloat(lng);
        if (!hasGeo(a, b)) return null;
        return { lat: a, lng: b };
    }

    /** Ease-in-out for marker glide between GPS fixes. */
    function easeInOutQuad(t) {
        return t < 0.5 ? 2 * t * t : 1 - Math.pow(-2 * t + 2, 2) / 2;
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
            this.realtimeHealthy = false;
            this.alertTimer = null;
            this._echoHooksBound = false;
            this._alertBaselineDone = false;
            this._lastReverbActivityAt = 0;
            this._lastHttpHeartbeatAt = 0;
            this._reverbWatchTimer = null;
            this.echoChannels = new Map();
            this.initialFitDone = false;
            this.motionRaf = null;
            this._tick = (t) => this.motionTick(t);

            // history
            this.historyLayers = [];
            this.historyActive = false;
            this.historyPulse = null;
            this._statusMarkers = [];
            this._addressCache = new Map();
            this._historyLoadSeq = 0;
            this._historyAbort = null;
            this._historyResponseCache = new Map();
            this._historyVehicle = null;
            this._historyPoints = [];
            this._historyPolylines = [];
            this._lazyStatusMarkerByIndex = new Map();
            this._statusBoundsListener = null;
            this._virtualEventScrollEl = null;
            this._fleetRenderer = null;
            this._historyAutoFitDone = false;

            // route playback
            this._playbackPoints = [];
            this._playbackIndex = 0;
            this._playbackTimer = null;
            this._playbackAnimFrame = null;
            this._playbackActive = false;
            this._isPlaying = false;
            this._playbackSpeed = 1;
            this._playbackFollow = false;

            // places
            this.placeLayers = [];

            // events
            this.eventMarker = null;
            this.iconBuilder = null;

            // Planned route trip overlay (fallback if RouteTripProgress fails).
            this._assignedRoutePolyline = null;
            /** @type {Map<number, google.maps.Polyline>} */
            this._vehicleRoutePolylines = new Map();
            this._snapCache = new Map();
            this._snapInflight = new Map();
            this.routeTripKit = null;
            this._routeTripDeviceId = null;
            this._routeBoundsFitted = false;
            this._focusedVehicleId = null;

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

        vehicleHasAssignedRoute(id) {
            return !!this.vehicles.get(Number(id))?.route_trip?.route;
        }

        /**
         * Show route progress / polyline only when the user selects a vehicle with an assigned route.
         */
        activateRouteTripForVehicle(id) {
            const numId = Number(id);
            if (!this.uiOn('polyline') && !this.uiOn('route_progress')) {
                this.clearRouteTripSelection();
                return;
            }
            if (!this.vehicleHasAssignedRoute(numId)) {
                const cached = this.vehicles.get(numId)?.route_trip;
                if (cached == null) {
                    this.clearRouteTripSelection();
                    return;
                }
                if (Number(this._routeTripDeviceId) === numId) {
                    this.clearRouteTripSelection();
                }
                this._routeTripDeviceId = numId;
                this.loadRouteTripForDevice(numId);
                return;
            }
            this.selectRouteTripVehicle(numId);
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
            const startMap = () => {
                if (!global.google?.maps?.Map) {
                    this.showError(cfg.i18n?.loadingMapFailed || 'Google Maps failed to initialize.');
                    return;
                }
                this.initMap();
            };

            if (global.GoogleMapsPlatform?.load) {
                global.GoogleMapsPlatform.load({
                    key: cfg.googleMapsKey,
                    mapId: cfg.googleMapsMapId,
                    libraries: ['marker'],
                }).then(startMap).catch(() => {
                    this.showError(cfg.i18n?.loadingMapFailed || 'Could not load Google Maps.');
                });
                return;
            }

            global[cbName] = () => {
                try { delete global[cbName]; } catch (_) { global[cbName] = undefined; }
                startMap();
            };

            const script = document.createElement('script');
            script.async = true;
            script.defer = true;
            script.onerror = () => this.showError(cfg.i18n?.loadingMapFailed || 'Could not load Google Maps.');
            script.src = `https://maps.googleapis.com/maps/api/js?key=${encodeURIComponent(cfg.googleMapsKey)}&loading=async&v=weekly&callback=${cbName}`;
            document.head.appendChild(script);
        }

        initMap() {
            const mapEl = document.getElementById('tcMap');
            if (!mapEl) return;

            this.map = new google.maps.Map(mapEl, mapInitOptions({
                center: DEFAULT_CENTER,
                zoom: 11,
                mapTypeControl: false,
                streetViewControl: false,
                fullscreenControl: false,
                zoomControl: false,
                gestureHandling: 'greedy',
                // Fleet markers are custom icons — avoid Google POI popups stealing clicks.
                clickableIcons: false,
            }));
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
                        if (this.hasSelectedMapIcon(p)) {
                            return 'body';
                        }
                        // No upload / broken custom → status arrow (Google-style default).
                        return 'pin';
                    },
                    getMarkerSizeScale: (p) => VM.resolveMarkerSizeScale(p),
                    getMapIconUrl: (p) => (this.hasSelectedMapIcon(p) ? (VM.resolveMapIconUrl(p) || null) : null),
                    getFallbackIconUrl: () => null,
                    getCustomIconUrl: (p) => VM.resolveCustomIconUrl(p),
                    getRotationEnabled: (p) => VM.resolveRotationEnabled(p),
                    getRotationOffset: (p) => VM.resolveIconRotationOffset(p),
                    shouldShowDirection: (_, state) => MOVING_KEYS.has(state),
                });
            }

            if (global.VehicleMapPopup) {
                const i = this.cfg.i18n || {};
                this.vehiclePopup = new global.VehicleMapPopup({
                    getMap: () => this.map,
                    googleMaps: google,
                    mapOverlay: true,
                    stateColors: this.stateColors,
                    commandsSendUrl: this.ui.hub?.commands ? this.cfg.commandsSendUrl : null,
                    commandTypes: this.cfg.commandTypes,
                    csrfToken: this.cfg.csrfToken,
                    i18n: {
                        dash: '—',
                        plate: i.lblPlate || 'Plate',
                        odometer: i.lblOdometer || 'Odometer',
                        status: i.lblStatus || 'Status',
                        statusDuration: i.lblDuration || 'Duration',
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
                    onClose: () => {
                        this._popupVehicleId = null;
                    },
                });
                this.map.addListener('click', () => {
                    if (global.GoogleMapsPlatform?.shouldSuppressMapClick?.()) return;
                    this.vehiclePopup?.close();
                });
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
            if (document.getElementById('tcPlaybackPanel')) {
                this.bindPlayback();
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
                this.bindFooterResize();
            }
            this.bindModules();
            this.initMapPanelPositions();
            if (document.getElementById('tcWorkspaceNav') || document.getElementById('tcNavToggle')) {
                this.bindNavToggle();
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

            // Keep map on fleet view; route progress appears only after a vehicle click.
            this._routeBoundsFitted = true;
            this.seedLivePositionsFromCache();
            this.renderLiveClusters();
            this.fitAllIfNeeded();

            const followId = parseInt(new URLSearchParams(global.location.search).get('follow'), 10);
            if (followId && this.vehicles.has(followId)) {
                this.setFollow(followId, true);
            }
            this.clearRouteTripSelection();
            if (!this.uiOn('polyline') && !this.uiOn('route_progress')) {
                this.clearAllRouteMapOverlays();
            }
            this.renderLiveClusters();
            this.initCompanyMapCard();
            this.initDriverMapCard();
            this.bindEchoRealtime();
            if (this.isEchoConnected()) {
                this.realtimeHealthy = true;
            } else if (this._needsHttpLivePoll()) {
                this.startPolling();
            }
        }

        /** Apply cached SSR positions so markers/clusters render before the first poll. */
        seedLivePositionsFromCache() {
            this.visible.forEach((id) => {
                this.ensureMarker(id);
                this.subscribePusher(id);
                const v = this.vehicles.get(id);
                if (v?.lat != null && v?.lng != null) {
                    this.applyPoint(id, v);
                }
            });
        }

        /** Fit map to all visible vehicles once we have at least one position. */
        fitAllIfNeeded() {
            if (this.initialFitDone || !this.map) return;
            let count = 0;
            this.visible.forEach((id) => {
                const v = this.vehicles.get(id);
                if (hasGeo(v?.lat, v?.lng)) count++;
            });
            if (count === 0) {
                if (this.vehicles.size === 0) {
                    this.map.setCenter(DEFAULT_CENTER);
                    this.map.setZoom(11);
                    this.initialFitDone = true;
                }
                return;
            }
            this.fitAll();
            this.renderLiveClusters();
            this.initialFitDone = true;
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
            const i18n = this.cfg.i18n || {};
            const vehicleTypeRaw = String(vehicle?.vehicle_type || 'car').toLowerCase();
            const typeLabels = i18n.vehicleTypeLabels || {};
            const typeLabelsAr = i18n.vehicleTypeLabelsAr || typeLabels;
            const vehicleType = typeLabels[vehicleTypeRaw]
                ? vehicleTypeRaw
                : (typeLabels.car ? 'car' : vehicleTypeRaw);
            const typeEn = typeLabels[vehicleType] || typeLabels.car || 'Vehicle';
            const typeAr = typeLabelsAr[vehicleType] || typeLabelsAr.car || typeEn;
            const fillLabel = (template, type) => String(template || ':type').replace(':type', type);
            const nameLabelEn = card.querySelector('[data-company-row="bus_name"] .tc-info-row__label-en');
            const nameLabelAr = card.querySelector('[data-company-row="bus_name"] .tc-info-row__label-ar');
            const plateLabelEn = card.querySelector('[data-company-row="bus_plate"] .tc-info-row__label-en');
            const plateLabelAr = card.querySelector('[data-company-row="bus_plate"] .tc-info-row__label-ar');
            if (nameLabelEn) nameLabelEn.textContent = fillLabel(i18n.companyMapVehicleName, typeEn);
            if (nameLabelAr) nameLabelAr.textContent = fillLabel(i18n.companyMapVehicleNameAr, typeAr);
            if (plateLabelEn) plateLabelEn.textContent = fillLabel(i18n.companyMapVehiclePlate, typeEn);
            if (plateLabelAr) plateLabelAr.textContent = fillLabel(i18n.companyMapVehiclePlateAr, typeAr);
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
                return `<div class="tc-row ${this._panelDeviceId === v.id ? 'active' : ''}" data-id="${v.id}">
                    <input type="checkbox" class="form-check-input tc-check tc-check-eye" data-eye="${v.id}" ${eye} title="${escHtml(i18n.colShow || 'Show on map')}">
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

        applyMarkerLabel(st, v, focused = false) {
            // Live fleet markers must anchor exactly on lat/lng (pin tip / icon pivot).
            // Floating labels shift Advanced Marker content and look offset from the road.
            if (!st?.marker || typeof st.marker.setLabel !== 'function') return;
            st.marker.setLabel(null);
        }

        countNearbyVehicles(targetId, radiusMeters = 35) {
            const target = this.vehicles.get(targetId);
            if (!target || !hasGeo(target.lat, target.lng)) return 0;
            const origin = { lat: parseFloat(target.lat), lng: parseFloat(target.lng) };
            let count = 0;
            this.visible.forEach((id) => {
                const v = this.vehicles.get(id);
                if (!hasGeo(v?.lat, v?.lng)) return;
                if (distMeters(origin, { lat: parseFloat(v.lat), lng: parseFloat(v.lng) }) <= radiusMeters) {
                    count++;
                }
            });
            return count;
        }

        updateMarkerFocusStyles() {
            const focusedId = this._focusedVehicleId;
            this.visible.forEach((id) => {
                const st = this.states.get(id);
                if (!st?.marker) return;
                const isFocused = focusedId != null && Number(id) === Number(focusedId);
                if (typeof st.marker.setZIndex === 'function') {
                    st.marker.setZIndex(isFocused ? 3500 + Number(id) : 1500 + Number(id));
                }
                if (typeof st.marker.setFocused === 'function') {
                    st.marker.setFocused(isFocused);
                }
                const v = this.vehicles.get(id);
                if (v) {
                    this.applyMarkerLabel(st, v, isFocused);
                }
            });
        }

        toggleVisible(id) {
            if (this.visible.has(id)) {
                this.visible.delete(id);
                this.teardownVehicle(id);
                if (this.followId === id) { this.followId = null; this.updateFollowBtn(); }
                if (Number(this._routeTripDeviceId) === Number(id)) {
                    if (this._panelDeviceId && this.vehicleHasAssignedRoute(this._panelDeviceId)) {
                        this.activateRouteTripForVehicle(this._panelDeviceId);
                    } else if (this.followId && this.vehicleHasAssignedRoute(this.followId)) {
                        this.activateRouteTripForVehicle(this.followId);
                    } else {
                        this.clearRouteTripSelection();
                    }
                }
            } else {
                this.visible.add(id);
                this.ensureMarker(id);
                this.subscribePusher(id);
            }
            this.renderList();
            this.syncVisibleVehicleRoutePolylines();
            this.renderLiveClusters();
            this.startPolling();
        }

        locateVehicle(id) {
            this._panelDeviceId = id;
            this.focusVehicleOnMap(id);
            this.activateRouteTripForVehicle(id);
            this.renderList();
            this.openDevicePanel(id);
        }

        /** Whether a vehicle marker/trail should be on the map (cluster break + overlap focus). */
        markerShouldShowOnMap(id, hasPosition = true) {
            if (!this.map || this.historyActive || !this.visible.has(Number(id))) return false;
            const numId = Number(id);
            const clustered = this.clusteredDeviceIds?.has(numId);
            const breakId = this.activeClusterBreakId();
            return this.shouldShowVehicleMarker(numId, hasPosition, clustered, breakId);
        }

        markerGpsPosition(marker) {
            if (!marker?.getPosition) return null;
            const pos = marker.getPosition();
            if (!pos) return null;
            const lat = typeof pos.lat === 'function' ? pos.lat() : Number(pos.lat);
            const lng = typeof pos.lng === 'function' ? pos.lng() : Number(pos.lng);
            if (!Number.isFinite(lat) || !Number.isFinite(lng)) return null;
            return { lat, lng };
        }

        openVehiclePopup(id, clickLatLng = null) {
            const numId = Number(id);
            const v = this.vehicles.get(numId);
            const st = this.vehicleState(numId);
            if (!v || !this.vehiclePopup) return;

            // Keep the compat wrapper (has _advanced). Native getAnchor() made the popup
            // open via InfoWindow and sit far above the click when other markers were present.
            const anchor = st.marker || null;
            const markerPos = this.markerGpsPosition(anchor) || this.markerGpsPosition(st.marker);
            const lat = clickLatLng?.lat ?? markerPos?.lat ?? st.renderPos?.lat ?? v.lat;
            const lng = clickLatLng?.lng ?? markerPos?.lng ?? st.renderPos?.lng ?? v.lng;
            if (!hasGeo(lat, lng)) return;

            this._popupVehicleId = numId;

            const point = {
                ...v,
                id: numId,
                lat: parseFloat(lat),
                lng: parseFloat(lng),
                heading: st.renderHeading ?? v.heading ?? v.angle ?? 0,
                status_label: v.status_label || v.status || v.status_key,
                status_key: v.status_key || 'offline',
            };

            global.GoogleMapsPlatform?.runAfterMarkerClick?.();
            this.vehiclePopup.open(point, anchor);
        }

        /** Marker click: stable map popup only — no cluster reflow or hiding nearby markers. */
        onMapMarkerClick(id, clickLatLng = null) {
            global.GoogleMapsPlatform?.runAfterMarkerClick?.();
            this.openVehiclePopup(id, clickLatLng);
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
                this.focusVehicleOnMap(id);
                if (this.vehicleHasAssignedRoute(id)) {
                    this.activateRouteTripForVehicle(id);
                } else {
                    this.clearRouteTripSelection();
                }
            } else if (this.followId === id) {
                this.followId = null;
                if (this._panelDeviceId && this.vehicleHasAssignedRoute(this._panelDeviceId)) {
                    this.activateRouteTripForVehicle(this._panelDeviceId);
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

        popupError(message, title) {
            const text = String(message || this.cfg.i18n?.loadFailed || 'Failed').trim();
            const heading = title || this.cfg.i18n?.accessDeniedTitle || 'Access Denied';
            if (global.Swal) {
                global.Swal.fire({
                    icon: 'error',
                    title: heading,
                    text,
                    confirmButtonText: this.cfg.i18n?.ok || 'OK',
                });
                return;
            }
            global.alert(text);
        }

        historyPermissionMessage(payload, status) {
            const bodyMessage = String(payload?.message || '').trim();
            if (status === 403 || status === 401) {
                return bodyMessage
                    || this.cfg.i18n?.historyPermissionDenied
                    || 'Access Denied. You do not have permission to view tracking history.';
            }
            return bodyMessage || `Request failed (${status})`;
        }

        isHistoryPermissionError(err) {
            return Boolean(err && (err.status === 401 || err.status === 403 || err.code === 'history_permission_denied'));
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

        /** True when a real custom/library icon is selected. Bare defaults → status arrow. */
        hasSelectedMapIcon(v) {
            const VM = global.VehicleMarker;
            if (!v || !VM) return false;
            if (VM.resolveCustomIconUrl?.(v)) return true;
            const src = String(v.map_icon_source || v.mapIconSource || 'default').toLowerCase();
            if (src === 'custom') return false; // orphaned / broken upload
            const path = String(v.map_builtin_icon_path || v.mapBuiltinIconPath || '').replace(/^\//, '');
            const type = String(v.vehicle_type || v.vehicleType || '').toLowerCase().trim();
            if (type.startsWith('shared_')) return true;
            if (type && type !== 'car' && type !== 'pin_marker' && type !== 'other') return true;
            // Explicit non-default builtin path (not the generic Vehicles/car.svg filler).
            if (path && !/^Vehicles\/car\.svg$/i.test(path)) return true;
            return false;
        }

        setVehicleMarkerIcon(st, v, heading) {
            const VM = global.VehicleMarker;
            const color = colorForPoint(v, this.stateColors);
            const h = heading != null ? heading : parseFloat(v.heading || 0);
            const point = { ...v, heading: h };
            // Orphaned custom (file missing) → treat as no icon.
            if (point.map_icon_source === 'custom' && !VM?.resolveCustomIconUrl?.(point)) {
                point.map_icon_source = 'default';
                point.map_custom_icon_url = null;
            }
            let icon = null;
            if (this.hasSelectedMapIcon(point)) {
                icon = this.iconBuilder?.iconFor(point) || null;
            }
            // No upload / broken custom → Google-style status arrow pin.
            if (!icon?.url) {
                icon = arrowIcon(color, h);
            }
            applyMarkerIcon(st.marker, icon);
            if (typeof st.marker?.setLabel === 'function') {
                st.marker.setLabel(null);
            }
        }

        /** Rebuild marker bitmap only when heading bucket / status color changes. */
        _markerIconSignature(v, heading) {
            const VM = global.VehicleMarker;
            const key = v?.status_key || 'offline';
            const offset = VM?.resolveIconRotationOffset?.(v) || 0;
            const enabled = VM?.resolveRotationEnabled?.(v) !== false;
            const final = VM?.finalRotation?.(heading, offset, enabled) ?? (((heading || 0) + offset + 360) % 360);
            const bucket = Math.round((((final || 0) % 360) + 360) % 360 / 2) * 2;
            const style = VM?.resolveMarkerStyle?.(v) || 'pin';
            const scale = VM?.resolveMarkerSizeScale?.(v) || 1;
            const custom = VM?.resolveMapIconUrl?.(v) || '';
            return `${key}|${colorForPoint(v, this.stateColors)}|${bucket}|${style}|${scale}|${custom}|${offset}`;
        }

        activeClusterBreakId() {
            const candidates = [this._focusedVehicleId, this._panelDeviceId, this.followId, this._routeTripDeviceId];
            for (const raw of candidates) {
                const id = raw == null ? null : Number(raw);
                if (id != null && this.visible.has(id)) return id;
            }
            return null;
        }

        /**
         * Ensure the selected vehicle marker is visible and the map is centered on it.
         */
        focusVehicleOnMap(id, options = {}) {
            const numId = Number(id);
            if (!Number.isFinite(numId) || !this.vehicles.has(numId) || !this.map) return;

            this._focusedVehicleId = numId;

            if (!this.visible.has(numId)) {
                this.visible.add(numId);
                this.ensureMarker(numId);
                this.subscribePusher(numId);
                this.startPolling();
            }
            this.ensureMarker(numId);

            if (options.merge && typeof options.merge === 'object') {
                const cur = this.vehicles.get(numId) || { id: numId };
                this.vehicles.set(numId, { ...cur, ...options.merge, id: numId });
            }

            const v = this.vehicles.get(numId);
            const st = this.vehicleState(numId);

            if (hasGeo(v?.lat, v?.lng)) {
                const lat = parseFloat(v.lat);
                const lng = parseFloat(v.lng);
                if (!st.motion) {
                    st.lastPoint = { ...v, lat, lng };
                }
                if (st.marker && !st.motion) {
                    this.placeVehicleMarker(
                        st,
                        numId,
                        lat,
                        lng,
                        colorForPoint(v, this.stateColors),
                        v.heading ?? v.angle ?? 0,
                    );
                } else if (!st.renderPos) {
                    st.renderPos = { lat, lng };
                    st.renderHeading = parseFloat(v.heading ?? v.angle ?? 0);
                }
            }

            this.renderLiveClusters();

            if (options.pan !== false && hasGeo(v?.lat, v?.lng)) {
                const lat = parseFloat(v.lat);
                const lng = parseFloat(v.lng);
                const nearby = this.countNearbyVehicles(numId);
                const inCluster = this.clusteredDeviceIds?.has(Number(numId));
                const crowded = nearby > 1 || inCluster;
                const targetZoom = crowded
                    ? (options.maxZoom ?? 20)
                    : Math.max(options.minZoom ?? 16, this.map.getZoom() || 0);
                this.map.setCenter({ lat, lng });
                if ((this.map.getZoom() || 0) !== targetZoom) {
                    this.map.setZoom(targetZoom);
                } else {
                    this.map.panTo({ lat, lng });
                }
            }

            this.updateMarkerFocusStyles();
        }

        /** When many vehicles overlap, show only the focused marker until selection changes. */
        shouldShowVehicleMarker(id, hasPosition, clustered, breakId) {
            if (!hasPosition) return false;
            const numId = Number(id);
            const focusedId = this._focusedVehicleId;
            if (focusedId != null && numId !== Number(focusedId)) {
                const focused = this.vehicles.get(focusedId);
                const other = this.vehicles.get(numId);
                if (hasGeo(focused?.lat, focused?.lng) && hasGeo(other?.lat, other?.lng)) {
                    const nearbyFocused = this.countNearbyVehicles(focusedId, 50);
                    if (nearbyFocused > 1) {
                        const d = distMeters(
                            { lat: parseFloat(focused.lat), lng: parseFloat(focused.lng) },
                            { lat: parseFloat(other.lat), lng: parseFloat(other.lng) },
                        );
                        if (d <= 45) return false;
                    }
                }
            }
            return !clustered || numId === Number(breakId);
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
                const marker = createMapMarker({
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
                const shouldShow = this.shouldShowVehicleMarker(id, hasPosition, clustered, breakId);
                if (st?.marker) st.marker.setMap(shouldShow ? this.map : null);
                st?.trailPolylines?.forEach((line) => line.setMap(shouldShow ? this.map : null));
            });
            this.clusteredDeviceIds = clusteredIds;
        }

        ensureMarker(id) {
            const v = this.vehicles.get(id);
            const st = this.vehicleState(id);
            if (!this.map || !v || st.marker) return;
            const pos = normalizeGps(v.lat, v.lng);
            st.marker = createMapMarker({
                map: pos && !this.historyActive ? this.map : null,
                position: pos || DEFAULT_CENTER,
                title: this.labelFor(v),
                zIndex: 1500 + id,
                optimized: false,
                // AdvancedMarkerElement: required for CSS heading rotation of flat image icons.
            });
            this.setVehicleMarkerIcon(st, v, v.heading || 0);
            st.marker.addListener('click', (e) => {
                if (e?.domEvent) {
                    e.domEvent.stopPropagation?.();
                    e.domEvent.preventDefault?.();
                }
                let clickLatLng = null;
                const ll = e?.latLng;
                if (ll) {
                    const lat = typeof ll.lat === 'function' ? ll.lat() : Number(ll.lat);
                    const lng = typeof ll.lng === 'function' ? ll.lng() : Number(ll.lng);
                    if (Number.isFinite(lat) && Number.isFinite(lng)) {
                        clickLatLng = { lat, lng };
                    }
                }
                global.GoogleMapsPlatform?.runAfterMarkerClick?.(() => {
                    this.onMapMarkerClick(id, clickLatLng);
                });
            });
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

        /** Snap cache key — 5 decimal places (~1.1 m). */
        _snapKey(lat, lng) {
            return `${Number(lat).toFixed(5)},${Number(lng).toFixed(5)}`;
        }

        /** Return cached road snap if Roads API already resolved this fix. */
        snapFromCache(lat, lng) {
            return this._snapCache.get(this._snapKey(lat, lng)) || null;
        }

        /**
         * Optional Google Roads snap (non-blocking). Marker + trail always share the
         * same coordinate once snap is applied.
         */
        queueRoadSnap(id, st, lat, lng, color, heading) {
            if (!this.cfg.roadsSnapEnabled || !this.cfg.googleMapsKey) return;
            const key = this._snapKey(lat, lng);
            if (this._snapCache.has(key) || this._snapInflight.has(key)) return;
            this._snapInflight.set(key, true);
            const url = `https://roads.googleapis.com/v1/snapToRoads?path=${encodeURIComponent(`${lat},${lng}`)}&interpolate=false&key=${encodeURIComponent(this.cfg.googleMapsKey)}`;
            fetch(url, { credentials: 'omit', cache: 'no-store' })
                .then((r) => (r.ok ? r.json() : null))
                .then((data) => {
                    const loc = data?.snappedPoints?.[0]?.location;
                    if (!loc || loc.latitude == null || loc.longitude == null) return;
                    const snapped = normalizeGps(loc.latitude, loc.longitude);
                    if (!snapped) return;
                    this._snapCache.set(key, snapped);
                    const vehicle = this.vehicles.get(id);
                    const last = st.lastPoint;
                    if (!vehicle || !last) return;
                    if (Math.abs(last.lat - lat) > 1e-5 || Math.abs(last.lng - lng) > 1e-5) return;
                    vehicle.lat = snapped.lat;
                    vehicle.lng = snapped.lng;
                    st.lastPoint = { ...last, lat: snapped.lat, lng: snapped.lng };
                    if (st.trail.length > 0) {
                        st.trail[st.trail.length - 1] = { lat: snapped.lat, lng: snapped.lng };
                    }
                    this.placeVehicleMarker(st, id, snapped.lat, snapped.lng, color, heading, true);
                    this.renderLiveClusters();
                })
                .catch(() => {})
                .finally(() => this._snapInflight.delete(key));
        }

        /**
         * Commit a real GPS fix to the travelled-path buffer (not animation frames).
         */
        commitGpsTrailPoint(st, lat, lng) {
            const head = normalizeGps(lat, lng);
            if (!head) return;
            const committed = st.trail;
            const last = committed[committed.length - 1];
            if (!last) {
                committed.push({ ...head });
                return;
            }
            const stepM = distMeters(last, head);
            if (stepM >= TRAIL_MIN_STEP_M) {
                committed.push({ ...head });
                while (committed.length > TRAIL_MAX) committed.shift();
            } else if (stepM > 0.05) {
                committed[committed.length - 1] = { ...head };
            }
        }

        /**
         * Polyline path = committed GPS vertices + current render head (marker).
         * While animating, the in-flight target is excluded so the tail follows
         * the marker without a straight jump to the not-yet-reached fix.
         */
        buildTrailPath(st, renderLat, renderLng) {
            const head = normalizeGps(renderLat, renderLng);
            if (!head) return [];
            const committed = st.trail.map((p) => ({ lat: p.lat, lng: p.lng }));
            if (committed.length === 0) return [{ ...head }];

            if (st.motion) {
                const base = committed.length >= 2 ? committed.slice(0, -1) : committed.slice(0, 1);
                const path = [...base, { ...head }];
                if (path.length < 2 && committed.length >= 2) {
                    return [committed[0], { ...head }];
                }
                return path.length >= 2 ? path : [{ ...head }];
            }

            const path = [...committed];
            path[path.length - 1] = { ...head };
            return path;
        }

        syncTrailPolyline(st, lat, lng, color, deviceId) {
            if (!this.map) return;
            const key = st.lastPoint?.status_key || this.vehicles.get(deviceId)?.status_key || '';
            if (!MOVING_KEYS.has(key)) {
                this.clearTrail(st);
                return;
            }
            const path = this.buildTrailPath(st, lat, lng);
            if (path.length < 2) {
                st.trailPolylines.forEach((l) => l.setMap(null));
                return;
            }

            let line = st.trailPolylines[0];
            if (!line) {
                line = new google.maps.Polyline({
                    map: this.map,
                    path,
                    strokeColor: color,
                    strokeOpacity: 0.75,
                    strokeWeight: 5,
                    zIndex: 80,
                    clickable: false,
                    geodesic: true,
                });
                st.trailPolylines = [line];
            } else {
                line.setPath(path);
                line.setOptions({ strokeColor: color });
            }
            const showTrail = this.markerShouldShowOnMap(deviceId, true);
            line.setMap(showTrail ? this.map : null);
        }

        /** Marker + render cache — always the device GPS lat/lng. */
        placeVehicleMarker(st, id, lat, lng, color, heading) {
            const pos = normalizeGps(lat, lng);
            if (!pos || !st.marker) return;
            st.renderPos = pos;
            if (heading != null && Number.isFinite(Number(heading))) {
                st.renderHeading = Number(heading);
            }
            const show = this.markerShouldShowOnMap(id, true);
            st.marker.setMap(show ? this.map : null);
            st.marker.setPosition({ lat: pos.lat, lng: pos.lng });
            const vehicle = this.vehicles.get(id);
            if (vehicle) {
                const sig = this._markerIconSignature(
                    { ...vehicle, color: color || colorForPoint(vehicle, this.stateColors) },
                    st.renderHeading,
                );
                if (st._iconSig !== sig) {
                    st._iconSig = sig;
                    this.setVehicleMarkerIcon(
                        st,
                        { ...vehicle, lat: pos.lat, lng: pos.lng, color: color || colorForPoint(vehicle, this.stateColors) },
                        st.renderHeading,
                    );
                }
            }
            const key = st.lastPoint?.status_key || vehicle?.status_key || '';
            if (MOVING_KEYS.has(key)) {
                this.syncTrailPolyline(st, pos.lat, pos.lng, color || colorForPoint(vehicle || {}, this.stateColors), id);
            }
        }

        applyPoint(id, point) {
            if (!this.visible.has(id) || !point) return;
            const gps = normalizeGps(point.lat, point.lng);
            if (!gps) return;
            const st = this.vehicleState(id);
            this.ensureMarker(id);
            const merged = { ...this.vehicles.get(id), ...point, ...gps, id };
            if (point.route_trip == null && this.vehicles.get(id)?.route_trip) {
                merged.route_trip = this.vehicles.get(id).route_trip;
            }
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
                const rp = st.renderPos;
                this.vehiclePopup.update({
                    ...merged,
                    id,
                    lat: rp?.lat ?? merged.lat,
                    lng: rp?.lng ?? merged.lng,
                    heading: st.renderHeading ?? merged.heading,
                });
            }
            if (this._routeTripDeviceId != null
                && Number(this._routeTripDeviceId) === Number(id)
                && merged.route_trip) {
                this.applyRouteTrip(id, merged.route_trip);
            }
            this.syncCompanyMapCard();
            this.syncDriverMapCard();

            const key = merged.status_key || 'offline';
            if (!MOVING_KEYS.has(key)) this.clearTrail(st);

            if (this.historyActive) { st.lastPoint = merged; return; }

            st.marker.setTitle(this.labelFor(merged));

            const moving = MOVING_KEYS.has(key);
            const spd = Math.max(0, parseFloat(merged.speed) || 0);

            // Single source of truth: marker always on the device GPS fix (no drift / dead-reckon).
            st.motion = null;
            st.lastPoint = merged;
            let h = parseFloat(merged.heading);
            if (!Number.isFinite(h) || (!moving && spd < 3)) {
                h = st.renderHeading != null ? st.renderHeading : 0;
            }
            if (moving) this.commitGpsTrailPoint(st, merged.lat, merged.lng);
            else this.clearTrail(st);
            this.placeVehicleMarker(st, id, merged.lat, merged.lng, liveColor, h);
            this.renderLiveClusters();
        }

        /**
         * Glide from the current rendered position to the latest GPS fix.
         * Stops exactly on the fix — no dead-reckoning past the target.
         */
        startMotion(id, to) {
            const st = this.vehicleState(id);
            const toLL = normalizeGps(to.lat, to.lng);
            if (!toLL) return;
            const from = st.renderPos
                || (st.lastPoint ? normalizeGps(st.lastPoint.lat, st.lastPoint.lng) : toLL)
                || toLL;
            const fromH = st.renderHeading != null ? st.renderHeading : parseFloat(to.heading || 0);
            const moving = MOVING_KEYS.has(to.status_key || 'offline');
            const speedKmh = Math.max(0, parseFloat(to.speed) || 0);
            const interval = this.cfg.pollIntervalMs || 2000;
            const segMeters = distMeters(from, toLL);

            let toH = parseFloat(to.heading);
            if (!Number.isFinite(toH) || (!moving && speedKmh < 3)) toH = fromH;

            let catchupMs = Math.min(interval * 0.85, 1800);
            if (segMeters > 400) catchupMs = Math.min(catchupMs, 900);
            else if (segMeters < 4) catchupMs = Math.min(catchupMs, 450);

            st.dupSince = null;
            if (moving) this.commitGpsTrailPoint(st, toLL.lat, toLL.lng);
            else this.clearTrail(st);
            st.motion = {
                from,
                to: toLL,
                fromH,
                toH,
                speedKmh,
                color: colorForPoint(to, this.stateColors),
                catchupMs: Math.max(200, catchupMs),
                staleCruise: false,
                start: performance.now(),
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
                    const rawT = m.catchupMs > 0 ? Math.min(1, elapsed / m.catchupMs) : 1;
                    const t = easeInOutQuad(rawT);
                    lat = m.from.lat + (m.to.lat - m.from.lat) * t;
                    lng = m.from.lng + (m.to.lng - m.from.lng) * t;
                    heading = lerpHeading(m.fromH, m.toH, t);
                    if (rawT >= 1) done = true;
                } else {
                    lat = m.to.lat;
                    lng = m.to.lng;
                    heading = m.toH;
                    done = true;
                }

                this.placeVehicleMarker(st, id, lat, lng, m.color, heading);

                if (this.vehiclePopup?.isOpenFor(id)) {
                    const v = this.vehicles.get(id);
                    if (v) {
                        this.vehiclePopup.update({
                            ...v,
                            id,
                            lat,
                            lng,
                            heading,
                        });
                    }
                }

                if (done) {
                    st.motion = null;
                    if (!m.staleCruise && MOVING_KEYS.has(st.lastPoint?.status_key || '')) {
                        this.syncTrailPolyline(st, m.to.lat, m.to.lng, m.color, id);
                    }
                } else {
                    active = true;
                }
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
        /** WebSocket transport up (does not guarantee channel events are flowing). */
        isEchoConnected() {
            return global.Echo?.connector?.pusher?.connection?.state === 'connected';
        }

        _reverbEventsRecent(maxMs) {
            const limit = maxMs ?? (this.cfg.reverbStaleMs || 45000);
            return this._lastReverbActivityAt > 0
                && (Date.now() - this._lastReverbActivityAt) < limit;
        }

        /** Reverb connected and GPS events are arriving — skip live-json HTTP. */
        _reverbGpsActive() {
            return this.realtimeHealthy && this._reverbEventsRecent();
        }

        _needsHttpLivePoll(force = false) {
            if (force) return true;
            if (document.hidden || this.visible.size === 0) return false;
            // Reverb socket up → no repeating live-json (watchdog may do rare heartbeat).
            if (this.realtimeHealthy) return false;
            return true;
        }

        /** One-off HTTP poll when Reverb is up but no GPS event for a long time. */
        _maybeStaleReverbHeartbeat() {
            if (!this.realtimeHealthy || document.hidden || this.visible.size === 0) return;
            if (this._reverbEventsRecent()) return;
            const minGap = this.cfg.reverbStaleMs || 45000;
            const since = Date.now() - (this._lastHttpHeartbeatAt || 0);
            if (this._lastHttpHeartbeatAt && since < minGap) return;
            this._lastHttpHeartbeatAt = Date.now();
            this.pollLive(true);
        }

        stopLivePolling() {
            if (this.pollTimer) {
                clearInterval(this.pollTimer);
                this.pollTimer = null;
            }
        }

        syncRealtimePolling() {
            const wsConnected = this.isEchoConnected();
            if (wsConnected !== this.realtimeHealthy) {
                this.realtimeHealthy = wsConnected;
                if (typeof console !== 'undefined' && console.info) {
                    console.info(
                        '[traccar-ui] Reverb',
                        wsConnected
                            ? 'connected — live-json polling stopped'
                            : 'unavailable — HTTP polling active',
                    );
                }
            }
            if (this._needsHttpLivePoll()) {
                this.startPolling();
            } else {
                this.stopLivePolling();
            }
            if (wsConnected) {
                if (this.alertTimer) {
                    clearInterval(this.alertTimer);
                    this.alertTimer = null;
                }
            } else {
                this.startAlertPollingFallback();
            }
        }

        bindEchoRealtime() {
            if (this._echoHooksBound) return;
            this._echoHooksBound = true;

            const sync = () => this.syncRealtimePolling();
            global.addEventListener('reverb:connected', sync);
            global.addEventListener('reverb:disconnected', sync);
            global.addEventListener('focus', sync);

            document.addEventListener('visibilitychange', () => {
                if (document.hidden) {
                    this.pausePolling();
                } else {
                    this.syncRealtimePolling();
                }
            });

            if (!global.Echo) {
                this.realtimeHealthy = false;
                this.startPolling();
                this.startAlertPollingFallback();
                return;
            }

            this._reverbWatchTimer = setInterval(() => {
                if (!this.isEchoConnected()) {
                    this.realtimeHealthy = false;
                    if (!document.hidden && this.visible.size > 0) {
                        this.startPolling();
                    }
                    return;
                }
                this.realtimeHealthy = true;
                this.stopLivePolling();
                this._maybeStaleReverbHeartbeat();
            }, 20000);

            setTimeout(sync, 800);
            setTimeout(sync, 2500);
            setTimeout(() => {
                if (!this.isEchoConnected()) sync();
                else this.syncRealtimePolling();
            }, 6000);
        }

        resolveLivePollIntervalMs() {
            if (document.hidden || this.visible.size === 0) return 0;

            const fast = this.cfg.pollIntervalMs || 10000;
            const count = this.visible.size;
            if (count > 40) return Math.max(fast, 15000);
            if (count > 20) return Math.max(fast, 12000);
            return fast;
        }

        pausePolling() {
            this.stopLivePolling();
            if (this.alertTimer) { clearInterval(this.alertTimer); this.alertTimer = null; }
        }

        resumePolling() {
            this.syncRealtimePolling();
        }

        startPolling() {
            if (!this._needsHttpLivePoll()) {
                this.stopLivePolling();
                return;
            }
            if (this.visible.size === 0 || document.hidden) return;
            const ms = this.resolveLivePollIntervalMs();
            if (ms <= 0) return;

            const hadTimer = !!this.pollTimer;
            if (this.pollTimer) {
                clearInterval(this.pollTimer);
                this.pollTimer = null;
            }
            if (!hadTimer) {
                this.pollLive(true);
            }
            this.pollTimer = setInterval(() => this.pollLive(false), ms);
        }

        startAlertPollingFallback() {
            if (this.realtimeHealthy) return;
            if (this.alertTimer) { clearInterval(this.alertTimer); this.alertTimer = null; }
            if (!this.cfg.eventsJsonUrl || document.hidden) return;
            if (!this._alertBaselineDone) {
                this._alertBaselineDone = true;
                this.pollAlerts(true);
            }
            const interval = this.cfg.alertPollIntervalMs || 30000;
            this.alertTimer = setInterval(() => this.pollAlerts(false), interval);
        }

        /** @deprecated use startAlertPollingFallback */
        restartAlertPolling() {
            this.startAlertPollingFallback();
        }

        async pollLive(force) {
            if (!force && !this._needsHttpLivePoll()) return;
            if (this.pollInFlight) return;
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
                this.fitAllIfNeeded();
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
                    const loc = payload?.location && payload.location.lat != null
                        ? payload.location
                        : payload;
                    const gps = loc ? normalizeGps(loc.lat, loc.lng) : null;
                    if (!gps) return;
                    const merged = { ...loc, ...gps };
                    if (merged.id == null) merged.id = id;
                    if (merged.color == null) {
                        merged.color = colorForPoint(merged, this.stateColors);
                    }
                    this._lastReverbActivityAt = Date.now();
                    this.applyPoint(id, merged);
                    this.stopLivePolling();
                });
                this.echoChannels.set(id, channel);
                if (this.isEchoConnected()) {
                    this.realtimeHealthy = true;
                    this.stopLivePolling();
                } else {
                    this.syncRealtimePolling();
                }
            } catch (err) { console.warn('[traccar-ui] echo subscribe failed', id, err); }
        }

        unsubscribePusher(id) {
            if (!this.echoChannels.has(id)) return;
            try { global.Echo.leave(`device.${id}`); } catch (_) { /* ignore */ }
            this.echoChannels.delete(id);
            this.syncRealtimePolling();
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
            /* Keep fleet panel + module nav open until the user closes them. */
        }

        bindNavToggle() {
            const panelToggle = document.getElementById('tcPanelToggle');
            const closeBtn = document.getElementById('tcNavClose');
            const revealBtn = document.getElementById('tcNavRevealBtn');
            const backdrop = document.getElementById('tcPanelBackdrop');
            const app = document.querySelector('.tc-app');
            if (!app) return;

            this.setModuleNavOpen(true, { persist: false });
            this.setPanelOpen(!this.isMobile(), { persist: false });

            panelToggle?.addEventListener('click', (e) => {
                e.stopPropagation();
                const open = !app.classList.contains('tc-app--panel-open');
                this.setPanelOpen(open, { persist: false });
            });

            closeBtn?.addEventListener('click', (e) => {
                e.stopPropagation();
                this.setModuleNavOpen(false, { persist: false });
            });

            revealBtn?.addEventListener('click', (e) => {
                e.stopPropagation();
                this.setModuleNavOpen(true, { persist: false });
            });

            backdrop?.addEventListener('click', () => {
                if (this.isMobile()) this.setPanelOpen(false, { persist: false });
            });

            document.addEventListener('click', (e) => {
                if (!this.isMobile() || !app.classList.contains('tc-app--panel-open')) return;
                const panel = document.getElementById('tcPanel');
                const nav = document.getElementById('tcWorkspaceNav');
                const reveal = document.getElementById('tcNavReveal');
                if (panel?.contains(e.target) || nav?.contains(e.target) || reveal?.contains(e.target)) return;
                this.setPanelOpen(false, { persist: false });
            });

            document.addEventListener('keydown', (e) => {
                if (e.key === 'Escape' && this.isMobile()) {
                    this.setPanelOpen(false, { persist: false });
                }
            });

            let raf = null;
            const onResize = () => {
                if (raf) cancelAnimationFrame(raf);
                raf = requestAnimationFrame(() => {
                    const panelOpen = app.classList.contains('tc-app--panel-open');
                    document.getElementById('tcPanelBackdrop')?.classList.toggle('show', panelOpen && this.isMobile());
                    this.fitAppHeight();
                    this.mapResize();
                });
            };
            global.addEventListener('resize', onResize);
            global.addEventListener('orientationchange', onResize);
        }

        setModuleNavOpen(open, options = {}) {
            const root = document.documentElement;
            const body = document.body;
            const shouldOpen = !!open;
            root.classList.toggle('tc-module-nav-open', shouldOpen);
            root.classList.toggle('tc-module-nav-collapsed', !shouldOpen);
            body?.classList.toggle('tc-module-nav-open', shouldOpen);
            body?.classList.toggle('tc-module-nav-collapsed', !shouldOpen);
            if (options.persist) {
                try {
                    global.localStorage.setItem('tcModuleNavOpen', shouldOpen ? '1' : '0');
                } catch (_) { /* ignore */ }
            }
            this.mapResize();
        }

        setNavPinned() {
            /* Pin removed — nav defaults open; user collapse is remembered via tcModuleNavOpen. */
        }

        setPanelOpen(open, options = {}) {
            const app = document.querySelector('.tc-app');
            if (!app) return;
            const shouldOpen = !!open;
            const mobile = this.isMobile();
            app.classList.toggle('tc-app--panel-open', shouldOpen);
            document.getElementById('tcPanelToggle')?.classList.toggle('active', shouldOpen);
            document.getElementById('tcPanelToggle')?.setAttribute('aria-expanded', shouldOpen ? 'true' : 'false');

            const panel = document.getElementById('tcPanel');
            const backdrop = document.getElementById('tcPanelBackdrop');
            panel?.classList.toggle('tc-panel--open', shouldOpen);
            backdrop?.classList.toggle('show', shouldOpen && mobile);

            if (options.persist) {
                try { global.localStorage.setItem('tcPanelOpen', shouldOpen ? '1' : '0'); } catch (_) { /* ignore */ }
            }
            this.mapResize();
        }

        setNavOpen(open) {
            this.setModuleNavOpen(open);
            this.setPanelOpen(!!open);
        }

        closeNav() {
            this.setPanelOpen(false);
        }

        openPanel() {
            this.setPanelOpen(true);
        }

        closePanel() {
            this.setPanelOpen(false);
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
            if (this._playbackPoints.length && document.getElementById('tcPlaybackPanel')?.classList.contains('active')) {
                this._playbackFollow = !this._playbackFollow;
                const btn = document.getElementById('tcFollow');
                btn?.classList.toggle('active', this._playbackFollow);
                btn?.setAttribute('aria-pressed', this._playbackFollow ? 'true' : 'false');
                if (this._playbackFollow && this._playbackPoints[this._playbackIndex]) {
                    const p = this._playbackPoints[this._playbackIndex];
                    this.map?.panTo({ lat: p.lat, lng: p.lng });
                }
                return;
            }
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
                this._stopPanelDurationTick();
                this._panelAbort?.abort();
                this._footerMode = null;
                this._panelDeviceId = null;
                this._focusedVehicleId = null;
                this.clearRouteTripSelection();
                this.renderLiveClusters();
                this.resizeMapSoon();
            });
        }

        mi(key, fallback) {
            const i18n = this.cfg.i18n || {};
            return i18n[key] || fallback;
        }

        scheduleIdleWork(fn) {
            if (typeof requestIdleCallback === 'function') {
                requestIdleCallback(() => fn(), { timeout: 120 });
            } else {
                setTimeout(fn, 0);
            }
        }

        ensureFleetRenderer() {
            if (!this.map) return null;
            if (!global.FleetMapRenderer) return null;
            if (!this._fleetRenderer) {
                this._fleetRenderer = new global.FleetMapRenderer({
                    googleMaps: global.google,
                    getIdentity: (p) => ({ title: p.title || p.name || this._historyName || '', plate: p.plate || '' }),
                    getState: (p) => p.status_key || 'offline',
                    getColor: (state) => this.stateColors[state] || this.stateColors.offline || '#94a3b8',
                    getVehicleType: (p) => p.vehicle_type || 'car',
                    getMarkerStyle: (p) => {
                        const VM = global.VehicleMarker;
                        if (VM?.resolveMapIconUrl?.(p)) return 'body';
                        return VM?.resolveMarkerStyle?.(p) || 'labeled';
                    },
                    getMarkerSizeScale: (p) => global.VehicleMarker?.resolveMarkerSizeScale?.(p) ?? 1,
                    getMapIconUrl: (p) => global.VehicleMarker?.resolveMapIconUrl?.(p)
                        || global.VehicleMarker?.resolveFallbackIconUrl?.(p)
                        || null,
                    getFallbackIconUrl: (p) => global.VehicleMarker?.resolveFallbackIconUrl?.(p)
                        || '/icons/builtin/Vehicles/car.svg',
                    getCustomIconUrl: (p) => global.VehicleMarker?.resolveCustomIconUrl?.(p) ?? null,
                    getRotationEnabled: (p) => global.VehicleMarker?.resolveRotationEnabled?.(p) !== false,
                    getRotationOffset: (p) => global.VehicleMarker?.resolveIconRotationOffset?.(p) || 0,
                    shouldShowDirection: (_, state) => MOVING_KEYS.has(state),
                    isHidden: (p) => !hasGeo(p?.lat, p?.lng),
                    speedToColor,
                    mediumSpeedKmh: MEDIUM_SPEED,
                    overSpeedLimit: OVER_SPEED,
                    animDurationMs: this.cfg.animDurationMs || 1200,
                    startIconUrl: this.cfg.startIconUrl,
                    endIconUrl: this.cfg.endIconUrl,
                });
                this._fleetRenderer.attachMap(this.map);
            }
            return this._fleetRenderer;
        }

        buildHistoryRequestUrl(baseUrl, params) {
            const qs = params.toString();
            return qs ? `${baseUrl}?${qs}` : baseUrl;
        }

        appendCacheBust(url) {
            const sep = url.includes('?') ? '&' : '?';
            return `${url}${sep}_=${Date.now()}`;
        }

        mergeAbortSignals(...signals) {
            const ctrl = new AbortController();
            signals.forEach((signal) => {
                if (!signal) return;
                if (signal.aborted) {
                    ctrl.abort();
                    return;
                }
                signal.addEventListener('abort', () => ctrl.abort(), { once: true });
            });
            return ctrl.signal;
        }

        async fetchHistoryJson(url, signal, timeoutMs = 120000) {
            const cached = this._historyResponseCache.get(url);
            if (cached && (Date.now() - cached.ts) < HISTORY_CACHE_TTL_MS) {
                return cached.data;
            }

            const timeoutCtrl = new AbortController();
            const timeoutId = setTimeout(() => timeoutCtrl.abort(), timeoutMs);
            const fetchSignal = this.mergeAbortSignals(signal, timeoutCtrl.signal);

            try {
                const response = await fetch(url, { credentials: 'same-origin', cache: 'no-store', signal: fetchSignal });
                let json = {};
                try {
                    json = await response.json();
                } catch (_) {
                    if (response.ok) {
                        throw new Error('Invalid history response');
                    }
                    json = {};
                }
                if (!response.ok) {
                    const err = new Error(this.historyPermissionMessage(json, response.status));
                    err.status = response.status;
                    if (response.status === 401 || response.status === 403) {
                        err.code = 'history_permission_denied';
                    }
                    throw err;
                }
                const data = { response, json };
                this._historyResponseCache.set(url, { ts: Date.now(), data });
                if (this._historyResponseCache.size > HISTORY_CACHE_MAX_ENTRIES) {
                    const oldest = [...this._historyResponseCache.entries()].sort((a, b) => a[1].ts - b[1].ts)[0];
                    if (oldest) this._historyResponseCache.delete(oldest[0]);
                }
                return data;
            } catch (err) {
                if (timeoutCtrl.signal.aborted && !signal?.aborted) {
                    throw new Error('History request timed out');
                }
                throw err;
            } finally {
                clearTimeout(timeoutId);
            }
        }

        historyBannerLabel(key, state) {
            const map = {
                route: { loading: ['loadingRoute', 'Loading route…'], done: ['routeLoaded', '✓ Route loaded'] },
                stats: { loading: ['loadingStatistics', 'Loading statistics…'], done: ['statisticsLoaded', '✓ Statistics loaded'] },
                timeline: { loading: ['loadingTimeline', 'Loading timeline…'], done: ['timelineLoaded', '✓ Timeline loaded'] },
                events: { loading: ['loadingEvents', 'Loading events…'], done: ['eventsLoaded', '✓ Events loaded'] },
                stops: { loading: ['loadingStops', 'Loading stops…'], done: ['stopsLoaded', '✓ Stops loaded'] },
            };
            const entry = map[key]?.[state] || map[key]?.loading;
            return entry ? this.mi(entry[0], entry[1]) : '';
        }

        setHistoryLoadBanner(key, state, message) {
            const banner = document.getElementById('tcHistoryLoadBanner');
            if (!banner) return;
            banner.hidden = false;
            const item = banner.querySelector(`[data-load="${key}"]`);
            if (!item) return;
            item.dataset.state = state;
            const label = item.querySelector('.map-history-load-banner__label');
            if (label) label.textContent = message || this.historyBannerLabel(key, state);
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

        beginHistoryLoadBanner() {
            ['route', 'timeline', 'events', 'stops', 'stats'].forEach((key) => {
                this.setHistoryLoadBanner(key, 'loading', this.historyBannerLabel(key, 'loading'));
            });
            const skeleton = (msg) => `<div class="tc-empty"><i class="fas fa-circle-notch fa-spin" aria-hidden="true"></i> ${escHtml(msg)}</div>`;
            const summary = document.getElementById('tcHistSummary');
            const results = document.getElementById('tcHistResults');
            if (summary) {
                summary.hidden = false;
                summary.innerHTML = skeleton(this.mi('loadingStatistics', 'Loading statistics…'));
            }
            if (results) {
                results.innerHTML = skeleton(this.mi('loadingEvents', 'Loading events…'));
            }
        }

        unbindLazyStatusMarkers() {
            if (this._statusBoundsListener) {
                global.google.maps.event.removeListener(this._statusBoundsListener);
                this._statusBoundsListener = null;
            }
        }

        clearLazyStatusMarkers() {
            this._lazyStatusMarkerByIndex.forEach((m) => m.setMap(null));
            this._lazyStatusMarkerByIndex.clear();
        }

        stopPlayback() {
            clearInterval(this._playbackTimer);
            clearTimeout(this._playbackTimer);
            this._playbackTimer = null;
            if (this._playbackAnimFrame) {
                cancelAnimationFrame(this._playbackAnimFrame);
                this._playbackAnimFrame = null;
            }
            this._isPlaying = false;
            this._playbackActive = false;
            this._fleetRenderer?.setPlaybackActive(false);
            this.setPlayPauseUi(false);
            this.updatePlaybackMeta();
        }

        clearHistory() {
            this._fleetRenderer?.cancelProgressiveDraw();
            this.unbindLazyStatusMarkers();
            this.clearLazyStatusMarkers();
            this._historyPolylines.forEach((l) => l.setMap(null));
            this._historyPolylines = [];
            this.historyLayers.forEach((l) => l.setMap(null));
            this.historyLayers = [];
            this._statusMarkers = [];
            this._histFastPath = null;
            this._historyVehicle = null;
            this._historyPoints = [];
            this._virtualEventScrollEl = null;
            this.historyPulse?.hide();
            this.stopInfo?.close();
            this.stopPlayback();
            this._playbackPoints = [];
            this._playbackIndex = 0;
            this._fleetRenderer?.clearRoute({ keepVehicle: false });
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
            this._historyAutoFitDone = false;
            this.setPlaybackPanelOpen(false);
            ['route', 'timeline', 'events', 'stops', 'stats'].forEach((k) => {
                const banner = document.getElementById('tcHistoryLoadBanner');
                const item = banner?.querySelector(`[data-load="${k}"]`);
                if (item) item.dataset.state = 'idle';
            });
            document.getElementById('tcHistoryLoadBanner')?.setAttribute('hidden', 'hidden');
        }

        exitHistory() {
            this._historyAbort?.abort();
            this.clearHistory();
            this.historyActive = false;
            this.showLiveLayer(true);
        }

        prepareHistoryRoute() {
            const renderer = this.ensureFleetRenderer();
            renderer?.cancelProgressiveDraw();
            this.unbindLazyStatusMarkers();
            this.clearLazyStatusMarkers();
            this._historyPolylines.forEach((l) => l.setMap(null));
            this._historyPolylines = [];
            this.historyLayers.forEach((l) => l.setMap(null));
            this.historyLayers = [];
            this._statusMarkers = [];
            renderer?.clearRoute({ keepVehicle: false });
            this.historyPulse?.hide();
            this.stopPlayback();
        }

        normalizeHistoryPoint(p) {
            if (!p || !hasGeo(p.lat, p.lng)) return null;
            return {
                lat: parseFloat(p.lat),
                lng: parseFloat(p.lng),
                speed: parseFloat(p.speed || 0),
                heading: p.heading != null ? parseFloat(p.heading) : null,
                altitude: p.altitude != null ? parseFloat(p.altitude) : null,
                ignition: p.ignition,
                recorded_at: p.recorded_at || p.timestamp || null,
                status_key: p.status_key || null,
                color: p.color || null,
                name: p.name || null,
                title: p.title || null,
                plate: p.plate || null,
                vehicle_type: p.vehicle_type || null,
            };
        }

        async renderHistoryRouteProgressive(points, vehicle, seq) {
            const renderer = this.ensureFleetRenderer();
            if (!renderer) {
                this.setHistoryLoadBanner('route', 'done');
                return null;
            }

            const workerUrl = this.cfg.historyWorkerUrl || null;
            let processed = null;
            const processRoute = global.HistoryMapProcessor?.processRoute;
            if (processRoute) {
                try {
                    processed = await processRoute(points, {
                        maxPoints: 2500,
                        mediumSpeedKmh: MEDIUM_SPEED,
                        overSpeedLimit: OVER_SPEED,
                        workerTimeoutMs: 10000,
                    }, workerUrl);
                } catch (procErr) {
                    console.warn('[traccar-ui] route processing fallback', procErr);
                }
            }
            if (seq !== this._historyLoadSeq) return null;

            const simplified = processed?.simplified?.length ? processed.simplified : points;
            const chunks = processed?.chunks || [];
            const endpoints = { start: simplified[0], end: simplified[simplified.length - 1] };
            const i18n = this.cfg.i18n || {};

            const syncRouteLayers = () => {
                this._historyPolylines = renderer.polylines || [];
                this.historyLayers = [
                    ...this._historyPolylines,
                    ...(renderer.glowPolylines || []),
                    renderer.startMarker,
                    renderer.endMarker,
                ].filter(Boolean);
            };

            if (!chunks.length) {
                if (simplified.length >= 2) {
                    renderer.drawRoute(simplified, {
                        clickable: false,
                        startTitle: i18n.routeStart || 'Route start',
                        endTitle: i18n.routeEnd || 'Route end',
                    });
                } else {
                    renderer.setRouteEndpoints(endpoints.start, endpoints.end, {
                        startTitle: i18n.routeStart || 'Route start',
                        endTitle: i18n.routeEnd || 'Route end',
                        updateCurrent: false,
                        focusZoom: null,
                    });
                }
                syncRouteLayers();
                this.setHistoryLoadBanner('route', 'done');
            } else {
                renderer.drawRouteChunksProgressive(chunks, endpoints, {
                    clickable: false,
                    mapPointCount: processed?.mapPointCount ?? simplified.length,
                    startTitle: i18n.routeStart || 'Route start',
                    endTitle: i18n.routeEnd || 'Route end',
                    onEndpointsPlaced: () => {
                        if (seq !== this._historyLoadSeq) return;
                        syncRouteLayers();
                    },
                    onComplete: () => {
                        if (seq !== this._historyLoadSeq) return;
                        syncRouteLayers();
                        this.setHistoryLoadBanner('route', 'done');
                    },
                });
                syncRouteLayers();
            }

            const endPoint = points[points.length - 1];
            const playbackPoint = {
                ...endPoint,
                name: vehicle?.name || endPoint.name,
                title: vehicle?.title || vehicle?.name || endPoint.title,
                plate: vehicle?.plate || endPoint.plate,
                vehicle_type: vehicle?.vehicle_type || endPoint.vehicle_type,
            };
            renderer.setCurrentVehicle(playbackPoint, { animate: false, skipAnimation: true });
            try {
                this.showHistoryPulse(endPoint, vehicle);
            } catch (pulseErr) {
                console.warn('[traccar-ui] history pulse', pulseErr);
            }

            this._playbackPoints = points;
            this._playbackIndex = 0;
            this.updatePlaybackMeta();
            this.updatePlaybackFab();

            if (!this._historyAutoFitDone) {
                let bounds = null;
                if (processed?.bounds) {
                    const b = processed.bounds;
                    bounds = new google.maps.LatLngBounds(
                        { lat: b.south, lng: b.west },
                        { lat: b.north, lng: b.east },
                    );
                }
                try {
                    if (bounds && points.length < 100) {
                        this.map.fitBounds(bounds, 60);
                    } else if (endPoint) {
                        this.map.panTo({ lat: endPoint.lat, lng: endPoint.lng });
                        if ((this.map.getZoom() || 11) < 14) this.map.setZoom(14);
                    }
                } catch (_) { /* ignore */ }
                this._historyAutoFitDone = true;
            }

            return endpoints;
        }

        syncLazyStatusMarkers() {
            if (!this.historyActive || !this._statusMarkers.length || !this.map) return;
            const bounds = this.map.getBounds();
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
            this._statusMarkers.forEach((seg, i) => {
                if (seg.lat >= minLat && seg.lat <= maxLat && seg.lng >= minLng && seg.lng <= maxLng) {
                    visible.add(i);
                }
            });

            this._lazyStatusMarkerByIndex.forEach((marker, i) => {
                if (!visible.has(i)) {
                    marker.setMap(null);
                    this._lazyStatusMarkerByIndex.delete(i);
                }
            });

            const name = this._historyName || '';
            visible.forEach((i) => {
                if (!this._lazyStatusMarkerByIndex.has(i)) {
                    const seg = this._statusMarkers[i];
                    if (!seg || !hasGeo(seg.lat, seg.lng)) return;
                    const icon = statusSegmentIcon(seg.letter || 'P', seg.color || '#2563eb');
                    const marker = createMapMarker({
                        position: { lat: seg.lat, lng: seg.lng },
                        map: this.map,
                        icon,
                        zIndex: seg.zIndex || 570,
                        title: `${seg.status_label || seg.typeKey || 'Stop'} · ${formatDuration(seg.durationSec)}`,
                    });
                    marker.addListener('click', () => this.openStatusSegmentInfo(seg, name, marker));
                    seg._marker = marker;
                    this._lazyStatusMarkerByIndex.set(i, marker);
                    this.historyLayers.push(marker);
                }
            });
        }

        bindLazyStatusMarkers() {
            if (!this.map || this._statusBoundsListener) return;
            this._statusBoundsListener = this.map.addListener('idle', () => this.syncLazyStatusMarkers());
        }

        renderStatusMarkersLazy(name) {
            this.unbindLazyStatusMarkers();
            this.clearLazyStatusMarkers();
            if (!this._statusMarkers.length) return;

            // Show markers immediately for typical day tracks; lazy-load only huge sets.
            if (this._statusMarkers.length <= 120) {
                this._statusMarkers.forEach((seg) => this.addStatusMarker(seg, name));
                return;
            }

            this.bindLazyStatusMarkers();
            this.syncLazyStatusMarkers();
        }

        renderHistoryEventsChunked(events, name, onComplete) {
            const list = Array.isArray(events) ? events : [];
            if (!list.length) {
                this.renderHistoryEventList([], name, null);
                if (typeof onComplete === 'function') onComplete();
                return;
            }

            if (list.length <= 60) {
                this.renderHistoryEventList(list, name, null);
                if (typeof onComplete === 'function') onComplete();
                return;
            }

            this.mountVirtualEventList(list, name);
            if (typeof onComplete === 'function') onComplete();
        }

        mountVirtualEventList(events, name) {
            const res = document.getElementById('tcHistResults');
            const wrap = document.getElementById('tcHistResultsWrap');
            if (!res) return;
            if (wrap) wrap.hidden = false;

            const rowHeight = 54;
            res.classList.add('tc-hist-virtual-host');
            res.innerHTML = '';

            const spacer = document.createElement('div');
            spacer.className = 'tc-hist-virtual__spacer';
            spacer.style.height = `${events.length * rowHeight}px`;

            const viewport = document.createElement('div');
            viewport.className = 'tc-hist-virtual__viewport';
            spacer.appendChild(viewport);
            res.appendChild(spacer);
            this._virtualEventScrollEl = res;

            const renderRow = (ev, idx) => {
                const type = ev.event_type || ev.type || 'event';
                const badge = historyEventBadge(type);
                const title = ev.title || ev.message || type;
                const meta = ev.message && ev.message !== title
                    ? ev.message
                    : (ev.duration_seconds ? formatDuration(ev.duration_seconds) : '');
                return `<div class="tc-row tc-stop-row" data-hist-ev="${idx}">
                    <span class="tc-evmark ${badge.cls}">${escHtml(badge.mark)}</span>
                    <span class="tc-row-info">
                        <span class="tc-row-title">${escHtml(title)}</span>
                        <span class="tc-row-meta">${escHtml(historyEventTime(ev))}${meta ? ` · ${escHtml(meta)}` : ''}</span>
                    </span></div>`;
            };

            const bindRows = (container) => {
                container.querySelectorAll('[data-hist-ev]').forEach((row) => {
                    row.addEventListener('click', () => {
                        const ev = events[parseInt(row.dataset.histEv, 10)];
                        this.onHistoryEventClick(ev, name);
                    });
                });
            };

            const paint = () => {
                const scrollTop = res.scrollTop;
                const viewHeight = res.clientHeight || 280;
                const start = Math.max(0, Math.floor(scrollTop / rowHeight) - 5);
                const end = Math.min(events.length, Math.ceil((scrollTop + viewHeight) / rowHeight) + 5);
                viewport.style.top = `${start * rowHeight}px`;
                viewport.innerHTML = events.slice(start, end).map((ev, i) => renderRow(ev, start + i)).join('');
                bindRows(viewport);
            };

            res.onscroll = paint;
            paint();
        }

        onHistoryEventClick(ev, name) {
            if (!ev || !hasGeo(ev.lat, ev.lng)) return;
            const lat = parseFloat(ev.lat);
            const lng = parseFloat(ev.lng);
            this.map.panTo({ lat, lng });
            if (this.map.getZoom() < 15) this.map.setZoom(16);
            const typeKey = statusMarkerTypeKey(ev.event_type || ev.type || ev.status_key);
            if (!typeKey) return;
            const matched = (this._statusMarkers || []).find((seg) => {
                if (!hasGeo(seg.lat, seg.lng)) return false;
                return Math.abs(seg.lat - lat) < 0.002 && Math.abs(seg.lng - lng) < 0.002;
            });
            const segment = matched || {
                typeKey,
                letter: STATUS_MARKER_STYLES[typeKey]?.letter || '•',
                color: STATUS_MARKER_STYLES[typeKey]?.color || '#64748b',
                lat,
                lng,
                status_key: ev.event_type || ev.type || typeKey,
                status_label: ev.title || ev.message || typeKey,
                durationSec: ev.duration_seconds || 0,
                arrived: ev.time || ev.recorded_at || ev.start,
                departed: ev.end,
                arrivedDisplay: historyEventTime(ev),
                departedDisplay: fmtTime(ev.end),
            };
            this.openStatusSegmentInfo(segment, name, matched?._marker);
        }

        applyHistoryAnalytics(vehicle, points, seq) {
            if (seq !== this._historyLoadSeq || !vehicle) return;

            const name = vehicle.name || vehicle.title || '';
            this._historyName = name;
            const historyEvents = vehicle.history_events || vehicle.events || [];
            const statusMarkers = computeHistoryStatusMarkers(vehicle, points);
            this._statusMarkers = statusMarkers;
            const stops = statusMarkers.filter((m) => m.typeKey === 'parked');
            const stats = normalizeHistoryStats(vehicle.stats, points, stops);

            this.setHistoryLoadBanner('timeline', 'loading');
            this.setHistoryLoadBanner('stats', 'loading');
            this.setHistoryLoadBanner('stops', 'loading');
            this.setHistoryLoadBanner('events', 'loading');

            this.renderHistoryEventList(historyEvents, name, stats);
            this.setHistoryLoadBanner('timeline', 'done');
            this.setHistoryLoadBanner('stats', 'done');

            this.renderSpeedLegend(name);
            this.renderHistoryFooter(vehicle, points, stops, stats);

            // Status markers first (what users look for on the map), then chart/events.
            this.renderStatusMarkersLazy(name);
            this.setHistoryLoadBanner('stops', 'done');

            this.scheduleIdleWork(() => {
                if (seq !== this._historyLoadSeq) return;
                this.renderGraph(points);
                this.renderHistoryEventsChunked(historyEvents, name, () => {
                    if (seq !== this._historyLoadSeq) return;
                    this.setHistoryLoadBanner('events', 'done');
                });
            });
        }

        async applyHistoryPoints(vehicle, seq) {
            const points = (vehicle?.points || [])
                .map((p) => this.normalizeHistoryPoint(p))
                .filter(Boolean);

            if (points.length < 2) {
                this.toast(this.cfg.i18n?.noData || 'No data for the selected period.', 'warning');
                this.exitHistory();
                return false;
            }

            this._historyVehicle = vehicle;
            this._historyPoints = points;
            this.prepareHistoryRoute();
            this.setHistoryLoadBanner('route', 'loading');
            await this.renderHistoryRouteProgressive(points, vehicle, seq);
            return seq === this._historyLoadSeq;
        }

        finishHistoryLoadBanners() {
            ['route', 'timeline', 'events', 'stops', 'stats'].forEach((k) => this.setHistoryLoadBanner(k, 'done'));
        }

        applyAnalyticsFallback(loadSeq) {
            if (loadSeq !== this._historyLoadSeq || !this._historyPoints.length) return;
            const stops = computeStops(this._historyPoints);
            const stats = normalizeHistoryStats(null, this._historyPoints, stops);
            this.applyHistoryAnalytics({ ...this._historyVehicle, stats }, this._historyPoints, loadSeq);
        }

        async loadHistory() {
            const id = parseInt(document.getElementById('tcHistVehicle')?.value, 10);
            if (!id) {
                this.toast(this.cfg.i18n?.selectVehicle || 'Select a vehicle.', 'warning');
                return;
            }

            const fromDate = document.getElementById('tcHistDateFrom')?.value || '';
            const toDate = document.getElementById('tcHistDateTo')?.value || '';
            const tFrom = document.getElementById('tcHistTimeFrom')?.value || '';
            const tTo = document.getElementById('tcHistTimeTo')?.value || '';
            let from = fromDate;
            let to = toDate;
            if (fromDate && tFrom) from = `${fromDate} ${tFrom}`;
            if (toDate && tTo) to = `${toDate} ${tTo}`;

            const params = new URLSearchParams();
            params.set('ids', String(id));
            if (from) params.set('from', from);
            if (to) params.set('to', to);

            const btn = document.getElementById('tcHistShow');
            const wrap = document.getElementById('tcHistResultsWrap');
            const loadSeq = (this._historyLoadSeq = (this._historyLoadSeq || 0) + 1);
            this._historyAbort?.abort();
            this.clearHistory();
            this._historyAbort = new AbortController();
            const signal = this._historyAbort.signal;
            this.historyActive = true;
            this.showLiveLayer(false);
            this.beginHistoryLoadBanner();
            if (wrap) wrap.hidden = false;
            btn?.setAttribute('disabled', 'disabled');

            const pointsBase = this.cfg.historyPointsJsonUrl || this.cfg.historyJsonUrl;
            const analyticsBase = this.cfg.historyAnalyticsJsonUrl || this.cfg.historyJsonUrl;
            const legacyUrl = this.buildHistoryRequestUrl(this.cfg.historyJsonUrl, params);
            const pointsUrl = this.appendCacheBust(this.buildHistoryRequestUrl(pointsBase, params));
            const analyticsUrl = this.appendCacheBust(this.buildHistoryRequestUrl(analyticsBase, params));
            const canParallel = Boolean(
                this.cfg.historyPointsJsonUrl
                && this.cfg.historyAnalyticsJsonUrl
                && pointsBase !== analyticsBase,
            );

            const applyPointsVehicle = async (vehicle) => {
                if (loadSeq !== this._historyLoadSeq) return false;
                if (!vehicle) {
                    this.toast(this.cfg.i18n?.noData || 'No data for the selected period.', 'warning');
                    this.exitHistory();
                    this.finishHistoryLoadBanners();
                    return false;
                }
                return this.applyHistoryPoints(vehicle, loadSeq);
            };

            const applyAnalyticsVehicle = (vehicle) => {
                if (loadSeq !== this._historyLoadSeq || !this._historyPoints.length) return;
                if (vehicle) {
                    this.applyHistoryAnalytics(
                        { ...this._historyVehicle, ...vehicle },
                        this._historyPoints,
                        loadSeq,
                    );
                } else {
                    this.applyAnalyticsFallback(loadSeq);
                }
            };

            try {
                if (canParallel) {
                    let skipParallelAnalytics = false;

                    const pointsTask = this.fetchHistoryJson(pointsUrl, signal)
                        .then(async (result) => {
                            const vehicle = (result.json.vehicles || [])[0] || null;
                            return applyPointsVehicle(vehicle);
                        })
                        .catch(async (err) => {
                            if (loadSeq !== this._historyLoadSeq || err?.name === 'AbortError') return false;
                            if (this.isHistoryPermissionError(err)) {
                                throw err;
                            }
                            console.warn('[traccar-ui] history points', err);
                            try {
                                skipParallelAnalytics = true;
                                const legacy = await this.fetchHistoryJson(this.appendCacheBust(legacyUrl), signal);
                                if (loadSeq !== this._historyLoadSeq) return false;
                                const vehicle = (legacy.json.vehicles || [])[0] || null;
                                const applied = await applyPointsVehicle(vehicle);
                                if (applied && vehicle) {
                                    applyAnalyticsVehicle(vehicle);
                                }
                                return applied;
                            } catch (legacyErr) {
                                if (loadSeq !== this._historyLoadSeq || legacyErr?.name === 'AbortError') return false;
                                throw legacyErr;
                            }
                        });

                    const analyticsTask = this.fetchHistoryJson(analyticsUrl, signal)
                        .then((result) => {
                            if (skipParallelAnalytics || loadSeq !== this._historyLoadSeq) return false;
                            const vehicle = (result.json.vehicles || [])[0] || null;
                            applyAnalyticsVehicle(vehicle);
                            return true;
                        })
                        .catch((err) => {
                            if (loadSeq !== this._historyLoadSeq || err?.name === 'AbortError') return false;
                            if (this.isHistoryPermissionError(err)) {
                                throw err;
                            }
                            console.warn('[traccar-ui] history analytics', err);
                            if (this._historyPoints.length) {
                                this.applyAnalyticsFallback(loadSeq);
                            } else {
                                ['timeline', 'events', 'stops', 'stats'].forEach((k) => this.setHistoryLoadBanner(k, 'done'));
                            }
                            return false;
                        });

                    const pointsOutcome = await pointsTask;
                    if (loadSeq !== this._historyLoadSeq) return;

                    if (pointsOutcome === false && this.historyActive) {
                        this.toast(this.cfg.i18n?.loadFailed || 'Failed to load history.', 'error');
                        this.finishHistoryLoadBanners();
                        return;
                    }

                    if (!skipParallelAnalytics) {
                        await analyticsTask;
                    }
                } else {
                    const result = await this.fetchHistoryJson(this.appendCacheBust(legacyUrl), signal);
                    if (loadSeq !== this._historyLoadSeq) return;
                    const vehicle = (result.json.vehicles || [])[0] || null;
                    const pointsApplied = await applyPointsVehicle(vehicle);
                    if (pointsApplied) {
                        this.scheduleIdleWork(() => applyAnalyticsVehicle(vehicle));
                    }
                }
            } catch (err) {
                if (loadSeq !== this._historyLoadSeq || err?.name === 'AbortError') return;
                console.error('[traccar-ui] history', err);
                this.finishHistoryLoadBanners();
                this.exitHistory();
                if (this.isHistoryPermissionError(err)) {
                    this.popupError(
                        err.message
                            || this.cfg.i18n?.historyPermissionDenied
                            || 'Access Denied. You do not have permission to view tracking history.',
                        this.cfg.i18n?.accessDeniedTitle || 'Access Denied',
                    );
                    return;
                }
                if (!(this.historyActive && this.historyLayers.length > 0)) {
                    this.toast(
                        `${this.cfg.i18n?.loadFailed || 'Failed to load history.'} ${err.message || ''}`.trim(),
                        'error',
                    );
                }
            } finally {
                if (loadSeq === this._historyLoadSeq) btn?.removeAttribute('disabled');
            }
        }

        drawHistory(vehicle) {
            const loadSeq = (this._historyLoadSeq = (this._historyLoadSeq || 0) + 1);
            this.clearHistory();
            this.historyActive = true;
            this.showLiveLayer(false);
            this.beginHistoryLoadBanner();
            this.applyHistoryPoints(vehicle, loadSeq).then((ok) => {
                if (ok) {
                    this.applyHistoryAnalytics(vehicle, this._historyPoints, loadSeq);
                }
            });
        }

        addStatusMarker(segment, name) {
            if (!segment || !hasGeo(segment.lat, segment.lng)) return;
            const icon = statusSegmentIcon(segment.letter || 'P', segment.color || '#2563eb');
            const marker = createMapMarker({
                position: { lat: segment.lat, lng: segment.lng },
                map: this.map,
                icon,
                zIndex: segment.zIndex || 570,
                title: `${segment.status_label || segment.typeKey || 'Stop'} · ${formatDuration(segment.durationSec)}`,
            });
            marker.addListener('click', () => this.openStatusSegmentInfo(segment, name, marker));
            segment._marker = marker;
            this.historyLayers.push(marker);
        }

        addStop(stop, name) {
            this.addStatusMarker({
                typeKey: 'parked',
                letter: 'P',
                color: STATUS_MARKER_STYLES.parked.color,
                zIndex: STATUS_MARKER_STYLES.parked.zIndex,
                lat: stop.lat,
                lng: stop.lng,
                status_key: 'parked',
                status_label: 'Parking',
                durationSec: stop.durationSec,
                arrived: stop.arrived?.recorded_at,
                departed: stop.departed?.recorded_at,
                arrivedDisplay: fmtTime(stop.arrived?.recorded_at),
                departedDisplay: fmtTime(stop.departed?.recorded_at),
                heading: stop.heading,
                altitude: stop.altitude,
            }, name);
        }

        buildStatusSegmentInfoHtml(segment, name, addressText) {
            const i18n = this.cfg.i18n || {};
            const rows = [
                [i18n.lblStatus || 'Status', escHtml(segment.status_label || segment.status_key || '—')],
                [i18n.lblObject || 'Object', escHtml(name || '—')],
                [i18n.lblAddress || 'Address', `<span data-tc-seg-address>${escHtml(addressText || i18n.addressLoading || 'Loading address…')}</span>`],
                [i18n.lblPosition || 'Position', `${Number(segment.lat).toFixed(6)}, ${Number(segment.lng).toFixed(6)}`],
                [i18n.lblArrived || 'Arrived', escHtml(segment.arrivedDisplay || fmtTime(segment.arrived))],
                [i18n.lblDeparted || 'Departed', escHtml(segment.departedDisplay || fmtTime(segment.departed))],
                [i18n.lblDuration || 'Duration', escHtml(formatDuration(segment.durationSec))],
            ];
            if (segment.speed_kmh != null) {
                rows.push([i18n.lblSpeed || 'Speed', `${Number(segment.speed_kmh).toFixed(0)} ${escHtml(i18n.kmhUnit || 'km/h')}`]);
            }
            if (segment.heading != null) {
                rows.push([i18n.lblAngle || 'Angle', `${Math.round(segment.heading)}\u00b0`]);
            }
            if (segment.ignition != null) {
                rows.push([
                    i18n.lblIgnition || 'Ignition',
                    segment.ignition ? (i18n.ignitionOn || 'ON') : (i18n.ignitionOff || 'OFF'),
                ]);
            }
            if (segment.altitude != null) {
                rows.splice(4, 0, [i18n.lblAltitude || 'Altitude', `${Math.round(segment.altitude)} m`]);
            }
            return `<div class="tc-info tc-info--segment">${rows.map((r) => `<div class="tc-info-row"><span>${r[0]}</span><b>${r[1]}</b></div>`).join('')}</div>`;
        }

        resolveSegmentAddress(lat, lng) {
            const key = `${Number(lat).toFixed(5)},${Number(lng).toFixed(5)}`;
            if (this._addressCache.has(key)) {
                return Promise.resolve(this._addressCache.get(key));
            }
            const g = global.google;
            if (!g?.maps?.Geocoder) {
                return Promise.resolve(null);
            }
            const geocoder = new g.maps.Geocoder();
            return new Promise((resolve) => {
                geocoder.geocode({ location: { lat, lng } }, (results, status) => {
                    const label = status === 'OK' && results?.[0]?.formatted_address
                        ? results[0].formatted_address
                        : null;
                    this._addressCache.set(key, label);
                    resolve(label);
                });
            });
        }

        openStatusSegmentInfo(segment, name, marker) {
            if (!this.stopInfo) this.stopInfo = new google.maps.InfoWindow();
            const i18n = this.cfg.i18n || {};
            const render = (addressText) => {
                this.stopInfo.setContent(this.buildStatusSegmentInfoHtml(segment, name, addressText));
            };
            render(i18n.addressLoading || 'Loading address…');
            this.stopInfo.open(this.map, marker || segment._marker);
            this.resolveSegmentAddress(segment.lat, segment.lng).then((address) => {
                render(address || i18n.addressUnavailable || 'Address unavailable');
            });
        }

        openStopInfo(stop, name, marker) {
            this.openStatusSegmentInfo({
                typeKey: 'parked',
                letter: 'P',
                color: STATUS_MARKER_STYLES.parked.color,
                lat: stop.lat,
                lng: stop.lng,
                status_key: 'parked',
                status_label: 'Parking',
                durationSec: stop.durationSec,
                arrived: stop.arrived?.recorded_at,
                departed: stop.departed?.recorded_at,
                arrivedDisplay: fmtTime(stop.arrived?.recorded_at),
                departedDisplay: fmtTime(stop.departed?.recorded_at),
                heading: stop.heading,
                altitude: stop.altitude,
                _marker: stop._marker || marker,
            }, name, marker || stop._marker);
        }

        renderHistoryEventList(events, name, stats) {
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
            res.classList.remove('tc-hist-virtual-host');
            this._virtualEventScrollEl = null;
            const list = Array.isArray(events) ? events : [];
            if (!list.length) {
                res.innerHTML = `<div class="tc-empty">${escHtml(i.noEvents || 'No events')}</div>`;
                return;
            }
            res.innerHTML = list.map((ev, idx) => {
                const type = ev.event_type || ev.type || 'event';
                const badge = historyEventBadge(type);
                const title = ev.title || ev.message || type;
                const meta = ev.message && ev.message !== title
                    ? ev.message
                    : (ev.duration_seconds ? formatDuration(ev.duration_seconds) : '');
                return `<div class="tc-row tc-stop-row" data-hist-ev="${idx}">
                <span class="tc-evmark ${badge.cls}">${escHtml(badge.mark)}</span>
                <span class="tc-row-info">
                    <span class="tc-row-title">${escHtml(title)}</span>
                    <span class="tc-row-meta">${escHtml(historyEventTime(ev))}${meta ? ` · ${escHtml(meta)}` : ''}</span>
                </span></div>`;
            }).join('');
            res.querySelectorAll('[data-hist-ev]').forEach((row) => {
                row.addEventListener('click', () => {
                    const ev = list[parseInt(row.dataset.histEv, 10)];
                    this.onHistoryEventClick(ev, name);
                });
            });
        }

        renderStopList(stops, name, stats) {
            this.renderHistoryEventList([], name, stats);
        }

        renderHistoryFooter(vehicle, points, stops, historyStats = null) {
            const dataEl = document.getElementById('tcFooterData');
            const msgEl = document.getElementById('tcFooterMessages');
            if (!dataEl) return;

            const i = this.cfg.i18n || {};
            const kmh = i.kmhUnit || 'km/h';
            const stats = historyStats || normalizeHistoryStats(vehicle?.stats, points, stops);
            const events = vehicle?.history_events || vehicle?.events || [];

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
                ${statCard(i.historyMarkers || 'Status markers', String((this._statusMarkers || []).length))}
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

        bindFooterResize() {
            const footer = document.getElementById('tcFooter');
            const handle = document.getElementById('tcFooterResize');
            if (!footer || !handle) return;

            const minH = 120;
            const maxRatio = 0.72;

            const applyHeight = (px) => {
                const maxH = Math.max(minH, Math.floor(window.innerHeight * maxRatio));
                const h = Math.min(Math.max(px, minH), maxH);
                footer.style.setProperty('--tc-footer-height', `${h}px`);
                this.resizeMapSoon();
                return h;
            };

            try {
                const stored = parseInt(localStorage.getItem('tcFooterHeight'), 10);
                if (Number.isFinite(stored) && stored >= minH) {
                    applyHeight(stored);
                }
            } catch (_) { /* ignore */ }

            let startY = null;
            let startH = 0;

            handle.addEventListener('pointerdown', (e) => {
                startY = e.clientY;
                startH = footer.getBoundingClientRect().height;
                handle.setPointerCapture?.(e.pointerId);
                e.preventDefault();
            });

            handle.addEventListener('pointermove', (e) => {
                if (startY == null) return;
                applyHeight(startH + (startY - e.clientY));
            });

            const finishDrag = () => {
                if (startY == null) return;
                const h = footer.getBoundingClientRect().height;
                try {
                    localStorage.setItem('tcFooterHeight', String(Math.round(h)));
                } catch (_) { /* ignore */ }
                startY = null;
                this.resizeMapSoon();
            };

            handle.addEventListener('pointerup', finishDrag);
            handle.addEventListener('pointercancel', finishDrag);
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

        panelFromVehicle(v) {
            if (!v) return null;
            const odoKm = v.odometer_km != null
                ? v.odometer_km
                : (v.odometer != null ? Math.round((parseFloat(v.odometer) || 0) / 1000) : null);

            return {
                id: Number(v.id),
                name: v.title || v.name || String(v.id),
                plate: v.plate || '',
                status: v.status_label || v.status || v.status_key,
                status_key: v.status_key,
                connectivity_tier: v.connectivity_tier || null,
                last_known_status: v.last_known_status || null,
                color: colorForPoint(v, this.stateColors),
                speed: v.speed != null ? Math.round(parseFloat(v.speed) || 0) : null,
                angle: v.heading != null ? Math.round(parseFloat(v.heading) || 0) : (v.angle != null ? Math.round(parseFloat(v.angle) || 0) : null),
                altitude: v.altitude != null ? Math.round(parseFloat(v.altitude) || 0) : null,
                odometer: odoKm,
                lat: v.lat != null ? parseFloat(v.lat) : null,
                lng: v.lng != null ? parseFloat(v.lng) : null,
                ignition: v.ignition,
                time_position: v.recorded_at_human || v.last_update || null,
                time_server: null,
                status_since: v.status_since || null,
                status_duration_seconds: v.status_duration_seconds ?? null,
                icon: v.icon,
                driver: v.driver,
                tasks: [],
                mileage: null,
                fuel: v.fuel ?? null,
                battery: v.battery ?? v.battery_level ?? null,
                notes: null,
                photo: null,
                speed_max: 160,
                stats: null,
                events: null,
                positions: null,
            };
        }

        async openDevicePanel(id) {
            if (!this.cfg.devicePanelUrl || !document.getElementById('tcFooter')) return;
            const numId = Number(id);
            const v = this.vehicles.get(numId);
            this._footerMode = 'panel';
            this._panelDeviceId = numId;
            this._panelFetchGen = (this._panelFetchGen || 0) + 1;
            const fetchGen = this._panelFetchGen;
            this._panelAbort?.abort();
            this._panelAbort = new AbortController();
            const signal = this._panelAbort.signal;

            this.activateRouteTripForVehicle(numId);
            this.showFooter(this.labelFor(v) || ('#' + numId));
            this.switchFooterTab('data');

            const dataEl = document.getElementById('tcFooterData');
            const msgEl = document.getElementById('tcFooterMessages');
            const instant = this.panelFromVehicle(v);
            if (dataEl) {
                dataEl.classList.remove('tc-fbody--hist');
                if (instant) {
                    this._panel = { ...instant, id: numId };
                    this.renderPanelData(this._panel);
                    this._startPanelDurationTick();
                } else {
                    this._panel = { id: numId };
                    dataEl.innerHTML = this.panelSkeleton();
                }
            }
            if (msgEl) msgEl.innerHTML = '';

            const panelUrl = this.cfg.devicePanelUrl;
            const fetchOpts = {
                credentials: 'same-origin',
                cache: 'no-store',
                signal,
                headers: { Accept: 'application/json' },
            };

            const mergePanel = (partial) => {
                if (!partial || this._panelFetchGen !== fetchGen || Number(this._panelDeviceId) !== numId) return;
                if (partial.id != null && Number(partial.id) !== numId) return;

                const base = (this._panel && Number(this._panel.id) === numId)
                    ? this._panel
                    : (this.panelFromVehicle(this.vehicles.get(numId)) || { id: numId });
                this._panel = { ...base, ...partial, id: numId };
                if (partial.positions) {
                    this._panelGraphRows = (partial.positions || []).map((p) => ({
                        label: String(p.time || '').slice(11, 16),
                        speed: Math.round(p.speed || 0),
                    }));
                }

                const hasGrid = !!document.querySelector('#tcFooterData .tc-data-grid');
                const isCore = partial.id != null
                    || partial.name != null
                    || partial.lat != null
                    || partial.status != null;
                if (!hasGrid || isCore) {
                    this.renderPanelData(this._panel);
                } else {
                    if (partial.stats != null) this.updatePanelStats(partial.stats);
                    if (partial.events != null) this.updatePanelEvents(partial.events);
                    if (partial.positions?.length) this.renderPanelMessages(partial.positions);
                }

                this._startPanelDurationTick();
                if (partial.lat != null && partial.lng != null) {
                    this.focusVehicleOnMap(numId, {
                        pan: false,
                        merge: {
                            lat: partial.lat,
                            lng: partial.lng,
                            speed: partial.speed,
                            heading: partial.angle,
                            status_label: partial.status,
                            status_key: partial.status_key,
                            color: partial.color,
                        },
                    });
                }
                if (partial.driver) {
                    const cur = this.vehicles.get(numId);
                    if (cur) this.vehicles.set(numId, { ...cur, driver: partial.driver });
                    this.syncDriverMapCard();
                }
            };

            const fetchSection = async (sections) => {
                const res = await fetch(
                    `${panelUrl}?device_id=${encodeURIComponent(numId)}&sections=${encodeURIComponent(sections)}&_=${Date.now()}`,
                    fetchOpts,
                );
                const data = await res.json().catch(() => ({}));
                if (!res.ok || !data.success || !data.panel) {
                    throw new Error(data.message || 'failed');
                }
                if (data.panel.id != null && Number(data.panel.id) !== numId) {
                    throw new Error('panel device mismatch');
                }
                return data.panel;
            };

            try {
                const core = await fetchSection('core');
                mergePanel(core);

                const secondary = await Promise.allSettled([
                    fetchSection('stats'),
                    fetchSection('events'),
                    fetchSection('graph'),
                ]);

                secondary.forEach((result) => {
                    if (result.status === 'fulfilled') {
                        mergePanel(result.value);
                    }
                });
            } catch (err) {
                if (err?.name === 'AbortError') return;
                if (this._panelFetchGen !== fetchGen || Number(this._panelDeviceId) !== numId) return;
                if (dataEl && Number(this._panel?.id) !== numId) {
                    dataEl.innerHTML = `<div class="tc-empty">${escHtml(this.cfg.i18n?.panelLoadFailed || this.cfg.i18n?.loadFailed || 'Failed')}</div>`;
                }
            }
        }

        _startPanelDurationTick() {
            this._stopPanelDurationTick();
            const since = this._panel?.status_since
                || this.vehicles.get(this._panelDeviceId)?.status_since;
            if (!since) return;

            const tick = () => {
                const el = document.getElementById('tcPanelStatusDuration');
                if (!el || this._footerMode !== 'panel') {
                    this._stopPanelDurationTick();
                    return;
                }
                const iso = this._panel?.status_since
                    || this.vehicles.get(this._panelDeviceId)?.status_since;
                if (!iso) return;
                const sec = Math.max(0, Math.floor((Date.now() - new Date(iso).getTime()) / 1000));
                const i = this.cfg.i18n || {};
                el.textContent = `${i.lblStatusDuration || 'for'} ${formatDuration(sec)}`;
                el.style.display = '';
            };

            tick();
            this._panelDurationTick = setInterval(tick, 1000);
        }

        _stopPanelDurationTick() {
            if (this._panelDurationTick) {
                clearInterval(this._panelDurationTick);
                this._panelDurationTick = null;
            }
        }

        initMapPanelPositions() {
            if (!global.MapPanelPosition || this._mapPanelPositionsReady) return;
            const bounds = document.getElementById('mapArea') || document.querySelector('.tc-map-area');
            if (!bounds) return;

            const prefix = String(this.cfg.panelStoragePrefix || 'tracking');
            const mount = (panelSelector, key) => {
                if (!document.querySelector(panelSelector)) return;
                global.MapPanelPosition.mount({
                    panel: panelSelector,
                    bounds,
                    storageKey: `mapPanelPos.${prefix}.${key}`,
                });
            };

            mount('#routeTripProgressBar', 'routeProgress');
            this._mapPanelPositionsReady = true;

            if (!this._mapPanelResizeBound) {
                this._mapPanelResizeBound = true;
                window.addEventListener('resize', () => global.MapPanelPosition?.reclampAll?.());
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
                shouldFitRouteBounds: () => false,
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
                    dragPanel: i.dragPanel || 'Drag to reposition',
                    resetPanelPosition: i.resetPanelPosition || 'Double-click to reset position',
                },
                onMilestoneReached: (_milestone, message) => this.toast(message, 'success'),
                onComplete: () => this.completeAssignedTrip(),
                onStartNew: () => this.startNewAssignedTrip(),
                onRestart: () => this.restartAssignedTrip(),
            });
            return this.routeTripKit;
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
         * Route polylines are drawn only for the user-selected vehicle (via RouteTripProgress).
         */
        syncVisibleVehicleRoutePolylines() {
            this._vehicleRoutePolylines.forEach((line) => line.setMap(null));
            this._vehicleRoutePolylines.clear();
        }

        /** Show route progress only for the vehicle the user explicitly selected. */
        selectRouteTripVehicle(id) {
            if (!this.vehicleHasAssignedRoute(id)) {
                this.clearRouteTripSelection();
                return;
            }
            if (this.uiOn('polyline') || this.uiOn('route_progress')) {
                if (Number(this._routeTripDeviceId) !== Number(id)) {
                    this.clearSelectedRouteOverlays();
                }
                this._routeTripDeviceId = id;
                const cached = this.vehicles.get(id)?.route_trip;
                if (cached?.route) {
                    this.applyRouteTrip(id, this.sanitizeRouteTrip(cached), {
                        forceFitBounds: false,
                        skipVehicleZoom: true,
                    });
                } else {
                    this.clearSelectedRouteOverlays();
                    this.routeTripKit?.clear();
                    this.loadRouteTripForDevice(id);
                }
                this.syncVisibleVehicleRoutePolylines();
            }
            this.syncCompanyMapCard();
            this.syncDriverMapCard();
        }

        async loadRouteTripForDevice(id) {
            const url = this.cfg.devicePanelUrl;
            if (!url || Number(this._routeTripDeviceId) !== Number(id)) return;
            const cached = this.vehicles.get(id)?.route_trip;
            if (cached?.route) return;
            try {
                const res = await fetch(`${url}?device_id=${encodeURIComponent(id)}&sections=route_trip&_=${Date.now()}`, {
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
                    this.applyRouteTrip(id, sanitized, { forceFitBounds: false, skipVehicleZoom: true });
                } else if (Number(this._routeTripDeviceId) === Number(id)) {
                    this.clearRouteTripSelection();
                }
            } catch (_) { /* ignore */ }
        }

        clearRouteTripSelection() {
            this._routeTripDeviceId = null;
            this._routeBoundsFitted = true;
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
            if (!el || !panel) return;
            try {
                this._renderPanelDataInner(panel, el);
            } catch (err) {
                console.error('[traccar-ui] panel render failed', err);
                el.innerHTML = `<div class="tc-empty">${escHtml(this.cfg.i18n?.panelLoadFailed || 'Failed to display panel data.')}</div>`;
            }
        }

        _renderPanelDataInner(panel, el) {
            const i = this.cfg.i18n || {};
            const kmh = i.kmhUnit || 'km/h';
            const dash = '—';
            const kv = (k, v, icon, id) => `<div class="tc-kv"><span class="tc-kv-k">${icon ? `<i class="fas ${icon}"></i>` : ''}${escHtml(k)}</span><span class="tc-kv-v"${id ? ` id="${id}"` : ''}>${v}</span></div>`;
            const pos = (panel.lat != null && panel.lng != null)
                ? `<a href="#" data-tc-locate="${panel.lat},${panel.lng}">${Number(panel.lat).toFixed(6)}, ${Number(panel.lng).toFixed(6)}</a>`
                : dash;
            const statusLabelHtml = this.panelStatusHtml(panel, i);
            const statusDuration = (panel.status_since || panel.status_duration_seconds != null)
                ? ` <span id="tcPanelStatusDuration" class="tc-muted">${panel.status_duration_seconds != null && !panel.status_since
                    ? `${escHtml(i.lblStatusDuration || 'for')} ${formatDuration(panel.status_duration_seconds)}`
                    : ''}</span>`
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
            const statsLoading = panel.stats == null;
            const stats = statsLoading
                ? `<div class="tc-empty tc-stats-loading">${escHtml(i.loadingStats || 'Loading today\'s statistics…')}</div>`
                : [
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
                <div class="tc-data-col" data-panel-col="identity">${colA}</div>
                <div class="tc-data-col" data-panel-col="position">${colB}</div>
                <div class="tc-data-col" data-panel-col="control"><h6>${escHtml(i.secObjectControl || 'Object control')}</h6>${control}</div>
                <div class="tc-data-col" data-panel-col="stats"><h6>${escHtml(i.secDailyStats || 'Daily statistics')}</h6>${stats}</div>
                <div class="tc-data-col" data-panel-col="events"><h6>${escHtml(i.secRecentEvents || 'Recent events')}</h6>${eventsHtml}</div>
                <div class="tc-data-col" data-panel-col="tasks"><h6>${escHtml(i.secRecentTasks || 'Recent tasks')}</h6>${tasksHtml}</div>
                <div class="tc-data-col" data-panel-col="fuel"><h6>${escHtml(i.secFuel || 'Fuel')}</h6>${fuelHtml}</div>
                <div class="tc-data-col" data-panel-col="mileage"><h6>${escHtml(i.secMileage || 'Mileage (km)')}</h6>${mileageHtml}</div>
                <div class="tc-data-col" data-panel-col="speedo"><h6>${escHtml(i.secSpeedometer || 'Speedometer')}</h6>${speedoHtml}</div>
                <div class="tc-data-col" data-panel-col="notes"><h6>${escHtml(i.secNotes || 'Notes')}</h6>${notesHtml}</div>
                <div class="tc-data-col" data-panel-col="photo"><h6>${escHtml(i.secPhoto || 'Photo')}</h6>${photoHtml}</div>
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
            el.querySelector('#tcCmdSend')?.addEventListener('click', () => {
                if (Number(this._panelDeviceId) === Number(panel.id)) {
                    this.sendCommand(this._panelDeviceId);
                }
            });

            if (panel.mileage == null && this.cfg.deviceMileageUrl) {
                this.loadPanelMileage(panel.id);
            }
        }

        updatePanelStats(s) {
            const host = document.querySelector('#tcFooterData [data-panel-col="stats"]');
            if (!host) return;
            const i = this.cfg.i18n || {};
            const kmh = i.kmhUnit || 'km/h';
            const kv = (k, v) => `<div class="tc-kv"><span class="tc-kv-k">${escHtml(k)}</span><span class="tc-kv-v">${v}</span></div>`;
            host.innerHTML = `<h6>${escHtml(i.secDailyStats || 'Daily statistics')}</h6>${[
                kv(i.statRouteLength || 'Route length', `${s.distance_km != null ? s.distance_km : 0} km`),
                kv(i.statMoveDuration || 'Move duration', formatDuration(s.move_seconds || 0)),
                kv(i.statStopDuration || 'Stop duration', formatDuration(s.stop_seconds || 0)),
                kv(i.statTopSpeed || 'Top speed', `${s.top_speed != null ? s.top_speed : 0} ${kmh}`),
                kv(i.statAvgSpeed || 'Average speed', `${s.avg_speed != null ? s.avg_speed : 0} ${kmh}`),
            ].join('')}`;
        }

        updatePanelEvents(events) {
            const host = document.querySelector('#tcFooterData [data-panel-col="events"]');
            if (!host) return;
            const i = this.cfg.i18n || {};
            const eventsHtml = (events || []).length
                ? `<ul class="tc-mini-events">${events.map((ev) => {
                    const loc = hasGeo(ev.lat, ev.lng) ? `data-tc-locate="${ev.lat},${ev.lng}"` : '';
                    return `<li ${loc}><span>${escHtml(ev.title || ev.message || ev.type || ev.event_type || 'Event')}</span><span class="tc-me-time">${escHtml(String(ev.time || '').slice(0, 19))}</span></li>`;
                }).join('')}</ul>`
                : `<div class="tc-empty">${escHtml(i.noEvents || 'No events')}</div>`;
            host.innerHTML = `<h6>${escHtml(i.secRecentEvents || 'Recent events')}</h6>${eventsHtml}`;
            host.querySelectorAll('[data-tc-locate]').forEach((node) => {
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
            if (this._panel && Number(this._panel.id) !== Number(v.id)) return;

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
            if (v.status_since) {
                if (this._panel) this._panel.status_since = v.status_since;
                this._startPanelDurationTick();
            } else if (statusDurEl) {
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
                    this.closeNav();
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
            const marker = createMapMarker({
                position: { lat: point.lat, lng: point.lng }, map: this.map,
                icon: { url, scaledSize: new google.maps.Size(40, 40), anchor: new google.maps.Point(20, 20) },
                zIndex: type === 'start' ? 600 : 601,
                title: type === 'start' ? (this.cfg.i18n?.routeStart || 'Start') : (this.cfg.i18n?.routeEnd || 'End'),
            });
            this.historyLayers.push(marker);
        }

        bindPlayback() {
            document.querySelectorAll('[data-tc-playback]').forEach((el) => {
                el.addEventListener('click', (e) => {
                    e.stopPropagation();
                    if (!this._playbackPoints.length) {
                        this.toast(this.mi('loadHistoryFirst', 'Load route history first'), 'info');
                        return;
                    }
                    this.setPlaybackPanelOpen(true);
                    if (!this._isPlaying) this.startPlayback();
                });
            });
            document.getElementById('tcPlaybackClose')?.addEventListener('click', () => {
                if (this._isPlaying) this.pausePlayback();
                this.setPlaybackPanelOpen(false);
            });
            document.getElementById('tcPbPlayPause')?.addEventListener('click', () => {
                if (this._isPlaying) this.pausePlayback();
                else this.startPlayback();
            });
            document.getElementById('tcPbStop')?.addEventListener('click', () => this.stopPlayback());
            document.getElementById('tcPbRewind')?.addEventListener('click', () => {
                if (!this._playbackPoints.length) return;
                this.pausePlayback();
                this._playbackIndex = 0;
                this.updatePlaybackAtIndex(0);
            });
            document.getElementById('tcPbStepBack')?.addEventListener('click', () => {
                if (!this._playbackPoints.length) return;
                this.pausePlayback();
                this._playbackIndex = Math.max(0, this._playbackIndex - 1);
                this.updatePlaybackAtIndex(this._playbackIndex);
            });
            document.getElementById('tcPbStepForward')?.addEventListener('click', () => {
                if (!this._playbackPoints.length) return;
                this.pausePlayback();
                this._playbackIndex = Math.min(this._playbackPoints.length - 1, this._playbackIndex + 1);
                this.updatePlaybackAtIndex(this._playbackIndex);
            });
            document.getElementById('tcPlaybackProgress')?.addEventListener('click', (e) => this.scrubPlayback(e));
            document.querySelectorAll('[data-tc-speed]').forEach((btn) => {
                btn.addEventListener('click', () => {
                    this._playbackSpeed = parseFloat(btn.dataset.tcSpeed || '1') || 1;
                    document.querySelectorAll('[data-tc-speed]').forEach((b) => b.classList.toggle('active', b === btn));
                });
            });
        }

        setPlaybackPanelOpen(open) {
            const panel = document.getElementById('tcPlaybackPanel');
            const mapArea = document.querySelector('.tc-map-area');
            if (!panel) return;
            panel.classList.toggle('active', open);
            mapArea?.classList.toggle('tc-playback-open', open);
        }

        setPlayPauseUi(playing) {
            const icon = document.getElementById('tcPbPlayPauseIcon');
            if (icon) {
                icon.classList.toggle('fa-play', !playing);
                icon.classList.toggle('fa-pause', playing);
            }
        }

        updatePlaybackFab() {
            const hasRoute = this._playbackPoints.length > 0;
            document.querySelectorAll('[data-tc-playback]').forEach((el) => {
                el.disabled = !hasRoute;
                el.classList.toggle('disabled', !hasRoute);
            });
        }

        playbackTotalMs() {
            if (this._playbackPoints.length < 2) return 0;
            const start = new Date(this._playbackPoints[0].recorded_at).getTime();
            const end = new Date(this._playbackPoints[this._playbackPoints.length - 1].recorded_at).getTime();
            return Number.isNaN(start) || Number.isNaN(end) || end <= start ? 0 : end - start;
        }

        updatePlaybackMeta() {
            const total = this._playbackPoints.length;
            const subtitle = document.getElementById('tcPlaybackSubtitle');
            if (subtitle) {
                subtitle.textContent = total
                    ? `${this.mi('playRoute', 'Play Route')} · ${total} pts`
                    : (this.mi('loadHistoryPlayback', 'Load history to start'));
            }
            const totalEl = document.getElementById('tcPbPointTotal');
            const indexEl = document.getElementById('tcPbPointIndex');
            if (totalEl) totalEl.textContent = String(total);
            if (indexEl) indexEl.textContent = String(total ? this._playbackIndex + 1 : 0);
            this.updatePlaybackProgress();
        }

        updatePlaybackProgress() {
            const bar = document.getElementById('tcPlaybackProgressBar');
            const thumb = document.getElementById('tcPlaybackProgressThumb');
            const cur = document.getElementById('tcPlaybackTimeCurrent');
            const tot = document.getElementById('tcPlaybackTimeTotal');
            const total = this._playbackPoints.length;
            const pct = total > 1 ? (this._playbackIndex / (total - 1)) * 100 : 0;
            if (bar) bar.style.width = `${pct}%`;
            if (thumb) thumb.style.left = `${pct}%`;

            const fmtClock = (ms) => {
                const s = Math.floor(ms / 1000);
                const m = Math.floor(s / 60);
                const r = s % 60;
                return `${String(m).padStart(2, '0')}:${String(r).padStart(2, '0')}`;
            };
            const totalMs = this.playbackTotalMs();
            let curMs = 0;
            if (totalMs > 0 && total > 1) {
                const t0 = new Date(this._playbackPoints[0].recorded_at).getTime();
                const ti = new Date(this._playbackPoints[this._playbackIndex].recorded_at).getTime();
                curMs = Math.max(0, ti - t0);
            }
            if (cur) cur.textContent = fmtClock(curMs);
            if (tot) tot.textContent = fmtClock(totalMs);
        }

        scrubPlayback(e) {
            if (!this._playbackPoints.length) return;
            const track = document.getElementById('tcPlaybackProgress');
            if (!track) return;
            const rect = track.getBoundingClientRect();
            const percent = Math.min(1, Math.max(0, (e.clientX - rect.left) / rect.width));
            this.pausePlayback();
            this._playbackIndex = Math.min(this._playbackPoints.length - 1, Math.floor(percent * this._playbackPoints.length));
            this.updatePlaybackAtIndex(this._playbackIndex);
        }

        updatePlaybackAtIndex(index) {
            const p = this._playbackPoints[index];
            if (!p) return;
            const renderer = this.ensureFleetRenderer();
            renderer?.setPlaybackActive(this._playbackActive);
            renderer?.setCurrentVehicle(p, { animate: false, skipAnimation: true });
            const speedEl = document.getElementById('tcPbLiveSpeed');
            if (speedEl) speedEl.textContent = parseFloat(p.speed || 0).toFixed(0);
            const indexEl = document.getElementById('tcPbPointIndex');
            if (indexEl) indexEl.textContent = String(index + 1);
            this.updatePlaybackProgress();
            if (this._playbackFollow) this.map?.panTo({ lat: p.lat, lng: p.lng });
        }

        animatePlaybackToIndex(nextIndex, onDone) {
            const from = this._playbackPoints[this._playbackIndex];
            const to = this._playbackPoints[nextIndex];
            if (!from || !to || this._playbackIndex === nextIndex) {
                this.updatePlaybackAtIndex(nextIndex);
                onDone?.();
                return;
            }
            const renderer = this.ensureFleetRenderer();
            if (this._playbackAnimFrame) cancelAnimationFrame(this._playbackAnimFrame);
            this._playbackAnimFrame = null;
            const fromH = parseFloat(from.heading || 0);
            renderer?.setCurrentVehicle({
                ...to,
                _fromHeading: fromH,
            }, {
                skipAnimation: false,
                animDurationMs: Math.max(200, 1000 / this._playbackSpeed),
                onComplete: () => {
                    this._playbackAnimFrame = null;
                    this._playbackIndex = nextIndex;
                    this.updatePlaybackAtIndex(nextIndex);
                    onDone?.();
                },
            });
        }

        advancePlaybackStep() {
            if (!this._isPlaying) return;
            if (this._playbackIndex >= this._playbackPoints.length - 1) {
                this.stopPlayback();
                this.toast(this.mi('playbackFinished', 'Playback finished'), 'success');
                return;
            }
            const nextIndex = this._playbackIndex + 1;
            this.animatePlaybackToIndex(nextIndex, () => {
                if (this._isPlaying) {
                    this._playbackTimer = setTimeout(() => this.advancePlaybackStep(), 80);
                }
            });
        }

        startPlayback() {
            if (!this._playbackPoints.length) {
                this.toast(this.mi('noPlaybackData', 'No playback data'), 'warning');
                return;
            }
            if (this._playbackIndex >= this._playbackPoints.length) this._playbackIndex = 0;
            this._playbackActive = true;
            this._fleetRenderer?.setPlaybackActive(true);
            this._isPlaying = true;
            this.setPlayPauseUi(true);
            this.updatePlaybackMeta();
            clearInterval(this._playbackTimer);
            clearTimeout(this._playbackTimer);
            if (this._playbackAnimFrame) cancelAnimationFrame(this._playbackAnimFrame);
            this.advancePlaybackStep();
        }

        pausePlayback() {
            clearInterval(this._playbackTimer);
            clearTimeout(this._playbackTimer);
            this._playbackTimer = null;
            if (this._playbackAnimFrame) cancelAnimationFrame(this._playbackAnimFrame);
            this._playbackAnimFrame = null;
            this._isPlaying = false;
            this.setPlayPauseUi(false);
            this.updatePlaybackMeta();
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
            const marker = createMapMarker({
                position: { lat: ev.lat, lng: ev.lng }, map: this.map,
                icon: { path: google.maps.SymbolPath.CIRCLE, fillColor: ev.type === 'stop' ? '#f97316' : '#ef4444', fillOpacity: 0.95, strokeColor: '#fff', strokeWeight: 2, scale: 6 },
                title: ev.title || ev.type, zIndex: 550,
            });
            this.historyLayers.push(marker);
        }

        renderSpeedLegend(name) {
            if (!this.legendEl) return;
            const i = this.cfg.i18n || {};
            const rows = [
                { c: '#64748b', t: '0' }, { c: '#22c55e', t: `≤ ${MEDIUM_SPEED}` },
                { c: '#eab308', t: `≤ ${OVER_SPEED}` }, { c: '#ef4444', t: `> ${OVER_SPEED}` },
            ];
            const statusLegend = [
                { letter: 'P', color: STATUS_MARKER_STYLES.parked.color, t: i.statusParking || 'Parking' },
                { letter: 'I', color: STATUS_MARKER_STYLES.idle.color, t: i.statusIdle || 'Idle' },
                { letter: 'S', color: STATUS_MARKER_STYLES.stopped.color, t: i.statusStopped || 'Stopped' },
                { letter: 'X', color: STATUS_MARKER_STYLES.offline.color, t: i.statusOffline || 'Offline' },
            ];
            this.legendEl.innerHTML = (name ? `<div class="tc-legend-item"><strong>${escHtml(name)}</strong></div>` : '') +
                rows.map((r) => `<div class="tc-legend-item"><span class="tc-legend-swatch" style="background:${r.c}"></span>${escHtml(r.t)} ${this.cfg.i18n?.kmhUnit || 'km/h'}</div>`).join('') +
                `<div class="tc-legend-item tc-legend-item--heading"><strong>${escHtml(i.historyMarkers || 'History markers')}</strong></div>` +
                statusLegend.map((r) => `<div class="tc-legend-item"><span class="tc-legend-pin" style="background:${r.color}">${escHtml(r.letter)}</span>${escHtml(r.t)}</div>`).join('');
        }

        fitLayers(layers) {
            const bounds = new google.maps.LatLngBounds();
            let count = 0;
            layers.forEach((l) => {
                if (l.getPath) {
                    l.getPath().forEach((ll) => {
                        const lat = typeof ll.lat === 'function' ? ll.lat() : ll.lat;
                        const lng = typeof ll.lng === 'function' ? ll.lng() : ll.lng;
                        if (!hasGeo(lat, lng)) return;
                        bounds.extend({ lat: parseFloat(lat), lng: parseFloat(lng) });
                        count++;
                    });
                } else if (l.getPosition) {
                    const pos = l.getPosition();
                    const lat = pos?.lat?.();
                    const lng = pos?.lng?.();
                    if (!hasGeo(lat, lng)) return;
                    bounds.extend(pos);
                    count++;
                }
            });
            if (count > 0) {
                try {
                    this.map.fitBounds(bounds, 60);
                } catch (fitErr) {
                    console.warn('[traccar-ui] fitBounds', fitErr);
                }
            }
        }

        /* ---------- Events tab ---------- */
        bindEventsTab() {
            document.getElementById('tcEventsReload')?.addEventListener('click', () => this.loadEvents());
        }

        eventsPermissionMessage(payload, status) {
            const bodyMessage = String(payload?.message || '').trim();
            if (status === 403 || status === 401) {
                return bodyMessage
                    || this.cfg.i18n?.eventsPermissionDenied
                    || 'Access Denied. You do not have permission to view events.';
            }
            return bodyMessage || `Request failed (${status})`;
        }

        async loadEvents() {
            const listEl = document.getElementById('tcEventsList');
            if (!listEl || !this.cfg.eventsJsonUrl) {
                if (listEl) listEl.innerHTML = `<div class="tc-empty">—</div>`;
                return;
            }
            listEl.innerHTML = `<div class="tc-empty">…</div>`;
            try {
                const res = await fetch(`${this.cfg.eventsJsonUrl}?per_page=50&_=${Date.now()}`, {
                    credentials: 'same-origin',
                    cache: 'no-store',
                    headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
                });
                const data = await res.json().catch(() => ({}));
                if (!res.ok) {
                    if (res.status === 401 || res.status === 403) {
                        this._eventsPermissionDenied = true;
                        if (this.alertTimer) {
                            clearInterval(this.alertTimer);
                            this.alertTimer = null;
                        }
                        listEl.innerHTML = `<div class="tc-empty">${escHtml(this.cfg.i18n?.accessDeniedTitle || 'Access Denied')}</div>`;
                        this.popupError(
                            this.eventsPermissionMessage(data, res.status),
                            this.cfg.i18n?.accessDeniedTitle || 'Access Denied',
                        );
                        return;
                    }
                    throw new Error(data.message || `Request failed (${res.status})`);
                }
                this._eventsPermissionDenied = false;
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
                this.toast(err.message || this.cfg.i18n?.loadFailed || 'Failed', 'error');
            }
        }

        locateEvent(lat, lng) {
            this.eventMarker?.setMap(null);
            this.eventMarker = createMapMarker({
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
            // HTTP alert polling only when Reverb is down (see startAlertPollingFallback).
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
            if (this._eventsPermissionDenied || !this.cfg.eventsJsonUrl || document.hidden || (this.realtimeHealthy && !baseline)) return;
            const params = new URLSearchParams({
                per_page: '25',
                page: '1',
                alert_poll: '1',
            });
            if (baseline || this._lastEventId == null) {
                const since = new Date(Date.now() - 30 * 60 * 1000).toISOString();
                params.set('from', since);
            } else {
                params.set('after_id', String(this._lastEventId));
            }
            if (!this.realtimeHealthy) {
                params.set('_', String(Date.now()));
            }
            const url = `${this.cfg.eventsJsonUrl}?${params.toString()}`;
            try {
                const res = await fetch(url, { credentials: 'same-origin', cache: 'no-store' });
                if (!res.ok) {
                    if (res.status === 401 || res.status === 403) {
                        this._eventsPermissionDenied = true;
                        if (this.alertTimer) {
                            clearInterval(this.alertTimer);
                            this.alertTimer = null;
                        }
                    }
                    return;
                }
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

        geofencePermissionMessage(payload, status) {
            const bodyMessage = String(payload?.message || '').trim();
            if (status === 403 || status === 401) {
                return bodyMessage
                    || this.cfg.i18n?.geofencePermissionDenied
                    || 'Access Denied. You do not have permission to view geofences.';
            }
            return bodyMessage || `Request failed (${status})`;
        }

        async loadPlaces() {
            const listEl = document.getElementById('tcPlacesList');
            if (!listEl || !this.cfg.geofencesJsonUrl) {
                if (listEl) listEl.innerHTML = `<div class="tc-empty">—</div>`;
                return;
            }
            listEl.innerHTML = `<div class="tc-empty">…</div>`;
            try {
                const res = await fetch(`${this.cfg.geofencesJsonUrl}?_=${Date.now()}`, {
                    credentials: 'same-origin',
                    cache: 'no-store',
                    headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
                });
                const data = await res.json().catch(() => ({}));
                if (!res.ok) {
                    if (res.status === 401 || res.status === 403) {
                        listEl.innerHTML = `<div class="tc-empty">${escHtml(this.cfg.i18n?.accessDeniedTitle || 'Access Denied')}</div>`;
                        this.popupError(
                            this.geofencePermissionMessage(data, res.status),
                            this.cfg.i18n?.accessDeniedTitle || 'Access Denied',
                        );
                        return;
                    }
                    throw new Error(data.message || `Request failed (${res.status})`);
                }
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
                this.toast(err.message || this.cfg.i18n?.loadFailed || 'Failed', 'error');
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
