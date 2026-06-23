<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Server Error - {{ config('app.name') }}</title>
    <link href="https://cdn.jsdelivr.net/npm/tailwindcss@2.2.19/dist/tailwind.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
</head>
<body class="bg-gradient-to-br from-gray-50 to-slate-100 min-h-screen flex items-center justify-center p-4">
<div class="max-w-lg w-full bg-white rounded-xl shadow-xl p-8 md:p-10">
    <div class="text-center">
        <div class="inline-block bg-gray-100 rounded-full p-4 mb-6">
            <i class="fas fa-server text-gray-500 text-5xl"></i>
        </div>
        <h1 class="text-3xl font-bold text-gray-800 mb-2">500 - Server Error</h1>
        <p class="text-gray-600 mb-6">Something went wrong on our server. We're working to fix it.</p>
    </div>

    <div class="bg-gray-50 border border-gray-200 rounded-lg p-4 mb-8">
        <h3 class="font-semibold text-gray-800 mb-2 flex items-center">
            <i class="fas fa-tools mr-2"></i>
            What's happening:
        </h3>
        <p class="text-sm text-gray-700">Our technical team has been notified and is investigating the issue.</p>
    </div>

    <div class="space-y-4">
        <button onclick="location.reload()"
                class="w-full bg-gray-600 hover:bg-gray-700 text-white font-semibold py-3 px-4 rounded-lg transition duration-200 flex items-center justify-center">
            <i class="fas fa-redo mr-2"></i>
            Try Again
        </button>

        <div class="flex space-x-3">
            <a href="{{ url('/') }}"
               class="flex-1 bg-blue-500 hover:bg-blue-600 text-white font-medium py-2 px-4 rounded-lg transition duration-200 text-center">
                <i class="fas fa-home mr-2"></i>
                Home
            </a>

            <a href="{{ url('/status') }}"
               class="flex-1 bg-yellow-500 hover:bg-yellow-600 text-white font-medium py-2 px-4 rounded-lg transition duration-200 text-center">
                <i class="fas fa-chart-line mr-2"></i>
                System Status
            </a>

            <a href="mailto:support@example.com"
               class="flex-1 bg-red-500 hover:bg-red-600 text-white font-medium py-2 px-4 rounded-lg transition duration-200 text-center">
                <i class="fas fa-exclamation-circle mr-2"></i>
                Report Problem
            </a>
        </div>

        <a href="{{ url('/help') }}"
           class="block text-center text-blue-600 hover:text-blue-800 hover:underline text-sm mt-4">
            <i class="fas fa-question-circle mr-1"></i>
            Visit Help Center for More Information
        </a>
    </div>
</div>
</body>
</html>
