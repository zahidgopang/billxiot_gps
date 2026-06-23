<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Service Unavailable - {{ config('app.name') }}</title>
    <link href="https://cdn.jsdelivr.net/npm/tailwindcss@2.2.19/dist/tailwind.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        .maintenance-icon {
            animation: bounce 2s infinite;
        }
        @keyframes bounce {
            0%, 100% { transform: translateY(0); }
            50% { transform: translateY(-10px); }
        }
    </style>
</head>
<body class="bg-gradient-to-br from-indigo-50 to-purple-100 min-h-screen flex items-center justify-center p-4">
<div class="max-w-lg w-full bg-white rounded-xl shadow-xl p-8 md:p-10">
    <div class="text-center">
        <div class="inline-block bg-indigo-100 rounded-full p-4 mb-6 maintenance-icon">
            <i class="fas fa-tools text-indigo-500 text-5xl"></i>
        </div>
        <h1 class="text-3xl font-bold text-gray-800 mb-2">503 - Service Unavailable</h1>
        <p class="text-gray-600 mb-6">We're currently down for maintenance. We'll be back shortly!</p>
    </div>

    <div class="bg-indigo-50 border border-indigo-200 rounded-lg p-4 mb-8">
        <h3 class="font-semibold text-indigo-800 mb-2 flex items-center">
            <i class="fas fa-clock mr-2"></i>
            Maintenance Schedule:
        </h3>
        <div class="text-sm text-indigo-700 space-y-1">
            <div class="flex justify-between">
                <span>Start Time:</span>
                <span class="font-semibold">{{ now()->format('h:i A') }}</span>
            </div>
            <div class="flex justify-between">
                <span>Expected Completion:</span>
                <span class="font-semibold">{{ now()->addHours(2)->format('h:i A') }}</span>
            </div>
            <div class="flex justify-between">
                <span>Duration:</span>
                <span class="font-semibold">~2 hours</span>
            </div>
        </div>
    </div>

    <div class="space-y-4">
        <div class="flex space-x-3">
            <a href="{{ url('/') }}"
               class="flex-1 bg-indigo-500 hover:bg-indigo-600 text-white font-medium py-3 px-4 rounded-lg transition duration-200 text-center">
                <i class="fas fa-home mr-2"></i>
                Home
            </a>

            <button onclick="location.reload()"
                    class="flex-1 bg-gray-500 hover:bg-gray-600 text-white font-medium py-3 px-4 rounded-lg transition duration-200 text-center">
                <i class="fas fa-redo mr-2"></i>
                Refresh
            </button>
        </div>

        <div class="grid grid-cols-2 gap-3">
            <a href="{{ url('/status') }}"
               class="bg-blue-100 hover:bg-blue-200 text-blue-800 font-medium py-2 px-4 rounded-lg transition duration-200 text-center">
                <i class="fas fa-chart-line mr-2"></i>
                Status
            </a>

            <a href="{{ url('/help') }}"
               class="bg-green-100 hover:bg-green-200 text-green-800 font-medium py-2 px-4 rounded-lg transition duration-200 text-center">
                <i class="fas fa-question-circle mr-2"></i>
                Help
            </a>

            <a href="https://twitter.com/{{ config('app.name') }}"
               target="_blank"
               class="bg-sky-100 hover:bg-sky-200 text-sky-800 font-medium py-2 px-4 rounded-lg transition duration-200 text-center">
                <i class="fab fa-twitter mr-2"></i>
                Twitter
            </a>

            <a href="mailto:support@example.com"
               class="bg-red-100 hover:bg-red-200 text-red-800 font-medium py-2 px-4 rounded-lg transition duration-200 text-center">
                <i class="fas fa-envelope mr-2"></i>
                Contact
            </a>
        </div>

        <!-- Auto-refresh timer -->
        <div class="mt-6 pt-4 border-t border-gray-200 text-center">
            <p class="text-sm text-gray-600">
                <i class="fas fa-sync-alt mr-2"></i>
                Auto-refreshing in <span id="countdown">60</span> seconds
            </p>
        </div>
    </div>
</div>

<script>
    let seconds = 60;
    const countdownElement = document.getElementById('countdown');

    function updateCountdown() {
        countdownElement.textContent = seconds;
        if (seconds > 0) {
            seconds--;
            setTimeout(updateCountdown, 1000);
        } else {
            location.reload();
        }
    }

    updateCountdown();
</script>
</body>
</html>
