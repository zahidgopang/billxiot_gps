/**
 * Same module popup behavior as /tracking (data-tc-module → iframe modal).
 * Used on standalone module pages so the workspace top bar matches Live.
 */
(function (global) {
    'use strict';

    function escHtml(value) {
        return String(value ?? '')
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;')
            .replace(/'/g, '&#39;');
    }

    function openModule(url, title, icon) {
        const modalEl = document.getElementById('tcModuleModal');
        const frame = document.getElementById('tcModuleFrame');
        if (!modalEl || !frame || !global.bootstrap || !global.bootstrap.Modal) {
            // Fallback: full-page navigation (same destination as /tracking popup target)
            if (url) global.location.href = url;
            return;
        }

        const modal = global.bootstrap.Modal.getOrCreateInstance(modalEl);
        const loader = document.getElementById('tcModuleLoader');
        const titleEl = document.getElementById('tcModuleTitle');
        if (titleEl) {
            titleEl.innerHTML = `<i class="fas ${escHtml(icon || 'fa-satellite-dish')} me-2"></i>${escHtml(title || '')}`;
        }
        frame.title = title || '';
        if (loader) loader.hidden = false;
        frame.src = url + (url.includes('?') ? '&' : '?') + 'embed=1';
        modal.show();
    }

    function bindTrackingModulePopup() {
        if (document.documentElement.dataset.tcModulePopupBound === '1') return;
        document.documentElement.dataset.tcModulePopupBound = '1';

        // Event delegation — works even if nav links are re-rendered
        document.addEventListener('click', (e) => {
            const link = e.target && e.target.closest ? e.target.closest('[data-tc-module]') : null;
            if (!link) return;
            const url = link.getAttribute('data-tc-module') || link.dataset.tcModule;
            if (!url) return;
            e.preventDefault();
            openModule(
                url,
                link.getAttribute('data-tc-module-title') || link.dataset.tcModuleTitle || '',
                link.getAttribute('data-tc-module-icon') || link.dataset.tcModuleIcon || 'fa-satellite-dish'
            );
        });

        const modalEl = document.getElementById('tcModuleModal');
        const frame = document.getElementById('tcModuleFrame');
        const loader = document.getElementById('tcModuleLoader');
        if (!modalEl || !frame) return;

        frame.addEventListener('load', () => {
            if (loader && frame.src && frame.src !== 'about:blank') loader.hidden = true;
        });
        modalEl.addEventListener('hidden.bs.modal', () => {
            frame.src = 'about:blank';
            if (loader) loader.hidden = true;
        });
    }

    global.bindTrackingModulePopup = bindTrackingModulePopup;
    global.openTrackingModulePopup = openModule;

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', bindTrackingModulePopup);
    } else {
        bindTrackingModulePopup();
    }
})(window);
