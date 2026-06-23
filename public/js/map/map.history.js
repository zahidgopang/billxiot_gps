/* ===========================
   map.history.js
=========================== */

window.historyData = [];

function initHistoryModule() {
    console.log('📍 History module ready');
}

async function loadHistory(from = null, to = null) {
    clearRoute();

    let url = `${BASE_URL}/user/device/${deviceId}/history-json`;
    if (from && to) {
        url += `?from=${from}&to=${to}`;
    }

    const res = await fetch(url);
    const data = await res.json();
    historyData = normalizeHistory(data);

    if (historyData.length < 2) return;

    drawRoute(historyData);
}

function drawRoute(data) {
    for (let i = 1; i < data.length; i++) {
        const prev = data[i - 1];
        const curr = data[i];

        const polyline = new google.maps.Polyline({
            path: [
                { lat: +prev.lat, lng: +prev.lng },
                { lat: +curr.lat, lng: +curr.lng }
            ],
            strokeColor: getSpeedColor(curr.speed),
            strokeOpacity: 0.9,
            strokeWeight: 4,
            map
        });

        // 🔥 FIX: attach click
        google.maps.event.addListener(polyline, 'click', (e) => {
            showPolylineInfo(prev, curr, e.latLng);
        });

        polylines.push(polyline);
    }

    addStartEndMarkers(data[0], data[data.length - 1]);
}

function showPolylineInfo(prev, curr, position) {
    const html = document.getElementById('polylineInfoTemplate').innerHTML;
    customInfoWindow.setContent(html);
    customInfoWindow.setPosition(position);
    customInfoWindow.open(map);

    document.getElementById('statSpeed').innerText = curr.speed + ' km/h';
    document.getElementById('detailCoords').innerText =
        curr.lat + ', ' + curr.lng;
}

function addStartEndMarkers(start, end) {
    if (startMarker) startMarker.setMap(null);
    if (currentPositionMarker) currentPositionMarker.setMap(null);

    startMarker = new google.maps.Marker({
        position: { lat: +start.lat, lng: +start.lng },
        map,
        label: 'S'
    });

    currentPositionMarker = new google.maps.Marker({
        position: { lat: +end.lat, lng: +end.lng },
        map,
        label: 'E'
    });

    markers.push(startMarker, currentPositionMarker);
}

function clearRoute() {
    polylines.forEach(p => p.setMap(null));
    polylines = [];
    markers.forEach(m => m.setMap(null));
    markers = [];
}

function normalizeHistory(raw) {
    if (Array.isArray(raw)) return raw;
    if (raw?.data) return raw.data;
    if (raw?.locations) return raw.locations;
    return [];
}

function getSpeedColor(speed) {
    if (speed <= 0) return '#9aa0a6';
    if (speed <= 40) return '#34a853';
    if (speed <= 80) return '#fbbc05';
    return '#ea4335';
}
