(function (global) {
    'use strict';

    const APEX_URL = 'https://cdn.jsdelivr.net/npm/apexcharts@3.49.1/dist/apexcharts.min.js';

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
            if (el.closest('[data-deferred]') && el.textContent.trim() === '—') {
                return;
            }
            animateCounter(el, el.dataset.count, el.dataset.suffix || '');
        });
    }

    function applyDeferredMetrics(metrics) {
        if (!metrics) return;

        const map = {
            distanceTodayKm: metrics.distanceTodayKm,
            totalDistanceKm: metrics.totalDistanceKm,
            activeAlerts: metrics.activeAlerts,
        };

        Object.keys(map).forEach((key) => {
            const val = map[key];
            document.querySelectorAll(`[data-deferred="${key}"]`).forEach((el) => {
                const target = Math.round(Number(val) || 0);
                if (el.hasAttribute('data-count')) {
                    el.dataset.count = String(target);
                    el.textContent = '0';
                    animateCounter(el, target, el.dataset.suffix || '');
                } else {
                    el.dataset.count = String(target);
                    el.textContent = '0';
                    animateCounter(el, target, '');
                }
            });
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

    function clearChartLoading() {
        document.querySelectorAll('.ud-chart--loading').forEach((el) => {
            el.classList.remove('ud-chart--loading');
            el.removeAttribute('aria-busy');
        });
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

        clearChartLoading();
    }

    function loadApexCharts() {
        return new Promise((resolve, reject) => {
            if (global.ApexCharts) {
                resolve();
                return;
            }
            const existing = document.querySelector('script[data-ud-apex]');
            if (existing) {
                existing.addEventListener('load', () => resolve(), { once: true });
                existing.addEventListener('error', () => reject(new Error('ApexCharts failed')), { once: true });
                return;
            }
            const script = document.createElement('script');
            script.src = APEX_URL;
            script.async = true;
            script.dataset.udApex = '1';
            script.onload = () => resolve();
            script.onerror = () => reject(new Error('ApexCharts failed'));
            document.head.appendChild(script);
        });
    }

    async function loadMetrics(url) {
        const res = await fetch(url, {
            credentials: 'same-origin',
            headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
        });
        if (!res.ok) {
            throw new Error('metrics failed');
        }
        return res.json();
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

        const globalSearch = document.getElementById('udGlobalSearch');
        if (globalSearch && globalSearch.value.trim()) {
            input.value = globalSearch.value;
            input.dispatchEvent(new Event('input', { bubbles: true }));
        }
    }

    async function boot() {
        const cfg = global.USER_DASHBOARD_CONFIG;
        if (!cfg) return;

        initCounters(document);
        initTableFilter();

        document.getElementById('udRefreshActivity')?.addEventListener('click', () => {
            global.location.reload();
        });

        if (!cfg.metricsUrl) {
            if (cfg.charts) {
                await loadApexCharts().catch(() => {});
                renderCharts(cfg.charts);
            }
            return;
        }

        try {
            const [metrics] = await Promise.all([
                loadMetrics(cfg.metricsUrl),
                loadApexCharts().catch(() => null),
            ]);
            applyDeferredMetrics(metrics);
            if (metrics.chartData && global.ApexCharts) {
                renderCharts(metrics.chartData);
            } else {
                clearChartLoading();
            }
        } catch (err) {
            console.warn('[user-dashboard] metrics load failed', err);
            clearChartLoading();
        }
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', boot);
    } else {
        boot();
    }
})(window);
