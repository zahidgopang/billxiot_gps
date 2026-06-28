(function (global) {
    'use strict';
    const cfg = global.TRACKING_DRIVERS_CONFIG;
    if (!cfg) return;

    const csrf = () => document.querySelector('meta[name="csrf-token"]')?.content || '';

    async function load() {
        const res = await fetch(cfg.jsonUrl, { credentials: 'same-origin' });
        const data = await res.json();
        document.getElementById('gtDriverBody').innerHTML = (data.drivers || []).map((d) => {
            const opts = (cfg.vehicles || []).map((v) => `<option value="${v.id}" ${v.id === d.device_id ? 'selected' : ''}>${v.title || v.id}</option>`).join('');
            return `<tr><td>${d.name}</td><td>${d.phone || ''}</td><td><select class="form-select form-select-sm gt-assign" data-id="${d.id}"><option value="">—</option>${opts}</select></td><td>${d.id ? `<button type="button" class="btn btn-sm btn-outline-danger" data-del="${d.id}">×</button>` : ''}</td></tr>`;
        }).join('');
    }

    document.getElementById('gtDriverForm')?.addEventListener('submit', async (e) => {
        e.preventDefault();
        const fd = new FormData(e.target);
        await fetch(cfg.storeUrl, { method: 'POST', credentials: 'same-origin', headers: { 'X-CSRF-TOKEN': csrf() }, body: fd });
        load();
        e.target.reset();
    });

    document.getElementById('gtDriverBody')?.addEventListener('change', async (e) => {
        if (!e.target.classList.contains('gt-assign')) return;
        const driverId = e.target.dataset.id;
        const deviceId = e.target.value;
        if (!deviceId) return;
        await fetch(`${cfg.storeUrl.replace('/store', '')}/${driverId}/assign`, { method: 'POST', credentials: 'same-origin', headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': csrf() }, body: JSON.stringify({ device_id: deviceId }) });
    });

    document.getElementById('gtDriverBody')?.addEventListener('click', async (e) => {
        const btn = e.target.closest('[data-del]');
        if (!btn) return;
        const driverId = btn.dataset.del;
        if (!driverId) return;

        const confirmMsg = cfg.confirmDelete || 'Delete this driver?';
        if (global.Swal) {
            const r = await global.Swal.fire({ icon: 'warning', title: confirmMsg, showCancelButton: true });
            if (!r.isConfirmed) return;
        } else if (!global.confirm(confirmMsg)) {
            return;
        }

        btn.setAttribute('disabled', 'disabled');
        try {
            const res = await fetch(`${cfg.storeUrl.replace('/store', '')}/${driverId}`, {
                method: 'DELETE',
                credentials: 'same-origin',
                headers: { 'X-CSRF-TOKEN': csrf(), Accept: 'application/json' },
            });
            if (!res.ok) throw new Error('Request failed');
            await load();
        } catch (err) {
            btn.removeAttribute('disabled');
            if (global.Swal) global.Swal.fire({ icon: 'error', title: cfg.deleteFailed || 'Failed to delete driver' });
            else global.alert(cfg.deleteFailed || 'Failed to delete driver');
        }
    });

    load();
})(window);
