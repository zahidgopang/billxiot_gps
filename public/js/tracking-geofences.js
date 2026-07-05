(function (global) {
    'use strict';

    const cfg = global.TRACKING_GEOFENCES_CONFIG;
    if (!cfg) return;

    const i18n = cfg.i18n || {};
    const $ = (id) => document.getElementById(id);
    const csrf = () => cfg.csrfToken || document.querySelector('meta[name="csrf-token"]')?.content || '';

    const TRAIL_MAX = 22;
    const TRAIL_MIN_STEP_DEG = 0.000022;

    let map = null;
    let geofenceDrawer = null;
    let currentDrawing = null;
    let existingShapes = [];
    let pollTimer = null;
    let pollInFlight = false;

    const vehicles = new Map((cfg.vehicles || []).map((v) => [Number(v.id), { ...v }]));
    const visible = new Set([...vehicles.keys()]);
    const trackStates = new Map();

    function escapeHtml(value) {
        return String(value ?? '')
            .replace(/&/g, '&amp;').replace(/</g, '&lt;')
            .replace(/>/g, '&gt;').replace(/"/g, '&quot;');
    }

    function notify(message, type) {
        if (global.Swal) {
            global.Swal.fire({
                toast: true,
                position: 'top-end',
                timer: type === 'success' ? 2200 : 2800,
                showConfirmButton: false,
                icon: type || 'info',
                title: message,
            });
        } else if (type === 'error') {
            global.alert(message);
        }
    }

    function colorForVehicle(v) {
        const key = v?.status_key || 'offline';
        return v?.color || (cfg.stateColors && cfg.stateColors[key]) || '#2563eb';
    }

    function selectedVehicleIds() {
        return [...document.querySelectorAll('.gt-geo-veh-check:checked')].map((el) => parseInt(el.value, 10)).filter(Boolean);
    }

    function firstSelectedVehicle() {
        const id = selectedVehicleIds()[0];
        if (!id) return null;
        return vehicles.get(id) || null;
    }

    function vehicleState(id) {
        if (!trackStates.has(id)) {
            trackStates.set(id, { marker: null, trail: [], trailLine: null, lastPoint: null });
        }
        return trackStates.get(id);
    }

    function buildMapsScriptUrl(key) {
        const params = new URLSearchParams({
            key,
            v: 'weekly',
            loading: 'async',
            libraries: 'marker',
        });
        return `https://maps.googleapis.com/maps/api/js?${params.toString()}`;
    }

    function waitForMapsCore(maxMs = 15000) {
        return new Promise((resolve, reject) => {
            const start = Date.now();
            (function poll() {
                if (typeof global.google?.maps?.importLibrary === 'function') {
                    resolve();
                    return;
                }
                if (Date.now() - start > maxMs) {
                    reject(new Error('Google Maps unavailable'));
                    return;
                }
                setTimeout(poll, 50);
            })();
        });
    }

    function loadMapsScript(key) {
        return new Promise((resolve, reject) => {
            if (typeof global.google?.maps?.importLibrary === 'function') {
                resolve();
                return;
            }

            const existing = document.querySelector('script[data-gt-geo-maps]');
            if (existing) {
                waitForMapsCore().then(resolve).catch(reject);
                return;
            }

            const script = document.createElement('script');
            script.dataset.gtGeoMaps = '1';
            script.async = true;
            script.defer = true;
            script.src = buildMapsScriptUrl(key);
            script.onerror = () => reject(new Error('Google Maps script failed'));
            script.onload = () => {
                waitForMapsCore().then(resolve).catch(reject);
            };
            document.head.appendChild(script);
        });
    }

    function ensureMapDrawer() {
        if (!map || !global.GeofenceMapDrawer) {
            return null;
        }
        if (!geofenceDrawer) {
            geofenceDrawer = new global.GeofenceMapDrawer({
                map,
                onComplete: (overlay) => {
                    currentDrawing = overlay;
                    const saveBtn = $('gtGeoSave');
                    if (saveBtn) saveBtn.disabled = false;
                    const cancelBtn = $('gtGeoCancel');
                    if (cancelBtn) cancelBtn.hidden = false;
                },
                onChange: (state) => {
                    const saveBtn = $('gtGeoSave');
                    if (!saveBtn || state.ready) {
                        return;
                    }
                    if (state.mode === 'polygon' && (state.pointCount || 0) >= 3) {
                        saveBtn.disabled = false;
                    }
                },
            });
        }
        return geofenceDrawer;
    }

    function extractDrawingPayload(overlay) {
        return global.GeofenceDraw?.resolvePayload(geofenceDrawer, overlay || currentDrawing) || null;
    }

    function startDraw(kind) {
        if (!global.GeofenceMapDrawer) {
            notify(i18n.drawingUnavailable || 'Drawing tools failed to load', 'error');
            return;
        }
        const drawer = ensureMapDrawer();
        if (!drawer) {
            notify(i18n.drawingUnavailable || 'Drawing tools failed to load', 'error');
            return;
        }

        currentDrawing?.setMap(null);
        currentDrawing = null;
        const saveBtn = $('gtGeoSave');
        if (saveBtn) saveBtn.disabled = true;
        const cancelBtn = $('gtGeoCancel');
        if (cancelBtn) cancelBtn.hidden = false;

        if (kind === 'circle') {
            drawer.startCircle();
        } else {
            drawer.startPolygon();
        }
    }

    function cancelDraw() {
        geofenceDrawer?.cancel();
        currentDrawing?.setMap(null);
        currentDrawing = null;
        const saveBtn = $('gtGeoSave');
        if (saveBtn) saveBtn.disabled = true;
        const cancelBtn = $('gtGeoCancel');
        if (cancelBtn) cancelBtn.hidden = true;
    }

    function teardownVehicle(id) {
        const st = trackStates.get(id);
        if (!st) return;
        st.marker?.setMap(null);
        st.trailLine?.setMap(null);
        trackStates.delete(id);
    }

    function ensureMarker(id) {
        const v = vehicles.get(id);
        const st = vehicleState(id);
        if (!map || !v || v.lat == null || v.lng == null) return;

        const color = colorForVehicle(v);
        const pos = { lat: Number(v.lat), lng: Number(v.lng) };

        if (!st.marker) {
            const createMarker = global.VehicleMarker?.createMarker
                || global.GoogleMapsPlatform?.createMarker
                || ((opts) => new global.google.maps.Marker(opts));
            st.marker = createMarker({
                map,
                position: pos,
                title: v.title || v.plate || `#${id}`,
                icon: {
                    path: global.google.maps.SymbolPath.CIRCLE,
                    fillColor: color,
                    fillOpacity: 1,
                    strokeColor: '#fff',
                    strokeWeight: 2,
                    scale: 7,
                },
                zIndex: 500,
            });
        } else {
            st.marker.setPosition(pos);
            st.marker.setIcon({
                path: global.google.maps.SymbolPath.CIRCLE,
                fillColor: color,
                fillOpacity: 1,
                strokeColor: '#fff',
                strokeWeight: 2,
                scale: 7,
            });
            if (st.marker.getMap() !== map) st.marker.setMap(map);
        }
    }

    function appendTrail(id, lat, lng, color) {
        if (!map || lat == null || lng == null) return;
        const st = vehicleState(id);
        const committed = st.trail;
        const last = committed[committed.length - 1];
        if (!last || Math.abs(lat - last.lat) > TRAIL_MIN_STEP_DEG || Math.abs(lng - last.lng) > TRAIL_MIN_STEP_DEG) {
            committed.push({ lat, lng });
            while (committed.length > TRAIL_MAX) committed.shift();
        }

        const path = committed.slice();
        const tail = path[path.length - 1];
        if (!tail || tail.lat !== lat || tail.lng !== lng) path.push({ lat, lng });
        if (path.length < 2) return;

        if (!st.trailLine) {
            st.trailLine = new global.google.maps.Polyline({
                map,
                path,
                strokeColor: color,
                strokeOpacity: 0.65,
                strokeWeight: 4,
                zIndex: 400,
                clickable: false,
            });
        } else {
            st.trailLine.setPath(path);
            st.trailLine.setOptions({ strokeColor: color });
            if (st.trailLine.getMap() !== map) st.trailLine.setMap(map);
        }
    }

    function applyVehiclePoint(id, point) {
        if (!visible.has(id) || !point || point.lat == null || point.lng == null) return;

        const merged = { ...vehicles.get(id), ...point, id };
        vehicles.set(id, merged);
        ensureMarker(id);
        appendTrail(id, Number(point.lat), Number(point.lng), colorForVehicle(merged));
    }

    function syncVisibleFromCheckboxes() {
        const next = new Set(selectedVehicleIds());
        visible.forEach((id) => {
            if (!next.has(id)) {
                visible.delete(id);
                teardownVehicle(id);
            }
        });
        next.forEach((id) => {
            if (!visible.has(id)) {
                visible.add(id);
                const v = vehicles.get(id);
                if (v?.lat != null && v?.lng != null) {
                    ensureMarker(id);
                    appendTrail(id, Number(v.lat), Number(v.lng), colorForVehicle(v));
                }
            }
        });
        startPolling();
        fitVisibleVehicles();
    }

    function fitVisibleVehicles() {
        if (!map || visible.size === 0) return;
        const bounds = new global.google.maps.LatLngBounds();
        let count = 0;
        visible.forEach((id) => {
            const v = vehicles.get(id);
            if (v?.lat != null && v?.lng != null) {
                bounds.extend({ lat: Number(v.lat), lng: Number(v.lng) });
                count++;
            }
        });
        if (count === 0) return;
        if (count === 1) {
            map.setCenter(bounds.getCenter());
            if (map.getZoom() < 13) map.setZoom(14);
        } else {
            map.fitBounds(bounds, 50);
        }
    }

    function startPolling() {
        if (pollTimer) {
            clearInterval(pollTimer);
            pollTimer = null;
        }
        if (visible.size === 0 || !cfg.liveJsonUrl) return;
        pollLive(true);
        pollTimer = setInterval(() => pollLive(false), cfg.pollIntervalMs || 4000);
    }

    async function pollLive(force) {
        if (pollInFlight && !force) return;
        const ids = [...visible];
        if (ids.length === 0 || !cfg.liveJsonUrl) return;

        pollInFlight = true;
        try {
            const res = await fetch(`${cfg.liveJsonUrl}?ids=${ids.join(',')}&_=${Date.now()}`, {
                credentials: 'same-origin',
                cache: 'no-store',
            });
            if (!res.ok) return;
            const data = await res.json();
            (data.devices || []).forEach((d) => applyVehiclePoint(Number(d.id), d));
        } catch (err) {
            console.warn('[tracking-geofences] live poll failed', err);
        } finally {
            pollInFlight = false;
        }
    }

    function bindVehiclePicker() {
        document.querySelectorAll('.gt-geo-veh-check').forEach((el) => {
            el.addEventListener('change', syncVisibleFromCheckboxes);
        });

        $('gtGeoSelectAll')?.addEventListener('click', () => {
            document.querySelectorAll('.gt-geo-veh-check').forEach((el) => { el.checked = true; });
            syncVisibleFromCheckboxes();
        });

        $('gtGeoSelectNone')?.addEventListener('click', () => {
            document.querySelectorAll('.gt-geo-veh-check').forEach((el) => { el.checked = false; });
            syncVisibleFromCheckboxes();
        });
    }

    function bindControls() {
        $('gtGeoDrawPolygon')?.addEventListener('click', () => startDraw('polygon'));
        $('gtGeoDrawCircle')?.addEventListener('click', () => startDraw('circle'));
        $('gtGeoCancel')?.addEventListener('click', cancelDraw);
        $('gtGeoSave')?.addEventListener('click', saveGeofence);
    }

    function scheduleMapResize() {
        if (!map || !global.google?.maps?.event) {
            return;
        }
        global.google.maps.event.trigger(map, 'resize');
    }

    async function saveGeofence() {
        const shape = extractDrawingPayload(currentDrawing);
        if (!shape) {
            notify(i18n.drawFirst || 'Draw a shape first', 'error');
            return;
        }

        const vehicle = firstSelectedVehicle();
        if (!vehicle) {
            notify(i18n.pickVehicle || 'Select a vehicle first', 'error');
            return;
        }

        const name = ($('gtGeoName')?.value || '').trim() || `Geofence ${new Date().toLocaleString()}`;

        const payload = {
            name,
            device_id: Number(vehicle.id),
            type: shape.type,
        };
        if (shape.type === 'polygon') {
            payload.coords = shape.coords;
        } else {
            payload.center = shape.center;
            payload.radius = shape.radius;
        }

        const btn = $('gtGeoSave');
        if (btn) btn.disabled = true;
        try {
            const res = await fetch(cfg.storeUrl, {
                method: 'POST',
                credentials: 'same-origin',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': csrf(),
                    Accept: 'application/json',
                },
                body: JSON.stringify(payload),
            });
            const json = await res.json().catch(() => ({}));
            if (!res.ok || json.success === false) throw new Error(json.message || 'save failed');

            currentDrawing?.setMap(null);
            currentDrawing = null;
            geofenceDrawer?.clearOverlay?.();
            const cancelBtn = $('gtGeoCancel');
            if (cancelBtn) cancelBtn.hidden = true;
            const nameInput = $('gtGeoName');
            if (nameInput) nameInput.value = '';

            const savedMsg = (i18n.savedForVehicle || 'Geofence saved for :vehicle')
                .replace(':vehicle', vehicle.title || vehicle.plate || `#${vehicle.id}`);
            notify(savedMsg, 'success');
            await loadList();
        } catch (err) {
            if (btn) btn.disabled = false;
            notify(i18n.saveFailed || 'Failed to save geofence', 'error');
        }
    }

    async function loadList() {
        const listEl = $('gtGeofenceList');
        let data;
        try {
            const res = await fetch(`${cfg.jsonUrl}?_=${Date.now()}`, { credentials: 'same-origin', cache: 'no-store' });
            data = await res.json();
        } catch (err) {
            if (listEl) listEl.textContent = i18n.saveFailed || 'Failed to load';
            return;
        }

        existingShapes.forEach((s) => s.setMap(null));
        existingShapes = [];

        const geofences = data.geofences || [];
        const canManage = cfg.canManageGeofences !== false;
        if (listEl) {
            listEl.innerHTML = geofences.length
                ? geofences.map((g) => `<div class="gt-geo-row" data-id="${g.id}">
                        <span><span class="gt-geo-swatch" style="background:${escapeHtml(g.color || '#2563eb')}"></span>
                        <strong>${escapeHtml(g.name)}</strong> — ${escapeHtml(g.device_name)} <span class="text-muted">(${escapeHtml(g.type)})</span></span>
                        <span class="gt-geo-actions">
                            <button type="button" class="btn btn-sm btn-link p-0 me-2 gt-geo-zoom" data-id="${g.id}" title="Zoom"><i class="fas fa-search-location"></i></button>
                            ${canManage ? `<button type="button" class="btn btn-sm btn-outline-danger gt-geo-del" data-id="${g.id}">${escapeHtml(i18n.del || 'Delete')}</button>` : ''}
                        </span>
                    </div>`).join('')
                : `<div class="text-muted">${escapeHtml(i18n.none || 'No geofences yet')}</div>`;
        }

        if (!map) return;

        const bounds = new global.google.maps.LatLngBounds();
        let hasBounds = false;
        geofences.forEach((g) => {
            const shape = drawGeofence(g);
            if (!shape) return;
            existingShapes.push(shape);
            if (shape instanceof global.google.maps.Circle) {
                const b = shape.getBounds();
                if (b) { bounds.union(b); hasBounds = true; }
            } else if (shape.getPath) {
                shape.getPath().forEach((ll) => { bounds.extend(ll); hasBounds = true; });
            }
        });

        bindListActions(geofences);
    }

    function shapeBoundsCenter(g) {
        if (g.type === 'circle' && g.center) {
            const c = Array.isArray(g.center) ? g.center : JSON.parse(g.center);
            return { lat: +c[0], lng: +c[1] };
        }
        const coords = Array.isArray(g.coords) ? g.coords : (g.coords ? JSON.parse(g.coords) : null);
        if (coords?.length) return { lat: +coords[0][0], lng: +coords[0][1] };
        return null;
    }

    function bindListActions(geofences) {
        const byId = new Map(geofences.map((g) => [String(g.id), g]));

        document.querySelectorAll('.gt-geo-zoom').forEach((btn) => {
            btn.addEventListener('click', () => {
                const g = byId.get(btn.dataset.id);
                const center = g && shapeBoundsCenter(g);
                if (center) { map.panTo(center); map.setZoom(15); }
            });
        });

        document.querySelectorAll('.gt-geo-del').forEach((btn) => {
            btn.addEventListener('click', async () => {
                const id = btn.dataset.id;
                if (!id) return;
                const msg = i18n.deleteConfirm || 'Delete this geofence?';
                if (global.Swal) {
                    const r = await global.Swal.fire({ icon: 'warning', title: msg, showCancelButton: true });
                    if (!r.isConfirmed) return;
                } else if (!global.confirm(msg)) {
                    return;
                }
                btn.disabled = true;
                try {
                    const deleteUrl = (cfg.deleteUrl || `${cfg.storeUrl}/${id}`).replace(/\/0(\?|$)/, `/${id}$1`);
                    const res = await fetch(deleteUrl, {
                        method: 'DELETE',
                        credentials: 'same-origin',
                        headers: { 'X-CSRF-TOKEN': csrf(), Accept: 'application/json' },
                    });
                    if (!res.ok) throw new Error('delete failed');
                    await loadList();
                } catch (err) {
                    btn.disabled = false;
                    notify(i18n.deleteFailed || 'Failed to delete geofence', 'error');
                }
            });
        });
    }

    function drawGeofence(g) {
        if (!map) return null;
        if (g.type === 'circle' && g.center && g.radius) {
            const c = Array.isArray(g.center) ? g.center : JSON.parse(g.center);
            return new global.google.maps.Circle({
                map,
                center: { lat: +c[0], lng: +c[1] },
                radius: +g.radius,
                fillColor: g.color || '#0891b2',
                fillOpacity: 0.12,
                strokeColor: g.color || '#0891b2',
                strokeWeight: 2,
            });
        }
        const coords = Array.isArray(g.coords) ? g.coords : (g.coords ? JSON.parse(g.coords) : null);
        if (coords?.length >= 3) {
            return new global.google.maps.Polygon({
                map,
                paths: coords.map((p) => ({ lat: +p[0], lng: +p[1] })),
                fillColor: g.color || '#0891b2',
                fillOpacity: 0.12,
                strokeColor: g.color || '#0891b2',
                strokeWeight: 2,
            });
        }
        return null;
    }

    async function boot() {
        const mapEl = $('gtGeofenceMap');
        const key = (cfg.googleMapsKey || '').trim();

        if (!mapEl) return;

        if (!key) {
            mapEl.innerHTML = `<div class="d-flex align-items-center justify-content-center h-100 text-muted small p-3">${escapeHtml(i18n.mapKeyMissing || 'Google Maps key missing')}</div>`;
            return;
        }

        try {
            await loadMapsScript(key);
            await global.google.maps.importLibrary('marker');
            const { Map } = await global.google.maps.importLibrary('maps');

            const baseMapOpts = {
                center: { lat: 25.276987, lng: 55.296249 },
                zoom: 11,
                mapTypeControl: true,
                streetViewControl: false,
                gestureHandling: 'greedy',
            };
            map = new Map(
                mapEl,
                global.GoogleMapsPlatform?.mapOptions
                    ? global.GoogleMapsPlatform.mapOptions(baseMapOpts, cfg.googleMapsMapId)
                    : baseMapOpts,
            );

            ensureMapDrawer();

            global.google.maps.event.addListenerOnce(map, 'idle', scheduleMapResize);
            setTimeout(scheduleMapResize, 300);
            setTimeout(scheduleMapResize, 900);
            global.addEventListener('resize', scheduleMapResize);

            bindVehiclePicker();
            bindControls();
            syncVisibleFromCheckboxes();
            await loadList();
        } catch (err) {
            console.error('[tracking-geofences] boot failed', err);
            mapEl.innerHTML = `<div class="d-flex align-items-center justify-content-center h-100 text-muted small p-3">${escapeHtml(i18n.mapLoadFailed || 'Map failed to load')}</div>`;
            notify(i18n.mapLoadFailed || 'Map failed to load', 'error');
        }
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', boot);
    } else {
        boot();
    }
})(window);
