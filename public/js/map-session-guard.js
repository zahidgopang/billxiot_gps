/**
 * Map session lifecycle: keep session on refresh, end when leaving the map page.
 */
(function () {
    'use strict';

    const RELOAD_KEY = 'gps_map_reload_token';
    const cfg = window.DEVICE_MAP_CONFIG || {};
    const mapToken = cfg.mapToken || '';
    const endUrl = (cfg.mapSession && cfg.mapSession.endUrl) || (document.body && document.body.dataset.mapSessionEnd) || '/map-session/end';
    const csrf = cfg.csrfToken || document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '';

    function isMapPagePath() {
        return /\/(?:user|admin)\/device\/[^/]+\/map\/?$/i.test(window.location.pathname);
    }

    function endMapSessionBeacon() {
        if (!endUrl || !csrf) {
            return;
        }
        const form = new FormData();
        form.append('_token', csrf);
        if (navigator.sendBeacon) {
            navigator.sendBeacon(endUrl, form);
            return;
        }
        fetch(endUrl, {
            method: 'POST',
            headers: {
                Accept: 'application/json',
                'X-CSRF-TOKEN': csrf,
            },
            body: form,
            keepalive: true,
        }).catch(function () {});
    }

    if (mapToken && isMapPagePath()) {
        var pending = sessionStorage.getItem(RELOAD_KEY);
        window.__MAP_PAGE_IS_REFRESH__ = pending === mapToken;
        sessionStorage.removeItem(RELOAD_KEY);

        window.addEventListener('pagehide', function () {
            sessionStorage.setItem(RELOAD_KEY, mapToken);
        });

        return;
    }

    var pendingLeave = sessionStorage.getItem(RELOAD_KEY);
    if (pendingLeave) {
        sessionStorage.removeItem(RELOAD_KEY);
        if (!isMapPagePath()) {
            endMapSessionBeacon();
        }
    }
})();
