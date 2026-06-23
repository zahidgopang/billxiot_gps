@php
    $panel = $panel ?? (request()->routeIs('client.*') ? 'client' : 'admin');
@endphp
@if($panel === 'admin' && !empty($clients) && $clients->count())
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

        function initClientSelect2() {
            if (!$ || !$.fn.select2 || !window.FormEnhancements) return;
            if (!$(clientSelect).hasClass('select2-hidden-accessible')) {
                window.FormEnhancements.initSelect2(clientSelect.closest('.admin-field') || clientSelect.parentElement);
            }
        }

        function setDeviceOptions(devices, keepDeviceId) {
            destroyDeviceSelect2();

            const placeholder = devices.length ? i18n.selectDevice : i18n.noDevices;
            deviceSelect.innerHTML = '';

            const blank = document.createElement('option');
            blank.value = '';
            blank.textContent = placeholder;
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
                headers: {
                    'Accept': 'application/json',
                    'X-Requested-With': 'XMLHttpRequest',
                },
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

        initClientSelect2();

        if (clientSelect.value) {
            loadDevicesForClient(clientSelect.value, selectedDeviceId || null);
        } else {
            initDeviceSelect2();
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
@endif
