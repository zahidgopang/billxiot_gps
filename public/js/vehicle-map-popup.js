/**
 * Shared vehicle info popup for Google Maps (web + fleet views).
 * Shows odometer, plate, status+duration, altitude, angle, position, engine, commands.
 *
 * Fleet tracking uses a Map OverlayView host (reliable with AdvancedMarkerElement).
 * Single-device maps may use the classic InfoWindow.
 */
(function (global) {
    'use strict';

    function escHtml(value) {
        return String(value ?? '')
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/"/g, '&quot;');
    }

    function formatDuration(sec) {
        sec = Math.max(0, Math.round(parseFloat(sec) || 0));
        const h = Math.floor(sec / 3600);
        const m = Math.floor((sec % 3600) / 60);
        const s = sec % 60;
        const parts = [];
        if (h) parts.push(`${h} h`);
        if (h || m) parts.push(`${m} min`);
        parts.push(`${s} s`);
        return parts.join(' ');
    }

    function dash(i18n) {
        return i18n.dash || '—';
    }

    function row(label, valueHtml) {
        return `<div class="vehicle-map-popup__row"><strong>${escHtml(label)}</strong><span>${valueHtml}</span></div>`;
    }

    function odometerText(point, i18n) {
        if (point.odometer_km != null && point.odometer_km !== '') {
            return `${escHtml(point.odometer_km)} km`;
        }
        if (point.odometer != null && point.odometer !== '') {
            const km = Math.round((parseFloat(point.odometer) || 0) / 100) / 10;
            return `${km} km`;
        }
        return escHtml(dash(i18n));
    }

    function resolveStatusDurationSeconds(point) {
        if (point?.status_since) {
            const since = Date.parse(point.status_since);
            if (Number.isFinite(since)) {
                return Math.max(0, Math.round((Date.now() - since) / 1000));
            }
        }
        if (point?.status_duration_seconds != null) {
            const sec = Number(point.status_duration_seconds);
            if (Number.isFinite(sec)) {
                return Math.max(0, Math.round(sec));
            }
        }
        return null;
    }

    function statusText(point, i18n) {
        const label = point.status_label || point.status || point.motion_status || dash(i18n);
        return escHtml(label);
    }

    function durationText(point, i18n) {
        const dur = resolveStatusDurationSeconds(point);
        if (dur == null) {
            return escHtml(dash(i18n));
        }
        return escHtml(formatDuration(dur));
    }

    function statusColor(point, stateColors) {
        const key = point.status_key || 'offline';
        return stateColors?.[key] || '#64748b';
    }

    function ignitionText(point, i18n) {
        if (point.ignition == null) return escHtml(dash(i18n));
        return escHtml(point.ignition ? (i18n.ignitionOn || 'On') : (i18n.ignitionOff || 'Off'));
    }

    function positionText(point, i18n) {
        const lat = parseFloat(point.lat);
        const lng = parseFloat(point.lng);
        if (!Number.isFinite(lat) || !Number.isFinite(lng)) {
            return escHtml(dash(i18n));
        }
        const text = `${lat.toFixed(6)}, ${lng.toFixed(6)}`;
        const mapsUrl = `https://www.google.com/maps?q=${lat},${lng}`;
        return `<a class="vmp-pos-link" href="${mapsUrl}" target="_blank" rel="noopener">${escHtml(text)}</a>`;
    }

    function commandOptions(commandTypes, selectedValue = '') {
        if (!commandTypes) return '';
        const entries = Array.isArray(commandTypes)
            ? commandTypes.map((t) => [t, t])
            : Object.entries(commandTypes);
        const selected = String(selectedValue || '');
        return entries.map(([value, label]) => {
            const val = String(value);
            const isSelected = selected !== '' && val === selected ? ' selected' : '';
            return `<option value="${escHtml(val)}"${isSelected}>${escHtml(label)}</option>`;
        }).join('');
    }

    function htmlToText(html) {
        const tmp = document.createElement('div');
        tmp.innerHTML = String(html ?? '');
        return tmp.textContent || tmp.innerText || '';
    }

    /** Update live fields without rebuilding the popup (keeps select/button state). */
    function patchLiveFields(root, point, opts = {}) {
        if (!root || !point) return;
        const i18n = opts.i18n || {};
        const color = statusColor(point, opts.stateColors);
        const statusEl = root.querySelector('.vehicle-map-popup__status');
        if (statusEl) {
            statusEl.style.color = color;
            statusEl.textContent = htmlToText(statusText(point, i18n));
        }
        const durEl = root.querySelector('.vehicle-map-popup__dur');
        if (durEl) {
            durEl.textContent = htmlToText(durationText(point, i18n));
        }
        const titleEl = root.querySelector('.vehicle-map-popup__title');
        if (titleEl) {
            titleEl.textContent = point.title || point.name || point.vehicle_name || `Vehicle #${point.id || ''}`;
        }
        // Refresh odometer / angle / altitude / engine / position rows by label order is fragile;
        // patch known data attributes when present.
        root.querySelectorAll('[data-vmp-field]').forEach((el) => {
            const field = el.getAttribute('data-vmp-field');
            if (field === 'odometer') el.innerHTML = odometerText(point, i18n);
            if (field === 'altitude') {
                el.textContent = point.altitude != null
                    ? `${Math.round(parseFloat(point.altitude) || 0)} m`
                    : (i18n.dash || '—');
            }
            if (field === 'angle') {
                el.textContent = point.heading != null
                    ? `${Math.round(parseFloat(point.heading) || 0)}°`
                    : (i18n.dash || '—');
            }
            if (field === 'position') el.innerHTML = positionText(point, i18n);
            if (field === 'engine') el.innerHTML = ignitionText(point, i18n);
        });
    }

    function buildHtml(point, opts = {}) {
        const i18n = opts.i18n || {};
        const d = dash(i18n);
        const title = point.title || point.name || point.vehicle_name || `Vehicle #${point.id || ''}`;
        const plate = point.plate || point.vehicle_number || point.map_marker_plate || '';
        const angle = point.heading != null ? `${Math.round(parseFloat(point.heading) || 0)}°` : d;
        const alt = point.altitude != null ? `${Math.round(parseFloat(point.altitude) || 0)} m` : d;
        const color = statusColor(point, opts.stateColors);

        const selectedCmd = opts.selectedCommandType || '';
        const cmdSection = opts.commandsSendUrl && opts.commandTypes
            ? `<div class="vehicle-map-popup__commands">
                <select id="vehicleMapPopupCmdType" data-vehicle-map-cmd-type>${commandOptions(opts.commandTypes, selectedCmd)}</select>
                <button type="button" id="vehicleMapPopupCmdSend" data-vehicle-map-cmd-send>${escHtml(i18n.sendCommand || 'Send')}</button>
               </div>`
            : '';

        const detailLink = point.launch_map_url || opts.detailUrl
            ? `<a class="vehicle-map-popup__footer-link" href="${escHtml(point.launch_map_url || opts.detailUrl)}">${escHtml(i18n.openMap || 'Open full map')}</a>`
            : '';

        return `<div class="vehicle-map-popup">
            <div class="vehicle-map-popup__head">
                <div class="vehicle-map-popup__title">${escHtml(title)}</div>
                <button type="button" class="vehicle-map-popup__close" id="vehicleMapPopupClose" aria-label="${escHtml(i18n.close || 'Close')}">&times;</button>
            </div>
            <div class="vehicle-map-popup__body">
                ${plate ? row(i18n.plate || 'Plate', escHtml(plate)) : ''}
                ${row(i18n.odometer || 'Odometer', `<span data-vmp-field="odometer">${odometerText(point, i18n)}</span>`)}
                ${row(i18n.status || 'Status', `<span class="vehicle-map-popup__status" style="color:${escHtml(color)}">${statusText(point, i18n)}</span>`)}
                ${row(i18n.statusDuration || 'Duration', `<span class="vehicle-map-popup__dur">${durationText(point, i18n)}</span>`)}
                ${row(i18n.altitude || 'Altitude', `<span data-vmp-field="altitude">${escHtml(alt)}</span>`)}
                ${row(i18n.angle || 'Angle', `<span data-vmp-field="angle">${escHtml(angle)}</span>`)}
                ${row(i18n.position || 'Position', `<span data-vmp-field="position">${positionText(point, i18n)}</span>`)}
                ${row(i18n.engine || 'Engine', `<span data-vmp-field="engine">${ignitionText(point, i18n)}</span>`)}
                ${cmdSection}
                ${detailLink}
            </div>
        </div>`;
    }

    function resolveAnchor(anchor) {
        return global.GoogleMapsPlatform?.resolveNativeMarker?.(anchor)
            || anchor?._native
            || anchor
            || null;
    }

    function markerLatLng(anchor) {
        if (!anchor) return null;
        // Compat wrapper first (has getPosition).
        if (typeof anchor.getPosition === 'function') {
            const pos = anchor.getPosition();
            if (pos) {
                const lat = typeof pos.lat === 'function' ? pos.lat() : Number(pos.lat);
                const lng = typeof pos.lng === 'function' ? pos.lng() : Number(pos.lng);
                if (Number.isFinite(lat) && Number.isFinite(lng)) {
                    return { lat, lng };
                }
            }
        }
        const resolved = resolveAnchor(anchor);
        if (!resolved) return null;
        if (typeof resolved.getPosition === 'function') {
            const pos = resolved.getPosition();
            if (pos) {
                const lat = typeof pos.lat === 'function' ? pos.lat() : Number(pos.lat);
                const lng = typeof pos.lng === 'function' ? pos.lng() : Number(pos.lng);
                if (Number.isFinite(lat) && Number.isFinite(lng)) {
                    return { lat, lng };
                }
            }
        }
        // Native AdvancedMarkerElement uses .position
        const pos = resolved.position;
        if (!pos) return null;
        const lat = typeof pos.lat === 'function' ? pos.lat() : Number(pos.lat);
        const lng = typeof pos.lng === 'function' ? pos.lng() : Number(pos.lng);
        if (!Number.isFinite(lat) || !Number.isFinite(lng)) {
            return null;
        }
        return { lat, lng };
    }

    function resolvePopupPosition(point, anchor) {
        // Prefer explicit point coords from the click handler (most accurate).
        const lat = parseFloat(point?.lat);
        const lng = parseFloat(point?.lng);
        if (Number.isFinite(lat) && Number.isFinite(lng)) {
            return { lat, lng };
        }
        return markerLatLng(anchor);
    }

    function isAdvancedMarkerAnchor(anchor) {
        if (!anchor) {
            return false;
        }
        if (anchor._advanced || anchor._native) {
            return true;
        }
        const resolved = resolveAnchor(anchor);
        if (!resolved) {
            return false;
        }
        const Advanced = global.google?.maps?.marker?.AdvancedMarkerElement;
        if (Advanced && resolved instanceof Advanced) {
            return true;
        }
        // Native AdvancedMarkerElement exposes .content / .position, not classic getIcon().
        return resolved.content !== undefined
            && typeof resolved.getIcon !== 'function'
            && (resolved.position !== undefined || typeof resolved.getPosition === 'function');
    }

    function isClassicMarkerAnchor(anchor) {
        if (!anchor || isAdvancedMarkerAnchor(anchor)) {
            return false;
        }
        const resolved = resolveAnchor(anchor);
        return !!(resolved && typeof resolved.getPosition === 'function' && typeof resolved.getIcon === 'function');
    }

    function unionClientRect(rects) {
        let left = Infinity;
        let top = Infinity;
        let right = -Infinity;
        let bottom = -Infinity;
        let found = false;
        rects.forEach((r) => {
            if (!r || r.width < 1 || r.height < 1) return;
            found = true;
            left = Math.min(left, r.left);
            top = Math.min(top, r.top);
            right = Math.max(right, r.right);
            bottom = Math.max(bottom, r.bottom);
        });
        if (!found) return null;
        return {
            left,
            top,
            width: right - left,
            height: bottom - top,
        };
    }

    /** Visible marker DOM (AdvancedMarker content can be a zero-size anchor wrapper). */
    function markerVisualElement(anchor) {
        const resolved = resolveAnchor(anchor);
        if (!resolved) return null;

        const candidates = [
            resolved.element,
            resolved.content,
            anchor?.content,
        ].filter((node) => node instanceof HTMLElement);

        for (const el of candidates) {
            const rect = el.getBoundingClientRect();
            if (rect.width >= 2 && rect.height >= 2) {
                return el;
            }
            const parts = [...el.querySelectorAll('img, svg, div, span')]
                .map((node) => node.getBoundingClientRect())
                .filter((r) => r.width > 0 && r.height > 0);
            const union = unionClientRect(parts);
            if (union) {
                return el;
            }
        }

        return null;
    }

    /**
     * Pixel of the marker click/GPS pivot relative to the overlay pane.
     * Prefer the icon/img center (AdvancedMarkers are center-anchored on lat/lng).
     */
    function markerDomPixel(anchor, mapDiv, paneEl) {
        const el = markerVisualElement(anchor);
        if (!el || !mapDiv) return null;

        const origin = paneEl?.getBoundingClientRect() || mapDiv.getBoundingClientRect();
        // Prefer the actual vehicle image — labels above the icon inflate the box and
        // push the popup far away from the click point.
        const iconEl = el.querySelector?.('img, svg') || null;
        let rect = iconEl?.getBoundingClientRect?.() || null;
        if (!rect || rect.width < 2 || rect.height < 2) {
            rect = el.getBoundingClientRect();
        }
        if (!rect || rect.width < 2 || rect.height < 2) {
            const parts = [...el.querySelectorAll('img, svg, div, span')]
                .map((node) => node.getBoundingClientRect())
                .filter((r) => r.width > 0 && r.height > 0);
            rect = unionClientRect(parts);
            if (!rect) return null;
        }

        return {
            x: rect.left + rect.width / 2 - origin.left,
            // Center Y matches the GPS/click pivot for body markers.
            y: rect.top + rect.height / 2 - origin.top,
        };
    }

    function classicMarkerTopPixel(anchor, projection, g) {
        if (!anchor || anchor._advanced) return null;
        const resolved = resolveAnchor(anchor);
        if (!resolved || typeof resolved.getPosition !== 'function') return null;
        const pos = resolved.getPosition();
        if (!pos) return null;
        const lat = typeof pos.lat === 'function' ? pos.lat() : Number(pos.lat);
        const lng = typeof pos.lng === 'function' ? pos.lng() : Number(pos.lng);
        const pt = latLngToPixel(projection, lat, lng, g);
        if (!pt) return null;

        if (typeof resolved.getIcon !== 'function') {
            return pt;
        }

        const icon = resolved.getIcon();
        if (!icon || typeof icon !== 'object') {
            return pt;
        }

        let anchorY = 0;
        const ss = icon.scaledSize;
        if (icon.anchor) {
            const a = icon.anchor;
            anchorY = typeof a.y === 'number' ? a.y : (typeof a.getY === 'function' ? a.getY() : 0);
        } else if (ss) {
            const h = typeof ss.height === 'number' ? ss.height : (typeof ss.getHeight === 'function' ? ss.getHeight() : 48);
            anchorY = h / 2;
        }

        pt.y -= anchorY;
        return pt;
    }

    /**
     * Pixel relative to the map container (getDiv), not OverlayView floatPane.
     * floatPane/divPixel drifts vs AdvancedMarkers when zoomed in.
     */
    function resolvePopupPixel({ anchor, position, projection, map, g }) {
        const mapDiv = map?.getDiv?.();
        if (!mapDiv) return null;

        // 1) Exact GPS/click lat-lng in container pixels (stable at every zoom).
        if (position && projection) {
            const gpsPt = latLngToPixel(projection, position.lat, position.lng, g);
            if (gpsPt && Number.isFinite(gpsPt.x) && Number.isFinite(gpsPt.y)) {
                return { x: gpsPt.x, y: gpsPt.y };
            }
        }

        // 2) Visual marker center vs mapDiv (AdvancedMarker DOM).
        const domPt = markerDomPixel(anchor, mapDiv, mapDiv);
        if (domPt) {
            return domPt;
        }

        // 3) Marker lat/lng if point coords were missing.
        const fromMarker = markerLatLng(anchor);
        if (fromMarker && projection) {
            const gpsPt = latLngToPixel(projection, fromMarker.lat, fromMarker.lng, g);
            if (gpsPt) {
                return { x: gpsPt.x, y: gpsPt.y };
            }
        }

        return null;
    }

    function latLngToPixel(projection, lat, lng, g) {
        const latLng = new g.maps.LatLng(lat, lng);
        if (typeof projection.fromLatLngToContainerPixel === 'function') {
            return projection.fromLatLngToContainerPixel(latLng);
        }
        if (typeof projection.fromLatLngToDivPixel === 'function') {
            return projection.fromLatLngToDivPixel(latLng);
        }
        return null;
    }

    function layoutPopupElement(el, pt, mapDiv) {
        if (!el || !pt || !mapDiv) return;
        const mapW = mapDiv.clientWidth || mapDiv.offsetWidth || 0;
        const mapH = mapDiv.clientHeight || mapDiv.offsetHeight || 0;
        const popupW = el.offsetWidth || Math.min(340, mapW > 0 ? mapW - 24 : 340);
        const popupH = el.offsetHeight || 260;
        const margin = 8;
        const markerGap = 6;

        let x = pt.x;
        let y = pt.y;
        // Bottom-center of the card sits on the GPS/click point.
        let transform = `translate(-50%, calc(-100% - ${markerGap}px))`;

        if (mapH > 0 && y < popupH + margin + markerGap) {
            transform = `translate(-50%, ${markerGap}px)`;
        }

        // Soft edge clamp — keep tip near the marker; only shift when clipped.
        if (mapW > 0) {
            const half = popupW / 2;
            if (x < half + margin) x = half + margin;
            else if (x > mapW - half - margin) x = mapW - half - margin;
        }

        if (getComputedStyle(mapDiv).position === 'static') {
            mapDiv.style.position = 'relative';
        }

        el.style.position = 'absolute';
        el.style.display = 'block';
        el.style.left = `${Math.round(x)}px`;
        el.style.top = `${Math.round(y)}px`;
        el.style.transform = transform;
        el.style.margin = '0';
        el.style.right = 'auto';
        el.style.bottom = 'auto';
        el.style.zIndex = '1000000';
        el.style.pointerEvents = 'auto';
        el.style.willChange = 'left, top, transform';
    }

    function schedulePopupRedraw(redraw) {
        if (typeof redraw !== 'function') return;
        redraw();
        global.requestAnimationFrame(redraw);
        global.setTimeout(redraw, 0);
        global.setTimeout(redraw, 48);
        global.setTimeout(redraw, 120);
    }

    function openInfoWindow(iw, map, point, anchor) {
        const lat = parseFloat(point.lat);
        const lng = parseFloat(point.lng);
        const resolved = resolveAnchor(anchor);
        const isAdvanced = !!anchor?._advanced;

        if (resolved) {
            if (isAdvanced) {
                if (!resolved.map && map) {
                    try { resolved.map = map; } catch (_) { /* ignore */ }
                }
                if (resolved.map) {
                    try {
                        iw.open({ map, anchor: resolved });
                        return true;
                    } catch (_) { /* fall through */ }
                }
            } else if (typeof resolved.getPosition === 'function') {
                try {
                    iw.open({ map, anchor: resolved });
                    return true;
                } catch (_) { /* fall through */ }
            }
        }

        if (!Number.isFinite(lat) || !Number.isFinite(lng)) {
            return false;
        }

        iw.setPosition({ lat, lng });
        try {
            iw.open({ map });
            return true;
        } catch (_) {
            try {
                iw.open(map);
                return true;
            } catch (_2) {
                return false;
            }
        }
    }

    function createHostAnchor(map, selector, googleMaps) {
        const g = googleMaps || global.google;
        const el = typeof selector === 'string' ? document.querySelector(selector) : selector;
        if (!map || !el || !g?.maps?.OverlayView) {
            return null;
        }

        const state = {
            position: null,
            anchor: null,
            visible: false,
        };

        class HostAnchor extends g.maps.OverlayView {
            onAdd() {
                this.draw();
            }

            draw() {
                if (!state.visible || !state.position) {
                    el.classList.remove('is-open');
                    el.style.display = 'none';
                    return;
                }
                const projection = this.getProjection();
                if (!projection) {
                    global.requestAnimationFrame(() => this.draw());
                    return;
                }
                const mapDiv = map.getDiv?.();
                const pt = resolvePopupPixel({
                    anchor: state.anchor,
                    position: state.position,
                    projection,
                    map,
                    g,
                });
                if (!pt || !mapDiv) return;
                layoutPopupElement(el, pt, mapDiv);
                el.classList.add('is-open');
            }

            onRemove() {
                el.classList.remove('is-open');
                el.style.display = 'none';
            }
        }

        const overlay = new HostAnchor();
        overlay.setMap(map);

        const mapListeners = [];
        const redrawHost = () => overlay.draw();
        ['bounds_changed', 'zoom_changed', 'center_changed', 'idle'].forEach((ev) => {
            mapListeners.push(g.maps.event.addListener(map, ev, redrawHost));
        });
        global.addEventListener('map-sidebar-toggled', redrawHost);
        global.addEventListener('resize', redrawHost);

        return {
            map,
            setContent(html) {
                el.innerHTML = html;
            },
            setPosition(pos) {
                if (!pos) {
                    state.position = null;
                } else {
                    state.position = {
                        lat: parseFloat(pos.lat),
                        lng: parseFloat(pos.lng),
                    };
                }
                overlay.draw();
            },
            setAnchor(anchor) {
                state.anchor = anchor || null;
                overlay.draw();
            },
            show() {
                state.visible = true;
                schedulePopupRedraw(() => overlay.draw());
            },
            hide() {
                state.visible = false;
                el.classList.remove('is-open');
                el.style.display = 'none';
            },
            isOpen() {
                return state.visible && el.classList.contains('is-open');
            },
            getElement() {
                return el;
            },
            destroy() {
                mapListeners.forEach((l) => g.maps.event.removeListener(l));
                global.removeEventListener('map-sidebar-toggled', redrawHost);
                global.removeEventListener('resize', redrawHost);
                overlay.setMap(null);
                el.innerHTML = '';
                el.classList.remove('is-open');
                el.style.display = 'none';
                state.anchor = null;
            },
            _overlay: overlay,
        };
    }

    function createMapOverlayHost(map, googleMaps) {
        const g = googleMaps || global.google;
        if (!g?.maps?.OverlayView || !map) {
            return null;
        }

        const mapDiv = map.getDiv?.();
        if (!mapDiv) {
            return null;
        }

        const host = {
            map,
            position: null,
            anchor: null,
            visible: false,
            _pendingHtml: '',
            _overlay: null,
            _el: null,
            _listeners: [],
        };

        class PopupOverlay extends g.maps.OverlayView {
            onAdd() {
                // Mount on the map container (not floatPane). AdvancedMarkers + zoom
                // transform floatPane differently, which misplaces popups when zoomed in.
                const div = document.createElement('div');
                div.className = 'vehicle-map-popup-overlay';
                div.setAttribute('role', 'dialog');
                // Stop map close/drag, but never preventDefault — selects need native UI.
                ['click', 'mousedown', 'mouseup', 'pointerdown', 'pointerup', 'dblclick', 'contextmenu', 'wheel', 'touchstart', 'touchend'].forEach((ev) => {
                    div.addEventListener(ev, (e) => {
                        e.stopPropagation();
                    }, { passive: true });
                });
                host._el = div;
                if (host._pendingHtml) {
                    host._el.innerHTML = host._pendingHtml;
                }
                if (getComputedStyle(mapDiv).position === 'static') {
                    mapDiv.style.position = 'relative';
                }
                mapDiv.appendChild(div);
                if (host.visible) {
                    this.draw();
                }
            }

            draw() {
                if (!host._el || !host.position || !host.visible) {
                    if (host._el) host._el.style.display = 'none';
                    return;
                }
                const projection = this.getProjection();
                if (!projection) {
                    global.requestAnimationFrame(() => this.draw());
                    return;
                }
                const pt = resolvePopupPixel({
                    anchor: host.anchor,
                    position: host.position,
                    projection,
                    map: host.map,
                    g,
                });
                if (!pt) return;
                layoutPopupElement(host._el, pt, mapDiv);
            }

            onRemove() {
                host._detachMapListeners();
                host._el?.remove();
                host._el = null;
            }
        }

        host._overlay = new PopupOverlay();
        host._overlay.setMap(map);

        host._detachMapListeners = () => {
            host._listeners.forEach((l) => g.maps.event.removeListener(l));
            host._listeners = [];
        };

        host._attachMapListeners = () => {
            host._detachMapListeners();
            const redraw = () => host._overlay?.draw();
            [
                'bounds_changed',
                'zoom_changed',
                'center_changed',
                'projection_changed',
                'idle',
                'drag',
                'dragend',
            ].forEach((ev) => {
                host._listeners.push(g.maps.event.addListener(map, ev, redraw));
            });
        };
        host._attachMapListeners();

        host.setContent = (html) => {
            host._pendingHtml = html;
            if (host._el) host._el.innerHTML = html;
        };

        host.setPosition = (pos) => {
            if (!pos) {
                host.position = null;
            } else {
                host.position = {
                    lat: parseFloat(pos.lat),
                    lng: parseFloat(pos.lng),
                };
            }
            host._overlay?.draw();
        };

        host.setAnchor = (anchor) => {
            host.anchor = anchor || null;
            host._overlay?.draw();
        };

        host.show = () => {
            host.visible = true;
            schedulePopupRedraw(() => host._overlay?.draw());
        };

        host.hide = () => {
            host.visible = false;
            if (host._el) host._el.style.display = 'none';
        };

        host.isOpen = () => host.visible && !!host._el && host._el.style.display !== 'none';

        host.destroy = () => {
            host.hide();
            host.anchor = null;
            host._detachMapListeners();
            host._overlay?.setMap(null);
            host._overlay = null;
        };

        return host;
    }

    class VehicleMapPopup {
        constructor(options = {}) {
            this.opts = options;
            this.infoWindow = null;
            this.overlayHost = null;
            this.hostAnchor = null;
            this.openId = null;
            this.currentPoint = null;
            this._anchor = null;
            this._tickTimer = null;
            this._selectedCommandType = '';
            this._lastOpenId = null;
            this._useHost = !!options.hostElement;
            this._useOverlay = !this._useHost
                && options.mapOverlay !== false
                && (options.mapOverlay === true || !!global.GoogleMapsPlatform?.canUseAdvancedMarkers?.());
        }

        ensureHost(map) {
            if (!this._useHost || !map) return null;
            if (!this.hostAnchor || this.hostAnchor.map !== map) {
                this.hostAnchor?.destroy();
                this.hostAnchor = createHostAnchor(map, this.opts.hostElement, this.opts.googleMaps || global.google);
            }
            return this.hostAnchor;
        }

        ensureWindow() {
            const g = this.opts.googleMaps || global.google;
            if (!this.infoWindow && g?.maps) {
                this.infoWindow = new g.maps.InfoWindow({
                    maxWidth: 340,
                    pixelOffset: new g.maps.Size(0, 0),
                    disableAutoPan: false,
                });
                this.infoWindow.addListener('closeclick', () => this.close());
                g.maps.event.addListener(this.infoWindow, 'domready', () => this._wireDom());
            }
            return this.infoWindow;
        }

        ensureOverlay(map) {
            if (!this._useOverlay || !map) return null;
            if (!this.overlayHost || this.overlayHost.map !== map) {
                this.overlayHost?.destroy();
                this.overlayHost = createMapOverlayHost(map, this.opts.googleMaps || global.google);
            }
            return this.overlayHost;
        }

        open(point, anchor) {
            const map = typeof this.opts.getMap === 'function' ? this.opts.getMap() : this.opts.map;
            if (!map || !point) return;

            global.GoogleMapsPlatform?.runAfterMarkerClick?.();

            this._anchor = anchor || null;
            const position = resolvePopupPosition(point, anchor);
            if (!position) return;

            this.currentPoint = { ...point, lat: position.lat, lng: position.lng };
            this.openId = point.id ?? null;
            // New vehicle → clear remembered command; same vehicle keeps last choice.
            if (this._lastOpenId != null && Number(this._lastOpenId) !== Number(this.openId)) {
                this._selectedCommandType = '';
            }
            this._lastOpenId = this.openId;
            const html = buildHtml(this.currentPoint, {
                ...this.opts,
                selectedCommandType: this._selectedCommandType || '',
            });

            const host = this.ensureHost(map);
            if (host) {
                this.infoWindow?.close();
                this.overlayHost?.hide();
                host.setContent(html);
                host.setAnchor(anchor);
                host.setPosition(position);
                host.show();
                this._wireDom(host.getElement());
                this.opts.onOpen?.(this.currentPoint);
                this._startDurationTick();
                return;
            }

            // Prefer GPS-anchored overlay (exact click point). InfoWindow tip offsets drift
            // when AdvancedMarkers / multiple vehicles are on the map.
            const overlay = this.ensureOverlay(map);
            if (overlay) {
                this.infoWindow?.close();
                overlay.setContent(html);
                overlay.setAnchor(anchor);
                overlay.setPosition(position);
                overlay.show();
                this._wireDom(overlay._el);
                this.opts.onOpen?.(this.currentPoint);
                this._startDurationTick();
                return;
            }

            if (isClassicMarkerAnchor(anchor) && !isAdvancedMarkerAnchor(anchor)) {
                const iw = this.ensureWindow();
                iw.setContent(html);
                const tryOpen = () => {
                    if (!openInfoWindow(iw, map, this.currentPoint, anchor)) return false;
                    this._wireDom();
                    this.opts.onOpen?.(this.currentPoint);
                    this._startDurationTick();
                    return true;
                };
                if (!tryOpen()) {
                    global.requestAnimationFrame(() => tryOpen());
                }
                return;
            }

            const iw = this.ensureWindow();
            iw.setContent(html);
            const tryOpen = () => {
                if (!openInfoWindow(iw, map, this.currentPoint, anchor)) return false;
                this._wireDom();
                this.opts.onOpen?.(this.currentPoint);
                this._startDurationTick();
                return true;
            };
            if (!tryOpen()) {
                global.requestAnimationFrame(() => tryOpen());
            }
        }

        _popupRoot() {
            if (this.hostAnchor?.isOpen()) return this.hostAnchor.getElement();
            if (this.overlayHost?.isOpen()) return this.overlayHost._el;
            return document.getElementById('vehicleMapPopupClose')?.closest('.vehicle-map-popup')
                || document.querySelector('.vehicle-map-popup-overlay .vehicle-map-popup')
                || null;
        }

        _readSelectedCommand(root = null) {
            const el = (root || this._popupRoot())?.querySelector?.('[data-vehicle-map-cmd-type], #vehicleMapPopupCmdType');
            return el?.value || this._selectedCommandType || '';
        }

        _rememberSelectedCommand(root = null) {
            const value = this._readSelectedCommand(root);
            if (value) this._selectedCommandType = value;
            return value;
        }

        _refreshLiveContent(point) {
            const root = this._popupRoot();
            if (!root) return false;
            this._rememberSelectedCommand(root);
            patchLiveFields(root, point, this.opts);
            return true;
        }

        update(point) {
            if (this.openId != null && point.id != null && Number(this.openId) !== Number(point.id)) {
                return;
            }
            if (!this.isOpen()) return;

            const position = resolvePopupPosition(point, this._anchor) || {
                lat: parseFloat(point.lat),
                lng: parseFloat(point.lng),
            };
            this.currentPoint = { ...point, lat: position.lat, lng: position.lng };

            // Patch in place — full HTML rebuild resets the command <select>.
            if (this._refreshLiveContent(this.currentPoint)) {
                if (this.hostAnchor?.isOpen()) {
                    this.hostAnchor.setAnchor?.(this._anchor);
                    if (Number.isFinite(position.lat) && Number.isFinite(position.lng)) {
                        this.hostAnchor.setPosition(position);
                    }
                } else if (this.overlayHost?.isOpen()) {
                    this.overlayHost.setAnchor?.(this._anchor);
                    if (Number.isFinite(position.lat) && Number.isFinite(position.lng)) {
                        this.overlayHost.setPosition(position);
                    }
                } else if (this.infoWindow?.getMap() && Number.isFinite(position.lat) && Number.isFinite(position.lng)) {
                    this.infoWindow.setPosition(position);
                }
                return;
            }

            // Fallback rebuild (first paint / InfoWindow without root).
            const html = buildHtml(this.currentPoint, {
                ...this.opts,
                selectedCommandType: this._selectedCommandType || '',
            });
            if (this.infoWindow?.getMap()) {
                this.infoWindow.setContent(html);
                if (isClassicMarkerAnchor(this._anchor) && !isAdvancedMarkerAnchor(this._anchor)) {
                    const map = typeof this.opts.getMap === 'function' ? this.opts.getMap() : this.opts.map;
                    openInfoWindow(this.infoWindow, map, this.currentPoint, this._anchor);
                } else if (Number.isFinite(position.lat) && Number.isFinite(position.lng)) {
                    this.infoWindow.setPosition(position);
                }
                this._wireDom();
            }
        }

        close() {
            this._stopDurationTick();
            this._rememberSelectedCommand();
            this.openId = null;
            this.currentPoint = null;
            this._anchor = null;
            this.hostAnchor?.hide();
            this.overlayHost?.hide();
            this.infoWindow?.close();
            this.opts.onClose?.();
        }

        isOpen() {
            return this.isOpenFor(this.openId);
        }

        _startDurationTick() {
            this._stopDurationTick();
            const point = this.currentPoint;
            if (!point || (point.status_since == null && point.status_duration_seconds == null)) {
                return;
            }
            this._tickTimer = global.setInterval(() => {
                if (!this.isOpen() || !this.currentPoint) {
                    this._stopDurationTick();
                    return;
                }
                // Never rebuild HTML here — that resets the command select to the first option.
                this._refreshLiveContent(this.currentPoint);
            }, 1000);
        }

        _stopDurationTick() {
            if (this._tickTimer != null) {
                global.clearInterval(this._tickTimer);
                this._tickTimer = null;
            }
        }

        isOpenFor(id) {
            if (this.openId == null || Number(this.openId) !== Number(id)) {
                return false;
            }
            if (this.hostAnchor?.isOpen()) return true;
            if (this.overlayHost?.isOpen()) return true;
            return !!this.infoWindow?.getMap();
        }

        _wireDom(rootEl) {
            const root = rootEl || this._popupRoot();
            if (!root) return;

            const closeBtn = root.querySelector('#vehicleMapPopupClose');
            if (closeBtn) {
                closeBtn.onclick = (e) => {
                    e.preventDefault();
                    e.stopPropagation();
                    this.close();
                };
            }

            const selectEl = root.querySelector('[data-vehicle-map-cmd-type], #vehicleMapPopupCmdType');
            if (selectEl) {
                if (this._selectedCommandType) {
                    const has = [...selectEl.options].some((o) => o.value === this._selectedCommandType);
                    if (has) selectEl.value = this._selectedCommandType;
                }
                selectEl.onchange = (e) => {
                    e.stopPropagation();
                    this._selectedCommandType = selectEl.value || '';
                };
                selectEl.onmousedown = (e) => e.stopPropagation();
                selectEl.onpointerdown = (e) => e.stopPropagation();
                selectEl.onclick = (e) => e.stopPropagation();
            }

            const sendBtn = root.querySelector('[data-vehicle-map-cmd-send], #vehicleMapPopupCmdSend');
            if (sendBtn) {
                sendBtn.onclick = (e) => {
                    e.preventDefault();
                    e.stopPropagation();
                    this._sendCommand();
                };
                sendBtn.onmousedown = (e) => e.stopPropagation();
            }
        }

        async _sendCommand() {
            const url = this.opts.commandsSendUrl;
            const point = this.currentPoint;
            const deviceId = point?.id;
            const root = this._popupRoot() || document;
            const type = this._rememberSelectedCommand(root);
            const btn = root.querySelector?.('[data-vehicle-map-cmd-send], #vehicleMapPopupCmdSend')
                || document.getElementById('vehicleMapPopupCmdSend');
            if (!url || !deviceId) {
                this._notifyCommand('warning', this.opts.i18n?.cmdFailed || 'Commands unavailable');
                return;
            }
            if (!type) {
                this._notifyCommand('warning', this.opts.i18n?.cmdFailed || 'Select a command first');
                return;
            }

            if (typeof this.opts.onSendCommand === 'function') {
                this.opts.onSendCommand(deviceId, type, '', btn);
                return;
            }

            btn?.setAttribute('disabled', 'disabled');
            try {
                const res = await fetch(url, {
                    method: 'POST',
                    credentials: 'same-origin',
                    headers: {
                        'Content-Type': 'application/json',
                        Accept: 'application/json',
                        'X-CSRF-TOKEN': this.opts.csrfToken || '',
                    },
                    body: JSON.stringify({ device_id: deviceId, type, data: '' }),
                });
                const out = await res.json().catch(() => ({}));
                const ok = res.ok && out.success;
                const msg = out.message || (ok ? (this.opts.i18n?.cmdSent || 'Command queued') : (this.opts.i18n?.cmdFailed || 'Failed'));
                this._notifyCommand(ok ? 'success' : 'error', msg);
            } catch (_) {
                this._notifyCommand('error', this.opts.i18n?.cmdFailed || 'Failed');
            } finally {
                btn?.removeAttribute('disabled');
            }
        }

        _notifyCommand(icon, msg) {
            if (global.Swal) {
                global.Swal.fire({
                    icon,
                    title: msg,
                    timer: icon === 'success' ? 2200 : undefined,
                    showConfirmButton: icon !== 'success',
                });
            } else {
                alert(msg);
            }
        }
    }

    VehicleMapPopup.buildHtml = buildHtml;
    VehicleMapPopup.formatDuration = formatDuration;
    global.VehicleMapPopup = VehicleMapPopup;
}(window));
