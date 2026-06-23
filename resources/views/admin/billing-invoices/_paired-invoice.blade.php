@php
    $panel = $panel ?? 'admin';
    $paired = $pairedInvoice ?? $invoice->pairedInvoice();
@endphp
@if($paired)
    <div class="alert alert-info border-info d-flex flex-wrap align-items-center gap-2 mb-3">
        <div class="flex-grow-1">
            <strong>{{ __('app.billing.linked_invoice_ref') }}:</strong>
            <code class="ms-1">{{ $paired->invoice_no }}</code>
            <span class="text-muted small ms-2">({{ $paired->typeEnum()->label() }})</span>
        </div>
        <a href="{{ route($panel . '.billing-invoices.show', $paired) }}" class="btn btn-sm btn-outline-primary">
            {{ __('app.billing.view_linked_invoice') }}
        </a>
    </div>
@endif
