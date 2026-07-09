@php
    $panel = $panel ?? (request()->routeIs('user.*') ? 'user' : (request()->routeIs('client.*') ? 'client' : 'admin'));
    $isUserPanel = $panel === 'user';
    $layout = $isUserPanel ? 'user.layout_user' : 'admin.layouts.app';
@endphp

@extends($layout)

@section('title', __('app.sub_accounts.edit'))
@if(! $isUserPanel)
@section('page-title', __('app.sub_accounts.edit'))
@endif

@section('content')
    @if($isUserPanel)
        @include('sub-accounts._user-form-styles')

        <div class="ud-dashboard ud-fade-in sub-account-form">
            <header class="mb-4">
                <h1 class="ud-page-title">{{ __('app.sub_accounts.edit') }}</h1>
                <p class="ud-page-sub mb-0">{{ $subAccount->name }}</p>
            </header>

            @if($errors->any())
                <div class="ud-alert ud-alert--warn mb-4">
                    <div>
                        @foreach($errors->all() as $error)
                            <div>{{ $error }}</div>
                        @endforeach
                    </div>
                </div>
            @endif

            <form method="POST" action="{{ route('user.sub-accounts.update', $subAccount) }}">
                @csrf
                @method('PUT')
                @include('sub-accounts._form-user')
                <div class="d-flex flex-wrap gap-2">
                    <a href="{{ route('user.sub-accounts.index') }}" class="ud-btn ud-btn--secondary">{{ __('app.common.cancel') }}</a>
                    <button type="submit" class="ud-btn ud-btn--primary">{{ __('app.common.update') }}</button>
                </div>
            </form>
        </div>
    @else
        <x-admin.form-shell
            :action="route($panel . '.sub-accounts.update', $subAccount)"
            method="PUT"
            :cancel-url="route($panel . '.sub-accounts.index')"
        >
            @include('sub-accounts._form')
            <x-slot:footer>
                <a href="{{ route($panel . '.sub-accounts.index') }}" class="btn btn-light btn-sm">{{ __('app.common.cancel') }}</a>
                <button type="submit" class="btn btn-primary btn-sm">{{ __('app.common.update') }}</button>
            </x-slot:footer>
        </x-admin.form-shell>
    @endif
@endsection
