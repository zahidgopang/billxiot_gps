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
        const wrapper = document.createElement('div');
        wrapper.className = 'gmap-adv-marker gmap-adv-marker--symbol';
        wrapper.style.width = `${size}px`;
        wrapper.style.height = `${size}px`;
        wrapper.style.borderRadius = '50%';
        wrapper.style.background = icon.fillColor || '#ef4444';
        wrapper.style.opacity = icon.fillOpacity == null ? 1 : icon.fillOpacity;
        wrapper.style.border = `${icon.strokeWeight || 2}px solid ${icon.strokeColor || '#ffffff'}`;
        wrapper.style.boxSizing = 'border-box';
        wrapper.style.transform = `translate(-${size / 2}px, -${size / 2}px)`;
        return wrapper;
    }

    function urlIconToContent(icon, state) {
        const { w, h } = scaledSizePx(icon);
        const { x: ax, y: ay } = anchorPx(icon, w, h);
        const wrapper = document.createElement('div');
        wrapper.className = 'gmap-adv-marker';
        wrapper.style.lineHeight = '0';
        wrapper.style.transform = `translate(-${ax}px, -${ay}px)`;

        const img = document.createElement('img');
        img.src = icon.url;
        img.alt = '';
        img.draggable = false;
        img.style.width = `${w}px`;
        img.style.height = `${h}px`;
        img.style.display = 'block';
        img.style.userSelect = 'none';
        img.style.pointerEvents = 'none';

        const rotation = Number(icon.meta?.rotation ?? state.rotation ?? 0) || 0;
        const flat = !!(icon.meta?.flat ?? state.flat);
        if (flat && rotation) {
            img.style.transformOrigin = 'center center';
            img.style.transform = `rotate(${rotation}deg)`;
        }

        wrapper.appendChild(img);
        wrapper._img = img;
        return wrapper;
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
        const text = typeof label === 'string' ? label : label?.text;
        if (!text) {
            return undefined;
        }
        const color = (typeof label === 'object' && label.color) ? label.color : '#ffffff';
        const wrapper = document.createElement('div');
        wrapper.className = 'gmap-adv-marker gmap-adv-marker--label';
        wrapper.textContent = text;
        wrapper.style.width = '28px';
        wrapper.style.height = '28px';
        wrapper.style.borderRadius = '50%';
        wrapper.style.background = '#2563eb';
        wrapper.style.color = color;
        wrapper.style.display = 'flex';
        wrapper.style.alignItems = 'center';
        wrapper.style.justifyContent = 'center';
        wrapper.style.fontWeight = (typeof label === 'object' && label.fontWeight) ? label.fontWeight : '700';
        wrapper.style.fontSize = '12px';
        wrapper.style.boxShadow = '0 1px 4px rgba(15,23,42,0.35)';
        wrapper.style.transform = 'translate(-14px, -14px)';
        return wrapper;
    }

    function createAdvancedMarker(options) {
        const AdvancedMarkerElement = global.google.maps.marker.AdvancedMarkerElement;
        const state = { flat: false, rotation: 0 };
        let content = options.label
            ? labelToContent(options.label)
            : iconToContent(options.icon, state);

        const native = new AdvancedMarkerElement({
            map: options.map ?? null,
            position: options.position,
            title: options.title,
            content,
            zIndex: options.zIndex,
            gmpDraggable: !!options.draggable,
        });

        const compat = {
            _native: native,
            _advanced: true,
            setMap(map) {
                native.map = map;
            },
            setPosition(pos) {
                native.position = pos;
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
                content = iconToContent(icon, state);
                native.content = content;
                if (icon.meta) {
                    compat.setFlat(!!icon.meta.flat);
                    compat.setRotation(Number(icon.meta.rotation) || 0);
                }
            },
            setTitle(title) {
                native.title = title;
            },
            setLabel() {
                // Advanced markers do not support setLabel — no-op.
            },
            setFlat(flat) {
                state.flat = !!flat;
                updateContentRotation(content, state.rotation, state.flat);
            },
            setRotation(rotation) {
                state.rotation = Number(rotation) || 0;
                updateContentRotation(content, state.rotation, state.flat);
            },
            addListener(event, fn) {
                return native.addListener(event, fn);
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
    };
})(typeof window !== 'undefined' ? window : globalThis);
