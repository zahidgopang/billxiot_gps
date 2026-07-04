@extends('admin.layouts.app')

@section('title', __('app.routes.create'))

@section('content')
<div class="container-fluid py-3">
    <h1 class="h4 mb-3">{{ __('app.routes.create') }}</h1>
    @include('admin.routes._form', [
        'action' => route($panel . '.routes.store'),
        'method' => 'POST',
    ])
</div>
@endsection
