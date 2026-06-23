<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Page Not Found - {{ config('app.name') }}</title>
    <link href="https://cdn.jsdelivr.net/npm/tailwindcss@2.2.19/dist/tailwind.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
</head>
<body class="bg-gradient-to-br from-purple-50 to-indigo-100 min-h-screen flex items-center justify-center p-4">
<div class="max-w-lg w-full bg-white rounded-xl shadow-xl p-8 md:p-10">
    <div class="text-center">
        <div class="inline-block bg-purple-100 rounded-full p-4 mb-6">
            <i class="fas fa-map-signs text-purple-500 text-5xl"></i>
        </div>
        <h1 class="text-3xl font-bold text-gray-800 mb-2">404 - Page Not Found</h1>
        <p class="text-gray-600 mb-6">The page you're looking for doesn't exist or has been moved.</p>
    </div>

    <div class="bg-purple-50 border border-purple-200 rounded-lg p-4 mb-8">
        <h3 class="font-semibold text-purple-800 mb-2 flex items-center">
            <i class="fas fa-search mr-2"></i>
            Did you mean to visit:
        </h3>
        <div class="space-y-2">
            <a href="{{ url('/') }}" class="block text-purple-600 hover:text-purple-800 hover:underline">Homepage</a>
            <a href="{{ url('/help') }}" class="block text-purple-600 hover:text-purple-800 hover:underline">Help Center</a>
            <a href="{{ url('/contact') }}" class="block text-purple-600 hover:text-purple-800 hover:underline">Contact Page</a>
        </div>
    </div>

    <div class="space-y-4">
        <div class="relative">
            <input type="text"
                   id="search-input"
                   placeholder="Search for content..."
                   class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-purple-500 focus:border-transparent">
            <button onclick="performSearch()"
                    class="absolute right-2 top-2 bg-purple-500 hover:bg-purple-600 text-white p-2 rounded-lg">
                <i class="fas fa-search"></i>
            </button>
        </div>

        <div class="flex space-x-3">
            <a href="{{ url('/') }}"
               class="flex-1 bg-purple-500 hover:bg-purple-600 text-white font-medium py-2 px-4 rounded-lg transition duration-200 text-center">
                <i class="fas fa-home mr-2"></i>
                Home
            </a>

            <a href="{{ url('/help') }}"
               class="flex-1 bg-blue-100 hover:bg-blue-200 text-blue-800 font-medium py-2 px-4 rounded-lg transition duration-200 text-center">
                <i class="fas fa-question-circle mr-2"></i>
                Help
            </a>

            <a href="mailto:support@example.com"
               class="flex-1 bg-green-100 hover:bg-green-200 text-green-800 font-medium py-2 px-4 rounded-lg transition duration-200 text-center">
                <i class="fas fa-envelope mr-2"></i>
                Contact
            </a>
        </div>
    </div>
</div>

<script>
    function performSearch() {
        const query = document.getElementById('search-input').value;
        if (query) {
            window.location.href = '/search?q=' + encodeURIComponent(query);
        }
    }

    // Auto-focus search input
    document.addEventListener('DOMContentLoaded', function() {
        document.getElementById('search-input').focus();
    });
</script>
</body>
</html>
