/**
 * Unified fleet map renderer — current vehicle, pulse, route, start/end, history dots.
 * Single implementation for live tracking, history, playback, and device detail map.
 */
(function (global) {
    'use strict';

    const VM = () => global.VehicleMarker;
    const MARKER_Z = 1000000;
    const ROUTE_START_Z = 500;
    const ROUTE_END_Z = 499;
    const HISTORY_DOT_Z = 100;

    class FleetMapRenderer {
        /**
         * @param {object} options
         * @param {object} options.googleMaps
         * @param {Function} options.getIdentity
         * @param {Function} options.getState
         * @param {Function} options.getColor
         * @param {Function} options.getVehicleType
         * @param {Function} options.shouldShowDirection
         * @param {Function} options.isHidden
         * @param {Function} options.speedToColor
         * @param {Function} [options.onVehicleClick]
         * @param {number} [options.animDurationMs]
         * @param {number} [options.mediumSpeedKmh]
         * @param {number} [options.overSpeedLimit]
         * @param {boolean} [options.routeGlowEnabled]
         * @param {Function} [options.getNightMode]
         * @param {string} [options.startIconUrl]
         * @param {string} [options.endIconUrl]
         */
        constructor(options) {
            this.opts = options;
            this.map = null;
            this.followVehicle = true;
            this.playbackActive = false;
            this.showLiveBadge = true;

            this.vehicleMarker = null;
            this.iconBuilder = null;
            this.pulse = null;
            this._animFrame = null;
            this._animStart = 0;
            this._animFrom = null;
            this._animFromHeading = 0;

            this.polylines = [];
            this.glowPolylines = [];
            this.realtimePolylines = [];
            this.historyDotMarkers = [];
            this.startMarker = null;
            this.endMarker = null;
            this._extraMarkers = [];
        }

        attachMap(map) {
            this.map = map;
            this._initKit();
            if (this.pulse) {
                this.pulse.attachMap(map);
            }
        }

        _initKit() {
            const VehicleMarker = VM();
            if (!VehicleMarker) {
                console.warn('[FleetMapRenderer] VehicleMarker module missing');
                return;
            }
            const g = this.opts.googleMaps || global.google;
            if (!this.iconBuilder) {
                this.iconBuilder = VehicleMarker.createIconBuilder({
                    googleMaps: g,
                    getIdentity: (p) => this.opts.getIdentity(p),
                    getState: (p) => this.opts.getState(p),
                    getColor: (s) => this.opts.getColor(s),
                    getVehicleType: (p) => this.opts.getVehicleType(p),
                    shouldShowDirection: (p, s) => this.opts.shouldShowDirection(p, s),
                    getShowLiveBadge: () => this.showLiveBadge && !this.playbackActive,
                });
            }
            if (!this.pulse) {
                this.pulse = VehicleMarker.createPulseController({
                    googleMaps: g,
                    isHidden: (p) => this.opts.isHidden(p),
                    getColor: (p) => this.opts.getColor(this.opts.getState(p)),
                });
            }
            if (this.map) {
                this.pulse.attachMap(this.map);
            }
        }

        getMarker() {
            return this.vehicleMarker;
        }

        getPosition() {
            const p = this.vehicleMarker?.getPosition();
            if (!p) return null;
            return { lat: p.lat(), lng: p.lng() };
        }

        setFollowVehicle(follow) {
            this.followVehicle = !!follow;
        }

        setPlaybackActive(active) {
            this.playbackActive = !!active;
            this.showLiveBadge = !active;
        }

        setShowLiveBadge(show) {
            this.showLiveBadge = !!show;
        }

        _iconFor(point) {
            this._initKit();
            return this.iconBuilder?.iconFor(point, {
                showLiveBadge: this.showLiveBadge && !this.playbackActive,
            }) || null;
        }

        _updatePulse(point) {
            if (!point || this.opts.isHidden(point)) {
                this.pulse?.hide();
                return;
            }
            const state = this.opts.getState?.(point) ?? 'offline';
            if (state === 'offline' || state === 'blocked' || state === 'stale') {
                this.pulse?.hide();
                return;
            }
            this.pulse?.update(point);
        }

        /**
         * Set current vehicle at exact GPS — used for live, history end, playback.
         */
        setCurrentVehicle(point, options = {}) {
            if (!this.map || !point || this.opts.isHidden(point)) {
                return;
            }

            const position = { lat: point.lat, lng: point.lng };
            const icon = this._iconFor(point);
            const title = this.opts.getIdentity(point).title || 'Vehicle';
            const skipAnimation = options.animate === false || options.skipAnimation === true;

            if (!this.vehicleMarker) {
                const g = this.opts.googleMaps || global.google;
                this.vehicleMarker = new g.maps.Marker({
                    position,
                    map: this.map,
                    title,
                    icon,
                    zIndex: MARKER_Z,
                    optimized: false,
                });
                this.vehicleMarker.addListener('click', () => {
                    this.opts.onVehicleClick?.(point);
                });
                if (this.followVehicle) {
                    this._panTo(position, options.focusZoom);
                }
                this._updatePulse(point);
                return;
            }

            if (skipAnimation) {
                this.vehicleMarker.setPosition(position);
                this.vehicleMarker.setIcon(icon);
                this.vehicleMarker.setTitle(title);
                this._updatePulse(point);
                if (this.followVehicle) {
                    this._panTo(position, options.focusZoom);
                }
                options.onComplete?.();
                return;
            }

            this._animateTo(point, options || {});
        }

        _panTo(position, zoom) {
            if (!this.map) return;
            this.map.panTo(position);
            if (zoom != null && Number.isFinite(zoom)) {
                const z = this.map.getZoom();
                if (z == null || z < zoom) {
                    this.map.setZoom(zoom);
                }
            }
        }

        focusOnVehicle(zoom = 16) {
            const pos = this.getPosition();
            if (pos) {
                this._panTo(pos, zoom);
            }
        }

        _interpolateHeading(from, to, t) {
            let delta = ((to - from + 540) % 360) - 180;
            return (from + delta * t + 360) % 360;
        }

        _animateTo(point, options = {}) {
            const target = { lat: point.lat, lng: point.lng };
            const startPos = this.vehicleMarker?.getPosition();
            const duration = options.animDurationMs ?? this.opts.animDurationMs ?? 1200;

            if (!startPos) {
                this.vehicleMarker.setPosition(target);
                this.vehicleMarker.setIcon(this._iconFor(point));
                this._updatePulse(point);
                options.onComplete?.();
                return;
            }

            this._animFrom = { lat: startPos.lat(), lng: startPos.lng() };
            this._animFromHeading = parseFloat(point._fromHeading ?? point.heading ?? 0);
            this._animStart = performance.now();

            if (this._animFrame) {
                cancelAnimationFrame(this._animFrame);
            }

            const step = (now) => {
                const t = Math.min(1, (now - this._animStart) / duration);
                const eased = 1 - Math.pow(1 - t, 3);
                const lat = this._animFrom.lat + (target.lat - this._animFrom.lat) * eased;
                const lng = this._animFrom.lng + (target.lng - this._animFrom.lng) * eased;
                const heading = this._interpolateHeading(
                    this._animFromHeading,
                    parseFloat(point.heading || 0),
                    eased
                );
                const framePoint = { ...point, lat, lng, heading };

                this.vehicleMarker.setPosition({ lat, lng });
                this.vehicleMarker.setIcon(this._iconFor(framePoint));
                this._updatePulse(framePoint);

                if (this.followVehicle && t > 0.4) {
                    this.map.panTo({ lat, lng });
                }

                if (t < 1) {
                    this._animFrame = requestAnimationFrame(step);
                } else {
                    this._animFrame = null;
                    this.vehicleMarker.setPosition(target);
                    this.vehicleMarker.setIcon(this._iconFor(point));
                    this._updatePulse(point);
                    options.onComplete?.();
                }
            };

            this._animFrame = requestAnimationFrame(step);
        }

        updateVehicleIcon(point) {
            if (!this.vehicleMarker || !point) return;
            this.vehicleMarker.setIcon(this._iconFor(point));
            this._updatePulse(point);
        }

        /** Sync pulse only (e.g. heading refresh without moving marker). */
        syncPulse(point) {
            this._updatePulse(point);
        }

        cancelAnimation() {
            if (this._animFrame) {
                cancelAnimationFrame(this._animFrame);
                this._animFrame = null;
            }
        }

        _routeMarkerIcon(type) {
            const g = this.opts.googleMaps || global.google;
            const url = type === 'start'
                ? (this.opts.startIconUrl || '/images/map/marker-start.svg')
                : (this.opts.endIconUrl || '/images/map/marker-end.svg');
            return {
                url,
                scaledSize: new g.maps.Size(48, 48),
                anchor: new g.maps.Point(24, 24),
            };
        }

        setRouteEndpoints(start, end, options = {}) {
            const g = this.opts.googleMaps || global.google;
            this.startMarker?.setMap(null);
            this.endMarker?.setMap(null);

            if (start) {
                this.startMarker = new g.maps.Marker({
                    position: { lat: start.lat, lng: start.lng },
                    map: this.map,
                    title: options.startTitle || 'Route start',
                    icon: this._routeMarkerIcon('start'),
                    zIndex: ROUTE_START_Z,
                });
                this._extraMarkers.push(this.startMarker);
            }

            if (end) {
                this.endMarker = new g.maps.Marker({
                    position: { lat: end.lat, lng: end.lng },
                    map: this.map,
                    title: options.endTitle || 'Route end',
                    icon: this._routeMarkerIcon('end'),
                    zIndex: ROUTE_END_Z,
                });
                this._extraMarkers.push(this.endMarker);
            }

            if (end && options.updateCurrent !== false) {
                this.setCurrentVehicle(end, { animate: false, focusZoom: options.focusZoom });
            }
        }

        /**
         * Draw full history route with speed-colored segments.
         * @returns {google.maps.LatLngBounds|null}
         */
        drawRoute(points, options = {}) {
            this.clearRoute({ keepVehicle: true, keepRealtime: options.keepRealtime });
            if (!this.map || !points || points.length < 2) {
                return null;
            }

            const g = this.opts.googleMaps || global.google;
            const bounds = new g.maps.LatLngBounds();
            const night = this.opts.getNightMode?.() ?? false;
            const glowOn = this.opts.routeGlowEnabled !== false;

            for (let i = 1; i < points.length; i++) {
                const a = points[i - 1];
                const b = points[i];
                bounds.extend({ lat: a.lat, lng: a.lng });
                bounds.extend({ lat: b.lat, lng: b.lng });

                const segs = this._createSegment(a, b, b.speed, {
                    clickable: options.clickable !== false,
                    night,
                    glowOn,
                    onClick: options.onSegmentClick,
                });
                const line = segs[segs.length - 1];
                if (line && options.haversineDistance) {
                    line._segmentData = {
                        start: a,
                        end: b,
                        speed: b.speed,
                        distance: options.haversineDistance(a.lat, a.lng, b.lat, b.lng),
                        startTime: a.recorded_at,
                        endTime: b.recorded_at,
                    };
                }
                if (line) {
                    this.polylines.push(line);
                }
            }

            if (options.showHistoryDots !== false) {
                this._drawHistoryDots(points);
            }

            this.setRouteEndpoints(points[0], points[points.length - 1], {
                startTitle: options.startTitle,
                endTitle: options.endTitle,
                updateCurrent: options.updateCurrent !== false,
                focusZoom: null,
            });

            return bounds;
        }

        _createSegment(from, to, speed, segOpts) {
            const g = this.opts.googleMaps || global.google;
            const path = [{ lat: from.lat, lng: from.lng }, { lat: to.lat, lng: to.lng }];
            const color = this.opts.speedToColor(speed);
            const medium = this.opts.mediumSpeedKmh ?? 60;
            const over = this.opts.overSpeedLimit ?? 80;
            const weight = speed <= 0 ? 7 : speed <= medium ? 9 : speed <= over ? 10 : 11;
            const segments = [];

            if (segOpts.glowOn) {
                const glow = new g.maps.Polyline({
                    path,
                    strokeColor: color,
                    strokeOpacity: segOpts.night ? 0.38 : 0.26,
                    strokeWeight: weight + 12,
                    clickable: false,
                    map: this.map,
                    zIndex: 1,
                });
                this.glowPolylines.push(glow);
            }

            const line = new g.maps.Polyline({
                path,
                strokeColor: color,
                strokeOpacity: 0.96,
                strokeWeight: weight,
                clickable: segOpts.clickable,
                map: this.map,
                zIndex: 2,
            });

            if (segOpts.onClick) {
                g.maps.event.addListener(line, 'click', (e) => segOpts.onClick(line, e.latLng));
            }

            segments.push(line);
            return segments;
        }

        _drawHistoryDots(points) {
            const g = this.opts.googleMaps || global.google;
            const n = points.length;
            const step = Math.max(1, Math.floor(n / 80));
            const lastIdx = n - 1;

            for (let i = 0; i < lastIdx; i += step) {
                if (i === 0) continue;
                const p = points[i];
                const dot = new g.maps.Marker({
                    position: { lat: p.lat, lng: p.lng },
                    map: this.map,
                    icon: {
                        path: g.maps.SymbolPath.CIRCLE,
                        fillColor: '#38bdf8',
                        fillOpacity: 0.75,
                        strokeColor: '#ffffff',
                        strokeWeight: 2,
                        scale: 4,
                    },
                    zIndex: HISTORY_DOT_Z,
                    clickable: false,
                    optimized: true,
                });
                this.historyDotMarkers.push(dot);
            }
        }

        drawRealtimeSegment(from, to, speed) {
            const segs = this._createSegment(from, to, speed, {
                clickable: false,
                night: this.opts.getNightMode?.() ?? false,
                glowOn: this.opts.routeGlowEnabled !== false,
            });
            segs.forEach((seg) => this.realtimePolylines.push(seg));
            while (this.realtimePolylines.length > 200) {
                this.realtimePolylines.shift().setMap(null);
            }
        }

        clearRealtimeTrail() {
            this.realtimePolylines.forEach((p) => p.setMap(null));
            this.realtimePolylines = [];
        }

        clearRoute(options = {}) {
            this.polylines.forEach((p) => p.setMap(null));
            this.polylines = [];
            this.glowPolylines.forEach((p) => p.setMap(null));
            this.glowPolylines = [];
            if (!options.keepRealtime) {
                this.clearRealtimeTrail();
            }
            this.historyDotMarkers.forEach((m) => m.setMap(null));
            this.historyDotMarkers = [];
            this.startMarker = null;
            this.endMarker = null;
            this._extraMarkers.forEach((m) => {
                if (m !== this.vehicleMarker) {
                    m.setMap(null);
                }
            });
            this._extraMarkers = this.vehicleMarker ? [this.vehicleMarker] : [];
        }

        fitBounds(bounds, padding = 48) {
            if (!bounds || !this.map) return;
            this.map.fitBounds(bounds, padding);
        }

        registerMarker(marker) {
            if (marker && !this._extraMarkers.includes(marker)) {
                this._extraMarkers.push(marker);
            }
        }

        clearExtraMarkers() {
            this._extraMarkers.forEach((m) => {
                if (m !== this.vehicleMarker) {
                    m.setMap(null);
                }
            });
            this._extraMarkers = this.vehicleMarker ? [this.vehicleMarker] : [];
            this.startMarker = null;
            this.endMarker = null;
        }
    }

    global.FleetMapRenderer = FleetMapRenderer;
})(typeof window !== 'undefined' ? window : globalThis);
