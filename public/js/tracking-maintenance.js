(function (global) {
    'use strict';
    const cfg = global.TRACKING_MAINTENANCE_CONFIG;
    if (!cfg) return;

    const i18n = cfg.i18n || {};
    const body = document.getElementById('gtMaintBody');
    const modal = document.getElementById('gtMaintModal');
    const form = document.getElementById('gtMaintForm');
    const addBtn = document.getElementById('gtMaintAdd');
    const objectsBox = document.getElementById('gtMaintObjects');

    const $ = global.jQuery;
    const useSelect2 = !!($ && $.fn && $.fn.select2 && objectsBox);

    const csrf = () => document.querySelector('meta[name="csrf-token"]')?.content || '';

    function initObjects() {
        if (!useSelect2) return;
        const dialog = modal?.querySelector('.gt-modal__dialog');
        $(objectsBox).select2({
            theme: 'bootstrap-5',
            width: '100%',
            placeholder: i18n.selectObject || 'Select objects',
            closeOnSelect: false,
            dropdownParent: $(dialog || modal),
        });
    }

    function getSelectedObjects() {
        return Array.from(objectsBox?.selectedOptions || []).map((o) => o.value);
    }

    function setSelectedObjects(ids) {
        const set = new Set((ids || []).map(String));
        Array.from(objectsBox?.options || []).forEach((o) => { o.selected = set.has(o.value); });
        if (useSelect2) $(objectsBox).trigger('change.select2');
    }

    function clearSelectedObjects() {
        Array.from(objectsBox?.options || []).forEach((o) => { o.selected = false; });
        if (useSelect2) $(objectsBox).trigger('change.select2');
    }

    function escHtml(s) {
        return String(s == null ? '' : s).replace(/[&<>"']/g, (c) =>
            ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]));
    }

    function statusBadge(s) {
        const cls = s === 'overdue' ? 'danger' : s === 'soon' ? 'warning' : 'success';
        const label = (i18n.status && i18n.status[s]) || s;
        return `<span class="badge bg-${cls} gt-maint-status">${escHtml(label)}</span>`;
    }

    function setField(name, value) {
        const el = form.elements[name];
        if (!el) return;
        if (el.type === 'checkbox') {
            el.checked = !!value;
        } else {
            el.value = value == null ? '' : value;
        }
    }

    function syncToggles() {
        form.querySelectorAll('[data-toggle-target]').forEach((cb) => {
            const target = form.elements[cb.dataset.toggleTarget];
            if (target) target.disabled = !cb.checked;
        });
    }

    function openModal() { modal.classList.add('open'); modal.setAttribute('aria-hidden', 'false'); }
    function closeModal() { modal.classList.remove('open'); modal.setAttribute('aria-hidden', 'true'); }

    function resetForm() {
        form.reset();
        setField('maintenance_id', '');
        setField('data_list', true);
        setField('popup', true);
        clearSelectedObjects();
        syncToggles();
    }

    function fillForm(item) {
        resetForm();
        const c = item.config || {};
        setField('maintenance_id', item.id);
        setField('name', item.name);
        setField('data_list', c.data_list);
        setField('popup', c.popup);

        const odo = c.odometer || {}, hrs = c.hours || {}, days = c.days || {}, trg = c.trigger || {};
        setField('odometer_enabled', odo.enabled);
        setField('odometer_interval', odo.interval);
        setField('odometer_last', odo.last);
        setField('hours_enabled', hrs.enabled);
        setField('hours_interval', hrs.interval);
        setField('hours_last', hrs.last);
        setField('days_enabled', days.enabled);
        setField('days_interval', days.interval);
        setField('days_last', days.last);
        setField('trigger_odometer', trg.odometer_left);
        setField('trigger_hours', trg.hours_left);
        setField('trigger_days', trg.days_left);
        setField('update_last_service', c.update_last_service);

        setSelectedObjects(item.object_ids || []);
        syncToggles();
    }

    async function load() {
        let items = [];
        try {
            const res = await fetch(cfg.jsonUrl, { credentials: 'same-origin', headers: { Accept: 'application/json' } });
            const data = await res.json();
            items = data.items || [];
        } catch (e) { items = []; }

        if (!items.length) {
            body.innerHTML = `<tr><td colspan="5" class="text-center text-muted py-3">${escHtml(i18n.noRecords || 'No records')}</td></tr>`;
            return;
        }

        body.innerHTML = items.map((m) => {
            const objs = (m.objects || []).map((o) => escHtml(o.name)).join(', ');
            return `<tr data-id="${m.id}">
                <td>${objs}</td>
                <td>${escHtml(m.name)}</td>
                <td class="text-muted small">${escHtml(m.summary || '')}</td>
                <td>${statusBadge(m.status)}</td>
                <td class="text-end text-nowrap">
                    <button type="button" class="btn btn-sm btn-outline-secondary" data-edit='${encodeURIComponent(JSON.stringify(m))}'><i class="fas fa-pen"></i></button>
                    <button type="button" class="btn btn-sm btn-outline-danger" data-del="${m.id}">&times;</button>
                </td>
            </tr>`;
        }).join('');
    }

    addBtn?.addEventListener('click', () => { resetForm(); openModal(); });

    modal?.addEventListener('click', (e) => {
        if (e.target === modal || e.target.closest('[data-maint-close]')) closeModal();
    });

    form?.addEventListener('change', (e) => {
        if (e.target.matches('[data-toggle-target]')) syncToggles();
    });

    body?.addEventListener('click', async (e) => {
        const editBtn = e.target.closest('[data-edit]');
        if (editBtn) {
            try { fillForm(JSON.parse(decodeURIComponent(editBtn.dataset.edit))); openModal(); } catch (err) {}
            return;
        }
        const delBtn = e.target.closest('[data-del]');
        if (!delBtn) return;
        const id = delBtn.dataset.del;
        if (global.Swal) {
            const r = await global.Swal.fire({ icon: 'warning', title: i18n.confirmDelete || 'Delete?', showCancelButton: true });
            if (!r.isConfirmed) return;
        } else if (!global.confirm(i18n.confirmDelete || 'Delete?')) {
            return;
        }
        delBtn.setAttribute('disabled', 'disabled');
        try {
            const res = await fetch(`${cfg.baseUrl}/${id}`, {
                method: 'DELETE',
                credentials: 'same-origin',
                headers: { 'X-CSRF-TOKEN': csrf(), Accept: 'application/json' },
            });
            if (!res.ok) throw new Error('failed');
            await load();
        } catch (err) {
            delBtn.removeAttribute('disabled');
            if (global.Swal) global.Swal.fire({ icon: 'error', title: i18n.failed || 'Failed' });
        }
    });

    form?.addEventListener('submit', async (e) => {
        e.preventDefault();

        const selected = getSelectedObjects();
        if (!selected.length) {
            if (global.Swal) global.Swal.fire({ icon: 'info', title: i18n.selectObject || 'Select at least one object' });
            else global.alert(i18n.selectObject || 'Select at least one object');
            return;
        }

        const anyInterval = form.elements['odometer_enabled'].checked
            || form.elements['hours_enabled'].checked
            || form.elements['days_enabled'].checked;
        if (!anyInterval) {
            if (global.Swal) global.Swal.fire({ icon: 'info', title: i18n.selectTrigger || 'Enable at least one interval' });
            else global.alert(i18n.selectTrigger || 'Enable at least one interval');
            return;
        }

        const fd = new FormData(form);
        // Ensure unchecked checkboxes post an explicit 0 so the server clears them.
        ['data_list', 'popup', 'odometer_enabled', 'hours_enabled', 'days_enabled',
            'trigger_odometer', 'trigger_hours', 'trigger_days', 'update_last_service'].forEach((n) => {
            if (!fd.has(n)) fd.set(n, '0');
        });
        selected.forEach((id) => fd.append('device_ids[]', id));

        const id = form.elements['maintenance_id'].value;
        const url = id ? `${cfg.baseUrl}/${id}` : cfg.storeUrl;

        const submitBtn = form.querySelector('button[type="submit"]');
        submitBtn?.setAttribute('disabled', 'disabled');
        try {
            const res = await fetch(url, {
                method: 'POST',
                credentials: 'same-origin',
                headers: { 'X-CSRF-TOKEN': csrf(), Accept: 'application/json' },
                body: fd,
            });
            const data = await res.json().catch(() => ({}));
            if (!res.ok || !data.success) throw new Error('failed');
            closeModal();
            await load();
        } catch (err) {
            if (global.Swal) global.Swal.fire({ icon: 'error', title: i18n.failed || 'Failed' });
        } finally {
            submitBtn?.removeAttribute('disabled');
        }
    });

    initObjects();
    load();
})(window);
