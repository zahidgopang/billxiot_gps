(function (global) {
    'use strict';

    function animateCounter(el, target, suffix) {
        if (!el) return;
        const end = Number(target) || 0;
        const duration = 600;
        const start = performance.now();
        const from = 0;

        function tick(now) {
            const t = Math.min(1, (now - start) / duration);
            const eased = 1 - Math.pow(1 - t, 3);
            const val = Math.round(from + (end - from) * eased);
            el.textContent = suffix ? `${val}${suffix}` : String(val);
            if (t < 1) requestAnimationFrame(tick);
        }

        requestAnimationFrame(tick);
    }

    function initCounters(root) {
        root.querySelectorAll('[data-count]').forEach((el) => {
            animateCounter(el, el.dataset.count, el.dataset.suffix || '');
        });
    }

    function chartBase() {
        return {
            chart: {
                fontFamily: 'Inter, -apple-system, sans-serif',
                toolbar: { show: false },
                animations: { enabled: true, speed: 400 },
            },
            grid: { borderColor: '#E5E5EA', strokeDashArray: 4 },
            dataLabels: { enabled: false },
        };
    }

    function renderCharts(data) {
        if (!global.ApexCharts || !data) return;

        const donutEl = document.getElementById('udChartStatus');
        if (donutEl && data.statusDonut) {
            const s = data.statusDonut;
            new ApexCharts(donutEl, {
                ...chartBase(),
                chart: { ...chartBase().chart, type: 'donut', height: 280 },
                labels: ['Running', 'Parked', 'Idle', 'Offline'],
                series: [s.running, s.parked, s.idle, s.offline],
                colors: ['#34C759', '#007AFF', '#FF9F0A', '#8E8E93'],
                legend: { position: 'bottom', fontSize: '12px' },
                plotOptions: { pie: { donut: { size: '68%' } } },
            }).render();
        }

        const areaEl = document.getElementById('udChartActivity');
        if (areaEl && data.activityArea) {
            new ApexCharts(areaEl, {
                ...chartBase(),
                chart: { ...chartBase().chart, type: 'area', height: 280, sparkline: { enabled: false } },
                series: [{ name: 'Devices reporting', data: data.activityArea.values || [] }],
                xaxis: { categories: data.activityArea.labels || [], labels: { style: { fontSize: '11px' } } },
                colors: ['#007AFF'],
                fill: { type: 'gradient', gradient: { opacityFrom: 0.35, opacityTo: 0.02 } },
                stroke: { curve: 'smooth', width: 2 },
            }).render();
        }

        const barEl = document.getElementById('udChartAlerts');
        if (barEl && data.alertsBar) {
            new ApexCharts(barEl, {
                ...chartBase(),
                chart: { ...chartBase().chart, type: 'bar', height: 280 },
                series: [{ name: 'Alerts', data: data.alertsBar.values || [] }],
                xaxis: { categories: data.alertsBar.labels || [], labels: { style: { fontSize: '11px' } } },
                colors: ['#FF9F0A'],
                plotOptions: { bar: { borderRadius: 8, columnWidth: '55%' } },
            }).render();
        }

        const lineEl = document.getElementById('udChartPerformance');
        if (lineEl && data.performanceLine) {
            const p = data.performanceLine;
            new ApexCharts(lineEl, {
                ...chartBase(),
                chart: { ...chartBase().chart, type: 'line', height: 280 },
                series: [
                    { name: 'GPS pings', data: p.gpsPings || [] },
                    { name: 'Reporting devices', data: p.activeDevices || [] },
                ],
                xaxis: { categories: p.labels || [], labels: { rotate: -45, style: { fontSize: '10px' } } },
                colors: ['#007AFF', '#AF52DE'],
                stroke: { curve: 'smooth', width: 2 },
            }).render();
        }

        const weekEl = document.getElementById('udChartWeekly');
        if (weekEl && data.weeklyKm) {
            const w = data.weeklyKm;
            new ApexCharts(weekEl, {
                ...chartBase(),
                chart: { ...chartBase().chart, type: 'bar', height: 280 },
                series: [{ name: 'Distance (km)', data: w.values || [] }],
                xaxis: { categories: w.labels || [], labels: { style: { fontSize: '11px' } } },
                colors: ['#007AFF'],
                plotOptions: { bar: { borderRadius: 8, columnWidth: '55%' } },
                yaxis: { labels: { formatter: (v) => `${Math.round(v)} km` } },
            }).render();
        }
    }

    function initMiniMap(markers, apiKey) {
        const el = document.getElementById('udMiniMap');
        if (!el || !global.google?.maps || !markers?.length) return;

        const bounds = new google.maps.LatLngBounds();
        markers.forEach((m) => bounds.extend({ lat: m.lat, lng: m.lng }));

        const map = new google.maps.Map(el, {
            center: bounds.getCenter(),
            zoom: 11,
            disableDefaultUI: true,
            zoomControl: true,
            mapTypeControl: false,
            streetViewControl: false,
            fullscreenControl: false,
        });

        if (markers.length > 1) {
            map.fitBounds(bounds, 48);
        }

        markers.forEach((m) => {
            new google.maps.Marker({
                map,
                position: { lat: m.lat, lng: m.lng },
                title: m.name,
            });
        });
    }

    function initTableFilter() {
        const input = document.getElementById('udFleetSearch');
        const table = document.getElementById('udFleetTable');
        if (!input || !table) return;

        input.addEventListener('input', () => {
            const q = input.value.trim().toLowerCase();
            table.querySelectorAll('tbody tr').forEach((row) => {
                row.hidden = q !== '' && !row.textContent.toLowerCase().includes(q);
            });
        });
    }

    function boot() {
        const cfg = global.USER_DASHBOARD_CONFIG;
        if (!cfg) return;

        initCounters(document);
        renderCharts(cfg.charts);

        if (cfg.mapMarkers?.length && cfg.googleMapsKey) {
            const runMap = () => initMiniMap(cfg.mapMarkers, cfg.googleMapsKey);
            if (global.GoogleMapsPlatform?.loadMapsApi) {
                global.GoogleMapsPlatform.loadMapsApi({ googleMapsKey: cfg.googleMapsKey, libraries: [] })
                    .then(runMap)
                    .catch(() => {});
            } else if (global.google?.maps) {
                runMap();
            } else {
                global.initUdMiniMap = runMap;
            }
        }

        initTableFilter();

        document.getElementById('udRefreshActivity')?.addEventListener('click', () => {
            global.location.reload();
        });
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', boot);
    } else {
        boot();
    }
})(window);
