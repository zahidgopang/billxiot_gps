@php
    $panel = $panel ?? 'admin';
    $activeTab = $activeTab ?? \App\Enums\BillingInvoiceType::Platform->value;
    $baseUrl = route($panel . '.billing-invoices.index');
    $query = request()->except(['type', 'page']);
@endphp
<ul class="nav nav-tabs mb-3 billing-invoice-tabs" role="tablist">
    <li class="nav-item" role="presentation">
        <a class="nav-link @if($activeTab === \App\Enums\BillingInvoiceType::Platform->value) active @endif"
           href="{{ $baseUrl . '?' . http_build_query(array_merge($query, ['type' => \App\Enums\BillingInvoiceType::Platform->value])) }}"
           role="tab">
            {{ __('app.billing.tab_invoices_platform') }}
        </a>
    </li>
    <li class="nav-item" role="presentation">
        <a class="nav-link @if($activeTab === \App\Enums\BillingInvoiceType::Client->value) active @endif"
           href="{{ $baseUrl . '?' . http_build_query(array_merge($query, ['type' => \App\Enums\BillingInvoiceType::Client->value])) }}"
           role="tab">
            {{ __('app.billing.tab_invoices_end_user') }}
        </a>
    </li>
</ul>
