(function (global) {
    'use strict';
    const cfg = global.TRACKING_SETTINGS_CONFIG;
    if (!cfg) return;

    const i18n = cfg.i18n || {};
    const form = document.getElementById('gtSettingsForm');

    const csrf = () => document.querySelector('meta[name="csrf-token"]')?.content || '';

    function notify(icon, title) {
        if (global.Swal) {
            global.Swal.fire({ icon, title, timer: icon === 'success' ? 1600 : undefined, showConfirmButton: icon !== 'success' });
        } else {
            global.alert(title);
        }
    }

    function fillForm(settings) {
        if (!form || !settings) return;
        Object.entries(settings).forEach(([key, value]) => {
            const el = form.elements[key];
            if (!el) return;
            if (el.type === 'checkbox') {
                el.checked = !!Number(value) || value === true || value === '1' || value === 'true';
            } else if (value != null) {
                el.value = value;
            }
        });
    }

    async function load() {
        if (!cfg.jsonUrl) return;
        try {
            const res = await fetch(cfg.jsonUrl, { credentials: 'same-origin', headers: { Accept: 'application/json' } });
            const data = await res.json();
            fillForm(data.settings || {});
        } catch (e) { /* keep server-rendered defaults */ }
    }

    form?.addEventListener('submit', async (e) => {
        e.preventDefault();
        const settings = Object.fromEntries(new FormData(form).entries());
        // Unchecked checkboxes are omitted from FormData — send explicit 0.
        if (!settings.maintenance_notify_sub_accounts) {
            settings.maintenance_notify_sub_accounts = '0';
        }
        const btn = form.querySelector('button[type="submit"]');
        btn?.setAttribute('disabled', 'disabled');
        try {
            const res = await fetch(cfg.updateUrl, {
                method: 'POST',
                credentials: 'same-origin',
                headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': csrf(), Accept: 'application/json' },
                body: JSON.stringify({ settings }),
            });
            const data = await res.json().catch(() => ({}));
            if (!res.ok || !data.success) {
                notify('error', data.message || i18n.failed || 'Failed');
                return;
            }
            fillForm(data.settings || settings);
            notify('success', i18n.saved || 'Saved');
        } catch (err) {
            notify('error', i18n.failed || 'Failed');
        } finally {
            btn?.removeAttribute('disabled');
        }
    });

    load();
})(window);
