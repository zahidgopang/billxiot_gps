@php
    $appI18n = [
        'areYouSure' => __('app.common.are_you_sure'),
        'cannotUndo' => __('app.common.cannot_undo'),
        'yesDelete' => __('app.common.yes_delete'),
        'cancel' => __('app.common.cancel'),
        'delete' => __('app.common.delete'),
        'save' => __('app.common.save'),
        'noData' => __('app.common.no_data'),
        'deleteUserConfirm' => __('app.forms.delete_user_confirm'),
        'searchPlaceholder' => __('app.forms.search_placeholder_datatable'),
        'recordsPerPage' => __('app.forms.records_per_page'),
        'darkModeEnabled' => __('app.forms.dark_mode_enabled'),
        'darkModeDisabled' => __('app.forms.dark_mode_disabled'),
        'on' => __('app.forms.on'),
        'beta' => __('app.forms.beta'),
        'online' => __('app.admin.navbar.online'),
        'users' => __('app.admin.navbar.users_stat'),
        'devices' => __('app.admin.navbar.devices_stat'),
        'isRtl' => ($htmlDir ?? 'ltr') === 'rtl',
        'adding' => __('app.user.devices.adding'),
        'updating' => __('app.user.devices.updating'),
        'close' => __('app.forms.close'),
        'selectPlaceholder' => __('app.forms.select_placeholder'),
    ];
@endphp
<script>
window.APP_I18N = @json($appI18n);

window.adminEscapeHtml = function (text) {
    const div = document.createElement('div');
    div.textContent = text == null ? '' : String(text);
    return div.innerHTML;
};

/**
 * Close Select2 and remove stray dropdown nodes from the document.
 */
window.adminCloseSelect2Dropdowns = function () {
    if (typeof jQuery !== 'undefined' && jQuery.fn.select2) {
        jQuery('select.select2-hidden-accessible').each(function () {
            try {
                jQuery(this).select2('close');
            } catch (e) { /* ignore */ }
        });
    }
    document.querySelectorAll('.select2-dropdown').forEach(function (el) {
        el.remove();
    });
};

/**
 * Flash message using Bootstrap Toast (not SweetAlert) so Select2 never
 * appears inside the notification.
 */
window.adminFlashToast = function (icon, message) {
    if (!message) {
        return;
    }

    window.adminCloseSelect2Dropdowns();

    if (document.activeElement && typeof document.activeElement.blur === 'function') {
        document.activeElement.blur();
    }

    document.body.classList.add('admin-flash-visible');

    const isRtl = window.APP_I18N && window.APP_I18N.isRtl;
    let container = document.getElementById('adminToastContainer');
    if (!container) {
        container = document.createElement('div');
        container.id = 'adminToastContainer';
        container.className = 'toast-container position-fixed p-3';
        container.style.zIndex = '10050';
        container.classList.add(isRtl ? 'top-0' : 'top-0', isRtl ? 'start-0' : 'end-0');
        document.body.appendChild(container);
    }

    const isError = icon === 'error';
    const bgClass = isError ? 'text-bg-danger' : 'text-bg-success';
    const toastEl = document.createElement('div');
    toastEl.className = 'toast align-items-center border-0 show shadow ' + bgClass;
    toastEl.setAttribute('role', 'alert');
    toastEl.setAttribute('aria-live', 'assertive');
    toastEl.setAttribute('aria-atomic', 'true');
    toastEl.innerHTML =
        '<div class="d-flex">' +
            '<div class="toast-body d-flex align-items-center gap-2">' +
                '<i class="fas fa-' + (isError ? 'exclamation-circle' : 'check-circle') + '" aria-hidden="true"></i>' +
                '<span>' + window.adminEscapeHtml(message) + '</span>' +
            '</div>' +
            '<button type="button" class="btn-close btn-close-white me-2 m-auto" data-bs-dismiss="toast" aria-label="' + window.adminEscapeHtml(window.APP_I18N?.close || 'Close') + '"></button>' +
        '</div>';

    container.appendChild(toastEl);

    const cleanup = function () {
        toastEl.remove();
        if (!container.children.length) {
            container.remove();
        }
        if (!document.getElementById('adminToastContainer')) {
            document.body.classList.remove('admin-flash-visible');
        }
    };

    if (typeof bootstrap !== 'undefined' && bootstrap.Toast) {
        const toast = bootstrap.Toast.getOrCreateInstance(toastEl, {
            autohide: true,
            delay: isError ? 4000 : 3000,
        });
        toastEl.addEventListener('hidden.bs.toast', cleanup, { once: true });
        toast.show();
    } else {
        setTimeout(cleanup, isError ? 4000 : 3000);
    }
};

if (window.APP_I18N.isRtl) {
    document.documentElement.setAttribute('dir', 'rtl');
    document.documentElement.setAttribute('lang', @json($htmlLang ?? 'ar'));
}
</script>
