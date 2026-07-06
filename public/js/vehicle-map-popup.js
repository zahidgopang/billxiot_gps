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

    function commandOptions(commandTypes) {
        if (!commandTypes) return '';
        const entries = Array.isArray(commandTypes)
            ? commandTypes.map((t) => [t, t])
            : Object.entries(commandTypes);
        return entries.map(([value, label]) =>
            `<option value="${escHtml(value)}">${escHtml(label)}</option>`,
        ).join('');
    }

    function buildHtml(point, opts = {}) {
        const i18n = opts.i18n || {};
        const d = dash(i18n);
        const title = point.title || point.name || point.vehicle_name || `Vehicle #${point.id || ''}`;
        const plate = point.plate || point.vehicle_number || point.map_marker_plate || '';
        const angle = point.heading != null ? `${Math.round(parseFloat(point.heading) || 0)}°` : d;
        const alt = point.altitude != null ? `${Math.round(parseFloat(point.altitude) || 0)} m` : d;
        const color = statusColor(point, opts.stateColors);

        const cmdSection = opts.commandsSendUrl && opts.commandTypes
            ? `<div class="vehicle-map-popup__commands">
                <select id="vehicleMapPopupCmdType">${commandOptions(opts.commandTypes)}</select>
                <button type="button" id="vehicleMapPopupCmdSend">${escHtml(i18n.sendCommand || 'Send')}</button>
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
                ${row(i18n.odometer || 'Odometer', odometerText(point, i18n))}
                ${row(i18n.status || 'Status', `<span class="vehicle-map-popup__status" style="color:${escHtml(color)}">${statusText(point, i18n)}</span>`)}
                ${row(i18n.statusDuration || 'Duration', `<span class="vehicle-map-popup__dur">${durationText(point, i18n)}</span>`)}
                ${row(i18n.altitude || 'Altitude', escHtml(alt))}
                ${row(i18n.angle || 'Angle', escHtml(angle))}
                ${row(i18n.position || 'Position', positionText(point, i18n))}
                ${row(i18n.engine || 'Engine', ignitionText(point, i18n))}
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
        const resolved = resolveAnchor(anchor);
        if (!resolved || typeof resolved.getPosition !== 'function') {
            return null;
        }
        const pos = resolved.getPosition();
        if (!pos) return null;
        const lat = typeof pos.lat === 'function' ? pos.lat() : Number(pos.lat);
        const lng = typeof pos.lng === 'function' ? pos.lng() : Number(pos.lng);
        if (!Number.isFinite(lat) || !Number.isFinite(lng)) {
            return null;
        }
        return { lat, lng };
    }

    function resolvePopupPosition(point, anchor) {
        const fromMarker = markerLatLng(anchor);
        if (fromMarker) {
            return fromMarker;
        }
        const lat = parseFloat(point?.lat);
        const lng = parseFloat(point?.lng);
        if (!Number.isFinite(lat) || !Number.isFinite(lng)) {
            return null;
        }
        return { lat, lng };
    }

    function isClassicMarkerAnchor(anchor) {
        if (!anchor || anchor._advanced) {
            return false;
        }
        const resolved = resolveAnchor(anchor);
        return !!(resolved && typeof resolved.getPosition === 'function');
    }

    function latLngToPixel(projection, lat, lng, g) {
        const latLng = new g.maps.LatLng(lat, lng);
        if (typeof projection.fromLatLngToContainerPixel === 'function') {
            return projection.fromLatLngToContainerPixel(latLng);
        }
        return projection.fromLatLngToDivPixel(latLng);
    }

    function layoutPopupElement(el, pt, mapDiv) {
        if (!el || !pt) return;
        const mapW = mapDiv?.offsetWidth || 0;
        const mapH = mapDiv?.offsetHeight || 0;
        const popupW = el.offsetWidth || Math.min(340, mapW > 0 ? mapW - 24 : 340);
        const popupH = el.offsetHeight || 260;
        const margin = 12;
        const markerGap = 18;

        let x = pt.x;
        let y = pt.y;
        let transform = `translate(-50%, calc(-100% - ${markerGap}px))`;

        if (mapH > 0 && pt.y < popupH + margin + markerGap) {
            transform = `translate(-50%, ${markerGap}px)`;
        }

        if (mapW > 0) {
            const half = popupW / 2 + margin;
            x = Math.max(half, Math.min(mapW - half, x));
        }

        el.style.position = 'absolute';
        el.style.display = 'block';
        el.style.left = `${x}px`;
        el.style.top = `${y}px`;
        el.style.transform = transform;
        el.style.margin = '0';
        el.style.right = 'auto';
        el.style.bottom = 'auto';
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
                const pt = latLngToPixel(
                    projection,
                    state.position.lat,
                    state.position.lng,
                    g,
                );
                if (!pt) return;
                layoutPopupElement(el, pt, map.getDiv?.());
                el.classList.add('is-open');
            }

            onRemove() {
                el.classList.remove('is-open');
                el.style.display = 'none';
            }
        }

        const overlay = new HostAnchor();
        overlay.setMap(map);

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
            show() {
                state.visible = true;
                overlay.draw();
                global.requestAnimationFrame(() => overlay.draw());
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
                overlay.setMap(null);
                el.innerHTML = '';
                el.classList.remove('is-open');
                el.style.display = 'none';
            },
            _overlay: overlay,
        };
    }

    function createMapOverlayHost(map, googleMaps) {
        const g = googleMaps || global.google;
        if (!g?.maps?.OverlayView || !map) {
            return null;
        }

        const host = {
            map,
            position: null,
            visible: false,
            _pendingHtml: '',
            _overlay: null,
            _el: null,
            _listeners: [],
        };

        class PopupOverlay extends g.maps.OverlayView {
            onAdd() {
                const div = document.createElement('div');
                div.className = 'vehicle-map-popup-overlay';
                div.setAttribute('role', 'dialog');
                div.addEventListener('click', (e) => e.stopPropagation());
                host._el = div;
                if (host._pendingHtml) {
                    host._el.innerHTML = host._pendingHtml;
                }
                const panes = this.getPanes();
                const pane = panes?.overlayMouseTarget || panes?.overlayLayer || panes?.floatPane;
                pane?.appendChild(div);
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
                const pt = latLngToPixel(
                    projection,
                    host.position.lat,
                    host.position.lng,
                    g,
                );
                if (!pt) return;
                layoutPopupElement(host._el, pt, host.map?.getDiv?.());
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
            ['bounds_changed', 'zoom_changed', 'center_changed', 'idle'].forEach((ev) => {
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

        host.show = () => {
            host.visible = true;
            const redraw = () => host._overlay?.draw();
            redraw();
            global.requestAnimationFrame(redraw);
            global.setTimeout(redraw, 0);
        };

        host.hide = () => {
            host.visible = false;
            if (host._el) host._el.style.display = 'none';
        };

        host.isOpen = () => host.visible && !!host._el;

        host.destroy = () => {
            host.hide();
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
            const html = buildHtml(this.currentPoint, this.opts);

            const host = this.ensureHost(map);
            if (host) {
                this.infoWindow?.close();
                this.overlayHost?.hide();
                host.setContent(html);
                host.setPosition(position);
                host.show();
                this._wireDom(host.getElement());
                this.opts.onOpen?.(this.currentPoint);
                this._startDurationTick();
                return;
            }

            if (isClassicMarkerAnchor(anchor)) {
                const iw = this.ensureWindow();
                this.overlayHost?.hide();
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

            const overlay = this.ensureOverlay(map);
            if (overlay) {
                this.infoWindow?.close();
                overlay.setContent(html);
                overlay.setPosition(position);
                overlay.show();
                this._wireDom(overlay._el);
                this.opts.onOpen?.(this.currentPoint);
                this._startDurationTick();
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
            const html = buildHtml(this.currentPoint, this.opts);

            if (this.hostAnchor?.isOpen()) {
                this.hostAnchor.setContent(html);
                if (Number.isFinite(position.lat) && Number.isFinite(position.lng)) {
                    this.hostAnchor.setPosition(position);
                }
                this._wireDom(this.hostAnchor.getElement());
                return;
            }

            if (this.overlayHost?.isOpen()) {
                this.overlayHost.setContent(html);
                if (Number.isFinite(position.lat) && Number.isFinite(position.lng)) {
                    this.overlayHost.setPosition(position);
                }
                this._wireDom(this.overlayHost._el);
                return;
            }

            if (!this.infoWindow?.getMap()) return;
            this.infoWindow.setContent(html);
            if (isClassicMarkerAnchor(this._anchor)) {
                const map = typeof this.opts.getMap === 'function' ? this.opts.getMap() : this.opts.map;
                openInfoWindow(this.infoWindow, map, this.currentPoint, this._anchor);
            }
            this._wireDom();
        }

        close() {
            this._stopDurationTick();
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
                const html = buildHtml(this.currentPoint, this.opts);
                if (this.hostAnchor?.isOpen()) {
                    this.hostAnchor.setContent(html);
                } else if (this.overlayHost?.isOpen()) {
                    this.overlayHost.setContent(html);
                } else if (this.infoWindow?.getMap()) {
                    this.infoWindow.setContent(html);
                }
                this._wireDom();
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
            const root = rootEl
                || (this.hostAnchor?.isOpen() ? this.hostAnchor.getElement() : null)
                || (this.overlayHost?.isOpen() ? this.overlayHost._el : null);
            const closeBtn = root
                ? root.querySelector('#vehicleMapPopupClose')
                : document.getElementById('vehicleMapPopupClose');
            if (closeBtn) {
                closeBtn.onclick = (e) => {
                    e.preventDefault();
                    e.stopPropagation();
                    this.close();
                };
            }
            const sendBtn = root
                ? root.querySelector('#vehicleMapPopupCmdSend')
                : document.getElementById('vehicleMapPopupCmdSend');
            if (sendBtn) {
                sendBtn.onclick = (e) => {
                    e.stopPropagation();
                    this._sendCommand();
                };
            }
        }

        async _sendCommand() {
            const url = this.opts.commandsSendUrl;
            const point = this.currentPoint;
            const deviceId = point?.id;
            const root = this.hostAnchor?.isOpen()
                ? this.hostAnchor.getElement()
                : (this.overlayHost?.isOpen() ? this.overlayHost._el : document);
            const type = root.querySelector?.('#vehicleMapPopupCmdType')?.value
                || document.getElementById('vehicleMapPopupCmdType')?.value;
            const btn = root.querySelector?.('#vehicleMapPopupCmdSend')
                || document.getElementById('vehicleMapPopupCmdSend');
            if (!url || !deviceId || !type) return;

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
                if (global.Swal) {
                    global.Swal.fire({ icon: ok ? 'success' : 'error', title: msg, timer: ok ? 2200 : undefined, showConfirmButton: !ok });
                } else {
                    alert(msg);
                }
            } catch (_) {
                alert(this.opts.i18n?.cmdFailed || 'Failed');
            } finally {
                btn?.removeAttribute('disabled');
            }
        }
    }

    VehicleMapPopup.buildHtml = buildHtml;
    VehicleMapPopup.formatDuration = formatDuration;
    global.VehicleMapPopup = VehicleMapPopup;
}(window));
