@php
    $panel = $panel ?? (request()->routeIs('client.*') ? 'client' : 'admin');
@endphp
@if($panel === 'admin' && !empty($clients) && $clients->count())
<script>
(function () {
    function boot() {
    const clientSelect = document.getElementById('device-client-id');
    const userSelect = document.getElementById('device-user-id');
    if (!clientSelect || !userSelect) return;

    const usersByClient = @json($usersByClient ?? []);
    const usersUrlForClient = function (clientId) {
        return @json(route($panel . '.clients.users', ['client' => '__CLIENT__'])).replace('__CLIENT__', encodeURIComponent(clientId));
    };
    const selectedUserId = @json((string) ($selectedUserId ?? ''));
    const i18n = {
        selectClientFirst: @json(__('app.forms.select_client_first')),
        selectUser: @json(__('app.forms.select_user')),
        noUsers: @json(__('app.forms.no_users_for_client')),
    };
    const $ = window.jQuery;

    function destroyUserSelect2() {
        if ($ && $.fn.select2 && $(userSelect).hasClass('select2-hidden-accessible')) {
            $(userSelect).select2('destroy');
        }
    }

    function initUserSelect2() {
        if (!$ || !$.fn.select2 || !window.FormEnhancements) return;
        destroyUserSelect2();
        window.FormEnhancements.initSelect2(userSelect.closest('.admin-field') || userSelect.parentElement);
    }

    function setUserOptions(users, keepUserId) {
        destroyUserSelect2();

        const placeholder = users.length ? i18n.selectUser : i18n.noUsers;
        userSelect.innerHTML = '';

        const blank = document.createElement('option');
        blank.value = '';
        blank.textContent = placeholder;
        userSelect.appendChild(blank);

        users.forEach(function (u) {
            const opt = document.createElement('option');
            opt.value = String(u.id);
            opt.textContent = u.text;
            if (keepUserId && String(keepUserId) === String(u.id)) {
                opt.selected = true;
            }
            userSelect.appendChild(opt);
        });

        userSelect.disabled = false;
        initUserSelect2();

        if ($ && $.fn.select2) {
            $(userSelect).val(keepUserId ? String(keepUserId) : '').trigger('change');
        }
    }

    function loadUsersForClient(clientId, keepUserId) {
        if (!clientId) {
            destroyUserSelect2();
            userSelect.innerHTML = '<option value="">' + i18n.selectClientFirst + '</option>';
            initUserSelect2();
            return;
        }

        const key = String(clientId);

        if (Object.prototype.hasOwnProperty.call(usersByClient, key)) {
            setUserOptions(usersByClient[key] || [], keepUserId);
            return;
        }

        userSelect.disabled = true;

        fetch(usersUrlForClient(clientId), {
            headers: {
                'Accept': 'application/json',
                'X-Requested-With': 'XMLHttpRequest',
            },
            credentials: 'same-origin',
        })
            .then(function (res) {
                if (!res.ok) throw new Error('Failed to load users');
                return res.json();
            })
            .then(function (data) {
                usersByClient[key] = data.users || [];
                setUserOptions(usersByClient[key], keepUserId);
            })
            .catch(function () {
                setUserOptions([], null);
            });
    }

    function onClientChange() {
        loadUsersForClient(clientSelect.value, null);
    }

    clientSelect.addEventListener('change', onClientChange);

    if ($ && $.fn.select2) {
        $(clientSelect).on('change select2:select select2:clear', onClientChange);
    }

    if (clientSelect.value) {
        loadUsersForClient(clientSelect.value, selectedUserId || null);
    } else {
        initUserSelect2();
    }
    }

    if (window.jQuery) {
        window.jQuery(boot);
    } else if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', boot);
    } else {
        boot();
    }
})();
</script>
@endif
