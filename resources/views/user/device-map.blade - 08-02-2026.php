@extends('user.layout')

@section('title', $device->name . ' - Tracking')

@section('content')
    <div class="tracking-container">
        <!-- Sidebar -->
        @include('user.side_bar_map')

        <!-- MAP AREA -->
        <div class="map-area" id="mapArea">
            <div id="map"></div>

            <!-- 🔥 Heatmap Legend -->
            <div class="heatmap-legend" id="heatmapLegend" style="display:none;">
                <div class="bar"></div>
                <div style="text-align:right;font-size:12px;color:#444;">Fast →</div>
            </div>

            <!-- 🎬 Playback Controls -->
            <div id="playbackControls" class="playback-controls">
                <button id="pbStart">▶</button>
                <button id="pbPause">⏸</button>
                <button id="pbStop">⏹</button>
                <span id="pbStatus" style="color:#fff;font-size:13px;">Ready</span>
            </div>

            <!-- 🧭 Floating Map Buttons -->
            <div class="map-controls">
                <button id="btnRecenter" title="Recenter">⤾</button>
                <button id="btnLive" title="Toggle Follow">●</button>
                <button id="btnTheme" title="Toggle Theme">🌙</button>
                <button id="btnDraw" title="Geofence Tools">🎯</button>
            </div>
        </div>
    </div>
@endsection

@push('styles')
    <style>
        /* ============================================
           MAP AREA - Updated for new sidebar system
        ============================================ */
        #mapArea {
            position: relative;
            flex: 1 !important;
            width: 100% !important;
            height: calc(100vh - 70px) !important;
            transition: margin-left 0.35s ease;
        }

        /* This class is now applied by the sidebar toggle in layout */
        .map-area.sidebar-open {
            margin-left: 380px;
        }

        @media (max-width: 768px) {
            .map-area.sidebar-open {
                margin-left: 0 !important;
            }
        }

        #map {
            position: relative;
            z-index: 1;
            width: 100%;
            height: 100% !important;
            border-radius: 0;
            overflow: hidden;
        }

        /* ============================================
           POPUP (GLASS LOOK)
        ============================================ */
        .leaflet-popup-content-wrapper {
            backdrop-filter: blur(10px);
            background: rgba(255,255,255,0.70);
            border-radius: 12px;
            border: 1px solid rgba(255,255,255,0.4);
        }
        .leaflet-popup-tip {
            background: rgba(255,255,255,0.70);
        }

        /* ============================================
           IDLE MARKER
        ============================================ */
        .idle-marker {
            width: 16px;
            height: 16px;
            background: #f39c12;
            border-radius: 50%;
            border: 2px solid white;
            box-shadow: 0 0 8px rgba(243,156,18,0.9);
        }

        /* ============================================
           GEOFENCE STYLES
        ============================================ */
        .geofence-polygon {
            color: #8e44ad;
            weight: 2;
            opacity: 0.7;
            fill-opacity: 0.05;
        }
        .geofence-polygon.inside {
            color: #27ae60;
            fill-opacity: 0.12;
        }

        .geofence-circle {
            color: #2980b9;
            weight: 2;
            opacity: 0.7;
            fill-opacity: 0.03;
        }
        .geofence-circle.inside {
            color: #27ae60;
            fill-opacity: 0.12;
        }

        /* ============================================
           STOP MARKER (PREMIUM)
        ============================================ */
        .stop-marker-wrapper {
            position: relative;
            width: 55px;
            height: 55px;
            display: inline-block;
            transform: translate3d(0,0,0);
        }

        .stop-animated-icon {
            width: 100%;
            height: 100%;
            animation: stopFloat 1.6s ease-in-out infinite;
            filter: drop-shadow(0 3px 6px rgba(0,0,0,0.25));
        }

        .stop-glow {
            position: absolute;
            left: 50%;
            top: 50%;
            width: 46px;
            height: 24px;
            transform: translate(-50%, -20%);
            border-radius: 50%;
            background: rgba(255,40,40,0.28);
            pointer-events: none;
            z-index: -1;
            animation: stopGlow 1.8s ease-out infinite;
        }

        .stop-glow::after {
            content: "";
            position: absolute;
            left: 50%;
            top: 50%;
            width: 100%;
            height: 100%;
            border-radius: 50%;
            transform: translate(-50%, -50%);
            background: rgba(255, 40, 40, 0.15);
            filter: blur(6px);
        }

        /* Stop pulse large variant */
        .stop-pulse-large {
            width: 40px !important;
            height: 40px !important;
            background: rgba(255,0,0,0.9);
            border-radius: 50%;
            border: 4px solid white;
            box-shadow: 0 0 25px rgba(255,0,0,0.8);
            animation: stopPulse 1.3s infinite;
        }

        .stop-pulse-ring {
            width: 55px;
            height: 55px;
            background: rgba(255,0,0,0.25);
            border-radius: 50%;
            position: absolute;
            animation: stopPulse 1.5s infinite ease-out;
        }

        /* ============================================
           STOP MARKER ANIMATIONS (CLEANED)
        ============================================ */
        @keyframes stopFloat {
            0%, 100% { transform: translateY(0); }
            50% { transform: translateY(-6px); }
        }

        @keyframes stopGlow {
            0% { transform: translate(-50%,-20%) scale(0.9); opacity: 0.45; }
            65% { transform: translate(-50%,-20%) scale(1.1); opacity: 0.22; }
            100% { transform: translate(-50%,-20%) scale(1.25); opacity: 0; }
        }

        @keyframes stopPulse {
            0% { transform: scale(0.8); opacity: 0.9; }
            70% { transform: scale(1.6); opacity: 0.2; }
            100% { transform: scale(2); opacity: 0; }
        }

        /* ============================================
           LIVE TRAIL
        ============================================ */
        .leaflet-live-trail {
            z-index: 5000 !important;
            pointer-events: none;
        }

        /* ============================================
           MAP CONTROLS - FIXED: Higher z-index than heatmap
        ============================================ */
        .map-controls {
            position: absolute;
            top: 20px;
            right: 20px;
            display: flex;
            flex-direction: column;
            gap: 10px;
            z-index: 10000; /* Increased to be above heatmap */
        }

        .map-controls button {
            width: 48px;
            height: 48px;
            border: none;
            border-radius: 14px;
            background: rgba(255,255,255,0.9);
            backdrop-filter: blur(4px);
            box-shadow: 0 6px 20px rgba(0,0,0,0.15);
            font-size: 20px;
            cursor: pointer;
            transition: all 0.25s ease;
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .map-controls button:hover {
            transform: scale(1.08);
            background: rgba(255,255,255,0.95);
        }

        .map-controls button:active {
            transform: scale(0.95);
        }

        /* ============================================
           HEATMAP LEGEND - FIXED: Lower z-index than map controls
        ============================================ */
        .heatmap-legend {
            position: absolute;
            right: 80px;
            top: 14px;
            background: rgba(255,255,255,0.95);
            padding: 8px 10px;
            border-radius: 8px;
            font-size: 13px;
            box-shadow: 0 6px 18px rgba(0,0,0,0.12);
            z-index: 9000; /* Lower than map controls */
            max-width: 180px;
        }

        .heatmap-legend .bar {
            height: 8px;
            width: 100%;
            border-radius: 4px;
            background: linear-gradient(to right, #2ecc71, #f1c40f, #e67e22, #e74c3c);
            margin-bottom: 6px;
        }

        /* ============================================
           SMART PLAYBACK CONTROLS (Compact & Modern)
        ============================================ */
        #playbackControls {
            position: absolute;
            bottom: 20px;
            left: 50%;
            transform: translateX(-50%);
            display: flex;
            align-items: center;
            gap: 8px;
            background: rgba(30, 30, 30, 0.9);
            backdrop-filter: blur(10px);
            padding: 8px 12px;
            border-radius: 16px;
            box-shadow: 0 8px 32px rgba(0, 0, 0, 0.3);
            border: 1px solid rgba(255, 255, 255, 0.1);
            z-index: 10000;
            transition: all 0.3s ease;
        }

        #playbackControls.compact {
            gap: 6px;
            padding: 6px 10px;
        }

        #playbackControls.hidden {
            opacity: 0.3;
            transform: translateX(-50%) scale(0.95);
        }

        #playbackControls:hover {
            background: rgba(40, 40, 40, 0.95);
            box-shadow: 0 10px 40px rgba(0, 0, 0, 0.4);
        }

        #playbackControls button {
            width: 36px;
            height: 36px;
            border-radius: 10px;
            border: none;
            background: rgba(255, 255, 255, 0.1);
            color: white;
            font-size: 14px;
            cursor: pointer;
            transition: all 0.2s ease;
            display: flex;
            align-items: center;
            justify-content: center;
        }

        #playbackControls button:hover {
            background: var(--map-primary);
            transform: scale(1.1);
        }

        #playbackControls button:active {
            transform: scale(0.95);
        }

        #playbackControls button.active {
            background: var(--map-primary);
            color: white;
            box-shadow: 0 0 0 2px rgba(25, 118, 210, 0.3);
        }

        #playbackControls button#pbPause {
            background: rgba(25, 118, 210, 0.2);
            color: var(--map-primary);
        }

        #playbackControls button#pbPause.active {
            background: var(--map-primary);
            color: white;
        }

        #pbStatus {
            font-size: 12px;
            color: rgba(255, 255, 255, 0.8);
            font-weight: 500;
            padding: 0 8px;
            min-width: 80px;
            text-align: center;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }

        /* Playback progress bar (optional enhancement) */
        .playback-progress {
            width: 120px;
            height: 4px;
            background: rgba(255, 255, 255, 0.1);
            border-radius: 2px;
            margin: 0 8px;
            overflow: hidden;
        }

        .playback-progress-bar {
            height: 100%;
            background: var(--map-primary);
            border-radius: 2px;
            width: 0%;
            transition: width 0.3s ease;
        }

        /* ============================================
           RESPONSIVE DESIGN
        ============================================ */
        @media (max-width: 768px) {
            /* Map Controls for Mobile */
            .map-controls {
                top: 70px; /* Below navbar */
                right: 10px;
                gap: 8px;
            }

            .map-controls button {
                width: 42px;
                height: 42px;
                font-size: 18px;
            }

            /* Heatmap Legend for Mobile */
            .heatmap-legend {
                position: absolute;
                left: 10px;
                right: -300px;
                top: 10px;
                width: calc(30% - 20px);
                max-width: none;
                text-align: center;
                margin: 0 auto;
                z-index: 8000; /* Ensure it doesn't overlap controls */
            }

            .heatmap-legend .bar {
                width: 100%;
                margin: 0 auto 6px;
            }

            /* Smart Playback Controls for Mobile */
            #playbackControls {
                bottom: 15px;
                padding: 6px 10px;
                gap: 6px;
                min-width: 280px;
                width: auto;
                max-width: 90%;
            }

            #playbackControls.compact {
                padding: 5px 8px;
                gap: 4px;
                min-width: 240px;
            }

            #playbackControls button {
                width: 32px;
                height: 32px;
                font-size: 12px;
            }

            #pbStatus {
                font-size: 11px;
                min-width: 70px;
                padding: 0 6px;
            }

            .playback-progress {
                width: 80px;
                margin: 0 6px;
            }
        }

        @media (max-width: 576px) {
            /* Stack map controls horizontally on very small screens */
            .map-controls {
                flex-direction: row;
                flex-wrap: wrap;
                justify-content: flex-end;
                top: 75px;
                right: 10px;
                left: auto;
                gap: 6px;
                max-width: 200px;
            }

            .map-controls button {
                width: 40px;
                height: 40px;
                font-size: 16px;
            }

            /* Adjust heatmap legend position */
            .heatmap-legend {
                top: 125px; /* Below map controls */
                font-size: 12px;
                padding: 6px 8px;
            }

            /* Ultra-compact playback controls for mobile */
            #playbackControls {
                flex-wrap: nowrap;
                padding: 5px 8px;
                gap: 4px;
                min-width: 250px;
                bottom: 10px;
            }

            #playbackControls.compact {
                min-width: 200px;
                padding: 4px 6px;
            }

            #playbackControls button {
                width: 30px;
                height: 30px;
                font-size: 11px;
                border-radius: 8px;
            }

            #pbStatus {
                font-size: 10px;
                min-width: 60px;
                padding: 0 4px;
            }

            .playback-progress {
                width: 60px;
                margin: 0 4px;
            }
        }

        @media (max-width: 400px) {
            /* Extra small screens */
            .map-controls {
                top: 70px;
                gap: 4px;
            }

            .map-controls button {
                width: 38px;
                height: 38px;
                font-size: 15px;
            }

            .heatmap-legend {
                top: 120px;
                font-size: 11px;
                padding: 5px 6px;
            }

            #playbackControls {
                min-width: 220px;
                padding: 4px 6px;
            }

            #playbackControls button {
                width: 28px;
                height: 28px;
                font-size: 10px;
            }

            #pbStatus {
                font-size: 9px;
                min-width: 50px;
            }
        }

        /* Landscape mode adjustments */
        @media (max-height: 500px) and (orientation: landscape) {
            .map-controls {
                top: 10px;
            }

            .heatmap-legend {
                top: 14px;
            }

            #playbackControls {
                bottom: 10px;
                padding: 4px 8px;
            }
        }
    </style>
@endpush

@push('scripts')
    <script>
        window.BASE_URL = "{{ url('/') }}";
        const deviceId = {{ $device->id }};
        const CSRF_TOKEN = "{{ csrf_token() }}";

        /* Configs */
        const IDLE_SPEED_THRESHOLD = 3; // km/h
        const IDLE_TIME_THRESHOLD_SECONDS = 2 * 60; // 5 minutes
        let historyData = [];

        /* Add minimal rotated marker method if missing */
        if (!L.Marker.prototype.setRotationAngle) {
            L.Marker.include({
                setRotationAngle: function(angle) {
                    if (this._icon) this._icon.style.transform = `rotate(${angle}deg)`;
                }
            });
        }

        /* Map init */
        const map = L.map("map", { preferCanvas:true }).setView([24.8607, 67.0011], 13);
        let lightTiles = L.tileLayer("https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png",{ maxZoom:22, minZoom:3 });
        let darkTiles  = L.tileLayer("https://{s}.basemaps.cartocdn.com/dark_all/{z}/{x}/{y}{r}.png",{ maxZoom:22, minZoom:3 });

        // ===============================
        // SATELLITE + LABELS (ESRI)
        // ===============================
        let satelliteTiles = L.tileLayer(
            "https://server.arcgisonline.com/ArcGIS/rest/services/World_Imagery/MapServer/tile/{z}/{y}/{x}",
            { maxZoom: 22 }
        );

        let satelliteRoads = L.tileLayer(
            "https://services.arcgisonline.com/ArcGIS/rest/services/Reference/World_Transportation/MapServer/tile/{z}/{y}/{x}",
            { maxZoom: 22 }
        );

        let satellitePlaces = L.tileLayer(
            "https://services.arcgisonline.com/ArcGIS/rest/services/Reference/World_Boundaries_and_Places/MapServer/tile/{z}/{y}/{x}",
            { maxZoom: 22 }
        );

        let satelliteLabels = L.tileLayer(
            "https://{s}.basemaps.cartocdn.com/light_only_labels/{z}/{x}/{y}.png",
            { maxZoom: 22, opacity: 0.95 }
        );


        lightTiles.addTo(map);

        /* Layers */
        let routeContainer = L.layerGroup().addTo(map);
        let geofenceLayer = L.layerGroup().addTo(map);
        let idleLayer = L.layerGroup().addTo(map);
        let drawnItems = new L.FeatureGroup().addTo(map); // used by Leaflet.draw — keep geofences here
        let stopMarkers = [];
        let moveMarker = null;
        let followVehicle = true;
        let geofences = [];

        /* Draw control (EDIT uses drawnItems) */
        const drawControl = new L.Control.Draw({
            draw: {
                polyline: false,
                rectangle: false,
                marker: false,
                circlemarker: false,
                polygon: { allowIntersection: false, showArea: true },
                circle: true
            },
            edit: {
                featureGroup: drawnItems,
                remove: true
            }
        });
        map.addControl(drawControl);

        /* Utilities (reuse safe functions) */
        function normalizeResponse(j){ if(!j) return []; if(Array.isArray(j)) return j; if(Array.isArray(j.locations)) return j.locations; if(Array.isArray(j.data)) return j.data; return []; }
        function pointInPolygon(point, vs) { const x = point[1], y = point[0]; let inside = false; for (let i=0,j=vs.length-1;i<vs.length;j=i++) { const xi=vs[i][1], yi=vs[i][0], xj=vs[j][1], yj=vs[j][0]; const intersect = ((yi>y)!=(yj>y)) && (x < (xj-xi)*(y-yi)/(yj-yi+0.0) + xi); if (intersect) inside = !inside; } return inside; }
        function haversineDistance(lat1, lon1, lat2, lon2) { const R = 6371; const toRad = Math.PI/180; const dLat = (lat2-lat1)*toRad; const dLon = (lon2-lon1)*toRad; const a = Math.sin(dLat/2)**2 + Math.cos(lat1*toRad)*Math.cos(lat2*toRad)*Math.sin(dLon/2)**2; return 2*R*Math.asin(Math.sqrt(a)); }
        function pointInCircle(point, center, radiusMeters) { const dkm = haversineDistance(point[0], point[1], center[0], center[1]); return dkm*1000 <= radiusMeters; }

        /* UI helper */
        function popupHtml(p) {
            return `<div style="font-size:13px;line-height:1.4;min-width:170px;">
                <div style="font-weight:700;margin-bottom:6px;">Location Details</div>
                <div><strong>Speed:</strong> ${p.speed ?? '-'} km/h</div>
                <div><strong>Heading:</strong> ${p.heading ?? '-'}</div>
                <div><strong>Battery:</strong> ${p.battery_level ?? '-'}%</div>
                <div style="margin-top:6px;color:#555"><strong>Time:</strong> ${p.recorded_at ?? '-'}</div>
            </div>`;
        }

        /* Clear visuals
           IMPORTANT: do NOT clear drawnItems or geofenceLayer here —
           we want geofences to persist when reloading history */
        function clearRouteVisuals(){
            routeContainer.clearLayers();
            idleLayer.clearLayers();
            // Do not clear drawnItems or geofenceLayer here — these hold geofence shapes
            stopMarkers.forEach(m=>m.remove()); stopMarkers=[];
            if (moveMarker){ moveMarker.remove(); moveMarker=null; }
            // geofences array will be repopulated by loadGeofences when needed
        }

        /* --- GEOFENCE: Load, Render, Manage --- */
        async function loadGeofences(){
            // clear only geofence display layers, then repopulate
            geofenceLayer.clearLayers();
            drawnItems.clearLayers(); // clear then re-add to keep draw edit in sync
            geofences = [];
            document.getElementById('geofenceList').innerText = 'Loading...';
            document.getElementById('geofenceManageList').innerHTML = '';

            try {
                const r = await fetch(`${BASE_URL}/user/device/${deviceId}/geofences-json`);
                const raw = await r.json();
                const list = normalizeResponse(raw);

                if (!list.length) {
                    document.getElementById('geofenceList').innerText = 'No geofences';
                    document.getElementById('geofenceManageList').innerHTML = '<li style="color:#777">No geofences</li>';
                    return;
                }

                document.getElementById('geofenceList').innerHTML = '';
                document.getElementById('geofenceManageList').innerHTML = '';

                list.forEach(g => {
                    let layer = null;
                    if (g.type === 'polygon' && Array.isArray(g.coords)) {
                        layer = L.polygon(g.coords, { color:'#8e44ad', weight:2, opacity:0.85, fillOpacity:0.05 });
                        layer.addTo(geofenceLayer);
                    } else if (g.type === 'circle' && g.center && g.radius) {
                        layer = L.circle(g.center, { radius: g.radius, color:'#2980b9', weight:2, opacity:0.85, fillOpacity:0.03 });
                        layer.addTo(geofenceLayer);
                    }
                    if (layer) {
                        layer._geofenceId = g.id;
                        layer._geofenceMeta = g;
                        drawnItems.addLayer(layer); // IMPORTANT: add to drawnItems so Leaflet.draw can edit it
                        layer.bindPopup(`<strong>${g.name}</strong><br>${g.type === 'circle' ? 'Circle' : 'Polygon'}`);
                        geofences.push({ meta: g, layer: layer, type: g.type });
                    }
                    const div = document.createElement('div');
                    div.style.marginBottom = '6px';
                    div.innerHTML = `<strong>${g.name}</strong> <small style="color:#666">(${g.type})</small>`;
                    document.getElementById('geofenceList').appendChild(div);

                    const li = document.createElement('li');
                    li.style.marginBottom = '6px';
                    li.innerHTML = `
            <strong>${g.name}</strong> <small style="color:#666">(${g.type})</small>
            <div style="float:right">
                <button class="btn btn-sm btn-outline-secondary" onclick="zoomToGeofence(${g.id})">Zoom</button>
                <button class="btn btn-sm btn-outline-primary" onclick="startEditGeofence(${g.id})">Edit</button>
                <button class="btn btn-sm btn-outline-danger" onclick="deleteGeofence(${g.id})">Delete</button>
            </div>
        `;
                    document.getElementById('geofenceManageList').appendChild(li);
                });

            } catch (e) {
                console.warn('loadGeofences failed', e);
                document.getElementById('geofenceList').innerText = 'Error loading geofences';
                document.getElementById('geofenceManageList').innerHTML = '<li style="color:#c00">Error loading</li>';
            }
        }

        function zoomToGeofence(id) {
            const item = geofences.find(g => g.meta.id === id);
            if (!item) return alert('Geofence not found on map');
            const layer = item.layer;
            if (layer.getBounds) map.fitBounds(layer.getBounds(), { padding:[30,30], maxZoom:18 });
            else if (layer.getLatLng) map.panTo(layer.getLatLng());
            layer.openPopup && layer.openPopup();
        }

        async function deleteGeofence(id) {
            if (!confirm('Delete this geofence?')) return;
            try {
                await fetch(`${BASE_URL}/user/geofence/${id}`, {
                    method: 'DELETE',
                    headers: {
                        'X-CSRF-TOKEN': CSRF_TOKEN
                    }
                });
                alert('Deleted');
                await loadGeofences();
            } catch (e) {
                console.warn('delete failed', e);
                alert('Delete failed');
            }
        }

        function startEditGeofence(id) {
            const item = geofences.find(g => g.meta.id === id);
            if (!item) return alert('Geofence not found');
            const layer = item.layer;
            if (layer.getBounds) map.fitBounds(layer.getBounds(), { padding:[30,30] });
            else if (layer.getLatLng) map.panTo(layer.getLatLng());
            if (layer.editing && layer.editing.enable) {
                layer.editing.enable();
                alert('Edit the shape on map, then click the Edit toolbar "Save" (pencil icon) to persist changes.');
            } else {
                alert('Use the Edit button on the map toolbar to modify this geofence, then click Save.');
            }
        }

        /* --- Leaflet.draw handlers (CREATED / EDITED / DELETED) --- */
        map.on(L.Draw.Event.CREATED, async function (e) {
            const layer = e.layer;
            const type = e.layerType; // 'polygon' or 'circle'
            const name = prompt('Geofence name:', 'New Geofence') || 'New Zone';

            let payload = { _token: CSRF_TOKEN, name, type: null, coords: null, center: null, radius: null };

            if (type === 'polygon') {
                payload.type = 'polygon';
                const latlngs = layer.getLatLngs()[0] || layer.getLatLngs();
                payload.coords = latlngs.map(p => [p.lat, p.lng]);
            } else if (type === 'circle') {
                payload.type = 'circle';
                const c = layer.getLatLng();
                payload.center = [c.lat, c.lng];
                payload.radius = Math.round(layer.getRadius());
            } else {
                return;
            }

            // optimistic add
            drawnItems.addLayer(layer);
            geofenceLayer.addLayer(layer);

            try {
                const res = await fetch(`${BASE_URL}/user/device/${deviceId}/geofences-save`, {
                    method: 'POST',
                    headers: { 'Content-Type':'application/json', 'X-CSRF-TOKEN': CSRF_TOKEN },
                    body: JSON.stringify(payload)
                });
                const json = await res.json();
                if (json && json.id) {
                    layer._geofenceId = json.id;
                    layer._geofenceMeta = json;
                    layer.bindPopup(`<strong>${payload.name}</strong><br>${payload.type}`);
                } else {
                    // fallback: reload all geofences
                    await loadGeofences();
                }
                await loadGeofences();
                alert('Geofence saved');
            } catch (err) {
                console.warn('save geofence failed', err);
                alert('Failed to save geofence');
                drawnItems.removeLayer(layer);
                geofenceLayer.removeLayer(layer);
            }
        });

        map.on('draw:edited', async function (e) {
            const layers = e.layers;
            const updated = [];
            layers.eachLayer(function (layer) {
                const id = layer._geofenceId;
                if (!id) return;
                if (layer instanceof L.Circle) {
                    const c = layer.getLatLng();
                    updated.push({ id, type:'circle', center:[c.lat, c.lng], radius: Math.round(layer.getRadius()) });
                } else if (layer instanceof L.Polygon) {
                    const latlngs = layer.getLatLngs()[0] || layer.getLatLngs();
                    updated.push({ id, type:'polygon', coords: latlngs.map(p => [p.lat, p.lng]) });
                }
            });

            for (const u of updated) {
                try {
                    await fetch(`${BASE_URL}/user/geofence/${u.id}/update`, {
                        method: 'POST',
                        headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': CSRF_TOKEN },
                        body: JSON.stringify(u)
                    });
                } catch (err) {
                    console.warn('update failed', err);
                }
            }

            if (updated.length) {
                alert('Geofence(s) updated');
                await loadGeofences();
            }
        });

        map.on('draw:deleted', async function (e) {
            const layers = e.layers;
            const toDelete = [];
            layers.eachLayer(function(layer){
                if (layer._geofenceId) toDelete.push(layer._geofenceId);
            });

            for (const id of toDelete) {
                try {
                    await fetch(`${BASE_URL}/user/geofence/${id}`, {
                        method: 'DELETE',
                        headers: { 'X-CSRF-TOKEN': CSRF_TOKEN }
                    });
                } catch (err) {
                    console.warn('delete failed', err);
                }
            }

            if (toDelete.length) {
                alert('Geofence(s) deleted');
                await loadGeofences();
            }
        });

        /* ----------------------------------------
           SPEED HEATMAP + LIVE TRAIL + SMART ZOOM
           (rest of your code preserved)
           ---------------------------------------- */

        // Global settings
        const GOOGLE_ROADS_KEY = "AIzaSyAiLSCqey78lHh_6Zq7pg1qd_04384bPmo"; // keep yours
        const MAX_SNAP_DISTANCE_KM = 0.12; // 120m
        const MAX_JUMP_DISTANCE_KM = 0.30;   // 300m (ignore big diagonal roads)
        const LIVE_TRAIL_MAX_POINTS = 90;
        const LIVE_TRAIL_POINT_TTL_MS = 20 * 60 * 1000; // 20 minutes
        const HEATMAP_SPEED_BOUNDS = { min: 0, max: 120 };

        function speedToColor(speed) {
            const s = Math.max(HEATMAP_SPEED_BOUNDS.min, Math.min(HEATMAP_SPEED_BOUNDS.max, speed || 0));
            const t = (s - HEATMAP_SPEED_BOUNDS.min) / (HEATMAP_SPEED_BOUNDS.max - HEATMAP_SPEED_BOUNDS.min);
            if (t < 0.5) {
                const p = t / 0.5;
                const r = Math.round(46 + (255 - 46) * p);
                const g = Math.round(204 + (255 - 204) * p);
                const b = Math.round(113 - 113 * p);
                return `rgb(${r},${g},${b})`;
            } else {
                const p = (t - 0.5) / 0.5;
                const r = 255;
                const g = Math.round(255 - (255 - 126) * p);
                const b = 0;
                return `rgb(${r},${g},${b})`;
            }
        }

        async function snapToRoads(points) {
            if (!points || points.length === 0) return points;
            const MAX_CHUNK = 100;
            let final = [];

            for (let i = 0; i < points.length; i += MAX_CHUNK) {
                const chunk = points.slice(i, i + MAX_CHUNK);
                const path = chunk.map(p => `${p[0]},${p[1]}`).join("|");
                const url = `https://roads.googleapis.com/v1/snapToRoads?path=${encodeURIComponent(path)}&interpolate=true&key=${GOOGLE_ROADS_KEY}`;

                try {
                    const res = await fetch(url);
                    const json = await res.json();
                    if (!json.snappedPoints) {
                        console.warn("Google Roads returned no snapped points for chunk", i);
                        final.push(...chunk);
                        continue;
                    }

                    const snappedChunk = [];
                    json.snappedPoints.forEach(sp => {
                        snappedChunk.push([sp.location.latitude, sp.location.longitude]);
                    });

                    final.push(...snappedChunk);

                } catch (err) {
                    console.warn("Google Roads request failed: ", err);
                    final.push(...chunk); // fallback
                }
            }

            const mapped = [];
            for (let i = 0; i < points.length; i++) {
                const raw = points[i];
                let best = null, bestD = Infinity;
                for (let j = 0; j < final.length; j++) {
                    const cand = final[j];
                    const d = haversineDistance(raw[0], raw[1], cand[0], cand[1]);
                    if (d < bestD) { bestD = d; best = cand; }
                }
                if (bestD > MAX_SNAP_DISTANCE_KM) mapped.push(raw);
                else mapped.push(best);
            }

            const cleaned = [mapped[0]];
            for (let i = 1; i < mapped.length; i++) {
                const a = cleaned[cleaned.length - 1];
                const b = mapped[i];
                const jump = haversineDistance(a[0], a[1], b[0], b[1]);
                if (jump > MAX_JUMP_DISTANCE_KM) {
                    continue;
                }
                cleaned.push(b);
            }

            return cleaned;
        }

        function drawSpeedHeatmap(layer, dataPoints) {
            for (let i = 1; i < dataPoints.length; i++) {
                const a = dataPoints[i-1];
                const b = dataPoints[i];
                const color = speedToColor(parseFloat(b.speed || 0));
                L.polyline([[a.lat, a.lng], [b.lat, b.lng]], { color, weight: 5, opacity: 0.95 }).addTo(layer);
            }
        }

        function addArrowsToRoute(coords) {
            try {
                const base = L.polyline(coords);
                const arrowSymbol = (L.Symbol && L.Symbol.arrowHead) ? L.Symbol.arrowHead({
                    pixelSize:8, polygon:true, pathOptions:{ color:"#ffffff", weight:0, fillOpacity:1 }
                }) : null;
                if (arrowSymbol) L.polylineDecorator(base, { patterns:[ { offset:'5%', repeat:'12%', symbol:arrowSymbol } ] }).addTo(routeContainer);
            } catch (e) {
                console.warn("polylineDecorator missing or failed:", e);
            }
        }

        let liveTrailPoints = [];
        let liveTrailLayer = L.layerGroup().addTo(map);

        function pushToLiveTrail(lat, lng) {
            const now = Date.now();
            liveTrailPoints.push({ lat, lng, t: now });
            const cutoff = now - LIVE_TRAIL_POINT_TTL_MS;
            liveTrailPoints = liveTrailPoints.filter(p => p.t >= cutoff);
            if (liveTrailPoints.length > LIVE_TRAIL_MAX_POINTS) liveTrailPoints.splice(0, liveTrailPoints.length - LIVE_TRAIL_MAX_POINTS);
            redrawLiveTrail();
        }

        function redrawLiveTrail() {
            liveTrailLayer.clearLayers();
            if (liveTrailPoints.length < 2) return;
            for (let i = 1; i < liveTrailPoints.length; i++) {
                const a = liveTrailPoints[i-1], b = liveTrailPoints[i];
                const ageRatio = i / liveTrailPoints.length;
                const opacity = 0.9 * ageRatio;
                L.polyline([[a.lat,a.lng],[b.lat,b.lng]], { color: "#00E0FF", weight: 4, opacity, interactive:false, className:'leaflet-live-trail' }).addTo(liveTrailLayer);
            }
        }

        function smartZoomOnSpeed(speed) {
            const minZoom = 12;
            const maxZoom = 18;
            const s = Math.max(0, Math.min(HEATMAP_SPEED_BOUNDS.max, speed || 0));
            const ratio = 1 - (s / HEATMAP_SPEED_BOUNDS.max);
            const z = minZoom + (maxZoom - minZoom) * ratio;
            map.setZoom(Math.round(z));
        }

        function processRouteData(data) {
            const summary = {
                distanceKm: 0,
                durationSec: 0,
                avgSpeedKmh: 0,
                maxSpeedKmh: 0,   // ✅ ADD
                idleEvents: 0,
                idleTotalSec: 0
            };

            if (!data || data.length < 2) return summary;

            let lastIdleStart = null;
            let idleEvents = 0, idleTotal = 0;
            let maxSpeed = 0;

            for (let i = 1; i < data.length; i++) {
                const a = data[i - 1];
                const b = data[i];

                const lat1 = +a.lat, lon1 = +a.lng;
                const lat2 = +b.lat, lon2 = +b.lng;

                summary.distanceKm += haversineDistance(lat1, lon1, lat2, lon2);

                const spd = parseFloat(b.speed ?? 0);
                if (spd > maxSpeed) maxSpeed = spd; // ✅ TRACK MAX

                const t1 = a.recorded_at ? new Date(a.recorded_at).getTime() / 1000 : null;
                const t2 = b.recorded_at ? new Date(b.recorded_at).getTime() / 1000 : null;

                if (spd <= IDLE_SPEED_THRESHOLD) {
                    if (lastIdleStart === null) lastIdleStart = t1 || t2;
                } else {
                    if (lastIdleStart !== null) {
                        const idleSec = (t2 && lastIdleStart) ? (t2 - lastIdleStart) : 0;

                        if (idleSec >= IDLE_TIME_THRESHOLD_SECONDS) {
                            idleEvents++;
                            idleTotal += idleSec;
                        }
                        lastIdleStart = null;
                    }
                }
            }

            const tStart = data[0].recorded_at ? new Date(data[0].recorded_at).getTime() / 1000 : null;
            const tEnd = data[data.length - 1].recorded_at ? new Date(data[data.length - 1].recorded_at).getTime() / 1000 : null;

            if (tStart && tEnd && tEnd > tStart) {
                summary.durationSec = tEnd - tStart;
                summary.avgSpeedKmh = summary.distanceKm / (summary.durationSec / 3600);
            }

            summary.maxSpeedKmh = maxSpeed;        // ✅ ASSIGN
            summary.idleEvents = idleEvents;
            summary.idleTotalSec = idleTotal;

            return summary;
        }


        function processRouteData_old(data) {
            const summary = { distanceKm:0, durationSec:0, avgSpeedKmh:0,maxSpeedKmh: 0, idleEvents:0, idleTotalSec:0 };
            if(!data || data.length<2) return summary;
            let lastIdleStart=null, idleEvents=0, idleTotal=0;
            for(let i=1;i<data.length;i++){
                const a=data[i-1], b=data[i];
                const lat1=parseFloat(a.lat), lon1=parseFloat(a.lng), lat2=parseFloat(b.lat), lon2=parseFloat(b.lng);
                const segKm = haversineDistance(lat1, lon1, lat2, lon2);
                summary.distanceKm += segKm;
                const spd = parseFloat(b.speed ?? 0);
                const t1 = a.recorded_at ? new Date(a.recorded_at).getTime()/1000 : null;
                const t2 = b.recorded_at ? new Date(b.recorded_at).getTime()/1000 : null;
                if(spd <= IDLE_SPEED_THRESHOLD) {
                    if(lastIdleStart === null) lastIdleStart = t1 || t2;
                } else {
                    if(lastIdleStart !== null) {
                        const idleSec = (t1 ? (t1 - lastIdleStart) : 0);
                        if(idleSec >= IDLE_TIME_THRESHOLD_SECONDS) {
                            idleEvents++; idleTotal += idleSec;
                            const midLat = (lat1+lat2)/2, midLng = (lon1+lon2)/2;
                            L.marker([midLat, midLng], { icon: L.divIcon({ className:'idle-marker', iconSize:[16,16] }) }).bindPopup(`<strong>Idle</strong><br>Duration: ${Math.round(idleSec/60)}m`).addTo(idleLayer);
                        }
                        lastIdleStart = null;
                    }
                }
            }
            if(lastIdleStart !== null) {
                const lastT = data[data.length-1].recorded_at ? new Date(data[data.length-1].recorded_at).getTime()/1000 : null;
                if(lastT && lastIdleStart) {
                    const idleSec = lastT - lastIdleStart;
                    if(idleSec >= IDLE_TIME_THRESHOLD_SECONDS) {
                        idleEvents++; idleTotal += idleSec;
                        const lastP = data[data.length-1];
                        L.marker([lastP.lat, lastP.lng], { icon: L.divIcon({ className:'idle-marker', iconSize:[16,16] }) }).bindPopup(`<strong>Idle</strong><br>Duration: ${Math.round(idleSec/60)}m`).addTo(idleLayer);
                    }
                }
            }
            const tStart = data[0].recorded_at ? new Date(data[0].recorded_at).getTime()/1000 : null;
            const tEnd   = data[data.length-1].recorded_at ? new Date(data[data.length-1].recorded_at).getTime()/1000 : null;
            if(tStart && tEnd && tEnd > tStart) {
                summary.durationSec = tEnd - tStart;
                summary.avgSpeedKmh = summary.distanceKm / (summary.durationSec/3600) || 0;
            }
            summary.idleEvents = idleEvents;
            summary.idleTotalSec = idleTotal;
            return summary;
        }

        function addMarkersAndMoving(data) {
            const first = data[0];
            const last  = data[data.length - 1];

            L.marker([first.lat, first.lng], {
                icon: L.icon({
                    iconUrl: "{{ asset('images/start.png') }}",
                    iconSize: [55,55],
                    iconAnchor: [27,27]
                })
            }).addTo(routeContainer);

            data.forEach((p, index) => {
                if (index === data.length - 1) return;
                const marker = L.circleMarker([p.lat, p.lng], {
                    radius: 4,
                    fillColor: "#00E0FF",
                    color: "#006688",
                    fillOpacity: 0.85
                });
                marker.bindPopup(popupHtml(p));
                marker.addTo(routeContainer);
                stopMarkers.push(marker);
            });

            if (moveMarker) moveMarker.remove();

            if (parseInt(last.speed) === 0) {
                const html = `
            <div class="stop-marker-wrapper">
                <span class="stop-glow"></span>
                <img src="{{ asset('images/stop.svg') }}" class="stop-animated-icon" />
            </div>
        `;
                moveMarker = L.marker([last.lat, last.lng], {
                    icon: L.divIcon({
                        html,
                        className: "stop-marker-container",
                        iconSize: [55,55],
                        iconAnchor: [27,48]
                    }),
                    zIndexOffset: 9999
                }).addTo(routeContainer);
            } else {
                moveMarker = L.marker([last.lat, last.lng], {
                    icon: L.icon({
                        iconUrl: "{{ asset('images/navy-arrow.png') }}",
                        iconSize: [50,50],
                        iconAnchor: [25,25]
                    }),
                    zIndexOffset: 9999
                }).addTo(routeContainer);
                moveMarker.setRotationAngle(((last.heading ?? 0)));
            }

            if (moveMarker && moveMarker.bringToFront) moveMarker.bringToFront();
        }

        function fitMapToRouteIfAny(){
            const layers = routeContainer.getLayers().filter(l => l.getBounds);
            if(!layers.length) return;
            const group = L.featureGroup(layers);
            const bounds = group.getBounds();
            if(bounds.isValid()) map.fitBounds(bounds, { padding:[40,40], maxZoom:18 });
        }

        function checkGeofenceForPoint(lat, lng) {
            const point = [parseFloat(lat), parseFloat(lng)];
            let insideAny = false;

            geofences.forEach(g => {
                if (g.type === 'polygon') {
                    const coords = g.meta.coords;
                    if (pointInPolygon(point, coords)) {
                        insideAny = true;
                        g.layer.setStyle({ color: '#27ae60', fillOpacity: 0.12 });
                    } else {
                        g.layer.setStyle({ color: '#8e44ad', fillOpacity: 0.05 });
                    }
                }
                else if (g.type === 'circle') {
                    const center = g.meta.center;
                    if (pointInCircle(point, center, g.meta.radius)) {
                        insideAny = true;
                        g.layer.setStyle({ color: '#27ae60', fillOpacity: 0.12 });
                    } else {
                        g.layer.setStyle({ color: '#2980b9', fillOpacity: 0.03 });
                    }
                }
            });

            return insideAny;
        }

        async function loadHistory(from = null, to = null) {
            from = normalizeDateParam(from);
            to   = normalizeDateParam(to);

            let url = `${BASE_URL}/user/device/${deviceId}/history-json`;
            if (from && to) url += `?from=${from}&to=${to}`;

            const r = await fetch(url);
            const raw = await r.json();
            const data = normalizeResponse(raw);

            historyData = data;

            clearRouteVisuals();
            idleLayer.clearLayers();

            await loadGeofences();

            if (!data.length) {
                document.getElementById('playbackControls').style.display = 'none';
                return;
            }

            const summary = processRouteData(data);

            const rawPoints = data.map(p => [ parseFloat(p.lat), parseFloat(p.lng) ]);

            let snappedPoints = rawPoints;
            try {
                snappedPoints = await snapToRoads(rawPoints);
            } catch (e) {
                console.warn("Snap failed, using raw points", e);
                snappedPoints = rawPoints;
            }

            if (snappedPoints && snappedPoints.length > 1) {
                L.polyline(snappedPoints, { color: "#2b8cff", weight: 3, opacity: 0.6 }).addTo(routeContainer);
            }

            const ptsMeta = data.map(p => ({ lat: parseFloat(p.lat), lng: parseFloat(p.lng), speed: parseFloat(p.speed || 0) }));
            drawSpeedHeatmap(routeContainer, ptsMeta);

            if (snappedPoints && snappedPoints.length > 3) addArrowsToRoute(snappedPoints);

            addMarkersAndMoving(data);

            const last = data[data.length - 1];
            checkGeofenceForPoint(last.lat, last.lng);

            document.getElementById('playbackControls').style.display = 'flex';
            document.getElementById('totalDistance').innerText = summary.distanceKm.toFixed(2);
            const dur = summary.durationSec;
            document.getElementById('routeDuration').innerText = dur ? Math.floor(dur/3600) + 'h ' + Math.floor((dur%3600)/60) + 'm' : '-';
            document.getElementById('avgSpeed').innerText = summary.avgSpeedKmh ? summary.avgSpeedKmh.toFixed(1) : '-';
            document.getElementById('idleCount').innerText = summary.idleEvents;
            document.getElementById('idleTotal').innerText = summary.idleTotalSec ? Math.round(summary.idleTotalSec/60) + 'm' : '0m';

            document.getElementById('maxSpeed').innerText =
                summary.maxSpeedKmh > 0
                    ? summary.maxSpeedKmh.toFixed(1) + ' km/h'
                    : '0 km/h';


            const legend = document.getElementById('heatmapLegend');
            if (legend) legend.style.display = 'block';

            setTimeout(()=>{ map.invalidateSize(); fitMapToRouteIfAny(); }, 300);
        }

        /* Realtime */
        let lastRealtimePoint = null;

        function setupRealtime() {
            if (!window.Echo) {
                console.warn('Echo missing');
                return;
            }

            window.Echo.private(`device.${deviceId}`).listen('.DeviceLocationUpdated', (payload) => {

                const p = payload.location ?? payload;
                const lat = parseFloat(p.lat);
                const lng = parseFloat(p.lng);
                const newPoint = [lat, lng];
                const spd = parseFloat(p.speed ?? 0);

                if (lastRealtimePoint) {
                    const color = speedToColor(spd);
                    L.polyline([lastRealtimePoint, newPoint], { color, weight:4, opacity:0.95 }).addTo(routeContainer);
                }
                lastRealtimePoint = newPoint;

                if (parseInt(spd) === 0) {
                    if (moveMarker) moveMarker.remove();
                    const html = `<div class="stop-marker-wrapper"><span class="stop-glow"></span><img src="{{ asset('images/stop.svg') }}" class="stop-animated-icon" /></div>`;
                    moveMarker = L.marker([lat, lng], { icon: L.divIcon({ html, className:"stop-marker-container", iconSize:[55,55], iconAnchor:[27,48] }), zIndexOffset:9999 }).addTo(routeContainer);
                } else {
                    if (!moveMarker || (moveMarker && moveMarker._icon && moveMarker._icon.classList.contains('stop-marker-container'))) {
                        if (moveMarker) moveMarker.remove();
                        moveMarker = L.marker(newPoint, { icon: L.icon({ iconUrl: "{{ asset('images/navy-arrow.png') }}", iconSize:[50,50], iconAnchor:[25,25] }), zIndexOffset:9999 }).addTo(routeContainer);
                    } else {
                        smoothMoveMarkerTo(moveMarker, newPoint);
                    }
                    //moveMarker.setRotationAngle((p.heading ?? 0));
                    let angle = 0;

                    if (spd > 5 && Number.isFinite(p.heading)) {
                        // Use device heading when moving properly
                        angle = p.heading;
                    }
                    else if (lastRealtimePoint) {
                        // Calculate bearing from movement
                        angle = calculateBearing(
                            lastRealtimePoint[0],
                            lastRealtimePoint[1],
                            lat,
                            lng
                        );
                    }

                    moveMarker.setRotationAngle(angle);

                }

                pushToLiveTrail(lat, lng);
                checkGeofenceForPoint(lat, lng);
                updateSummary(p);

                if (followVehicle) {
                    map.panTo(newPoint);
                    smartZoomOnSpeed(spd);
                }
            });
        }

        function smoothMoveMarkerTo(marker,target,steps=20,stepMs=35){
            if(!marker) return;
            const start = marker.getLatLng(), end = L.latLng(target);
            let i=0; const dLat=(end.lat-start.lat)/steps, dLng=(end.lng-start.lng)/steps;
            const t = setInterval(()=>{ i++; marker.setLatLng([start.lat+dLat*i, start.lng+dLng*i]); if(i>=steps) clearInterval(t); }, stepMs);
        }

        function updateSummary(p){ document.getElementById('lastSeen').innerText = p.recorded_at ? new Date(p.recorded_at).toLocaleString() : '-'; document.getElementById('curStatus').innerText = (p.online===1||p.online==='1'||p.online===true)?'Online':'Offline'; }

        document.getElementById('reverseBtn').addEventListener('click', async ()=>{
            if(!moveMarker) return;
            const pos = moveMarker.getLatLng(); const r = await fetch(`${BASE_URL}/user/device/${deviceId}/reverse-geocode?lat=${pos.lat}&lng=${pos.lng}`); const j = await r.json();
            document.getElementById('addressBox').innerText = j.address ?? 'Address not found';
        });

        let flatpickrInstance = null;
        (function initDateRange(){
            try {
                flatpickrInstance = flatpickr("#dateRange", {
                    mode: "range",
                    dateFormat: "Y-m-d",
                    allowInput: true,
                    onReady: function(selectedDates, dateStr, instance){ flatpickrInstance = instance; }
                });
            } catch(e) { console.warn('flatpickr init failed', e); flatpickrInstance = null; }
        })();

        function normalizeDateParam(v) {
            if(!v) return null;
            if (v instanceof Date) {
                const y = v.getFullYear(), m = String(v.getMonth()+1).padStart(2,'0'), d = String(v.getDate()).padStart(2,'0');
                return `${y}-${m}-${d}`;
            }
            return String(v);
        }

        document.getElementById('applyFilter').addEventListener('click', () => {
            let from=null, to=null;
            if (flatpickrInstance && Array.isArray(flatpickrInstance.selectedDates) && flatpickrInstance.selectedDates.length>=2) {
                from = flatpickrInstance.selectedDates[0]; to = flatpickrInstance.selectedDates[1];
            } else {
                const raw = (document.getElementById('dateRange').value || '').trim();
                const parts = raw.split(/\s+to\s+| - |—|,|;/).map(s=>s.trim()).filter(Boolean);
                if(parts.length>=2){ from=new Date(parts[0]); to=new Date(parts[1]); }
                else if(parts.length===1){ from=new Date(parts[0]); to=new Date(parts[0]); }
            }
            if(!from || !to || isNaN(from.getTime()) || isNaN(to.getTime())) return alert('Select a valid date range.');
            const fmt = d => `${d.getFullYear()}-${String(d.getMonth()+1).padStart(2,'0')}-${String(d.getDate()).padStart(2,'0')}`;
            loadHistory(fmt(from), fmt(to));
        });

        document.getElementById('btnRecenter').addEventListener('click', ()=>{ followVehicle=true; if(moveMarker) map.panTo(moveMarker.getLatLng()); });
        document.getElementById('btnLive').addEventListener('click', ()=>{ followVehicle=!followVehicle; document.getElementById('btnLive').style.background = followVehicle ? 'red':'rgba(0,0,0,0.5)'; });

        let mapTheme = 'light';

        document.getElementById('btnTheme').addEventListener('click', () => {

            // Remove all base + label layers first
            map.removeLayer(lightTiles);
            map.removeLayer(darkTiles);

            map.removeLayer(satelliteTiles);
            map.removeLayer(satelliteRoads);
            map.removeLayer(satellitePlaces);
            map.removeLayer(satelliteLabels); // ⭐ IMPORTANT

            if (mapTheme === 'light') {
                // → DARK
                darkTiles.addTo(map);
                mapTheme = 'dark';
                btnTheme.innerText = '🛰️';
            }
            else if (mapTheme === 'dark') {
                // → SATELLITE + LABELS
                satelliteTiles.addTo(map);
                satelliteRoads.addTo(map);
                satellitePlaces.addTo(map);
                satelliteLabels.addTo(map);   // ✅ ADD HERE ONLY

                mapTheme = 'satellite';
                btnTheme.innerText = '🗺️';
            }
            else {
                // → LIGHT
                lightTiles.addTo(map);
                mapTheme = 'light';
                btnTheme.innerText = '🌙';
            }
        });

        let playbackIndex = 0;
        let playbackTimer = null;
        let playbackPoints = [];
        let isPaused = false;

        function startPlayback() {
            // If playback is NOT paused → start fresh
            if (!isPaused) {
                playbackPoints = historyData.map(p => [ parseFloat(p.lat), parseFloat(p.lng) ]);
                playbackIndex = 0;
            }

            if (!playbackPoints.length) {
                document.getElementById('pbStatus').innerText = 'No Data';
                return;
            }

            document.getElementById('pbStatus').innerText = 'Playing';
            isPaused = false;

            clearInterval(playbackTimer);

            playbackTimer = setInterval(() => {
                if (playbackIndex >= playbackPoints.length) {
                    clearInterval(playbackTimer);
                    document.getElementById('pbStatus').innerText = 'Finished';
                    return;
                }

                const p = playbackPoints[playbackIndex];

                if (!moveMarker) {
                    moveMarker = L.marker(p, {
                        icon: L.icon({ iconUrl: "{{ asset('images/navy-arrow.png') }}", iconSize:[50,50], iconAnchor:[25,25] })
                    }).addTo(routeContainer);
                } else {
                    moveMarker.setLatLng(p);
                }

                map.panTo(p);
                playbackIndex++;
            }, 300);
        }

        function pausePlayback() {
            clearInterval(playbackTimer);
            document.getElementById('pbStatus').innerText = 'Paused';
            isPaused = true;
        }

        function stopPlayback() {
            clearInterval(playbackTimer);
            playbackIndex = 0;
            document.getElementById('pbStatus').innerText = 'Stopped';
            isPaused = false;
        }
        document.getElementById('pbStart').addEventListener('click', startPlayback);
        document.getElementById('pbPause').addEventListener('click', pausePlayback);
        document.getElementById('pbStop').addEventListener('click', stopPlayback);

        (async function init() { await loadGeofences(); await loadHistory(); setupRealtime(); })();

        //new function
        function calculateBearing(lat1, lng1, lat2, lng2) {
            const toRad = Math.PI / 180;
            const toDeg = 180 / Math.PI;

            const dLng = (lng2 - lng1) * toRad;

            const y = Math.sin(dLng) * Math.cos(lat2 * toRad);
            const x =
                Math.cos(lat1 * toRad) * Math.sin(lat2 * toRad) -
                Math.sin(lat1 * toRad) * Math.cos(lat2 * toRad) * Math.cos(dLng);

            let brng = Math.atan2(y, x) * toDeg;
            return (brng + 360) % 360;
        }

    </script>
@endpush

