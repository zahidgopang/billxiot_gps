(function (global) {
    'use strict';

    const APEX_URL = 'https://cdn.jsdelivr.net/npm/apexcharts@3.49.1/dist/apexcharts.min.js';

    const STATUS_BUCKETS = [
        { key: 'running', label: 'Running', color: '#34C759' },
        { key: 'parked', label: 'Parked', color: '#007AFF' },
        { key: 'idle', label: 'Idle', color: '#FF9F0A' },
        { key: 'offline', label: 'Offline', color: '#8E8E93' },
    ];

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
                el.dataset.count = String(target);
                el.textContent = '0';
                animateCounter(el, target, el.dataset.suffix || '');
            });
        });
    }

    function markDeferredFailed() {
        document.querySelectorAll('[data-deferred]').forEach((el) => {
            if (el.textContent.trim() === '—') {
                el.dataset.count = '0';
                el.textContent = '0';
            }
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

    function statusSeries(donut) {
        const s = donut || {};
        return STATUS_BUCKETS.map((bucket) => ({
            ...bucket,
            value: Number(s[bucket.key]) || 0,
        }));
    }

    function renderCharts(data) {
        if (!global.ApexCharts || !data) {
            clearChartLoading();
            return;
        }

        const donutEl = document.getElementById('udChartStatus');
        if (donutEl && data.statusDonut && !donutEl.dataset.chartReady) {
            const buckets = statusSeries(data.statusDonut);
            // Drop zero slices so ApexCharts cannot remap colors/labels onto the wrong status.
            const active = buckets.filter((b) => b.value > 0);
            const chartSeries = active.length ? active.map((b) => b.value) : [1];
            const chartLabels = active.length ? active.map((b) => b.label) : ['No vehicles'];
            const chartColors = active.length ? active.map((b) => b.color) : ['#C7C7CC'];
            const total = buckets.reduce((sum, b) => sum + b.value, 0);

            new ApexCharts(donutEl, {
                ...chartBase(),
                chart: { ...chartBase().chart, type: 'donut', height: 280 },
                labels: chartLabels,
                series: chartSeries,
                colors: chartColors,
                legend: { show: false },
                plotOptions: {
                    pie: {
                        donut: {
                            size: '68%',
                            labels: {
                                show: true,
                                name: { show: true, fontSize: '13px', color: '#8E8E93' },
                                value: {
                                    show: true,
                                    fontSize: '22px',
                                    fontWeight: 700,
                                    color: '#1C1C1E',
                                    formatter(val) {
                                        return active.length ? String(val) : '0';
                                    },
                                },
                                total: {
                                    show: true,
                                    label: 'Fleet',
                                    fontSize: '13px',
                                    color: '#8E8E93',
                                    formatter() {
                                        return String(total);
                                    },
                                },
                            },
                        },
                    },
                },
                tooltip: {
                    y: {
                        formatter(val) {
                            if (!active.length) return '0';
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
        const controller = typeof AbortController !== 'undefined' ? new AbortController() : null;
        const timer = controller
            ? setTimeout(() => controller.abort(), 25000)
            : null;
        try {
            const res = await fetch(url, {
                credentials: 'same-origin',
                headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
                signal: controller ? controller.signal : undefined,
            });
            if (!res.ok) {
                throw new Error('metrics failed');
            }
            return await res.json();
        } finally {
            if (timer) clearTimeout(timer);
        }
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

        // Render Vehicle Status immediately from the same shell snapshot as the KPI cards.
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
            markDeferredFailed();
        }
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', boot);
    } else {
        boot();
    }
})(window);
