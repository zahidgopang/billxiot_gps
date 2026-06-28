(function (global) {
    'use strict';
    const cfg = global.TRACKING_REPORTS_CONFIG;
    if (!cfg) return;

    function selectedIds() {
        return [...document.querySelectorAll('#gtReportVehicles input:checked')].map((el) => el.value);
    }

    function queryParams() {
        const p = new URLSearchParams();
        p.set('type', document.getElementById('gtReportType').value);
        p.set('from', document.getElementById('gtReportFrom').value);
        p.set('to', document.getElementById('gtReportTo').value);
        p.set('ids', selectedIds().join(','));
        return p;
    }

    function renderTable(data) {
        const head = document.getElementById('gtReportHead');
        const body = document.getElementById('gtReportBody');
        head.innerHTML = '';
        body.innerHTML = '';
        const type = data.type;
        const devices = data.devices || [];
        if (!devices.length) return;

        if (type === 'summary') {
            head.innerHTML = '<th>Vehicle</th><th>Distance km</th><th>Max speed</th><th>Stops</th><th>Duration s</th>';
            devices.forEach((d) => {
                body.innerHTML += `<tr><td>${d.device_name}</td><td>${d.total_distance_km}</td><td>${d.max_speed_kmh}</td><td>${d.stop_count}</td><td>${d.total_duration_seconds}</td></tr>`;
            });
        } else if (type === 'trips') {
            head.innerHTML = '<th>Vehicle</th><th>Start</th><th>End</th><th>Distance km</th><th>Duration s</th>';
            devices.forEach((d) => (d.trips || []).forEach((t) => {
                body.innerHTML += `<tr><td>${d.device_name}</td><td>${t.start_time || ''}</td><td>${t.end_time || ''}</td><td>${t.distance_km}</td><td>${t.duration_seconds}</td></tr>`;
            }));
        } else if (type === 'stops') {
            head.innerHTML = '<th>Vehicle</th><th>Start</th><th>Duration s</th><th>Lat</th><th>Lng</th>';
            devices.forEach((d) => (d.stops || []).forEach((s) => {
                body.innerHTML += `<tr><td>${d.device_name}</td><td>${s.start_display || s.start || ''}</td><td>${s.duration_seconds}</td><td>${s.lat}</td><td>${s.lng}</td></tr>`;
            }));
        } else if (type === 'events') {
            head.innerHTML = '<th>Vehicle</th><th>Time</th><th>Title</th><th>Message</th>';
            devices.forEach((d) => (d.events || []).forEach((e) => {
                body.innerHTML += `<tr><td>${d.device_name}</td><td>${e.time || ''}</td><td>${e.title || ''}</td><td>${e.message || ''}</td></tr>`;
            }));
        } else if (type === 'route' && devices[0]?.points?.length && cfg.googleMapsKey) {
            document.getElementById('gtReportMap').hidden = false;
            loadMap(devices[0].points);
            head.innerHTML = '<th>Vehicle</th><th>Points</th>';
            devices.forEach((d) => { body.innerHTML += `<tr><td>${d.device_name}</td><td>${d.point_count}</td></tr>`; });
        }
    }

    function loadMap(points) {
        const cb = '__gtReportMapReady';
        global[cb] = () => {
            const map = new google.maps.Map(document.getElementById('gtReportMap'), { zoom: 11, center: { lat: points[0].lat, lng: points[0].lng } });
            if (global.FleetMapRenderer) {
                const r = new global.FleetMapRenderer({ googleMaps: google, speedToColor: (s) => s > 80 ? '#ef4444' : s > 60 ? '#eab308' : '#22c55e' });
                r.attachMap(map);
                r.drawRoute(points);
            }
        };
        const s = document.createElement('script');
        s.src = `https://maps.googleapis.com/maps/api/js?key=${encodeURIComponent(cfg.googleMapsKey)}&callback=${cb}`;
        document.head.appendChild(s);
    }

    async function runReport() {
        const res = await fetch(`${cfg.generateUrl}?${queryParams()}&_=${Date.now()}`, { credentials: 'same-origin' });
        const data = await res.json();
        renderTable(data);
    }

    function exportFmt(fmt) {
        window.location.href = `${cfg.exportUrl}?${queryParams()}&format=${fmt}`;
    }

    document.getElementById('gtReportRun')?.addEventListener('click', runReport);
    document.getElementById('gtReportCsv')?.addEventListener('click', () => exportFmt('csv'));
    document.getElementById('gtReportXlsx')?.addEventListener('click', () => exportFmt('xlsx'));
    document.getElementById('gtReportPdf')?.addEventListener('click', () => exportFmt('pdf'));

    const today = new Date().toISOString().slice(0, 10);
    document.getElementById('gtReportFrom').value = today;
    document.getElementById('gtReportTo').value = today;
})(window);
