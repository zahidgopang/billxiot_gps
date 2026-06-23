<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Bad Request - {{ config('app.name') }}</title>
    <link href="https://cdn.jsdelivr.net/npm/tailwindcss@2.2.19/dist/tailwind.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
</head>
<body class="bg-gradient-to-br from-blue-50 to-indigo-100 min-h-screen flex items-center justify-center p-4">
<div class="max-w-lg w-full bg-white rounded-xl shadow-xl p-8 md:p-10">
    <div class="text-center">
        <div class="inline-block bg-red-100 rounded-full p-4 mb-6">
            <i class="fas fa-exclamation-circle text-red-500 text-5xl"></i>
        </div>
        <h1 class="text-3xl font-bold text-gray-800 mb-2">400 - Bad Request</h1>
        <p class="text-gray-600 mb-6">The server cannot process your request due to invalid syntax.</p>
    </div>

    <div class="bg-blue-50 border border-blue-200 rounded-lg p-4 mb-8">
        <h3 class="font-semibold text-blue-800 mb-2 flex items-center">
            <i class="fas fa-lightbulb mr-2"></i>
            Possible Causes:
        </h3>
        <ul class="text-sm text-blue-700 space-y-1 pl-5">
            <li>• Malformed request syntax</li>
            <li>• Invalid request message framing</li>
            <li>• Deceptive request routing</li>
        </ul>
    </div>

    <div class="space-y-4">
        <a href="{{ url('/') }}"
           class="block w-full bg-blue-500 hover:bg-blue-600 text-white font-semibold py-3 px-4 rounded-lg transition duration-200 text-center">
            <i class="fas fa-home mr-2"></i>
            Go to Homepage
        </a>

        <div class="flex space-x-3">
            <a href="{{ url('/help') }}"
               class="flex-1 bg-gray-100 hover:bg-gray-200 text-gray-800 font-medium py-2 px-4 rounded-lg transition duration-200 text-center">
                <i class="fas fa-question-circle mr-2"></i>
                Help Center
            </a>

            <a href="mailto:support@example.com"
               class="flex-1 bg-green-100 hover:bg-green-200 text-green-800 font-medium py-2 px-4 rounded-lg transition duration-200 text-center">
                <i class="fas fa-envelope mr-2"></i>
                Contact Support
            </a>
        </div>
    </div>
</div>
</body>
</html>
