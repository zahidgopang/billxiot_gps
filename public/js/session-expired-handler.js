(function (global) {
    'use strict';

    var cfg = global.SESSION_EXPIRED_CONFIG || {};
    var loginUrl = cfg.loginUrl || '/login';
    var message = cfg.message || 'Your session has expired. Please log in again.';
    var storageKey = cfg.storageKey || 'login_flash';

    function redirectToLogin(url) {
        try {
            sessionStorage.setItem(storageKey, message);
        } catch (e) {
            // Ignore private mode / blocked storage.
        }
        global.location.href = url || loginUrl;
    }

    if (typeof global.fetch === 'function') {
        var nativeFetch = global.fetch;
        global.fetch = function () {
            return nativeFetch.apply(this, arguments).then(function (response) {
                if (response.status !== 419) {
                    return response;
                }

                response.clone().json().catch(function () {
                    return null;
                }).then(function (data) {
                    redirectToLogin(data && data.redirect ? data.redirect : loginUrl);
                });

                return response;
            });
        };
    }

    if (global.jQuery) {
        global.jQuery(document).ajaxError(function (_event, xhr) {
            if (xhr && xhr.status === 419) {
                redirectToLogin(loginUrl);
            }
        });
    }
})(window);
