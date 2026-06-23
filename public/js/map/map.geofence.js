/* ===========================
   map.geofence.js
=========================== */

window.currentDrawing = null;

function initGeofenceModule() {
    google.maps.event.addListener(drawingManager, 'overlaycomplete', (e) => {
        currentDrawing = e.overlay;
        console.log('🟢 Geofence drawn');
    });
}

function startDrawing(type) {
    drawingManager.setDrawingMode(
        type === 'polygon'
            ? google.maps.drawing.OverlayType.POLYGON
            : google.maps.drawing.OverlayType.CIRCLE
    );
}

async function saveGeofence() {
    if (!currentDrawing) return;

    let payload = { device_id: deviceId };

    if (currentDrawing instanceof google.maps.Polygon) {
        payload.type = 'polygon';
        payload.coords = currentDrawing.getPath().getArray().map(p => [p.lat(), p.lng()]);
    }

    if (currentDrawing instanceof google.maps.Circle) {
        payload.type = 'circle';
        payload.center = [
            currentDrawing.getCenter().lat(),
            currentDrawing.getCenter().lng()
        ];
        payload.radius = currentDrawing.getRadius();
    }

    await fetch(`${BASE_URL}/user/device/${deviceId}/geofences-save`, {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
            'X-CSRF-TOKEN': CSRF_TOKEN
        },
        body: JSON.stringify(payload)
    });

    drawingManager.setDrawingMode(null);
    loadGeofences();
}

async function loadGeofences() {
    geofences.forEach(g => g.setMap(null));
    geofences = [];

    const res = await fetch(`${BASE_URL}/user/device/${deviceId}/geofences-json`);
    const data = await res.json();

    data.forEach(g => {
        let shape;
        if (g.type === 'polygon') {
            shape = new google.maps.Polygon({
                paths: g.coords.map(c => ({ lat: +c[0], lng: +c[1] })),
                map,
                fillOpacity: 0.15
            });
        }
        if (g.type === 'circle') {
            shape = new google.maps.Circle({
                center: { lat: +g.center[0], lng: +g.center[1] },
                radius: +g.radius,
                map,
                fillOpacity: 0.15
            });
        }
        geofences.push(shape);
    });
}
