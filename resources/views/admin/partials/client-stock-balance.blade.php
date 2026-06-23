@php
    $balance = $balance ?? null;
    $showSelectClientHint = $showSelectClientHint ?? false;
    $compact = $compact ?? false;
@endphp

<div id="client-stock-balance-panel" class="{{ $compact ? 'mb-0' : 'mb-3' }}">
    @if($showSelectClientHint)
        <p class="text-muted small mb-0">{{ __('app.admin.stock_sales.select_client_for_balance') }}</p>
    @elseif(empty($balance) || (($balance['totals']['sold'] ?? 0) === 0 && ($balance['totals']['installed'] ?? 0) === 0))
        <div class="alert alert-warning py-2 mb-0 small">
            <i class="fas fa-box-open me-1" aria-hidden="true"></i>{{ __('app.admin.stock_sales.no_client_stock') }}
        </div>
    @else
        <div class="p-3 rounded border bg-light">
            @unless($compact)
                <p class="small fw-semibold mb-2">{{ __('app.admin.stock_sales.client_balance_title') }}</p>
            @endunless
            <div class="row g-2 small mb-2">
                <div class="col-md-4">
                    <span class="text-muted d-block">{{ __('app.admin.stock_sales.sold_to_client') }}</span>
                    <span class="fw-semibold admin-ltr" data-stock-total="sold" dir="ltr">{{ number_format($balance['totals']['sold'] ?? 0) }}</span>
                </div>
                <div class="col-md-4">
                    <span class="text-muted d-block">{{ __('app.admin.stock_sales.installed_count') }}</span>
                    <span class="fw-semibold admin-ltr" data-stock-total="installed" dir="ltr">{{ number_format($balance['totals']['installed'] ?? 0) }}</span>
                </div>
                <div class="col-md-4">
                    <span class="text-muted d-block">{{ __('app.admin.stock_sales.available_to_install') }}</span>
                    <span class="fw-semibold admin-ltr text-success" data-stock-total="available" dir="ltr">{{ number_format($balance['totals']['available'] ?? 0) }}</span>
                </div>
            </div>
            @if(!empty($balance['by_type']))
                <div class="table-responsive">
                    <table class="table table-sm table-bordered mb-0 bg-white">
                        <thead class="table-light">
                        <tr>
                            <th>{{ __('app.forms.device_type') }}</th>
                            <th class="text-end">{{ __('app.admin.stock_sales.sold_to_client') }}</th>
                            <th class="text-end">{{ __('app.admin.stock_sales.installed_count') }}</th>
                            <th class="text-end">{{ __('app.admin.stock_sales.available_to_install') }}</th>
                        </tr>
                        </thead>
                        <tbody data-stock-by-type>
                        @foreach($balance['by_type'] as $row)
                            <tr>
                                <td>{{ __('app.forms.device_type_' . $row['device_type']) }}</td>
                                <td class="text-end admin-ltr" dir="ltr">{{ number_format($row['sold']) }}</td>
                                <td class="text-end admin-ltr" dir="ltr">{{ number_format($row['installed']) }}</td>
                                <td class="text-end admin-ltr fw-semibold {{ $row['available'] > 0 ? 'text-success' : 'text-muted' }}" dir="ltr">{{ number_format($row['available']) }}</td>
                            </tr>
                        @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
        </div>
    @endif
</div>
