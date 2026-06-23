@extends('admin.layouts.app')

@section('title', __('app.admin.stock.add_order'))
@section('page-title', __('app.admin.stock.add_order'))

@section('content')
    <x-admin.form-shell
        :action="route('admin.device-stock.store')"
        :cancel-url="route('admin.device-stock.index')"
    >
        @include('admin.device-stock._form', ['order' => $order])

        <x-slot:footer>
            <a href="{{ route('admin.device-stock.index') }}" class="btn btn-light btn-sm">
                <i class="fas fa-times me-1" aria-hidden="true"></i>{{ __('app.common.cancel') }}
            </a>
            <button type="submit" class="btn btn-primary btn-sm">
                <i class="fas fa-check me-1" aria-hidden="true"></i>{{ __('app.common.create') }}
            </button>
        </x-slot:footer>
    </x-admin.form-shell>
@endsection
