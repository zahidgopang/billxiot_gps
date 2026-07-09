@php
    $theme = 'slate';
@endphp

@extends('errors.layout')

@section('title', __('app.errors.500_title'))

@section('content')
    <div class="err-icon" aria-hidden="true">
        <i class="fas fa-server"></i>
    </div>
    <div class="err-code">
        <i class="fas fa-screwdriver-wrench"></i>
        500
    </div>
    <h1 class="err-title">{{ __('app.errors.500_title') }}</h1>
    <p class="err-message">{{ __('app.errors.500_message') }}</p>
    <div class="err-detail">
        <strong>
            <i class="fas fa-circle-info"></i>
            {{ __('app.errors.why') }}
        </strong>
        {{ __('app.errors.500_detail') }}
    </div>
@endsection

@section('actions')
    <button type="button" class="err-btn err-btn--primary" onclick="location.reload()">
        <i class="fas fa-rotate-right"></i>
        {{ __('app.errors.try_again') }}
    </button>
    <a href="{{ url('/') }}" class="err-btn err-btn--secondary">
        <i class="fas fa-home"></i>
        {{ __('app.errors.go_home') }}
    </a>
    @if(Route::has('contact'))
        <a href="{{ route('contact') }}" class="err-btn err-btn--secondary">
            <i class="fas fa-envelope"></i>
            {{ __('app.errors.contact_support') }}
        </a>
    @endif
@endsection
