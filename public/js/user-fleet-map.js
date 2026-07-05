/**
 * Multi-device fleet map — labeled markers, pulse, clustering.
 */
(function (global) {
    'use strict';

    const DEFAULT_CENTER = { lat: 25.276987, lng: 55.296249 };

    const STATE_COLORS = {
        running: '#22c55e',
        stopped: '#f97316',
        parked: '#94a3b8',
        moving: '#a855f7',
        delayed: '#eab308',
        stale: '#f59e0b',
        offline: '#ef4444',
        alert: '#ef4444',
        idle: '#f97316',
        ignition_off: '#94a3b8',
    };

    function svgDataUrl(svg) {
        return 'data:image/svg+xml;charset=UTF-8,' + encodeURIComponent(svg);
    }

    function clusterIcon(count) {
        const size = 44;
        const svg = `<svg xmlns="http://www.w3.org/2000/svg" width="${size}" height="${size}" viewBox="0 0 ${size} ${size}">
            <circle cx="22" cy="22" r="20" fill="#1976D2" stroke="#fff" stroke-width="3"/>
            <text x="22" y="27" text-anchor="middle" fill="#fff" font-family="system-ui,sans-serif" font-size="14" font-weight="700">${count}</text>
        </svg>`;
        return {
            url: svgDataUrl(svg),
            scaledSize: new google.maps.Size(size, size),
            anchor: new google.maps.Point(size / 2, size / 2),
        };
    }

    function positionCount(positions) {
        return Object.keys(positions).length;
    }

    class UserFleetMap {
        constructor(config) {
            this.config = config;
            this.map = null;
            this.mapZoom = 11;
            this.isGestureActive = false;
            this.pollTimer = null;
            this.pollInFlight = false;

            this.deviceById = new Map();
            this.markers = new Map();
            this.pulses = new Map();
            this.iconBuilder = null;
            this.clusterIconCache = new Map();
            this.vehiclePopup = null;
        }

        showBootError(message) {
            const el = document.getElementById('userFleetMapError');
            const mapEl = document.getElementById('userFleetMap');
            if (el) {
                el.hidden = false;
                el.querySelector('[data-fleet-error-text]')?.replaceChildren(document.createTextNode(message));
            }
            if (mapEl) mapEl.setAttribute('aria-hidden', 'true');
        }

        boot() {
            const cfg = this.config;
            if (!cfg.googleMapsKey) {
                this.showBootError('Google Maps API key is missing.');
                return;
            }

            (cfg.devices || []).forEach((d) => this.deviceById.set(d.id, d));

            const callbackName = '__userFleetMapGoogleReady';
            const startMap = () => {
                if (!global.google?.maps?.Map) {
                    this.showBootError('Google Maps failed to initialize.');
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
                    this.showBootError('Could not load Google Maps. Check your connection or API key.');
                });
                return;
            }

            global[callbackName] = () => {
                try {
                    delete global[callbackName];
                } catch (_) {
                    global[callbackName] = undefined;
                }
                startMap();
            };

            const existing = document.querySelector('script[data-user-fleet-maps]');
            if (existing) {
                if (global.google?.maps?.Map) {
                    this.initMap();
                } else {
                    existing.addEventListener('load', () => global[callbackName]?.(), { once: true });
                }
                return;
            }

            const script = document.createElement('script');
            script.dataset.userFleetMaps = '1';
            script.async = true;
            script.defer = true;
            script.onerror = () => this.showBootError('Could not load Google Maps. Check your connection or API key.');
            script.src = `https://maps.googleapis.com/maps/api/js?key=${encodeURIComponent(cfg.googleMapsKey)}&loading=async&v=weekly&callback=${callbackName}`;
            document.head.appendChild(script);
        }

        initMap() {
            const VM = global.VehicleMarker;
            if (!VM) {
                this.showBootError('Map marker module failed to load.');
                return;
            }

            const mapEl = document.getElementById('userFleetMap');
            if (!mapEl) return;

            const positions = this.positionMap();
            const count = positionCount(positions);
            const bounds = new google.maps.LatLngBounds();
            Object.values(positions).forEach((p) => bounds.extend(p));

            const center = count > 0 ? bounds.getCenter() : DEFAULT_CENTER;
            const initialZoom = count === 1 ? 13 : 11;

            const baseMapOpts = {
                center,
                zoom: initialZoom,
                mapTypeControl: false,
                streetViewControl: false,
                fullscreenControl: true,
                gestureHandling: 'greedy',
            };
            this.map = new google.maps.Map(
                mapEl,
                global.GoogleMapsPlatform?.mapOptions
                    ? global.GoogleMapsPlatform.mapOptions(baseMapOpts, this.config.googleMapsMapId)
                    : baseMapOpts,
            );

            if (count > 1) {
                this.map.fitBounds(bounds, 56);
            } else if (count === 1) {
                this.map.setCenter(Object.values(positions)[0]);
                this.map.setZoom(13);
            }

            this.mapZoom = this.map.getZoom() || initialZoom;

            this.iconBuilder = VM.createIconBuilder({
                googleMaps: google,
                getIdentity: (p) => ({ title: p.title, plate: p.plate || '' }),
                getState: (p) => p.status_key || 'offline',
                getColor: (state) => STATE_COLORS[state] || STATE_COLORS.offline,
                getVehicleType: (p) => p.vehicle_type || 'car',
                getMarkerStyle: (p) => VM.resolveMarkerStyle(p),
                getMarkerSizeScale: (p) => VM.resolveMarkerSizeScale(p, this.config.mapSpec),
                getCustomIconUrl: (p) => VM.resolveCustomIconUrl(p),
                getRotationEnabled: (p) => VM.resolveRotationEnabled(p),
                shouldShowDirection: (p, state) => state === 'moving' || state === 'running',
                getShowLiveBadge: () => true,
                vehicleBodyPx: this.config.mapSpec?.vehicle_body_px,
                displayScale: this.config.mapSpec?.display_scale,
                maxIconWidth: this.config.mapSpec?.max_icon_width,
            });

            if (global.VehicleMapPopup) {
                const i = this.config.i18n || {};
                this.vehiclePopup = new global.VehicleMapPopup({
                    getMap: () => this.map,
                    googleMaps: google,
                    stateColors: STATE_COLORS,
                    i18n: {
                        dash: i.dash || '—',
                        plate: i.plate || 'Plate',
                        odometer: i.odometer || 'Odometer',
                        status: i.status || 'Status',
                        altitude: i.altitude || 'Altitude',
                        angle: i.angle || 'Angle',
                        position: i.position || 'Position',
                        engine: i.engine || 'Engine',
                        statusFor: i.statusFor || 'for',
                        ignitionOn: i.ignitionOn || 'On',
                        ignitionOff: i.ignitionOff || 'Off',
                        openMap: i.openMap || 'Open full map',
                        close: i.close || 'Close',
                    },
                });
                this.map.addListener('click', () => {
                    if (global.GoogleMapsPlatform?.shouldSuppressMapClick?.()) return;
                    this.vehiclePopup?.close();
                });
            }

            google.maps.event.addListener(this.map, 'zoom_changed', () => {
                if (this.isGestureActive) return;
                this.mapZoom = this.map.getZoom() || this.mapZoom;
                this.renderMarkers();
            });

            google.maps.event.addListener(this.map, 'dragstart', () => {
                this.isGestureActive = true;
                this.pausePulses();
            });

            google.maps.event.addListener(this.map, 'idle', () => {
                if (!this.isGestureActive) return;
                this.isGestureActive = false;
                this.mapZoom = this.map.getZoom() || this.mapZoom;
                this.renderMarkers();
                this.resumePulses();
            });

            document.getElementById('btnFitFleet')?.addEventListener('click', () => this.fitBounds());
            document.getElementById('btnRefreshFleet')?.addEventListener('click', () => this.pollLive(true));

            this.renderMarkers();
            this.startPolling();
            this.updateStats(this.config.stats);

            const resize = () => {
                if (!this.map) return;
                google.maps.event.trigger(this.map, 'resize');
                if (count > 1) {
                    this.map.fitBounds(bounds, 56);
                }
            };
            requestAnimationFrame(resize);
            window.setTimeout(resize, 150);
            window.addEventListener('resize', () => {
                if (!this.map) return;
                google.maps.event.trigger(this.map, 'resize');
            });
        }

        positionMap() {
            const out = {};
            this.deviceById.forEach((d, id) => {
                if (d.lat == null || d.lng == null) return;
                if (!Number.isFinite(d.lat) || !Number.isFinite(d.lng)) return;
                out[id] = { lat: d.lat, lng: d.lng };
            });
            return out;
        }

        devicePoint(device) {
            return {
                id: device.id,
                lat: device.lat,
                lng: device.lng,
                heading: device.heading || 0,
                title: device.title,
                plate: device.plate || '',
                vehicle_type: device.vehicle_type || 'car',
                map_marker_style: device.map_marker_style || 'labeled',
                map_marker_size: device.map_marker_size || '100',
                map_marker_size_scale: device.map_marker_size_scale || 1,
                map_icon_source: device.map_icon_source || 'default',
                map_custom_icon_url: device.map_custom_icon_url || null,
                map_icon_rotation_enabled: device.map_icon_rotation_enabled !== false,
                status_key: device.status_key || 'offline',
                status_label: device.status_label,
                status_duration_seconds: device.status_duration_seconds,
                speed: device.speed,
                ignition: device.ignition,
                altitude: device.altitude,
                odometer: device.odometer,
                odometer_km: device.odometer_km,
                launch_map_url: device.launch_map_url,
            };
        }

        refreshIconKit() {
            this.iconBuilder?.clearCache?.();
            this.iconBuilder = null;
            const VM = global.VehicleMarker;
            if (!VM || !this.map) return;
            this.iconBuilder = VM.createIconBuilder({
                googleMaps: google,
                getIdentity: (p) => ({ title: p.title, plate: p.plate || '' }),
                getState: (p) => p.status_key || 'offline',
                getColor: (state) => STATE_COLORS[state] || STATE_COLORS.offline,
                getVehicleType: (p) => p.vehicle_type || 'car',
                getMarkerStyle: (p) => VM.resolveMarkerStyle(p),
                getMarkerSizeScale: (p) => VM.resolveMarkerSizeScale(p, this.config.mapSpec),
                getCustomIconUrl: (p) => VM.resolveCustomIconUrl(p),
                getRotationEnabled: (p) => VM.resolveRotationEnabled(p),
                shouldShowDirection: (p, state) => state === 'moving' || state === 'running',
                getShowLiveBadge: () => true,
                vehicleBodyPx: this.config.mapSpec?.vehicle_body_px,
                displayScale: this.config.mapSpec?.display_scale,
                maxIconWidth: this.config.mapSpec?.max_icon_width,
            });
            this.renderMarkers();
        }

        clearMarkers() {
            this.markers.forEach((m) => m.setMap(null));
            this.markers.clear();
        }

        pausePulses() {
            this.pulses.forEach((pulse) => pulse.hide?.());
        }

        resumePulses() {
            this.deviceById.forEach((device, id) => {
                if (device.lat == null || device.lng == null) return;
                const pulse = this.pulses.get(id);
                if (!pulse) return;
                const state = device.status_key || 'offline';
                if (state === 'offline' || state === 'blocked' || state === 'stale') {
                    pulse.hide?.();
                    return;
                }
                pulse.update?.(this.devicePoint(device));
            });
        }

        ensurePulse(deviceId) {
            const VM = global.VehicleMarker;
            if (!this.pulses.has(deviceId) && VM?.createPulseController) {
                const pulse = VM.createPulseController({
                    googleMaps: google,
                    isHidden: (p) => !p || p.status_key === 'offline' || p.status_key === 'blocked',
                    getColor: (p) => STATE_COLORS[p?.status_key] || STATE_COLORS.offline,
                });
                pulse.attachMap(this.map);
                this.pulses.set(deviceId, pulse);
            }
            return this.pulses.get(deviceId);
        }

        renderMarkers() {
            if (!this.map || !global.FleetMapCluster) return;

            this.clearMarkers();
            const positions = this.positionMap();
            const items = global.FleetMapCluster.group(positions, this.mapZoom);

            items.forEach((item) => {
                if (item.isCluster) {
                    const clusterCount = item.count || item.memberIds.length;
                    let icon = this.clusterIconCache.get(clusterCount);
                    if (!icon) {
                        icon = clusterIcon(clusterCount);
                        this.clusterIconCache.set(clusterCount, icon);
                    }
                    const marker = global.VehicleMarker.createMarker({
                        map: this.map,
                        position: item.position,
                        icon,
                        zIndex: 900,
                        title: `${clusterCount} vehicles`,
                    });
                    marker.addListener('click', () => {
                        const b = global.FleetMapCluster.boundsFor(item, positions);
                        if (b) {
                            const clusterBounds = new google.maps.LatLngBounds(
                                { lat: b.minLat, lng: b.minLng },
                                { lat: b.maxLat, lng: b.maxLng }
                            );
                            this.map.fitBounds(clusterBounds, 64);
                        } else {
                            this.map.setCenter(item.position);
                            this.map.setZoom(global.FleetMapCluster.expandZoom(this.mapZoom));
                        }
                    });
                    this.markers.set(global.FleetMapCluster.stableMarkerId(item), marker);
                    return;
                }

                const device = this.deviceById.get(item.deviceId);
                if (!device) return;
                const point = this.devicePoint(device);
                const icon = this.iconBuilder?.iconFor(point, { showLiveBadge: true });
                const marker = global.VehicleMarker.createMarker({
                    map: this.map,
                    position: item.position,
                    icon: icon || undefined,
                    zIndex: 1000,
                    title: device.title,
                });
                marker.addListener('click', () => {
                    global.GoogleMapsPlatform?.runAfterMarkerClick?.(() => {
                        const point = this.devicePoint(device);
                        if (this.vehiclePopup && point.lat != null) {
                            this.vehiclePopup.open(point, marker?.getAnchor?.() || marker);
                        } else if (device.launch_map_url) {
                            window.location.href = device.launch_map_url;
                        }
                    });
                });
                this.markers.set(global.FleetMapCluster.stableMarkerId(item), marker);

                const pulse = this.ensurePulse(item.deviceId);
                pulse?.update?.(point);
            });

            const visibleIds = new Set(
                items.filter((i) => !i.isCluster).map((i) => i.deviceId)
            );
            this.pulses.forEach((pulse, id) => {
                if (!visibleIds.has(id)) pulse.hide?.();
            });
        }

        fitBounds() {
            const positions = this.positionMap();
            const keys = Object.keys(positions);
            if (!keys.length || !this.map) return;
            if (keys.length === 1) {
                this.map.setCenter(positions[keys[0]]);
                this.map.setZoom(14);
                return;
            }
            const bounds = new google.maps.LatLngBounds();
            keys.forEach((id) => bounds.extend(positions[id]));
            this.map.fitBounds(bounds, 56);
        }

        updateStats(stats) {
            if (!stats) return;
            const set = (key, val) => {
                const el = document.querySelector(`[data-fleet-stat="${key}"]`);
                if (el) el.textContent = String(val ?? 0);
            };
            set('totalDevices', stats.totalDevices);
            set('onlineNow', stats.onlineNow);
            set('running', stats.running);
            set('offlineNow', stats.offlineNow);
        }

        applyLivePayload(payload) {
            (payload.devices || []).forEach((d) => this.deviceById.set(d.id, d));
            if (!this.isGestureActive) {
                this.renderMarkers();
            }
            if (this.vehiclePopup?.openId != null) {
                const d = this.deviceById.get(this.vehiclePopup.openId);
                if (d) {
                    this.vehiclePopup.update(this.devicePoint(d));
                }
            }
            this.updateStats(payload.stats);
        }

        async pollLive(force) {
            if (this.pollInFlight && !force) return;
            const url = this.config.liveJsonUrl;
            if (!url) return;
            this.pollInFlight = true;
            try {
                // Cache-bust + no-store so each poll returns fresh positions
                // instead of a replayed cached response (frozen markers).
                const sep = url.includes('?') ? '&' : '?';
                const res = await fetch(`${url}${sep}_=${Date.now()}`, {
                    cache: 'no-store',
                    headers: {
                        Accept: 'application/json',
                        'X-Requested-With': 'XMLHttpRequest',
                    },
                    credentials: 'same-origin',
                });
                if (!res.ok) return;
                const data = await res.json();
                this.applyLivePayload(data);
            } catch (err) {
                console.warn('[UserFleetMap] live poll failed', err);
            } finally {
                this.pollInFlight = false;
            }
        }

        startPolling() {
            const interval = this.config.pollIntervalMs || 5000;
            this.pollLive(false);
            this.pollTimer = setInterval(() => this.pollLive(false), interval);
            document.addEventListener('visibilitychange', () => {
                if (document.visibilityState === 'visible') {
                    this.pollLive(true);
                }
            });
        }
    }

    function bootUserFleetMap() {
        const cfg = global.USER_FLEET_MAP_CONFIG;
        if (!cfg) return;
        const map = new UserFleetMap(cfg);
        map.boot();
        global.__userFleetMap = map;
    }

    bootUserFleetMap();
}(typeof window !== 'undefined' ? window : global));
