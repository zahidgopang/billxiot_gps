@extends('admin.layouts.app')

@section('title', __('app.admin.purchase_invoices.title'))
@section('page-title', __('app.admin.purchase_invoices.title'))

@section('content')
    <div class="card border-0 shadow-sm">
        <div class="card-body">
            <div class="d-flex justify-content-between align-items-start flex-wrap gap-2 mb-3">
                <div>
                    <h5 class="mb-1">{{ __('app.admin.purchase_invoices.title') }}</h5>
                    <p class="text-muted small mb-0">{{ __('app.admin.purchase_invoices.subtitle') }}</p>
                </div>
                <a href="{{ route('client.dashboard') }}" class="btn btn-sm btn-light">
                    <i class="fas fa-arrow-left me-1" aria-hidden="true"></i>{{ __('app.common.back') }}
                </a>
            </div>

            <form method="GET" class="admin-filter-bar d-flex flex-wrap gap-2 align-items-end mb-3">
                <div class="flex-grow-1" style="min-width: 12rem; max-width: 24rem;">
                    <label class="form-label small mb-1" for="purchases-filter-q">{{ __('app.common.search') }}</label>
                    <input name="q" id="purchases-filter-q" value="{{ request('q') }}"
                           class="form-control form-control-sm admin-ltr" dir="ltr"
                           placeholder="{{ __('app.admin.stock_sales.search_placeholder') }}">
                </div>
                <div class="admin-filter-actions">
                    <button class="btn btn-sm btn-primary" type="submit">{{ __('app.common.search') }}</button>
                    @if(request()->filled('q'))
                        <a href="{{ route('client.purchases.index') }}" class="btn btn-sm btn-outline-secondary" title="{{ __('app.common.clear') }}">
                            <i class="fas fa-times" aria-hidden="true"></i>
                        </a>
                    @endif
                </div>
            </form>

            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead>
                    <tr>
                        <th>{{ __('app.admin.stock_sales.invoice_no') }}</th>
                        <th>{{ __('app.admin.stock_sales.client') }}</th>
                        <th>{{ __('app.admin.stock_sales.issued_at') }}</th>
                        <th class="text-end">{{ __('app.admin.stock_sales.total') }}</th>
                        <th class="text-end">{{ __('app.common.actions') }}</th>
                    </tr>
                    </thead>
                    <tbody>
                    @forelse($sales as $sale)
                        <tr>
                            <td class="admin-ltr" dir="ltr"><code>{{ $sale->invoice_no }}</code></td>
                            <td>{{ $sale->client?->name }}</td>
                            <td>{{ $sale->issued_at?->format('Y-m-d') }}</td>
                            <td class="text-end admin-ltr" dir="ltr">{{ $sale->currency }} {{ number_format($sale->totalAmount(), 2) }}</td>
                            <td class="text-end">
                                <a href="{{ route('client.purchases.show', $sale) }}" class="btn btn-sm btn-outline-secondary">
                                    {{ __('app.common.view') }}
                                </a>
                                <a href="{{ route('client.purchases.show', $sale) }}#print" class="btn btn-sm btn-outline-secondary">
                                    <i class="fas fa-print me-1" aria-hidden="true"></i>{{ __('app.admin.stock_sales.print') }}
                                </a>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="5" class="text-muted text-center py-4">{{ __('app.admin.stock_sales.empty') }}</td>
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

