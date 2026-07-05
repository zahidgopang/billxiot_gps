@php
    $linkedSubscriptions = $linkedSubscriptions ?? collect();
@endphp

@if($linkedSubscriptions->isNotEmpty())
    <div class="{{ $class ?? 'mb-3' }}">
        <strong>{{ __('app.billing.invoice_devices') }} ({{ $linkedSubscriptions->count() }})</strong>
        @if($invoice->isConsolidated())
            <span class="badge bg-secondary ms-1">{{ __('app.billing.consolidated_invoice') }}</span>
        @endif
        <ul class="list-unstyled small mb-0 mt-2">
            @foreach($linkedSubscriptions as $sub)
                <li class="mb-1">
                    <i class="fas fa-satellite-dish text-muted me-1" aria-hidden="true"></i>
                    {{ $sub->device->name ?? $sub->device->imei ?? ('Device #'.$sub->device_id) }}
                    @if($sub->device?->imei)
                        <span class="text-muted">· IMEI {{ $sub->device->imei }}</span>
                    @endif
                    @if($sub->plan)
                        <span class="text-muted">· {{ $sub->plan }}</span>
                    @endif
                </li>
            @endforeach
        </ul>
    </div>
@endif
