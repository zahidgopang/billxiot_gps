@extends('admin.layouts.app')
@section('title','Add Subscription')
@section('page-title','Add Subscription')

@section('content')
    @php $panel = $panel ?? (request()->routeIs('client.*') ? 'client' : 'admin'); @endphp
    <x-admin.form-shell
        :action="route($panel . '.subscriptions.store')"
        :cancel-url="route($panel . '.subscriptions.index')"
        id="subscription-form"
        data-subscription-dates
        data-subscription-create="1"
        data-subscription-billing="1"
    >
        @include('admin.subscriptions._form', [
            'subscription' => null,
            'clients' => $clients ?? collect(),
            'devicesByClient' => $devicesByClient ?? [],
            'selectedClient' => $selectedClient ?? null,
            'plans' => $plans ?? collect(),
            'panel' => $panel,
        ])

        <x-slot:footer>
            <a href="{{ route($panel . '.subscriptions.index') }}" class="btn btn-light btn-sm">
                <i class="fas fa-times me-1" aria-hidden="true"></i>{{ __('app.common.cancel') }}
            </a>
            <button type="submit" class="btn btn-primary btn-sm">
                <i class="fas fa-check me-1" aria-hidden="true"></i>{{ __('app.common.save') }}
            </button>
        </x-slot:footer>
    </x-admin.form-shell>
@endsection

@push('scripts')
    <script src="{{ protected_js('subscription-dates.js') }}"></script>
    @include('admin.subscriptions._devices-script', [
        'panel' => $panel ?? (request()->routeIs('client.*') ? 'client' : 'admin'),
        'clients' => $clients ?? collect(),
        'devicesByClient' => $devicesByClient ?? [],
        'selectedDeviceId' => $selectedDeviceId ?? null,
    ])
    <script src="{{ protected_js('subscription-payment-modal.js') }}"></script>
    @include('admin.subscriptions._billing-script', ['panel' => $panel])
@endpush
