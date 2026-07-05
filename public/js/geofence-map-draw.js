/**
 * Custom geofence drawing for Google Maps (replaces deprecated DrawingManager in API 3.65+).
 */
(function (global) {
    'use strict';

    const DEFAULT_SHAPE_OPTS = {
        fillColor: '#2563eb',
        fillOpacity: 0.15,
        strokeColor: '#2563eb',
        strokeWeight: 2,
    };

    function haversineMeters(a, b) {
        const R = 6371000;
        const lat1 = (a.lat() * Math.PI) / 180;
        const lat2 = (b.lat() * Math.PI) / 180;
        const dLat = lat2 - lat1;
        const dLng = ((b.lng() - a.lng()) * Math.PI) / 180;
        const h = Math.sin(dLat / 2) ** 2
            + Math.cos(lat1) * Math.cos(lat2) * Math.sin(dLng / 2) ** 2;
        return 2 * R * Math.asin(Math.sqrt(h));
    }

    function distanceMeters(g, a, b) {
        if (g?.geometry?.spherical?.computeDistanceBetween) {
            return g.geometry.spherical.computeDistanceBetween(a, b);
        }
        return haversineMeters(a, b);
    }

    function latLngToPair(ll) {
        if (!ll) {
            return null;
        }
        const lat = typeof ll.lat === 'function' ? ll.lat() : Number(ll.lat);
        const lng = typeof ll.lng === 'function' ? ll.lng() : Number(ll.lng);
        if (!Number.isFinite(lat) || !Number.isFinite(lng)) {
            return null;
        }
        return [lat, lng];
    }

    function pathToCoords(path) {
        if (!path || typeof path.getLength !== 'function') {
            return null;
        }
        const coords = [];
        for (let i = 0; i < path.getLength(); i++) {
            const pair = latLngToPair(path.getAt(i));
            if (pair) {
                coords.push(pair);
            }
        }
        if (coords.length >= 2) {
            const first = coords[0];
            const last = coords[coords.length - 1];
            if (first[0] === last[0] && first[1] === last[1]) {
                coords.pop();
            }
        }
        return coords.length >= 3 ? coords : null;
    }

    function extractPayload(overlay) {
        if (!overlay) {
            return null;
        }
        if (typeof overlay.getCenter === 'function' && typeof overlay.getRadius === 'function') {
            const pair = latLngToPair(overlay.getCenter());
            if (!pair) {
                return null;
            }
            return {
                type: 'circle',
                center: pair,
                radius: Math.round(overlay.getRadius()),
            };
        }
        if (typeof overlay.getPath === 'function') {
            const coords = pathToCoords(overlay.getPath());
            if (coords) {
                return { type: 'polygon', coords };
            }
        }
        if (typeof overlay.getPaths === 'function') {
            const paths = overlay.getPaths();
            if (paths?.getLength?.() > 0) {
                const coords = pathToCoords(paths.getAt(0));
                if (coords) {
                    return { type: 'polygon', coords };
                }
            }
        }
        return null;
    }

    class GeofenceMapDrawer {
        /**
         * @param {{ map: google.maps.Map, googleMaps?: typeof google.maps, onComplete?: Function, onChange?: Function, shapeOptions?: object }} options
         */
        constructor(options = {}) {
            this.map = options.map;
            this.g = options.googleMaps || global.google?.maps;
            this.onComplete = typeof options.onComplete === 'function' ? options.onComplete : () => {};
            this.onChange = typeof options.onChange === 'function' ? options.onChange : () => {};
            this.shapeOptions = { ...DEFAULT_SHAPE_OPTS, ...(options.shapeOptions || {}) };
            this.mode = null;
            this.listeners = [];
            this.preview = null;
            this.previewLine = null;
            this.points = [];
            this.center = null;
            this.overlay = null;
            this._clickTimer = null;
            this._keydownHandler = (e) => this._onKeyDown(e);
        }

        /** Finalize an in-progress polygon (3+ points) before save. */
        commitForSave() {
            if (this.mode === 'polygon' && this.points.length >= 3) {
                this._finishPolygon();
            }
            return this.overlay;
        }

        extractPendingPayload() {
            if (this.mode !== 'polygon' || this.points.length < 3) {
                return null;
            }
            const coords = this.points.map((ll) => latLngToPair(ll)).filter(Boolean);
            return coords.length >= 3 ? { type: 'polygon', coords } : null;
        }

        startPolygon() {
            this.cancel();
            this.mode = 'polygon';
            this.points = [];
            this._setCursor('crosshair');
            this._listen(this.map, 'click', (e) => this._onPolygonClick(e));
            this._listen(this.map, 'dblclick', (e) => {
                if (this._isModifierClick(e)) {
                    return;
                }
                clearTimeout(this._clickTimer);
                this._clickTimer = null;
                e.stop();
                this._finishPolygon();
            });
            global.addEventListener('keydown', this._keydownHandler);
            this.onChange({ mode: 'polygon', hint: 'click_points' });
        }

        startCircle() {
            this.cancel();
            this.mode = 'circle';
            this.center = null;
            this._setCursor('crosshair');
            this._listen(this.map, 'click', (e) => this._onCircleClick(e));
            this._listen(this.map, 'mousemove', (e) => this._onCircleMove(e));
            global.addEventListener('keydown', this._keydownHandler);
            this.onChange({ mode: 'circle', hint: 'click_center' });
        }

        cancel() {
            this._teardown();
            if (this.overlay) {
                this.overlay.setMap(null);
                this.overlay = null;
            }
            this.onChange({ mode: null });
        }

        getOverlay() {
            return this.overlay;
        }

        clearOverlay() {
            if (this.overlay) {
                this.overlay.setMap(null);
                this.overlay = null;
            }
        }

        /** Skip draw clicks when user holds Ctrl/Cmd (cooperative map zoom uses ctrl+scroll). */
        _isModifierClick(e) {
            const dom = e?.domEvent;
            if (!dom) {
                return false;
            }
            if (dom.ctrlKey || dom.metaKey || dom.altKey) {
                return true;
            }
            return typeof dom.button === 'number' && dom.button !== 0;
        }

        _onPolygonClick(e) {
            if (this.mode !== 'polygon' || this._isModifierClick(e)) {
                return;
            }
            clearTimeout(this._clickTimer);
            const latLng = e.latLng;
            this._clickTimer = setTimeout(() => {
                this._clickTimer = null;
                this.points.push(latLng);
                this._updatePolygonPreview();
                this.onChange({
                    mode: 'polygon',
                    hint: this.points.length >= 3 ? 'dblclick_or_enter' : 'click_points',
                    pointCount: this.points.length,
                });
            }, 220);
        }

        _finishPolygon() {
            if (this.mode !== 'polygon' || this.points.length < 3) {
                return;
            }
            const path = this.points.map((ll) => ({ lat: ll.lat(), lng: ll.lng() }));
            this._clearPreview();
            this.overlay = new this.g.Polygon({
                map: this.map,
                paths: path,
                ...this.shapeOptions,
            });
            this._teardown();
            this.onComplete(this.overlay);
            this.onChange({ mode: null, ready: true });
        }

        _onCircleClick(e) {
            if (this.mode !== 'circle' || this._isModifierClick(e)) {
                return;
            }
            if (!this.center) {
                this.center = e.latLng;
                this.onChange({ mode: 'circle', hint: 'click_radius' });
                return;
            }
            const radius = distanceMeters(this.g, this.center, e.latLng);
            if (radius < 5) {
                return;
            }
            this._finishCircle(radius);
        }

        _onCircleMove(e) {
            if (this.mode !== 'circle' || !this.center) {
                return;
            }
            const radius = distanceMeters(this.g, this.center, e.latLng);
            this._updateCirclePreview(radius);
        }

        _finishCircle(radius) {
            this._clearPreview();
            this.overlay = new this.g.Circle({
                map: this.map,
                center: this.center,
                radius,
                ...this.shapeOptions,
            });
            this._teardown();
            this.onComplete(this.overlay);
            this.onChange({ mode: null, ready: true });
        }

        _updatePolygonPreview() {
            this._clearPreview();
            if (this.points.length < 2) {
                if (this.points.length === 1) {
                    this.previewLine = new this.g.Marker({
                        map: this.map,
                        position: this.points[0],
                        clickable: false,
                        icon: {
                            path: this.g.SymbolPath.CIRCLE,
                            scale: 4,
                            fillColor: this.shapeOptions.strokeColor,
                            fillOpacity: 1,
                            strokeColor: '#fff',
                            strokeWeight: 1,
                        },
                    });
                }
                return;
            }
            const path = this.points.map((ll) => ({ lat: ll.lat(), lng: ll.lng() }));
            this.previewLine = new this.g.Polyline({
                map: this.map,
                path,
                strokeColor: this.shapeOptions.strokeColor,
                strokeOpacity: 0.85,
                strokeWeight: 2,
                clickable: false,
            });
            if (this.points.length >= 3) {
                this.preview = new this.g.Polygon({
                    map: this.map,
                    paths: path,
                    fillColor: this.shapeOptions.fillColor,
                    fillOpacity: 0.08,
                    strokeColor: this.shapeOptions.strokeColor,
                    strokeOpacity: 0.55,
                    strokeWeight: 2,
                    clickable: false,
                });
            }
        }

        _updateCirclePreview(radius) {
            this._clearPreview();
            this.preview = new this.g.Circle({
                map: this.map,
                center: this.center,
                radius: Math.max(radius, 1),
                fillColor: this.shapeOptions.fillColor,
                fillOpacity: 0.08,
                strokeColor: this.shapeOptions.strokeColor,
                strokeOpacity: 0.85,
                strokeWeight: 2,
                clickable: false,
            });
        }

        _clearPreview() {
            this.preview?.setMap(null);
            this.previewLine?.setMap(null);
            this.preview = null;
            this.previewLine = null;
        }

        _onKeyDown(e) {
            if (e.key === 'Escape') {
                this.cancel();
                return;
            }
            if (e.key === 'Enter' && this.mode === 'polygon') {
                this._finishPolygon();
            }
        }

        _setCursor(cursor) {
            if (this.map) {
                this.map.setOptions({ draggableCursor: cursor, draggingCursor: cursor });
            }
        }

        _listen(target, event, handler) {
            const listener = this.g.event.addListener(target, event, handler);
            this.listeners.push(listener);
        }

        _teardown() {
            clearTimeout(this._clickTimer);
            this._clickTimer = null;
            this._clearPreview();
            this.listeners.forEach((l) => this.g.event.removeListener(l));
            this.listeners = [];
            global.removeEventListener('keydown', this._keydownHandler);
            this.mode = null;
            this.points = [];
            this.center = null;
            this._setCursor(null);
        }
    }

    global.GeofenceMapDrawer = GeofenceMapDrawer;
    global.GeofenceDraw = {
        extractPayload,
        resolvePayload(drawer, overlay) {
            drawer?.commitForSave?.();
            const target = overlay || drawer?.getOverlay?.() || null;
            return extractPayload(target) || drawer?.extractPendingPayload?.() || null;
        },
    };
})(window);
