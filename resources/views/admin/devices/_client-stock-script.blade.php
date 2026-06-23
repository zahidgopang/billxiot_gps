@php
    use App\Models\Device;

    $panel = $panel ?? 'admin';
    $formClientId = $formClientId ?? null;
    $allowedDeviceTypes = $allowedDeviceTypes ?? array_keys(Device::DEVICE_TYPES);
    $deviceType = $deviceType ?? old('device_type', '');
    $deviceTypeLabels = collect(Device::DEVICE_TYPES)->mapWithKeys(fn ($label, $key) => [
        $key => __('app.forms.device_type_' . $key),
    ]);
@endphp
<script>
document.addEventListener('DOMContentLoaded', function () {
    var panel = @json($panel);
    var clientSelect = document.getElementById('device-client-id');
    var typeSelect = document.getElementById('device-type');
    var stockPanel = document.getElementById('client-stock-balance-panel');
    var submitBtn = document.getElementById('device-submit-btn');
    var initialClientId = @json($formClientId);
    var currentType = @json($deviceType);
    var typeLabels = @json($deviceTypeLabels);
    var balanceUrl = panel === 'admin'
        ? @json(route('admin.clients.stock-balance', ['client' => '__ID__']))
        : @json(route('client.stock-balance'));

    var strings = {
        selectClient: @json(__('app.admin.stock_sales.select_client_for_balance')),
        noStock: @json(__('app.admin.stock_sales.no_client_stock')),
        selectType: @json(__('app.forms.select_device_type')),
        selectClientFirst: @json(__('app.forms.select_client_first')),
    };

    function setSubmitDisabled(disabled) {
        if (submitBtn) submitBtn.disabled = !!disabled;
    }

    function renderStockPanel(data, showSelectClient) {
        if (!stockPanel) return;

        if (showSelectClient) {
            stockPanel.innerHTML = '<p class="text-muted small mb-0">' + strings.selectClient + '</p>';
            return;
        }

        if (!data || ((data.totals.sold || 0) === 0 && (data.totals.installed || 0) === 0)) {
            stockPanel.innerHTML = '<div class="alert alert-warning py-2 mb-0 small"><i class="fas fa-box-open me-1"></i>' + strings.noStock + '</div>';
            return;
        }

        var html = '<div class="p-3 rounded border bg-light">';
        html += '<div class="row g-2 small mb-2">';
        html += '<div class="col-md-4"><span class="text-muted d-block">' + @json(__('app.admin.stock_sales.sold_to_client')) + '</span><span class="fw-semibold admin-ltr">' + (data.totals.sold || 0) + '</span></div>';
        html += '<div class="col-md-4"><span class="text-muted d-block">' + @json(__('app.admin.stock_sales.installed_count')) + '</span><span class="fw-semibold admin-ltr">' + (data.totals.installed || 0) + '</span></div>';
        html += '<div class="col-md-4"><span class="text-muted d-block">' + @json(__('app.admin.stock_sales.available_to_install')) + '</span><span class="fw-semibold admin-ltr text-success">' + (data.totals.available || 0) + '</span></div>';
        html += '</div>';

        if (data.by_type && data.by_type.length) {
            html += '<div class="table-responsive"><table class="table table-sm table-bordered mb-0 bg-white"><thead class="table-light"><tr>';
            html += '<th>' + @json(__('app.forms.device_type')) + '</th>';
            html += '<th class="text-end">' + @json(__('app.admin.stock_sales.sold_to_client')) + '</th>';
            html += '<th class="text-end">' + @json(__('app.admin.stock_sales.installed_count')) + '</th>';
            html += '<th class="text-end">' + @json(__('app.admin.stock_sales.available_to_install')) + '</th>';
            html += '</tr></thead><tbody>';
            data.by_type.forEach(function (row) {
                var label = typeLabels[row.device_type] || row.device_type;
                var availClass = row.available > 0 ? 'text-success' : 'text-muted';
                html += '<tr><td>' + label + '</td>';
                html += '<td class="text-end admin-ltr">' + row.sold + '</td>';
                html += '<td class="text-end admin-ltr">' + row.installed + '</td>';
                html += '<td class="text-end admin-ltr fw-semibold ' + availClass + '">' + row.available + '</td></tr>';
            });
            html += '</tbody></table></div>';
        }
        html += '</div>';
        stockPanel.innerHTML = html;
    }

    function installableTypes(data) {
        if (!data || !data.by_type) return [];
        var types = [];
        data.by_type.forEach(function (row) {
            if (row.available > 0) types.push(row.device_type);
        });
        if (currentType && types.indexOf(currentType) === -1) {
            types.push(currentType);
        }
        return types;
    }

    function destroyTypeSelect2() {
        if (!typeSelect || typeof window.jQuery === 'undefined') {
            return;
        }

        var $type = window.jQuery(typeSelect);
        if ($type.hasClass('select2-hidden-accessible')) {
            $type.select2('destroy');
        }
    }

    function initTypeSelect2() {
        if (!typeSelect || typeof window.FormEnhancements === 'undefined') {
            return;
        }

        destroyTypeSelect2();
        window.FormEnhancements.initSelect2(typeSelect.closest('.admin-form-col') || typeSelect.parentElement);
    }

    function renderDeviceTypes(types, needsClient, noStock) {
        if (!typeSelect) return;

        destroyTypeSelect2();
        typeSelect.innerHTML = '';

        if (needsClient) {
            var o0 = document.createElement('option');
            o0.value = '';
            o0.disabled = true;
            o0.selected = true;
            o0.textContent = strings.selectClientFirst;
            typeSelect.appendChild(o0);
            typeSelect.disabled = true;
            typeSelect.removeAttribute('required');
            setSubmitDisabled(true);
            return;
        }

        if (noStock || !types.length) {
            var o1 = document.createElement('option');
            o1.value = '';
            o1.disabled = true;
            o1.selected = true;
            o1.textContent = strings.noStock;
            typeSelect.appendChild(o1);
            typeSelect.disabled = true;
            typeSelect.removeAttribute('required');
            setSubmitDisabled(true);
            return;
        }

        var placeholder = document.createElement('option');
        placeholder.value = '';
        placeholder.disabled = true;
        placeholder.textContent = strings.selectType;
        if (!currentType || types.indexOf(currentType) === -1) {
            placeholder.selected = true;
        }
        typeSelect.appendChild(placeholder);

        types.forEach(function (key) {
            var opt = document.createElement('option');
            opt.value = key;
            opt.textContent = typeLabels[key] || key;
            if (currentType === key) {
                opt.selected = true;
                placeholder.selected = false;
            }
            typeSelect.appendChild(opt);
        });

        typeSelect.disabled = false;
        typeSelect.setAttribute('required', 'required');
        setSubmitDisabled(false);
        initTypeSelect2();
    }

    function onClientChange() {
        currentType = '';
        if (typeSelect) {
            typeSelect.value = '';
        }
        fetchBalance(clientSelect ? clientSelect.value : initialClientId);
    }

    function fetchBalance(clientId) {
        if (panel === 'admin' && !clientId) {
            renderStockPanel(null, true);
            renderDeviceTypes([], true, false);
            return;
        }

        var url = panel === 'admin'
            ? balanceUrl.replace('__ID__', encodeURIComponent(String(clientId)))
            : balanceUrl;

        fetch(url, { headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' } })
            .then(function (r) { return r.json(); })
            .then(function (data) {
                renderStockPanel(data, false);
                var types = installableTypes(data);
                renderDeviceTypes(types, false, types.length === 0);
            })
            .catch(function () {
                renderStockPanel(null, false);
                renderDeviceTypes(@json($allowedDeviceTypes), false, false);
            });
    }

    if (typeSelect) {
        typeSelect.addEventListener('change', function () {
            currentType = typeSelect.value;
        });
    }

    if (panel === 'admin' && clientSelect) {
        clientSelect.addEventListener('change', onClientChange);

        if (typeof window.jQuery !== 'undefined') {
            window.jQuery(clientSelect).on('change select2:select select2:clear', onClientChange);
        }

        if (window.FormEnhancements) {
            window.setTimeout(function () {
                fetchBalance(clientSelect.value || initialClientId);
            }, 0);
        } else {
            fetchBalance(clientSelect.value || initialClientId);
        }
    } else if (panel === 'client') {
        fetchBalance(initialClientId);
    }
});
</script>
