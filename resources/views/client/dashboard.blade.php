@extends('admin.layouts.app')

@section('title', 'Client Dashboard')
@section('page-title', 'Client Dashboard')

@section('content')
    <div class="row g-3 mb-4">
        <div class="col-md-4">
            <div class="card p-3">
                <div class="text-muted small">Users</div>
                <div class="fs-3 fw-bold">{{ $stats['users'] }}</div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="card p-3">
                <div class="text-muted small">Devices</div>
                <div class="fs-3 fw-bold">{{ $stats['devices'] }}</div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="card p-3">
                <div class="text-muted small">Companies</div>
                <div class="fs-3 fw-bold">{{ $stats['clients'] }}</div>
            </div>
        </div>
    </div>

    <div class="card p-3">
        <h5 class="mb-3">Quick links</h5>
        <div class="d-flex flex-wrap gap-2">
            <a href="{{ route('client.users.index') }}" class="btn btn-primary btn-sm">Manage users</a>
            <a href="{{ route('client.devices.index') }}" class="btn btn-primary btn-sm">Manage devices</a>
            <a href="{{ route('client.subscriptions.index') }}" class="btn btn-outline-primary btn-sm">Subscriptions</a>
            <a href="{{ route('client.purchases.index') }}" class="btn btn-outline-primary btn-sm">{{ __('app.admin.purchase_invoices.title') }}</a>
        </div>
    </div>

    <div class="card p-3 mt-3">
        <div class="d-flex justify-content-between align-items-center flex-wrap gap-2 mb-2">
            <div>
                <h5 class="mb-0">{{ __('app.admin.purchase_invoices.title') }}</h5>
                <p class="text-muted small mb-0">{{ __('app.admin.purchase_invoices.subtitle') }}</p>
            </div>
            <a href="{{ route('client.purchases.index') }}" class="btn btn-sm btn-primary">{{ __('app.common.view') }}</a>
        </div>

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
                @forelse(($recentPurchases ?? collect()) as $sale)
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
                        <td colspan="5" class="text-muted text-center py-3">{{ __('app.admin.stock_sales.empty') }}</td>
                    </tr>
                @endforelse
                </tbody>
            </table>
        </div>
    </div>
@endsection
