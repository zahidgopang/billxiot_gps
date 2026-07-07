<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" dir="{{ $htmlDir ?? 'ltr' }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1, maximum-scale=5, viewport-fit=cover">
        <meta name="csrf-token" content="{{ csrf_token() }}">

        @include('partials.seo-meta', [
            'seoTitle' => config('branding.name').' — Sign In',
            'seoDescription' => 'Sign in to '.config('branding.name').' for live GPS tracking, fleet management, and vehicle monitoring.',
        ])

        <link rel="stylesheet" href="{{ asset('css/brand-logo.css') }}?v={{ filemtime(public_path('css/brand-logo.css')) }}">
        <link rel="stylesheet" href="{{ asset('css/auth-guest.css') }}?v={{ filemtime(public_path('css/auth-guest.css')) }}">
        <link rel="stylesheet" href="{{ asset('css/auth-forms.css') }}?v={{ filemtime(public_path('css/auth-forms.css')) }}">
        <script src="https://cdn.tailwindcss.com"></script>
    </head>
    <body class="auth-guest-body text-gray-900 antialiased">
        <div class="auth-guest-shell">
            <div class="auth-logo-wrap">
                <a href="{{ url('/') }}" class="flex justify-center brand-logo-slot" aria-label="{{ config('branding.name') }}">
                    @include('partials.brand-logo')
                </a>
            </div>

            <div class="auth-guest-card">
                {{ $slot }}
            </div>
        </div>
    </body>
</html>
