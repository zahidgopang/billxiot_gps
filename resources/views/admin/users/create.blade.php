@extends('admin.layouts.app')
@section('title','Add User')
@section('page-title','Add User')

@section('content')
    @php $panel = $panel ?? (request()->routeIs('client.*') ? 'client' : 'admin'); @endphp
    <x-admin.form-shell
        :action="route($panel . '.users.store')"
        :cancel-url="route($panel . '.users.index')"
    >
        @include('admin.users._form', ['user' => null, 'panel' => $panel])

        <x-slot:footer>
            <a href="{{ route($panel . '.users.index') }}" class="btn btn-light btn-sm">
                <i class="fas fa-times me-1" aria-hidden="true"></i>{{ __('app.common.cancel') }}
            </a>
            <button type="submit" class="btn btn-primary btn-sm">
                <i class="fas fa-check me-1" aria-hidden="true"></i>{{ __('app.common.save') }}
            </button>
        </x-slot:footer>
    </x-admin.form-shell>
@endsection
