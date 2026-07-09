/**
 * Shared history analytics — mirrors backend HistoryAnalyticsService + VehicleStatusSpec.
 */
(function (global) {
    'use strict';

    const MOVING_SPEED_KMH = 1;
    const STOP_MIN_SECONDS = 120;
    const STOPPED_MIN_SECONDS = 600;
    const OFFLINE_GAP_SECONDS = 600;
    const OVERSPEED_KMH = 120;

    function parseMs(ts) {
        if (global.AppDateTime?.parseTimestampMs) {
            return global.AppDateTime.parseTimestampMs(ts);
        }
        if (!ts) return null;
        const parsed = Date.parse(String(ts));
        return Number.isNaN(parsed) ? null : parsed;
    }

    function segmentSeconds(t0Ms, t1Ms) {
        if (t0Ms == null || t1Ms == null || t1Ms <= t0Ms) return 0;
        return Math.max(0, (t1Ms - t0Ms) / 1000);
    }

    function haversineKm(lat1, lng1, lat2, lng2) {
        const earth = 6371;
        const dLat = (lat2 - lat1) * Math.PI / 180;
        const dLng = (lng2 - lng1) * Math.PI / 180;
        const a = Math.sin(dLat / 2) ** 2
            + Math.cos(lat1 * Math.PI / 180) * Math.cos(lat2 * Math.PI / 180) * Math.sin(dLng / 2) ** 2;
        return earth * 2 * Math.asin(Math.min(1, Math.sqrt(a)));
    }

    function pointIgnition(point) {
        if (point?.ignition === true || point?.ignition === 1 || point?.ignition === '1') return true;
        if (point?.ignition === false || point?.ignition === 0 || point?.ignition === '0') return false;
        if (point?.acc === true || point?.acc === 1 || point?.acc === '1') return true;
        return false;
    }

    function motionKey(point) {
        const speed = parseFloat(point?.speed || 0);
        const ignition = pointIgnition(point);
        if (ignition) {
            return speed > MOVING_SPEED_KMH ? 'running' : 'idle';
        }
        return speed > MOVING_SPEED_KMH ? 'moving' : 'parked';
    }

    function tripStatusKey(point) {
        const speed = parseFloat(point?.speed || 0);
        const ignition = pointIgnition(point);
        if (speed > MOVING_SPEED_KMH) return 'moving';
        return ignition ? 'idle' : 'parking';
    }

    function timelineLabel(key) {
        const k = String(key || '').toLowerCase();
        if (k === 'running' || k === 'moving') return 'Moving';
        if (k === 'idle') return 'Idle';
        if (k === 'stopped') return 'Stopped';
        if (k === 'parked' || k === 'parking') return 'Parking';
        if (k === 'offline') return 'Offline';
        if (k === 'ignition_on') return 'Ignition ON';
        if (k === 'ignition_off') return 'Ignition OFF';
        return key || '—';
    }

    function refineIdleToStopped(timeline) {
        return (timeline || []).map((seg) => {
            if (seg?.is_transition) return seg;
            const key = String(seg?.status_key || '').toLowerCase();
            const duration = Math.max(0, parseInt(seg?.duration_seconds, 10) || 0);
            if (key === 'idle' && duration >= STOPPED_MIN_SECONDS) {
                return {
                    ...seg,
                    status_key: 'stopped',
                    motion_key: 'stopped',
                    status_label: timelineLabel('stopped'),
                };
            }
            return seg;
        });
    }

    function sortedPoints(points) {
        return [...(points || [])].sort((a, b) => {
            const ta = a.recorded_at_ms ?? parseMs(a.recorded_at) ?? 0;
            const tb = b.recorded_at_ms ?? parseMs(b.recorded_at) ?? 0;
            if (ta !== tb) return ta - tb;
            return (a.position_id || 0) - (b.position_id || 0);
        });
    }

    function analyze(points) {
        const data = sortedPoints(points);
        if (!data.length) {
            return {
                dist: 0,
                maxSpeed: 0,
                overspeedEvents: 0,
                movingSec: 0,
                idleSec: 0,
                parkingSec: 0,
                stoppedSec: 0,
                offlineSec: 0,
                totalSec: 0,
                stops: [],
            };
        }

        let dist = 0;
        let maxSpeed = 0;
        let overspeedEvents = 0;
        let movingSec = 0;
        let idleSec = 0;
        let parkingSec = 0;
        let offlineSec = 0;
        const stops = [];
        let stopRun = [];

        const flushStop = () => {
            if (stopRun.length < 2) {
                stopRun = [];
                return;
            }
            const t0 = parseMs(stopRun[0].recorded_at);
            const t1 = parseMs(stopRun[stopRun.length - 1].recorded_at);
            const dur = segmentSeconds(t0, t1);
            if (dur >= STOP_MIN_SECONDS) {
                const mid = stopRun[Math.floor(stopRun.length / 2)];
                stops.push({
                    lat: mid.lat,
                    lng: mid.lng,
                    duration: dur,
                    start: stopRun[0].recorded_at,
                    end: stopRun[stopRun.length - 1].recorded_at,
                });
            }
            stopRun = [];
        };

        for (let i = 1; i < data.length; i++) {
            const a = data[i - 1];
            const b = data[i];
            dist += haversineKm(a.lat, a.lng, b.lat, b.lng);

            const t0 = parseMs(a.recorded_at);
            const t1 = parseMs(b.recorded_at);
            const dt = segmentSeconds(t0, t1);

            if (dt > OFFLINE_GAP_SECONDS) {
                offlineSec += dt;
                flushStop();
                continue;
            }

            const spd = parseFloat(b.speed || 0);
            if (spd > maxSpeed) maxSpeed = spd;
            if (spd > OVERSPEED_KMH) overspeedEvents++;

            const motion = motionKey(b);
            if (motion === 'running' || motion === 'moving') {
                movingSec += dt;
                flushStop();
            } else if (motion === 'stopped' || motion === 'idle') {
                idleSec += dt;
                stopRun.push(b);
            } else {
                parkingSec += dt;
                stopRun.push(b);
            }
        }
        flushStop();

        const firstMs = parseMs(data[0].recorded_at);
        const lastMs = parseMs(data[data.length - 1].recorded_at);
        const totalSec = segmentSeconds(firstMs, lastMs);

        const stoppedSec = idleSec + parkingSec;
        return {
            dist,
            maxSpeed,
            overspeedEvents,
            movingSec: Math.max(0, movingSec),
            idleSec: Math.max(0, idleSec),
            parkingSec: Math.max(0, parkingSec),
            stoppedSec: Math.max(0, stoppedSec),
            offlineSec: Math.max(0, offlineSec),
            totalSec: Math.max(0, totalSec),
            stops,
        };
    }

    function statusDurationAtIndex(points, index) {
        const data = sortedPoints(points);
        if (index < 0 || index >= data.length) return 0;
        const target = motionKey(data[index]);
        const endMs = parseMs(data[index].recorded_at);
        if (endMs == null) return 0;
        let sinceMs = endMs;
        for (let i = index - 1; i >= 0; i--) {
            if (motionKey(data[i]) !== target) break;
            const ms = parseMs(data[i].recorded_at);
            if (ms != null) sinceMs = ms;
        }
        return segmentSeconds(sinceMs, endMs);
    }

    function statusPayloadAtIndex(points, index) {
        const data = sortedPoints(points);
        if (index < 0 || index >= data.length) return {};
        const motion = motionKey(data[index]);
        const label = timelineLabel(motion);
        const tripKey = tripStatusKey(data[index]);
        return {
            status_key: tripKey,
            status_label: label,
            motion_status_key: motion,
            motion_status: label,
            trip_status_key: tripKey,
            trip_status_label: label,
            status_duration_seconds: statusDurationAtIndex(data, index),
        };
    }

    global.HistoryAnalytics = {
        MOVING_SPEED_KMH,
        STOP_MIN_SECONDS,
        STOPPED_MIN_SECONDS,
        OFFLINE_GAP_SECONDS,
        analyze,
        motionKey,
        tripStatusKey,
        timelineLabel,
        refineIdleToStopped,
        pointIgnition,
        statusPayloadAtIndex,
        statusDurationAtIndex,
        segmentSeconds,
        parseMs,
    };
})(window);
