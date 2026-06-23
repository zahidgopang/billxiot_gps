@extends('admin.layouts.app')
@section('title', __('app.billing.profit_loss'))
@section('page-title', __('app.billing.profit_loss'))

@section('content')
    <div class="row g-3">
        @foreach([
            ['subscription_profit', 'app.billing.subscription_profit', 'primary'],
            ['device_profit', 'app.billing.device_profit', 'info'],
            ['total_profit', 'app.billing.total_profit', 'success'],
            ['total_revenue', 'app.billing.total_revenue', 'dark'],
            ['total_pending_dues', 'app.billing.pending_dues', 'warning'],
            ['stock_available_units', 'app.billing.stock_available', 'secondary'],
        ] as [$key, $label, $color])
            <div class="col-md-4 col-lg-2">
                <div class="card border-{{ $color }}">
                    <div class="card-body p-3 text-center">
                        <div class="small text-muted">{{ __($label) }}</div>
                        <div class="fs-5 fw-bold">
                            @if(str_ends_with($key, 'units'))
                                {{ number_format($metrics[$key]) }}
                            @else
                                {{ number_format($metrics[$key], 2) }}
                            @endif
                        </div>
                    </div>
                </div>
            </div>
        @endforeach
    </div>

    <div class="row g-3 mt-2">
        <div class="col-md-6">
            <div class="card">
                <div class="card-header">{{ __('app.billing.invoices_summary') }}</div>
                <ul class="list-group list-group-flush">
                    <li class="list-group-item d-flex justify-content-between">
                        <span>{{ __('app.billing.paid_invoices') }}</span><strong>{{ $metrics['paid_invoices'] }}</strong>
                    </li>
                    <li class="list-group-item d-flex justify-content-between">
                        <span>{{ __('app.billing.unpaid_invoices') }}</span><strong>{{ $metrics['unpaid_invoices'] }}</strong>
                    </li>
                    <li class="list-group-item d-flex justify-content-between">
                        <span>{{ __('app.billing.partial_invoices') }}</span><strong>{{ $metrics['partial_invoices'] }}</strong>
                    </li>
                    <li class="list-group-item d-flex justify-content-between">
                        <span>{{ __('app.billing.cancelled_invoices') }}</span><strong>{{ $metrics['cancelled_invoices'] }}</strong>
                    </li>
                </ul>
            </div>
        </div>
        <div class="col-md-6">
            <div class="card">
                <div class="card-header">{{ __('app.common.subscriptions') }}</div>
                <ul class="list-group list-group-flush">
                    <li class="list-group-item d-flex justify-content-between">
                        <span>{{ __('app.common.active') }}</span><strong>{{ $metrics['active_subscriptions'] }}</strong>
                    </li>
                    <li class="list-group-item d-flex justify-content-between">
                        <span>{{ __('app.forms.cancelled') }}</span><strong>{{ $metrics['cancelled_subscriptions'] }}</strong>
                    </li>
                    <li class="list-group-item d-flex justify-content-between">
                        <span>{{ __('app.forms.expired') }}</span><strong>{{ $metrics['expired_subscriptions'] }}</strong>
                    </li>
                </ul>
            </div>
        </div>
    </div>
@endsection
