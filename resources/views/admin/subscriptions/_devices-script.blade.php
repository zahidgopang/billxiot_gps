@php
    $panel = $panel ?? (request()->routeIs('client.*') ? 'client' : 'admin');
    $isCreate = ! isset($subscription) || $subscription === null;
@endphp
@if($isCreate)
<script>
(function () {
    function boot() {
        const clientSelect = document.getElementById('subscription-client-id');
        const userSelect = document.getElementById('subscription-user-id');
        const deviceSelect = document.getElementById('subscription-device-ids');
        const selectAllBtn = document.getElementById('subscription-select-all-devices');
        const clearAllBtn = document.getElementById('subscription-clear-devices');
        if (!deviceSelect) return;

        const panel = @json($panel);
        const clientIdFromPanel = @json($panel === 'client' ? (string) ($selectedClient ?? '') : '');
        const clientFormUsersUrl = @json($panel === 'client' ? route('client.subscriptions.form-users') : null);
        const clientFormDevicesUrl = @json($panel === 'client' ? route('client.subscriptions.form-devices') : null);
        const usersUrlForClient = function (clientId) {
            if (panel === 'client') {
                return clientFormUsersUrl;
            }
            return @json(route('admin.clients.users', ['client' => '__CLIENT__'])).replace('__CLIENT__', encodeURIComponent(clientId));
        };
        const devicesUrlForClient = function (clientId, userId) {
            let url;
            if (panel === 'client') {
                url = clientFormDevicesUrl;
            } else {
                url = @json(route('admin.clients.devices', ['client' => '__CLIENT__'])).replace('__CLIENT__', encodeURIComponent(clientId));
            }
            if (userId) {
                url += (url.indexOf('?') >= 0 ? '&' : '?') + 'user_id=' + encodeURIComponent(userId);
            }
            return url;
        };
        const selectedUserId = @json((string) ($selectedUserId ?? ''));
        const selectedDeviceIds = @json($selectedDeviceIds ?? []);
        const i18n = {
            selectClientFirst: @json(__('app.forms.select_client_first')),
            selectEndUser: @json(__('app.forms.select_end_user_optional')),
            selectDevices: @json(__('app.forms.select_devices')),
            noUsers: @json(__('app.forms.no_end_users_for_client')),
            noDevices: @json(__('app.forms.no_devices_for_client')),
        };
        const $ = window.jQuery;

        function destroySelect2(el) {
            if ($ && $.fn.select2 && el && $(el).hasClass('select2-hidden-accessible')) {
                $(el).select2('destroy');
            }
        }

        function initDeviceSelect2() {
            if (!$ || !$.fn.select2 || !deviceSelect || !window.FormEnhancements) return;
            destroySelect2(deviceSelect);
            const $el = $(deviceSelect);
            const placeholder = deviceSelect.getAttribute('data-placeholder') || i18n.selectDevices;
            const options = {
                theme: 'bootstrap-5',
                width: '100%',
                placeholder: placeholder,
                closeOnSelect: false,
                dropdownParent: $(document.body),
            };
            if (document.documentElement.getAttribute('dir') === 'rtl') {
                options.dir = 'rtl';
            }
            $el.select2(options);
        }

        function initSelect2(el) {
            if (!$ || !$.fn.select2 || !el || !window.FormEnhancements) return;
            if (el === deviceSelect) {
                initDeviceSelect2();
                return;
            }
            destroySelect2(el);
            window.FormEnhancements.initSelect2(el.closest('.admin-field') || el.parentElement);
        }

        function allDeviceOptionValues() {
            return Array.prototype.filter.call(deviceSelect.options, function (opt) {
                return opt.value !== '';
            }).map(function (opt) {
                return String(opt.value);
            });
        }

        function currentDeviceSelection() {
            if ($ && $.fn.select2 && $(deviceSelect).hasClass('select2-hidden-accessible')) {
                const val = $(deviceSelect).val();
                return val ? val.map(String) : [];
            }
            return Array.prototype.filter.call(deviceSelect.selectedOptions, function (opt) {
                return opt.value !== '';
            }).map(function (opt) {
                return String(opt.value);
            });
        }

        function applyDeviceSelection(ids) {
            const values = (ids || []).map(String);
            if ($ && $.fn.select2 && $(deviceSelect).hasClass('select2-hidden-accessible')) {
                $(deviceSelect).val(values.length ? values : null).trigger('change');
            } else {
                Array.prototype.forEach.call(deviceSelect.options, function (opt) {
                    opt.selected = values.indexOf(String(opt.value)) >= 0;
                });
            }
            syncSelectAllButtons();
            document.dispatchEvent(new CustomEvent('subscription:devices-ready'));
        }

        function syncSelectAllButtons() {
            if (!selectAllBtn) return;
            const total = allDeviceOptionValues().length;
            const selected = currentDeviceSelection().length;
            const allSelected = total > 0 && selected === total;
            selectAllBtn.classList.toggle('active', allSelected);
            selectAllBtn.setAttribute('aria-pressed', allSelected ? 'true' : 'false');
            if (clearAllBtn) {
                clearAllBtn.disabled = selected === 0;
            }
        }

        function selectAllDevices() {
            applyDeviceSelection(allDeviceOptionValues());
        }

        function clearAllDevices() {
            applyDeviceSelection([]);
        }

        function setUserOptions(users, keepUserId) {
            if (!userSelect) return;
            destroySelect2(userSelect);
            userSelect.innerHTML = '';
            const blank = document.createElement('option');
            blank.value = '';
            blank.textContent = users.length ? i18n.selectEndUser : i18n.noUsers;
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
            initSelect2(userSelect);
            if ($ && $.fn.select2) {
                $(userSelect).val(keepUserId ? String(keepUserId) : '').trigger('change');
            }
        }

        function setDeviceOptions(devices, keepIds) {
            destroySelect2(deviceSelect);
            deviceSelect.innerHTML = '';
            if (!devices.length) {
                const blank = document.createElement('option');
                blank.value = '';
                blank.textContent = i18n.noDevices;
                blank.disabled = true;
                deviceSelect.appendChild(blank);
            } else {
                devices.forEach(function (d) {
                    const opt = document.createElement('option');
                    opt.value = String(d.id);
                    opt.textContent = d.text;
                    if (keepIds && keepIds.indexOf(String(d.id)) >= 0) {
                        opt.selected = true;
                    }
                    deviceSelect.appendChild(opt);
                });
            }
            deviceSelect.disabled = false;
            initDeviceSelect2();
            applyDeviceSelection(keepIds && keepIds.length ? keepIds.map(String) : []);
        }

        function resolvedClientId() {
            if (clientSelect && clientSelect.value) {
                return clientSelect.value;
            }
            return clientIdFromPanel || '';
        }

        function loadUsers(clientId, keepUserId) {
            if (!userSelect) {
                loadDevices(clientId, null, selectedDeviceIds);
                return;
            }
            if (!clientId) {
                setUserOptions([], null);
                setDeviceOptions([], []);
                return;
            }
            fetch(usersUrlForClient(clientId), {
                headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
                credentials: 'same-origin',
            })
                .then(function (res) {
                    if (!res.ok) throw new Error('users');
                    return res.json();
                })
                .then(function (data) {
                    setUserOptions(data.users || [], keepUserId);
                    loadDevices(clientId, keepUserId || null, keepUserId ? selectedDeviceIds : []);
                })
                .catch(function () {
                    setUserOptions([], null);
                    setDeviceOptions([], []);
                });
        }

        function loadDevices(clientId, userId, keepIds) {
            if (!clientId) {
                setDeviceOptions([], []);
                return;
            }
            fetch(devicesUrlForClient(clientId, userId), {
                headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
                credentials: 'same-origin',
            })
                .then(function (res) {
                    if (!res.ok) throw new Error('devices');
                    return res.json();
                })
                .then(function (data) {
                    setDeviceOptions(data.devices || [], keepIds);
                })
                .catch(function () {
                    setDeviceOptions([], []);
                });
        }

        function onClientChange() {
            loadUsers(resolvedClientId(), null);
        }

        function onUserChange() {
            const clientId = resolvedClientId();
            const userId = userSelect ? userSelect.value : '';
            loadDevices(clientId, userId || null, []);
        }

        if (clientSelect) {
            clientSelect.addEventListener('change', onClientChange);
            if ($ && $.fn.select2) {
                $(clientSelect).on('change select2:select select2:clear', onClientChange);
            }
            initSelect2(clientSelect);
        }

        if (userSelect) {
            userSelect.addEventListener('change', onUserChange);
            if ($ && $.fn.select2) {
                $(userSelect).on('change select2:select select2:clear', onUserChange);
            }
        }

        if (selectAllBtn) {
            selectAllBtn.addEventListener('click', function (e) {
                e.preventDefault();
                selectAllDevices();
            });
        }

        if (clearAllBtn) {
            clearAllBtn.addEventListener('click', function (e) {
                e.preventDefault();
                clearAllDevices();
            });
        }

        deviceSelect.addEventListener('change', syncSelectAllButtons);
        if ($ && $.fn.select2) {
            $(deviceSelect).on('change select2:select select2:unselect', syncSelectAllButtons);
        }

        initDeviceSelect2();

        const initialClient = resolvedClientId();
        if (initialClient) {
            loadUsers(initialClient, selectedUserId || null);
        }
    }

    if (window.jQuery) {
        window.jQuery(function () {
            if (window.FormEnhancements) {
                boot();
            } else {
                window.setTimeout(boot, 50);
            }
        });
    } else if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', boot);
    } else {
        boot();
    }
})();
</script>
@elseif($panel === 'admin' && !empty($clients) && $clients->count())
<script>
(function () {
    function boot() {
        const clientSelect = document.getElementById('subscription-client-id');
        const deviceSelect = document.getElementById('subscription-device-id');
        if (!clientSelect || !deviceSelect) return;
        const devicesByClient = @json($devicesByClient ?? []);
        const devicesUrlForClient = function (clientId) {
            return @json(route($panel . '.clients.devices', ['client' => '__CLIENT__'])).replace('__CLIENT__', encodeURIComponent(clientId));
        };
        const selectedDeviceId = @json((string) ($selectedDeviceId ?? ''));
        const i18n = {
            selectClientFirst: @json(__('app.forms.select_client_first')),
            selectDevice: @json(__('app.forms.select_device_option')),
            noDevices: @json(__('app.forms.no_devices_for_client')),
        };
        const $ = window.jQuery;

        function destroyDeviceSelect2() {
            if ($ && $.fn.select2 && $(deviceSelect).hasClass('select2-hidden-accessible')) {
                $(deviceSelect).select2('destroy');
            }
        }

        function initDeviceSelect2() {
            if (!$ || !$.fn.select2 || !window.FormEnhancements) return;
            destroyDeviceSelect2();
            window.FormEnhancements.initSelect2(deviceSelect.closest('.admin-field') || deviceSelect.parentElement);
        }

        function setDeviceOptions(devices, keepDeviceId) {
            destroyDeviceSelect2();
            deviceSelect.innerHTML = '';
            const blank = document.createElement('option');
            blank.value = '';
            blank.textContent = devices.length ? i18n.selectDevice : i18n.noDevices;
            deviceSelect.appendChild(blank);
            devices.forEach(function (d) {
                const opt = document.createElement('option');
                opt.value = String(d.id);
                opt.textContent = d.text;
                if (keepDeviceId && String(keepDeviceId) === String(d.id)) {
                    opt.selected = true;
                }
                deviceSelect.appendChild(opt);
            });
            deviceSelect.disabled = false;
            initDeviceSelect2();
            if ($ && $.fn.select2) {
                $(deviceSelect).val(keepDeviceId ? String(keepDeviceId) : '').trigger('change');
            }
            document.dispatchEvent(new CustomEvent('subscription:devices-ready'));
        }

        function loadDevicesForClient(clientId, keepDeviceId) {
            if (!clientId) {
                destroyDeviceSelect2();
                deviceSelect.innerHTML = '<option value="">' + i18n.selectClientFirst + '</option>';
                deviceSelect.disabled = false;
                initDeviceSelect2();
                return;
            }
            const key = String(clientId);
            if (Object.prototype.hasOwnProperty.call(devicesByClient, key)) {
                setDeviceOptions(devicesByClient[key] || [], keepDeviceId);
                return;
            }
            deviceSelect.disabled = true;
            fetch(devicesUrlForClient(clientId), {
                headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
                credentials: 'same-origin',
            })
                .then(function (res) {
                    if (!res.ok) throw new Error('Failed to load devices');
                    return res.json();
                })
                .then(function (data) {
                    devicesByClient[key] = data.devices || [];
                    setDeviceOptions(devicesByClient[key], keepDeviceId);
                })
                .catch(function () {
                    setDeviceOptions([], null);
                });
        }

        function onClientChange() {
            loadDevicesForClient(clientSelect.value, null);
        }

        clientSelect.addEventListener('change', onClientChange);
        if ($ && $.fn.select2) {
            $(clientSelect).on('change select2:select select2:clear', onClientChange);
        }
        if (window.FormEnhancements) {
            window.FormEnhancements.initSelect2(clientSelect.closest('.admin-field') || clientSelect.parentElement);
        }
        if (clientSelect.value) {
            loadDevicesForClient(clientSelect.value, selectedDeviceId || null);
        } else {
            initDeviceSelect2();
        }
    }

    if (window.jQuery) {
        window.jQuery(function () { boot(); });
    } else if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', boot);
    } else {
        boot();
    }
})();
</script>
@endif
