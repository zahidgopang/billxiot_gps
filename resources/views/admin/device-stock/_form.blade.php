@php
    $status = old('status', $order->status ?? 'in_stock');
    $condition = old('condition', $order->condition ?? 'new');
    $deviceType = old('device_type', $order->device_type ?? 'gps_tracker');
    $qty = (int) old('quantity', $order->quantity ?? 1);
    $unitCost = old('unit_cost', $order->unit_cost ?? '0.00');
    $unitSell = old('selling_price', $order->selling_price ?? '0.00');
@endphp

<x-admin.form-section
    :title="__('app.admin.stock.section_order')"
    icon="fas fa-boxes-stacked"
    :description="__('app.admin.stock.section_order_hint')"
>
    @if($order->exists)
        <x-admin.form-col>
            <label class="admin-label">{{ __('app.admin.stock.order_code') }}</label>
            <input type="text" class="form-control form-control-sm admin-ltr" dir="ltr" value="{{ $order->order_code }}" readonly disabled>
        </x-admin.form-col>
    @endif

    <x-admin.form-col>
        <label class="admin-label" for="stock-quantity">{{ __('app.admin.stock.quantity') }}</label>
        <input type="number" name="quantity" id="stock-quantity" class="form-control form-control-sm admin-ltr" dir="ltr"
               min="1" max="10000" required value="{{ $qty }}">
        <p class="admin-hint">{{ __('app.admin.stock.quantity_hint') }}</p>
        @error('quantity') <p class="admin-field__error text-danger">{{ $message }}</p> @enderror
    </x-admin.form-col>

    <x-admin.form-col>
        <label class="admin-label" for="stock-device-type">{{ __('app.forms.device_type') }}</label>
        <select name="device_type" id="stock-device-type" class="form-select form-select-sm" required>
            @foreach(\App\Models\Device::DEVICE_TYPES as $key => $label)
                <option value="{{ $key }}" @selected($deviceType === $key)>{{ __('app.forms.device_type_'.$key) }}</option>
            @endforeach
        </select>
        @error('device_type') <p class="admin-field__error text-danger">{{ $message }}</p> @enderror
    </x-admin.form-col>

    <x-admin.form-col>
        <label class="admin-label" for="stock-brand">{{ __('app.admin.stock.brand') }}</label>
        <input type="text" name="brand" id="stock-brand" class="form-control form-control-sm"
               value="{{ old('brand', $order->brand ?? '') }}">
        @error('brand') <p class="admin-field__error text-danger">{{ $message }}</p> @enderror
    </x-admin.form-col>

    <x-admin.form-col>
        <label class="admin-label" for="stock-model">{{ __('app.admin.stock.model') }}</label>
        <input type="text" name="model" id="stock-model" class="form-control form-control-sm"
               value="{{ old('model', $order->model ?? '') }}">
        @error('model') <p class="admin-field__error text-danger">{{ $message }}</p> @enderror
    </x-admin.form-col>

    <x-admin.form-col>
        <label class="admin-label" for="stock-condition">{{ __('app.admin.stock.condition') }}</label>
        <select name="condition" id="stock-condition" class="form-select form-select-sm" data-search="false">
            @foreach(\App\Models\DeviceStockOrder::CONDITIONS as $key => $label)
                <option value="{{ $key }}" @selected($condition === $key)>{{ __('app.admin.stock.condition_'.$key) }}</option>
            @endforeach
        </select>
        @error('condition') <p class="admin-field__error text-danger">{{ $message }}</p> @enderror
    </x-admin.form-col>

    <x-admin.form-col>
        <label class="admin-label" for="stock-status">{{ __('app.common.status') }}</label>
        <select name="status" id="stock-status" class="form-select form-select-sm" data-search="false">
            @foreach(\App\Models\DeviceStockOrder::STATUSES as $key => $label)
                <option value="{{ $key }}" @selected($status === $key)>{{ __('app.admin.stock.status_'.$key) }}</option>
            @endforeach
        </select>
        <p class="admin-hint mb-0" id="stock-status-hint"></p>
        @error('status') <p class="admin-field__error text-danger">{{ $message }}</p> @enderror
    </x-admin.form-col>
</x-admin.form-section>

<x-admin.form-section
    :title="__('app.admin.stock.section_pricing')"
    icon="fas fa-tags"
    :description="__('app.admin.stock.section_pricing_per_unit')"
>
    <x-admin.form-col>
        <label class="admin-label" for="stock-currency">{{ __('app.admin.stock.currency') }}</label>
        <select name="currency" id="stock-currency" class="form-select form-select-sm" data-search="false">
            @foreach(['USD', 'EUR', 'GBP', 'SAR', 'AED', 'PKR'] as $cur)
                <option value="{{ $cur }}" @selected(old('currency', $order->currency ?? 'USD') === $cur)>{{ $cur }}</option>
            @endforeach
        </select>
        @error('currency') <p class="admin-field__error text-danger">{{ $message }}</p> @enderror
    </x-admin.form-col>

    <x-admin.form-col>
        <label class="admin-label" for="stock-unit-cost">{{ __('app.admin.stock.unit_cost') }}</label>
        <input type="number" name="unit_cost" id="stock-unit-cost" class="form-control form-control-sm admin-ltr" dir="ltr"
               step="0.01" min="0" required value="{{ $unitCost }}">
        <p class="admin-hint">{{ __('app.admin.stock.unit_cost_hint') }}</p>
        @error('unit_cost') <p class="admin-field__error text-danger">{{ $message }}</p> @enderror
    </x-admin.form-col>

    <x-admin.form-col>
        <label class="admin-label" for="stock-selling-price">{{ __('app.admin.stock.selling_price') }}</label>
        <input type="number" name="selling_price" id="stock-selling-price" class="form-control form-control-sm admin-ltr" dir="ltr"
               step="0.01" min="0" required value="{{ $unitSell }}">
        <p class="admin-hint">{{ __('app.admin.stock.selling_price_hint') }}</p>
        @error('selling_price') <p class="admin-field__error text-danger">{{ $message }}</p> @enderror
    </x-admin.form-col>

    <x-admin.form-col :full="true">
        <div class="p-3 rounded border bg-light" id="stock-totals-preview">
            <div class="row g-2 small">
                <div class="col-md-4">
                    <span class="text-muted">{{ __('app.admin.stock.total_cost') }}</span>
                    <div class="fw-semibold admin-ltr" id="preview-total-cost" dir="ltr">—</div>
                </div>
                <div class="col-md-4">
                    <span class="text-muted">{{ __('app.admin.stock.total_selling') }}</span>
                    <div class="fw-semibold admin-ltr" id="preview-total-sell" dir="ltr">—</div>
                </div>
                <div class="col-md-4">
                    <span class="text-muted">{{ __('app.admin.stock.total_margin') }}</span>
                    <div class="fw-semibold admin-ltr" id="preview-total-margin" dir="ltr">—</div>
                </div>
            </div>
            <p class="admin-hint mb-0 mt-2">{{ __('app.admin.stock.totals_formula_hint') }}</p>
        </div>
    </x-admin.form-col>
</x-admin.form-section>

<x-admin.form-section
    :title="__('app.admin.stock.section_procurement')"
    icon="fas fa-truck"
>
    <x-admin.form-col>
        <label class="admin-label" for="stock-supplier">{{ __('app.admin.stock.supplier') }}</label>
        <input type="text" name="supplier" id="stock-supplier" class="form-control form-control-sm"
               value="{{ old('supplier', $order->supplier ?? '') }}">
        @error('supplier') <p class="admin-field__error text-danger">{{ $message }}</p> @enderror
    </x-admin.form-col>

    <x-admin.form-col>
        <label class="admin-label" for="stock-po">{{ __('app.admin.stock.purchase_order_ref') }}</label>
        <input type="text" name="purchase_order_ref" id="stock-po" class="form-control form-control-sm admin-ltr" dir="ltr"
               value="{{ old('purchase_order_ref', $order->purchase_order_ref ?? '') }}">
        @error('purchase_order_ref') <p class="admin-field__error text-danger">{{ $message }}</p> @enderror
    </x-admin.form-col>

    <x-admin.form-col>
        <x-admin.date-input
            name="purchased_at"
            id="stock-purchased-at"
            :label="__('app.admin.stock.purchased_at')"
            :value="old('purchased_at', optional($order->purchased_at)->format('Y-m-d'))"
        />
        @error('purchased_at') <p class="admin-field__error text-danger">{{ $message }}</p> @enderror
    </x-admin.form-col>

    <x-admin.form-col>
        <label class="admin-label" for="stock-warehouse">{{ __('app.admin.stock.warehouse_location') }}</label>
        <input type="text" name="warehouse_location" id="stock-warehouse" class="form-control form-control-sm"
               value="{{ old('warehouse_location', $order->warehouse_location ?? '') }}">
        @error('warehouse_location') <p class="admin-field__error text-danger">{{ $message }}</p> @enderror
    </x-admin.form-col>

    <x-admin.form-col :full="true">
        <label class="admin-label" for="stock-notes">{{ __('app.admin.stock.notes') }}</label>
        <textarea name="notes" id="stock-notes" class="form-control form-control-sm" rows="2">{{ old('notes', $order->notes ?? '') }}</textarea>
        @error('notes') <p class="admin-field__error text-danger">{{ $message }}</p> @enderror
    </x-admin.form-col>
</x-admin.form-section>

@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', function () {
    var condition = document.getElementById('stock-condition');
    var status = document.getElementById('stock-status');
    var statusHint = document.getElementById('stock-status-hint');
    var qty = document.getElementById('stock-quantity');
    var cost = document.getElementById('stock-unit-cost');
    var sell = document.getElementById('stock-selling-price');
    var currency = document.getElementById('stock-currency');
    var elCost = document.getElementById('preview-total-cost');
    var elSell = document.getElementById('preview-total-sell');
    var elMargin = document.getElementById('preview-total-margin');
    if (!qty || !cost || !sell) return;

    function fmt(n) {
        return (currency ? currency.value + ' ' : '') + Number(n).toLocaleString(undefined, { minimumFractionDigits: 2, maximumFractionDigits: 2 });
    }

    function sync() {
        if (condition && status) {
            var isFaulty = condition.value === 'faulty';
            if (isFaulty) {
                status.value = 'repair';
                status.disabled = true;
                if (statusHint) statusHint.textContent = @json(__('app.admin.stock.faulty_forces_repair'));
            } else {
                status.disabled = false;
                if (statusHint) statusHint.textContent = '';
                if (status.value === 'repair') status.value = 'in_stock';
            }
        }

        var q = Math.max(1, parseInt(qty.value, 10) || 1);
        var c = parseFloat(cost.value) || 0;
        var s = parseFloat(sell.value) || 0;
        var totalC = c * q;
        var totalS = s * q;
        elCost.textContent = fmt(totalC);
        elSell.textContent = fmt(totalS);
        elMargin.textContent = fmt(totalS - totalC);
    }

    ['input', 'change'].forEach(function (ev) {
        if (condition) condition.addEventListener(ev, sync);
        if (status) status.addEventListener(ev, sync);
        qty.addEventListener(ev, sync);
        cost.addEventListener(ev, sync);
        sell.addEventListener(ev, sync);
        if (currency) currency.addEventListener(ev, sync);
    });
    sync();
});
</script>
@endpush
