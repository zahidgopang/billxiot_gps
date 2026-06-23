<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">

    <title>Verify Email - {{ config('app.name') }}</title>

    <!-- Tailwind CSS -->
    <link href="https://cdn.jsdelivr.net/npm/tailwindcss@2.2.19/dist/tailwind.min.css" rel="stylesheet">
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
</head>

<body class="bg-gray-50 min-h-screen flex items-center justify-center p-4">

<div class="max-w-md w-full bg-white rounded-lg shadow-lg p-8">

    <!-- Header -->
    <div class="text-center mb-8">
        <div class="w-16 h-16 bg-blue-100 rounded-full flex items-center justify-center mx-auto mb-4">
            <i class="fas fa-envelope text-blue-500 text-2xl"></i>
        </div>

        <h1 class="text-2xl font-bold text-gray-900">
            Verify Your Email Address
        </h1>

        <p class="text-gray-600 mt-2">
            Please verify your email to continue
        </p>
    </div>

    <!-- Email Info -->
    <div class="bg-blue-50 border border-blue-200 rounded-lg p-4 mb-6">
        <div class="flex items-start">
            <i class="fas fa-envelope text-blue-500 mt-1 mr-3"></i>
            <div>
                <p class="font-medium text-gray-800">
                    Verification email sent to:
                </p>
                <p class="text-blue-600 font-semibold break-all">
                    {{ Auth::user()->email }}
                </p>
            </div>
        </div>

        @if (! Auth::user()->hasVerifiedEmail())
            <div class="mt-3 pt-3 border-t border-blue-200 text-sm text-gray-600">
                <i class="far fa-clock mr-2"></i>
                Waiting for verification
            </div>
        @endif
    </div>

    <!-- SUCCESS MESSAGE (Breeze resend) -->
    @if (session('status') === 'verification-link-sent')
        <div class="bg-green-50 border border-green-200 rounded-lg p-4 mb-6">
            <div class="flex items-center">
                <i class="fas fa-check-circle text-green-500 mr-3"></i>
                <div>
                    <p class="font-medium text-green-800">
                        A new verification link has been sent to your email.
                    </p>
                    <p class="text-green-600 text-sm mt-1">
                        Please check your inbox or spam folder.
                    </p>
                </div>
            </div>
        </div>
    @endif

    <!-- VERIFIED BADGE -->
    @if (Auth::user()->hasVerifiedEmail())
        <div class="bg-green-100 border border-green-300 rounded-lg p-4 mb-6 text-center">
            <i class="fas fa-badge-check text-green-600 text-2xl mb-2"></i>
            <p class="font-semibold text-green-800">
                Email Verified Successfully
            </p>
            <p class="text-green-700 text-sm">
                Redirecting you to your dashboard…
            </p>
        </div>
    @endif

    <!-- ERROR MESSAGE -->
    @if (session('error'))
        <div class="bg-red-50 border border-red-200 rounded-lg p-4 mb-6">
            <p class="font-medium text-red-800">
                {{ session('error') }}
            </p>
        </div>
    @endif

    <!-- Instructions -->
    @if (! Auth::user()->hasVerifiedEmail())
        <div class="bg-gray-50 rounded-lg p-4 mb-6">
            <h3 class="font-medium text-gray-800 mb-2 flex items-center">
                <i class="fas fa-info-circle text-blue-500 mr-2"></i>
                What to do next
            </h3>

            <ul class="space-y-2 text-gray-600 text-sm">
                <li class="flex items-start">
                    <i class="fas fa-check-circle text-green-500 mt-1 mr-2"></i>
                    <span>Check your inbox and spam folder</span>
                </li>
                <li class="flex items-start">
                    <i class="fas fa-mouse-pointer text-blue-500 mt-1 mr-2"></i>
                    <span>Click the verification link in the email</span>
                </li>
                <li class="flex items-start">
                    <i class="fas fa-unlock text-purple-500 mt-1 mr-2"></i>
                    <span>Your account will be unlocked after verification</span>
                </li>
            </ul>
        </div>
    @endif

    <!-- Actions -->
    <div class="space-y-4">

        @if (! Auth::user()->hasVerifiedEmail())
            <!-- Resend Verification -->
            <form method="POST" action="{{ route('verification.send') }}">
                @csrf
                <button
                    type="submit"
                    id="resend-btn"
                    class="w-full bg-blue-500 hover:bg-blue-600 text-white font-medium py-3 px-4 rounded-lg transition flex items-center justify-center disabled:opacity-50 disabled:cursor-not-allowed"
                >
                    <i class="fas fa-paper-plane mr-2"></i>
                    <span id="resend-text">Resend Verification Email</span>
                </button>
            </form>
        @endif

        <!-- Logout -->
        <form method="POST" action="{{ route('logout') }}">
            @csrf
            <button type="submit"
                    class="w-full bg-gray-100 hover:bg-gray-200 text-gray-800 font-medium py-2 px-4 rounded-lg transition flex items-center justify-center">
                <i class="fas fa-sign-out-alt mr-2"></i>
                Logout
            </button>
        </form>
    </div>

    <!-- Footer -->
    <div class="mt-8 pt-6 border-t border-gray-200 text-center text-gray-500 text-sm">
        <p>
            Need help?
            <a href="{{ route('help') }}" class="text-blue-500 hover:text-blue-600">
                Contact Support
            </a>
        </p>
    </div>

</div>

<!-- JS: Cooldown + Auto Redirect -->
<script>
    const COOLDOWN_SECONDS = 60;
    const STORAGE_KEY = 'verification_resend_until';

    const resendBtn = document.getElementById('resend-btn');
    const resendText = document.getElementById('resend-text');

    function startCooldown(remainingSeconds) {
        if (!resendBtn || !resendText) return;

        resendBtn.disabled = true;

        const timer = setInterval(() => {
            resendText.innerText = `Resend available in ${remainingSeconds}s`;
            remainingSeconds--;

            if (remainingSeconds < 0) {
                clearInterval(timer);
                resendBtn.disabled = false;
                resendText.innerText = 'Resend Verification Email';
                localStorage.removeItem(STORAGE_KEY);
            }
        }, 1000);
    }

    // 🔹 CASE 1: Resend just happened (server response)
    @if (session('status') === 'verification-link-sent')
    const resendUntil = Date.now() + (COOLDOWN_SECONDS * 1000);
    localStorage.setItem(STORAGE_KEY, resendUntil);
    startCooldown(COOLDOWN_SECONDS);
    @endif

    // 🔹 CASE 2: Page refreshed during cooldown
    const storedUntil = localStorage.getItem(STORAGE_KEY);
    if (storedUntil) {
        const remaining = Math.ceil((storedUntil - Date.now()) / 1000);
        if (remaining > 0) {
            startCooldown(remaining);
        } else {
            localStorage.removeItem(STORAGE_KEY);
        }
    }

    // 🔹 Auto-redirect after verification
    @if (Auth::user()->hasVerifiedEmail())
    setTimeout(() => {
        window.location.href = "{{ route('dashboard') }}";
    }, 2000);
    @endif
</script>


</body>
</html>
