@php
    $theme = 'teal';
    $exceptionMessage = isset($exception) ? trim((string) $exception->getMessage()) : '';
    $detail = $exceptionMessage !== '' && ! str_contains(strtolower($exceptionMessage), 'unprocessable')
        ? $exceptionMessage
        : __('app.errors.422_detail');
@endphp

@extends('errors.layout')

@section('title', __('app.errors.422_title'))

@section('content')
    <div class="err-icon" aria-hidden="true">
        <i class="fas fa-clipboard-check"></i>
    </div>
    <div class="err-code">
        <i class="fas fa-file-circle-exclamation"></i>
        422
    </div>
    <h1 class="err-title">{{ __('app.errors.422_title') }}</h1>
    <p class="err-message">{{ __('app.errors.422_message') }}</p>
    <div class="err-detail">
        <strong>
            <i class="fas fa-circle-info"></i>
            {{ __('app.errors.why') }}
        </strong>
        {{ $detail }}
    </div>
@endsection

@section('actions')
    <button type="button" class="err-btn err-btn--primary" onclick="history.back()">
        <i class="fas fa-arrow-{{ app()->getLocale() === 'ar' ? 'right' : 'left' }}"></i>
        {{ __('app.errors.go_back') }}
    </button>
    <a href="{{ url('/') }}" class="err-btn err-btn--secondary">
        <i class="fas fa-home"></i>
        {{ __('app.errors.go_home') }}
    </a>
@endsection
