/**
 * Light deterrent for casual view-source / DevTools / right-click copying.
 * Not a security boundary — determined users can always bypass client scripts.
 */
(function () {
    'use strict';

    if (!window.__CLIENT_HARDENING__) {
        return;
    }

    document.addEventListener('contextmenu', function (e) {
        e.preventDefault();
    });

    document.addEventListener('keydown', function (e) {
        var key = (e.key || '').toLowerCase();

        if (key === 'f12') {
            e.preventDefault();
            return;
        }

        if (e.ctrlKey && e.shiftKey && (key === 'i' || key === 'j' || key === 'c')) {
            e.preventDefault();
            return;
        }

        if (e.ctrlKey && !e.shiftKey && key === 'u') {
            e.preventDefault();
        }
    });

    document.addEventListener('dragstart', function (e) {
        if (e.target && (e.target.tagName === 'IMG' || e.target.closest('img'))) {
            e.preventDefault();
        }
    });
})();
