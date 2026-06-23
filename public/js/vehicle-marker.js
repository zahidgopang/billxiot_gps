/**
 * Fleet vehicle marker primitives — icon SVG, GPS anchor, heading, screen-fixed pulse.
 * Consumed by FleetMapRenderer (fleet-map-renderer.js) on all map modes.
 */
(function (global) {
    'use strict';

    const SVG_CENTER = 26;
    const VIEWBOX = 52;

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

    const DEFAULTS = {
        vehicleBodyPx: 76,
        labelGap: 8,
        displayScale: 1.2,
        maxIconWidth: 200,
        headingStepDeg: 4,
    };

    function vehicleFrontCapSvg() {
        return '<path d="M26 6 L34 18 L18 18 Z" fill="#ffffff" opacity="0.98"/>'
            + '<path d="M26 6 L34 18 L18 18 Z" fill="none" stroke="rgba(15,23,42,0.35)" stroke-width="1.4"/>';
    }

    function vehicleHaloSvg(color) {
        return `<circle cx="26" cy="26" r="22" fill="none" stroke="${color}" stroke-width="3" opacity="0.45"/>`
            + `<circle cx="26" cy="26" r="18" fill="${color}" opacity="0.12"/>`;
    }

    function vehicleBodySvgInner(vehicleType, color) {
        const white = '#ffffff';
        const wheel = '#0f172a';
        const stroke = ` stroke="${white}" stroke-width="2.6" stroke-linejoin="round"`;
        const shadow = `<ellipse cx="26" cy="29" rx="16" ry="6.5" fill="#000000" opacity="0.28"/>`;
        const front = vehicleFrontCapSvg();
        const halo = vehicleHaloSvg(color);
        const bodies = {
            car: `${shadow}${halo}
                <rect x="14" y="10" width="24" height="34" rx="5" fill="${color}"${stroke}/>
                ${front}
                <rect x="19" y="13" width="14" height="7" rx="2" fill="${white}" opacity="0.92"/>
                <rect x="20" y="32" width="12" height="5" rx="1.5" fill="${white}" opacity="0.65"/>
                <circle cx="17" cy="18" r="3" fill="${wheel}"/><circle cx="35" cy="18" r="3" fill="${wheel}"/>
                <circle cx="17" cy="36" r="3" fill="${wheel}"/><circle cx="35" cy="36" r="3" fill="${wheel}"/>`,
            suv: `${shadow}${halo}
                <rect x="12" y="7" width="28" height="38" rx="6" fill="${color}"${stroke}/>
                ${front}
                <rect x="18" y="10" width="16" height="9" rx="2" fill="${white}" opacity="0.92"/>
                <circle cx="15" cy="15" r="3" fill="${wheel}"/><circle cx="37" cy="15" r="3" fill="${wheel}"/>
                <circle cx="15" cy="38" r="3" fill="${wheel}"/><circle cx="37" cy="38" r="3" fill="${wheel}"/>`,
            truck: `${shadow}${halo}
                <rect x="15" y="8" width="22" height="16" rx="4" fill="${color}"${stroke}/>
                <rect x="13" y="22" width="26" height="22" rx="3" fill="${color}" opacity="0.94"${stroke}/>
                ${front}
                <rect x="20" y="10" width="12" height="7" rx="1.5" fill="${white}" opacity="0.9"/>
                <circle cx="16" cy="22" r="3" fill="${wheel}"/><circle cx="36" cy="22" r="3" fill="${wheel}"/>
                <circle cx="16" cy="40" r="3" fill="${wheel}"/><circle cx="36" cy="40" r="3" fill="${wheel}"/>`,
            van: `${shadow}${halo}
                <rect x="13" y="6" width="26" height="40" rx="4" fill="${color}"${stroke}/>
                ${front}
                <rect x="19" y="9" width="14" height="8" rx="2" fill="${white}" opacity="0.92"/>
                <line x1="13" y1="22" x2="39" y2="22" stroke="${white}" stroke-opacity="0.35" stroke-width="1.2"/>
                <circle cx="16" cy="17" r="3" fill="${wheel}"/><circle cx="36" cy="17" r="3" fill="${wheel}"/>
                <circle cx="16" cy="40" r="3" fill="${wheel}"/><circle cx="36" cy="40" r="3" fill="${wheel}"/>`,
            bus: `${shadow}${halo}
                <rect x="12" y="4" width="28" height="44" rx="5" fill="${color}"${stroke}/>
                <rect x="17" y="8" width="18" height="6" rx="1" fill="${white}" opacity="0.82"/>
                <rect x="17" y="18" width="18" height="6" rx="1" fill="${white}" opacity="0.82"/>
                <rect x="17" y="28" width="18" height="6" rx="1" fill="${white}" opacity="0.82"/>
                <circle cx="15" cy="14" r="3.2" fill="${wheel}"/><circle cx="37" cy="14" r="3.2" fill="${wheel}"/>
                <circle cx="15" cy="40" r="3.2" fill="${wheel}"/><circle cx="37" cy="40" r="3.2" fill="${wheel}"/>`,
            pickup: `${shadow}${halo}
                <rect x="15" y="7" width="22" height="18" rx="4" fill="${color}"${stroke}/>
                <rect x="14" y="23" width="24" height="16" rx="2" fill="${color}" opacity="0.92"${stroke}/>
                ${front}
                <rect x="20" y="9" width="12" height="7" rx="1.5" fill="${white}" opacity="0.9"/>
                <circle cx="16" cy="21" r="3" fill="${wheel}"/><circle cx="36" cy="21" r="3" fill="${wheel}"/>
                <circle cx="16" cy="37" r="3" fill="${wheel}"/><circle cx="36" cy="37" r="3" fill="${wheel}"/>`,
            motorcycle: `${shadow}${halo}
                <rect x="21" y="10" width="10" height="28" rx="4" fill="${color}"${stroke}/>
                ${front}
                <rect x="22" y="12" width="8" height="6" rx="2" fill="${white}" opacity="0.92"/>
                <circle cx="26" cy="14" r="5" fill="none" stroke="${wheel}" stroke-width="2.8"/>
                <circle cx="26" cy="38" r="5" fill="none" stroke="${wheel}" stroke-width="2.8"/>`,
            trailer: `${shadow}${halo}
                <rect x="15" y="12" width="22" height="32" rx="3" fill="${color}" opacity="0.94"${stroke}/>
                <rect x="19" y="16" width="14" height="8" rx="2" fill="${white}" opacity="0.65"/>
                <circle cx="17" cy="40" r="3.4" fill="${wheel}"/><circle cx="35" cy="40" r="3.4" fill="${wheel}"/>`,
            other: `${shadow}${halo}
                <rect x="12" y="12" width="28" height="28" rx="4" fill="${color}"${stroke}/>
                ${front}
                <rect x="18" y="16" width="16" height="8" rx="2" fill="${white}" opacity="0.9"/>
                <circle cx="15" cy="36" r="3.2" fill="${wheel}"/><circle cx="37" cy="36" r="3.2" fill="${wheel}"/>`,
        };
        return bodies[vehicleType] || bodies.car;
    }

    function vehicleBodyTransform(rotation, cx, cy, scale) {
        const s = scale != null ? scale : 1;
        const rot = Number.isFinite(rotation) ? rotation : 0;
        return `translate(${cx}, ${cy}) rotate(${rot}) scale(${s}) translate(${-SVG_CENTER}, ${-SVG_CENTER})`;
    }

    function svgDataUrl(svg) {
        return 'data:image/svg+xml;charset=UTF-8,' + encodeURIComponent(svg);
    }

    function labeledVehicleSvg(identity, color, heading, showDirection, vehicleType, options) {
        const opts = { ...DEFAULTS, ...options };
        const title = identity.title || 'Vehicle';
        const plate = identity.plate || '';
        const type = vehicleType || 'car';
        const body = vehicleBodySvgInner(type, color);
        const esc = (s) => String(s)
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/"/g, '&quot;');

        const titleLen = title.length * 7.2;
        const plateLen = plate ? plate.length * 6 : 0;
        const pillW = Math.max(56, Math.min(168, Math.max(titleLen, plateLen) + 22));
        const titleLineH = 14;
        const plateLineH = plate ? 12 : 0;
        const innerGap = plate ? 3 : 0;
        const pillPadY = 7;
        const pillH = pillPadY + titleLineH + innerGap + plateLineH + pillPadY;
        const vehicleSize = opts.vehicleBodyPx;
        const vehicleTop = pillH + opts.labelGap;
        const vehicleCenterY = vehicleTop + vehicleSize / 2;
        const totalW = Math.max(pillW + 12, vehicleSize + 16);
        const totalH = vehicleTop + vehicleSize + 4;
        const cx = totalW / 2;
        const pillX = (totalW - pillW) / 2;
        const titleY = pillPadY + titleLineH - 3;
        const plateY = titleY + innerGap + plateLineH;
        const rotation = showDirection ? parseFloat(heading || 0) : 0;
        const bodyScale = vehicleSize / VIEWBOX;
        const bodyTransform = vehicleBodyTransform(rotation, cx, vehicleCenterY, bodyScale);
        const liveBadge = opts.showLiveBadge
            ? `<circle cx="${pillX + 14}" cy="12" r="5" fill="#22c55e" stroke="#fff" stroke-width="1.5"/>
               <circle cx="${pillX + 14}" cy="12" r="5" fill="#22c55e" opacity="0.5"><animate attributeName="r" values="5;8;5" dur="1.2s" repeatCount="indefinite"/></circle>`
            : '';

        const svg = `<svg xmlns="http://www.w3.org/2000/svg" width="${totalW}" height="${totalH}" viewBox="0 0 ${totalW} ${totalH}">
            <defs>
                <filter id="vmBadgeShadow" x="-30%" y="-30%" width="160%" height="160%">
                    <feDropShadow dx="0" dy="2" stdDeviation="3" flood-color="#000000" flood-opacity="0.55"/>
                </filter>
            </defs>
            <g filter="url(#vmBadgeShadow)">
                <rect x="${pillX}" y="0" width="${pillW}" height="${pillH}" rx="${Math.min(14, pillH / 2)}" fill="rgba(15,23,42,0.97)" stroke="rgba(255,255,255,0.22)" stroke-width="1.4"/>
            </g>
            ${liveBadge}
            <text x="${cx}" y="${titleY}" text-anchor="middle" fill="#ffffff" font-family="system-ui,-apple-system,sans-serif" font-size="12.5" font-weight="700">${esc(title)}</text>
            ${plate ? `<text x="${cx}" y="${plateY}" text-anchor="middle" fill="rgba(255,255,255,0.85)" font-family="system-ui,-apple-system,sans-serif" font-size="10.5" font-weight="500">${esc(plate)}</text>` : ''}
            <g transform="${bodyTransform}">${body}</g>
        </svg>`;

        return {
            url: svgDataUrl(svg),
            width: totalW,
            height: totalH,
            anchorX: cx,
            anchorY: vehicleCenterY,
        };
    }

    function createIconBuilder(options) {
        const opts = { ...DEFAULTS, ...options };
        const cache = Object.create(null);
        const google = opts.googleMaps || global.google;

        function iconFor(point, extra) {
            if (!google?.maps) {
                return null;
            }

            const identity = opts.getIdentity(point);
            const state = opts.getState(point);
            const color = opts.getColor(state);
            const vehicleType = opts.getVehicleType(point);
            const showDirection = opts.shouldShowDirection(point, state);
            const showLiveBadge = extra?.showLiveBadge ?? opts.getShowLiveBadge?.(point) ?? false;
            const heading = parseFloat(point?.heading || 0);
            const step = opts.headingStepDeg || DEFAULTS.headingStepDeg;
            const cacheKey = [
                identity.title,
                identity.plate || '',
                color,
                vehicleType,
                Math.round(heading / step),
                showDirection ? 1 : 0,
                showLiveBadge ? 1 : 0,
            ].join('|');

            if (cache[cacheKey]) {
                return cache[cacheKey];
            }

            const sized = labeledVehicleSvg(identity, color, heading, showDirection, vehicleType, {
                ...opts,
                showLiveBadge,
            });
            const scale = Math.min(opts.maxIconWidth / sized.width, opts.displayScale);
            const w = Math.round(sized.width * scale);
            const h = Math.round(sized.height * scale);
            const anchorX = Math.round(w * (sized.anchorX / sized.width));
            const anchorY = Math.round(h * (sized.anchorY / sized.height));

            const icon = {
                url: sized.url,
                scaledSize: new google.maps.Size(w, h),
                anchor: new google.maps.Point(anchorX, anchorY),
            };
            cache[cacheKey] = icon;
            return icon;
        }

        return {
            iconFor,
            clearCache() {
                Object.keys(cache).forEach((k) => delete cache[k]);
            },
        };
    }

    function getPulseOverlayClass(googleMaps, stateColors) {
        const g = googleMaps || global.google;
        if (!g?.maps?.OverlayView) {
            return null;
        }
        const colors = stateColors || STATE_COLORS;

        if (getPulseOverlayClass._cls) {
            return getPulseOverlayClass._cls;
        }

        getPulseOverlayClass._cls = class VehiclePulseOverlay extends g.maps.OverlayView {
            constructor() {
                super();
                this.position = null;
                this.color = colors.moving;
                this.container = null;
                this._listeners = [];
            }

            onAdd() {
                const div = document.createElement('div');
                div.className = 'vehicle-live-pulse-wrap';
                div.setAttribute('aria-hidden', 'true');
                div.innerHTML = [
                    '<div class="vehicle-live-pulse-glow"></div>',
                    '<div class="vehicle-live-pulse-ring"></div>',
                    '<div class="vehicle-live-pulse-ring vehicle-live-pulse-ring--delay"></div>',
                    '<div class="vehicle-live-pulse-ring vehicle-live-pulse-ring--delay2"></div>',
                ].join('');
                this.container = div;
                const pane = this.getPanes().overlayLayer || this.getPanes().floatPane;
                pane.appendChild(div);
            }

            onRemove() {
                this._detachMapListeners();
                if (this.container?.parentNode) {
                    this.container.parentNode.removeChild(this.container);
                }
                this.container = null;
            }

            _detachMapListeners() {
                this._listeners.forEach((l) => g.maps.event.removeListener(l));
                this._listeners = [];
            }

            _attachMapListeners() {
                this._detachMapListeners();
                const m = this.getMap();
                if (!m) return;
                const redraw = () => this.draw();
                [
                    'bounds_changed', 'zoom_changed', 'center_changed',
                    'drag', 'dragend', 'idle', 'tilesloaded', 'projection_changed',
                ].forEach((ev) => {
                    this._listeners.push(m.addListener(ev, redraw));
                });
            }

            draw() {
                if (!this.container || !this.position) return;
                const projection = this.getProjection();
                if (!projection) return;
                const point = projection.fromLatLngToDivPixel(
                    new g.maps.LatLng(this.position.lat, this.position.lng)
                );
                if (!point) return;
                this.container.style.left = `${point.x}px`;
                this.container.style.top = `${point.y}px`;
                this.container.style.setProperty('--pulse-color', this.color);
            }

            setPosition(lat, lng) {
                this.position = { lat, lng };
                this.draw();
            }

            setColor(color) {
                this.color = color || colors.moving;
                if (this.container) {
                    this.container.style.setProperty('--pulse-color', this.color);
                }
            }
        };

        return getPulseOverlayClass._cls;
    }

    function createPulseController(options) {
        const opts = options || {};
        const googleMaps = opts.googleMaps || global.google;
        const stateColors = opts.stateColors || STATE_COLORS;
        let overlay = null;
        let map = null;
        let rafId = null;
        let visible = false;

        function pulseTick() {
            if (!visible || !overlay) {
                rafId = null;
                return;
            }
            overlay.draw();
            rafId = global.requestAnimationFrame(pulseTick);
        }

        function startLoop() {
            visible = true;
            if (rafId == null) {
                rafId = global.requestAnimationFrame(pulseTick);
            }
        }

        function stopLoop() {
            visible = false;
            if (rafId != null) {
                global.cancelAnimationFrame(rafId);
                rafId = null;
            }
        }

        function ensure() {
            if (!map) return null;
            const Cls = getPulseOverlayClass(googleMaps, stateColors);
            if (!Cls) return null;
            if (!overlay) {
                overlay = new Cls();
                overlay.setMap(map);
                overlay._attachMapListeners?.();
            } else if (overlay.getMap() !== map) {
                overlay.setMap(map);
                overlay._attachMapListeners?.();
            }
            return overlay;
        }

        return {
            attachMap(m) {
                map = m;
                if (overlay) {
                    overlay.setMap(map);
                    overlay._attachMapListeners?.();
                }
            },
            update(point) {
                if (!point || opts.isHidden?.(point)) {
                    this.hide();
                    return;
                }
                const o = ensure();
                if (!o) return;
                o.setColor(opts.getColor?.(point) || stateColors.moving);
                o.setPosition(point.lat, point.lng);
                startLoop();
            },
            hide() {
                stopLoop();
                if (overlay) {
                    overlay.setMap(null);
                    overlay = null;
                }
            },
            redraw() {
                overlay?.draw();
            },
            stopLoop,
        };
    }

    global.VehicleMarker = {
        SVG_CENTER,
        VIEWBOX,
        STATE_COLORS,
        DEFAULTS,
        vehicleBodySvgInner,
        labeledVehicleSvg,
        createIconBuilder,
        getPulseOverlayClass,
        createPulseController,
        stateColor(state) {
            return STATE_COLORS[state] || STATE_COLORS.parked;
        },
    };
})(typeof window !== 'undefined' ? window : globalThis);
