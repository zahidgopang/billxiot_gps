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
        if (!global.ApexCharts || !data) {
            clearChartLoading();
            return;
        }

        const donutEl = document.getElementById('udChartStatus');
        if (donutEl && data.statusDonut && !donutEl.dataset.chartReady) {
            const s = data.statusDonut;
            const series = [
                Number(s.running) || 0,
                Number(s.parked) || 0,
                Number(s.idle) || 0,
                Number(s.offline) || 0,
            ];
            // ApexCharts rejects all-zero donut series — show a neutral placeholder slice.
            const chartSeries = series.every((n) => n === 0) ? [1] : series;
            const chartLabels = series.every((n) => n === 0)
                ? ['No vehicles']
                : ['Running', 'Parked', 'Idle', 'Offline'];
            const chartColors = series.every((n) => n === 0)
                ? ['#C7C7CC']
                : ['#34C759', '#007AFF', '#FF9F0A', '#8E8E93'];

            new ApexCharts(donutEl, {
                ...chartBase(),
                chart: { ...chartBase().chart, type: 'donut', height: 280 },
                labels: chartLabels,
                series: chartSeries,
                colors: chartColors,
                legend: { position: 'bottom', fontSize: '12px' },
                plotOptions: { pie: { donut: { size: '68%' } } },
                tooltip: {
                    y: {
                        formatter(val, opts) {
                            if (series.every((n) => n === 0)) return '0';
                            return String(val);
                        },
                    },
                },
            }).render();
            donutEl.dataset.chartReady = '1';
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

        // Render Vehicle Status immediately from shell fleet counts (no wait on heavy metrics).
        try {
            await loadApexCharts();
            if (cfg.charts) {
                renderCharts(cfg.charts);
            }
        } catch (err) {
            console.warn('[user-dashboard] chart boot failed', err);
            clearChartLoading();
        }

        if (!cfg.metricsUrl) {
            return;
        }

        try {
            const metrics = await loadMetrics(cfg.metricsUrl);
            applyDeferredMetrics(metrics);
        } catch (err) {
            console.warn('[user-dashboard] metrics load failed', err);
        }
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', boot);
    } else {
        boot();
    }
})(window);
