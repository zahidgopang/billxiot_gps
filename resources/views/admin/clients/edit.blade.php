@extends('admin.layouts.app')

@section('title', 'Edit client')
@section('page-title', 'Edit client')

@section('content')
    <x-admin.form-shell
        :action="route('admin.clients.update', $client)"
        method="PUT"
        :cancel-url="route('admin.clients.index')"
    >
        @include('admin.clients._form', [
            'client' => $client,
            'admins' => $admins ?? collect(),
        ])

        <x-slot:footer>
            <a href="{{ route('admin.clients.index') }}" class="btn btn-light btn-sm">
                <i class="fas fa-times me-1" aria-hidden="true"></i>{{ __('app.common.cancel') }}
            </a>
            <button type="submit" class="btn btn-primary btn-sm">
                <i class="fas fa-check me-1" aria-hidden="true"></i>{{ __('app.common.save') }}
            </button>
        </x-slot:footer>
    </x-admin.form-shell>
@endsection
