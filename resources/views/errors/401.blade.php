@php
    $theme = 'amber';
    $exceptionMessage = isset($exception) ? trim((string) $exception->getMessage()) : '';
    $detail = $exceptionMessage !== '' && ! in_array($exceptionMessage, ['Unauthorized', 'Unauthenticated.'], true)
        ? $exceptionMessage
        : __('app.errors.401_detail');
@endphp

@extends('errors.layout')

@section('title', __('app.errors.401_title'))

@section('content')
    <div class="err-icon" aria-hidden="true">
        <i class="fas fa-lock"></i>
    </div>
    <div class="err-code">
        <i class="fas fa-user-lock"></i>
        401
    </div>
    <h1 class="err-title">{{ __('app.errors.401_title') }}</h1>
    <p class="err-message">{{ __('app.errors.401_message') }}</p>
    <div class="err-detail">
        <strong>
            <i class="fas fa-circle-info"></i>
            {{ __('app.errors.why') }}
        </strong>
        {{ $detail }}
    </div>
@endsection

@section('actions')
    @if(Route::has('login'))
        <a href="{{ route('login') }}" class="err-btn err-btn--primary">
            <i class="fas fa-right-to-bracket"></i>
            {{ __('app.errors.sign_in') }}
        </a>
    @endif
    <a href="{{ url('/') }}" class="err-btn err-btn--secondary">
        <i class="fas fa-home"></i>
        {{ __('app.errors.go_home') }}
    </a>
@endsection
