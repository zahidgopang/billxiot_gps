<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Validation Error - {{ config('app.name') }}</title>
    <link href="https://cdn.jsdelivr.net/npm/tailwindcss@2.2.19/dist/tailwind.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
</head>
<body class="bg-gradient-to-br from-teal-50 to-emerald-100 min-h-screen flex items-center justify-center p-4">
<div class="max-w-lg w-full bg-white rounded-xl shadow-xl p-8 md:p-10">
    <div class="text-center">
        <div class="inline-block bg-teal-100 rounded-full p-4 mb-6">
            <i class="fas fa-exclamation-triangle text-teal-500 text-5xl"></i>
        </div>
        <h1 class="text-3xl font-bold text-gray-800 mb-2">422 - Validation Error</h1>
        <p class="text-gray-600 mb-6">The server cannot process your request due to validation errors.</p>
    </div>

    <div class="bg-teal-50 border border-teal-200 rounded-lg p-4 mb-8">
        <h3 class="font-semibold text-teal-800 mb-2 flex items-center">
            <i class="fas fa-clipboard-check mr-2"></i>
            Common Issues:
        </h3>
        <ul class="text-sm text-teal-700 space-y-1 pl-5">
            <li>• Required fields are missing</li>
            <li>• Invalid data format</li>
            <li>• File upload errors</li>
            <li>• Data type mismatches</li>
        </ul>
    </div>

    <div class="space-y-4">
        <button onclick="history.back()"
                class="w-full bg-teal-500 hover:bg-teal-600 text-white font-semibold py-3 px-4 rounded-lg transition duration-200 flex items-center justify-center">
            <i class="fas fa-arrow-left mr-2"></i>
            Go Back & Fix Errors
        </button>

        <div class="flex space-x-3">
            <a href="{{ url('/') }}"
               class="flex-1 bg-gray-100 hover:bg-gray-200 text-gray-800 font-medium py-2 px-4 rounded-lg transition duration-200 text-center">
                <i class="fas fa-home mr-2"></i>
                Home
            </a>

            <a href="{{ url('/help/validation') }}"
               class="flex-1 bg-blue-100 hover:bg-blue-200 text-blue-800 font-medium py-2 px-4 rounded-lg transition duration-200 text-center">
                <i class="fas fa-question-circle mr-2"></i>
                Validation Help
            </a>

            <a href="mailto:support@example.com"
               class="flex-1 bg-green-100 hover:bg-green-200 text-green-800 font-medium py-2 px-4 rounded-lg transition duration-200 text-center">
                <i class="fas fa-envelope mr-2"></i>
                Contact
            </a>
        </div>
    </div>
</div>
</body>
</html>
