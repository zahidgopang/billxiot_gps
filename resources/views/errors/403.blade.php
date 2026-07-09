@php
    $theme = 'red';
    $exceptionMessage = isset($exception) ? trim((string) $exception->getMessage()) : '';
    $genericMessages = [
        '',
        'Forbidden',
        'This action is unauthorized.',
        'You do not have permission to perform this action.',
    ];
    $detail = in_array($exceptionMessage, $genericMessages, true)
        ? __('app.errors.403_detail')
        : $exceptionMessage;
    $homeUrl = auth()->check() && Route::has('dashboard') ? route('dashboard') : url('/');
@endphp

@extends('errors.layout')

@section('title', __('app.errors.403_title'))

@section('content')
    <div class="err-icon" aria-hidden="true">
        <i class="fas fa-shield-halved"></i>
    </div>
    <div class="err-code">
        <i class="fas fa-ban"></i>
        403
    </div>
    <h1 class="err-title">{{ __('app.errors.403_title') }}</h1>
    <p class="err-message">{{ __('app.errors.403_message') }}</p>
    <div class="err-detail">
        <strong>
            <i class="fas fa-circle-info"></i>
            {{ __('app.errors.why') }}
        </strong>
        {{ $detail }}
    </div>
@endsection

@section('actions')
    <a href="{{ $homeUrl }}" class="err-btn err-btn--primary">
        <i class="fas fa-home"></i>
        {{ auth()->check() ? __('app.errors.go_dashboard') : __('app.errors.go_home') }}
    </a>
    @if(auth()->check())
        <button type="button" class="err-btn err-btn--secondary" onclick="history.back()">
            <i class="fas fa-arrow-{{ app()->getLocale() === 'ar' ? 'right' : 'left' }}"></i>
            {{ __('app.errors.go_back') }}
        </button>
    @elseif(Route::has('login'))
        <a href="{{ route('login') }}" class="err-btn err-btn--secondary">
            <i class="fas fa-right-to-bracket"></i>
            {{ __('app.errors.sign_in') }}
        </a>
    @endif
    @if(Route::has('contact'))
        <a href="{{ route('contact') }}" class="err-btn err-btn--secondary">
            <i class="fas fa-envelope"></i>
            {{ __('app.errors.contact_support') }}
        </a>
    @endif
@endsection
