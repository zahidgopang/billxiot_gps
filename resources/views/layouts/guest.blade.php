<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1, maximum-scale=5, viewport-fit=cover">
        <meta name="csrf-token" content="{{ csrf_token() }}">

        @include('partials.seo-meta', [
            'seoTitle' => config('branding.name').' — Sign In',
            'seoDescription' => 'Sign in to '.config('branding.name').' for live GPS tracking, fleet management, and vehicle monitoring.',
        ])

        <!-- Fonts -->
        <link rel="preconnect" href="https://fonts.bunny.net">
        <link href="https://fonts.bunny.net/css?family=figtree:400,500,600&display=swap" rel="stylesheet" />

        <link rel="stylesheet" href="{{ asset('css/brand-logo.css') }}?v={{ filemtime(public_path('css/brand-logo.css')) }}">

        <!-- Scripts -->
        @vite(['resources/css/app.css', 'resources/js/app.js'])
    </head>
    <body class="font-sans text-gray-900 antialiased">
        <div class="min-h-screen flex flex-col sm:justify-center items-center pt-6 sm:pt-0 bg-gray-100">
            <div class="auth-logo-wrap">
                <a href="{{ url('/') }}" class="flex justify-center brand-logo-slot" aria-label="{{ config('branding.name') }}">
                    @include('partials.brand-logo')
                </a>
            </div>

            <div class="w-full sm:max-w-md mt-6 px-6 py-4 bg-white shadow-md overflow-hidden sm:rounded-lg">
                {{ $slot }}
            </div>
        </div>
    </body>
</html>
