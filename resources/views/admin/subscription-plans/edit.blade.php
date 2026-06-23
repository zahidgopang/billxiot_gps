@extends('admin.layouts.app')
@section('title', __('app.billing.edit_plan'))
@section('page-title', __('app.billing.edit_plan'))

@section('content')
    <x-admin.form-shell :action="route('admin.subscription-plans.update', $plan)" method="PUT" :cancel-url="route('admin.subscription-plans.index')">
        @include('admin.subscription-plans._form', ['plan' => $plan])
        <x-slot:footer>
            <a href="{{ route('admin.subscription-plans.index') }}" class="btn btn-light btn-sm">{{ __('app.common.cancel') }}</a>
            <button type="submit" class="btn btn-primary btn-sm">{{ __('app.common.update') }}</button>
        </x-slot:footer>
    </x-admin.form-shell>
@endsection
