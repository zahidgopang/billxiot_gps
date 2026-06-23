<x-guest-layout>
    <!-- Session Status -->
    <x-auth-session-status class="mb-4" :status="session('status')" />

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

    <style>
        /* Premium Login Form Styling */
        .premium-login-container {
            max-width: 440px;
            margin: 0 auto;
            padding: 1.5rem;
            /*background: linear-gradient(135deg, #ffffff 0%, #f8fafc 100%);
            border-radius: 20px;
            box-shadow:
                0 20px 40px rgba(0, 0, 0, 0.05),
                0 10px 20px rgba(0, 0, 0, 0.03),
                inset 0 1px 0 rgba(255, 255, 255, 0.8);
            border: 1px solid rgba(255, 255, 255, 0.9);*/
            position: relative;
            overflow: hidden;
        }

        /* Subtle background pattern */
        .premium-login-container::before {
            content: "";
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 4px;
            background: linear-gradient(90deg, #667eea 0%, #764ba2 100%);
            border-radius: 20px 20px 0 0;
        }

        .premium-login-header {
            text-align: center;
            margin-bottom: 2.5rem;
        }

        .logo-container {
            margin-bottom: 1.5rem;
            display: flex;
            justify-content: center;
        }

        .premium-logo {
            height: 70px;
            width: auto;
            filter: drop-shadow(0 4px 6px rgba(0, 0, 0, 0.1));
        }

        .premium-title {
            font-size: 2rem;
            font-weight: 700;
            color: #1a202c;
            margin-bottom: 0.5rem;
            background: linear-gradient(90deg, #667eea 0%, #764ba2 100%);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
        }

        .premium-subtitle {
            color: #718096;
            font-size: 1rem;
            margin-bottom: 0;
        }

        .premium-login-form {
            width: 100%;
        }

        .premium-input-group {
            margin-bottom: 1.5rem;
        }

        .premium-label {
            display: block;
            font-weight: 600;
            color: #4a5568;
            margin-bottom: 0.5rem;
            font-size: 0.95rem;
            letter-spacing: 0.3px;
        }

        .input-with-icon {
            position: relative;
        }

        .input-icon {
            position: absolute;
            left: 16px;
            top: 50%;
            transform: translateY(-50%);
            color: #a0aec0;
            z-index: 10;
        }

        .premium-text-input {
            width: 100%;
            padding: 14px 16px 14px 48px;
            border: 1px solid #e2e8f0;
            border-radius: 12px;
            background-color: #ffffff;
            font-size: 1rem;
            color: #2d3748;
            transition: all 0.2s ease;
            box-shadow: 0 1px 2px rgba(0, 0, 0, 0.05);
        }

        .premium-text-input:focus {
            border-color: #667eea;
            box-shadow: 0 0 0 3px rgba(102, 126, 234, 0.1);
            outline: none;
        }

        .premium-text-input::placeholder {
            color: #a0aec0;
        }

        .premium-error {
            color: #e53e3e;
            font-size: 0.875rem;
            margin-top: 0.5rem;
        }

        .form-options {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin: 1.5rem 0 2rem;
        }

        .premium-checkbox-label {
            display: flex;
            align-items: center;
            cursor: pointer;
            position: relative;
            user-select: none;
        }

        .premium-checkbox {
            position: absolute;
            opacity: 0;
            cursor: pointer;
            height: 0;
            width: 0;
        }

        .checkmark {
            height: 20px;
            width: 20px;
            background-color: #ffffff;
            border: 2px solid #cbd5e0;
            border-radius: 6px;
            margin-right: 10px;
            transition: all 0.2s ease;
            position: relative;
        }

        .premium-checkbox:checked ~ .checkmark {
            background-color: #667eea;
            border-color: #667eea;
        }

        .checkmark:after {
            content: "";
            position: absolute;
            display: none;
            left: 6px;
            top: 2px;
            width: 5px;
            height: 10px;
            border: solid white;
            border-width: 0 2px 2px 0;
            transform: rotate(45deg);
        }

        .premium-checkbox:checked ~ .checkmark:after {
            display: block;
        }

        .checkbox-text {
            color: #4a5568;
            font-size: 0.95rem;
        }

        .premium-forgot-link {
            color: #667eea;
            font-size: 0.95rem;
            font-weight: 500;
            text-decoration: none;
            transition: color 0.2s ease;
        }

        .premium-forgot-link:hover {
            color: #764ba2;
            text-decoration: underline;
        }

        .form-submit {
            margin-top: 1.5rem;
        }

        .premium-login-button {
            width: 100%;
            padding: 16px;
            background: linear-gradient(90deg, #667eea 0%, #764ba2 100%);
            color: white;
            border: none;
            border-radius: 12px;
            font-weight: 600;
            font-size: 1rem;
            cursor: pointer;
            transition: all 0.3s ease;
            display: flex;
            justify-content: center;
            align-items: center;
            box-shadow: 0 4px 15px rgba(102, 126, 234, 0.3);
        }

        .premium-login-button:hover {
            transform: translateY(-2px);
            box-shadow: 0 6px 20px rgba(102, 126, 234, 0.4);
        }

        .premium-login-button:active {
            transform: translateY(0);
        }

        .button-icon {
            margin-left: 10px;
            transition: transform 0.2s ease;
        }

        .premium-login-button:hover .button-icon {
            transform: translateX(4px);
        }

        .premium-form-footer {
            text-align: center;
            margin-top: 2rem;
            padding-top: 1.5rem;
            border-top: 1px solid #e2e8f0;
        }

        .footer-text {
            color: #718096;
            font-size: 0.95rem;
        }

        .footer-link {
            color: #667eea;
            font-weight: 600;
            text-decoration: none;
            margin-left: 5px;
            transition: color 0.2s ease;
        }

        .footer-link:hover {
            color: #764ba2;
            text-decoration: underline;
        }

        /* Responsive adjustments */
        @media (max-width: 640px) {
            .premium-login-container {
                padding: 2rem 1.5rem;
                margin: 1rem;
            }

            .premium-title {
                font-size: 1.75rem;
            }

            .form-options {
                flex-direction: column;
                align-items: flex-start;
                gap: 1rem;
            }

            .premium-forgot-link {
                align-self: flex-start;
            }
        }
    </style>
</x-guest-layout>
