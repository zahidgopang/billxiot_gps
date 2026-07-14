(function (global) {
    'use strict';
    const cfg = global.TRACKING_COMMANDS_CONFIG;
    if (!cfg) return;

    const i18n = cfg.i18n || {};
    const form = document.getElementById('gtCmdForm');
    const body = document.getElementById('gtCmdBody');
    const typeSel = document.getElementById('gtCmdType');

    const csrf = () => document.querySelector('meta[name="csrf-token"]')?.content || '';

    function escHtml(s) {
        return String(s == null ? '' : s).replace(/[&<>"']/g, (c) =>
            ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]));
    }

    function statusBadge(status) {
        const map = {
            pending: ['warning', i18n.statusPending || 'Pending'],
            sent: ['info', i18n.statusSent || 'Sent'],
            delivered: ['primary', i18n.statusDelivered || 'Delivered'],
            executed: ['success', i18n.statusExecuted || 'Executed'],
            failed: ['danger', i18n.statusFailed || 'Failed'],
            timeout: ['dark', i18n.statusTimeout || 'Timeout'],
            canceled: ['secondary', i18n.statusCanceled || 'Canceled'],
        };
        const [cls, label] = map[status] || ['secondary', status];
        return `<span class="badge bg-${cls} gt-cmd-badge">${escHtml(label)}</span>`;
    }

    function toggleDataField() {
        // The custom command must carry its raw payload in the data field;
        // other types accept optional data, so only `custom` is required.
        const input = document.getElementById('gtCmdData');
        if (input) input.required = typeSel?.value === 'custom';
    }

    async function confirmDialog(title) {
        if (global.Swal) {
            const r = await global.Swal.fire({ icon: 'question', title, showCancelButton: true });
            return r.isConfirmed;
        }
        return global.confirm(title);
    }

    function notify(icon, title) {
        if (global.Swal) {
            global.Swal.fire({ icon, title, timer: icon === 'success' ? 1600 : undefined, showConfirmButton: icon !== 'success' });
        } else {
            global.alert(title);
        }
    }

    async function load() {
        let commands = [];
        try {
            const res = await fetch(cfg.jsonUrl, { credentials: 'same-origin', headers: { Accept: 'application/json' } });
            const data = await res.json();
            commands = data.commands || [];
        } catch (e) { commands = []; }

        if (!commands.length) {
            body.innerHTML = `<tr><td colspan="7" class="gt-cmd-empty">${escHtml(i18n.noHistory || 'No commands yet')}</td></tr>`;
            return;
        }

        body.innerHTML = commands.map((c) => {
            const cancelBtn = (c.status === 'pending' || c.status === 'sent')
                ? `<button type="button" class="btn btn-sm btn-outline-danger" data-cancel="${c.id}"><i class="fas fa-times"></i> ${escHtml(i18n.cancel || 'Cancel')}</button>`
                : '';
            const wire = c.wire_type
                ? `<div class="small text-muted">${escHtml(c.wire_type)}${c.wire_data ? ': ' + escHtml(c.wire_data) : ''}</div>`
                : '';
            const result = c.result
                ? `<div class="small text-muted" title="${escHtml(c.result)}">${escHtml(String(c.result).slice(0, 80))}</div>`
                : '';
            return `<tr data-id="${c.id}">
                <td class="text-nowrap small">${escHtml(c.time_display || c.time || '')}</td>
                <td>${escHtml(c.device || ('#' + c.device_id))}</td>
                <td>${escHtml(c.type_label || c.type)}${wire}</td>
                <td class="small text-muted">${escHtml(c.data || '')}</td>
                <td>${statusBadge(c.status)}${result}</td>
                <td class="small text-muted">${escHtml(c.requested_by || '')}</td>
                <td class="text-end">${cancelBtn}</td>
            </tr>`;
        }).join('');
    }

    typeSel?.addEventListener('change', toggleDataField);

    form?.addEventListener('submit', async (e) => {
        e.preventDefault();
        if (!(await confirmDialog(i18n.confirmSend || 'Send command to device?'))) return;

        const fd = new FormData(form);
        const payload = Object.fromEntries(fd.entries());
        const submitBtn = form.querySelector('button[type="submit"]');
        submitBtn?.setAttribute('disabled', 'disabled');

        try {
            const res = await fetch(cfg.sendUrl, {
                method: 'POST',
                credentials: 'same-origin',
                headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': csrf(), Accept: 'application/json' },
                body: JSON.stringify(payload),
            });
            const data = await res.json().catch(() => ({}));
            if (!res.ok || !data.success) {
                notify('error', data.message || i18n.failed || 'Failed');
                return;
            }
            notify('success', data.message || i18n.sent || 'Sent');
            const dataInput = document.getElementById('gtCmdData');
            if (dataInput) dataInput.value = '';
            await load();
        } catch (err) {
            notify('error', i18n.failed || 'Failed');
        } finally {
            submitBtn?.removeAttribute('disabled');
        }
    });

    body?.addEventListener('click', async (e) => {
        const btn = e.target.closest('[data-cancel]');
        if (!btn) return;
        if (!(await confirmDialog(i18n.confirmCancel || 'Cancel this command?'))) return;

        const id = btn.dataset.cancel;
        const url = cfg.cancelUrl.replace(/\/0(\?|$)/, `/${id}$1`);
        btn.setAttribute('disabled', 'disabled');
        try {
            const res = await fetch(url, {
                method: 'DELETE',
                credentials: 'same-origin',
                headers: { 'X-CSRF-TOKEN': csrf(), Accept: 'application/json' },
            });
            if (!res.ok) throw new Error('failed');
            await load();
        } catch (err) {
            btn.removeAttribute('disabled');
            notify('error', i18n.failed || 'Failed');
        }
    });

    toggleDataField();
    load();
})(window);
