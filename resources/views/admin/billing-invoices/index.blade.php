@extends('admin.layouts.app')
@section('title', __('app.billing.invoices'))
@section('page-title', __('app.billing.invoices'))

@section('content')
    @php
        $panel = $panel ?? 'admin';
        $activeTab = $type;
    @endphp

    @include('admin.billing-invoices._tabs', ['panel' => $panel, 'activeTab' => $activeTab])

    <div class="admin-filter-bar d-flex flex-wrap gap-2 mb-3 align-items-end">
        <form method="GET" class="d-flex flex-wrap gap-2 align-items-end">
            <input type="hidden" name="type" value="{{ $activeTab }}">
            <div>
                <label class="form-label small mb-1">{{ __('app.common.status') }}</label>
                <select name="status" class="form-select form-select-sm">
                    <option value="">{{ __('app.common.all') }}</option>
                    @foreach(['unpaid','due','partial','paid','overdue','cancelled'] as $s)
                        <option value="{{ $s }}" @selected(request('status') === $s)>{{ __('app.billing.status_' . $s) }}</option>
                    @endforeach
                </select>
            </div>
            <div>
                <label class="form-label small mb-1">{{ __('app.billing.invoice_no') }}</label>
                <input type="text" name="q" value="{{ request('q') }}" class="form-control form-control-sm"
                       placeholder="{{ __('app.billing.search_invoice_or_linked') }}">
            </div>
            <button type="submit" class="btn btn-sm btn-primary">{{ __('app.common.filter') }}</button>
            @if(request()->hasAny(['status', 'q']))
                <a href="{{ route($panel . '.billing-invoices.index', ['type' => $activeTab]) }}"
                   class="btn btn-sm btn-outline-secondary" title="{{ __('app.common.clear') }}">
                    <i class="fas fa-times" aria-hidden="true"></i>
                </a>
            @endif
        </form>
    </div>

    <p class="small text-muted mb-3">
        @if($activeTab === \App\Enums\BillingInvoiceType::Platform->value)
            {{ __('app.billing.tab_invoices_platform_hint') }}
        @else
            {{ __('app.billing.tab_invoices_end_user_hint') }}
        @endif
    </p>

    <div class="table-responsive">
        <table class="table table-hover table-sm">
            <thead>
            <tr>
                <th>{{ __('app.billing.invoice_no') }}</th>
                <th>{{ __('app.billing.linked_invoice_ref') }}</th>
                <th>{{ __('app.forms.client_company') }}</th>
                @if($activeTab === \App\Enums\BillingInvoiceType::Client->value)
                    <th>{{ __('app.billing.bill_to') }}</th>
                @endif
                <th>{{ __('app.common.total') }}</th>
                <th>{{ __('app.billing.balance_due') }}</th>
                <th>{{ __('app.common.status') }}</th>
                <th></th>
            </tr>
            </thead>
            <tbody>
            @forelse($invoices as $inv)
                <tr>
                    <td><code>{{ $inv->invoice_no }}</code></td>
                    <td>
                        @include('admin.billing-invoices._linked-invoice', ['invoice' => $inv, 'panel' => $panel])
                    </td>
                    <td>{{ $inv->client?->name ?? '—' }}</td>
                    @if($activeTab === \App\Enums\BillingInvoiceType::Client->value)
                        <td>
                            @if($inv->user)
                                <span>{{ $inv->user->name }}</span>
                            @else
                                <span class="text-muted">—</span>
                            @endif
                        </td>
                    @endif
                    <td>{{ number_format((float) $inv->total, 2) }} {{ $inv->currency }}</td>
                    <td>{{ number_format((float) $inv->balance_due, 2) }}</td>
                    <td><span class="badge bg-{{ $inv->statusEnum()->badgeClass() }}">{{ $inv->statusEnum()->label() }}</span></td>
                    <td class="text-end">
                        <a href="{{ route($panel . '.billing-invoices.show', $inv) }}" class="btn btn-sm btn-outline-secondary">{{ __('app.common.view') }}</a>
                    </td>
                </tr>
            @empty
                <tr>
                    <td colspan="{{ $activeTab === \App\Enums\BillingInvoiceType::Client->value ? 8 : 7 }}" class="text-muted">
                        {{ __('app.billing.no_invoices') }}
                    </td>
                </tr>
            @endforelse
            </tbody>
        </table>
    </div>
    {{ $invoices->links() }}
@endsection
