@extends('admin.layouts.app')

@section('title', __('app.admin.stock.edit'))
@section('page-title', __('app.admin.stock.edit'))

@section('content')
    <x-admin.form-shell
        :action="route('admin.device-stock.update', $order)"
        method="PUT"
        :cancel-url="route('admin.device-stock.show', $order)"
    >
        @include('admin.device-stock._form', ['order' => $order])

        <x-slot:footer>
            <a href="{{ route('admin.device-stock.show', $order) }}" class="btn btn-light btn-sm">
                <i class="fas fa-times me-1" aria-hidden="true"></i>{{ __('app.common.cancel') }}
            </a>
            <button type="submit" class="btn btn-primary btn-sm">
                <i class="fas fa-save me-1" aria-hidden="true"></i>{{ __('app.common.save') }}
            </button>
        </x-slot:footer>
    </x-admin.form-shell>
@endsection
