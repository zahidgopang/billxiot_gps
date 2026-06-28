(function (global) {
    'use strict';
    const cfg = global.TRACKING_NOTIFICATIONS_CONFIG;
    if (!cfg) return;

    const i18n = cfg.i18n || {};
    const form = document.getElementById('gtNotifForm');
    const body = document.getElementById('gtNotifBody');

    const csrf = () => document.querySelector('meta[name="csrf-token"]')?.content || '';

    function escHtml(s) {
        return String(s == null ? '' : s).replace(/[&<>"']/g, (c) =>
            ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]));
    }

    function notify(icon, title) {
        if (global.Swal) {
            global.Swal.fire({ icon, title, timer: icon === 'success' ? 1600 : undefined, showConfirmButton: icon !== 'success' });
        } else {
            global.alert(title);
        }
    }

    function row(p, i) {
        const web = `<input type="checkbox" class="form-check-input" data-type="${escHtml(p.type)}" data-ch="web" ${p.web ? 'checked' : ''} aria-label="${escHtml(i18n.web || 'Web')}">`;
        const push = `<input type="checkbox" class="form-check-input" data-type="${escHtml(p.type)}" data-ch="push" ${p.push ? 'checked' : ''} aria-label="${escHtml(i18n.push || 'Push')}">`;
        return `<tr>
            <td>${escHtml(p.label || p.type)}</td>
            <td class="gt-notif-ch">${web}</td>
            <td class="gt-notif-ch">${push}</td>
        </tr>`;
    }

    async function load() {
        let prefs = [];
        try {
            const res = await fetch(cfg.jsonUrl, { credentials: 'same-origin', headers: { Accept: 'application/json' } });
            const data = await res.json();
            prefs = data.preferences || [];
        } catch (e) { prefs = []; }

        if (!prefs.length) {
            body.innerHTML = `<tr><td colspan="3" class="gt-notif-empty">${escHtml(i18n.noTypes || 'No notification types')}</td></tr>`;
            return;
        }

        body.innerHTML = prefs.map(row).join('');
    }

    // Column "toggle all" links in the header.
    form?.querySelectorAll('[data-toggle-col]').forEach((link) => {
        link.addEventListener('click', () => {
            const ch = link.dataset.toggleCol;
            const boxes = [...body.querySelectorAll(`[data-ch="${ch}"]`)];
            const allOn = boxes.every((b) => b.checked);
            boxes.forEach((b) => { b.checked = !allOn; });
        });
    });

    form?.addEventListener('submit', async (e) => {
        e.preventDefault();
        const types = new Set([...body.querySelectorAll('[data-type]')].map((el) => el.dataset.type));
        const preferences = [...types].map((type) => ({
            type,
            web: !!body.querySelector(`[data-type="${CSS.escape(type)}"][data-ch="web"]`)?.checked,
            push: !!body.querySelector(`[data-type="${CSS.escape(type)}"][data-ch="push"]`)?.checked,
        }));

        const btn = form.querySelector('button[type="submit"]');
        btn?.setAttribute('disabled', 'disabled');
        try {
            const res = await fetch(cfg.updateUrl, {
                method: 'POST',
                credentials: 'same-origin',
                headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': csrf(), Accept: 'application/json' },
                body: JSON.stringify({ preferences }),
            });
            const data = await res.json().catch(() => ({}));
            if (!res.ok || !data.success) throw new Error('failed');
            notify('success', i18n.saved || 'Saved');
        } catch (err) {
            notify('error', i18n.failed || 'Failed');
        } finally {
            btn?.removeAttribute('disabled');
        }
    });

    load();
})(window);
