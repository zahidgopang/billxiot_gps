<x-guest-layout>
    @if (session('session_expired'))
        <div class="mb-4 font-medium text-sm text-amber-600">
            {{ session('session_expired') }}
        </div>
    @endif

    <!-- Session Status -->
    <x-auth-session-status class="mb-4" :status="session('status')" />
    <div id="jsSessionExpiredMsg" class="mb-4 font-medium text-sm text-amber-600 hidden"></div>

    <div class="premium-login-container">
        <!-- Premium Header with Custom Logo -->
        <div class="premium-login-header">
            <h1 class="premium-title">Welcome Back</h1>
            <p class="premium-subtitle">Sign in to your account to continue</p>
        </div>

        <form method="POST" action="{{ route('login') }}" class="premium-login-form">
            @csrf

            <!-- Email Address -->
            <div class="form-group premium-input-group">
                <x-input-label for="email" :value="__('Email')" class="premium-label" />
                <div class="input-with-icon">
                    <svg class="input-icon" xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <path d="M4 4h16c1.1 0 2 .9 2 2v12c0 1.1-.9 2-2 2H4c-1.1 0-2-.9-2-2V6c0-1.1.9-2 2-2z"></path>
                        <polyline points="22,6 12,13 2,6"></polyline>
                    </svg>
                    <x-text-input id="email" class="premium-text-input" type="email" name="email" :value="old('email')" required autofocus autocomplete="username" placeholder="Enter your email address" />
                </div>
                <x-input-error :messages="$errors->get('email')" class="premium-error mt-2" />
            </div>

            <!-- Password -->
            <div class="form-group premium-input-group mt-6">
                <x-input-label for="password" :value="__('Password')" class="premium-label" />
                <div class="input-with-icon">
                    <svg class="input-icon" xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <rect x="3" y="11" width="18" height="11" rx="2" ry="2"></rect>
                        <path d="M7 11V7a5 5 0 0 1 10 0v4"></path>
                    </svg>
                    <x-text-input id="password" class="premium-text-input" type="password" name="password" required autocomplete="current-password" placeholder="Enter your password" />
                </div>
                <x-input-error :messages="$errors->get('password')" class="premium-error mt-2" />
            </div>

            <!-- Remember Me & Forgot Password -->
            <div class="form-options">
                <div class="remember-me">
                    <label for="remember_me" class="premium-checkbox-label">
                        <input id="remember_me" type="checkbox" class="premium-checkbox" name="remember" value="1" @checked(old('remember', true))>
                        <span class="checkmark"></span>
                        <span class="checkbox-text">{{ __('app.auth.keep_signed_in') }}</span>
                    </label>
                </div>

                @if (Route::has('password.request'))
                    <a class="premium-forgot-link" href="{{ route('password.request') }}">
                        {{ __('Forgot your password?') }}
                    </a>
                @endif
            </div>

            <!-- Submit Button -->
            <div class="form-submit">
                <x-primary-button class="premium-login-button">
                    {{ __('Log in') }}

                </x-primary-button>
            </div>

            <!-- Optional: Social Login or Sign Up Link -->
            <div class="premium-form-footer">
                <p class="footer-text">
                    Don't have an account?
                    @if (Route::has('register'))
                        <a href="{{ route('register') }}" class="footer-link">Sign up</a>
                    @endif
                </p>
            </div>
        </form>
    </div>

    <script>
        document.addEventListener('DOMContentLoaded', function () {
            try {
                var msg = sessionStorage.getItem('login_flash');
                if (!msg) return;
                sessionStorage.removeItem('login_flash');
                var el = document.getElementById('jsSessionExpiredMsg');
                if (!el) return;
                el.textContent = msg;
                el.classList.remove('hidden');
            } catch (e) {}
        });
    </script>
</x-guest-layout>
