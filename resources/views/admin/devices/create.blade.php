@extends('admin.layouts.app')
@section('title','Add Device')
@section('page-title','Add Device')

@section('content')
    @php $panel = $panel ?? (request()->routeIs('client.*') ? 'client' : 'admin'); @endphp
    <x-admin.form-shell
        :action="route($panel . '.devices.store')"
        :cancel-url="route($panel . '.devices.index')"
    >
        @include('admin.devices._form', [
            'device' => null,
            'panel' => $panel,
            'users' => $users,
            'clients' => $clients ?? collect(),
            'usersByClient' => $usersByClient ?? [],
            'formClientId' => $formClientId ?? null,
            'allowedDeviceTypes' => $allowedDeviceTypes ?? [],
            'clientStockBalance' => $clientStockBalance ?? null,
        ])

        <x-slot:footer>
            <a href="{{ route($panel . '.devices.index') }}" class="btn btn-light btn-sm">
                <i class="fas fa-times me-1" aria-hidden="true"></i>{{ __('app.common.cancel') }}
            </a>
            <button type="submit" class="btn btn-primary btn-sm" id="device-submit-btn"
                @if(($formClientId ?? null) && empty($allowedDeviceTypes ?? [])) disabled @endif>
                <i class="fas fa-check me-1" aria-hidden="true"></i>{{ __('app.common.save') }}
            </button>
        </x-slot:footer>
    </x-admin.form-shell>
@endsection

@push('scripts')
    @include('admin.devices._users-script', [
        'panel' => $panel,
        'clients' => $clients ?? collect(),
        'usersByClient' => $usersByClient ?? [],
        'selectedUserId' => old('user_id'),
    ])
    @include('admin.devices._client-stock-script', [
        'panel' => $panel,
        'formClientId' => $formClientId ?? null,
        'allowedDeviceTypes' => $allowedDeviceTypes ?? [],
        'deviceType' => old('device_type', ''),
    ])
@endpush
