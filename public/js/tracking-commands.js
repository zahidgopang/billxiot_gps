(function (global) {
    'use strict';
    const cfg = global.TRACKING_COMMANDS_CONFIG;
    if (!cfg) return;

    async function load() {
        const res = await fetch(cfg.jsonUrl, { credentials: 'same-origin' });
        const data = await res.json();
        document.getElementById('gtCmdBody').innerHTML = (data.commands || []).map((c) =>
            `<tr><td>${c.time || ''}</td><td>${c.device_id}</td><td>${c.type}</td></tr>`
        ).join('');
    }

    document.getElementById('gtCmdForm')?.addEventListener('submit', async (e) => {
        e.preventDefault();
        if (!confirm('Send command to device?')) return;
        const fd = new FormData(e.target);
        const body = Object.fromEntries(fd.entries());
        const res = await fetch(cfg.sendUrl, { method: 'POST', credentials: 'same-origin', headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content || '' }, body: JSON.stringify(body) });
        const data = await res.json();
        alert(data.message || (data.success ? 'Sent' : 'Failed'));
        load();
    });

    load();
})(window);
