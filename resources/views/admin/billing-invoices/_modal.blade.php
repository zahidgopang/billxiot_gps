@php
    $panel = $panel ?? 'admin';
    $type = $invoice->typeEnum();
    $isPlatform = $type === \App\Enums\BillingInvoiceType::Platform;
@endphp

<div class="d-flex flex-wrap justify-content-between align-items-start gap-2">
    <div>
        <div class="fw-semibold">{{ $invoice->invoice_no }}</div>
        <div class="small text-muted">{{ $invoice->typeEnum()->label() }}</div>
    </div>
    <div class="text-end">
        <span class="badge bg-{{ $invoice->statusEnum()->badgeClass() }}">{{ $invoice->statusEnum()->label() }}</span>
        <div class="small text-muted mt-1">{{ __('app.billing.due_date') }}: {{ $invoice->due_date?->format('d M Y') ?? '—' }}</div>
    </div>
</div>

<hr class="my-3">

@include('admin.billing-invoices._paired-invoice', [
    'invoice' => $invoice,
    'pairedInvoice' => $pairedInvoice ?? null,
    'panel' => $panel,
])

<div class="row g-2 small">
    <div class="col-md-6">
        <div><strong>{{ __('app.forms.client_company') }}:</strong> {{ $invoice->client?->name ?? '—' }}</div>
        @if($invoice->user)
            <div><strong>{{ __('app.billing.bill_to') }}:</strong> {{ $invoice->user->name }} ({{ $invoice->user->email }})</div>
        @elseif($isPlatform)
            <div><strong>{{ __('app.billing.payable_to') }}:</strong> {{ __('app.billing.platform_company') }}</div>
        @endif
    </div>
    <div class="col-md-6">
        @if($invoice->subscription)
            <div><strong>{{ __('app.common.subscriptions') }}:</strong> {{ $invoice->subscription->plan }}</div>
            @if($invoice->subscription->device)
                <div class="text-muted">{{ $invoice->subscription->device->name ?? $invoice->subscription->device->imei }}</div>
            @endif
        @endif
    </div>
</div>

<div class="table-responsive mt-3">
    <table class="table table-sm align-middle mb-0">
        <thead class="table-light">
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

<div class="d-flex justify-content-end gap-2 mt-3">
    <a href="{{ route($panel . '.billing-invoices.show', $invoice) }}" target="_blank" class="btn btn-sm btn-outline-secondary">
        {{ __('app.billing.view_invoice') }}
    </a>
    <a href="{{ route($panel . '.billing-invoices.show', $invoice) }}" target="_blank" class="btn btn-sm btn-primary">
        Print
    </a>
</div>

