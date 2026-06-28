(function (global) {
    'use strict';
    const cfg = global.TRACKING_SETTINGS_CONFIG;
    if (!cfg) return;

    document.getElementById('gtSettingsForm')?.addEventListener('submit', async (e) => {
        e.preventDefault();
        const fd = new FormData(e.target);
        const settings = Object.fromEntries(fd.entries());
        await fetch(cfg.updateUrl, { method: 'POST', credentials: 'same-origin', headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content || '' }, body: JSON.stringify({ settings }) });
        alert('Saved');
    });
})(window);
