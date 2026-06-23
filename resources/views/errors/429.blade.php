<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Too Many Requests - {{ config('app.name') }}</title>
    <link href="https://cdn.jsdelivr.net/npm/tailwindcss@2.2.19/dist/tailwind.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        .bg-gradient-429 {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        }
        .pulse {
            animation: pulse 2s infinite;
        }
        @keyframes pulse {
            0% { transform: scale(1); }
            50% { transform: scale(1.05); }
            100% { transform: scale(1); }
        }
    </style>
</head>
<body class="bg-gradient-429 min-h-screen flex items-center justify-center p-4">
<div class="max-w-2xl w-full bg-white rounded-2xl shadow-2xl overflow-hidden">
    <!-- Header -->
    <div class="bg-gradient-to-r from-red-500 to-orange-500 p-8 text-white text-center">
        <div class="inline-block bg-white/20 rounded-full p-6 mb-4 pulse">
            <i class="fas fa-stopwatch fa-4x"></i>
        </div>
        <h1 class="text-4xl font-bold mb-2">429 - Too Many Requests</h1>
        <p class="text-xl opacity-90">You've been sending too many requests</p>
    </div>

    <!-- Content -->
    <div class="p-8 md:p-12">
        <div class="grid md:grid-cols-2 gap-8">
            <!-- Left Column: Explanation -->
            <div>
                <h2 class="text-2xl font-bold text-gray-800 mb-4 flex items-center">
                    <i class="fas fa-exclamation-triangle text-orange-500 mr-3"></i>
                    What Happened?
                </h2>
                <div class="space-y-4 text-gray-600">
                    <div class="flex items-start">
                        <i class="fas fa-bolt text-yellow-500 mt-1 mr-3"></i>
                        <div>
                            <h3 class="font-semibold text-gray-800">Rate Limit Exceeded</h3>
                            <p class="text-sm">You've made too many requests in a short period of time.</p>
                        </div>
                    </div>

                    <div class="flex items-start">
                        <i class="fas fa-shield-alt text-blue-500 mt-1 mr-3"></i>
                        <div>
                            <h3 class="font-semibold text-gray-800">Security Measure</h3>
                            <p class="text-sm">This is a security feature to protect against abuse.</p>
                        </div>
                    </div>

                    <div class="flex items-start">
                        <i class="fas fa-clock text-purple-500 mt-1 mr-3"></i>
                        <div>
                            <h3 class="font-semibold text-gray-800">Temporary Restriction</h3>
                            <p class="text-sm">This is temporary and will be lifted shortly.</p>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Right Column: Wait Time & Details -->
            <div>
                <h2 class="text-2xl font-bold text-gray-800 mb-4 flex items-center">
                    <i class="fas fa-clock text-red-500 mr-3"></i>
                    Wait Time
                </h2>

                <div class="bg-red-50 border border-red-200 rounded-xl p-6 mb-6 text-center">
                    <div class="text-5xl font-bold text-red-600 mb-2" id="countdown">60</div>
                    <p class="text-red-700 font-medium">seconds remaining</p>
                    <div class="w-full bg-red-200 rounded-full h-2 mt-4">
                        <div id="progress-bar" class="bg-red-600 h-2 rounded-full" style="width: 100%"></div>
                    </div>
                </div>

                <div class="bg-gray-50 rounded-lg p-4">
                    <p class="text-sm text-gray-600">
                        <i class="fas fa-info-circle text-blue-500 mr-2"></i>
                        Limit: <span class="font-semibold">6 requests per minute</span>
                    </p>
                </div>
            </div>
        </div>

        <!-- What You Can Do -->
        <div class="mt-10 pt-8 border-t border-gray-200">
            <h3 class="text-xl font-bold text-gray-800 mb-4 text-center">
                <i class="fas fa-lightbulb text-green-500 mr-2"></i>
                What You Can Do
            </h3>

            <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                <div class="bg-blue-50 border border-blue-100 rounded-lg p-5 text-center hover:bg-blue-100 transition duration-300">
                    <div class="text-blue-500 text-3xl mb-3">
                        <i class="fas fa-coffee"></i>
                    </div>
                    <h4 class="font-bold text-gray-800 mb-2">Wait Patiently</h4>
                    <p class="text-sm text-gray-600">The timer above shows when you can try again.</p>
                </div>

                <div class="bg-green-50 border border-green-100 rounded-lg p-5 text-center hover:bg-green-100 transition duration-300">
                    <div class="text-green-500 text-3xl mb-3">
                        <i class="fas fa-sync-alt"></i>
                    </div>
                    <h4 class="font-bold text-gray-800 mb-2">Slow Down</h4>
                    <p class="text-sm text-gray-600">Take a break between requests next time.</p>
                </div>

                <div class="bg-purple-50 border border-purple-100 rounded-lg p-5 text-center hover:bg-purple-100 transition duration-300">
                    <div class="text-purple-500 text-3xl mb-3">
                        <i class="fas fa-headset"></i>
                    </div>
                    <h4 class="font-bold text-gray-800 mb-2">Need Help?</h4>
                    <p class="text-sm text-gray-600">Contact support if this happens frequently.</p>
                </div>
            </div>
        </div>

        <!-- Action Buttons -->
        <div class="mt-10 flex flex-col sm:flex-row gap-4 justify-center">

            <a href="{{ url('email/verify') }}"
               class="px-6 py-3 bg-gradient-to-r from-blue-500 to-blue-600 text-white font-semibold rounded-lg hover:from-blue-600 hover:to-blue-700 transition duration-300 flex items-center justify-center">
                <i class="fas fa-redo mr-2"></i>
                Try Again
            </a>

            <a href="{{ url('/') }}"
               class="px-6 py-3 bg-gradient-to-r from-gray-500 to-gray-600 text-white font-semibold rounded-lg hover:from-gray-600 hover:to-gray-700 transition duration-300 flex items-center justify-center">
                <i class="fas fa-home mr-2"></i>
                Go Home
            </a>

            <a href="{{route('help')}}"
               class="px-6 py-3 bg-gradient-to-r from-green-500 to-green-600 text-white font-semibold rounded-lg hover:from-green-600 hover:to-green-700 transition duration-300 flex items-center justify-center">
                <i class="fas fa-envelope mr-2"></i>
                Contact Support
            </a>
        </div>

        <!-- Auto-retry Notice -->
        <div class="mt-8 p-4 bg-yellow-50 border border-yellow-200 rounded-lg text-center">
            <p class="text-yellow-800 text-sm">
                <i class="fas fa-robot mr-2"></i>
                This page will automatically refresh when the wait time is over
            </p>
        </div>
    </div>

    <!-- Footer -->
    <div class="bg-gray-50 px-8 py-4 border-t border-gray-200 text-center text-gray-500 text-sm">
        <p>&copy; {{ date('Y') }} {{ config('app.name') }}. All rights reserved.</p>
    </div>
</div>

<!-- Countdown Timer Script -->
<script>
    let timeLeft = 60; // 60 seconds = 1 minute
    const countdownElement = document.getElementById('countdown');
    const progressBar = document.getElementById('progress-bar');

    function updateCountdown() {
        countdownElement.textContent = timeLeft;
        const progressPercentage = (timeLeft / 60) * 100;
        progressBar.style.width = `${progressPercentage}%`;

        if (timeLeft > 0) {
            timeLeft--;
            setTimeout(updateCountdown, 1000);
        } else {
            // When countdown reaches 0, show retry option
            countdownElement.textContent = '0';
            progressBar.style.width = '0%';

            // Change message
            const waitTimeDiv = document.querySelector('.bg-red-50 p.text-red-700');
            if (waitTimeDiv) {
                waitTimeDiv.textContent = 'You can now try again!';
                waitTimeDiv.classList.remove('text-red-700');
                waitTimeDiv.classList.add('text-green-600');
            }

            // Enable auto-refresh after 3 seconds
            setTimeout(() => {
                location.reload();
            }, 3000);
        }
    }

    // Start countdown when page loads
    document.addEventListener('DOMContentLoaded', updateCountdown);

    // Add some visual effects
    document.querySelectorAll('.hover\\:bg-blue-100').forEach(element => {
        element.addEventListener('mouseenter', function() {
            this.style.transform = 'translateY(-5px)';
        });
        element.addEventListener('mouseleave', function() {
            this.style.transform = 'translateY(0)';
        });
    });
</script>
</body>
</html>
