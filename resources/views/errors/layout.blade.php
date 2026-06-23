<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="viewport" content="width=device-width, initial-scale=1, maximum-scale=5">
    @include('partials.seo-meta', [
        'seoTitle' => (trim($__env->yieldContent('title')) ?: 'Error').' — '.config('branding.name'),
    ])
    <link href="https://cdn.jsdelivr.net/npm/tailwindcss@2.2.19/dist/tailwind.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    @yield('styles')
</head>
<body class="min-h-screen flex items-center justify-center p-4">
<div class="max-w-lg w-full bg-white rounded-xl shadow-xl p-8 md:p-10">
    @yield('content')

    <!-- Standard Navigation Footer -->
    <div class="mt-8 pt-6 border-t border-gray-200">
        <div class="flex flex-wrap justify-center gap-3">
            <a href="{{ url('/') }}"
               class="px-4 py-2 text-sm bg-gray-100 hover:bg-gray-200 text-gray-800 rounded-lg transition duration-200">
                <i class="fas fa-home mr-1"></i> Home
            </a>
            <a href="{{ url('/help') }}"
               class="px-4 py-2 text-sm bg-blue-100 hover:bg-blue-200 text-blue-800 rounded-lg transition duration-200">
                <i class="fas fa-question-circle mr-1"></i> Help
            </a>
            <a href="mailto:support@example.com"
               class="px-4 py-2 text-sm bg-green-100 hover:bg-green-200 text-green-800 rounded-lg transition duration-200">
                <i class="fas fa-envelope mr-1"></i> Contact
            </a>
            <a href="{{ url('/status') }}"
               class="px-4 py-2 text-sm bg-yellow-100 hover:bg-yellow-200 text-yellow-800 rounded-lg transition duration-200">
                <i class="fas fa-chart-line mr-1"></i> Status
            </a>
        </div>
        <p class="text-center text-gray-500 text-xs mt-4">
            &copy; {{ date('Y') }} {{ config('app.name') }}
        </p>
    </div>
</div>

@yield('scripts')
</body>
</html>
