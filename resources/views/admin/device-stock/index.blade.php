@extends('admin.layouts.app')

@php
    $repairList = $repairList ?? false;
    $listRoute = $repairList ? 'admin.device-stock.repairs' : 'admin.device-stock.index';
@endphp

@section('title', $repairList ? __('app.admin.stock.repair_list_title') : __('app.admin.stock.title'))
@section('page-title', $repairList ? __('app.admin.stock.repair_list_title') : __('app.admin.stock.title'))

@section('content')
    @if(!$repairList)
        <div class="card border-0 shadow-sm mb-3">
            <div class="card-body">
                <div class="d-flex justify-content-between align-items-center flex-wrap gap-2 mb-2">
                    <div>
                        <h6 class="mb-0">{{ __('app.admin.stock.inventory_summary_title') }}</h6>
                        <p class="text-muted small mb-0">{{ __('app.admin.stock.inventory_summary_hint') }}</p>
                    </div>
                </div>

                <div class="table-responsive">
                    <table class="table table-sm table-bordered align-middle mb-0">
                        <thead class="table-light">
                        <tr>
                            <th>{{ __('app.forms.device_type') }}</th>
                            <th class="text-end">{{ __('app.admin.stock.total_purchased') }}</th>
                            <th class="text-end">{{ __('app.admin.stock.total_sold') }}</th>
                            <th class="text-end">{{ __('app.admin.stock.total_installed') }}</th>
                            <th class="text-end">{{ __('app.admin.stock.available') }}</th>
                        </tr>
                        </thead>
                        <tbody>
                        @forelse(($warehouseByType ?? collect()) as $row)
                            <tr>
                                <td>{{ __('app.forms.device_type_' . $row->device_type) }}</td>
                                <td class="text-end admin-ltr" dir="ltr">{{ number_format((int) $row->total_purchased) }}</td>
                                <td class="text-end admin-ltr" dir="ltr">{{ number_format((int) $row->total_sold) }}</td>
                                <td class="text-end admin-ltr" dir="ltr">{{ number_format((int) $row->total_installed) }}</td>
                                <td class="text-end admin-ltr fw-semibold {{ ((int) $row->available_qty) > 0 ? 'text-success' : 'text-muted' }}" dir="ltr">{{ number_format((int) $row->available_qty) }}</td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="5" class="text-muted text-center py-3">{{ __('app.admin.stock.inventory_summary_empty') }}</td>
                            </tr>
                        @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    @endif

    <div class="row g-3 mb-3">
        <div class="col-6 col-md-3">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-body py-3">
                    <div class="text-muted small">{{ __('app.admin.stock.stats_orders') }}</div>
                    <div class="fs-4 fw-semibold">{{ number_format($stats['total_orders']) }}</div>
                </div>
            </div>
        </div>
        <div class="col-6 col-md-3">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-body py-3">
                    <div class="text-muted small">{{ __('app.admin.stock.stats_total_units') }}</div>
                    <div class="fs-4 fw-semibold">{{ number_format($stats['total_units']) }}</div>
                </div>
            </div>
        </div>
        <div class="col-6 col-md-3">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-body py-3">
                    <div class="text-muted small">{{ __('app.admin.stock.stats_inventory_cost') }}</div>
                    <div class="fs-5 fw-semibold">{{ number_format($stats['inventory_cost'], 2) }}</div>
                </div>
            </div>
        </div>
        <div class="col-6 col-md-3">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-body py-3">
                    <div class="text-muted small">{{ __('app.admin.stock.stats_inventory_retail') }}</div>
                    <div class="fs-5 fw-semibold">{{ number_format($stats['inventory_retail'], 2) }}</div>
                </div>
            </div>
        </div>
    </div>

    <div class="card border-0 shadow-sm">
        <div class="card-body">
            <div class="d-flex justify-content-between align-items-start flex-wrap gap-2 mb-3">
                <div>
                    <h5 class="mb-1">
                        {{ $repairList ? __('app.admin.stock.repair_list_title') : __('app.admin.stock.title') }}
                    </h5>
                    <p class="text-muted small mb-0">
                        {{ $repairList ? __('app.admin.stock.repair_list_subtitle') : __('app.admin.stock.index_subtitle') }}
                    </p>
                </div>
                @can('permission', 'stock.manage')
                    <a href="{{ route('admin.device-stock.create') }}" class="btn btn-sm btn-primary">
                        <i class="fas fa-plus me-1" aria-hidden="true"></i>{{ __('app.admin.stock.add_order') }}
                    </a>
                @endcan
            </div>

            <form method="GET" action="{{ route($listRoute) }}" class="admin-filter-bar row g-2 mb-3 align-items-end">
                <div class="col-lg-4 col-md-6">
                    <label class="form-label small mb-1" for="stock-filter-q">{{ __('app.common.search') }}</label>
                    <input type="text" name="q" id="stock-filter-q" value="{{ request('q') }}"
                           class="form-control form-control-sm"
                           placeholder="{{ __('app.admin.stock.search_placeholder') }}">
                </div>
                <div class="col-lg-2 col-md-3">
                    <label class="form-label small mb-1" for="stock-filter-status">{{ __('app.common.status') }}</label>
                    <select name="status" id="stock-filter-status" class="form-select form-select-sm" data-search="false"
                            data-placeholder="{{ __('app.admin.stock.all_statuses') }}">
                        <option value="">{{ __('app.admin.stock.all_statuses') }}</option>
                        @foreach(\App\Models\DeviceStockOrder::STATUSES as $key => $label)
                            <option value="{{ $key }}" @selected(request('status') === $key)>{{ __('app.admin.stock.status_'.$key) }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-lg-2 col-md-3">
                    <label class="form-label small mb-1" for="stock-filter-type">{{ __('app.forms.device_type') }}</label>
                    <select name="device_type" id="stock-filter-type" class="form-select form-select-sm" data-search="false"
                            data-placeholder="{{ __('app.admin.stock.all_device_types') }}">
                        <option value="">{{ __('app.admin.stock.all_device_types') }}</option>
                        @foreach(\App\Models\Device::DEVICE_TYPES as $key => $label)
                            <option value="{{ $key }}" @selected(request('device_type') === $key)>{{ __('app.forms.device_type_'.$key) }}</option>
                        @endforeach
                    </select>
                </div>
                @if(!$repairList)
                    <div class="col-lg-2 col-md-3">
                        <label class="form-label small mb-1" for="stock-filter-condition">{{ __('app.admin.stock.condition') }}</label>
                        <select name="condition" id="stock-filter-condition" class="form-select form-select-sm" data-search="false"
                                data-placeholder="{{ __('app.admin.stock.all_conditions') }}">
                            <option value="">{{ __('app.admin.stock.all_conditions') }}</option>
                            @foreach(\App\Models\DeviceStockOrder::CONDITIONS as $key => $label)
                                <option value="{{ $key }}" @selected(request('condition') === $key)>{{ __('app.admin.stock.condition_'.$key) }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-lg-2 col-md-12 d-flex gap-1 admin-filter-actions">
                        <button type="submit" class="btn btn-sm btn-primary flex-grow-1">{{ __('app.common.filter') }}</button>
                        @if(request()->hasAny(['q', 'status', 'device_type', 'condition']))
                            <a href="{{ route('admin.device-stock.index') }}" class="btn btn-sm btn-outline-secondary" title="{{ __('app.common.clear') }}">
                                <i class="fas fa-times" aria-hidden="true"></i>
                            </a>
                        @endif
                    </div>
                @else
                    <div class="col-lg-4 col-md-12 d-flex gap-1 align-items-end admin-filter-actions">
                        <button type="submit" class="btn btn-sm btn-primary flex-grow-1">{{ __('app.common.filter') }}</button>
                        @if(request()->hasAny(['q', 'status', 'device_type']))
                            <a href="{{ route('admin.device-stock.repairs') }}" class="btn btn-sm btn-outline-secondary" title="{{ __('app.common.clear') }}">
                                <i class="fas fa-times" aria-hidden="true"></i>
                            </a>
                        @endif
                    </div>
                @endif
            </form>

            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead>
                    <tr>
                        <th>{{ __('app.admin.stock.order_code') }}</th>
                        <th class="text-center">{{ __('app.admin.stock.quantity') }}</th>
                        <th>{{ __('app.admin.stock.product') }}</th>
                        @if($repairList)
                            <th>{{ __('app.admin.stock.condition') }}</th>
                        @endif
                        <th>{{ __('app.common.status') }}</th>
                        <th class="text-end">{{ __('app.admin.stock.unit_cost') }}</th>
                        <th class="text-end">{{ __('app.admin.stock.total_cost') }}</th>
                        <th></th>
                    </tr>
                    </thead>
                    <tbody>
                    @forelse($orders as $order)
                        <tr>
                            <td>
                                <a href="{{ route('admin.device-stock.show', $order) }}" class="text-decoration-none admin-ltr">
                                    <code>{{ $order->order_code }}</code>
                                </a>
                            </td>
                            <td class="text-center fw-semibold">
                                {{ number_format($order->quantity) }}
                                <div class="small text-muted">{{ __('app.admin.stock.available') }} {{ number_format($order->availableQuantity()) }}</div>
                            </td>
                            <td>
                                <div class="fw-medium">{{ $order->displayLabel() }}</div>
                                <div class="small text-muted">{{ __('app.forms.device_type_'.$order->device_type) }}</div>
                            </td>
                            @if($repairList)
                                <td>
                                    <span class="badge bg-warning text-dark">{{ __('app.admin.stock.condition_faulty') }}</span>
                                </td>
                            @endif
                            <td>
                                @php
                                    $badge = match($order->status) {
                                        'in_stock' => 'success',
                                        'reserved' => 'warning text-dark',
                                        'sold' => 'secondary',
                                        'partial' => 'info text-dark',
                                        'closed' => 'dark',
                                        'repair' => 'danger',
                                        default => 'secondary',
                                    };
                                @endphp
                                <span class="badge bg-{{ $badge }}">{{ __('app.admin.stock.status_'.$order->status) }}</span>
                            </td>
                            <td class="text-end admin-ltr text-nowrap" dir="ltr">
                                {{ $order->currency }} {{ number_format($order->unit_cost, 2) }}
                            </td>
                            <td class="text-end admin-ltr text-nowrap" dir="ltr">
                                {{ $order->currency }} {{ number_format($order->totalCost(), 2) }}
                            </td>
                            <td class="text-end text-nowrap">
                                <a href="{{ route('admin.device-stock.show', $order) }}" class="btn btn-sm btn-outline-secondary">{{ __('app.common.view') }}</a>
                                @can('permission', 'stock.manage')
                                    <a href="{{ route('admin.device-stock.edit', $order) }}" class="btn btn-sm btn-outline-primary">{{ __('app.common.edit') }}</a>
                                @endcan
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="{{ $repairList ? 8 : 7 }}" class="text-muted text-center py-4">
                                {{ $repairList ? __('app.admin.stock.repair_list_empty') : __('app.admin.stock.empty') }}
                            </td>
                        </tr>
                    @endforelse
                    </tbody>
                </table>
            </div>

            @if($orders->hasPages())
                <div class="mt-3">{{ $orders->links() }}</div>
            @endif
        </div>
    </div>
@endsection
