(function (global) {
    'use strict';

    let map;
    let geocoder;
    let startMarker;
    let destMarker;
    let routePolyline;
    let straightPolyline;
    let pickMode = 'start';
    let pickingCheckpointIndex = -1;
    let checkpoints = [];
    let labels = {};
    let directionsService;
    let directionsRequestId = 0;
    let drivingRouteTimer = null;
    let currentDrivingPath = [];
    let cfg = {};

    function init() {
        cfg = global.ROUTE_ADMIN_CONFIG || {};
        labels = cfg.labels || {};
        checkpoints = Array.isArray(cfg.checkpoints) ? cfg.checkpoints.slice() : [];
        currentDrivingPath = Array.isArray(cfg.guidedPolyline) ? cfg.guidedPolyline.slice() : [];
        renderCheckpointTable();
        document.getElementById('addCheckpointBtn')?.addEventListener('click', addCheckpointRow);
        document.getElementById('routeAdminForm')?.addEventListener('submit', syncCheckpointsField);
        document.getElementById('pickStartBtn')?.addEventListener('click', () => setPickMode('start'));
        document.getElementById('pickDestBtn')?.addEventListener('click', () => setPickMode('dest'));
        document.getElementById('showPolyline')?.addEventListener('change', scheduleDrivingRouteFetch);
        bootMap(cfg.googleMapsKey);
    }

    function isShowPolylineEnabled() {
        return document.getElementById('showPolyline')?.checked === true;
    }

    function setPolylineStatus(message, tone = 'muted') {
        const el = document.getElementById('routePolylineStatus');
        if (!el) return;
        if (!message) {
            el.hidden = true;
            el.textContent = '';
            return;
        }
        el.hidden = false;
        el.className = `small mt-2 text-${tone}`;
        el.textContent = message;
    }

    function setPickMode(mode, checkpointIndex = -1) {
        pickMode = mode;
        pickingCheckpointIndex = checkpointIndex;
        updatePickModeUi();
    }

    function updatePickModeUi() {
        document.querySelectorAll('.route-pick-btn').forEach((btn) => {
            btn.classList.toggle('active', btn.dataset.pickMode === pickMode && pickMode !== 'checkpoint');
        });

        const hint = document.getElementById('routePickHint');
        if (!hint) return;

        if (pickMode === 'start') {
            hint.textContent = labels.pickStart || 'Click the map or search to set the start point.';
        } else if (pickMode === 'dest') {
            hint.textContent = labels.pickDest || 'Click the map or search to set the destination.';
        } else if (pickMode === 'checkpoint' && pickingCheckpointIndex >= 0) {
            hint.textContent = (labels.pickCheckpoint || 'Picking checkpoint :n.').replace(':n', String(pickingCheckpointIndex + 1));
        }

        document.querySelectorAll('#checkpointsTable tbody tr').forEach((row, index) => {
            row.classList.toggle('table-primary', pickMode === 'checkpoint' && index === pickingCheckpointIndex);
        });
    }

    function bootMap(key) {
        if (!key) {
            showMapError('Google Maps API key is missing.');
            return;
        }

        if (global.google?.maps?.places) {
            initMap();
            return;
        }

        if (global.GoogleMapsPlatform?.load) {
            global.GoogleMapsPlatform.load({
                key,
                mapId: cfg.googleMapsMapId,
                libraries: ['marker', 'places'],
            }).then(initMap).catch(() => showMapError('Failed to load Google Maps.'));
            return;
        }

        const script = document.createElement('script');
        script.src = `https://maps.googleapis.com/maps/api/js?key=${encodeURIComponent(key)}&libraries=places&loading=async&v=weekly&callback=__routeAdminMapReady`;
        script.async = true;
        global.__routeAdminMapReady = () => initMap();
        script.onerror = () => showMapError('Failed to load Google Maps.');
        document.head.appendChild(script);
    }

    function showMapError(message) {
        const el = document.getElementById('routeAdminMap');
        if (el) {
            el.innerHTML = `<div class="p-3 text-danger small">${escapeHtml(message)}</div>`;
        }
    }

    function initMap() {
        const el = document.getElementById('routeAdminMap');
        if (!el || !global.google?.maps) return;

        const startLat = parseFloat(document.getElementById('routeStartLat')?.value || '21.4225');
        const startLng = parseFloat(document.getElementById('routeStartLng')?.value || '39.8262');
        const destLat = parseFloat(document.getElementById('routeDestLat')?.value || '24.4672');
        const destLng = parseFloat(document.getElementById('routeDestLng')?.value || '39.6111');

        map = new google.maps.Map(el, global.GoogleMapsPlatform?.mapOptions ? global.GoogleMapsPlatform.mapOptions({
            center: { lat: startLat, lng: startLng },
            zoom: 7,
            mapTypeControl: false,
            streetViewControl: false,
            fullscreenControl: true,
            zoomControl: true,
            scrollwheel: true,
            gestureHandling: 'greedy',
        }, cfg.googleMapsMapId) : {
            center: { lat: startLat, lng: startLng },
            zoom: 7,
            mapTypeControl: false,
            streetViewControl: false,
            fullscreenControl: true,
            zoomControl: true,
            scrollwheel: true,
            gestureHandling: 'greedy',
        });

        geocoder = new google.maps.Geocoder();

        const createMarker = global.VehicleMarker?.createMarker || global.GoogleMapsPlatform?.createMarker;
        const mk = (opts) => (createMarker ? createMarker(opts) : new google.maps.Marker(opts));

        startMarker = mk({
            map,
            position: { lat: startLat, lng: startLng },
            label: { text: 'S', color: '#fff', fontWeight: '700' },
            draggable: true,
            title: labels.pickStart || 'Start',
        });

        destMarker = mk({
            map,
            position: { lat: destLat, lng: destLng },
            label: { text: 'D', color: '#fff', fontWeight: '700' },
            draggable: true,
            title: labels.pickDest || 'Destination',
        });

        routePolyline = new google.maps.Polyline({
            map,
            strokeColor: '#2563eb',
            strokeWeight: 5,
            strokeOpacity: 0.95,
            path: [],
            zIndex: 2,
        });

        straightPolyline = new google.maps.Polyline({
            map,
            strokeColor: '#94a3b8',
            strokeWeight: 2,
            strokeOpacity: 0.45,
            path: [],
            zIndex: 1,
        });

        if (global.google.maps.DirectionsService) {
            directionsService = new google.maps.DirectionsService();
        }

        startMarker.addListener('dragend', () => {
            syncMarkerToInputs(startMarker, 'routeStartLat', 'routeStartLng');
            reverseGeocodeMarker(startMarker, 'routeStartCity');
        });
        destMarker.addListener('dragend', () => {
            syncMarkerToInputs(destMarker, 'routeDestLat', 'routeDestLng');
            reverseGeocodeMarker(destMarker, 'routeDestCity');
        });

        map.addListener('click', (e) => {
            if (pickMode === 'start') {
                startMarker.setPosition(e.latLng);
                syncMarkerToInputs(startMarker, 'routeStartLat', 'routeStartLng');
                reverseGeocodeMarker(startMarker, 'routeStartCity');
            } else if (pickMode === 'dest') {
                destMarker.setPosition(e.latLng);
                syncMarkerToInputs(destMarker, 'routeDestLat', 'routeDestLng');
                reverseGeocodeMarker(destMarker, 'routeDestCity');
            } else {
                const cpIndex = getActiveCheckpointIndex();
                if (cpIndex >= 0) {
                    const lat = e.latLng.lat();
                    const lng = e.latLng.lng();
                    applyCheckpointDestination(cpIndex, lat, lng, null);
                    reverseGeocodeLatLng(e.latLng, (name) => {
                        if (name && checkpoints[cpIndex]) {
                            checkpoints[cpIndex].to_location = name;
                            updateCheckpointRowInDom(cpIndex);
                        }
                    });
                }
            }
            if (pickMode !== 'checkpoint') {
                redrawPolyline();
                scheduleDrivingRouteFetch();
                fitMapToRoute();
            }
        });

        ['routeStartLat', 'routeStartLng', 'routeDestLat', 'routeDestLng'].forEach((id) => {
            document.getElementById(id)?.addEventListener('change', () => {
                syncInputsToMarkers();
                redrawPolyline();
                scheduleDrivingRouteFetch();
                fitMapToRoute();
            });
        });

        bindPlaceAutocomplete(document.getElementById('routeStartCity'), (place) => applyPlaceToStart(place));
        bindPlaceAutocomplete(document.getElementById('routeDestCity'), (place) => applyPlaceToDest(place));
        bindPlaceAutocomplete(document.getElementById('routeMapSearch'), (place) => applyPlaceToCurrentPickMode(place));

        preventEnterSubmit(document.getElementById('routeStartCity'));
        preventEnterSubmit(document.getElementById('routeDestCity'));
        preventEnterSubmit(document.getElementById('routeMapSearch'));

        updatePickModeUi();
        refreshRouteDisplay();
        if (currentDrivingPath.length >= 2 && isShowPolylineEnabled()) {
            setPolylineStatus(
                (labels.polylineLoaded || 'Driving route loaded')
                    + (cfg.guidedDistanceKm ? ` · ${Number(cfg.guidedDistanceKm).toFixed(1)} km` : ''),
                'success',
            );
        } else {
            scheduleDrivingRouteFetch();
        }
        bindMapResize();
    }

    function bindMapResize() {
        let timer;
        const onResize = () => {
            clearTimeout(timer);
            timer = setTimeout(() => {
                if (!map || !global.google?.maps) return;
                global.google.maps.event.trigger(map, 'resize');
                fitMapToRoute();
            }, 150);
        };
        window.addEventListener('resize', onResize);
        window.addEventListener('orientationchange', () => setTimeout(onResize, 300));
    }

    function preventEnterSubmit(input) {
        input?.addEventListener('keydown', (e) => {
            if (e.key === 'Enter') e.preventDefault();
        });
    }

    function bindPlaceAutocomplete(input, onPlace) {
        if (!input || !global.google?.maps?.places) return;

        const autocomplete = new google.maps.places.Autocomplete(input, {
            fields: ['geometry', 'formatted_address', 'name', 'address_components'],
        });

        if (map) {
            autocomplete.bindTo('bounds', map);
        }

        autocomplete.addListener('place_changed', () => {
            const place = autocomplete.getPlace();
            if (!place?.geometry?.location) return;
            onPlace(place);
        });
    }

    function placeLabel(place) {
        return place.name || place.formatted_address || '';
    }

    function applyPlaceToStart(place) {
        const loc = place.geometry.location;
        startMarker?.setPosition(loc);
        document.getElementById('routeStartLat').value = loc.lat().toFixed(6);
        document.getElementById('routeStartLng').value = loc.lng().toFixed(6);
        const cityInput = document.getElementById('routeStartCity');
        if (cityInput) {
            cityInput.value = placeLabel(place);
        }
        setPickMode('dest');
        redrawPolyline();
        scheduleDrivingRouteFetch();
        fitMapToRoute();
        map?.panTo(loc);
    }

    function applyPlaceToDest(place) {
        const loc = place.geometry.location;
        destMarker?.setPosition(loc);
        document.getElementById('routeDestLat').value = loc.lat().toFixed(6);
        document.getElementById('routeDestLng').value = loc.lng().toFixed(6);
        const cityInput = document.getElementById('routeDestCity');
        if (cityInput) {
            cityInput.value = placeLabel(place);
        }
        redrawPolyline();
        scheduleDrivingRouteFetch();
        fitMapToRoute();
        map?.panTo(loc);
    }

    function applyPlaceToCurrentPickMode(place) {
        const loc = place.geometry.location;
        const name = placeLabel(place);

        if (pickMode === 'start') {
            document.getElementById('routeStartCity').value = name;
            applyPlaceToStart(place);
            return;
        }

        if (pickMode === 'dest') {
            document.getElementById('routeDestCity').value = name;
            applyPlaceToDest(place);
            return;
        }

        if (pickMode === 'checkpoint' && pickingCheckpointIndex >= 0) {
            const cpIndex = pickingCheckpointIndex;
            if (checkpoints[cpIndex]) {
                applyCheckpointDestination(cpIndex, loc.lat(), loc.lng(), name);
                map?.panTo(loc);
            }
            return;
        }

        const cpIndex = getActiveCheckpointIndex();
        if (cpIndex >= 0) {
            applyCheckpointDestination(cpIndex, loc.lat(), loc.lng(), name);
            map?.panTo(loc);
            return;
        }

        map?.panTo(loc);
        map?.setZoom(12);
    }

    function reverseGeocodeMarker(marker, cityInputId) {
        reverseGeocodeLatLng(marker.getPosition(), (name) => {
            if (name) {
                const input = document.getElementById(cityInputId);
                if (input) input.value = name;
            }
        });
    }

    function localityFromGeocoderResult(result) {
        const preferred = ['locality', 'postal_town', 'administrative_area_level_2', 'sublocality', 'administrative_area_level_1'];
        for (const type of preferred) {
            const comp = result.address_components?.find((c) => c.types?.includes(type));
            if (comp?.long_name) return comp.long_name;
        }
        const formatted = String(result.formatted_address || '').trim();
        if (/^[23456789CFGHJMPQRVWX]{4,8}\+[23456789CFGHJMPQRVWX]{2,3}$/i.test(formatted)) {
            return '';
        }
        return formatted.split(',')[0]?.trim() || '';
    }

    function reverseGeocodeLatLng(latLng, callback) {
        if (!geocoder || !latLng) return;
        geocoder.geocode({ location: latLng }, (results, status) => {
            if (status === 'OK' && results?.[0]) {
                callback(localityFromGeocoderResult(results[0]) || results[0].formatted_address || '');
            }
        });
    }

    function syncMarkerToInputs(marker, latId, lngId) {
        const pos = marker.getPosition();
        document.getElementById(latId).value = pos.lat().toFixed(6);
        document.getElementById(lngId).value = pos.lng().toFixed(6);
        redrawPolyline();
        scheduleDrivingRouteFetch();
        suggestRouteTotals();
    }

    function syncInputsToMarkers() {
        const sLat = parseFloat(document.getElementById('routeStartLat')?.value);
        const sLng = parseFloat(document.getElementById('routeStartLng')?.value);
        const dLat = parseFloat(document.getElementById('routeDestLat')?.value);
        const dLng = parseFloat(document.getElementById('routeDestLng')?.value);

        if (Number.isFinite(sLat) && Number.isFinite(sLng)) {
            startMarker?.setPosition({ lat: sLat, lng: sLng });
        }
        if (Number.isFinite(dLat) && Number.isFinite(dLng)) {
            destMarker?.setPosition({ lat: dLat, lng: dLng });
        }
    }

    function buildPath() {
        const path = [];
        const sLat = parseFloat(document.getElementById('routeStartLat')?.value);
        const sLng = parseFloat(document.getElementById('routeStartLng')?.value);
        if (Number.isFinite(sLat) && Number.isFinite(sLng)) path.push({ lat: sLat, lng: sLng });
        checkpoints.forEach((cp) => {
            if (Number.isFinite(cp.to_lat) && Number.isFinite(cp.to_lng)) {
                path.push({ lat: cp.to_lat, lng: cp.to_lng });
            }
        });
        const dLat = parseFloat(document.getElementById('routeDestLat')?.value);
        const dLng = parseFloat(document.getElementById('routeDestLng')?.value);
        if (Number.isFinite(dLat) && Number.isFinite(dLng)) path.push({ lat: dLat, lng: dLng });
        return path;
    }

    function buildWaypointList() {
        return checkpoints
            .filter((cp) => Number.isFinite(cp.to_lat) && Number.isFinite(cp.to_lng))
            .map((cp) => ({
                location: { lat: cp.to_lat, lng: cp.to_lng },
                stopover: true,
            }));
    }

    function hasValidEndpoints() {
        const sLat = parseFloat(document.getElementById('routeStartLat')?.value);
        const sLng = parseFloat(document.getElementById('routeStartLng')?.value);
        const dLat = parseFloat(document.getElementById('routeDestLat')?.value);
        const dLng = parseFloat(document.getElementById('routeDestLng')?.value);
        return Number.isFinite(sLat) && Number.isFinite(sLng) && Number.isFinite(dLat) && Number.isFinite(dLng);
    }

    function scheduleDrivingRouteFetch() {
        clearTimeout(drivingRouteTimer);
        drivingRouteTimer = setTimeout(() => {
            fetchDrivingRoute();
        }, 450);
    }

    function refreshRouteDisplay() {
        redrawPolyline();
        fitMapToRoute();
    }

    async function fetchDrivingRoute() {
        if (!isShowPolylineEnabled()) {
            currentDrivingPath = [];
            setPolylineStatus(labels.polylineDisabled || 'Straight-line preview only', 'muted');
            refreshRouteDisplay();
            return;
        }

        if (!hasValidEndpoints()) {
            currentDrivingPath = [];
            setPolylineStatus('');
            refreshRouteDisplay();
            return;
        }

        const requestId = ++directionsRequestId;
        setPolylineStatus(labels.polylineLoading || 'Loading driving route…', 'primary');

        const clientPath = await fetchDrivingRouteFromClient(requestId);
        if (requestId !== directionsRequestId) return;

        if (clientPath?.path?.length >= 2) {
            applyDrivingRouteResult(clientPath);
            return;
        }

        const serverPath = await fetchDrivingRouteFromServer(requestId);
        if (requestId !== directionsRequestId) return;

        if (serverPath?.path?.length >= 2) {
            applyDrivingRouteResult(serverPath);
            return;
        }

        currentDrivingPath = [];
        setPolylineStatus(labels.polylineError || 'Could not load driving route', 'danger');
        refreshRouteDisplay();
    }

    function fetchDrivingRouteFromClient(requestId) {
        if (!directionsService || !global.google?.maps) {
            return Promise.resolve(null);
        }

        const sLat = parseFloat(document.getElementById('routeStartLat')?.value);
        const sLng = parseFloat(document.getElementById('routeStartLng')?.value);
        const dLat = parseFloat(document.getElementById('routeDestLat')?.value);
        const dLng = parseFloat(document.getElementById('routeDestLng')?.value);

        return new Promise((resolve) => {
            directionsService.route({
                origin: { lat: sLat, lng: sLng },
                destination: { lat: dLat, lng: dLng },
                waypoints: buildWaypointList(),
                travelMode: google.maps.TravelMode.DRIVING,
                optimizeWaypoints: false,
            }, (result, status) => {
                if (requestId !== directionsRequestId) return resolve(null);
                if (status !== 'OK' || !result?.routes?.[0]) return resolve(null);
                resolve(extractDirectionsResult(result));
            });
        });
    }

    async function fetchDrivingRouteFromServer(requestId) {
        const url = cfg.directionsPreviewUrl;
        if (!url) return null;

        const sLat = parseFloat(document.getElementById('routeStartLat')?.value);
        const sLng = parseFloat(document.getElementById('routeStartLng')?.value);
        const dLat = parseFloat(document.getElementById('routeDestLat')?.value);
        const dLng = parseFloat(document.getElementById('routeDestLng')?.value);

        try {
            const res = await fetch(url, {
                method: 'POST',
                headers: {
                    Accept: 'application/json',
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': cfg.csrfToken || '',
                },
                body: JSON.stringify({
                    start_lat: sLat,
                    start_lng: sLng,
                    destination_lat: dLat,
                    destination_lng: dLng,
                    checkpoints,
                }),
            });
            if (requestId !== directionsRequestId) return null;
            const data = await res.json().catch(() => ({}));
            if (!data.success || !Array.isArray(data.vertices) || data.vertices.length < 2) return null;
            return {
                path: data.vertices,
                distanceKm: data.distance_km,
                durationMinutes: data.duration_minutes,
            };
        } catch (_) {
            return null;
        }
    }

    function extractDirectionsResult(result) {
        const route = result.routes[0];
        const path = [];
        let distanceM = 0;
        let durationS = 0;

        route.legs.forEach((leg) => {
            distanceM += leg.distance?.value || 0;
            durationS += leg.duration?.value || 0;
            leg.steps.forEach((step) => {
                step.path.forEach((latLng) => {
                    path.push({ lat: latLng.lat(), lng: latLng.lng() });
                });
            });
        });

        return {
            path,
            distanceKm: distanceM / 1000,
            durationMinutes: Math.max(1, Math.round(durationS / 60)),
        };
    }

    function applyDrivingRouteResult(result) {
        currentDrivingPath = result.path;
        const distInput = document.getElementById('routeExpectedDistance');
        const durInput = document.getElementById('routeExpectedDuration');
        if (distInput && result.distanceKm > 0) {
            distInput.value = Number(result.distanceKm).toFixed(1);
        }
        if (durInput && result.durationMinutes > 0) {
            durInput.value = String(result.durationMinutes);
        }
        setPolylineStatus(
            `${labels.polylineLoaded || 'Driving route loaded'} · ${Number(result.distanceKm).toFixed(1)} km · ${result.durationMinutes} ${labels.minutes || 'min'}`,
            'success',
        );
        refreshRouteDisplay();
    }

    function redrawPolyline() {
        const straightPath = buildPath();
        straightPolyline?.setPath(straightPath);
        straightPolyline?.setMap(isShowPolylineEnabled() ? map : null);

        const displayPath = isShowPolylineEnabled() && currentDrivingPath.length >= 2
            ? currentDrivingPath
            : straightPath;

        routePolyline?.setPath(displayPath);
        routePolyline?.setOptions({
            strokeColor: isShowPolylineEnabled() ? '#2563eb' : '#64748b',
            strokeWeight: isShowPolylineEnabled() ? 5 : 3,
            strokeOpacity: isShowPolylineEnabled() ? 0.95 : 0.7,
        });
    }

    function fitMapToRoute() {
        if (!map) return;
        const bounds = new google.maps.LatLngBounds();
        const path = isShowPolylineEnabled() && currentDrivingPath.length >= 2
            ? currentDrivingPath
            : buildPath();
        if (path.length === 0) return;
        path.forEach((p) => bounds.extend(p));
        if (path.length === 1) {
            map.setCenter(path[0]);
            map.setZoom(10);
            return;
        }
        map.fitBounds(bounds, getMapFitPadding());
    }

    function getMapFitPadding() {
        const narrow = global.matchMedia('(max-width: 575.98px)').matches;
        return narrow ? 32 : 48;
    }

    function haversineKm(lat1, lng1, lat2, lng2) {
        const toRad = (d) => (d * Math.PI) / 180;
        const r = 6371;
        const dLat = toRad(lat2 - lat1);
        const dLng = toRad(lng2 - lng1);
        const a = Math.sin(dLat / 2) ** 2
            + Math.cos(toRad(lat1)) * Math.cos(toRad(lat2)) * Math.sin(dLng / 2) ** 2;
        return r * 2 * Math.atan2(Math.sqrt(a), Math.sqrt(1 - a));
    }

    function suggestRouteTotals() {
        const distInput = document.getElementById('routeExpectedDistance');
        const durInput = document.getElementById('routeExpectedDuration');
        if (!distInput || !durInput) return;
        if (distInput.value && durInput.value) return;

        let totalKm = 0;
        const path = buildPath();
        for (let i = 1; i < path.length; i += 1) {
            totalKm += haversineKm(path[i - 1].lat, path[i - 1].lng, path[i].lat, path[i].lng);
        }

        if (totalKm <= 0) return;
        if (!distInput.value) {
            distInput.value = totalKm.toFixed(1);
        }
        if (!durInput.value) {
            durInput.value = String(Math.max(1, Math.round((totalKm / 80) * 60)));
        }
    }

    function getActiveCheckpointIndex() {
        if (pickMode === 'checkpoint' && pickingCheckpointIndex >= 0) {
            return pickingCheckpointIndex;
        }
        if (checkpoints.length === 0) {
            return -1;
        }
        const lastIdx = checkpoints.length - 1;
        const last = checkpoints[lastIdx];
        if (!Number.isFinite(last.to_lat) || !Number.isFinite(last.to_lng)) {
            return lastIdx;
        }
        return lastIdx;
    }

    function updateCheckpointRowInDom(index) {
        const cp = checkpoints[index];
        if (!cp) return;
        const row = document.querySelector(`#checkpointsTable tbody tr[data-checkpoint-index="${index}"]`);
        if (!row) {
            renderCheckpointTable();
            return;
        }
        const fromInput = row.querySelector('[data-field="from_location"]');
        const toInput = row.querySelector('[data-field="to_location"]');
        const distInput = row.querySelector('[data-field="distance_km"]');
        const durInput = row.querySelector('[data-field="expected_duration_minutes"]');
        if (fromInput) fromInput.value = cp.from_location || '';
        if (toInput) toInput.value = cp.to_location || '';
        if (distInput) distInput.value = cp.distance_km ?? '';
        if (durInput) durInput.value = cp.expected_duration_minutes ?? '';
        updatePickModeUi();
    }

    function applyCheckpointDestination(index, lat, lng, locationName) {
        const cp = checkpoints[index];
        if (!cp || !Number.isFinite(lat) || !Number.isFinite(lng)) return;

        collectCheckpointsFromTable();
        chainCheckpointCoords();

        cp.to_lat = lat;
        cp.to_lng = lng;
        if (locationName) {
            cp.to_location = locationName;
        }

        chainCheckpointCoords();

        const fromLat = parseFloat(cp.from_lat);
        const fromLng = parseFloat(cp.from_lng);
        if (Number.isFinite(fromLat) && Number.isFinite(fromLng)) {
            cp.distance_km = Number(haversineKm(fromLat, fromLng, lat, lng).toFixed(2));
        }

        setPickMode('checkpoint', index);
        updateCheckpointRowInDom(index);
        redrawPolyline();
        scheduleDrivingRouteFetch();
        suggestRouteTotals();
        fitMapToRoute();
    }

    function chainCheckpointCoords() {
        let prevLat = parseFloat(document.getElementById('routeStartLat')?.value || 0);
        let prevLng = parseFloat(document.getElementById('routeStartLng')?.value || 0);
        checkpoints.forEach((cp, index) => {
            if (!Number.isFinite(cp.from_lat) || !Number.isFinite(cp.from_lng)) {
                cp.from_lat = prevLat;
                cp.from_lng = prevLng;
            }
            if (index > 0) {
                const prev = checkpoints[index - 1];
                cp.from_lat = prev.to_lat;
                cp.from_lng = prev.to_lng;
            }
            if (Number.isFinite(cp.to_lat) && Number.isFinite(cp.to_lng)) {
                prevLat = cp.to_lat;
                prevLng = cp.to_lng;
            }
        });
    }

    function renderCheckpointTable() {
        chainCheckpointCoords();
        const tbody = document.querySelector('#checkpointsTable tbody');
        if (!tbody) return;
        tbody.innerHTML = '';

        if (checkpoints.length === 0) {
            updatePickModeUi();
            return;
        }

        checkpoints.forEach((cp, index) => {
            const tr = document.createElement('tr');
            tr.dataset.checkpointIndex = String(index);
            tr.innerHTML = `
                <td>${index + 1}</td>
                <td><input class="form-control form-control-sm" data-field="from_location" data-index="${index}" value="${escapeAttr(cp.from_location || '')}" readonly tabindex="-1"></td>
                <td><input class="form-control form-control-sm route-checkpoint-to" data-field="to_location" data-index="${index}" value="${escapeAttr(cp.to_location || '')}" placeholder="${escapeAttr(labels.to || 'To')}"></td>
                <td><input class="form-control form-control-sm" type="number" step="0.01" data-field="distance_km" data-index="${index}" value="${cp.distance_km || ''}"></td>
                <td><input class="form-control form-control-sm" type="number" data-field="expected_duration_minutes" data-index="${index}" value="${cp.expected_duration_minutes || ''}"></td>
                <td class="text-nowrap">
                    <button type="button" class="btn btn-sm btn-outline-secondary" data-pick="${index}" title="${escapeAttr(labels.pickOnMap || 'Pick on map')}"><i class="fas fa-map-marker-alt"></i></button>
                    <button type="button" class="btn btn-sm btn-outline-danger" data-remove="${index}" title="${escapeAttr(labels.remove || 'Remove')}"><i class="fas fa-times"></i></button>
                </td>`;
            tbody.appendChild(tr);
        });

        tbody.querySelectorAll('[data-field]').forEach((input) => {
            input.addEventListener('change', () => {
                const idx = parseInt(input.dataset.index, 10);
                const field = input.dataset.field;
                checkpoints[idx][field] = input.type === 'number'
                    ? parseFloat(input.value || '0')
                    : input.value;
                chainCheckpointCoords();
                redrawPolyline();
                scheduleDrivingRouteFetch();
                suggestRouteTotals();
            });
        });

        tbody.querySelectorAll('.route-checkpoint-to').forEach((input) => {
            bindPlaceAutocomplete(input, (place) => {
                const idx = parseInt(input.dataset.index, 10);
                const loc = place.geometry.location;
                applyCheckpointDestination(idx, loc.lat(), loc.lng(), placeLabel(place));
                fitMapToRoute();
            });
            preventEnterSubmit(input);
        });

        tbody.querySelectorAll('[data-pick]').forEach((btn) => {
            btn.addEventListener('click', () => {
                setPickMode('checkpoint', parseInt(btn.dataset.pick, 10));
            });
        });

        tbody.querySelectorAll('[data-remove]').forEach((btn) => {
            btn.addEventListener('click', () => {
                checkpoints.splice(parseInt(btn.dataset.remove, 10), 1);
                checkpoints.forEach((cp, i) => { cp.sequence = i + 1; });
                if (pickMode === 'checkpoint') {
                    setPickMode('start');
                }
                renderCheckpointTable();
                redrawPolyline();
                scheduleDrivingRouteFetch();
                suggestRouteTotals();
            });
        });

        updatePickModeUi();
    }

    function escapeAttr(value) {
        return String(value ?? '')
            .replace(/&/g, '&amp;')
            .replace(/"/g, '&quot;')
            .replace(/</g, '&lt;');
    }

    function escapeHtml(value) {
        return escapeAttr(value);
    }

    function addCheckpointRow() {
        const prev = checkpoints[checkpoints.length - 1];
        const fromLat = prev ? prev.to_lat : parseFloat(document.getElementById('routeStartLat')?.value || 0);
        const fromLng = prev ? prev.to_lng : parseFloat(document.getElementById('routeStartLng')?.value || 0);
        checkpoints.push({
            sequence: checkpoints.length + 1,
            from_location: prev?.to_location || document.getElementById('routeStartCity')?.value || '',
            to_location: '',
            from_lat: fromLat,
            from_lng: fromLng,
            to_lat: null,
            to_lng: null,
            distance_km: 0,
            expected_duration_minutes: 0,
        });
        setPickMode('checkpoint', checkpoints.length - 1);
        renderCheckpointTable();
        redrawPolyline();
        scheduleDrivingRouteFetch();
    }

    function collectCheckpointsFromTable() {
        document.querySelectorAll('#checkpointsTable [data-field]').forEach((input) => {
            const idx = parseInt(input.dataset.index, 10);
            const field = input.dataset.field;
            if (!Number.isFinite(idx) || !field || !checkpoints[idx]) {
                return;
            }
            checkpoints[idx][field] = input.type === 'number'
                ? parseFloat(input.value || '0')
                : input.value;
        });
    }

    function syncCheckpointsField() {
        collectCheckpointsFromTable();
        chainCheckpointCoords();
        document.getElementById('routeCheckpointsJson').value = JSON.stringify(checkpoints);
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init);
    } else {
        init();
    }
})(window);
