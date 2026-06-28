(function (global) {
    'use strict';
    const cfg = global.TRACKING_TASKS_CONFIG;
    if (!cfg) return;

    const i18n = cfg.i18n || {};
    const body = document.getElementById('tsBody');
    const filterForm = document.getElementById('tsFilter');
    const addForm = document.getElementById('tsForm');
    const exportLink = document.getElementById('tsExport');
    const deleteAllBtn = document.getElementById('tsDeleteAll');

    const csrf = () => document.querySelector('meta[name="csrf-token"]')?.content || '';

    function escHtml(s) {
        return String(s == null ? '' : s).replace(/[&<>"']/g, (c) =>
            ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]));
    }

    function priorityBadge(p) {
        const cls = p === 'high' ? 'danger' : p === 'low' ? 'secondary' : 'info';
        const label = (i18n.priority && i18n.priority[p]) || p;
        return `<span class="badge bg-${cls}">${escHtml(label)}</span>`;
    }

    function statusBadge(s) {
        const cls = s === 'done' ? 'success' : s === 'cancelled' ? 'secondary' : s === 'in_progress' ? 'warning' : 'primary';
        const label = (i18n.status && i18n.status[s]) || s;
        return `<span class="badge bg-${cls}">${escHtml(label)}</span>`;
    }

    function currentFilterParams() {
        const params = new URLSearchParams();
        const fd = new FormData(filterForm);
        const device = fd.get('device_id');
        const from = fd.get('from');
        const to = fd.get('to');
        if (device && device !== '0') params.set('device_id', device);
        if (from) params.set('from', from.replace('T', ' '));
        if (to) params.set('to', to.replace('T', ' '));
        return params;
    }

    function syncExportLink() {
        const params = currentFilterParams();
        const qs = params.toString();
        exportLink.setAttribute('href', qs ? `${cfg.exportUrl}?${qs}` : cfg.exportUrl);
    }

    async function load() {
        syncExportLink();
        const qs = currentFilterParams().toString();
        const url = qs ? `${cfg.jsonUrl}?${qs}` : cfg.jsonUrl;
        let items = [];
        try {
            const res = await fetch(url, { credentials: 'same-origin', headers: { Accept: 'application/json' } });
            const data = await res.json();
            items = data.items || [];
        } catch (e) {
            items = [];
        }

        if (!items.length) {
            body.innerHTML = `<tr><td colspan="8" class="text-center text-muted py-3">${escHtml(i18n.noRecords || 'No records')}</td></tr>`;
            return;
        }

        body.innerHTML = items.map((t) =>
            `<tr>
                <td class="text-nowrap">${escHtml(t.time_from || '')}</td>
                <td>${escHtml(t.name)}</td>
                <td>${escHtml(t.device_name)}</td>
                <td>${escHtml(t.start)}</td>
                <td>${escHtml(t.destination)}</td>
                <td>${priorityBadge(t.priority)}</td>
                <td>${statusBadge(t.status)}</td>
                <td class="text-end"><button type="button" class="btn btn-sm btn-outline-danger" data-del="${t.id}" title="&times;">&times;</button></td>
            </tr>`
        ).join('');
    }

    filterForm?.addEventListener('submit', (e) => { e.preventDefault(); load(); });
    filterForm?.addEventListener('change', syncExportLink);

    addForm?.addEventListener('submit', async (e) => {
        e.preventDefault();
        const btn = addForm.querySelector('button[type="submit"]');
        btn?.setAttribute('disabled', 'disabled');
        try {
            const res = await fetch(cfg.storeUrl, {
                method: 'POST',
                credentials: 'same-origin',
                headers: { 'X-CSRF-TOKEN': csrf(), Accept: 'application/json' },
                body: new FormData(addForm),
            });
            if (!res.ok) throw new Error('failed');
            addForm.reset();
            await load();
        } catch (err) {
            if (global.Swal) global.Swal.fire({ icon: 'error', title: i18n.failed || 'Failed' });
        } finally {
            btn?.removeAttribute('disabled');
        }
    });

    body?.addEventListener('click', async (e) => {
        const btn = e.target.closest('[data-del]');
        if (!btn) return;
        const id = btn.dataset.del;
        if (!id) return;
        if (global.Swal) {
            const r = await global.Swal.fire({ icon: 'warning', title: i18n.confirmDelete || 'Delete?', showCancelButton: true });
            if (!r.isConfirmed) return;
        } else if (!global.confirm(i18n.confirmDelete || 'Delete?')) {
            return;
        }
        btn.setAttribute('disabled', 'disabled');
        try {
            const res = await fetch(`${cfg.destroyUrl}/${id}`, {
                method: 'DELETE',
                credentials: 'same-origin',
                headers: { 'X-CSRF-TOKEN': csrf(), Accept: 'application/json' },
            });
            if (!res.ok) throw new Error('failed');
            await load();
        } catch (err) {
            btn.removeAttribute('disabled');
            if (global.Swal) global.Swal.fire({ icon: 'error', title: i18n.failed || 'Failed' });
        }
    });

    deleteAllBtn?.addEventListener('click', async () => {
        if (global.Swal) {
            const r = await global.Swal.fire({ icon: 'warning', title: i18n.confirmDeleteAll || 'Delete all?', showCancelButton: true });
            if (!r.isConfirmed) return;
        } else if (!global.confirm(i18n.confirmDeleteAll || 'Delete all?')) {
            return;
        }
        deleteAllBtn.setAttribute('disabled', 'disabled');
        try {
            const res = await fetch(cfg.destroyAllUrl, {
                method: 'DELETE',
                credentials: 'same-origin',
                headers: { 'X-CSRF-TOKEN': csrf(), Accept: 'application/json' },
            });
            if (!res.ok) throw new Error('failed');
            await load();
        } catch (err) {
            if (global.Swal) global.Swal.fire({ icon: 'error', title: i18n.failed || 'Failed' });
        } finally {
            deleteAllBtn.removeAttribute('disabled');
        }
    });

    load();
})(window);
