/**
 * Global Tracking — single-vehicle history map (Traccar-style "Object" filter).
 *
 * Only one vehicle is rendered at a time. Loading a single route keeps the map
 * responsive; rendering many vehicles at once could hang the browser.
 */
(function (global) {
    'use strict';

    const DEFAULT_CENTER = { lat: 25.276987, lng: 55.296249 };
    const MEDIUM_SPEED = 60;
    const OVER_SPEED = 80;
    const STOP_MIN_SEC = 120;

    function escHtml(s) {
        return String(s ?? '')
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/"/g, '&quot;');
    }

    function speedToColor(speed) {
        const spd = parseFloat(speed || 0);
        if (spd <= 0) return '#64748b';
        if (spd <= MEDIUM_SPEED) return '#22c55e';
        if (spd <= OVER_SPEED) return '#eab308';
        return '#ef4444';
    }

    function detectStops(points) {
        if (!points || points.length < 2) return [];
        const events = [];
        let stopRun = [];

        const flush = () => {
            if (stopRun.length < 2) {
                stopRun = [];
                return;
            }
            const t0 = stopRun[0].recorded_at ? new Date(stopRun[0].recorded_at).getTime() : null;
            const t1 = stopRun[stopRun.length - 1].recorded_at ? new Date(stopRun[stopRun.length - 1].recorded_at).getTime() : null;
            if (t0 && t1 && (t1 - t0) / 1000 >= STOP_MIN_SEC) {
                const mid = stopRun[Math.floor(stopRun.length / 2)];
                events.push({
                    type: 'stop',
                    lat: mid.lat,
                    lng: mid.lng,
                    title: 'Long stop',
                });
            }
            stopRun = [];
        };

        points.forEach((p) => {
            if (parseFloat(p.speed || 0) <= 1) {
                stopRun.push(p);
            } else {
                flush();
            }
        });
        flush();
        return events;
    }

    function notify(title, icon = 'error') {
        if (global.Swal) {
            global.Swal.fire({
                icon,
                title,
                timer: icon === 'success' ? 1600 : undefined,
                showConfirmButton: icon !== 'success',
            });
        } else {
            alert(title);
        }
    }

    function historyPermissionMessage(cfg, payload, status) {
        const bodyMessage = String(payload?.message || '').trim();
        if (status === 403 || status === 401) {
            return bodyMessage
                || cfg?.i18n?.historyPermissionDenied
                || 'Access Denied. You do not have permission to view tracking history.';
        }
        return bodyMessage || `Request failed (${status})`;
    }

    function isHistoryPermissionError(err) {
        return Boolean(err && (err.status === 401 || err.status === 403 || err.code === 'history_permission_denied'));
    }

    function popupPermissionDenied(cfg, message) {
        const text = String(
            message
            || cfg?.i18n?.historyPermissionDenied
            || 'Access Denied. You do not have permission to view tracking history.',
        ).trim();
        if (global.Swal) {
            global.Swal.fire({
                icon: 'error',
                title: cfg?.i18n?.accessDeniedTitle || 'Access Denied',
                text,
                confirmButtonText: cfg?.i18n?.ok || 'OK',
            });
            return;
        }
        alert(text);
    }

    class GlobalTrackingHistory {
        constructor(cfg) {
            this.cfg = cfg;
            this.map = null;
            this.vehicles = new Map();
            this.selectedId = null;
            this.layers = [];
            this.legendEl = null;
            this._tripTimeline = null;
            this._playbackPoints = [];
            this._playbackIndex = 0;
            this._playbackSpeed = 1;
            this._playbackTimer = null;
            this._isPlaying = false;
            this._vehicleMarker = null;
        }

        showError(message) {
            const el = document.getElementById('gtHistoryError');
            if (el) {
                el.hidden = false;
                el.querySelector('[data-gt-error-text]')?.replaceChildren(document.createTextNode(message));
            }
        }

        toggleEmpty(show) {
            const el = document.getElementById('gtHistoryEmpty');
            if (el) el.hidden = !show;
        }

        boot() {
            const cfg = this.cfg;
            if (!cfg.googleMapsKey) {
                this.showError(cfg.i18n?.mapApiKeyMissing || 'Google Maps API key is missing.');
                return;
            }

            (cfg.vehicles || []).forEach((v) => this.vehicles.set(v.id, { ...v }));

            const callbackName = '__globalTrackingHistoryReady';
            const startMap = () => {
                if (!global.google?.maps?.Map) {
                    this.showError(cfg.i18n?.loadingMapFailed || 'Google Maps failed to initialize.');
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
                    this.showError(cfg.i18n?.loadingMapFailed || 'Could not load Google Maps.');
                });
                return;
            }

            global[callbackName] = () => {
                try { delete global[callbackName]; } catch (_) { global[callbackName] = undefined; }
                startMap();
            };

            const script = document.createElement('script');
            script.dataset.globalTrackingHistoryMaps = '1';
            script.async = true;
            script.defer = true;
            script.onerror = () => this.showError(cfg.i18n?.loadingMapFailed || 'Could not load Google Maps.');
            script.src = `https://maps.googleapis.com/maps/api/js?key=${encodeURIComponent(cfg.googleMapsKey)}&loading=async&v=weekly&callback=${callbackName}`;
            document.head.appendChild(script);
        }

        initMap() {
            const mapEl = document.getElementById('gtHistoryMap');
            if (!mapEl) return;

            const baseMapOpts = {
                center: DEFAULT_CENTER,
                zoom: 11,
                mapTypeControl: false,
                streetViewControl: false,
                fullscreenControl: true,
            };
            this.map = new google.maps.Map(
                mapEl,
                global.GoogleMapsPlatform?.mapOptions
                    ? global.GoogleMapsPlatform.mapOptions(baseMapOpts, this.cfg.googleMapsMapId)
                    : baseMapOpts,
            );

            this.legendEl = document.getElementById('gtHistoryLegend');
            this.bindUi();
            this.setDefaultDates();
            this.toggleEmpty(true);
        }

        setDefaultDates() {
            const now = new Date();
            const fmt = (d) => d.toISOString().slice(0, 10);
            const fromEl = document.getElementById('gtDateFrom');
            const toEl = document.getElementById('gtDateTo');
            if (fromEl && !fromEl.value) fromEl.value = fmt(now);
            if (toEl && !toEl.value) toEl.value = fmt(now);
        }

        readDateInput(id) {
            const el = document.getElementById(id);
            if (!el) return '';
            if (typeof global.FormEnhancements?.getDateValue === 'function') {
                return String(global.FormEnhancements.getDateValue(el) || '').trim();
            }
            return String(el.value || '').trim();
        }

        writeDateInput(elOrId, ymd) {
            const el = typeof elOrId === 'string' ? document.getElementById(elOrId) : elOrId;
            if (!el) return;
            if (typeof global.FormEnhancements?.setDateValue === 'function') {
                global.FormEnhancements.setDateValue(el, ymd, false);
                return;
            }
            el.value = ymd || '';
        }

        applyHistoryDay(ymd) {
            this.writeDateInput('gtDateFrom', ymd);
            this.writeDateInput('gtDateTo', ymd);
            const fromTime = document.getElementById('gtTimeFrom');
            const toTime = document.getElementById('gtTimeTo');
            if (fromTime) fromTime.value = '00:00';
            if (toTime) toTime.value = '23:59';
            // Day nav already refreshed by emitDayChange; avoid a second rebuild here.
        }

        onVehicleChanged() {
            const selectEl = document.getElementById('gtHistoryVehicle');
            this.selectedId = parseInt(selectEl?.value, 10) || null;
            if (this.selectedId) this.loadHistory();
        }

        bindUi() {
            const selectEl = document.getElementById('gtHistoryVehicle');
            if (selectEl) {
                this.selectedId = parseInt(selectEl.value, 10) || null;

                const $ = global.jQuery;
                if ($ && typeof $.fn?.select2 === 'function') {
                    const $sel = $(selectEl);
                    $sel.select2({
                        width: '100%',
                        placeholder: this.cfg.i18n?.selectVehicle || 'Select a vehicle',
                        dir: document.documentElement.getAttribute('dir') || 'ltr',
                    });
                    $sel.off('change.gtHistoryLoad').on('change.gtHistoryLoad', () => this.onVehicleChanged());
                } else {
                    selectEl.addEventListener('change', () => this.onVehicleChanged());
                }
            }

            document.getElementById('gtHistoryLoad')?.addEventListener('click', () => this.loadHistory());
            document.getElementById('gtHistoryFit')?.addEventListener('click', () => this.fitRoutes(true));
            document.getElementById('gtHistoryClear')?.addEventListener('click', () => {
                this.clearMap();
                this._tripTimeline?.clear();
                this.stopPlayback(true);
                this.toggleEmpty(true);
            });
            this.initTripTimeline();
            this.bindPlayback();
        }

        initTripTimeline() {
            if (this._tripTimeline || !global.HistoryTripTimeline?.create) return;
            this._tripTimeline = global.HistoryTripTimeline.create({
                summaryEl: document.getElementById('gtHistSummary'),
                listEl: document.getElementById('gtHistTimeline'),
                dayEl: document.getElementById('gtHistDayNav'),
                exportEl: document.getElementById('gtHistExport'),
                vehicleEl: document.getElementById('gtHistVehicleLabel'),
                geocodeUrl: this.cfg.historyGeocodeUrl || null,
                i18n: this.cfg.i18n || {},
                onDayChange: (ymd) => {
                    this.applyHistoryDay(ymd);
                    this.loadHistory();
                },
                onExport: (format) => this.exportHistory(format),
                onSelect: (seg) => this.focusSegment(seg),
            });
            const fromDate = this.readDateInput('gtDateFrom');
            if (fromDate) this._tripTimeline.setDay(fromDate);
        }

        exportHistory(format) {
            if (!this.selectedId || !this.cfg.historyExportUrl) {
                notify(this.cfg.i18n?.selectVehicle || 'Select a vehicle.', 'warning');
                return;
            }
            const params = this.buildQuery();
            params.set('format', format || 'xlsx');
            this.downloadHistoryExport(params, format || 'xlsx');
        }

        async downloadHistoryExport(params, format) {
            const url = `${this.cfg.historyExportUrl}?${params.toString()}`;
            try {
                const res = await fetch(url, {
                    method: 'GET',
                    credentials: 'same-origin',
                    cache: 'no-store',
                    headers: {
                        Accept: '*/*',
                        'X-Requested-With': 'XMLHttpRequest',
                    },
                });
                const contentType = (res.headers.get('Content-Type') || '').toLowerCase();
                const looksJson = contentType.includes('application/json') || contentType.includes('+json');
                if (!res.ok || looksJson) {
                    let message = this.cfg.i18n?.exportFailed
                        || 'Export failed. Select a vehicle and date range, then try again.';
                    try {
                        const data = await res.json();
                        if (data?.message) message = data.message;
                    } catch (_) { /* ignore */ }
                    notify(message, 'error');
                    return;
                }
                const blob = await res.blob();
                if (!blob.size || (blob.type && (blob.type.includes('json') || blob.type.includes('text/html')))) {
                    notify(
                        this.cfg.i18n?.exportFailed
                            || 'Export failed. Select a vehicle and date range, then try again.',
                        'error',
                    );
                    return;
                }
                const ext = format === 'xlsx' || format === 'xls' ? 'xls' : (format || 'xlsx');
                let filename = `history-export-${Date.now()}.${ext}`;
                const cd = res.headers.get('Content-Disposition') || '';
                const utf8 = /filename\*=UTF-8''([^;]+)/i.exec(cd);
                const plain = /filename="?([^";]+)"?/i.exec(cd);
                if (utf8) {
                    try { filename = decodeURIComponent(utf8[1].trim()); } catch (_) { filename = utf8[1].trim(); }
                } else if (plain) {
                    filename = plain[1].trim();
                }
                const objectUrl = URL.createObjectURL(blob);
                const a = document.createElement('a');
                a.href = objectUrl;
                a.download = filename;
                a.style.display = 'none';
                document.body.appendChild(a);
                a.click();
                window.setTimeout(() => {
                    URL.revokeObjectURL(objectUrl);
                    a.remove();
                }, 1500);
            } catch (err) {
                console.error('[history] export failed', err);
                notify(
                    err?.message
                        || this.cfg.i18n?.exportFailed
                        || 'Export failed. Please try again.',
                    'error',
                );
            }
        }

        focusSegment(seg) {
            if (!seg) return;
            const lat = parseFloat(seg.lat ?? seg.start_lat);
            const lng = parseFloat(seg.lng ?? seg.start_lng);
            if (Number.isFinite(lat) && Number.isFinite(lng) && this.map) {
                this.map.panTo({ lat, lng });
                if (this.map.getZoom() < 15) this.map.setZoom(15);
            }
            if (seg.start && this._playbackPoints.length) {
                const targetMs = Date.parse(seg.start);
                if (!Number.isNaN(targetMs)) {
                    let best = 0;
                    let bestDiff = Infinity;
                    this._playbackPoints.forEach((p, idx) => {
                        const ms = Date.parse(p.recorded_at || '');
                        if (Number.isNaN(ms)) return;
                        const diff = Math.abs(ms - targetMs);
                        if (diff < bestDiff) {
                            bestDiff = diff;
                            best = idx;
                        }
                    });
                    this.pausePlayback();
                    this.setPlaybackIndex(best);
                }
            }
        }

        bindPlayback() {
            document.getElementById('gtPbPlay')?.addEventListener('click', () => {
                if (this._isPlaying) this.pausePlayback();
                else this.startPlayback();
            });
            document.getElementById('gtPbStop')?.addEventListener('click', () => this.stopPlayback(true));
            document.querySelectorAll('[data-gt-speed]').forEach((btn) => {
                btn.addEventListener('click', () => {
                    this._playbackSpeed = parseFloat(btn.dataset.gtSpeed || '1') || 1;
                    document.querySelectorAll('[data-gt-speed]').forEach((b) => b.classList.toggle('active', b === btn));
                });
            });
            document.getElementById('gtPlaybackProgress')?.addEventListener('click', (e) => {
                if (!this._playbackPoints.length) return;
                const rect = e.currentTarget.getBoundingClientRect();
                const ratio = Math.min(1, Math.max(0, (e.clientX - rect.left) / rect.width));
                this.setPlaybackIndex(Math.round(ratio * (this._playbackPoints.length - 1)));
            });
        }

        startPlayback() {
            if (!this._playbackPoints.length) return;
            this._isPlaying = true;
            const icon = document.querySelector('#gtPbPlay i');
            if (icon) icon.className = 'fas fa-pause';
            const tick = () => {
                if (!this._isPlaying) return;
                if (this._playbackIndex >= this._playbackPoints.length - 1) {
                    this.pausePlayback();
                    return;
                }
                this.setPlaybackIndex(this._playbackIndex + 1);
                this._playbackTimer = setTimeout(tick, Math.max(80, 500 / this._playbackSpeed));
            };
            tick();
        }

        pausePlayback() {
            this._isPlaying = false;
            if (this._playbackTimer) {
                clearTimeout(this._playbackTimer);
                this._playbackTimer = null;
            }
            const icon = document.querySelector('#gtPbPlay i');
            if (icon) icon.className = 'fas fa-play';
        }

        stopPlayback(reset) {
            this.pausePlayback();
            if (reset) this.setPlaybackIndex(0);
        }

        setPlaybackIndex(idx) {
            if (!this._playbackPoints.length) return;
            this._playbackIndex = Math.max(0, Math.min(this._playbackPoints.length - 1, idx));
            const p = this._playbackPoints[this._playbackIndex];
            const total = this._playbackPoints.length;
            const bar = document.getElementById('gtPlaybackBar');
            if (bar) bar.style.width = `${(this._playbackIndex / Math.max(1, total - 1)) * 100}%`;
            const speedEl = document.getElementById('gtPbLiveSpeed');
            if (speedEl) speedEl.textContent = String(Math.round(parseFloat(p.speed || 0)));
            const pointEl = document.getElementById('gtPbPointLabel');
            if (pointEl) pointEl.textContent = `${this._playbackIndex + 1} / ${total}`;
            const cur = document.getElementById('gtPbTimeCurrent');
            if (cur) cur.textContent = String(p.recorded_at || '').slice(11, 16) || '00:00';
            const end = this._playbackPoints[total - 1];
            const tot = document.getElementById('gtPbTimeTotal');
            if (tot) tot.textContent = String(end?.recorded_at || '').slice(11, 16) || '00:00';

            if (this.map && p.lat != null && p.lng != null) {
                const pos = { lat: parseFloat(p.lat), lng: parseFloat(p.lng) };
                if (!this._vehicleMarker) {
                    this._vehicleMarker = new google.maps.Marker({
                        map: this.map,
                        position: pos,
                        title: 'Vehicle',
                        zIndex: 999,
                    });
                    this.layers.push(this._vehicleMarker);
                } else {
                    this._vehicleMarker.setPosition(pos);
                }
                this.map.panTo(pos);
            }
        }

        buildQuery() {
            const fromDate = this.readDateInput('gtDateFrom');
            const toDate = this.readDateInput('gtDateTo');
            const timeFrom = document.getElementById('gtTimeFrom')?.value || '';
            const timeTo = document.getElementById('gtTimeTo')?.value || '';

            let from = fromDate;
            let to = toDate;
            if (fromDate && timeFrom) from = `${fromDate} ${timeFrom}`;
            if (toDate && timeTo) to = `${toDate} ${timeTo}`;

            const params = new URLSearchParams();
            params.set('ids', String(this.selectedId));
            if (from) params.set('from', from);
            if (to) params.set('to', to);
            return params;
        }

        async loadHistory() {
            if (!this.selectedId) {
                notify(this.cfg.i18n?.selectVehicle || 'Select a vehicle.', 'warning');
                return;
            }

            const btn = document.getElementById('gtHistoryLoad');
            btn?.setAttribute('disabled', 'disabled');

            try {
                const params = this.buildQuery();
                const qs = `${params.toString()}&_=${Date.now()}`;
                const pointsUrl = this.cfg.historyPointsJsonUrl;
                const analyticsUrl = this.cfg.historyAnalyticsJsonUrl;
                const canParallel = Boolean(pointsUrl && analyticsUrl);

                let vehicle = null;

                if (canParallel) {
                    const [pointsResult, analyticsResult] = await Promise.allSettled([
                        fetch(`${pointsUrl}?${qs}`, { credentials: 'same-origin', cache: 'no-store' }),
                        fetch(`${analyticsUrl}?${qs}`, { credentials: 'same-origin', cache: 'no-store' }),
                    ]);

                    this.throwIfHistoryPermissionDenied(pointsResult);
                    this.throwIfHistoryPermissionDenied(analyticsResult);

                    const pointsVehicle = await this.parseHistoryVehicle(pointsResult);
                    const analyticsVehicle = await this.parseHistoryVehicle(analyticsResult);

                    if (!pointsVehicle && !analyticsVehicle) {
                        throw new Error(this.cfg.i18n?.loadFailed || 'Failed to load history.');
                    }

                    vehicle = {
                        ...(analyticsVehicle || {}),
                        ...(pointsVehicle || {}),
                        points: pointsVehicle?.points || [],
                        events: analyticsVehicle?.events || analyticsVehicle?.history_events || pointsVehicle?.events || [],
                        segments: analyticsVehicle?.segments || analyticsVehicle?.trip_timeline || [],
                        stats: analyticsVehicle?.stats || pointsVehicle?.stats || null,
                    };
                } else {
                    const res = await fetch(`${this.cfg.historyJsonUrl}?${qs}`, {
                        credentials: 'same-origin',
                        cache: 'no-store',
                    });
                    const data = await res.json().catch(() => ({}));
                    if (!res.ok) {
                        const err = new Error(historyPermissionMessage(this.cfg, data, res.status));
                        err.status = res.status;
                        if (res.status === 401 || res.status === 403) {
                            err.code = 'history_permission_denied';
                        }
                        throw err;
                    }
                    vehicle = (data.vehicles || [])[0] || null;
                }

                this.drawHistory(vehicle);
            } catch (err) {
                console.error('[global-tracking-history]', err);
                if (isHistoryPermissionError(err)) {
                    popupPermissionDenied(this.cfg, err.message);
                } else {
                    notify(err.message || this.cfg.i18n?.loadFailed || 'Failed to load history.', 'error');
                }
            } finally {
                btn?.removeAttribute('disabled');
            }
        }

        async throwIfHistoryPermissionDenied(result) {
            if (result.status !== 'fulfilled' || !result.value) {
                return;
            }
            const res = result.value;
            if (res.ok || (res.status !== 401 && res.status !== 403)) {
                return;
            }
            const data = await res.clone().json().catch(() => ({}));
            const err = new Error(historyPermissionMessage(this.cfg, data, res.status));
            err.status = res.status;
            err.code = 'history_permission_denied';
            throw err;
        }

        async parseHistoryVehicle(result) {
            if (result.status !== 'fulfilled' || !result.value?.ok) {
                return null;
            }
            const data = await result.value.json().catch(() => ({}));
            return (data.vehicles || [])[0] || null;
        }

        drawHistory(vehicle) {
            this.clearMap();

            const points = vehicle?.points || [];
            if (points.length < 2) {
                this.toggleEmpty(true);
                if (this.legendEl) this.legendEl.innerHTML = '';
                if (points.length === 1) {
                    this.addEndpoint(points[0], 'start');
                    this.toggleEmpty(false);
                    this.fitRoutes(true);
                }
                return;
            }

            this.toggleEmpty(false);
            this.drawSpeedRoute(points);
            this.addEndpoint(points[0], 'start');
            this.addEndpoint(points[points.length - 1], 'end');

            const routeEvents = detectStops(points);
            (vehicle.events || []).forEach((ev) => {
                if (ev.lat != null && ev.lng != null) {
                    routeEvents.push({
                        type: ev.event_type || ev.type || 'event',
                        lat: ev.lat,
                        lng: ev.lng,
                        title: ev.title || ev.message || 'Event',
                    });
                }
            });
            routeEvents.forEach((ev) => this.addEventMarker(ev));

            this.renderSpeedLegend(vehicle.name || vehicle.title);
            this._playbackPoints = points;
            this._vehicleMarker = null;
            this.setPlaybackIndex(0);
            this._tripTimeline?.setData({
                ...vehicle,
                segments: vehicle.segments || vehicle.trip_timeline || [],
                stats: vehicle.stats || null,
            });
            this.fitRoutes(true);
        }

        renderSpeedLegend(name) {
            if (!this.legendEl) return;
            const rows = [
                { c: '#64748b', t: '0' },
                { c: '#22c55e', t: `≤ ${MEDIUM_SPEED}` },
                { c: '#eab308', t: `≤ ${OVER_SPEED}` },
                { c: '#ef4444', t: `> ${OVER_SPEED}` },
            ];
            this.legendEl.innerHTML =
                (name ? `<div class="gt-legend-item"><strong>${escHtml(name)}</strong></div>` : '') +
                rows.map((r) =>
                    `<div class="gt-legend-item"><span class="gt-legend-swatch" style="background:${r.c}"></span>${escHtml(r.t)} km/h</div>`
                ).join('');
        }

        clearMap() {
            this.layers.forEach((layer) => {
                if (layer.setMap) layer.setMap(null);
                else if (Array.isArray(layer)) layer.forEach((l) => l.setMap?.(null));
            });
            this.layers = [];
            if (this.legendEl) this.legendEl.innerHTML = '';
        }

        drawSpeedRoute(points) {
            for (let i = 1; i < points.length; i++) {
                const a = points[i - 1];
                const b = points[i];
                const speed = Math.max(parseFloat(a.speed || 0), parseFloat(b.speed || 0));
                const line = new google.maps.Polyline({
                    path: [{ lat: a.lat, lng: a.lng }, { lat: b.lat, lng: b.lng }],
                    strokeColor: speedToColor(speed),
                    strokeOpacity: 0.92,
                    strokeWeight: speed <= 0 ? 6 : speed <= MEDIUM_SPEED ? 7 : speed <= OVER_SPEED ? 8 : 9,
                    map: this.map,
                    zIndex: 2,
                    clickable: false,
                });
                this.layers.push(line);
            }
        }

        endpointIcon(type) {
            const url = type === 'start'
                ? (this.cfg.startIconUrl || '/images/map/marker-start.svg')
                : (this.cfg.endIconUrl || '/images/map/marker-end.svg');
            return {
                url,
                scaledSize: new google.maps.Size(40, 40),
                anchor: new google.maps.Point(20, 20),
            };
        }

        addEndpoint(point, type) {
            const marker = global.VehicleMarker.createMarker({
                position: { lat: point.lat, lng: point.lng },
                map: this.map,
                icon: this.endpointIcon(type),
                zIndex: type === 'start' ? 600 : 601,
                title: type === 'start' ? (this.cfg.i18n?.routeStart || 'Start') : (this.cfg.i18n?.routeEnd || 'End'),
            });
            this.layers.push(marker);
        }

        addEventMarker(ev) {
            const marker = global.VehicleMarker.createMarker({
                position: { lat: ev.lat, lng: ev.lng },
                map: this.map,
                icon: {
                    path: google.maps.SymbolPath.CIRCLE,
                    fillColor: ev.type === 'stop' ? '#f97316' : '#ef4444',
                    fillOpacity: 0.95,
                    strokeColor: '#ffffff',
                    strokeWeight: 2,
                    scale: 7,
                },
                title: ev.title || ev.type,
                zIndex: 550,
            });
            this.layers.push(marker);
        }

        fitRoutes(smooth) {
            const bounds = new google.maps.LatLngBounds();
            let count = 0;
            this.layers.forEach((layer) => {
                if (layer.getPath) {
                    layer.getPath().forEach((ll) => { bounds.extend(ll); count++; });
                } else if (layer.getPosition) {
                    bounds.extend(layer.getPosition());
                    count++;
                }
            });
            if (count === 0) return;
            this.map.fitBounds(bounds, 48);
            if (smooth && this.map.panToBounds) {
                this.map.panToBounds(bounds, 48);
            }
        }
    }

    function init() {
        const cfg = global.GLOBAL_TRACKING_HISTORY_CONFIG;
        if (!cfg) return;
        const app = new GlobalTrackingHistory(cfg);
        app.boot();
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init);
    } else {
        init();
    }
})(typeof window !== 'undefined' ? window : globalThis);
