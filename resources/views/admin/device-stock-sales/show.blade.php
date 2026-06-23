@extends('admin.layouts.app')

@section('title', $sale->invoice_no)
@section('page-title', __('app.admin.stock_sales.invoice_detail'))

@push('styles')
<style>
    .invoice-print-doc {
        background: #fff;
        color: #111;
        border: 1px solid #dee2e6;
        border-radius: 0.5rem;
        padding: 1.5rem 1.75rem;
    }

    .invoice-print-doc__header {
        border-bottom: 2px solid #111;
        padding-bottom: 1rem;
        margin-bottom: 1.25rem;
    }

    .invoice-print-doc__brand {
        font-size: 1.25rem;
        font-weight: 700;
        letter-spacing: 0.02em;
    }

    .invoice-print-doc__title {
        font-size: 1.1rem;
        font-weight: 600;
        text-transform: uppercase;
        letter-spacing: 0.06em;
    }

    .invoice-print-doc table th {
        font-size: 0.8rem;
        text-transform: uppercase;
        letter-spacing: 0.04em;
        border-bottom: 2px solid #111 !important;
    }

    .invoice-print-doc table td,
    .invoice-print-doc table th {
        padding: 0.55rem 0.5rem;
        vertical-align: middle;
    }

    .invoice-print-doc__total-row td {
        border-top: 2px solid #111 !important;
        font-size: 1.05rem;
        font-weight: 700;
    }

    @media screen {
        .print-only-code { display: none; }
    }

    @media print {
        @page {
            size: A4;
            margin: 12mm 14mm;
        }

        body {
            background: #fff !important;
            color: #000 !important;
            -webkit-print-color-adjust: exact;
            print-color-adjust: exact;
        }

        .admin-sidebar,
        .admin-navbar,
        .sidebar-overlay,
        .footer-premium,
        .no-print,
        .no-print-inline {
            display: none !important;
        }

        .print-only-code {
            display: inline !important;
        }

        body.admin-panel .admin-main,
        body.admin-panel.admin-sidebar-open .admin-main,
        html[dir="rtl"] body.admin-panel.admin-sidebar-open .admin-main {
            margin: 0 !important;
            padding: 0 !important;
            width: 100% !important;
            max-width: 100% !important;
        }

        .content-wrap,
        main {
            padding: 0 !important;
            margin: 0 !important;
            max-width: 100% !important;
        }

        .invoice-print-doc {
            border: none !important;
            border-radius: 0 !important;
            padding: 0 !important;
            box-shadow: none !important;
        }

        .invoice-print-doc a {
            color: #000 !important;
            text-decoration: none !important;
        }

        .invoice-print-doc .badge {
            border: 1px solid #333 !important;
            color: #000 !important;
            background: transparent !important;
        }

        .table {
            page-break-inside: auto;
        }

        tr {
            page-break-inside: avoid;
            page-break-after: auto;
        }
    }
</style>
@endpush

@section('content')
    <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3 no-print">
        <div>
            <h5 class="mb-1 admin-ltr" dir="ltr"><code>{{ $sale->invoice_no }}</code></h5>
            <p class="text-muted small mb-0">
                {{ __('app.admin.stock_sales.client') }}: <strong>{{ $sale->client?->name }}</strong>
                · {{ __('app.admin.stock_sales.issued_at') }}: {{ $sale->issued_at?->format('Y-m-d') }}
            </p>
        </div>
        <div class="d-flex gap-1 flex-wrap">
            <button type="button" class="btn btn-sm btn-outline-secondary" onclick="window.print()">
                <i class="fas fa-print me-1" aria-hidden="true"></i>{{ __('app.admin.stock_sales.print') }}
            </button>
            <a href="{{ route('admin.device-stock-sales.index') }}" class="btn btn-sm btn-light">
                <i class="fas fa-arrow-left me-1" aria-hidden="true"></i>{{ __('app.admin.stock_sales.back_to_list') }}
            </a>
        </div>
    </div>

    <div id="invoice-print" class="invoice-print-doc">
        <div class="invoice-print-doc__header">
            <div class="row align-items-start">
                <div class="col-7">
                    <div class="invoice-print-doc__brand">{{ __('app.brand') }}</div>
                    <div class="small text-muted mt-1">{{ __('app.admin.stock_sales.invoice_subtitle') }}</div>
                </div>
                <div class="col-5 text-end">
                    <div class="invoice-print-doc__title">{{ __('app.admin.stock_sales.tax_invoice') }}</div>
                    <div class="admin-ltr mt-2" dir="ltr">
                        <strong>{{ __('app.admin.stock_sales.invoice_no') }}:</strong>
                        {{ $sale->invoice_no }}
                    </div>
                    <div class="small mt-1">
                        <strong>{{ __('app.admin.stock_sales.issued_at') }}:</strong>
                        {{ $sale->issued_at?->format('Y-m-d') }}
                    </div>
                    <div class="small mt-1">
                        <span class="badge bg-success">{{ __('app.admin.stock_sales.status_'.$sale->status) }}</span>
                    </div>
                </div>
            </div>
        </div>

        <div class="row mb-4">
            <div class="col-md-6">
                <div class="small text-muted text-uppercase mb-1">{{ __('app.admin.stock_sales.bill_to') }}</div>
                <div class="fw-semibold fs-5">{{ $sale->client?->name }}</div>
            </div>
            <div class="col-md-6 text-md-end mt-3 mt-md-0">
                @if($sale->creator)
                    <div class="small text-muted">
                        {{ __('app.admin.stock.created_by') }}: {{ $sale->creator->name }}
                    </div>
                    <div class="small text-muted admin-ltr" dir="ltr">
                        {{ $sale->created_at?->format('Y-m-d H:i') }}
                    </div>
                @endif
            </div>
        </div>

        <div class="table-responsive">
            <table class="table table-sm align-middle mb-0">
                <thead>
                <tr>
                    <th>#</th>
                    <th>{{ __('app.admin.stock.order_code') }}</th>
                    <th>{{ __('app.admin.stock.product') }}</th>
                    <th class="text-center">{{ __('app.admin.stock.quantity') }}</th>
                    <th class="text-end">{{ __('app.admin.stock_sales.unit_price') }}</th>
                    <th class="text-end">{{ __('app.admin.stock_sales.line_total') }}</th>
                </tr>
                </thead>
                <tbody>
                @foreach($sale->items as $index => $item)
                    <tr>
                        <td>{{ $index + 1 }}</td>
                        <td class="admin-ltr" dir="ltr">
                            <span class="print-only-code">{{ $item->stockOrder?->order_code }}</span>
                            <a href="{{ route('admin.device-stock.show', $item->stockOrder) }}" class="no-print-inline">{{ $item->stockOrder?->order_code }}</a>
                        </td>
                        <td>
                            <div>{{ $item->stockOrder?->displayLabel() }}</div>
                            @if($item->stockOrder?->device_type)
                                <div class="small text-muted">{{ __('app.forms.device_type_'.$item->stockOrder->device_type) }}</div>
                            @endif
                        </td>
                        <td class="text-center fw-semibold">{{ number_format($item->quantity) }}</td>
                        <td class="text-end admin-ltr text-nowrap" dir="ltr">
                            {{ $sale->currency }} {{ number_format($item->unit_price, 2) }}
                        </td>
                        <td class="text-end admin-ltr fw-semibold text-nowrap" dir="ltr">
                            {{ $sale->currency }} {{ number_format($item->line_total, 2) }}
                            <div class="small text-muted fw-normal no-print-inline">
                                {{ number_format($item->quantity) }} × {{ number_format($item->unit_price, 2) }}
                            </div>
                        </td>
                    </tr>
                @endforeach
                </tbody>
                <tfoot>
                <tr class="invoice-print-doc__total-row">
                    <td colspan="4" class="text-end">{{ __('app.admin.stock_sales.total_units') }}</td>
                    <td class="text-center fw-semibold">{{ number_format($sale->items->sum('quantity')) }}</td>
                    <td class="text-end admin-ltr" dir="ltr">
                        {{ $sale->currency }} {{ number_format($sale->totalAmount(), 2) }}
                    </td>
                </tr>
                </tfoot>
            </table>
        </div>

        @if($sale->notes)
            <div class="mt-3 pt-3 border-top">
                <div class="small text-muted text-uppercase mb-1">{{ __('app.admin.stock.notes') }}</div>
                <div>{{ $sale->notes }}</div>
            </div>
        @endif

        <div class="mt-4 pt-3 border-top small text-muted text-center">
            {{ __('app.admin.stock_sales.print_footer') }}
        </div>
    </div>
@endsection
