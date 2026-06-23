@extends('admin.layouts.app')

@section('title', __('app.admin.stock_sales.create'))
@section('page-title', __('app.admin.stock_sales.create'))

@section('content')
    <x-admin.form-shell
        :action="route('admin.device-stock-sales.store')"
        :cancel-url="route('admin.device-stock-sales.index')"
    >
        <x-admin.form-section
            :title="__('app.admin.stock_sales.section_invoice')"
            icon="fas fa-file-invoice-dollar"
            :description="__('app.admin.stock_sales.section_invoice_hint')"
        >
            <x-admin.form-col>
                <label class="admin-label" for="sale-client">{{ __('app.admin.stock_sales.client') }}</label>
                <select name="client_id" id="sale-client" class="form-select form-select-sm" required>
                    <option value="">{{ __('app.admin.stock_sales.select_client') }}</option>
                    @foreach($clients as $client)
                        <option value="{{ $client->id }}" @selected((string) old('client_id') === (string) $client->id)>{{ $client->name }}</option>
                    @endforeach
                </select>
                @error('client_id') <p class="admin-field__error text-danger">{{ $message }}</p> @enderror
            </x-admin.form-col>

            <x-admin.form-col :full="true">
                @include('admin.partials.client-stock-balance', [
                    'balance' => $clientStockBalance ?? null,
                    'showSelectClientHint' => ! ($selectedClientId ?? null),
                ])
            </x-admin.form-col>

            <x-admin.form-col :full="true">
                <label class="admin-label" for="sale-notes">{{ __('app.admin.stock.notes') }}</label>
                <textarea name="notes" id="sale-notes" class="form-control form-control-sm" rows="2">{{ old('notes') }}</textarea>
                @error('notes') <p class="admin-field__error text-danger">{{ $message }}</p> @enderror
            </x-admin.form-col>
        </x-admin.form-section>

        <x-admin.form-section
            :title="__('app.admin.stock_sales.section_items')"
            icon="fas fa-boxes-stacked"
            :description="__('app.admin.stock_sales.section_items_hint')"
        >
            <x-admin.form-col :full="true">
                <label class="admin-label" for="sale-order">{{ __('app.admin.stock.order_code') }}</label>
                <select name="stock_order_id" id="sale-order" class="form-select form-select-sm" required>
                    <option value="">{{ __('app.admin.stock_sales.select_order') }}</option>
                    @foreach($orders as $order)
                        @php $avail = $order->availableQuantity(); @endphp
                        <option
                            value="{{ $order->id }}"
                            data-currency="{{ $order->currency }}"
                            data-unit-price="{{ number_format((float) $order->selling_price, 2, '.', '') }}"
                            data-available="{{ $avail }}"
                            data-label="{{ $order->order_code }} — {{ $order->displayLabel() }}"
                            @selected((string) old('stock_order_id') === (string) $order->id)
                        >
                            {{ $order->order_code }} — {{ $order->displayLabel() }}
                            ({{ __('app.admin.stock.available') }}: {{ number_format($avail) }})
                        </option>
                    @endforeach
                </select>
                @error('stock_order_id') <p class="admin-field__error text-danger">{{ $message }}</p> @enderror
            </x-admin.form-col>

            <x-admin.form-col>
                <label class="admin-label" for="sale-qty">{{ __('app.admin.stock.quantity') }}</label>
                <input type="number" name="quantity" id="sale-qty" class="form-control form-control-sm admin-ltr" dir="ltr"
                       min="1" value="{{ old('quantity', 1) }}" required>
                <p class="admin-hint mb-0" id="sale-qty-hint">{{ __('app.admin.stock_sales.select_order_first') }}</p>
                @error('quantity') <p class="admin-field__error text-danger">{{ $message }}</p> @enderror
            </x-admin.form-col>

            <x-admin.form-col>
                <label class="admin-label" for="sale-unit-price-display">{{ __('app.admin.stock.selling_price') }}</label>
                <input type="text" id="sale-unit-price-display" class="form-control form-control-sm admin-ltr" dir="ltr"
                       readonly tabindex="-1" value="—">
                <p class="admin-hint mb-0">{{ __('app.admin.stock_sales.unit_price_from_stock') }}</p>
            </x-admin.form-col>

            <x-admin.form-col>
                <label class="admin-label">{{ __('app.admin.stock_sales.line_total') }}</label>
                <input type="text" id="sale-line-total-display" class="form-control form-control-sm admin-ltr fw-semibold" dir="ltr"
                       readonly tabindex="-1" value="—">
                <p class="admin-hint mb-0" id="sale-formula-hint">—</p>
            </x-admin.form-col>

            <x-admin.form-col :full="true">
                <div class="p-3 rounded border bg-light" id="sale-invoice-preview">
                    <div class="row g-2 small">
                        <div class="col-md-3">
                            <span class="text-muted d-block">{{ __('app.admin.stock.quantity') }}</span>
                            <span class="fw-semibold admin-ltr" id="preview-qty" dir="ltr">—</span>
                        </div>
                        <div class="col-md-3">
                            <span class="text-muted d-block">{{ __('app.admin.stock.selling_price') }}</span>
                            <span class="fw-semibold admin-ltr" id="preview-unit" dir="ltr">—</span>
                        </div>
                        <div class="col-md-3">
                            <span class="text-muted d-block">{{ __('app.admin.stock_sales.max_allowed') }}</span>
                            <span class="fw-semibold admin-ltr" id="preview-max" dir="ltr">—</span>
                        </div>
                        <div class="col-md-3">
                            <span class="text-muted d-block">{{ __('app.admin.stock_sales.invoice_total') }}</span>
                            <span class="fw-semibold admin-ltr text-success" id="preview-total" dir="ltr">—</span>
                        </div>
                    </div>
                </div>
            </x-admin.form-col>
        </x-admin.form-section>

        <x-slot:footer>
            <a href="{{ route('admin.device-stock-sales.index') }}" class="btn btn-light btn-sm">
                <i class="fas fa-times me-1" aria-hidden="true"></i>{{ __('app.common.cancel') }}
            </a>
            <button type="submit" class="btn btn-primary btn-sm" id="sale-submit-btn" disabled>
                <i class="fas fa-check me-1" aria-hidden="true"></i>{{ __('app.admin.stock_sales.issue') }}
            </button>
        </x-slot:footer>
    </x-admin.form-shell>
@endsection

@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', function () {
    var sel = document.getElementById('sale-order');
    var qty = document.getElementById('sale-qty');
    var unitDisplay = document.getElementById('sale-unit-price-display');
    var lineDisplay = document.getElementById('sale-line-total-display');
    var qtyHint = document.getElementById('sale-qty-hint');
    var formulaHint = document.getElementById('sale-formula-hint');
    var submitBtn = document.getElementById('sale-submit-btn');
    var previewQty = document.getElementById('preview-qty');
    var previewUnit = document.getElementById('preview-unit');
    var previewMax = document.getElementById('preview-max');
    var previewTotal = document.getElementById('preview-total');

    var maxAvailable = 0;
    var unitPrice = 0;
    var currency = '';

    function fmtMoney(n) {
        return (currency ? currency + ' ' : '') + Number(n || 0).toLocaleString(undefined, {
            minimumFractionDigits: 2,
            maximumFractionDigits: 2
        });
    }

    function clampQty() {
        if (!qty) return 1;
        var q = parseInt(qty.value || '1', 10);
        if (isNaN(q) || q < 1) q = 1;
        if (maxAvailable > 0 && q > maxAvailable) q = maxAvailable;
        qty.value = String(q);
        return q;
    }

    function syncAll() {
        if (!sel || !sel.value) {
            maxAvailable = 0;
            unitPrice = 0;
            currency = '';
            if (qty) {
                qty.min = '1';
                qty.removeAttribute('max');
                if (!qty.value) qty.value = '1';
            }
            if (unitDisplay) unitDisplay.value = '—';
            if (lineDisplay) lineDisplay.value = '—';
            if (qtyHint) qtyHint.textContent = @json(__('app.admin.stock_sales.select_order_first'));
            if (formulaHint) formulaHint.textContent = '—';
            if (previewQty) previewQty.textContent = '—';
            if (previewUnit) previewUnit.textContent = '—';
            if (previewMax) previewMax.textContent = '—';
            if (previewTotal) previewTotal.textContent = '—';
            if (submitBtn) submitBtn.disabled = true;
            return;
        }

        var opt = sel.options[sel.selectedIndex];
        currency = opt.getAttribute('data-currency') || '';
        unitPrice = parseFloat(opt.getAttribute('data-unit-price') || '0') || 0;
        maxAvailable = parseInt(opt.getAttribute('data-available') || '0', 10) || 0;

        if (qty) {
            qty.min = '1';
            qty.max = String(Math.max(1, maxAvailable));
        }

        var q = clampQty();
        var lineTotal = Math.round(unitPrice * q * 100) / 100;

        if (unitDisplay) unitDisplay.value = fmtMoney(unitPrice);
        if (lineDisplay) lineDisplay.value = fmtMoney(lineTotal);
        if (qtyHint) {
            qtyHint.textContent = @json(__('app.admin.stock_sales.qty_max_hint')) + ' ' + maxAvailable;
        }
        if (formulaHint) {
            formulaHint.textContent = q + ' × ' + unitPrice.toFixed(2) + ' = ' + lineTotal.toFixed(2);
        }
        if (previewQty) previewQty.textContent = String(q);
        if (previewUnit) previewUnit.textContent = fmtMoney(unitPrice);
        if (previewMax) previewMax.textContent = String(maxAvailable);
        if (previewTotal) previewTotal.textContent = fmtMoney(lineTotal);
        if (submitBtn) submitBtn.disabled = !(maxAvailable > 0 && q >= 1 && q <= maxAvailable);
    }

    if (sel) {
        sel.addEventListener('change', syncAll);
        sel.addEventListener('input', syncAll);
    }
    if (qty) {
        qty.addEventListener('input', syncAll);
        qty.addEventListener('change', syncAll);
    }

    // Ensure default quantity=1 shows price/total immediately after order selection.
    syncAll();

    var saleClient = document.getElementById('sale-client');
    var stockPanel = document.getElementById('client-stock-balance-panel');
    var balanceUrlTemplate = @json(route('admin.clients.stock-balance', ['client' => 0])).replace(/\/0(\/stock-balance)/, '/__ID__$1');
    var typeLabels = @json(collect(\App\Models\Device::DEVICE_TYPES)->mapWithKeys(fn ($l, $k) => [$k => __('app.forms.device_type_' . $k)]));

    function renderSaleClientBalance(data, showSelect) {
        if (!stockPanel) return;
        if (showSelect) {
            stockPanel.innerHTML = '<p class="text-muted small mb-0">' + @json(__('app.admin.stock_sales.select_client_for_balance')) + '</p>';
            return;
        }
        if (!data || ((data.totals.sold || 0) === 0 && (data.totals.installed || 0) === 0)) {
            stockPanel.innerHTML = '<div class="alert alert-warning py-2 mb-0 small"><i class="fas fa-box-open me-1"></i>' + @json(__('app.admin.stock_sales.no_client_stock')) + '</div>';
            return;
        }
        var html = '<div class="p-3 rounded border bg-light"><p class="small fw-semibold mb-2">' + @json(__('app.admin.stock_sales.client_balance_title')) + '</p>';
        html += '<div class="row g-2 small mb-2">';
        html += '<div class="col-md-4"><span class="text-muted d-block">' + @json(__('app.admin.stock_sales.sold_to_client')) + '</span><span class="fw-semibold admin-ltr">' + (data.totals.sold || 0) + '</span></div>';
        html += '<div class="col-md-4"><span class="text-muted d-block">' + @json(__('app.admin.stock_sales.installed_count')) + '</span><span class="fw-semibold admin-ltr">' + (data.totals.installed || 0) + '</span></div>';
        html += '<div class="col-md-4"><span class="text-muted d-block">' + @json(__('app.admin.stock_sales.available_to_install')) + '</span><span class="fw-semibold admin-ltr text-success">' + (data.totals.available || 0) + '</span></div>';
        html += '</div>';
        if (data.by_type && data.by_type.length) {
            html += '<div class="table-responsive"><table class="table table-sm table-bordered mb-0 bg-white"><thead class="table-light"><tr>';
            html += '<th>' + @json(__('app.forms.device_type')) + '</th><th class="text-end">' + @json(__('app.admin.stock_sales.sold_to_client')) + '</th>';
            html += '<th class="text-end">' + @json(__('app.admin.stock_sales.installed_count')) + '</th><th class="text-end">' + @json(__('app.admin.stock_sales.available_to_install')) + '</th></tr></thead><tbody>';
            data.by_type.forEach(function (row) {
                html += '<tr><td>' + (typeLabels[row.device_type] || row.device_type) + '</td>';
                html += '<td class="text-end admin-ltr">' + row.sold + '</td><td class="text-end admin-ltr">' + row.installed + '</td>';
                html += '<td class="text-end admin-ltr fw-semibold ' + (row.available > 0 ? 'text-success' : 'text-muted') + '">' + row.available + '</td></tr>';
            });
            html += '</tbody></table></div>';
        }
        html += '</div>';
        stockPanel.innerHTML = html;
    }

    function loadSaleClientBalance(clientId) {
        if (!clientId) {
            renderSaleClientBalance(null, true);
            return;
        }
        var url = balanceUrlTemplate.replace('__ID__', String(clientId));
        fetch(url, { headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' } })
            .then(function (r) { return r.json(); })
            .then(function (data) { renderSaleClientBalance(data, false); })
            .catch(function () { renderSaleClientBalance(null, false); });
    }

    if (saleClient) {
        saleClient.addEventListener('change', function () { loadSaleClientBalance(saleClient.value); });
        if (saleClient.value) loadSaleClientBalance(saleClient.value);
    }
});
</script>
@endpush
