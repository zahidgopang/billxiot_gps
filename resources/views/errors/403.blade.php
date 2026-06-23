<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Forbidden - {{ config('app.name') }}</title>
    <link href="https://cdn.jsdelivr.net/npm/tailwindcss@2.2.19/dist/tailwind.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
</head>
<body class="bg-gradient-to-br from-red-50 to-pink-100 min-h-screen flex items-center justify-center p-4">
<div class="max-w-lg w-full bg-white rounded-xl shadow-xl p-8 md:p-10">
    <div class="text-center">
        <div class="inline-block bg-red-100 rounded-full p-4 mb-6">
            <i class="fas fa-ban text-red-500 text-5xl"></i>
        </div>
        <h1 class="text-3xl font-bold text-gray-800 mb-2">403 - Forbidden</h1>
        <p class="text-gray-600 mb-6">You don't have permission to access this resource.</p>
    </div>

    <div class="bg-red-50 border border-red-200 rounded-lg p-4 mb-8">
        <h3 class="font-semibold text-red-800 mb-2 flex items-center">
            <i class="fas fa-shield-alt mr-2"></i>
            Access Denied:
        </h3>
        <ul class="text-sm text-red-700 space-y-1 pl-5">
            <li>• Insufficient permissions</li>
            <li>• IP restriction</li>
            <li>• Geographical restriction</li>
        </ul>
    </div>

    <div class="space-y-4">
        <a href="{{ url('/') }}"
           class="block w-full bg-red-500 hover:bg-red-600 text-white font-semibold py-3 px-4 rounded-lg transition duration-200 text-center">
            <i class="fas fa-home mr-2"></i>
            Return to Homepage
        </a>

        <div class="flex space-x-3">
            <a href="{{ url('/help/permissions') }}"
               class="flex-1 bg-blue-100 hover:bg-blue-200 text-blue-800 font-medium py-2 px-4 rounded-lg transition duration-200 text-center">
                <i class="fas fa-question-circle mr-2"></i>
                Permission Help
            </a>

            <a href="{{ route('login') }}"
               class="flex-1 bg-gray-100 hover:bg-gray-200 text-gray-800 font-medium py-2 px-4 rounded-lg transition duration-200 text-center">
                <i class="fas fa-user mr-2"></i>
                Try Different Account
            </a>

            <a href="mailto:support@example.com"
               class="flex-1 bg-green-100 hover:bg-green-200 text-green-800 font-medium py-2 px-4 rounded-lg transition duration-200 text-center">
                <i class="fas fa-envelope mr-2"></i>
                Request Access
            </a>
        </div>
    </div>
</div>
</body>
</html>
