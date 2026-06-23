/**
 * Admin locations table — live refresh for status, position, speed, last update.
 */
(function () {
    'use strict';

    const cfg = window.ADMIN_LOCATIONS_LIVE || {};
    const pollUrl = cfg.pollUrl || '';
    const pollMs = cfg.pollMs || 5000;
    const dash = cfg.dash || '—';
    const noData = cfg.noData || 'No data';
    const kmh = cfg.kmh || 'km/h';

    let pollTimer = null;
    let inFlight = false;

    function deviceIdsOnPage() {
        return Array.from(document.querySelectorAll('[data-device-id]'))
            .map((row) => parseInt(row.getAttribute('data-device-id'), 10))
            .filter((id) => id > 0);
    }

    function setStat(key, value) {
        const el = document.querySelector(`[data-stat="${key}"]`);
        if (el && el.textContent !== String(value)) {
            el.textContent = String(value);
            el.classList.add('stat-pulse');
            setTimeout(() => el.classList.remove('stat-pulse'), 600);
        }
    }

    function updateStats(stats) {
        if (!stats) {
            return;
        }
        setStat('totalDevices', stats.totalDevices ?? 0);
        setStat('onlineNow', stats.onlineNow ?? 0);
        setStat('running', stats.running ?? 0);
        setStat('offlineNow', stats.offlineNow ?? 0);
    }

    function formatPosition(lat, lng) {
        if (lat == null || lng == null) {
            return `<span class="text-muted">${dash}</span>`;
        }
        return `${Number(lat).toFixed(5)}, ${Number(lng).toFixed(5)}`;
    }

    function formatSpeed(speed) {
        if (speed == null) {
            return dash;
        }
        return `${Number(speed).toFixed(0)} ${kmh}`;
    }

    function updateRow(device) {
        const row = document.querySelector(`[data-device-id="${device.id}"]`);
        if (!row) {
            return;
        }

        const liveCell = row.querySelector('[data-field="live-status"]');
        const posCell = row.querySelector('[data-field="last-position"]');
        const speedCell = row.querySelector('[data-field="speed"]');
        const updateCell = row.querySelector('[data-field="last-update"]');

        if (liveCell && device.live_status) {
            const st = device.live_status;
            liveCell.innerHTML =
                `<span class="live-dot ${st.dot} me-1"></span>` +
                `<span class="badge ${st.class}">${st.label}</span>`;
        }

        if (posCell) {
            posCell.innerHTML = formatPosition(device.lat, device.lng);
        }

        if (speedCell) {
            speedCell.textContent = formatSpeed(device.speed);
        }

        if (updateCell) {
            updateCell.innerHTML = `<small>${device.recorded_at_human || noData}</small>`;
        }

        row.classList.add('row-updated');
        setTimeout(() => row.classList.remove('row-updated'), 700);
    }

    async function pollLive() {
        if (inFlight || !pollUrl) {
            return;
        }

        const ids = deviceIdsOnPage();
        if (!ids.length) {
            return;
        }

        inFlight = true;
        try {
            const res = await fetch(`${pollUrl}?ids=${ids.join(',')}`, {
                headers: {
                    Accept: 'application/json',
                    'X-Requested-With': 'XMLHttpRequest',
                },
                credentials: 'same-origin',
            });

            if (!res.ok) {
                return;
            }

            const data = await res.json();
            (data.devices || []).forEach(updateRow);
            updateStats(data.stats);
        } catch (err) {
            console.warn('[admin-locations] live poll failed', err);
        } finally {
            inFlight = false;
        }
    }

    function startPolling() {
        if (pollTimer) {
            clearInterval(pollTimer);
        }
        pollLive();
        pollTimer = setInterval(pollLive, pollMs);
    }

    function stopPolling() {
        if (pollTimer) {
            clearInterval(pollTimer);
            pollTimer = null;
        }
    }

    document.addEventListener('visibilitychange', () => {
        if (document.visibilityState === 'visible') {
            startPolling();
        } else {
            stopPolling();
        }
    });

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', startPolling);
    } else {
        startPolling();
    }
})();
