@extends('admin.layouts.app')

@section('title', $order->order_code)
@section('page-title', __('app.admin.stock.order_detail'))

@section('content')
    <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
        <div>
            <h5 class="mb-1 admin-ltr" dir="ltr">
                <code>{{ $order->order_code }}</code>
            </h5>
            <p class="text-muted small mb-0">{{ $order->displayLabel() }}</p>
        </div>
        <div class="d-flex gap-1 flex-wrap">
            <a href="{{ route('admin.device-stock.index') }}" class="btn btn-sm btn-light">
                <i class="fas fa-arrow-left me-1" aria-hidden="true"></i>{{ __('app.admin.stock.back_to_list') }}
            </a>
            @can('permission', 'stock.manage')
                <a href="{{ route('admin.device-stock.edit', $order) }}" class="btn btn-sm btn-primary">{{ __('app.common.edit') }}</a>
                <form action="{{ route('admin.device-stock.destroy', $order) }}" method="POST" class="d-inline"
                      onsubmit="return confirm(@json(__('app.admin.stock.delete_confirm')));">
                    @csrf
                    @method('DELETE')
                    <button type="submit" class="btn btn-sm btn-outline-danger">{{ __('app.common.delete') }}</button>
                </form>
            @endcan
        </div>
    </div>

    <div class="row g-3">
        <div class="col-lg-8">
            <div class="card border-0 shadow-sm">
                <div class="card-header bg-transparent">
                    <strong>{{ __('app.admin.stock.section_order') }}</strong>
                </div>
                <div class="card-body">
                    <dl class="row mb-0 small">
                        <dt class="col-sm-4 text-muted">{{ __('app.admin.stock.quantity') }}</dt>
                        <dd class="col-sm-8 fw-semibold">
                            {{ number_format($order->quantity) }}
                            <span class="text-muted fw-normal">
                                · {{ __('app.admin.stock.available') }} {{ number_format($order->availableQuantity()) }}
                            </span>
                        </dd>

                        <dt class="col-sm-4 text-muted">{{ __('app.forms.device_type') }}</dt>
                        <dd class="col-sm-8">{{ __('app.forms.device_type_'.$order->device_type) }}</dd>

                        <dt class="col-sm-4 text-muted">{{ __('app.admin.stock.brand') }}</dt>
                        <dd class="col-sm-8">{{ $order->brand ?: '—' }}</dd>

                        <dt class="col-sm-4 text-muted">{{ __('app.admin.stock.model') }}</dt>
                        <dd class="col-sm-8">{{ $order->model ?: '—' }}</dd>

                        <dt class="col-sm-4 text-muted">{{ __('app.admin.stock.condition') }}</dt>
                        <dd class="col-sm-8">{{ __('app.admin.stock.condition_'.$order->condition) }}</dd>

                        <dt class="col-sm-4 text-muted">{{ __('app.common.status') }}</dt>
                        <dd class="col-sm-8">{{ __('app.admin.stock.status_'.$order->status) }}</dd>

                        <dt class="col-sm-4 text-muted">{{ __('app.admin.stock.supplier') }}</dt>
                        <dd class="col-sm-8">{{ $order->supplier ?: '—' }}</dd>

                        <dt class="col-sm-4 text-muted">{{ __('app.admin.stock.purchase_order_ref') }}</dt>
                        <dd class="col-sm-8 admin-ltr" dir="ltr">{{ $order->purchase_order_ref ?: '—' }}</dd>

                        <dt class="col-sm-4 text-muted">{{ __('app.admin.stock.purchased_at') }}</dt>
                        <dd class="col-sm-8">{{ $order->purchased_at?->format('Y-m-d') ?: '—' }}</dd>

                        <dt class="col-sm-4 text-muted">{{ __('app.admin.stock.warehouse_location') }}</dt>
                        <dd class="col-sm-8">{{ $order->warehouse_location ?: '—' }}</dd>

                        @if($order->notes)
                            <dt class="col-sm-4 text-muted">{{ __('app.admin.stock.notes') }}</dt>
                            <dd class="col-sm-8">{{ $order->notes }}</dd>
                        @endif
                    </dl>
                </div>
            </div>
        </div>

        <div class="col-lg-4">
            <div class="card border-0 shadow-sm mb-3">
                <div class="card-header bg-transparent">
                    <strong>{{ __('app.admin.stock.section_pricing') }}</strong>
                </div>
                <div class="card-body">
                    <table class="table table-sm mb-0">
                        <tbody>
                        <tr>
                            <td class="text-muted">{{ __('app.admin.stock.unit_cost') }}</td>
                            <td class="text-end admin-ltr" dir="ltr">{{ $order->currency }} {{ number_format($order->unit_cost, 2) }}</td>
                        </tr>
                        <tr>
                            <td class="text-muted">{{ __('app.admin.stock.selling_price') }}</td>
                            <td class="text-end admin-ltr" dir="ltr">{{ $order->currency }} {{ number_format($order->selling_price, 2) }}</td>
                        </tr>
                        <tr>
                            <td class="text-muted">{{ __('app.admin.stock.unit_margin') }}</td>
                            <td class="text-end admin-ltr" dir="ltr">
                                {{ $order->currency }} {{ number_format($order->unitMarginAmount(), 2) }}
                                @if($order->unitMarginPercent() !== null)
                                    <span class="text-muted">({{ $order->unitMarginPercent() }}%)</span>
                                @endif
                            </td>
                        </tr>
                        </tbody>
                        <tfoot class="table-group-divider">
                        <tr class="fw-semibold">
                            <td>{{ __('app.admin.stock.total_cost') }}</td>
                            <td class="text-end admin-ltr" dir="ltr">{{ $order->currency }} {{ number_format($order->totalCost(), 2) }}</td>
                        </tr>
                        <tr class="fw-semibold">
                            <td>{{ __('app.admin.stock.total_selling') }}</td>
                            <td class="text-end admin-ltr" dir="ltr">{{ $order->currency }} {{ number_format($order->totalSellingPrice(), 2) }}</td>
                        </tr>
                        <tr class="fw-semibold text-success">
                            <td>{{ __('app.admin.stock.total_margin') }}</td>
                            <td class="text-end admin-ltr" dir="ltr">{{ $order->currency }} {{ number_format($order->totalMarginAmount(), 2) }}</td>
                        </tr>
                        </tfoot>
                    </table>
                    <p class="small text-muted mb-0 mt-2">
                        {{ __('app.admin.stock.totals_formula_detail', [
                            'qty' => number_format($order->quantity),
                            'unit_cost' => number_format($order->unit_cost, 2),
                            'unit_sell' => number_format($order->selling_price, 2),
                        ]) }}
                    </p>
                </div>
            </div>

            @if($order->creator)
                <div class="card border-0 shadow-sm">
                    <div class="card-body small text-muted">
                        {{ __('app.admin.stock.created_by') }}: {{ $order->creator->name }}
                        <br>
                        {{ $order->created_at?->format('Y-m-d H:i') }}
                    </div>
                </div>
            @endif
        </div>
    </div>
@endsection
