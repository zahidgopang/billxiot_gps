@php
    $theme = 'purple';
    $homeUrl = auth()->check() && Route::has('dashboard') ? route('dashboard') : url('/');
@endphp

@extends('errors.layout')

@section('title', __('app.errors.404_title'))

@section('content')
    <div class="err-icon" aria-hidden="true">
        <i class="fas fa-map-location-dot"></i>
    </div>
    <div class="err-code">
        <i class="fas fa-compass"></i>
        404
    </div>
    <h1 class="err-title">{{ __('app.errors.404_title') }}</h1>
    <p class="err-message">{{ __('app.errors.404_message') }}</p>
    <div class="err-detail">
        <strong>
            <i class="fas fa-circle-info"></i>
            {{ __('app.errors.why') }}
        </strong>
        {{ __('app.errors.404_detail') }}
    </div>
@endsection

@section('actions')
    <a href="{{ $homeUrl }}" class="err-btn err-btn--primary">
        <i class="fas fa-home"></i>
        {{ auth()->check() ? __('app.errors.go_dashboard') : __('app.errors.go_home') }}
    </a>
    <button type="button" class="err-btn err-btn--secondary" onclick="history.back()">
        <i class="fas fa-arrow-{{ app()->getLocale() === 'ar' ? 'right' : 'left' }}"></i>
        {{ __('app.errors.go_back') }}
    </button>
@endsection
