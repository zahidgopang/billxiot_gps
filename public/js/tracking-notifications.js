(function (global) {
    'use strict';
    const cfg = global.TRACKING_NOTIFICATIONS_CONFIG;
    if (!cfg) return;

    const i18n = cfg.i18n || {};
    const form = document.getElementById('gtNotifForm');
    const body = document.getElementById('gtNotifBody');
    const channels = ['web', 'push', 'email', 'whatsapp'];

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

    function row(p) {
        const cells = channels.map((ch) => {
            const checked = p[ch] ? 'checked' : '';
            const label = escHtml(i18n[ch] || ch);
            return `<td class="gt-notif-ch"><input type="checkbox" class="form-check-input" data-type="${escHtml(p.type)}" data-ch="${ch}" ${checked} aria-label="${label}"></td>`;
        }).join('');
        return `<tr>
            <td>${escHtml(p.label || p.type)}</td>
            ${cells}
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
            body.innerHTML = `<tr><td colspan="5" class="gt-notif-empty">${escHtml(i18n.noTypes || 'No notification types')}</td></tr>`;
            return;
        }

        body.innerHTML = prefs.map(row).join('');
    }

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
        const preferences = [...types].map((type) => {
            const pref = { type };
            channels.forEach((ch) => {
                pref[ch] = !!body.querySelector(`[data-type="${CSS.escape(type)}"][data-ch="${ch}"]`)?.checked;
            });
            return pref;
        });

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
