(function (global) {
    'use strict';
    const cfg = global.TRACKING_COMMANDS_CONFIG;
    if (!cfg) return;

    const i18n = cfg.i18n || {};
    const form = document.getElementById('gtCmdForm');
    const body = document.getElementById('gtCmdBody');
    const typeSel = document.getElementById('gtCmdType');
    const deliveryEl = document.getElementById('gtCmdDeliveryHealth');

    const csrf = () => document.querySelector('meta[name="csrf-token"]')?.content || '';
    const OPEN = new Set(['pending', 'sent', 'delivered']);
    let pollTimer = null;

    function escHtml(s) {
        return String(s == null ? '' : s).replace(/[&<>"']/g, (c) =>
            ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]));
    }

    function statusBadge(status, label) {
        const map = {
            pending: 'warning',
            sent: 'info',
            delivered: 'primary',
            executed: 'success',
            failed: 'danger',
            timeout: 'dark',
            canceled: 'secondary',
        };
        const cls = map[status] || 'secondary';
        const text = label || status;
        return `<span class="badge bg-${cls} gt-cmd-badge">${escHtml(text)}</span>`;
    }

    function stagesHtml(stages) {
        if (!Array.isArray(stages) || !stages.length) return '';
        const items = stages.map((s) => {
            const at = s.at ? String(s.at).replace('T', ' ').slice(0, 19) : '';
            return `<li><strong>${escHtml(s.stage || '')}</strong> — ${escHtml(s.message || '')}`
                + (at ? ` <span class="text-muted">(${escHtml(at)})</span>` : '')
                + `</li>`;
        }).join('');
        return `<details class="gt-cmd-stages mt-1"><summary class="small text-muted">${escHtml(i18n.timeline || 'Command timeline')}</summary>`
            + `<ol class="small mb-0 ps-3">${items}</ol></details>`;
    }

    function toggleDataField() {
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
            global.Swal.fire({ icon, title, timer: icon === 'success' ? 1800 : undefined, showConfirmButton: icon !== 'success' });
        } else {
            global.alert(title);
        }
    }

    function renderDelivery(delivery) {
        if (!deliveryEl || !delivery) return;
        const ok = !!delivery.ok;
        deliveryEl.className = `alert alert-${ok ? 'success' : 'danger'} py-2 px-3 small mb-3`;
        deliveryEl.hidden = false;
        deliveryEl.textContent = ok
            ? (delivery.message || 'Traccar API is reachable — commands can be delivered.')
            : (delivery.message || 'Traccar API is unreachable — commands will fail until Traccar is running.');
    }

    function schedulePoll(commands) {
        if (pollTimer) {
            clearInterval(pollTimer);
            pollTimer = null;
        }
        const needsPoll = (commands || []).some((c) => OPEN.has(c.status));
        if (!needsPoll) return;
        pollTimer = setInterval(() => { load(true); }, 8000);
    }

    async function load(silent) {
        let commands = [];
        let delivery = null;
        try {
            const res = await fetch(cfg.jsonUrl, { credentials: 'same-origin', headers: { Accept: 'application/json' } });
            const data = await res.json();
            commands = data.commands || [];
            delivery = data.delivery || null;
        } catch (e) {
            commands = [];
        }

        renderDelivery(delivery);

        if (!commands.length) {
            body.innerHTML = `<tr><td colspan="7" class="gt-cmd-empty">${escHtml(i18n.noHistory || 'No commands yet')}</td></tr>`;
            schedulePoll([]);
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
                ? `<div class="small text-muted" title="${escHtml(c.result)}">${escHtml(String(c.result).slice(0, 120))}</div>`
                : '';
            const deliveryTag = c.delivery
                ? `<div class="small text-muted">${escHtml(c.delivery)}</div>`
                : '';
            return `<tr data-id="${c.id}">
                <td class="text-nowrap small">${escHtml(c.time_display || c.time || '')}</td>
                <td>${escHtml(c.device || ('#' + c.device_id))}</td>
                <td>${escHtml(c.type_label || c.type)}${wire}</td>
                <td class="small text-muted">${escHtml(c.data || '')}</td>
                <td>${statusBadge(c.status, c.status_label)}${result}${deliveryTag}${stagesHtml(c.stages)}</td>
                <td class="small text-muted">${escHtml(c.requested_by || '')}</td>
                <td class="text-end">${cancelBtn}</td>
            </tr>`;
        }).join('');

        schedulePoll(commands);
        if (!silent && (commands || []).some((c) => OPEN.has(c.status)) && i18n.pollHint) {
            // no toast spam — hint lives in delivery banner area if needed
        }
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
                await load();
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
