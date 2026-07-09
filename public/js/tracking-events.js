(function (global) {
    'use strict';
    const cfg = global.TRACKING_EVENTS_CONFIG;
    if (!cfg) return;

    function permissionMessage(payload, status) {
        const bodyMessage = String(payload?.message || '').trim();
        if (status === 403 || status === 401) {
            return bodyMessage
                || cfg.i18n?.eventsPermissionDenied
                || 'Access Denied. You do not have permission to view events.';
        }
        return bodyMessage || `Request failed (${status})`;
    }

    function popupPermissionDenied(message) {
        const text = String(
            message
            || cfg.i18n?.eventsPermissionDenied
            || 'Access Denied. You do not have permission to view events.',
        ).trim();

        if (global.Swal) {
            global.Swal.fire({
                icon: 'error',
                title: cfg.i18n?.accessDeniedTitle || 'Access Denied',
                text,
                confirmButtonText: cfg.i18n?.ok || 'OK',
            });
            return;
        }

        alert(text);
    }

    function notify(message, icon = 'error') {
        if (global.Swal) {
            global.Swal.fire({
                icon,
                title: message,
                timer: icon === 'success' ? 1600 : undefined,
                showConfirmButton: icon !== 'success',
            });
            return;
        }
        alert(message);
    }

    async function load() {
        const body = document.getElementById('gtEventsBody');
        const btn = document.getElementById('gtEventsLoad');
        const p = new URLSearchParams();
        p.set('from', document.getElementById('gtEventsFrom').value);
        p.set('to', document.getElementById('gtEventsTo').value);
        const dev = document.getElementById('gtEventsDevice').value;
        if (dev) p.set('ids', dev);

        btn?.setAttribute('disabled', 'disabled');
        if (body) body.innerHTML = '<tr><td colspan="4">…</td></tr>';

        try {
            const res = await fetch(`${cfg.jsonUrl}?${p}&_=${Date.now()}`, {
                credentials: 'same-origin',
                headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
            });
            const data = await res.json().catch(() => ({}));

            if (!res.ok) {
                if (res.status === 401 || res.status === 403) {
                    if (body) {
                        body.innerHTML = `<tr><td colspan="4">${cfg.i18n?.accessDeniedTitle || 'Access Denied'}</td></tr>`;
                    }
                    popupPermissionDenied(permissionMessage(data, res.status));
                    return;
                }
                throw new Error(data.message || `Request failed (${res.status})`);
            }

            const events = data.events || [];
            if (!body) return;

            body.innerHTML = events.length
                ? events.map((e) =>
                    `<tr><td>${e.time || ''}</td><td>${e.device_name || ''}</td><td>${e.event_type || e.type || ''}</td><td>${e.title || e.message || ''}</td></tr>`
                ).join('')
                : `<tr><td colspan="4">${cfg.i18n?.noData || '—'}</td></tr>`;
        } catch (err) {
            console.error('[tracking-events]', err);
            if (body) {
                body.innerHTML = `<tr><td colspan="4">${cfg.i18n?.loadFailed || 'Failed'}</td></tr>`;
            }
            notify(err.message || cfg.i18n?.loadFailed || 'Failed to load events.', 'error');
        } finally {
            btn?.removeAttribute('disabled');
        }
    }

    document.getElementById('gtEventsLoad')?.addEventListener('click', load);
    const t = new Date().toISOString().slice(0, 10);
    document.getElementById('gtEventsFrom').value = t;
    document.getElementById('gtEventsTo').value = t;
    load();
})(window);
