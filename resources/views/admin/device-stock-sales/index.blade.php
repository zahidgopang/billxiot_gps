@extends('admin.layouts.app')

@section('title', __('app.admin.stock_sales.title'))
@section('page-title', __('app.admin.stock_sales.title'))

@section('content')
    <div class="card border-0 shadow-sm">
        <div class="card-body">
            <div class="d-flex justify-content-between align-items-start flex-wrap gap-2 mb-3">
                <div>
                    <h5 class="mb-1">{{ __('app.admin.stock_sales.title') }}</h5>
                    <p class="text-muted small mb-0">{{ __('app.admin.stock_sales.subtitle') }}</p>
                </div>
                <a href="{{ route('admin.device-stock-sales.create') }}" class="btn btn-sm btn-primary">
                    <i class="fas fa-file-invoice-dollar me-1" aria-hidden="true"></i>{{ __('app.admin.stock_sales.create') }}
                </a>
            </div>

            <form method="GET" class="admin-filter-bar row g-2 mb-3 align-items-end">
                <div class="col-md-4">
                    <label class="form-label small mb-1" for="sales-filter-client">{{ __('app.admin.stock_sales.client') }}</label>
                    <select name="client_id" id="sales-filter-client" class="form-select form-select-sm"
                            data-placeholder="{{ __('app.admin.stock_sales.all_clients') }}">
                        <option value="">{{ __('app.admin.stock_sales.all_clients') }}</option>
                        @foreach($clients as $client)
                            <option value="{{ $client->id }}" @selected((string) request('client_id') === (string) $client->id)>
                                {{ $client->name }}
                            </option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-4">
                    <label class="form-label small mb-1" for="sales-filter-q">{{ __('app.common.search') }}</label>
                    <input type="text" name="q" id="sales-filter-q" value="{{ request('q') }}" class="form-control form-control-sm admin-ltr" dir="ltr"
                           placeholder="{{ __('app.admin.stock_sales.search_placeholder') }}">
                </div>
                <div class="col-md-4 d-flex gap-1 admin-filter-actions">
                    <button type="submit" class="btn btn-sm btn-primary flex-grow-1">{{ __('app.common.filter') }}</button>
                    @if(request()->filled('q') || request()->filled('client_id'))
                        <a href="{{ route('admin.device-stock-sales.index') }}" class="btn btn-sm btn-outline-secondary" title="{{ __('app.common.clear') }}">
                            <i class="fas fa-times" aria-hidden="true"></i>
                        </a>
                    @endif
                </div>
            </form>

            @if($filterClientId && $filterClient)
                <div class="mb-4">
                    <h6 class="mb-2">
                        {{ __('app.admin.stock_sales.client_summary_for', ['name' => $filterClient->name]) }}
                    </h6>
                    @include('admin.partials.client-stock-balance', [
                        'balance' => $clientStockBalance,
                        'showSelectClientHint' => false,
                    ])
                </div>
            @endif

            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead>
                    <tr>
                        <th>{{ __('app.admin.stock_sales.invoice_no') }}</th>
                        @unless($filterClientId)
                            <th>{{ __('app.admin.stock_sales.client') }}</th>
                        @endunless
                        <th>{{ __('app.admin.stock_sales.issued_at') }}</th>
                        <th class="text-end">{{ __('app.admin.stock_sales.total') }}</th>
                        <th></th>
                    </tr>
                    </thead>
                    <tbody>
                    @forelse($sales as $sale)
                        <tr>
                            <td class="admin-ltr" dir="ltr"><code>{{ $sale->invoice_no }}</code></td>
                            @unless($filterClientId)
                                <td>{{ $sale->client?->name }}</td>
                            @endunless
                            <td>{{ $sale->issued_at?->format('Y-m-d') }}</td>
                            <td class="text-end admin-ltr" dir="ltr">{{ $sale->currency }} {{ number_format($sale->totalAmount(), 2) }}</td>
                            <td class="text-end">
                                <a href="{{ route('admin.device-stock-sales.show', $sale) }}" class="btn btn-sm btn-outline-secondary">
                                    {{ __('app.common.view') }}
                                </a>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="{{ $filterClientId ? 4 : 5 }}" class="text-muted text-center py-4">{{ __('app.admin.stock_sales.empty') }}</td>
                        </tr>
                    @endforelse
                    </tbody>
                </table>
            </div>

            @if($sales->hasPages())
                <div class="mt-3">{{ $sales->links() }}</div>
            @endif
        </div>
    </div>
@endsection
