@extends('admin.layouts.app')

@section('title', 'New client')
@section('page-title', 'New client')

@section('content')
    <x-admin.form-shell
        :action="route('admin.clients.store')"
        :cancel-url="route('admin.clients.index')"
    >
        @include('admin.clients._form', ['client' => null])

        <x-slot:footer>
            <a href="{{ route('admin.clients.index') }}" class="btn btn-light btn-sm">
                <i class="fas fa-times me-1" aria-hidden="true"></i>{{ __('app.common.cancel') }}
            </a>
            <button type="submit" class="btn btn-primary btn-sm">
                <i class="fas fa-check me-1" aria-hidden="true"></i>{{ __('app.common.create') }}
            </button>
        </x-slot:footer>
    </x-admin.form-shell>
@endsection
