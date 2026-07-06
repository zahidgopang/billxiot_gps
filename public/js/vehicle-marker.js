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

    function shadeColor(hex, percent) {
        const raw = String(hex || '#22c55e').replace('#', '');
        const full = raw.length === 3 ? raw.split('').map((c) => c + c).join('') : raw;
        const num = parseInt(full, 16);
        if (!Number.isFinite(num)) {
            return hex;
        }
        const adjust = (channel) => Math.min(255, Math.max(0, Math.round(channel + (255 * percent) / 100)));
        const r = adjust((num >> 16) & 0xff);
        const g = adjust((num >> 8) & 0xff);
        const b = adjust(num & 0xff);
        return `#${[r, g, b].map((v) => v.toString(16).padStart(2, '0')).join('')}`;
    }

    function vehicleShadowSvg() {
        return '<ellipse cx="26" cy="28" rx="13.5" ry="5" fill="#0f172a" opacity="0.2"/>';
    }

    function vehicleDirectionSvg() {
        return '<path d="M26 5.2 L30.8 12.8 L26 10.2 L21.2 12.8 Z" fill="#ffffff" opacity="0.96"/>'
            + '<path d="M26 5.2 L30.8 12.8 L26 10.2 L21.2 12.8 Z" fill="none" stroke="rgba(15,23,42,0.22)" stroke-width="0.9" stroke-linejoin="round"/>';
    }

    function vehicleGlassSvg(x, y, w, h, rx) {
        return `<rect x="${x}" y="${y}" width="${w}" height="${h}" rx="${rx}" fill="#dbeafe" opacity="0.62" stroke="#ffffff" stroke-width="0.7" stroke-opacity="0.45"/>`;
    }

    function wheelSvg(cx, cy, r) {
        const radius = r || 2.15;
        return `<circle cx="${cx}" cy="${cy}" r="${radius}" fill="#0f172a" opacity="0.88"/>`
            + `<circle cx="${cx}" cy="${cy}" r="${Math.max(0.8, radius - 0.9)}" fill="#334155" opacity="0.75"/>`;
    }

    function modernBodyShell(color, gradId, pathD, extras) {
        const light = shadeColor(color, 22);
        const dark = shadeColor(color, -14);
        return `${vehicleShadowSvg()}`
            + `<defs>`
            + `<linearGradient id="${gradId}" x1="26" y1="6" x2="26" y2="46" gradientUnits="userSpaceOnUse">`
            + `<stop offset="0%" stop-color="${light}"/>`
            + `<stop offset="100%" stop-color="${dark}"/>`
            + `</linearGradient>`
            + `<filter id="vm-${gradId}" x="-25%" y="-25%" width="150%" height="150%">`
            + `<feDropShadow dx="0" dy="1.6" stdDeviation="1.6" flood-color="#0f172a" flood-opacity="0.32"/>`
            + `</filter>`
            + `</defs>`
            + `<g filter="url(#vm-${gradId})">`
            + `<path d="${pathD}" fill="url(#${gradId})" stroke="#ffffff" stroke-width="1.35" stroke-linejoin="round"/>`
            + `${extras || ''}`
            + `</g>`
            + vehicleDirectionSvg();
    }

    function vehicleBodySvgInner(vehicleType, color) {
        const bodies = {
            car: modernBodyShell(color, 'car', `
                M26 7.2 C21.2 7.2 17.4 10.2 16.2 14.8 L14.8 20.8 C14.2 23 14.2 25.8 14.8 28 L16.2 37.2
                C17.4 41.4 21.2 44.2 26 44.2 C30.8 44.2 34.6 41.4 35.8 37.2 L37.2 28 C37.8 25.8 37.8 23 37.2 20.8
                L35.8 14.8 C34.6 10.2 30.8 7.2 26 7.2 Z`, `
                ${vehicleGlassSvg(19.5, 13.5, 13, 8.5, 2.2)}
                ${wheelSvg(18.2, 19.5)}${wheelSvg(33.8, 19.5)}
                ${wheelSvg(18.2, 35.5)}${wheelSvg(33.8, 35.5)}`),
            suv: modernBodyShell(color, 'suv', `
                M26 6.5 C20.5 6.5 16 10 14.8 15.2 L13.5 21.5 C12.8 24 12.8 27 13.5 29.5 L14.8 37.5
                C16 42.2 20.5 45.5 26 45.5 C31.5 45.5 36 42.2 37.2 37.5 L38.5 29.5 C39.2 27 39.2 24 38.5 21.5
                L37.2 15.2 C36 10 31.5 6.5 26 6.5 Z`, `
                ${vehicleGlassSvg(18, 11.5, 16, 10, 2.4)}
                ${wheelSvg(16.5, 17)}${wheelSvg(35.5, 17)}
                ${wheelSvg(16.5, 38)}${wheelSvg(35.5, 38)}`),
            truck: modernBodyShell(color, 'truck', `
                M26 7 C21.5 7 18.5 9.8 17.8 13.8 L17.2 18.5 L14.5 20.5 C13.5 21.2 13 22.5 13 24.2 L13.5 36.5
                C14.2 40.5 17.5 43.5 21.5 43.5 L30.5 43.5 C34.5 43.5 37.8 40.5 38.5 36.5 L39 24.2 C39 22.5 38.5 21.2 37.5 20.5
                L34.8 18.5 L34.2 13.8 C33.5 9.8 30.5 7 26 7 Z
                M21 20.5 L31 20.5 L31 38.5 L21 38.5 Z`, `
                ${vehicleGlassSvg(20.5, 9.5, 11, 7.5, 1.8)}
                ${wheelSvg(17.5, 20)}${wheelSvg(34.5, 20)}
                ${wheelSvg(17.5, 37)}${wheelSvg(34.5, 37)}`),
            van: modernBodyShell(color, 'van', `
                M26 6.2 C20.8 6.2 16.8 9.5 15.8 14.5 L14.5 21 C13.8 23.5 13.8 26.5 14.5 29 L15.8 37.5
                C16.8 42.2 20.8 45.5 26 45.5 C31.2 45.5 35.2 42.2 36.2 37.5 L37.5 29 C38.2 26.5 38.2 23.5 37.5 21
                L36.2 14.5 C35.2 9.5 31.2 6.2 26 6.2 Z`, `
                ${vehicleGlassSvg(18.5, 10.5, 15, 9, 2.2)}
                <line x1="15" y1="24" x2="37" y2="24" stroke="#ffffff" stroke-opacity="0.28" stroke-width="1"/>
                ${wheelSvg(17, 18)}${wheelSvg(35, 18)}
                ${wheelSvg(17, 38.5)}${wheelSvg(35, 38.5)}`),
            bus: modernBodyShell(color, 'bus', `
                M26 5.5 C20 5.5 15.5 9 14.5 14.5 L13.5 21.5 C12.8 24 12.8 27.5 13.5 30 L14.5 38
                C15.5 43 20 46.5 26 46.5 C32 46.5 36.5 43 37.5 38 L38.5 30 C39.2 27.5 39.2 24 38.5 21.5
                L37.5 14.5 C36.5 9 32 5.5 26 5.5 Z`, `
                ${vehicleGlassSvg(17.5, 9, 17, 5.5, 1.2)}
                ${vehicleGlassSvg(17.5, 16.5, 17, 5.5, 1.2)}
                ${vehicleGlassSvg(17.5, 24, 17, 5.5, 1.2)}
                ${wheelSvg(16, 15, 2.4)}${wheelSvg(36, 15, 2.4)}
                ${wheelSvg(16, 40, 2.4)}${wheelSvg(36, 40, 2.4)}`),
            pickup: modernBodyShell(color, 'pickup', `
                M26 7 C21.5 7 18.5 9.5 17.8 13.5 L17.2 18 L14.8 19.5 C13.8 20.2 13.2 21.5 13.2 23.2 L14 35.5
                C14.8 39.5 18 42.2 22 42.2 L30 42.2 C34 42.2 37.2 39.5 38 35.5 L38.8 23.2 C38.8 21.5 38.2 20.2 37.2 19.5
                L34.8 18 L34.2 13.5 C33.5 9.5 30.5 7 26 7 Z
                M15.5 24.5 L36.5 24.5 L36.5 37.5 L15.5 37.5 Z`, `
                ${vehicleGlassSvg(20, 9.5, 12, 7, 1.8)}
                ${wheelSvg(17.5, 20)}${wheelSvg(34.5, 20)}
                ${wheelSvg(17.5, 36.5)}${wheelSvg(34.5, 36.5)}`),
            motorcycle: modernBodyShell(color, 'moto', `
                M26 9.5 C24 9.5 22.5 11 22.2 13 L21.5 24 L21.2 32 C21 35 22.5 37 26 37 C29.5 37 31 35 30.8 32
                L30.5 24 L29.8 13 C29.5 11 28 9.5 26 9.5 Z`, `
                ${vehicleGlassSvg(22.5, 12.5, 7, 5.5, 1.5)}
                <circle cx="26" cy="14.5" r="4.8" fill="none" stroke="#0f172a" stroke-width="2.2" opacity="0.85"/>
                <circle cx="26" cy="36.5" r="4.8" fill="none" stroke="#0f172a" stroke-width="2.2" opacity="0.85"/>`),
            trailer: modernBodyShell(color, 'trailer', `
                M26 12 C21.5 12 18 14.5 17.2 18.5 L16.5 24.5 C16 27 16 29.5 16.5 32 L17.2 38.5
                C18 42.2 21.5 44.5 26 44.5 C30.5 44.5 34 42.2 34.8 38.5 L35.5 32 C36 29.5 36 27 35.5 24.5
                L34.8 18.5 C34 14.5 30.5 12 26 12 Z`, `
                ${vehicleGlassSvg(20, 17, 12, 7, 1.5)}
                ${wheelSvg(18, 38, 2.5)}${wheelSvg(34, 38, 2.5)}`),
            other: modernBodyShell(color, 'other', `
                M26 10 C21.5 10 18 12.8 17.2 16.8 L16.2 25.5 C15.8 27.5 15.8 29.5 16.2 31.5 L17.2 38.2
                C18 41.8 21.5 44.5 26 44.5 C30.5 44.5 34 41.8 34.8 38.2 L35.8 31.5 C36.2 29.5 36.2 27.5 35.8 25.5
                L34.8 16.8 C34 12.8 30.5 10 26 10 Z`, `
                <rect x="18.5" y="15.5" width="15" height="11" rx="2.5" fill="#ffffff" opacity="0.22"/>
                ${wheelSvg(18, 34, 2.3)}${wheelSvg(34, 34, 2.3)}`),
            taxi: modernBodyShell(color, 'taxi', `
                M26 7.2 C21.2 7.2 17.4 10.2 16.2 14.8 L14.8 20.8 C14.2 23 14.2 25.8 14.8 28 L16.2 37.2
                C17.4 41.4 21.2 44.2 26 44.2 C30.8 44.2 34.6 41.4 35.8 37.2 L37.2 28 C37.8 25.8 37.8 23 37.2 20.8
                L35.8 14.8 C34.6 10.2 30.8 7.2 26 7.2 Z`, `
                ${vehicleGlassSvg(19.5, 13.5, 13, 8.5, 2.2)}
                <rect x="18.5" y="24.5" width="15" height="5" rx="1.2" fill="#fbbf24" opacity="0.95"/>
                ${wheelSvg(18.2, 19.5)}${wheelSvg(33.8, 19.5)}
                ${wheelSvg(18.2, 35.5)}${wheelSvg(33.8, 35.5)}`),
            ambulance: modernBodyShell(color, 'ambulance', `
                M26 7.5 C21 7.5 17 10.5 16 15 L14.8 21.5 C14.2 24 14.2 27 14.8 29.5 L16 37.5
                C17 41.5 21 44.5 26 44.5 C31 44.5 35 41.5 36 37.5 L37.2 29.5 C37.8 27 37.8 24 37.2 21.5
                L36 15 C35 10.5 31 7.5 26 7.5 Z`, `
                ${vehicleGlassSvg(18.5, 11, 15, 8, 2)}
                <rect x="24" y="18" width="4" height="12" rx="0.8" fill="#ffffff" opacity="0.95"/>
                <rect x="20" y="22" width="12" height="4" rx="0.8" fill="#ffffff" opacity="0.95"/>
                ${wheelSvg(17.5, 18)}${wheelSvg(34.5, 18)}
                ${wheelSvg(17.5, 37)}${wheelSvg(34.5, 37)}`),
            police: modernBodyShell(color, 'police', `
                M26 7.2 C21.2 7.2 17.4 10.2 16.2 14.8 L14.8 20.8 C14.2 23 14.2 25.8 14.8 28 L16.2 37.2
                C17.4 41.4 21.2 44.2 26 44.2 C30.8 44.2 34.6 41.4 35.8 37.2 L37.2 28 C37.8 25.8 37.8 23 37.2 20.8
                L35.8 14.8 C34.6 10.2 30.8 7.2 26 7.2 Z`, `
                <rect x="17" y="8.5" width="18" height="4.5" rx="1.2" fill="#3b82f6" opacity="0.95"/>
                ${vehicleGlassSvg(19.5, 13.5, 13, 8.5, 2.2)}
                ${wheelSvg(18.2, 19.5)}${wheelSvg(33.8, 19.5)}
                ${wheelSvg(18.2, 35.5)}${wheelSvg(33.8, 35.5)}`),
            fire_truck: modernBodyShell(color, 'fire', `
                M26 7 C21 7 17.5 9.8 16.8 13.8 L16.2 18 L13.8 19.8 C12.8 20.5 12.2 22 12.2 24 L13 36.5
                C13.8 40.5 17.2 43.5 21.5 43.5 L30.5 43.5 C34.8 43.5 38.2 40.5 39 36.5 L39.8 24
                C39.8 22 39.2 20.5 38.2 19.8 L35.8 18 L35.2 13.8 C34.5 9.8 31 7 26 7 Z`, `
                ${vehicleGlassSvg(20, 9.5, 12, 7, 1.8)}
                <rect x="14" y="22" width="24" height="12" rx="1.5" fill="#ffffff" opacity="0.18"/>
                ${wheelSvg(17, 20)}${wheelSvg(35, 20)}
                ${wheelSvg(17, 37)}${wheelSvg(35, 37)}`),
            tractor: modernBodyShell(color, 'tractor', `
                M26 8 C22 8 19 10.5 18.2 14 L17.5 20 C17 22.5 17 25 17.5 27.5 L18.5 34
                C19.2 37.5 22 40 26 40 C30 40 32.8 37.5 33.5 34 L34.5 27.5 C35 25 35 22.5 34.5 20
                L33.8 14 C33 10.5 30 8 26 8 Z`, `
                ${vehicleGlassSvg(20.5, 10.5, 11, 6.5, 1.6)}
                <circle cx="18.5" cy="33.5" r="6.5" fill="none" stroke="#0f172a" stroke-width="2.4" opacity="0.82"/>
                <circle cx="33.5" cy="35.5" r="4.8" fill="none" stroke="#0f172a" stroke-width="2.2" opacity="0.82"/>`),
            crane: modernBodyShell(color, 'crane', `
                M18 24.5 L34 24.5 L34 40.5 L18 40.5 Z
                M24.5 8 L27.5 8 L27.5 24.5 L24.5 24.5 Z`, `
                <line x1="27.5" y1="10" x2="39" y2="18" stroke="${shadeColor(color, -18)}" stroke-width="2.8" stroke-linecap="round"/>
                <circle cx="16.5" cy="37.5" r="2.4" fill="#0f172a"/>
                <circle cx="35.5" cy="37.5" r="2.4" fill="#0f172a"/>`),
            boat: modernBodyShell(color, 'boat', `
                M11 28.5 C11 28.5 16 26.5 26 26.5 C36 26.5 41 28.5 41 28.5 L36.5 38.5 C34.5 40.5 30.5 41.5 26 41.5
                C21.5 41.5 17.5 40.5 15.5 38.5 Z`, `
                <rect x="24.5" y="14" width="3" height="14" rx="1" fill="#ffffff" opacity="0.75"/>
                <path d="M26 14 L33 21 L26 21 Z" fill="#ffffff" opacity="0.55"/>`),
            bicycle: modernBodyShell(color, 'bicycle', `
                M26 12.5 C24.5 12.5 23.5 13.5 23.2 15 L22.5 22 L26 28.5 L29.5 22 L29.2 15
                C28.9 13.5 27.5 12.5 26 12.5 Z`, `
                <circle cx="18" cy="31.5" r="7.2" fill="none" stroke="#0f172a" stroke-width="2.2" opacity="0.85"/>
                <circle cx="34" cy="31.5" r="7.2" fill="none" stroke="#0f172a" stroke-width="2.2" opacity="0.85"/>
                <path d="M18 31.5 L26 14 L34 31.5" fill="none" stroke="${shadeColor(color, -10)}" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round"/>`),
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

    const pinIconCache = Object.create(null);

    /**
     * Default teardrop map pin colored by vehicle status (tip-anchored).
     * Non-breaking addition — existing car-icon builders unchanged.
     */
    function pinIconFor(statusColor, googleMaps) {
        const g = googleMaps || global.google;
        if (!g?.maps) {
            return null;
        }

        const color = statusColor || STATE_COLORS.parked;
        if (pinIconCache[color]) {
            return pinIconCache[color];
        }

        const w = 36;
        const h = 48;
        const svg = `<svg xmlns="http://www.w3.org/2000/svg" width="${w}" height="${h}" viewBox="0 0 36 48">
            <defs>
                <filter id="gtPinShadow" x="-25%" y="-15%" width="150%" height="140%">
                    <feDropShadow dx="0" dy="2" stdDeviation="2.2" flood-color="#0f172a" flood-opacity="0.38"/>
                </filter>
            </defs>
            <g filter="url(#gtPinShadow)">
                <path d="M18 2 C10.3 2 4 9.2 4 17.5 C4 28.5 18 46 18 46 C18 46 32 28.5 32 17.5 C32 9.2 25.7 2 18 2 Z"
                      fill="${color}" stroke="#ffffff" stroke-width="2.2" stroke-linejoin="round"/>
                <circle cx="18" cy="17.5" r="6.5" fill="#ffffff" opacity="0.96"/>
            </g>
        </svg>`;

        const icon = {
            url: svgDataUrl(svg),
            scaledSize: new g.maps.Size(w, h),
            anchor: new g.maps.Point(w / 2, h),
            labelOrigin: new g.maps.Point(w / 2, 2),
        };
        pinIconCache[color] = icon;
        return icon;
    }

    function bodyOnlyVehicleSvg(color, heading, showDirection, vehicleType, options) {
        const opts = { ...DEFAULTS, ...options };
        const type = vehicleType || 'car';
        const body = vehicleBodySvgInner(type, color);
        const vehicleSize = opts.vehicleBodyPx;
        const pad = 8;
        const totalW = vehicleSize + pad * 2;
        const totalH = vehicleSize + pad * 2;
        const cx = totalW / 2;
        const cy = totalH / 2;
        const rotation = showDirection ? parseFloat(heading || 0) : 0;
        const bodyScale = vehicleSize / VIEWBOX;
        const bodyTransform = vehicleBodyTransform(rotation, cx, cy, bodyScale);
        const svg = `<svg xmlns="http://www.w3.org/2000/svg" width="${totalW}" height="${totalH}" viewBox="0 0 ${totalW} ${totalH}">
            <g transform="${bodyTransform}">${body}</g>
        </svg>`;
        return {
            url: svgDataUrl(svg),
            width: totalW,
            height: totalH,
            anchorX: cx,
            anchorY: cy,
        };
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

    function customIconFor(point, opts, google, sizeScale, style) {
        const url = opts.getCustomIconUrl?.(point);
        if (!url || style === 'pin') {
            return null;
        }
        const showDirection = opts.shouldShowDirection?.(point, opts.getState?.(point)) ?? true;
        const rotationEnabled = opts.getRotationEnabled?.(point) !== false;
        const heading = parseFloat(point?.heading || 0);
        const base = Math.round(64 * sizeScale);
        const icon = {
            url,
            scaledSize: new google.maps.Size(base, base),
            anchor: new google.maps.Point(base / 2, base / 2),
        };
        if (rotationEnabled && showDirection) {
            icon.meta = { flat: true, rotation: heading };
        }
        return icon;
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
            const style = opts.getMarkerStyle?.(point) || 'pin';
            const sizeScale = Number(opts.getMarkerSizeScale?.(point) ?? 1) || 1;
            const customUrl = opts.getCustomIconUrl?.(point);
            const sizedOpts = {
                ...opts,
                vehicleBodyPx: (opts.vehicleBodyPx || DEFAULTS.vehicleBodyPx) * sizeScale,
                displayScale: (opts.displayScale || DEFAULTS.displayScale) * sizeScale,
                maxIconWidth: (opts.maxIconWidth || DEFAULTS.maxIconWidth) * sizeScale,
            };
            const cacheKey = [
                identity.title,
                identity.plate || '',
                color,
                vehicleType,
                style,
                sizeScale,
                customUrl || '',
                Math.round(heading / step),
                showDirection ? 1 : 0,
                showLiveBadge ? 1 : 0,
                opts.getRotationEnabled?.(point) === false ? 0 : 1,
            ].join('|');

            if (cache[cacheKey]) {
                return cache[cacheKey];
            }

            if (customUrl) {
                const custom = customIconFor(point, opts, google, sizeScale, style);
                if (custom) {
                    cache[cacheKey] = custom;
                    return custom;
                }
            }

            if (style === 'pin') {
                const pin = pinIconFor(color, google);
                if (pin && sizeScale !== 1) {
                    const pw = Math.round(36 * sizeScale);
                    const ph = Math.round(48 * sizeScale);
                    return {
                        url: pin.url,
                        scaledSize: new google.maps.Size(pw, ph),
                        anchor: new google.maps.Point(Math.round(pw / 2), ph),
                        labelOrigin: new google.maps.Point(Math.round(pw / 2), 2),
                    };
                }
                cache[cacheKey] = pin;
                return pin;
            }

            const sized = style === 'body'
                ? bodyOnlyVehicleSvg(color, heading, showDirection, vehicleType, sizedOpts)
                : labeledVehicleSvg(identity, color, heading, showDirection, vehicleType, {
                    ...sizedOpts,
                    showLiveBadge,
                });
            const scale = Math.min(sizedOpts.maxIconWidth / sized.width, sizedOpts.displayScale);
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

    function createMarker(options) {
        const platform = global.GoogleMapsPlatform;
        if (platform?.createMarker) {
            return platform.createMarker({
                ...options,
                useClassicMarker: options.useClassicMarker === true,
            });
        }
        const google = options.google || global.google;
        return new google.maps.Marker({
            map: options.map ?? null,
            position: options.position,
            title: options.title,
            icon: options.icon,
            zIndex: options.zIndex,
            optimized: options.optimized,
        });
    }

    function applyMarkerIcon(marker, icon) {
        if (!marker || !icon) {
            return;
        }
        if (typeof marker.setIcon === 'function') {
            marker.setIcon(icon);
        }
        if (typeof marker.setFlat !== 'function') {
            return;
        }
        if (icon.meta) {
            marker.setFlat(!!icon.meta.flat);
            if (typeof marker.setRotation === 'function') {
                marker.setRotation(Number(icon.meta.rotation) || 0);
            }
        } else {
            marker.setFlat(false);
            if (typeof marker.setRotation === 'function') {
                marker.setRotation(0);
            }
        }
    }

    global.VehicleMarker = {
        SVG_CENTER,
        VIEWBOX,
        STATE_COLORS,
        DEFAULTS,
        vehicleBodySvgInner,
        labeledVehicleSvg,
        bodyOnlyVehicleSvg,
        pinIconFor,
        MARKER_SIZE_SCALES: { 50: 0.5, 75: 0.75, 100: 1, 125: 1.25, 150: 1.5, 200: 2, small: 0.75, medium: 1, large: 1.5 },
        resolveMarkerStyle(source) {
            if (this.resolveCustomIconUrl(source)) {
                return 'body';
            }
            return 'pin';
        },
        resolveMarkerSizeScale(source, mapRendering) {
            const size = String(source?.map_marker_size || source?.mapMarkerSize || '100').toLowerCase();
            const legacy = { small: '75', medium: '100', large: '150' };
            const normalized = legacy[size] || size;
            const scales = mapRendering?.marker_size_scales || { 50: 0.5, 75: 0.75, 100: 1, 125: 1.25, 150: 1.5, 200: 2 };
            return Number(scales[normalized] ?? source?.map_marker_size_scale ?? 1) || 1;
        },
        resolveRotationEnabled(source) {
            if (source?.map_icon_rotation_enabled === false || source?.mapIconRotationEnabled === false) {
                return false;
            }
            return true;
        },
        resolveCustomIconUrl(source) {
            const url = source?.map_custom_icon_url || source?.mapCustomIconUrl || null;
            const src = String(source?.map_icon_source || source?.mapIconSource || 'default').toLowerCase();
            return src === 'custom' && url ? url : null;
        },
        previewSvg(options) {
            const opts = { ...DEFAULTS, ...(options || {}) };
            const color = opts.color || STATE_COLORS.moving;
            const vehicleType = opts.vehicleType || 'car';
            const heading = opts.heading ?? 0;
            const style = opts.style || 'pin';
            const sizeScale = Number(opts.sizeScale ?? 1) || 1;
            const showDirection = opts.rotationEnabled !== false;
            const sizedOpts = {
                ...opts,
                vehicleBodyPx: (opts.vehicleBodyPx || DEFAULTS.vehicleBodyPx) * sizeScale,
                displayScale: (opts.displayScale || DEFAULTS.displayScale) * sizeScale,
            };
            if (style === 'pin') {
                return pinIconFor(color, global.google)?.url || '';
            }
            if (style === 'labeled') {
                return labeledVehicleSvg(
                    { title: opts.title || 'Preview', plate: opts.plate || '' },
                    color,
                    heading,
                    showDirection,
                    vehicleType,
                    sizedOpts,
                ).url;
            }
            return bodyOnlyVehicleSvg(color, heading, showDirection, vehicleType, sizedOpts).url;
        },
        createIconBuilder,
        createMarker,
        applyMarkerIcon,
        getPulseOverlayClass,
        createPulseController,
        stateColor(state) {
            return STATE_COLORS[state] || STATE_COLORS.parked;
        },
    };
})(typeof window !== 'undefined' ? window : globalThis);
