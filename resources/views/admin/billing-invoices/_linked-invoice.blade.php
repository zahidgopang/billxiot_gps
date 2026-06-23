@php
    /** @var \App\Models\BillingInvoice $invoice */
    $panel = $panel ?? 'admin';
    $paired = $invoice->pairedInvoice();
    $refNo = $invoice->pairedInvoiceNo();
@endphp
@if($paired && $refNo)
    <a href="{{ route($panel . '.billing-invoices.show', $paired) }}"
       class="text-decoration-none"
       title="{{ __('app.billing.view_linked_invoice') }}">
        <code>{{ $refNo }}</code>
        <i class="fas fa-external-link-alt fa-xs ms-1 opacity-75" aria-hidden="true"></i>
    </a>
@else
    <span class="text-muted">—</span>
@endif
