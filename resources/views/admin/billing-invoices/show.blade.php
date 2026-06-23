@extends('admin.layouts.app')
@section('title', $invoice->invoice_no)
@section('page-title', $invoice->invoice_no)

@section('content')
    @php
        $panel = $panel ?? 'admin';
        $type = $invoice->typeEnum();
        $isPlatform = $type === \App\Enums\BillingInvoiceType::Platform;
    @endphp

    <style>
        @media print {
            .admin-sidebar,
            .sidebar-header,
            .sidebar-nav,
            .sidebar-footer,
            .admin-topbar,
            .admin-filter-bar,
            .btn,
            .modal,
            .toast-container {
                display: none !important;
            }
            .card {
                border: 0 !important;
            }
            .table {
                font-size: 12px;
            }
        }
    </style>
    <div class="mb-3 d-flex flex-wrap gap-2 align-items-center">
        <a href="{{ route($panel . '.billing-invoices.index', ['type' => $listTab ?? $invoice->invoice_type]) }}" class="btn btn-sm btn-light">&larr; {{ __('app.billing.invoices') }}</a>
        @if($invoice->subscription_id)
            <a href="{{ route($panel . '.subscriptions.edit', $invoice->subscription_id) }}" class="btn btn-sm btn-outline-primary">
                {{ __('app.billing.back_to_subscription') }}
            </a>
        @endif
        <button type="button" class="btn btn-sm btn-primary ms-auto" onclick="window.print()">
            <i class="fas fa-print me-1"></i> Print
        </button>
    </div>

    @include('admin.billing-invoices._paired-invoice', [
        'invoice' => $invoice,
        'pairedInvoice' => $pairedInvoice ?? null,
        'panel' => $panel,
    ])

    <div class="alert alert-light border mb-3">
        <strong>{{ $type->subscriptionSummaryLabel() }}</strong>
        <p class="small text-muted mb-0 mt-1">
            {{ $isPlatform ? __('app.billing.platform_invoice_desc') : __('app.billing.end_user_invoice_desc') }}
        </p>
    </div>

    <div class="row g-3">
        <div class="col-lg-8">
            <div class="card">
                <div class="card-body">
                    <h5 class="card-title">{{ $invoice->typeEnum()->label() }} · {{ $invoice->invoice_no }}</h5>
                    <p class="mb-1"><strong>{{ __('app.forms.client_company') }}:</strong> {{ $invoice->client?->name ?? '—' }}</p>
                    @if($invoice->user)
                        <p class="mb-1"><strong>{{ __('app.billing.bill_to') }}:</strong> {{ $invoice->user->name }} ({{ $invoice->user->email }})</p>
                    @elseif($isPlatform)
                        <p class="mb-1"><strong>{{ __('app.billing.payable_to') }}:</strong> {{ __('app.billing.platform_company') }}</p>
                    @endif
                    @if($invoice->subscription)
                        <p class="mb-1">
                            <strong>{{ __('app.common.subscriptions') }}:</strong>
                            {{ $invoice->subscription->plan }}
                            @if($invoice->subscription->device)
                                · {{ $invoice->subscription->device->name ?? $invoice->subscription->device->imei }}
                            @endif
                        </p>
                    @endif
                    <p class="mb-1"><strong>{{ __('app.common.status') }}:</strong>
                        <span class="badge bg-{{ $invoice->statusEnum()->badgeClass() }}">{{ $invoice->statusEnum()->label() }}</span>
                    </p>
                    <p class="mb-0"><strong>{{ __('app.billing.due_date') }}:</strong> {{ $invoice->due_date?->format('d M Y') ?? '—' }}</p>
                </div>
            </div>

            <div class="table-responsive mt-3">
                <table class="table table-sm">
                    <thead>
                    <tr>
                        <th>{{ __('app.billing.line_description') }}</th>
                        @if($isPlatform)
                            <th class="text-end">{{ __('app.billing.company_plan_price') }}</th>
                        @else
                            <th class="text-end">{{ __('app.billing.your_cost') }}</th>
                            <th class="text-end">{{ __('app.billing.end_user_selling_price') }}</th>
                        @endif
                        <th class="text-end">{{ __('app.common.total') }}</th>
                    </tr>
                    </thead>
                    <tbody>
                    @foreach($invoice->lines as $line)
                        <tr>
                            <td>{{ $line->description }}</td>
                            @if($isPlatform)
                                <td class="text-end admin-ltr" dir="ltr">{{ number_format((float) $line->unit_price, 2) }}</td>
                            @else
                                <td class="text-end text-muted admin-ltr" dir="ltr">{{ number_format((float) $line->unit_cost, 2) }}</td>
                                <td class="text-end fw-semibold admin-ltr" dir="ltr">{{ number_format((float) $line->unit_price, 2) }}</td>
                            @endif
                            <td class="text-end admin-ltr" dir="ltr">{{ number_format((float) $line->line_total, 2) }}</td>
                        </tr>
                    @endforeach
                    </tbody>
                    <tfoot>
                    <tr>
                        <th colspan="{{ $isPlatform ? 2 : 3 }}" class="text-end">{{ __('app.common.total') }}</th>
                        <th class="text-end admin-ltr" dir="ltr">{{ number_format((float) $invoice->total, 2) }} {{ $invoice->currency }}</th>
                    </tr>
                    <tr>
                        <th colspan="{{ $isPlatform ? 2 : 3 }}" class="text-end">{{ __('app.billing.amount_paid') }}</th>
                        <th class="text-end admin-ltr" dir="ltr">{{ number_format((float) $invoice->amount_paid, 2) }}</th>
                    </tr>
                    <tr>
                        <th colspan="{{ $isPlatform ? 2 : 3 }}" class="text-end">{{ __('app.billing.balance_due') }}</th>
                        <th class="text-end admin-ltr" dir="ltr">{{ number_format((float) $invoice->balance_due, 2) }}</th>
                    </tr>
                    </tfoot>
                </table>
            </div>
        </div>

        <div class="col-lg-4">
            @if(!$invoice->isCancelled() && (float) $invoice->balance_due > 0 && Gate::allows('permission', 'billing.manage'))
                <div class="card mb-3">
                    <div class="card-header">{{ __('app.billing.record_payment') }}</div>
                    <div class="card-body">
                        <form method="POST" action="{{ route($panel . '.billing-invoices.payments.store', $invoice) }}">
                            @csrf
                            <div class="mb-2">
                                <label class="form-label small">{{ __('app.billing.payment_amount') }}</label>
                                <input type="number" name="amount" step="0.01" min="0.01" max="{{ $invoice->balance_due }}" class="form-control form-control-sm" required>
                            </div>
                            <div class="mb-2">
                                <label class="form-label small">{{ __('app.billing.payment_method') }}</label>
                                <input type="text" name="payment_method" class="form-control form-control-sm" placeholder="Cash, Bank, …">
                            </div>
                            <div class="mb-2">
                                <label class="form-label small">{{ __('app.billing.payment_reference') }}</label>
                                <input type="text" name="reference" class="form-control form-control-sm">
                            </div>
                            <button type="submit" class="btn btn-primary btn-sm w-100">{{ __('app.billing.record_payment') }}</button>
                        </form>
                    </div>
                </div>
            @endif

            @if($invoice->payments->isNotEmpty())
                <div class="card">
                    <div class="card-header">{{ __('app.billing.payments') }}</div>
                    <ul class="list-group list-group-flush">
                        @foreach($invoice->payments as $payment)
                            <li class="list-group-item small">
                                {{ number_format((float) $payment->amount, 2) }} — {{ $payment->paid_at->format('d M Y') }}
                                @if($payment->payment_method)<br><span class="text-muted">{{ $payment->payment_method }}</span>@endif
                            </li>
                        @endforeach
                    </ul>
                </div>
            @endif
        </div>
    </div>
@endsection
