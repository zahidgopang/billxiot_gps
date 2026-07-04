(function () {
    'use strict';

    const cfg = window.PERM_MGMT_CONFIG || {};
    const mode = cfg.mode || 'role';
    const labels = cfg.labels || {};

    function qs(sel, root) {
        return (root || document).querySelector(sel);
    }

    function qsa(sel, root) {
        return Array.from((root || document).querySelectorAll(sel));
    }

    function buildUrl(params) {
        const url = new URL(cfg.searchUrl || window.location.href);
        Object.entries(params || {}).forEach(([key, value]) => {
            if (value === null || value === undefined || value === '') {
                url.searchParams.delete(key);
            } else {
                url.searchParams.set(key, String(value));
            }
        });
        return url.toString();
    }

    function updateGrantedCount() {
        let count = 0;
        qsa('.perm-item').forEach((item) => {
            if (item.classList.contains('is-hidden')) return;
            if (item.querySelector('.perm-card__check')?.checked) count += 1;
        });
        const el = document.getElementById('permStatGranted');
        if (!el) return;
        const strong = el.querySelector('strong');
        if (strong) strong.textContent = String(count);
    }

    function filterSearch(term) {
        const q = String(term || '').trim().toLowerCase();
        qsa('.perm-item').forEach((el) => {
            if (!q) {
                el.classList.remove('is-hidden');
                return;
            }
            const hay = el.dataset.search || '';
            el.classList.toggle('is-hidden', !hay.includes(q));
        });
        updateGrantedCount();
    }

    function bindSearch() {
        const input = qs('#permSearch');
        if (!input) return;

        let timer;
        input.addEventListener('input', () => {
            clearTimeout(timer);
            timer = setTimeout(() => filterSearch(input.value), 150);
        });

        if (input.value) filterSearch(input.value);
    }

    function bindCheckboxes() {
        qsa('.perm-card__check').forEach((cb) => {
            cb.addEventListener('change', () => {
                cb.closest('.perm-card')?.classList.toggle('is-granted', cb.checked);
                updateGrantedCount();
            });
        });
    }

    function setAll(checked) {
        qsa('.perm-card__check').forEach((cb) => {
            const item = cb.closest('.perm-item');
            if (item && item.classList.contains('is-hidden')) return;
            cb.checked = checked;
            cb.closest('.perm-card')?.classList.toggle('is-granted', checked);
        });
        updateGrantedCount();
    }

    function bindBulkActions() {
        qs('#permSelectAll')?.addEventListener('click', () => setAll(true));
        qs('#permClearAll')?.addEventListener('click', () => setAll(false));
    }

    function bindRoleNav() {
        qs('#permRoleSelect')?.addEventListener('change', (e) => {
            window.location.href = buildUrl({ mode: 'role', role: e.target.value });
        });
    }

    document.addEventListener('DOMContentLoaded', () => {
        bindSearch();
        bindCheckboxes();
        bindBulkActions();
        bindRoleNav();
        updateGrantedCount();
    });
})();
