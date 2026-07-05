@extends('admin.layouts.app')
@section('title', __('app.admin.subscriptions.view_title'))
@section('page-title', __('app.admin.subscriptions.view_title'))

@section('content')
    @php
        $panel = $panel ?? 'admin';
        $s = $subscription;
        $clientInvoice = $s->clientInvoice;
        $platformInvoice = $s->platformInvoice;
    @endphp

    <style>
        @media print {
            .admin-sidebar, .sidebar-header, .sidebar-nav, .sidebar-footer,
            .admin-topbar, .admin-filter-bar, .btn, .no-print { display: none !important; }
            .card { border: 0 !important; }
        }
    </style>

    <div class="mb-3 d-flex flex-wrap gap-2 align-items-center no-print">
        <a href="{{ route($panel . '.subscriptions.index') }}" class="btn btn-sm btn-light">&larr; {{ __('app.common.subscriptions') }}</a>
        @if(Gate::allows('permission', 'subscriptions.manage'))
            <a href="{{ route($panel . '.subscriptions.edit', $s) }}" class="btn btn-sm btn-outline-primary">{{ __('app.common.edit') }}</a>
        @endif
        @if($clientInvoice)
            <a href="{{ route($panel . '.billing-invoices.show', $clientInvoice) }}" target="_blank" class="btn btn-sm btn-outline-secondary">{{ __('app.billing.view_invoice') }}</a>
        @endif
        <button type="button" class="btn btn-sm btn-primary ms-auto" onclick="window.print()">
            <i class="fas fa-print me-1"></i> {{ __('app.admin.subscriptions.print_details') }}
        </button>
    </div>

    <div class="row g-3">
        <div class="col-lg-5">
            <div class="card">
                <div class="card-body">
                    <h5 class="card-title mb-3">{{ __('app.admin.subscriptions.batch_summary') }}</h5>
                    <p class="mb-1"><strong>{{ __('app.forms.plan') }}:</strong> {{ $s->plan }}</p>
                    <p class="mb-1"><strong>{{ __('app.forms.owner') }}:</strong> {{ $s->user?->name ?? '—' }}</p>
                    <p class="mb-1"><strong>{{ __('app.forms.starts') }}:</strong> {{ optional($s->starts_at)->format('d M Y') ?? '—' }}</p>
                    <p class="mb-1"><strong>{{ __('app.forms.ends') }}:</strong> {{ optional($s->ends_at)->format('d M Y') ?? '—' }}</p>
                    <p class="mb-1"><strong>{{ __('app.common.status') }}:</strong>
                        @if($s->status === 'active')
                            <span class="badge bg-success">{{ __('app.common.active') }}</span>
                        @elseif($s->status === 'expired')
                            <span class="badge bg-danger">{{ __('app.forms.expired') }}</span>
                        @else
                            <span class="badge bg-warning text-dark">{{ __('app.forms.cancelled') }}</span>
                        @endif
                    </p>
                    <p class="mb-0"><strong>{{ __('app.admin.subscriptions.attached_vehicles') }}:</strong>
                        {{ __('app.admin.subscriptions.vehicle_count_label', ['count' => $batch->device_count]) }}
                    </p>
                </div>
            </div>

            @if($clientInvoice || $platformInvoice)
                <div class="card mt-3">
                    <div class="card-header py-2">{{ __('app.billing.invoices') }}</div>
                    <div class="card-body small">
                        @if($clientInvoice)
                            <div class="mb-2">
                                <strong>{{ __('app.billing.end_user_invoice_summary') }}:</strong>
                                <a href="{{ route($panel . '.billing-invoices.show', $clientInvoice) }}" target="_blank">{{ $clientInvoice->invoice_no }}</a>
                            </div>
                        @endif
                        @if($platformInvoice)
                            <div>
                                <strong>{{ __('app.billing.platform_invoice_summary') }}:</strong>
                                <a href="{{ route($panel . '.billing-invoices.show', $platformInvoice) }}" target="_blank">{{ $platformInvoice->invoice_no }}</a>
                            </div>
                        @endif
                    </div>
                </div>
            @endif
        </div>

        <div class="col-lg-7">
            @include('admin.subscriptions._batch-devices', ['batch' => $batch])
        </div>
    </div>
@endsection
