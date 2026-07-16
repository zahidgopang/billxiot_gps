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
            this.followVehicle = false;
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
            this._progressiveDrawToken = 0;
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
                    getMarkerStyle: (p) => this.opts.getMarkerStyle?.(p) || 'labeled',
                    getMarkerSizeScale: (p) => this.opts.getMarkerSizeScale?.(p) ?? 1,
                    getCustomIconUrl: (p) => this.opts.getCustomIconUrl?.(p) ?? null,
                    getMapIconUrl: (p) => this.opts.getMapIconUrl?.(p)
                        ?? global.VehicleMarker?.resolveMapIconUrl?.(p)
                        ?? global.VehicleMarker?.resolveFallbackIconUrl?.(p)
                        ?? null,
                    getFallbackIconUrl: (p) => this.opts.getFallbackIconUrl?.(p)
                        ?? global.VehicleMarker?.resolveFallbackIconUrl?.(p)
                        ?? global.BuiltinMapIcons?.fallbackUrl?.()
                        ?? '/icons/builtin/Vehicles/car.svg',
                    getRotationEnabled: (p) => this.opts.getRotationEnabled?.(p) !== false,
                    getRotationOffset: (p) => this.opts.getRotationOffset?.(p)
                        ?? global.VehicleMarker?.resolveIconRotationOffset?.(p)
                        ?? 0,
                    shouldShowDirection: (p, s) => this.opts.shouldShowDirection(p, s),
                    getShowLiveBadge: () => this.showLiveBadge && !this.playbackActive,
                    vehicleBodyPx: this.opts.mapRendering?.vehicle_body_px,
                    displayScale: this.opts.mapRendering?.display_scale,
                    maxIconWidth: this.opts.mapRendering?.max_icon_width,
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

        refreshIconKit() {
            this.iconBuilder?.clearCache?.();
            this.iconBuilder = null;
            this._initKit();
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

        _applyIconRotation(icon) {
            if (!this.vehicleMarker) {
                return;
            }
            if (global.VehicleMarker?.applyMarkerIcon && icon) {
                global.VehicleMarker.applyMarkerIcon(this.vehicleMarker, icon);
                return;
            }
            if (!icon?.meta) {
                this.vehicleMarker.setFlat?.(false);
                this.vehicleMarker.setRotation?.(0);
                return;
            }
            if (typeof this.vehicleMarker.setFlat === 'function') {
                this.vehicleMarker.setFlat(!!icon.meta.flat);
            }
            if (typeof this.vehicleMarker.setRotation === 'function') {
                this.vehicleMarker.setRotation(Number(icon.meta.rotation) || 0);
            }
        }

        _applyPose(lat, lng, heading, point) {
            if (!this.vehicleMarker) return;
            const VM = global.VehicleMarker;
            if (VM?.applyMarkerPose) {
                VM.applyMarkerPose(
                    this.vehicleMarker,
                    lat,
                    lng,
                    heading,
                    VM.resolveIconRotationOffset?.(point) ?? 0,
                    VM.resolveRotationEnabled?.(point) !== false,
                );
                return;
            }
            this.vehicleMarker.setPosition({ lat, lng });
            this._applyIconRotation(this._iconFor({ ...point, lat, lng, heading }));
        }

        ensureMotionEngine() {
            if (this._motionEngine || !global.VehicleMotion?.createMotionEngine) return;
            this._motionEngine = global.VehicleMotion.createMotionEngine({
                onPose: (_id, pose) => {
                    this._renderPos = { lat: pose.lat, lng: pose.lng };
                    this._renderHeading = pose.heading;
                    const point = { ...(this._lastPoint || {}), lat: pose.lat, lng: pose.lng, heading: pose.heading };
                    this._applyPose(pose.lat, pose.lng, pose.heading, point);
                    this._updatePulse(point);
                    if (this.followVehicle) {
                        this.map?.panTo({ lat: pose.lat, lng: pose.lng });
                    }
                },
            });
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
                const VM = global.VehicleMarker;
                const createMarker = VM?.createMarker || global.GoogleMapsPlatform?.createMarker;
                this.vehicleMarker = createMarker({
                    position,
                    map: this.map,
                    title,
                    icon,
                    zIndex: MARKER_Z,
                    optimized: false,
                });
                this._applyPose(
                    position.lat,
                    position.lng,
                    parseFloat(point.heading || 0) || 0,
                    point,
                );
                this.vehicleMarker.addListener('click', () => {
                    global.GoogleMapsPlatform?.runAfterMarkerClick?.(() => {
                        this.opts.onVehicleClick?.(point);
                    });
                });
                if (this.followVehicle) {
                    this._panTo(position, options.focusZoom);
                }
                this._updatePulse(point);
                return;
            }

            if (skipAnimation) {
                this._motionEngine?.clear('v');
                if (global.VehicleMarker?.applyMarkerIcon && icon) {
                    global.VehicleMarker.applyMarkerIcon(this.vehicleMarker, icon);
                } else {
                    this.vehicleMarker.setIcon(icon);
                }
                this._applyPose(
                    position.lat,
                    position.lng,
                    parseFloat(point.heading || 0) || 0,
                    point,
                );
                this.vehicleMarker.setTitle(title);
                this._updatePulse(point);
                this._lastPoint = point;
                this._renderPos = position;
                this._renderHeading = parseFloat(point.heading || 0) || 0;
                if (this.followVehicle) {
                    this._panTo(position, options.focusZoom);
                }
                options.onComplete?.();
                return;
            }

            this._lastPoint = point;
            this.ensureMotionEngine();
            if (this._motionEngine) {
                const key = this.opts.getState?.(point) || point.status_key || '';
                const spd = Math.max(0, parseFloat(point.speed) || 0);
                const moving = (key === 'running' || key === 'moving') && spd >= 2;
                this._motionEngine.setFix('v', {
                    lat: point.lat,
                    lng: point.lng,
                    heading: moving ? point.heading : (this._renderHeading ?? point.heading),
                    speed: spd,
                    moving,
                    recorded_at: point.recorded_at || point.last_update || point.timestamp || null,
                });
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
            const VM = global.VehicleMotion;
            const speedKmh = Math.max(0, parseFloat(point.speed) || 0);
            const now = performance.now();
            const interval = this.opts.pollIntervalMs || this.opts.animDurationMs || 2000;
            const observed = this._lastTargetAt ? (now - this._lastTargetAt) : interval;
            this._lastTargetAt = now;

            if (!startPos) {
                this.vehicleMarker.setPosition(target);
                this._applyIconRotation(this._iconFor(point));
                this._updatePulse(point);
                this._renderPos = target;
                this._renderHeading = parseFloat(point.heading || 0) || 0;
                options.onComplete?.();
                return;
            }

            const from = this._renderPos || { lat: startPos.lat(), lng: startPos.lng() };
            const fromHeading = Number.isFinite(Number(this._renderHeading))
                ? Number(this._renderHeading)
                : parseFloat(point._fromHeading ?? point.heading ?? 0);
            const toHeading = parseFloat(point.heading || fromHeading);
            const duration = options.animDurationMs
                ?? (VM?.durationMs
                    ? VM.durationMs({
                        from,
                        to: target,
                        speedKmh,
                        intervalMs: interval,
                        observedIntervalMs: observed,
                    })
                    : (this.opts.animDurationMs ?? 1200));

            this._animFrom = from;
            this._animFromHeading = fromHeading;
            this._animToHeading = toHeading;
            this._animStart = now;
            this._animDuration = duration;

            if (this._animFrame) {
                cancelAnimationFrame(this._animFrame);
            }

            const ease = VM?.easeInOutCubic
                || ((t) => 1 - Math.pow(1 - t, 3));
            const lerpH = VM?.lerpHeading
                || ((a, b, t) => this._interpolateHeading(a, b, t));

            const step = (frameNow) => {
                const rawT = Math.min(1, (frameNow - this._animStart) / this._animDuration);
                const eased = ease(rawT);
                const lat = this._animFrom.lat + (target.lat - this._animFrom.lat) * eased;
                const lng = this._animFrom.lng + (target.lng - this._animFrom.lng) * eased;
                const heading = lerpH(this._animFromHeading, this._animToHeading, eased);
                const framePoint = { ...point, lat, lng, heading };

                this._renderPos = { lat, lng };
                this._renderHeading = heading;
                this._applyPose(lat, lng, heading, framePoint);
                this._updatePulse(framePoint);

                if (this.followVehicle && rawT > 0.15) {
                    this.map.panTo({ lat, lng });
                }

                if (rawT < 1) {
                    this._animFrame = requestAnimationFrame(step);
                } else {
                    this._animFrame = null;
                    this._renderPos = target;
                    this._renderHeading = toHeading;
                    const finalPoint = { ...point, lat: target.lat, lng: target.lng, heading: toHeading };
                    this._applyPose(target.lat, target.lng, toHeading, finalPoint);
                    this._updatePulse(finalPoint);
                    options.onComplete?.();
                }
            };

            this._animFrame = requestAnimationFrame(step);
        }

        updateVehicleIcon(point) {
            if (!this.vehicleMarker || !point) return;
            const icon = this._iconFor(point);
            if (global.VehicleMarker?.applyMarkerIcon) {
                global.VehicleMarker.applyMarkerIcon(this.vehicleMarker, icon);
            } else {
                this.vehicleMarker.setIcon(icon);
            }
            this._applyPose(
                Number(point.lat),
                Number(point.lng),
                parseFloat(point.heading || 0) || 0,
                point,
            );
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
            this._motionEngine?.clear();
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

            const VM = global.VehicleMarker;
            const createMarker = VM?.createMarker || global.GoogleMapsPlatform?.createMarker;

            if (start) {
                this.startMarker = createMarker({
                    position: { lat: start.lat, lng: start.lng },
                    map: this.map,
                    title: options.startTitle || 'Route start',
                    icon: this._routeMarkerIcon('start'),
                    zIndex: ROUTE_START_Z,
                });
                this._extraMarkers.push(this.startMarker);
            }

            if (end) {
                this.endMarker = createMarker({
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
         * Merges consecutive same-color segments for large routes.
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
            const largeRoute = points.length > 250;
            const glowOn = options.glowOn !== false
                && this.opts.routeGlowEnabled !== false
                && points.length <= 1500;

            points.forEach((p) => bounds.extend({ lat: p.lat, lng: p.lng }));

            const chunks = this._buildColorChunks(points, options.haversineDistance);
            const clickable = options.clickable !== false && points.length <= 3500;

            chunks.forEach((chunk) => {
                const segs = this._createPathPolyline(chunk.path, chunk.color, {
                    clickable,
                    night,
                    glowOn,
                    onClick: options.onSegmentClick,
                    segmentData: chunk.segmentData,
                });
                segs.forEach((line) => {
                    if (line) this.polylines.push(line);
                });
            });

            if (options.showHistoryDots !== false && !largeRoute) {
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

        cancelProgressiveDraw() {
            this._progressiveDrawToken += 1;
        }

        /**
         * Draw pre-built color chunks progressively (non-blocking).
         * @param {Array} colorChunks
         * @param {{ start: object, end: object }} endpoints
         * @param {object} options
         * @returns {google.maps.LatLngBounds|null}
         */
        drawRouteChunksProgressive(colorChunks, endpoints, options = {}) {
            this.cancelProgressiveDraw();
            const token = this._progressiveDrawToken;

            if (!this.map || !colorChunks?.length) {
                return null;
            }

            const g = this.opts.googleMaps || global.google;
            const bounds = new g.maps.LatLngBounds();
            const night = this.opts.getNightMode?.() ?? false;
            const mapPointCount = options.mapPointCount ?? colorChunks.length;
            const glowOn = options.glowOn !== false
                && this.opts.routeGlowEnabled !== false
                && mapPointCount <= 1500;
            const clickable = options.clickable !== false && mapPointCount <= 3500;
            const batchSize = options.batchSize ?? 10;
            let index = 0;
            let endpointsPlaced = false;

            colorChunks.forEach((chunk) => {
                chunk.path.forEach((p) => bounds.extend(p));
            });

            const step = () => {
                if (token !== this._progressiveDrawToken) {
                    return;
                }

                const end = Math.min(index + batchSize, colorChunks.length);
                for (; index < end; index++) {
                    const chunk = colorChunks[index];
                    const segs = this._createPathPolyline(chunk.path, chunk.color, {
                        clickable,
                        night,
                        glowOn,
                        onClick: options.onSegmentClick,
                        segmentData: chunk.segmentData,
                    });
                    segs.forEach((line) => {
                        if (line) this.polylines.push(line);
                    });
                }

                if (!endpointsPlaced && endpoints?.start && endpoints?.end && index > 0) {
                    this.setRouteEndpoints(endpoints.start, endpoints.end, {
                        startTitle: options.startTitle,
                        endTitle: options.endTitle,
                        updateCurrent: false,
                        focusZoom: null,
                    });
                    endpointsPlaced = true;
                    if (typeof options.onEndpointsPlaced === 'function') {
                        options.onEndpointsPlaced();
                    }
                }

                if (index < colorChunks.length) {
                    requestAnimationFrame(step);
                } else if (typeof options.onComplete === 'function') {
                    options.onComplete(bounds);
                }
            };

            requestAnimationFrame(step);
            return bounds;
        }

        /**
         * @returns {Array<{color: string, path: Array<{lat:number,lng:number}>, segmentData: object|null}>}
         */
        _buildColorChunks(points, haversineDistance) {
            const speedToColor = this.opts.speedToColor;
            const haversine = haversineDistance || this.opts.haversineDistance || null;
            const chunks = [];

            for (let i = 1; i < points.length; i++) {
                const a = points[i - 1];
                const b = points[i];
                const color = speedToColor(b.speed);
                const last = chunks[chunks.length - 1];

                if (last && last.color === color) {
                    last.path.push({ lat: b.lat, lng: b.lng });
                    last.segmentData = {
                        start: last.segmentData?.start || a,
                        end: b,
                        speed: b.speed,
                        distance: haversine
                            ? (last.segmentData?.distance || 0) + haversine(a.lat, a.lng, b.lat, b.lng)
                            : 0,
                        startTime: last.segmentData?.startTime || a.recorded_at,
                        endTime: b.recorded_at,
                    };
                } else {
                    chunks.push({
                        color,
                        path: [{ lat: a.lat, lng: a.lng }, { lat: b.lat, lng: b.lng }],
                        segmentData: {
                            start: a,
                            end: b,
                            speed: b.speed,
                            distance: haversine ? haversine(a.lat, a.lng, b.lat, b.lng) : 0,
                            startTime: a.recorded_at,
                            endTime: b.recorded_at,
                        },
                    });
                }
            }

            return chunks;
        }

        _createPathPolyline(path, color, segOpts) {
            const g = this.opts.googleMaps || global.google;
            const speed = segOpts.segmentData?.speed ?? 0;
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

            if (segOpts.segmentData) {
                line._segmentData = segOpts.segmentData;
            }

            if (segOpts.onClick && segOpts.clickable) {
                g.maps.event.addListener(line, 'click', (e) => segOpts.onClick(line, e.latLng));
            }

            segments.push(line);
            return segments;
        }

        _createSegment(from, to, speed, segOpts) {
            const color = this.opts.speedToColor(speed);
            return this._createPathPolyline(
                [{ lat: from.lat, lng: from.lng }, { lat: to.lat, lng: to.lng }],
                color,
                {
                    ...segOpts,
                    segmentData: {
                        start: from,
                        end: to,
                        speed,
                        distance: this.opts.haversineDistance
                            ? this.opts.haversineDistance(from.lat, from.lng, to.lat, to.lng)
                            : 0,
                        startTime: from.recorded_at,
                        endTime: to.recorded_at,
                    },
                },
            );
        }

        _drawHistoryDots(points) {
            const g = this.opts.googleMaps || global.google;
            const n = points.length;
            const step = Math.max(1, Math.floor(n / 80));
            const lastIdx = n - 1;

            const createMarker = global.VehicleMarker?.createMarker || global.GoogleMapsPlatform?.createMarker;

            for (let i = 0; i < lastIdx; i += step) {
                if (i === 0) continue;
                const p = points[i];
                const dot = createMarker({
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
