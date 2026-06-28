(function (global) {
    'use strict';
    const cfg = global.TRACKING_NOTIFICATIONS_CONFIG;
    if (!cfg) return;

    async function load() {
        const res = await fetch(cfg.jsonUrl, { credentials: 'same-origin' });
        const data = await res.json();
        document.getElementById('gtNotifBody').innerHTML = (data.preferences || []).map((p, i) =>
            `<tr><td>${p.label}</td><td><input type="checkbox" name="web_${i}" data-type="${p.type}" data-ch="web" ${p.web ? 'checked' : ''}></td><td><input type="checkbox" name="push_${i}" data-type="${p.type}" data-ch="push" ${p.push ? 'checked' : ''}></td></tr>`
        ).join('');
    }

    document.getElementById('gtNotifForm')?.addEventListener('submit', async (e) => {
        e.preventDefault();
        const types = new Set([...document.querySelectorAll('#gtNotifBody [data-type]')].map((el) => el.dataset.type));
        const preferences = [...types].map((type) => ({
            type,
            web: !!document.querySelector(`#gtNotifBody [data-type="${type}"][data-ch="web"]`)?.checked,
            push: !!document.querySelector(`#gtNotifBody [data-type="${type}"][data-ch="push"]`)?.checked,
        }));
        await fetch(cfg.updateUrl, { method: 'POST', credentials: 'same-origin', headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content || '' }, body: JSON.stringify({ preferences }) });
        alert('Saved');
    });

    load();
})(window);
