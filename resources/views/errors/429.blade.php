@php
    $theme = 'orange';
@endphp

@extends('errors.layout')

@section('title', __('app.errors.429_title'))

@section('content')
    <div class="err-icon" aria-hidden="true">
        <i class="fas fa-gauge-high"></i>
    </div>
    <div class="err-code">
        <i class="fas fa-hourglass-half"></i>
        429
    </div>
    <h1 class="err-title">{{ __('app.errors.429_title') }}</h1>
    <p class="err-message">{{ __('app.errors.429_message') }}</p>
    <div class="err-detail">
        <strong>
            <i class="fas fa-circle-info"></i>
            {{ __('app.errors.why') }}
        </strong>
        {{ __('app.errors.429_detail') }}
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
@endsection
