/**
 * Polyline simplification for map rendering — preserves speed-class changes.
 */
(function (global) {
    'use strict';

    function speedBucket(speed, medium, over) {
        const spd = parseFloat(speed || 0);
        if (spd <= 0) return 0;
        if (spd <= medium) return 1;
        if (spd <= over) return 2;
        return 3;
    }

    /**
     * Downsample while keeping first/last and speed-bucket boundaries.
     * @param {Array} points
     * @param {number} maxPoints
     * @param {{ mediumSpeedKmh?: number, overSpeedLimit?: number }} opts
     */
    function simplifyForMap(points, maxPoints, opts = {}) {
        if (!Array.isArray(points) || points.length <= maxPoints) {
            return points || [];
        }

        const medium = opts.mediumSpeedKmh ?? 60;
        const over = opts.overSpeedLimit ?? 80;
        const n = points.length;
        const keep = new Set([0, n - 1]);

        for (let i = 1; i < n; i++) {
            const prev = speedBucket(points[i - 1]?.speed, medium, over);
            const cur = speedBucket(points[i]?.speed, medium, over);
            if (prev !== cur) {
                keep.add(i - 1);
                keep.add(i);
            }
        }

        const step = Math.max(1, Math.ceil(n / maxPoints));
        for (let i = 0; i < n; i += step) {
            keep.add(i);
        }

        const indices = Array.from(keep).sort((a, b) => a - b);
        if (indices.length > maxPoints) {
            const thinStep = Math.ceil(indices.length / maxPoints);
            const thinned = [];
            for (let i = 0; i < indices.length; i += thinStep) {
                thinned.push(indices[i]);
            }
            if (thinned[thinned.length - 1] !== indices[indices.length - 1]) {
                thinned.push(indices[indices.length - 1]);
            }
            return thinned.map((idx) => points[idx]);
        }

        return indices.map((idx) => points[idx]);
    }

    global.PolylineSimplify = {
        simplifyForMap,
    };
})(typeof window !== 'undefined' ? window : globalThis);
