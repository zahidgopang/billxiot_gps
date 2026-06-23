/* ===========================
   map.init.js
=========================== */

window.map = null;
window.drawingManager = null;
window.customInfoWindow = null;

window.markers = [];
window.polylines = [];
window.geofences = [];

window.currentPositionMarker = null;
window.startMarker = null;

window.BASE_URL = window.BASE_URL || '';
window.deviceId = window.deviceId || null;
window.CSRF_TOKEN = window.CSRF_TOKEN || '';

window.initMap = function () {
    console.log('🚀 initMap()');

    const mapDiv = document.getElementById('map');
    if (!mapDiv) {
        console.error('Map container missing');
        return;
    }

    map = new google.maps.Map(mapDiv, {
        center: { lat: 24.8607, lng: 67.0011 },
        zoom: 13,
        mapTypeId: 'roadmap',
        streetViewControl: false,
        fullscreenControl: true,
        zoomControl: true
    });

    customInfoWindow = new google.maps.InfoWindow({ maxWidth: 350 });

    drawingManager = new google.maps.drawing.DrawingManager({
        drawingMode: null,
        drawingControl: false
    });
    drawingManager.setMap(map);

    // Init modules
    initHistoryModule();
    initGeofenceModule();
    initPlaybackModule();

    console.log('✅ Map ready');
};
