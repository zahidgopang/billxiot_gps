@extends('admin.layouts.app')

@section('title', __('app.routes.edit'))

@section('content')
<div class="container-fluid py-3">
    <h1 class="h4 mb-3">{{ __('app.routes.edit') }} — {{ $route->name }}</h1>
    @include('admin.routes._form', [
        'action' => route($panel . '.routes.update', $route),
        'method' => 'PUT',
    ])
</div>
@endsection
