/**
 * Google Maps platform helpers — async loader + AdvancedMarkerElement compat layer.
 * Fixes console warnings: loading=async + deprecated google.maps.Marker.
 */
(function (global) {
    'use strict';

    let loadPromise = null;

    function readConfig(options) {
        const cfg = global.GOOGLE_MAPS_CONFIG || {};
        const key = String(options?.key || options?.googleMapsKey || cfg.key || '').trim();
        const mapId = String(options?.mapId || options?.googleMapsMapId || cfg.mapId || '').trim();
        const libraries = options?.libraries || ['marker'];
        return { key, mapId, libraries: [...new Set(libraries)] };
    }

    function ensureAsyncUrl(url) {
        try {
            const parsed = new URL(url, global.location.href);
            if (!parsed.searchParams.has('loading')) {
                parsed.searchParams.set('loading', 'async');
            }
            if (!parsed.searchParams.has('v')) {
                parsed.searchParams.set('v', 'weekly');
            }
            return parsed.toString();
        } catch (_) {
            if (url.includes('loading=async')) {
                return url;
            }
            return `${url}${url.includes('?') ? '&' : '?'}loading=async&v=weekly`;
        }
    }

    let suppressMapClickUntil = 0;

    function runAfterMarkerClick(fn) {
        suppressMapClickUntil = Date.now() + 400;
        if (typeof fn === 'function') {
            fn();
        }
    }

    function shouldSuppressMapClick() {
        return Date.now() < suppressMapClickUntil;
    }

    function loadMapsApi(options) {
        const { key, mapId, libraries } = readConfig(options);
        if (!key) {
            return Promise.reject(new Error('Missing Google Maps API key'));
        }

        if (loadPromise) {
            return loadPromise.then(async (result) => {
                if (global.google?.maps?.importLibrary) {
                    await Promise.all(libraries.map((lib) => global.google.maps.importLibrary(lib)));
                }
                return { ...result, mapId: mapId || result.mapId };
            });
        }

        loadPromise = new Promise((resolve, reject) => {
            const finish = async () => {
                if (!global.google?.maps) {
                    reject(new Error('Google Maps failed to initialize'));
                    return;
                }
                try {
                    if (global.google.maps.importLibrary) {
                        await Promise.all(libraries.map((lib) => global.google.maps.importLibrary(lib)));
                    }
                } catch (err) {
                    reject(err);
                    return;
                }
                resolve({ google: global.google, mapId });
            };

            if (global.google?.maps?.Map) {
                finish();
                return;
            }

            const cbName = '__billxiotMapsReady';
            global[cbName] = () => {
                try { delete global[cbName]; } catch (_) { global[cbName] = undefined; }
                finish();
            };

            const script = document.createElement('script');
            script.async = true;
            script.defer = true;
            script.onerror = () => reject(new Error('Google Maps script failed to load'));

            const params = new URLSearchParams({
                key,
                loading: 'async',
                v: 'weekly',
                callback: cbName,
            });
            if (libraries.length) {
                params.set('libraries', libraries.join(','));
            }
            script.src = `https://maps.googleapis.com/maps/api/js?${params.toString()}`;
            document.head.appendChild(script);
        });

        return loadPromise;
    }

    function mapOptions(base, mapId) {
        const opts = { ...(base || {}) };
        const id = String(mapId || readConfig({}).mapId || '').trim();
        if (id) {
            opts.mapId = id;
        }
        return opts;
    }

    function getMapId(options) {
        return readConfig(options).mapId;
    }

    function canUseAdvancedMarkers(mapId) {
        const id = String(mapId || readConfig({}).mapId || '').trim();
        return !!(id && global.google?.maps?.marker?.AdvancedMarkerElement);
    }

    function scaledSizePx(icon) {
        const ss = icon?.scaledSize;
        if (!ss) {
            return { w: 48, h: 48 };
        }
        if (typeof ss.width === 'number') {
            return { w: ss.width, h: ss.height };
        }
        if (typeof ss.getWidth === 'function') {
            return { w: ss.getWidth(), h: ss.getHeight() };
        }
        return { w: 48, h: 48 };
    }

    function anchorPx(icon, w, h) {
        const anchor = icon?.anchor;
        if (!anchor) {
            return { x: w / 2, y: h / 2 };
        }
        if (typeof anchor.x === 'number') {
            return { x: anchor.x, y: anchor.y };
        }
        if (typeof anchor.getX === 'function') {
            return { x: anchor.getX(), y: anchor.getY() };
        }
        return { x: w / 2, y: h / 2 };
    }

    function isSymbolIcon(icon) {
        return !!(icon?.path != null || icon?.path === 0);
    }

    function symbolIconToContent(icon) {
        const scale = Number(icon.scale) || 8;
        const size = Math.max(8, scale * 2);

        return createAnchoredContent((inner) => {
            inner.style.left = `${-size / 2}px`;
            inner.style.top = `${-size / 2}px`;

            const dot = document.createElement('div');
            dot.className = 'gmap-adv-marker gmap-adv-marker--symbol';
            dot.style.width = `${size}px`;
            dot.style.height = `${size}px`;
            dot.style.borderRadius = '50%';
            dot.style.background = icon.fillColor || '#ef4444';
            dot.style.opacity = icon.fillOpacity == null ? 1 : icon.fillOpacity;
            dot.style.border = `${icon.strokeWeight || 2}px solid ${icon.strokeColor || '#ffffff'}`;
            dot.style.boxSizing = 'border-box';
            inner.appendChild(dot);
        });
    }

    function createAnchoredContent(buildInner) {
        const outer = document.createElement('div');
        outer.className = 'gmap-adv-marker-anchor';
        outer.style.position = 'relative';
        outer.style.width = '0';
        outer.style.height = '0';
        outer.style.overflow = 'visible';
        outer.style.lineHeight = '0';
        outer.style.pointerEvents = 'auto';

        const inner = document.createElement('div');
        inner.style.position = 'absolute';
        buildInner(inner, outer);
        outer.appendChild(inner);
        return outer;
    }

    function urlIconToContent(icon, state) {
        return compositeMarkerContent(icon, null, state || { flat: false, rotation: 0 }, false);
    }

    function iconToContent(icon, state) {
        if (!icon) {
            return undefined;
        }
        if (isSymbolIcon(icon)) {
            return symbolIconToContent(icon);
        }
        if (!icon.url) {
            return undefined;
        }
        return urlIconToContent(icon, state || { flat: false, rotation: 0 });
    }

    function normalizeLabel(label) {
        if (!label) {
            return null;
        }
        if (typeof label === 'string') {
            return { text: label, color: '#1f2937', fontSize: '12px', fontWeight: '600', className: 'tc-mk-label' };
        }
        if (label.text) {
            return {
                text: String(label.text),
                color: label.color || '#ffffff',
                fontSize: label.fontSize || '12px',
                fontWeight: label.fontWeight || '600',
                className: label.className || 'tc-mk-label',
                backgroundColor: label.backgroundColor || null,
            };
        }
        return null;
    }

    /** Vertical space the label badge occupies above the icon anchor (px). */
    function estimateLabelStackPx(labelOpt) {
        if (!labelOpt?.text) {
            return 0;
        }
        const fontSize = parseFloat(String(labelOpt.fontSize || '12')) || 12;
        const lineHeight = 1.25;
        const padY = 4;
        const marginBottom = 2;
        return Math.ceil(fontSize * lineHeight + padY + marginBottom);
    }

    function appendLabelElement(parent, label, focused) {
        const labelOpt = normalizeLabel(label);
        if (!labelOpt?.text) {
            return null;
        }
        const badge = document.createElement('div');
        badge.className = labelOpt.className;
        badge.textContent = labelOpt.text;
        badge.style.fontSize = labelOpt.fontSize;
        badge.style.fontWeight = labelOpt.fontWeight;
        badge.style.marginBottom = '2px';
        badge.style.pointerEvents = 'none';
        if (labelOpt.backgroundColor) {
            badge.style.background = labelOpt.backgroundColor;
            badge.style.color = '#ffffff';
            badge.style.border = '1px solid rgba(255,255,255,0.5)';
        } else {
            badge.style.color = labelOpt.color || '#1f2937';
        }
        if (focused) {
            badge.classList.add('tc-mk-label--focused');
        }
        parent.appendChild(badge);
        return badge;
    }

    function appendIconElement(parent, icon, state) {
        if (!icon?.url) {
            return null;
        }
        const { w, h } = scaledSizePx(icon);
        const img = document.createElement('img');
        img.src = icon.url;
        img.alt = '';
        img.draggable = false;
        img.style.width = `${w}px`;
        img.style.height = `${h}px`;
        img.style.display = 'block';
        img.style.userSelect = 'none';

        const rotation = Number(icon.meta?.rotation ?? state.rotation ?? 0) || 0;
        const flat = !!(icon.meta?.flat ?? state.flat);
        if (flat && rotation) {
            img.style.transformOrigin = 'center center';
            img.style.transform = `rotate(${rotation}deg)`;
        }

        parent.appendChild(img);
        return img;
    }

    function compositeMarkerContent(icon, label, state, focused) {
        const labelOpt = normalizeLabel(label);
        const hasIcon = !!(icon && (icon.url || isSymbolIcon(icon)));
        if (!hasIcon && !labelOpt) {
            return undefined;
        }
        if (hasIcon && isSymbolIcon(icon)) {
            return symbolIconToContent(icon);
        }

        const { w, h } = scaledSizePx(icon || {});
        const { x: ax, y: ay } = anchorPx(icon || {}, w, h);
        const labelStack = labelOpt && hasIcon ? estimateLabelStackPx(labelOpt) : 0;

        return createAnchoredContent((inner, outer) => {
            inner.style.left = `${-ax}px`;
            inner.style.top = `${-(ay + labelStack)}px`;
            inner.style.display = 'flex';
            inner.style.flexDirection = 'column';
            inner.style.alignItems = 'center';
            inner.style.pointerEvents = 'none';
            if (focused) {
                outer.classList.add('gmap-adv-marker-wrap--focused');
            }

            if (labelOpt) {
                outer._labelEl = appendLabelElement(inner, labelOpt, focused);
            }
            if (hasIcon) {
                outer._img = appendIconElement(inner, icon, state || { flat: false, rotation: 0 });
                if (outer._img) {
                    outer._img.style.flexShrink = '0';
                }
            }
        });
    }

    function updateContentRotation(content, rotation, flat) {
        if (!content?._img) {
            return;
        }
        if (flat && rotation) {
            content._img.style.transform = `rotate(${rotation}deg)`;
        } else {
            content._img.style.transform = '';
        }
    }

    function labelToContent(label) {
        return compositeMarkerContent(null, label, { flat: false, rotation: 0 }, false);
    }

    function mapAdvancedMarkerEvent(event) {
        switch (event) {
            case 'click':
                return 'gmp-click';
            case 'drag':
                return 'gmp-drag';
            case 'dragend':
                return 'gmp-dragend';
            case 'dragstart':
                return 'gmp-dragstart';
            default:
                return event;
        }
    }

    function addAdvancedMarkerListener(native, event, fn) {
        const mapped = mapAdvancedMarkerEvent(event);
        const handler = (e) => {
            if (mapped === 'gmp-click' && typeof e?.stop === 'function') {
                e.stop();
            }
            fn(e);
        };

        if (typeof native.addEventListener === 'function') {
            native.addEventListener(mapped, handler);
            return {
                remove: () => native.removeEventListener(mapped, handler),
            };
        }

        if (typeof native.addListener === 'function') {
            return native.addListener(mapped, handler);
        }

        return null;
    }

    function resolveNativeMarker(marker) {
        if (!marker) {
            return null;
        }
        if (marker._native) {
            return marker._native;
        }
        return marker;
    }

    function createAdvancedMarker(options) {
        const AdvancedMarkerElement = global.google.maps.marker.AdvancedMarkerElement;
        const ctx = {
            icon: options.icon || null,
            label: options.label || null,
            state: { flat: false, rotation: 0 },
            focused: false,
            content: null,
        };

        function rebuildContent() {
            ctx.content = compositeMarkerContent(ctx.icon, ctx.label, ctx.state, ctx.focused);
            native.content = ctx.content;
            updateContentRotation(ctx.content, ctx.state.rotation, ctx.state.flat);
        }

        const native = new AdvancedMarkerElement({
            map: options.map ?? null,
            position: options.position,
            title: options.title,
            content: undefined,
            zIndex: options.zIndex,
            gmpClickable: options.gmpClickable !== false,
            gmpDraggable: !!options.draggable,
        });

        rebuildContent();

        const compat = {
            _native: native,
            _advanced: true,
            setMap(map) {
                native.map = map;
            },
            setPosition(pos) {
                if (!pos) {
                    native.position = null;
                    return;
                }
                const lat = typeof pos.lat === 'function' ? pos.lat() : Number(pos.lat);
                const lng = typeof pos.lng === 'function' ? pos.lng() : Number(pos.lng);
                if (!Number.isFinite(lat) || !Number.isFinite(lng)) return;
                native.position = { lat, lng };
            },
            getPosition() {
                const pos = native.position;
                if (!pos) {
                    return null;
                }
                if (typeof pos.lat === 'function') {
                    return pos;
                }
                return {
                    lat: () => Number(pos.lat),
                    lng: () => Number(pos.lng),
                };
            },
            setIcon(icon) {
                if (!icon) {
                    return;
                }
                ctx.icon = icon;
                if (icon.meta) {
                    ctx.state.flat = !!icon.meta.flat;
                    ctx.state.rotation = Number(icon.meta.rotation) || 0;
                }
                rebuildContent();
            },
            setTitle(title) {
                native.title = title;
            },
            setLabel(label) {
                ctx.label = label;
                rebuildContent();
            },
            setFocused(focused) {
                ctx.focused = !!focused;
                rebuildContent();
            },
            setFlat(flat) {
                ctx.state.flat = !!flat;
                updateContentRotation(ctx.content, ctx.state.rotation, ctx.state.flat);
            },
            setRotation(rotation) {
                ctx.state.rotation = Number(rotation) || 0;
                updateContentRotation(ctx.content, ctx.state.rotation, ctx.state.flat);
            },
            setZIndex(zIndex) {
                native.zIndex = zIndex;
            },
            addListener(event, fn) {
                return addAdvancedMarkerListener(native, event, fn);
            },
            getAnchor() {
                return native;
            },
            getMap() {
                return native.map ?? null;
            },
        };

        if (options.icon?.meta) {
            compat.setFlat(!!options.icon.meta.flat);
            compat.setRotation(Number(options.icon.meta.rotation) || 0);
        }

        return compat;
    }

    function createMarker(options) {
        const g = options.google || global.google;
        const mapId = options.mapId || readConfig({}).mapId;

        if (canUseAdvancedMarkers(mapId)) {
            return createAdvancedMarker(options);
        }

        return new g.maps.Marker({
            map: options.map ?? null,
            position: options.position,
            title: options.title,
            icon: options.icon,
            label: options.label,
            draggable: options.draggable,
            zIndex: options.zIndex,
            optimized: options.optimized,
        });
    }

    global.GoogleMapsPlatform = {
        load: loadMapsApi,
        mapOptions,
        getMapId,
        canUseAdvancedMarkers,
        ensureAsyncUrl,
        createMarker,
        resolveNativeMarker,
        runAfterMarkerClick,
        shouldSuppressMapClick,
    };
})(typeof window !== 'undefined' ? window : globalThis);
