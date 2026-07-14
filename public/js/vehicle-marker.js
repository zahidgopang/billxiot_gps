/**
 * Fleet vehicle marker primitives — icon SVG, GPS anchor, heading, screen-fixed pulse.
 * Consumed by FleetMapRenderer (fleet-map-renderer.js) on all map modes.
 */
(function (global) {
    'use strict';

    const SVG_CENTER = (global.VehicleIconShapes && global.VehicleIconShapes.CENTER) || 32;
    const VIEWBOX = (global.VehicleIconShapes && global.VehicleIconShapes.VIEWBOX) || 64;

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
        // Fine buckets keep baked SVG cache small while staying visually smooth.
        headingStepDeg: 2,
    };

    function normalizeHeading(deg) {
        const n = Number(deg);
        if (!Number.isFinite(n)) {
            return 0;
        }
        return ((n % 360) + 360) % 360;
    }

    function shortestPathHeading(from, to) {
        const a = normalizeHeading(from);
        const b = normalizeHeading(to);
        let delta = ((b - a + 540) % 360) - 180;
        return a + delta;
    }

    function lerpHeading(from, to, t) {
        const start = normalizeHeading(from);
        const end = shortestPathHeading(start, to);
        return normalizeHeading(start + (end - start) * Math.max(0, Math.min(1, t)));
    }

    /**
     * Final map rotation = GPS heading + per-icon artwork offset.
     * Offset examples: north-up=0, east-facing=-90, south-up=180, west-facing=90.
     */
    function finalRotation(heading, offset, enabled) {
        if (enabled === false) {
            return 0;
        }
        return normalizeHeading((Number(heading) || 0) + (Number(offset) || 0));
    }

    function resolveIconRotationOffset(source) {
        if (!source) {
            return 0;
        }
        const direct = source.map_icon_rotation_offset ?? source.mapIconRotationOffset
            ?? source.rotation_offset ?? source.rotationOffset;
        if (direct != null && direct !== '') {
            return Number(direct) || 0;
        }
        const type = String(source.vehicle_type || source.vehicleType || '').toLowerCase();
        const path = source.map_builtin_icon_path || source.mapBuiltinIconPath || '';
        const reg = global.VehicleIconRegistry || global.__vehicleIconRegistry || null;
        if (reg?.icons?.[type]?.rotation_offset != null) {
            return Number(reg.icons[type].rotation_offset) || 0;
        }
        if (path && reg?.icons) {
            const hit = Object.values(reg.icons).find((icon) => icon?.path === path);
            if (hit?.rotation_offset != null) {
                return Number(hit.rotation_offset) || 0;
            }
        }
        return 0;
    }

    /**
     * Flat image marker for AdvancedMarkerElement.
     * Uses the real image URL (not a data-URL SVG wrapping an external <image>),
     * because browsers block external resources inside SVG data URLs — that made
     * zoomed-in vehicle icons invisible while cluster bubbles still worked.
     * Rotation = heading + offset, applied via CSS transform-origin: center.
     */
    function flatRotatedImageIcon(url, heading, offset, google, sizeScale, enabled) {
        if (!url || !google?.maps) {
            return null;
        }
        const rotation = finalRotation(heading, offset, enabled !== false);
        const base = Math.max(24, Math.round(64 * (Number(sizeScale) || 1)));
        const fallback = global.BuiltinMapIcons?.fallbackUrl?.()
            || '/icons/builtin/Vehicles/car.svg';
        return {
            url,
            scaledSize: new google.maps.Size(base, base),
            anchor: new google.maps.Point(base / 2, base / 2),
            meta: {
                flat: true,
                rotation,
                baked: false,
                heading: rotation,
                sourceUrl: url,
                fallbackUrl: fallback,
            },
        };
    }

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
        const shapes = global.VehicleIconShapes;
        if (shapes?.renderInner) {
            return shapes.renderInner(vehicleType, color);
        }
        // Fallback if shapes script not loaded
        return modernBodyShell(color, 'car', `
            M26 7.2 C21.2 7.2 17.4 10.2 16.2 14.8 L14.8 20.8 C14.2 23 14.2 25.8 14.8 28 L16.2 37.2
            C17.4 41.4 21.2 44.2 26 44.2 C30.8 44.2 34.6 41.4 35.8 37.2 L37.2 28 C37.8 25.8 37.8 23 37.2 20.8
            L35.8 14.8 C34.6 10.2 30.8 7.2 26 7.2 Z`, `
            ${vehicleGlassSvg(19.5, 13.5, 13, 8.5, 2.2)}
            ${wheelSvg(18.2, 19.5)}${wheelSvg(33.8, 19.5)}
            ${wheelSvg(18.2, 35.5)}${wheelSvg(33.8, 35.5)}`);
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
        const offset = Number(opts.rotationOffset || 0) || 0;
        const rotation = showDirection ? finalRotation(heading, offset, true) : 0;
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
        const offset = Number(opts.rotationOffset || 0) || 0;
        const rotation = showDirection ? finalRotation(heading, offset, true) : 0;
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

    function flatImageIconFor(url, point, opts, google, sizeScale) {
        const fallback = opts.getFallbackIconUrl?.(point)
            || global.VehicleMarker?.resolveFallbackIconUrl?.(point)
            || global.BuiltinMapIcons?.fallbackUrl?.()
            || '/icons/builtin/Vehicles/car.svg';
        const resolvedUrl = url || fallback;
        if (!resolvedUrl) {
            return null;
        }
        const rotationEnabled = opts.getRotationEnabled?.(point) !== false;
        const heading = parseFloat(point?.heading || 0);
        const offset = opts.getRotationOffset?.(point)
            ?? resolveIconRotationOffset(point)
            ?? 0;
        return flatRotatedImageIcon(resolvedUrl, heading, offset, google, sizeScale, rotationEnabled);
    }

    function labeledFlatIconSvg(identity, iconUrl, heading, showDirection, options) {
        const opts = { ...DEFAULTS, ...options };
        const title = identity.title || 'Vehicle';
        const plate = identity.plate || '';
        const esc = (s) => String(s)
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/"/g, '&quot;');
        const escUrl = esc(iconUrl);

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
        const offset = Number(opts.rotationOffset || 0) || 0;
        const rotation = showDirection ? finalRotation(heading, offset, true) : 0;
        const imgX = cx - vehicleSize / 2;
        const imgY = vehicleTop;
        const liveBadge = opts.showLiveBadge
            ? `<circle cx="${pillX + 14}" cy="12" r="5" fill="#22c55e" stroke="#fff" stroke-width="1.5"/>
               <circle cx="${pillX + 14}" cy="12" r="5" fill="#22c55e" opacity="0.5"><animate attributeName="r" values="5;8;5" dur="1.2s" repeatCount="indefinite"/></circle>`
            : '';
        const imageTransform = showDirection
            ? `rotate(${rotation} ${cx} ${vehicleCenterY})`
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
            <image href="${escUrl}" xlink:href="${escUrl}" x="${imgX}" y="${imgY}" width="${vehicleSize}" height="${vehicleSize}" preserveAspectRatio="xMidYMid meet" transform="${imageTransform}"/>
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
        return flatImageIconFor(url, point, opts, google, sizeScale);
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
            const sizeScale = Number(opts.getMarkerSizeScale?.(point) ?? 1) || 1;
            const fallbackIconUrl = opts.getFallbackIconUrl?.(point)
                ?? global.VehicleMarker?.resolveFallbackIconUrl?.(point)
                ?? global.BuiltinMapIcons?.fallbackUrl?.()
                ?? '/icons/builtin/Vehicles/car.svg';
            const mapIconUrl = opts.getMapIconUrl?.(point)
                ?? global.VehicleMarker?.resolveMapIconUrl?.(point)
                ?? fallbackIconUrl;
            const customUrl = opts.getCustomIconUrl?.(point);
            const rotationOffset = opts.getRotationOffset?.(point)
                ?? resolveIconRotationOffset(point)
                ?? 0;
            const rotationEnabled = opts.getRotationEnabled?.(point) !== false;
            let style = opts.getMarkerStyle?.(point) || 'pin';
            // Selected library/custom icons always win over legacy status pins.
            if ((mapIconUrl || customUrl) && style === 'pin' && String(vehicleType || '').toLowerCase() !== 'pin_marker') {
                style = 'body';
            }
            const sizedOpts = {
                ...opts,
                vehicleBodyPx: (opts.vehicleBodyPx || DEFAULTS.vehicleBodyPx) * sizeScale,
                displayScale: (opts.displayScale || DEFAULTS.displayScale) * sizeScale,
                maxIconWidth: (opts.maxIconWidth || DEFAULTS.maxIconWidth) * sizeScale,
                rotationOffset,
            };
            const finalHeading = showDirection && rotationEnabled
                ? finalRotation(heading, rotationOffset, true)
                : 0;
            // Flat / CSS-rotated assets share one cache entry per appearance — heading
            // is applied via AdvancedMarker setRotation, not a new data-URL.
            const usesCssRotation = !!(mapIconUrl || customUrl)
                || (style === 'body');
            const cacheKey = [
                identity.title,
                identity.plate || '',
                color,
                vehicleType,
                style,
                sizeScale,
                customUrl || '',
                mapIconUrl || '',
                usesCssRotation ? 'css' : Math.round(finalHeading / step),
                rotationOffset,
                showDirection ? 1 : 0,
                showLiveBadge ? 1 : 0,
                rotationEnabled ? 1 : 0,
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

            // Never leave the map blank — prefer library/fallback URL over empty pin.
            if (!mapIconUrl && style !== 'pin') {
                const flatFallback = flatImageIconFor(fallbackIconUrl, point, {
                    ...opts,
                    getFallbackIconUrl: () => fallbackIconUrl,
                }, google, sizeScale);
                if (flatFallback) {
                    cache[cacheKey] = flatFallback;
                    return flatFallback;
                }
            }

            if (mapIconUrl && style !== 'pin') {
                // Always use the real image URL + CSS rotation. Do NOT wrap external
                // icons in SVG data-URLs (browsers block those <image> loads → blank markers).
                const flat = flatImageIconFor(mapIconUrl, point, opts, google, sizeScale);
                if (flat) {
                    cache[cacheKey] = flat;
                    return flat;
                }
            }

            // Only the explicit pin_marker type may use the status teardrop.
            // Everything else (including missing uploads) uses the builtin map icon.
            if (style === 'pin' && String(vehicleType || '').toLowerCase() === 'pin_marker') {
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

            if (mapIconUrl) {
                const flat = flatImageIconFor(mapIconUrl, point, opts, google, sizeScale);
                if (flat) {
                    cache[cacheKey] = flat;
                    return flat;
                }
            }

            const sized = style === 'body'
                ? bodyOnlyVehicleSvg(color, 0, false, vehicleType, sizedOpts)
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
                meta: style === 'body'
                    ? {
                        flat: true,
                        rotation: finalHeading,
                        baked: false,
                        heading: finalHeading,
                    }
                    : undefined,
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
        // Prefer pose-only rotation when the platform supports it; setIcon itself
        // skips DOM rebuild when the asset URL/size/anchor is unchanged.
        if (typeof marker.setIcon === 'function') {
            marker.setIcon(icon);
        }
        if (icon.meta) {
            if (typeof marker.setFlat === 'function') {
                marker.setFlat(!!icon.meta.flat);
            }
            if (typeof marker.setRotation === 'function') {
                marker.setRotation(Number(icon.meta.rotation) || 0);
            }
        } else if (typeof marker.setRotation === 'function') {
            if (typeof marker.setFlat === 'function') {
                marker.setFlat(false);
            }
            marker.setRotation(0);
        }
        ensureExternalIconLoads(marker, icon);
    }

    /**
     * Update only GPS position + CSS heading rotation (no AdvancedMarker DOM rebuild).
     * `rotationOffset` is artwork alignment (nose vs bitmap).
     */
    function applyMarkerPose(marker, lat, lng, heading, rotationOffset, rotationEnabled) {
        if (!marker) return;
        const rot = rotationEnabled === false
            ? 0
            : finalRotation(heading, rotationOffset || 0, true);
        if (typeof marker.setPose === 'function') {
            marker.setPose({ lat, lng }, rot);
            return;
        }
        if (typeof marker.setPosition === 'function') {
            marker.setPosition({ lat, lng });
        }
        if (typeof marker.setRotation === 'function') {
            marker.setRotation(rot);
        }
    }

    const iconLoadCache = Object.create(null);

    function clearIconLoadCache() {
        Object.keys(iconLoadCache).forEach((key) => {
            delete iconLoadCache[key];
        });
    }

    function ensureExternalIconLoads(marker, icon) {
        const sourceUrl = icon?.meta?.sourceUrl || (!String(icon?.url || '').startsWith('data:') ? icon?.url : null);
        const fallbackUrl = icon?.meta?.fallbackUrl
            || global.BuiltinMapIcons?.fallbackUrl?.()
            || '/icons/builtin/Vehicles/car.svg';
        if (!marker || !sourceUrl || !fallbackUrl || sourceUrl === fallbackUrl) {
            return;
        }
        if (iconLoadCache[sourceUrl] === true) {
            return;
        }
        if (iconLoadCache[sourceUrl] === false) {
            swapMarkerToFallback(marker, icon, fallbackUrl);
            return;
        }
        const probe = new Image();
        probe.onload = () => {
            iconLoadCache[sourceUrl] = true;
        };
        probe.onerror = () => {
            iconLoadCache[sourceUrl] = false;
            swapMarkerToFallback(marker, icon, fallbackUrl);
        };
        probe.src = sourceUrl;
    }

    function swapMarkerToFallback(marker, icon, fallbackUrl) {
        if (!marker || !fallbackUrl || !global.google?.maps) {
            return;
        }
        const heading = Number(icon?.meta?.heading || 0) || 0;
        const size = icon?.scaledSize?.width || 64;
        const sizeScale = Math.max(0.5, size / 64);
        const replacement = flatRotatedImageIcon(
            fallbackUrl,
            heading,
            0,
            global.google,
            sizeScale,
            true,
        );
        if (!replacement) {
            return;
        }
        // Mark fallback as known-good so we don't recurse on probe failure.
        iconLoadCache[fallbackUrl] = true;
        if (typeof marker.setIcon === 'function') {
            marker.setIcon(replacement);
        }
        if (typeof marker.setFlat === 'function') {
            marker.setFlat(true);
        }
        if (typeof marker.setRotation === 'function') {
            marker.setRotation(Number(replacement.meta?.rotation) || 0);
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
            const style = String(source?.map_marker_style || source?.mapMarkerStyle || '').toLowerCase();
            const type = String(source?.vehicle_type || source?.vehicleType || '').toLowerCase();
            // Status teardrop only for explicit pin_marker type.
            if (type === 'pin_marker' || style === 'pin') {
                // If a library/custom icon URL exists, prefer the selected icon over the pin.
                if (style === 'pin' && this.resolveMapIconUrl(source) && type !== 'pin_marker') {
                    return 'body';
                }
                return 'pin';
            }
            if (style === 'labeled') {
                return 'labeled';
            }
            // Built-in library icons render as body markers centered on GPS.
            return 'body';
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
        resolveIconRotationOffset,
        finalRotation,
        normalizeHeading,
        lerpHeading,
        shortestPathHeading,
        resolveCustomIconUrl(source) {
            const url = source?.map_custom_icon_url || source?.mapCustomIconUrl || null;
            const src = String(source?.map_icon_source || source?.mapIconSource || 'default').toLowerCase();
            return src === 'custom' && url ? url : null;
        },
        resolveFallbackIconUrl(source) {
            return source?.map_fallback_icon_url
                || source?.mapFallbackIconUrl
                || global.BuiltinMapIcons?.fallbackUrl?.()
                || global.BuiltinMapIcons?.urlForType?.('car')
                || '/icons/builtin/Vehicles/car.svg';
        },
        resolveMapIconUrl(source) {
            const fallback = this.resolveFallbackIconUrl(source);
            const custom = this.resolveCustomIconUrl(source);
            if (custom) {
                return custom;
            }
            // Prefer path/type over a possibly-stale map_builtin_icon_url left from a prior selection.
            const path = source?.map_builtin_icon_path || source?.mapBuiltinIconPath || null;
            if (path && global.BuiltinMapIcons?.urlForPath) {
                const fromPath = global.BuiltinMapIcons.urlForPath(path);
                if (fromPath) {
                    return fromPath;
                }
            }
            const type = source?.vehicle_type || source?.vehicleType || 'car';
            if (type && global.BuiltinMapIcons?.urlForType) {
                const fromType = global.BuiltinMapIcons.urlForType(type);
                if (fromType) {
                    return fromType;
                }
            }
            const builtin = source?.map_builtin_icon_url || source?.mapBuiltinIconUrl || null;
            if (builtin) {
                return builtin;
            }
            // Shared types without a registry entry must not invent a missing Shared/*.svg URL.
            if (String(type).toLowerCase().startsWith('shared_')) {
                return fallback;
            }
            return fallback;
        },
        previewSvg(options) {
            const opts = { ...DEFAULTS, ...(options || {}) };
            const vehicleType = opts.vehicleType || 'car';
            const heading = opts.heading ?? 0;
            const style = opts.style || 'body';
            const sizeScale = Number(opts.sizeScale ?? 1) || 1;
            const showDirection = opts.rotationEnabled !== false;
            const offset = Number(opts.rotationOffset ?? 0) || 0;
            const iconUrl = opts.iconUrl
                || global.BuiltinMapIcons?.urlForType?.(vehicleType)
                || null;
            const sizedOpts = {
                ...opts,
                vehicleBodyPx: (opts.vehicleBodyPx || DEFAULTS.vehicleBodyPx) * sizeScale,
                displayScale: (opts.displayScale || DEFAULTS.displayScale) * sizeScale,
                rotationOffset: offset,
            };
            if (style === 'pin') {
                const color = opts.color || STATE_COLORS.moving;
                return pinIconFor(color, global.google)?.url || '';
            }
            if (!iconUrl) {
                return '';
            }
            if (style === 'labeled') {
                return labeledFlatIconSvg(
                    { title: opts.title || 'Preview', plate: opts.plate || '' },
                    iconUrl,
                    heading,
                    showDirection,
                    sizedOpts,
                ).url;
            }
            const base = Math.round(64 * sizeScale);
            const pad = 8;
            const total = base + pad * 2;
            const cx = total / 2;
            const rotation = showDirection ? finalRotation(heading, offset, true) : 0;
            const escUrl = String(iconUrl).replace(/"/g, '&quot;');
            const svg = `<svg xmlns="http://www.w3.org/2000/svg" width="${total}" height="${total}" viewBox="0 0 ${total} ${total}">
                <g transform="rotate(${rotation} ${cx} ${cx})">
                    <image href="${escUrl}" xlink:href="${escUrl}" x="${pad}" y="${pad}" width="${base}" height="${base}" preserveAspectRatio="xMidYMid meet"/>
                </g>
            </svg>`;
            return svgDataUrl(svg);
        },
        createIconBuilder,
        createMarker,
        applyMarkerIcon,
        applyMarkerPose,
        clearIconLoadCache,
        getPulseOverlayClass,
        createPulseController,
        stateColor(state) {
            return STATE_COLORS[state] || STATE_COLORS.parked;
        },
    };
})(typeof window !== 'undefined' ? window : globalThis);
