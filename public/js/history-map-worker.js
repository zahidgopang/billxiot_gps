/**
 * Off-main-thread route processing: simplify GPS track + build color chunks.
 */
(function () {
    'use strict';

    function speedBucket(speed, medium, over) {
        const spd = parseFloat(speed || 0);
        if (spd <= 0) return 0;
        if (spd <= medium) return 1;
        if (spd <= over) return 2;
        return 3;
    }

    function speedToColor(speed, medium, over) {
        const spd = parseFloat(speed || 0);
        if (spd <= 0) return '#64748b';
        if (spd <= medium) return '#22c55e';
        if (spd <= over) return '#eab308';
        return '#ef4444';
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

    function perpendicularDistanceMeters(point, lineStart, lineEnd) {
        const lat = point.lat;
        const lng = point.lng;
        const lat1 = lineStart.lat;
        const lng1 = lineStart.lng;
        const lat2 = lineEnd.lat;
        const lng2 = lineEnd.lng;

        if (lat1 === lat2 && lng1 === lng2) {
            return haversineKm(lat, lng, lat1, lng1) * 1000;
        }

        const dx = lat2 - lat1;
        const dy = lng2 - lng1;
        const t = Math.max(0, Math.min(1, ((lat - lat1) * dx + (lng - lng1) * dy) / (dx * dx + dy * dy)));
        const projLat = lat1 + t * dx;
        const projLng = lng1 + t * dy;
        return haversineKm(lat, lng, projLat, projLng) * 1000;
    }

    function douglasPeucker(points, epsilonMeters) {
        if (points.length <= 2) return points;

        let maxDist = 0;
        let index = 0;
        const end = points.length - 1;

        for (let i = 1; i < end; i++) {
            const dist = perpendicularDistanceMeters(points[i], points[0], points[end]);
            if (dist > maxDist) {
                maxDist = dist;
                index = i;
            }
        }

        if (maxDist > epsilonMeters) {
            const left = douglasPeucker(points.slice(0, index + 1), epsilonMeters);
            const right = douglasPeucker(points.slice(index), epsilonMeters);
            return left.slice(0, -1).concat(right);
        }

        return [points[0], points[end]];
    }

    function simplifyForMap(points, maxPoints, opts) {
        if (!Array.isArray(points) || points.length <= 2) {
            return points || [];
        }

        const medium = opts.mediumSpeedKmh ?? 60;
        const over = opts.overSpeedLimit ?? 80;
        const epsilon = opts.epsilonMeters ?? 12;

        const buckets = [];
        let bucketStart = 0;
        for (let i = 1; i < points.length; i++) {
            const prev = speedBucket(points[i - 1]?.speed, medium, over);
            const cur = speedBucket(points[i]?.speed, medium, over);
            if (prev !== cur) {
                buckets.push(points.slice(bucketStart, i));
                bucketStart = i - 1;
            }
        }
        buckets.push(points.slice(bucketStart));

        const simplified = [];
        buckets.forEach((bucket, idx) => {
            const part = bucket.length <= 3
                ? bucket
                : douglasPeucker(bucket, epsilon);
            if (idx > 0 && simplified.length && part.length) {
                simplified.push(...part.slice(1));
            } else {
                simplified.push(...part);
            }
        });

        if (simplified.length > maxPoints) {
            const step = Math.ceil(simplified.length / maxPoints);
            const out = [];
            for (let i = 0; i < simplified.length; i += step) {
                out.push(simplified[i]);
            }
            const last = simplified[simplified.length - 1];
            if (out[out.length - 1] !== last) {
                out.push(last);
            }
            return out;
        }

        return simplified;
    }

    function buildColorChunks(points, medium, over) {
        const chunks = [];

        for (let i = 1; i < points.length; i++) {
            const a = points[i - 1];
            const b = points[i];
            const color = speedToColor(b.speed, medium, over);
            const last = chunks[chunks.length - 1];

            if (last && last.color === color) {
                last.path.push({ lat: b.lat, lng: b.lng });
                last.segmentData.end = b;
                last.segmentData.speed = b.speed;
                last.segmentData.distance += haversineKm(a.lat, a.lng, b.lat, b.lng);
                last.segmentData.endTime = b.recorded_at || null;
            } else {
                chunks.push({
                    color,
                    path: [
                        { lat: a.lat, lng: a.lng },
                        { lat: b.lat, lng: b.lng },
                    ],
                    segmentData: {
                        start: { lat: a.lat, lng: a.lng, speed: a.speed, recorded_at: a.recorded_at },
                        end: { lat: b.lat, lng: b.lng, speed: b.speed, recorded_at: b.recorded_at },
                        speed: b.speed,
                        distance: haversineKm(a.lat, a.lng, b.lat, b.lng),
                        startTime: a.recorded_at || null,
                        endTime: b.recorded_at || null,
                    },
                });
            }
        }

        return chunks;
    }

    function computeBounds(points) {
        let north = -90;
        let south = 90;
        let east = -180;
        let west = 180;
        points.forEach((p) => {
            north = Math.max(north, p.lat);
            south = Math.min(south, p.lat);
            east = Math.max(east, p.lng);
            west = Math.min(west, p.lng);
        });
        return { north, south, east, west };
    }

    self.onmessage = function (event) {
        const msg = event.data || {};
        if (msg.type !== 'processRoute') {
            return;
        }

        try {
            const points = Array.isArray(msg.points) ? msg.points : [];
            const opts = msg.opts || {};
            const medium = opts.mediumSpeedKmh ?? 60;
            const over = opts.overSpeedLimit ?? 80;
            const maxPoints = opts.maxPoints ?? 2500;

            const slim = points.map((p) => ({
                lat: +p.lat,
                lng: +p.lng,
                speed: +(p.speed || 0),
                recorded_at: p.recorded_at || p.time || null,
            }));

            const simplified = simplifyForMap(slim, maxPoints, {
                mediumSpeedKmh: medium,
                overSpeedLimit: over,
                epsilonMeters: opts.epsilonMeters ?? 12,
            });

            const chunks = buildColorChunks(simplified, medium, over);

            self.postMessage({
                type: 'processRouteResult',
                id: msg.id,
                ok: true,
                simplified,
                chunks,
                bounds: computeBounds(simplified),
                pointCount: slim.length,
                mapPointCount: simplified.length,
            });
        } catch (err) {
            self.postMessage({
                type: 'processRouteResult',
                id: msg.id,
                ok: false,
                error: String(err?.message || err),
            });
        }
    };
})();
