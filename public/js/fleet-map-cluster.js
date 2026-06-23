/**
 * Grid-based marker clustering for admin user fleet maps (web parity with mobile FleetMapCluster).
 */
(function (global) {
    'use strict';

    const CLUSTER_ZOOM_THRESHOLD = 12;
    const CLUSTER_RADIUS_PX = 64;

    function cellDegrees(zoom, latitude) {
        const latRad = (latitude * Math.PI) / 180;
        const metersPerPixel = (156543.03392 * Math.cos(latRad)) / Math.pow(2, Math.min(20, Math.max(3, zoom)));
        const radiusMeters = CLUSTER_RADIUS_PX * metersPerPixel;
        const degrees = radiusMeters / 111320.0;
        return Math.max(degrees, 0.0006);
    }

    function ungrouped(positions) {
        return Object.entries(positions).map(([id, pos]) => ({
            isCluster: false,
            deviceId: parseInt(id, 10),
            position: pos,
            memberIds: [parseInt(id, 10)],
        }));
    }

    /**
     * @param {Record<number, {lat: number, lng: number}>} positions
     * @param {number} zoom
     * @returns {Array<{isCluster: boolean, count?: number, deviceId?: number, position: {lat: number, lng: number}, memberIds: number[]}>}
     */
    function group(positions, zoom) {
        const entries = Object.entries(positions);
        if (entries.length === 0) return [];
        if (zoom >= CLUSTER_ZOOM_THRESHOLD || entries.length <= 1) {
            return ungrouped(positions);
        }

        const avgLat = entries.reduce((sum, [, p]) => sum + p.lat, 0) / entries.length;
        const cell = cellDegrees(zoom, avgLat);
        const buckets = {};

        entries.forEach(([id, pos]) => {
            const latCell = Math.floor(pos.lat / cell);
            const lngCell = Math.floor(pos.lng / cell);
            const key = `${latCell}_${lngCell}`;
            if (!buckets[key]) buckets[key] = [];
            buckets[key].push(parseInt(id, 10));
        });

        const items = [];
        Object.values(buckets).forEach((ids) => {
            ids.sort((a, b) => a - b);
            if (ids.length === 1) {
                const id = ids[0];
                items.push({
                    isCluster: false,
                    deviceId: id,
                    position: positions[id],
                    memberIds: ids,
                });
                return;
            }

            let lat = 0;
            let lng = 0;
            ids.forEach((id) => {
                lat += positions[id].lat;
                lng += positions[id].lng;
            });
            items.push({
                isCluster: true,
                count: ids.length,
                deviceId: ids[0],
                position: { lat: lat / ids.length, lng: lng / ids.length },
                memberIds: ids,
            });
        });

        items.sort((a, b) => {
            const aKey = a.isCluster ? `c_${a.memberIds.join('_')}` : `d_${a.deviceId}`;
            const bKey = b.isCluster ? `c_${b.memberIds.join('_')}` : `d_${b.deviceId}`;
            return aKey.localeCompare(bKey);
        });

        return items;
    }

    function stableMarkerId(item) {
        if (!item.isCluster) return `device_${item.deviceId}`;
        return `cluster_${item.memberIds.join('_')}`;
    }

    function expandZoom(currentZoom) {
        const base = currentZoom ?? CLUSTER_ZOOM_THRESHOLD;
        return Math.min(17, Math.max(CLUSTER_ZOOM_THRESHOLD + 1, Math.max(CLUSTER_ZOOM_THRESHOLD + 1.5, base + 2.5)));
    }

    function boundsFor(item, positions) {
        if (!item.isCluster) return null;
        let minLat = null;
        let maxLat = null;
        let minLng = null;
        let maxLng = null;
        item.memberIds.forEach((id) => {
            const p = positions[id];
            if (!p) return;
            minLat = minLat == null ? p.lat : Math.min(minLat, p.lat);
            maxLat = maxLat == null ? p.lat : Math.max(maxLat, p.lat);
            minLng = minLng == null ? p.lng : Math.min(minLng, p.lng);
            maxLng = maxLng == null ? p.lng : Math.max(maxLng, p.lng);
        });
        if (minLat == null) return null;
        return { minLat, maxLat, minLng, maxLng };
    }

    global.FleetMapCluster = {
        group,
        stableMarkerId,
        expandZoom,
        boundsFor,
        CLUSTER_ZOOM_THRESHOLD,
    };
}(typeof window !== 'undefined' ? window : global));
