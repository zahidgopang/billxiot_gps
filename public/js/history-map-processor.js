/**
 * Main-thread facade for history-map-worker with sync fallback.
 */
(function (global) {
    'use strict';

    let worker = null;
    let workerMsgId = 0;
    const pending = new Map();

    function slimPoints(points) {
        return (points || []).map((p) => ({
            lat: +p.lat,
            lng: +p.lng,
            speed: +(p.speed || 0),
            recorded_at: p.recorded_at || p.time || null,
        }));
    }

    function haversineKm(lat1, lng1, lat2, lng2) {
        const R = 6371;
        const toRad = Math.PI / 180;
        const dLat = (lat2 - lat1) * toRad;
        const dLng = (lng2 - lng1) * toRad;
        const a = Math.sin(dLat / 2) ** 2
            + Math.cos(lat1 * toRad) * Math.cos(lat2 * toRad) * Math.sin(dLng / 2) ** 2;
        return 2 * R * Math.asin(Math.sqrt(a));
    }

    function syncFallback(points, opts) {
        const simplify = global.PolylineSimplify?.simplifyForMap;
        const medium = opts.mediumSpeedKmh ?? 60;
        const over = opts.overSpeedLimit ?? 80;
        const slim = slimPoints(points);
        const simplified = simplify
            ? simplify(slim, opts.maxPoints ?? 2500, { mediumSpeedKmh: medium, overSpeedLimit: over })
            : slim;

        const speedToColor = (speed) => {
            const spd = parseFloat(speed || 0);
            if (spd <= 0) return '#64748b';
            if (spd <= medium) return '#22c55e';
            if (spd <= over) return '#eab308';
            return '#ef4444';
        };

        const chunks = [];
        for (let i = 1; i < simplified.length; i++) {
            const a = simplified[i - 1];
            const b = simplified[i];
            const color = speedToColor(b.speed);
            const last = chunks[chunks.length - 1];
            const stepKm = haversineKm(a.lat, a.lng, b.lat, b.lng);
            if (last && last.color === color) {
                last.path.push({ lat: b.lat, lng: b.lng });
                last.segmentData.end = b;
                last.segmentData.speed = b.speed;
                last.segmentData.distance = (Number(last.segmentData.distance) || 0) + stepKm;
                last.segmentData.endTime = b.recorded_at;
            } else {
                chunks.push({
                    color,
                    path: [{ lat: a.lat, lng: a.lng }, { lat: b.lat, lng: b.lng }],
                    segmentData: {
                        start: a,
                        end: b,
                        speed: b.speed,
                        distance: stepKm,
                        startTime: a.recorded_at,
                        endTime: b.recorded_at,
                    },
                });
            }
        }

        let north = -90;
        let south = 90;
        let east = -180;
        let west = 180;
        simplified.forEach((p) => {
            north = Math.max(north, p.lat);
            south = Math.min(south, p.lat);
            east = Math.max(east, p.lng);
            west = Math.min(west, p.lng);
        });

        return {
            simplified,
            chunks,
            bounds: { north, south, east, west },
            pointCount: slim.length,
            mapPointCount: simplified.length,
        };
    }

    function ensureWorker(workerUrl) {
        if (worker || !global.Worker || !workerUrl) {
            return worker;
        }
        try {
            worker = new Worker(workerUrl);
            worker.onmessage = (event) => {
                const data = event.data || {};
                if (data.type !== 'processRouteResult') return;
                const entry = pending.get(data.id);
                if (!entry) return;
                pending.delete(data.id);
                if (data.ok) {
                    entry.resolve(data);
                } else {
                    entry.reject(new Error(data.error || 'worker_failed'));
                }
            };
            worker.onerror = (err) => {
                pending.forEach((entry) => entry.reject(err));
                pending.clear();
                worker = null;
            };
        } catch (err) {
            worker = null;
            console.warn('[history-map-processor] worker unavailable', err);
        }
        return worker;
    }

    function processRoute(points, opts, workerUrl) {
        const w = ensureWorker(workerUrl);
        if (!w) {
            return Promise.resolve(syncFallback(points, opts || {}));
        }

        const timeoutMs = opts?.workerTimeoutMs ?? 12000;

        const workerTask = new Promise((resolve, reject) => {
            const id = ++workerMsgId;
            pending.set(id, { resolve, reject });
            w.postMessage({
                type: 'processRoute',
                id,
                points: slimPoints(points),
                opts: opts || {},
            });
        });

        const timeoutTask = new Promise((_, reject) => {
            setTimeout(() => reject(new Error('worker_timeout')), timeoutMs);
        });

        return Promise.race([workerTask, timeoutTask]).catch(() => syncFallback(points, opts || {}));
    }

    global.HistoryMapProcessor = {
        processRoute,
    };
})(typeof window !== 'undefined' ? window : globalThis);
