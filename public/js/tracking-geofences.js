(function (global) {
    'use strict';
    const cfg = global.TRACKING_GEOFENCES_CONFIG;
    if (!cfg?.googleMapsKey) return;

    const i18n = cfg.i18n || {};
    const $ = (id) => document.getElementById(id);
    const csrf = () => cfg.csrfToken || document.querySelector('meta[name="csrf-token"]')?.content || '';

    let map = null;
    let drawingManager = null;
    let currentDrawing = null;     // shape being created (not yet saved)
    let existingShapes = [];       // shapes loaded from the server

    function escapeHtml(value) {
        return String(value ?? '')
            .replace(/&/g, '&amp;').replace(/</g, '&lt;')
            .replace(/>/g, '&gt;').replace(/"/g, '&quot;');
    }

    function notify(message, type) {
        if (global.Swal) {
            global.Swal.fire({ toast: true, position: 'top-end', timer: 2200, showConfirmButton: false, icon: type || 'info', title: message });
        } else if (type === 'error') {
            global.alert(message);
        }
    }

    global.__gtGeoReady = () => {
        map = new google.maps.Map($('gtGeofenceMap'), {
            center: { lat: 25.276987, lng: 55.296249 },
            zoom: 11,
            mapTypeControl: true,
            streetViewControl: false,
        });
        initDrawing();
        bindControls();
        loadList();
    };

    function initDrawing() {
        if (!google.maps.drawing?.DrawingManager) {
            ['gtGeoDrawPolygon', 'gtGeoDrawCircle', 'gtGeoSave'].forEach((id) => { const el = $(id); if (el) el.disabled = true; });
            notify(i18n.drawingUnavailable || 'Drawing tools failed to load', 'error');
            return;
        }
        drawingManager = new google.maps.drawing.DrawingManager({
            drawingMode: null,
            drawingControl: false,
            polygonOptions: { fillColor: '#2563eb', fillOpacity: 0.15, strokeColor: '#2563eb', strokeWeight: 2 },
            circleOptions: { fillColor: '#2563eb', fillOpacity: 0.15, strokeColor: '#2563eb', strokeWeight: 2 },
        });
        drawingManager.setMap(map);

        google.maps.event.addListener(drawingManager, 'overlaycomplete', (e) => {
            currentDrawing = e.overlay;
            drawingManager.setDrawingMode(null);
            $('gtGeoSave').disabled = false;
            $('gtGeoCancel').hidden = false;
        });
    }

    function startDraw(mode) {
        if (!drawingManager) return;
        currentDrawing?.setMap(null);
        currentDrawing = null;
        $('gtGeoSave').disabled = true;
        $('gtGeoCancel').hidden = false;
        drawingManager.setDrawingMode(mode);
    }

    function cancelDraw() {
        currentDrawing?.setMap(null);
        currentDrawing = null;
        drawingManager?.setDrawingMode(null);
        $('gtGeoSave').disabled = true;
        $('gtGeoCancel').hidden = true;
    }

    function bindControls() {
        $('gtGeoDrawPolygon')?.addEventListener('click', () => startDraw(google.maps.drawing.OverlayType.POLYGON));
        $('gtGeoDrawCircle')?.addEventListener('click', () => startDraw(google.maps.drawing.OverlayType.CIRCLE));
        $('gtGeoCancel')?.addEventListener('click', cancelDraw);
        $('gtGeoSave')?.addEventListener('click', saveGeofence);
    }

    async function saveGeofence() {
        if (!currentDrawing) { notify(i18n.drawFirst || 'Draw a shape first', 'error'); return; }
        const deviceId = $('gtGeoDevice')?.value;
        if (!deviceId) { notify(i18n.pickVehicle || 'Select a vehicle first', 'error'); return; }

        const name = ($('gtGeoName')?.value || '').trim() || `Geofence ${new Date().toLocaleString()}`;
        const payload = { name, device_id: parseInt(deviceId, 10) };

        if (currentDrawing instanceof google.maps.Polygon) {
            payload.type = 'polygon';
            payload.coords = [];
            const path = currentDrawing.getPath();
            for (let i = 0; i < path.getLength(); i++) {
                const ll = path.getAt(i);
                payload.coords.push([ll.lat(), ll.lng()]);
            }
            if (payload.coords.length < 3) { notify(i18n.drawFirst || 'Draw a shape first', 'error'); return; }
        } else if (currentDrawing instanceof google.maps.Circle) {
            payload.type = 'circle';
            const c = currentDrawing.getCenter();
            payload.center = [c.lat(), c.lng()];
            payload.radius = Math.round(currentDrawing.getRadius());
        } else {
            return;
        }

        const btn = $('gtGeoSave');
        btn.disabled = true;
        try {
            const res = await fetch(cfg.storeUrl, {
                method: 'POST',
                credentials: 'same-origin',
                headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': csrf(), Accept: 'application/json' },
                body: JSON.stringify(payload),
            });
            const json = await res.json().catch(() => ({}));
            if (!res.ok || json.success === false) throw new Error(json.message || 'save failed');
            currentDrawing.setMap(null);
            currentDrawing = null;
            $('gtGeoCancel').hidden = true;
            if ($('gtGeoName')) $('gtGeoName').value = '';
            notify(i18n.saved || 'Geofence saved', 'success');
            await loadList();
        } catch (err) {
            btn.disabled = false;
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
        if (listEl) {
            listEl.innerHTML = geofences.length
                ? geofences.map((g) => `<div class="gt-geo-row" data-id="${g.id}">
                        <span><span class="gt-geo-swatch" style="background:${escapeHtml(g.color || '#2563eb')}"></span>
                        <strong>${escapeHtml(g.name)}</strong> — ${escapeHtml(g.device_name)} <span class="text-muted">(${escapeHtml(g.type)})</span></span>
                        <span class="gt-geo-actions">
                            <button type="button" class="btn btn-sm btn-link p-0 me-2 gt-geo-zoom" data-id="${g.id}" title="Zoom"><i class="fas fa-search-location"></i></button>
                            <button type="button" class="btn btn-sm btn-outline-danger gt-geo-del" data-id="${g.id}">${escapeHtml(i18n.del || 'Delete')}</button>
                        </span>
                    </div>`).join('')
                : `<div class="text-muted">${escapeHtml(i18n.none || 'No geofences yet')}</div>`;
        }

        const bounds = new google.maps.LatLngBounds();
        let hasBounds = false;
        geofences.forEach((g) => {
            const shape = drawGeofence(g);
            if (!shape) return;
            existingShapes.push(shape);
            if (shape instanceof google.maps.Circle) {
                const b = shape.getBounds();
                if (b) { bounds.union(b); hasBounds = true; }
            } else if (shape.getPath) {
                shape.getPath().forEach((ll) => { bounds.extend(ll); hasBounds = true; });
            }
        });
        if (hasBounds) map.fitBounds(bounds, 40);

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
                    const res = await fetch(`${cfg.storeUrl}/${id}`, {
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
        if (g.type === 'circle' && g.center && g.radius) {
            const c = Array.isArray(g.center) ? g.center : JSON.parse(g.center);
            return new google.maps.Circle({
                map, center: { lat: +c[0], lng: +c[1] }, radius: +g.radius,
                fillColor: g.color || '#2563eb', fillOpacity: 0.15, strokeColor: g.color || '#2563eb', strokeWeight: 2,
            });
        }
        if (g.coords) {
            const coords = Array.isArray(g.coords) ? g.coords : JSON.parse(g.coords);
            if (!coords?.length) return null;
            return new google.maps.Polygon({
                map, paths: coords.map((p) => ({ lat: +p[0], lng: +p[1] })),
                fillColor: g.color || '#2563eb', fillOpacity: 0.15, strokeColor: g.color || '#2563eb', strokeWeight: 2,
            });
        }
        return null;
    }

    const s = document.createElement('script');
    s.src = `https://maps.googleapis.com/maps/api/js?key=${encodeURIComponent(cfg.googleMapsKey)}&libraries=drawing,geometry&callback=__gtGeoReady`;
    document.head.appendChild(s);
})(window);
