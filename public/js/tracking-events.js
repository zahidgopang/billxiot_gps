(function (global) {
    'use strict';
    const cfg = global.TRACKING_EVENTS_CONFIG;
    if (!cfg) return;

    async function load() {
        const p = new URLSearchParams();
        p.set('from', document.getElementById('gtEventsFrom').value);
        p.set('to', document.getElementById('gtEventsTo').value);
        const dev = document.getElementById('gtEventsDevice').value;
        if (dev) p.set('ids', dev);
        const res = await fetch(`${cfg.jsonUrl}?${p}&_=${Date.now()}`, { credentials: 'same-origin' });
        const data = await res.json();
        const body = document.getElementById('gtEventsBody');
        body.innerHTML = (data.events || []).map((e) =>
            `<tr><td>${e.time || ''}</td><td>${e.device_name || ''}</td><td>${e.event_type || e.type || ''}</td><td>${e.title || e.message || ''}</td></tr>`
        ).join('') || '<tr><td colspan="4">—</td></tr>';
    }

    document.getElementById('gtEventsLoad')?.addEventListener('click', load);
    const t = new Date().toISOString().slice(0, 10);
    document.getElementById('gtEventsFrom').value = t;
    document.getElementById('gtEventsTo').value = t;
    load();
})(window);
