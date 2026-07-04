/**
 * Shared vehicle info popup for Google Maps (web + fleet views).
 * Shows odometer, plate, status+duration, altitude, angle, position, engine, commands.
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

    function statusText(point, i18n) {
        const label = point.status_label || point.status || point.motion_status || dash(i18n);
        const dur = point.status_duration_seconds;
        if (dur != null && dur > 0) {
            const forLbl = i18n.statusFor || 'for';
            return `${escHtml(label)} <span class="vehicle-map-popup__dur">${escHtml(forLbl)} ${escHtml(formatDuration(dur))}</span>`;
        }
        return escHtml(label);
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
                ${row(i18n.altitude || 'Altitude', escHtml(alt))}
                ${row(i18n.angle || 'Angle', escHtml(angle))}
                ${row(i18n.position || 'Position', positionText(point, i18n))}
                ${row(i18n.engine || 'Engine', ignitionText(point, i18n))}
                ${cmdSection}
                ${detailLink}
            </div>
        </div>`;
    }

    class VehicleMapPopup {
        constructor(options = {}) {
            this.opts = options;
            this.infoWindow = null;
            this.openId = null;
            this.currentPoint = null;
        }

        ensureWindow() {
            const g = this.opts.googleMaps || global.google;
            if (!this.infoWindow && g?.maps) {
                this.infoWindow = new g.maps.InfoWindow({
                    maxWidth: 340,
                    pixelOffset: new g.maps.Size(0, -8),
                });
                this.infoWindow.addListener('closeclick', () => this.close());
                g.maps.event.addListener(this.infoWindow, 'domready', () => this._wireDom());
            }
            return this.infoWindow;
        }

        open(point, anchor) {
            const map = typeof this.opts.getMap === 'function' ? this.opts.getMap() : this.opts.map;
            if (!map || !point) return;
            this.currentPoint = point;
            this.openId = point.id ?? null;
            const iw = this.ensureWindow();
            iw.setContent(buildHtml(point, this.opts));
            if (anchor?.getPosition) {
                iw.open({ map, anchor });
            } else if (point.lat != null && point.lng != null) {
                iw.setPosition({ lat: parseFloat(point.lat), lng: parseFloat(point.lng) });
                iw.open(map);
            }
            this.opts.onOpen?.(point);
        }

        update(point) {
            if (this.openId != null && point.id != null && Number(this.openId) !== Number(point.id)) {
                return;
            }
            if (!this.infoWindow?.getMap()) return;
            this.currentPoint = point;
            this.infoWindow.setContent(buildHtml(point, this.opts));
        }

        close() {
            this.openId = null;
            this.currentPoint = null;
            this.infoWindow?.close();
            this.opts.onClose?.();
        }

        isOpenFor(id) {
            return this.openId != null
                && Number(this.openId) === Number(id)
                && !!this.infoWindow?.getMap();
        }

        _wireDom() {
            document.getElementById('vehicleMapPopupClose')?.addEventListener('click', (e) => {
                e.preventDefault();
                this.close();
            });
            document.getElementById('vehicleMapPopupCmdSend')?.addEventListener('click', () => {
                this._sendCommand();
            });
        }

        async _sendCommand() {
            const url = this.opts.commandsSendUrl;
            const point = this.currentPoint;
            const deviceId = point?.id;
            const type = document.getElementById('vehicleMapPopupCmdType')?.value;
            const btn = document.getElementById('vehicleMapPopupCmdSend');
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
