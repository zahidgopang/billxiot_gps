/**
 * Admin /locations table — Reverb live updates (status, position, speed, last seen).
 */
(function () {
    'use strict';

    const global = window;
    const cfg = global.ADMIN_LOCATIONS_LIVE || {};
    const pollUrl = cfg.pollUrl || '';
    const pollMs = Number(cfg.pollMs) > 0 ? Number(cfg.pollMs) : 10000;
    const reverbStaleMs = Number(cfg.reverbStaleMs) > 0 ? Number(cfg.reverbStaleMs) : 45000;
    const dash = cfg.dash || '—';
    const noData = cfg.noData || 'No data';
    const kmh = cfg.kmh || 'km/h';
    const justNow = cfg.justNow || 'Just now';

    let pollTimer = null;
    let pollInFlight = false;
    let realtimeHealthy = false;
    let lastReverbActivityAt = 0;
    let lastHttpHeartbeatAt = 0;
    let echoHooksBound = false;
    let reverbWatchTimer = null;
    let relativeTimeTimer = null;
    const echoChannels = new Map();
    /** @type {Map<number, object>} */
    const stateById = new Map();

    function deviceIdsOnPage() {
        return Array.from(document.querySelectorAll('[data-device-id]'))
            .map((row) => parseInt(row.getAttribute('data-device-id'), 10))
            .filter((id) => id > 0);
    }

    function presentMapStatus(key, label) {
        const presentation = {
            running: { class: 'bg-success', dot: 'bg-success' },
            moving: { class: 'bg-success', dot: 'bg-success' },
            idle: { class: 'bg-warning', dot: 'bg-warning' },
            stopped: { class: 'bg-warning', dot: 'bg-warning' },
            parked: { class: 'bg-info', dot: 'bg-info' },
            delayed: { class: 'bg-warning text-dark', dot: 'bg-warning' },
            stale: { class: 'bg-warning text-dark', dot: 'bg-warning' },
            offline: { class: 'bg-secondary', dot: 'bg-secondary' },
            alert: { class: 'bg-danger', dot: 'bg-danger' },
            blocked: { class: 'bg-dark', dot: 'bg-dark' },
        }[key] || { class: 'bg-secondary', dot: 'bg-secondary' };

        return {
            label: label || key,
            class: presentation.class,
            dot: presentation.dot,
            key,
        };
    }

    function formatRelativeTime(iso) {
        if (!iso) return noData;
        const date = iso instanceof Date ? iso : new Date(iso);
        if (Number.isNaN(date.getTime())) return noData;
        const sec = Math.floor((Date.now() - date.getTime()) / 1000);
        if (sec < 15) return justNow;
        if (sec < 60) return `${sec}s ago`;
        const min = Math.floor(sec / 60);
        if (min < 60) return min === 1 ? '1 min ago' : `${min} mins ago`;
        const hr = Math.floor(min / 60);
        if (hr < 24) return hr === 1 ? '1 hr ago' : `${hr} hrs ago`;
        const day = Math.floor(hr / 24);
        return day === 1 ? '1 day ago' : `${day} days ago`;
    }

    function seedStateFromDom() {
        document.querySelectorAll('[data-device-id]').forEach((row) => {
            const id = parseInt(row.getAttribute('data-device-id'), 10);
            if (!id) return;
            const key = row.getAttribute('data-status-key') || 'offline';
            const labelEl = row.querySelector('[data-field="live-status"] .badge');
            const label = labelEl?.textContent?.trim() || key;
            const recordedAt = row.getAttribute('data-recorded-at') || null;
            stateById.set(id, {
                id,
                status_key: key,
                live_status: presentMapStatus(key, label),
                recorded_at: recordedAt,
            });
        });
    }

    function formatPosition(lat, lng) {
        if (lat == null || lng == null) {
            return `<span class="text-muted">${dash}</span>`;
        }
        return `${Number(lat).toFixed(5)}, ${Number(lng).toFixed(5)}`;
    }

    function formatSpeed(speed) {
        if (speed == null) return dash;
        return `${Number(speed).toFixed(0)} ${kmh}`;
    }

    function updateRow(device) {
        const row = document.querySelector(`[data-device-id="${device.id}"]`);
        if (!row) return;

        const liveCell = row.querySelector('[data-field="live-status"]');
        const posCell = row.querySelector('[data-field="last-position"]');
        const speedCell = row.querySelector('[data-field="speed"]');
        const updateCell = row.querySelector('[data-field="last-update"]');

        if (liveCell && device.live_status) {
            const st = device.live_status;
            liveCell.innerHTML =
                `<span class="live-dot ${st.dot} me-1"></span>` +
                `<span class="badge ${st.class}">${st.label}</span>`;
            row.setAttribute('data-status-key', st.key || device.status_key || 'offline');
        }

        if (posCell) {
            posCell.innerHTML = formatPosition(device.lat, device.lng);
        }

        if (speedCell) {
            speedCell.textContent = formatSpeed(device.speed);
        }

        if (updateCell) {
            const human = device.recorded_at_human
                || formatRelativeTime(device.recorded_at);
            updateCell.innerHTML = `<small>${human}</small>`;
        }

        if (device.recorded_at) {
            row.setAttribute('data-recorded-at', device.recorded_at);
        }

        row.classList.add('row-updated');
        setTimeout(() => row.classList.remove('row-updated'), 700);
    }

    function applyReverbUpdate(deviceId, payload) {
        const loc = payload?.location && payload.location.lat != null
            ? payload.location
            : payload;
        if (!loc || loc.lat == null || loc.lng == null) return;

        lastReverbActivityAt = Date.now();
        const key = loc.status_key || 'offline';
        const label = loc.status || loc.status_label || key;
        const recordedAt = loc.recorded_at || loc.last_update || loc.timestamp || new Date().toISOString();

        const device = {
            id: deviceId,
            status_key: key,
            live_status: presentMapStatus(key, label),
            lat: Number(loc.lat),
            lng: Number(loc.lng),
            speed: loc.speed != null ? Math.round(Number(loc.speed)) : null,
            recorded_at: recordedAt,
            recorded_at_human: formatRelativeTime(recordedAt),
        };

        stateById.set(deviceId, device);
        updateRow(device);
        stopLivePolling();
    }

    function tickRelativeTimes() {
        stateById.forEach((device) => {
            if (!device.recorded_at) return;
            const row = document.querySelector(`[data-device-id="${device.id}"]`);
            const updateCell = row?.querySelector('[data-field="last-update"]');
            if (!updateCell) return;
            device.recorded_at_human = formatRelativeTime(device.recorded_at);
            updateCell.innerHTML = `<small>${device.recorded_at_human}</small>`;
        });
    }

    function isEchoConnected() {
        return global.Echo?.connector?.pusher?.connection?.state === 'connected';
    }

    function reverbEventsRecent() {
        return lastReverbActivityAt > 0
            && (Date.now() - lastReverbActivityAt) < reverbStaleMs;
    }

    function needsHttpLivePoll(force = false) {
        if (force) return true;
        if (document.hidden) return false;
        if (realtimeHealthy) return false;
        return !!pollUrl;
    }

    function stopLivePolling() {
        if (pollTimer) {
            clearInterval(pollTimer);
            pollTimer = null;
        }
    }

    async function pollLive(force) {
        if (!needsHttpLivePoll(force)) return;
        if (pollInFlight) return;

        const ids = deviceIdsOnPage();
        if (!ids.length) return;

        pollInFlight = true;
        try {
            const res = await fetch(`${pollUrl}?ids=${ids.join(',')}`, {
                headers: {
                    Accept: 'application/json',
                    'X-Requested-With': 'XMLHttpRequest',
                },
                credentials: 'same-origin',
            });

            if (!res.ok) return;

            const data = await res.json();
            (data.devices || []).forEach((device) => {
                if (device.status_key) {
                    stateById.set(device.id, {
                        ...device,
                        status_key: device.status_key,
                    });
                }
                updateRow(device);
            });
            // Keep server fleet KPIs; never replace with page-only poll stats.
        } catch (err) {
            console.warn('[admin-locations] live poll failed', err);
        } finally {
            pollInFlight = false;
        }
    }

    function startLivePolling() {
        if (!needsHttpLivePoll()) {
            stopLivePolling();
            return;
        }
        const hadTimer = !!pollTimer;
        stopLivePolling();
        if (!hadTimer) {
            pollLive(true);
        }
        pollTimer = setInterval(() => pollLive(false), pollMs);
    }

    function maybeStaleReverbHeartbeat() {
        if (!realtimeHealthy || document.hidden) return;
        if (reverbEventsRecent()) return;
        const since = Date.now() - (lastHttpHeartbeatAt || 0);
        if (lastHttpHeartbeatAt && since < reverbStaleMs) return;
        lastHttpHeartbeatAt = Date.now();
        pollLive(true);
    }

    function syncRealtimePolling() {
        const wsConnected = isEchoConnected();
        if (wsConnected !== realtimeHealthy) {
            realtimeHealthy = wsConnected;
            console.info(
                '[admin-locations] Reverb',
                wsConnected
                    ? 'connected — live-json polling stopped'
                    : 'unavailable — HTTP fallback active',
            );
        }
        if (needsHttpLivePoll()) {
            startLivePolling();
        } else {
            stopLivePolling();
        }
    }

    function subscribeDevice(id) {
        if (!global.Echo || typeof global.Echo.private !== 'function' || echoChannels.has(id)) {
            return;
        }
        try {
            const channel = global.Echo.private(`device.${id}`);
            channel.listen('.DeviceLocationUpdated', (payload) => {
                applyReverbUpdate(id, payload);
            });
            echoChannels.set(id, channel);
        } catch (err) {
            console.warn('[admin-locations] Echo subscribe failed', id, err);
        }
    }

    function unsubscribeDevice(id) {
        if (!echoChannels.has(id)) return;
        try {
            global.Echo.leave(`device.${id}`);
        } catch (_) { /* ignore */ }
        echoChannels.delete(id);
    }

    function syncEchoSubscriptions() {
        const ids = new Set(deviceIdsOnPage());
        echoChannels.forEach((_, id) => {
            if (!ids.has(id)) unsubscribeDevice(id);
        });
        ids.forEach((id) => subscribeDevice(id));
    }

    function bindEchoRealtime() {
        if (echoHooksBound) return;
        echoHooksBound = true;

        const sync = () => {
            syncEchoSubscriptions();
            syncRealtimePolling();
        };

        global.addEventListener('reverb:connected', sync);
        global.addEventListener('reverb:disconnected', sync);
        global.addEventListener('focus', sync);

        document.addEventListener('visibilitychange', () => {
            if (document.hidden) {
                stopLivePolling();
            } else {
                sync();
            }
        });

        if (!global.Echo) {
            realtimeHealthy = false;
            return;
        }

        reverbWatchTimer = setInterval(() => {
            if (!isEchoConnected()) {
                realtimeHealthy = false;
                syncRealtimePolling();
                return;
            }
            realtimeHealthy = true;
            stopLivePolling();
            maybeStaleReverbHeartbeat();
        }, 20000);

        setTimeout(sync, 800);
        setTimeout(sync, 2500);
    }

    function startLiveServices() {
        seedStateFromDom();
        bindEchoRealtime();
        syncEchoSubscriptions();
        syncRealtimePolling();

        if (relativeTimeTimer) {
            clearInterval(relativeTimeTimer);
        }
        relativeTimeTimer = setInterval(tickRelativeTimes, 1000);

        if (needsHttpLivePoll()) {
            pollLive(true);
        }
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', startLiveServices);
    } else {
        startLiveServices();
    }
})();
