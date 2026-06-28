(function (global) {
    'use strict';
    const cfg = global.TRACKING_GEOFENCES_CONFIG;
    if (!cfg?.googleMapsKey) return;

    let map;
    global.__gtGeoReady = () => {
        map = new google.maps.Map(document.getElementById('gtGeofenceMap'), { center: { lat: 25.276987, lng: 55.296249 }, zoom: 11 });
        loadList();
    };

    async function loadList() {
        const res = await fetch(`${cfg.jsonUrl}?_=${Date.now()}`, { credentials: 'same-origin' });
        const data = await res.json();
        const list = document.getElementById('gtGeofenceList');
        list.innerHTML = (data.geofences || []).map((g) => `<div class="mb-1"><strong>${g.name}</strong> — ${g.device_name} (${g.type})</div>`).join('') || 'No geofences';
        (data.geofences || []).forEach(drawGeofence);
    }

    function drawGeofence(g) {
        if (g.type === 'circle' && g.center && g.radius) {
            const c = Array.isArray(g.center) ? g.center : JSON.parse(g.center);
            new google.maps.Circle({ map, center: { lat: +c[0], lng: +c[1] }, radius: +g.radius, fillOpacity: 0.15, strokeWeight: 2 });
        } else if (g.coords) {
            const coords = Array.isArray(g.coords) ? g.coords : JSON.parse(g.coords);
            new google.maps.Polygon({ map, paths: coords.map((p) => ({ lat: +p[0], lng: +p[1] })), fillOpacity: 0.15, strokeWeight: 2 });
        }
    }

    const s = document.createElement('script');
    s.src = `https://maps.googleapis.com/maps/api/js?key=${encodeURIComponent(cfg.googleMapsKey)}&callback=__gtGeoReady`;
    document.head.appendChild(s);
})(window);
