<x-guest-layout>
    <div class="premium-register-container">
        <!-- Premium Header with Custom Logo -->
        <div class="premium-register-header">
            <h1 class="premium-title">Create Account</h1>
            <p class="premium-subtitle">Join our community and get started</p>
        </div>

        <!-- Toast Container -->
        <div id="toast-container" class="toast-container"></div>

        <form method="POST" action="{{ route('register') }}" class="premium-register-form">
            @csrf

            <!-- Name -->
            <div class="form-group premium-input-group">
                <x-input-label for="name" :value="__('Full Name')" class="premium-label" />
                <div class="input-with-icon">
                    <svg class="input-icon" xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"></path>
                        <circle cx="12" cy="7" r="4"></circle>
                    </svg>
                    <x-text-input id="name" class="premium-text-input" type="text" name="name" :value="old('name')" required autofocus autocomplete="name" placeholder="Enter your full name" />
                </div>
                <div id="name-error" class="premium-error mt-2"></div>
            </div>

            <!-- Email Address -->
            <div class="form-group premium-input-group mt-6">
                <x-input-label for="email" :value="__('Email Address')" class="premium-label" />
                <div class="input-with-icon">
                    <svg class="input-icon" xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <path d="M4 4h16c1.1 0 2 .9 2 2v12c0 1.1-.9 2-2 2H4c-1.1 0-2-.9-2-2V6c0-1.1.9-2 2-2z"></path>
                        <polyline points="22,6 12,13 2,6"></polyline>
                    </svg>
                    <x-text-input id="email" class="premium-text-input" type="email" name="email" :value="old('email')" required autocomplete="username" placeholder="Enter your email address" />
                </div>
                <div id="email-error" class="premium-error mt-2"></div>
                <!-- Email suggestion for already registered emails -->
                <div id="email-suggestion" class="email-suggestion mt-2" style="display: none;">
                    <p style="font-size: 0.875rem; color: #718096;">
                        This email is already registered.
                        <a href="{{ route('login') }}" class="terms-link">Login here</a> or
                        <a href="{{ route('password.request') }}" class="terms-link">reset your password</a>.
                    </p>
                </div>
            </div>

            <!-- Phone Number with SEPARATE country code -->
            <div class="form-group premium-input-group mt-6">
                <x-input-label for="phone" :value="__('Phone Number')" class="premium-label" />
                <div class="phone-input-container">
                    <!-- Country Code Selector -->
                    <div class="country-code-selector" id="countryCodeSelector">
                        <div class="selected-country">
                            <span class="country-flag" id="selectedFlag">🇺🇸</span>
                            <span class="country-code" id="selectedCode">+1</span>
                            <svg class="dropdown-arrow" xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                <polyline points="6 9 12 15 18 9"></polyline>
                            </svg>
                        </div>
                        <div class="country-dropdown" id="countryDropdown">
                            <div class="country-search">
                                <svg class="search-icon" xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                    <circle cx="11" cy="11" r="8"></circle>
                                    <line x1="21" y1="21" x2="16.65" y2="16.65"></line>
                                </svg>
                                <input type="text" id="countrySearch" placeholder="Search country..." class="country-search-input">
                            </div>
                            <div class="country-list" id="countryList">
                                <!-- Country options will be populated by JavaScript -->
                            </div>
                        </div>
                    </div>
                    <input type="hidden" name="country_code" id="countryCode" value="+1">

                    <!-- Phone Number Input -->
                    <div class="phone-number-wrapper">
                        <input id="phone"
                               class="premium-text-input phone-number-input"
                               type="tel"
                               name="phone"
                               :value="old('phone')"
                               required
                               autocomplete="tel"
                               placeholder="Phone number" />
                    </div>
                </div>
                <div class="phone-validation-message" id="phoneValidation"></div>
                <div id="phone-error" class="premium-error mt-2"></div>
            </div>

            <!-- Password -->
            <div class="form-group premium-input-group mt-6">
                <x-input-label for="password" :value="__('Password')" class="premium-label" />
                <div class="input-with-icon password-field">
                    <svg class="input-icon" xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <rect x="3" y="11" width="18" height="11" rx="2" ry="2"></rect>
                        <path d="M7 11V7a5 5 0 0 1 10 0v4"></path>
                    </svg>
                    <x-text-input id="password" class="premium-text-input" type="password" name="password" required autocomplete="new-password" placeholder="Create a strong password" />
                    <button type="button" class="password-toggle" id="togglePassword">
                        <svg class="eye-icon" xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                            <path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"></path>
                            <circle cx="12" cy="12" r="3"></circle>
                        </svg>
                    </button>
                </div>
                <div class="password-strength" id="passwordStrength">
                    <div class="strength-bar"></div>
                    <div class="strength-bar"></div>
                    <div class="strength-bar"></div>
                    <div class="strength-bar"></div>
                    <span class="strength-text" id="strengthText">Password strength</span>
                </div>
                <div class="password-hints">
                    <p class="hint-text">Use at least 8 characters with a mix of letters, numbers & symbols</p>
                </div>
                <div id="password-error" class="premium-error mt-2"></div>
            </div>

            <!-- Confirm Password -->
            <div class="form-group premium-input-group mt-6">
                <x-input-label for="password_confirmation" :value="__('Confirm Password')" class="premium-label" />
                <div class="input-with-icon password-field">
                    <svg class="input-icon" xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <rect x="3" y="11" width="18" height="11" rx="2" ry="2"></rect>
                        <path d="M7 11V7a5 5 0 0 1 10 0v4"></path>
                    </svg>
                    <x-text-input id="password_confirmation" class="premium-text-input" type="password" name="password_confirmation" required autocomplete="new-password" placeholder="Confirm your password" />
                    <button type="button" class="password-toggle" id="toggleConfirmPassword">
                        <svg class="eye-icon" xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                            <path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"></path>
                            <circle cx="12" cy="12" r="3"></circle>
                        </svg>
                    </button>
                </div>
                <div class="password-match" id="passwordMatch">
                    <svg class="match-icon hidden" xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3" stroke-linecap="round" stroke-linejoin="round">
                        <polyline points="20 6 9 17 4 12"></polyline>
                    </svg>
                    <span class="match-text">Passwords must match</span>
                </div>
                <div id="password_confirmation-error" class="premium-error mt-2"></div>
            </div>

            <!-- Terms and Conditions -->
            <div class="terms-container mt-6">
                <label for="terms" class="premium-checkbox-label">
                    <input id="terms" type="checkbox" class="premium-checkbox" name="terms" required>
                    <span class="checkmark"></span>
                    <span class="checkbox-text">
                        I agree to the
                        <a href="{{route('terms')}}" class="terms-link">Terms of Service</a>
                        and
                        <a href="{{route('privacy')}}" class="terms-link">Privacy Policy</a>
                    </span>
                </label>
                <div id="terms-error" class="premium-error mt-2"></div>
            </div>

            <!-- Google reCAPTCHA v3 -->
            <div class="mt-6">
                @include('partials.bot-shield-fields', [
                    'includeRecaptchaInput' => false,
                    'includeRecaptchaNotice' => false,
                    'recaptchaEnabled' => false,
                ])
                <input type="hidden" name="g-recaptcha-response" id="g-recaptcha-response" value="">
                <div id="g-recaptcha-response-error" class="premium-error mt-2 recaptcha-error">
                    @error('g-recaptcha-response')
                        <span class="show">{{ $message }}</span>
                    @enderror
                </div>
                @if($recaptchaEnabled ?? false)
                    <p class="text-xs text-gray-500">This site is protected by reCAPTCHA and the Google
                        <a href="https://policies.google.com/privacy" class="terms-link" target="_blank">Privacy Policy</a> and
                        <a href="https://policies.google.com/terms" class="terms-link" target="_blank">Terms of Service</a> apply.
                    </p>
                @else
                    <p class="text-xs text-amber-600 dark:text-amber-400">Security check (reCAPTCHA) is not configured on this server. Registration works in dev mode only.</p>
                @endif
            </div>

            <!-- Submit Button -->
            <div class="form-submit mt-8">
                <x-primary-button class="premium-register-button" id="registerButton">
                    {{ __('Create Account') }}
                    <svg class="button-icon" xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <path d="M16 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"></path>
                        <circle cx="8.5" cy="7" r="4"></circle>
                        <line x1="20" y1="8" x2="20" y2="14"></line>
                        <line x1="23" y1="11" x2="17" y2="11"></line>
                    </svg>
                </x-primary-button>
            </div>

            <!-- Login Link -->
            <div class="premium-form-footer">
                <p class="footer-text">
                    Already have an account?
                    <a href="{{ route('login') }}" class="footer-link">Sign in</a>
                </p>
            </div>
        </form>
    </div>

    <style>

        /* Add toast notification styles */
        .toast-container {
            position: fixed;
            top: 20px;
            right: 20px;
            z-index: 9999;
            display: flex;
            flex-direction: column;
            gap: 10px;
            max-width: 400px;
        }

        .toast {
            display: flex;
            align-items: center;
            padding: 16px 20px;
            background: white;
            border-radius: 12px;
            box-shadow: 0 10px 25px rgba(0, 0, 0, 0.1);
            border-left: 4px solid;
            animation: slideIn 0.3s ease, fadeOut 0.3s ease 4.7s forwards;
            transform: translateX(120%);
            opacity: 0;
            transition: all 0.3s ease;
            max-width: 400px;
        }

        .toast.show {
            transform: translateX(0);
            opacity: 1;
        }

        .toast.hide {
            transform: translateX(120%);
            opacity: 0;
        }

        .toast.success {
            border-left-color: #48bb78;
            background: linear-gradient(90deg, #f0fff4 0%, #ffffff 100%);
        }

        .toast.error {
            border-left-color: #e53e3e;
            background: linear-gradient(90deg, #fff5f5 0%, #ffffff 100%);
        }

        .toast.info {
            border-left-color: #4299e1;
            background: linear-gradient(90deg, #ebf8ff 0%, #ffffff 100%);
        }

        .toast.warning {
            border-left-color: #ed8936;
            background: linear-gradient(90deg, #fffaf0 0%, #ffffff 100%);
        }

        .toast-icon {
            display: flex;
            align-items: center;
            justify-content: center;
            width: 24px;
            height: 24px;
            border-radius: 50%;
            margin-right: 12px;
            flex-shrink: 0;
        }

        .toast.success .toast-icon {
            background: #48bb78;
            color: white;
        }

        .toast.error .toast-icon {
            background: #e53e3e;
            color: white;
        }

        .toast.info .toast-icon {
            background: #4299e1;
            color: white;
        }

        .toast.warning .toast-icon {
            background: #ed8936;
            color: white;
        }

        .toast-content {
            flex: 1;
            min-width: 0;
        }

        .toast-title {
            font-weight: 600;
            color: #2d3748;
            margin-bottom: 4px;
            font-size: 0.95rem;
        }

        .toast-message {
            color: #718096;
            font-size: 0.875rem;
            line-height: 1.4;
            word-break: break-word;
        }

        .toast-close {
            background: none;
            border: none;
            color: #a0aec0;
            cursor: pointer;
            padding: 4px;
            margin-left: 8px;
            border-radius: 4px;
            transition: color 0.2s;
            flex-shrink: 0;
        }

        .toast-close:hover {
            color: #718096;
            background: #f7fafc;
        }

        @keyframes slideIn {
            from {
                transform: translateX(120%);
                opacity: 0;
            }
            to {
                transform: translateX(0);
                opacity: 1;
            }
        }

        @keyframes fadeOut {
            from {
                transform: translateX(0);
                opacity: 1;
            }
            to {
                transform: translateX(120%);
                opacity: 0;
            }
        }

        /* Add these styles to your existing CSS */
        .input-error {
            border-color: #e53e3e !important;
            box-shadow: 0 0 0 3px rgba(229, 62, 62, 0.1) !important;
        }

        .email-suggestion {
            background: #fef2f2;
            border: 1px solid #fed7d7;
            border-radius: 8px;
            padding: 12px;
            margin-top: 8px;
        }

        .premium-error {
            display: none;
            color: #e53e3e;
            font-size: 0.875rem;
            margin-top: 0.5rem;
        }

        .premium-error.show {
            display: block;
        }
        /* Premium Register Form Styling */
        .premium-register-container {
            max-width: 480px;
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

        .premium-register-container::before {
            content: "";
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 4px;
            background: linear-gradient(90deg, #667eea 0%, #764ba2 100%);
            border-radius: 20px 20px 0 0;
        }

        .premium-register-header {
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

        .premium-register-form {
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
            padding: 14px 50px;
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

        /* SEPARATED Phone Input Styling */
        .phone-input-container {
            display: flex;
            gap: 8px;
            align-items: center;
        }

        .country-code-selector {
            width: 120px;
            position: relative;
            min-width: 110px;
            flex-shrink: 0;
        }

        .selected-country {
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 14px 16px;
            background: white;
            border: 1px solid #e2e8f0;
            border-radius: 12px;
            cursor: pointer;
            transition: all 0.2s ease;
            user-select: none;
            height: 52px;
        }

        .selected-country:hover {
            border-color: #cbd5e0;
            background-color: #f7fafc;
        }

        .selected-country:active {
            border-color: #667eea;
        }

        .country-flag {
            font-size: 1.2rem;
            margin-right: 8px;
        }

        .country-code {
            font-weight: 600;
            color: #2d3748;
            flex-grow: 1;
        }

        .dropdown-arrow {
            color: #a0aec0;
            transition: transform 0.2s ease;
        }

        .country-code-selector.active .dropdown-arrow {
            transform: rotate(180deg);
        }

        .country-dropdown {
            position: absolute;
            top: calc(100% + 5px);
            left: 0;
            right: -120px;
            background: white;
            border: 1px solid #e2e8f0;
            border-radius: 12px;
            box-shadow: 0 10px 25px rgba(0, 0, 0, 0.1);
            z-index: 100;
            display: none;
            max-height: 300px;
            overflow: hidden;
        }

        .country-code-selector.active .country-dropdown {
            display: block;
        }

        .country-search {
            padding: 12px;
            border-bottom: 1px solid #e2e8f0;
            position: relative;
        }

        .search-icon {
            position: absolute;
            left: 20px;
            top: 50%;
            transform: translateY(-50%);
            color: #a0aec0;
        }

        .country-search-input {
            width: 100%;
            padding: 8px 8px 8px 32px;
            border: 1px solid #e2e8f0;
            border-radius: 8px;
            font-size: 0.9rem;
        }

        .country-search-input:focus {
            outline: none;
            border-color: #667eea;
        }

        .country-list {
            max-height: 200px;
            overflow-y: auto;
        }

        .country-option {
            display: flex;
            align-items: center;
            padding: 10px 16px;
            cursor: pointer;
            transition: background-color 0.2s;
        }

        .country-option:hover {
            background-color: #f7fafc;
        }

        .country-option.active {
            background-color: #edf2f7;
        }

        .country-option-flag {
            font-size: 1.2rem;
            margin-right: 10px;
            width: 24px;
        }

        .country-option-name {
            flex: 1;
            color: #4a5568;
            font-size: 0.95rem;
        }

        .country-option-code {
            color: #667eea;
            font-weight: 600;
            font-size: 0.9rem;
        }

        .phone-number-wrapper {
            flex: 1;
            position: relative;
        }

        .phone-number-input {
            width: 100%;
            height: 52px;
            padding-left: 16px;
        }

        .phone-validation-message {
            margin-top: 8px;
            font-size: 0.875rem;
            min-height: 20px;
        }

        .phone-validation-message.valid {
            color: #48bb78;
        }

        .phone-validation-message.invalid {
            color: #e53e3e;
        }

        /* Password Field Styling */
        .password-field {
            position: relative;
        }

        .password-toggle {
            position: absolute;
            right: 16px;
            top: 50%;
            transform: translateY(-50%);
            background: none;
            border: none;
            color: #a0aec0;
            cursor: pointer;
            padding: 4px;
            border-radius: 4px;
            transition: color 0.2s;
            z-index: 10;
        }

        .password-toggle:hover {
            color: #667eea;
        }

        .eye-icon {
            display: block;
        }

        .password-strength {
            display: flex;
            align-items: center;
            gap: 4px;
            margin-top: 8px;
        }

        .strength-bar {
            height: 4px;
            flex: 1;
            background: #e2e8f0;
            border-radius: 2px;
            transition: all 0.3s ease;
        }

        .strength-bar.very-weak { background: #e53e3e; }
        .strength-bar.weak { background: #ed8936; }
        .strength-bar.fair { background: #ecc94b; }
        .strength-bar.good { background: #68d391; }
        .strength-bar.strong { background: #48bb78; }

        .strength-text {
            font-size: 0.85rem;
            color: #718096;
            margin-left: 8px;
        }

        .password-hints {
            margin-top: 4px;
        }

        .hint-text {
            font-size: 0.85rem;
            color: #a0aec0;
            margin: 0;
        }

        .password-match {
            display: flex;
            align-items: center;
            gap: 8px;
            margin-top: 8px;
        }

        .match-icon {
            color: #48bb78;
        }

        .match-icon.hidden {
            display: none;
        }

        .match-text {
            font-size: 0.85rem;
            color: #718096;
        }

        /* Terms and Conditions */
        .terms-container {
            padding: 12px;
            background: #f7fafc;
            border-radius: 12px;
            border: 1px solid #e2e8f0;
        }

        .terms-link {
            color: #667eea;
            font-weight: 500;
            text-decoration: none;
        }

        .terms-link:hover {
            text-decoration: underline;
        }

        /* Checkbox Styling */
        .premium-checkbox-label {
            display: flex;
            align-items: flex-start;
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
            flex-shrink: 0;
            height: 20px;
            width: 20px;
            background-color: #ffffff;
            border: 2px solid #cbd5e0;
            border-radius: 6px;
            margin-right: 10px;
            margin-top: 2px;
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
            line-height: 1.4;
        }

        /* Submit Button */
        .premium-register-button {
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

        .premium-register-button:hover:not(:disabled) {
            transform: translateY(-2px);
            box-shadow: 0 6px 20px rgba(102, 126, 234, 0.4);
        }

        .premium-register-button:active:not(:disabled) {
            transform: translateY(0);
        }

        .premium-register-button:disabled {
            opacity: 0.6;
            cursor: not-allowed;
            transform: none;
            box-shadow: none;
        }

        .button-icon {
            margin-left: 10px;
            transition: transform 0.2s ease;
        }

        .premium-register-button:hover:not(:disabled) .button-icon {
            transform: translateX(4px);
        }

        /* Footer */
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

        .premium-error {
            color: #e53e3e;
            font-size: 0.875rem;
            margin-top: 0.5rem;
        }

        /* Responsive adjustments */
        @media (max-width: 640px) {
            .premium-register-container {
                padding: 2rem 1.5rem;
                margin: 1rem;
            }

            .premium-title {
                font-size: 1.75rem;
            }

            .phone-input-container {
                flex-direction: column;
                align-items: stretch;
            }

            .country-code-selector {
                transition:
                    width 0.3s ease,
                    opacity 0.2s ease,
                    margin 0.3s ease;
            }

            .country-dropdown {
                width: 100%;
                left: 0;
            }
            .country-code-selector.locked {
                pointer-events: none;
                background: #f1f5f9;
            }

            .country-code-selector.locked .dropdown-arrow {
                display: none;
            }

            .country-code-selector.locked::after {
                content: "✓";
                position: absolute;
                right: 10px;
                top: 50%;
                transform: translateY(-50%);
                color: #22c55e;
                font-weight: bold;
            }

        }
    </style>

    @if($recaptchaEnabled ?? false)
        <script src="https://www.google.com/recaptcha/api.js?render={{ $recaptchaSiteKey }}"></script>
    @endif
    <script>
        const RECAPTCHA_ENABLED = @json($recaptchaEnabled ?? false);
        const RECAPTCHA_SITE_KEY = @json($recaptchaSiteKey ?? '');

        (function () {
            const timingEl = document.getElementById('form_started_at');
            if (timingEl) {
                timingEl.value = Math.floor(Date.now() / 1000);
            }
        })();

        // Toast notification system
        class Toast {
            static show(message, type = 'info', title = null, duration = 5000) {
                const container = document.getElementById('toast-container');
                if (!container) {
                    // Create container if it doesn't exist
                    const newContainer = document.createElement('div');
                    newContainer.id = 'toast-container';
                    newContainer.className = 'toast-container';
                    document.querySelector('.premium-register-container').prepend(newContainer);
                }

                const toast = document.createElement('div');
                toast.className = `toast ${type}`;

                // Set icon based on type
                let iconSvg = '';
                switch(type) {
                    case 'success':
                        iconSvg = '<svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3" stroke-linecap="round" stroke-linejoin="round"><polyline points="20 6 9 17 4 12"></polyline></svg>';
                        title = title || 'Success';
                        break;
                    case 'error':
                        iconSvg = '<svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3" stroke-linecap="round" stroke-linejoin="round"><line x1="18" y1="6" x2="6" y2="18"></line><line x1="6" y1="6" x2="18" y2="18"></line></svg>';
                        title = title || 'Error';
                        break;
                    case 'warning':
                        iconSvg = '<svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3" stroke-linecap="round" stroke-linejoin="round"><path d="M10.29 3.86L1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z"></path><line x1="12" y1="9" x2="12" y2="13"></line><line x1="12" y1="17" x2="12" y2="17"></line></svg>';
                        title = title || 'Warning';
                        break;
                    default:
                        iconSvg = '<svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"></circle><line x1="12" y1="8" x2="12" y2="12"></line><line x1="12" y1="16" x2="12" y2="16"></line></svg>';
                        title = title || 'Info';
                }

                toast.innerHTML = `
                    <div class="toast-icon">${iconSvg}</div>
                    <div class="toast-content">
                        <div class="toast-title">${title}</div>
                        <div class="toast-message">${message}</div>
                    </div>
                    <button class="toast-close" onclick="this.parentElement.classList.add('hide')">
                        <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                            <line x1="18" y1="6" x2="6" y2="18"></line>
                            <line x1="6" y1="6" x2="18" y2="18"></line>
                        </svg>
                    </button>
                `;

                const toastContainer = document.getElementById('toast-container');
                toastContainer.appendChild(toast);

                // Show toast with animation
                setTimeout(() => toast.classList.add('show'), 10);

                // Auto remove after duration
                if (duration > 0) {
                    setTimeout(() => {
                        toast.classList.add('hide');
                        setTimeout(() => {
                            if (toast.parentNode) {
                                toast.parentNode.removeChild(toast);
                            }
                        }, 300);
                    }, duration);
                }

                return toast;
            }

            static success(message, title = null, duration = 5000) {
                return this.show(message, 'success', title, duration);
            }

            static error(message, title = null, duration = 5000) {
                return this.show(message, 'error', title, duration);
            }

            static info(message, title = null, duration = 5000) {
                return this.show(message, 'info', title, duration);
            }

            static warning(message, title = null, duration = 5000) {
                return this.show(message, 'warning', title, duration);
            }
        }

        // Country code data with flags
        const countries = [
            { name: "Afghanistan", code: "+93", flag: "🇦🇫" },
            { name: "Albania", code: "+355", flag: "🇦🇱" },
            { name: "Algeria", code: "+213", flag: "🇩🇿" },
            { name: "Argentina", code: "+54", flag: "🇦🇷" },
            { name: "Australia", code: "+61", flag: "🇦🇺" },
            { name: "Austria", code: "+43", flag: "🇦🇹" },
            { name: "Bangladesh", code: "+880", flag: "🇧🇩" },
            { name: "Belgium", code: "+32", flag: "🇧🇪" },
            { name: "Brazil", code: "+55", flag: "🇧🇷" },
            { name: "Canada", code: "+1", flag: "🇨🇦" },
            { name: "China", code: "+86", flag: "🇨🇳" },
            { name: "Denmark", code: "+45", flag: "🇩🇰" },
            { name: "Egypt", code: "+20", flag: "🇪🇬" },
            { name: "France", code: "+33", flag: "🇫🇷" },
            { name: "Germany", code: "+49", flag: "🇩🇪" },
            { name: "India", code: "+91", flag: "🇮🇳" },
            { name: "Indonesia", code: "+62", flag: "🇮🇩" },
            { name: "Iran", code: "+98", flag: "🇮🇷" },
            { name: "Iraq", code: "+964", flag: "🇮🇶" },
            { name: "Italy", code: "+39", flag: "🇮🇹" },
            { name: "Japan", code: "+81", flag: "🇯🇵" },
            { name: "Malaysia", code: "+60", flag: "🇲🇾" },
            { name: "Netherlands", code: "+31", flag: "🇳🇱" },
            { name: "Nigeria", code: "+234", flag: "🇳🇬" },
            { name: "Norway", code: "+47", flag: "🇳🇴" },
            { name: "Pakistan", code: "+92", flag: "🇵🇰" },
            { name: "Philippines", code: "+63", flag: "🇵🇭" },
            { name: "Russia", code: "+7", flag: "🇷🇺" },
            { name: "Saudi Arabia", code: "+966", flag: "🇸🇦" },
            { name: "Singapore", code: "+65", flag: "🇸🇬" },
            { name: "South Africa", code: "+27", flag: "🇿🇦" },
            { name: "South Korea", code: "+82", flag: "🇰🇷" },
            { name: "Spain", code: "+34", flag: "🇪🇸" },
            { name: "Sweden", code: "+46", flag: "🇸🇪" },
            { name: "Switzerland", code: "+41", flag: "🇨🇭" },
            { name: "Turkey", code: "+90", flag: "🇹🇷" },
            { name: "UAE", code: "+971", flag: "🇦🇪" },
            { name: "UK", code: "+44", flag: "🇬🇧" },
            { name: "USA", code: "+1", flag: "🇺🇸" }
        ];

        // DOM Elements
        const countryCodeSelector = document.getElementById('countryCodeSelector');
        const countryDropdown = document.getElementById('countryDropdown');
        const countryList = document.getElementById('countryList');
        const countrySearch = document.getElementById('countrySearch');
        const selectedFlag = document.getElementById('selectedFlag');
        const selectedCode = document.getElementById('selectedCode');
        const countryCodeInput = document.getElementById('countryCode');
        const phoneInput = document.getElementById('phone');
        const phoneValidation = document.getElementById('phoneValidation');
        const passwordInput = document.getElementById('password');
        const confirmPasswordInput = document.getElementById('password_confirmation');
        const togglePassword = document.getElementById('togglePassword');
        const toggleConfirmPassword = document.getElementById('toggleConfirmPassword');
        const strengthBars = document.querySelectorAll('.strength-bar');
        const strengthText = document.getElementById('strengthText');
        const passwordMatch = document.getElementById('passwordMatch');
        const matchIcon = passwordMatch.querySelector('.match-icon');
        const matchText = passwordMatch.querySelector('.match-text');
        const termsCheckbox = document.getElementById('terms');
        const registerButton = document.getElementById('registerButton');
        const emailSuggestion = document.getElementById('email-suggestion');

        // Populate country list
        function populateCountryList(filter = '') {
            countryList.innerHTML = '';
            const filteredCountries = countries.filter(country =>
                country.name.toLowerCase().includes(filter.toLowerCase()) ||
                country.code.includes(filter)
            );

            filteredCountries.forEach(country => {
                const option = document.createElement('div');
                option.className = 'country-option';
                option.innerHTML = `
                    <span class="country-option-flag">${country.flag}</span>
                    <span class="country-option-name">${country.name}</span>
                    <span class="country-option-code">${country.code}</span>
                `;

                option.addEventListener('click', (e) => {
                    e.stopPropagation();

                    selectedFlag.textContent = country.flag;
                    selectedCode.textContent = country.code;
                    countryCodeInput.value = country.code;

                    countryCodeSelector.classList.remove('active');
                    countrySearch.value = '';
                    populateCountryList('');
                    setTimeout(() => phoneInput.focus(), 100);
                });
                countryList.appendChild(option);
            });
        }

        // Initialize country list
        populateCountryList();

        // Toggle country dropdown
        countryCodeSelector.addEventListener('click', (e) => {
            e.stopPropagation();
            countryCodeSelector.classList.toggle('active');
            if (countryCodeSelector.classList.contains('active')) {
                countrySearch.focus();
            }
        });

        // Close dropdown when clicking outside
        document.addEventListener('click', (e) => {
            if (!countryCodeSelector.contains(e.target)) {
                countryCodeSelector.classList.remove('active');
            }
        });

        // Filter country list
        countrySearch.addEventListener('input', (e) => {
            populateCountryList(e.target.value);
        });

        // Validate phone number
        function validatePhoneNumber() {
            const phoneNumber = phoneInput.value.trim();
            const countryCode = countryCodeInput.value;

            if (!phoneNumber) {
                phoneValidation.textContent = '';
                phoneValidation.className = 'phone-validation-message';
                return false;
            }

            const phoneRegex = /^[0-9\-\+\s\(\)]{8,}$/;
            const isValid = phoneRegex.test(phoneNumber) && phoneNumber.replace(/\D/g, '').length >= 8;

            if (isValid) {
                phoneValidation.textContent = '✓ Valid phone number';
                phoneValidation.className = 'phone-validation-message valid';
                return true;
            } else {
                phoneValidation.textContent = '✗ Please enter a valid phone number';
                phoneValidation.className = 'phone-validation-message invalid';
                return false;
            }
        }

        // Auto-format phone number
        phoneInput.addEventListener('input', function(e) {
            let value = e.target.value.replace(/\D/g, '');

            if (value.length > 15) {
                value = value.substring(0, 15);
            }

            const countryCode = countryCodeInput.value;
            let formatted = value;

            if (countryCode === '+1') {
                if (value.length <= 3) {
                    formatted = value;
                } else if (value.length <= 6) {
                    formatted = `(${value.substring(0, 3)}) ${value.substring(3)}`;
                } else {
                    formatted = `(${value.substring(0, 3)}) ${value.substring(3, 6)}-${value.substring(6, 10)}`;
                }
            } else if (countryCode === '+44') {
                if (value.length <= 4) {
                    formatted = value;
                } else if (value.length <= 7) {
                    formatted = `${value.substring(0, 4)} ${value.substring(4)}`;
                } else {
                    formatted = `${value.substring(0, 4)} ${value.substring(4, 7)} ${value.substring(7)}`;
                }
            }

            phoneInput.value = formatted;
            validatePhoneNumber();
            validateForm();
        });

        // Toggle password visibility
        togglePassword.addEventListener('click', () => {
            const type = passwordInput.getAttribute('type') === 'password' ? 'text' : 'password';
            passwordInput.setAttribute('type', type);
            togglePassword.innerHTML = type === 'password' ?
                '<svg class="eye-icon" xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"></path><circle cx="12" cy="12" r="3"></circle></svg>' :
                '<svg class="eye-icon" xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M17.94 17.94A10.07 10.07 0 0 1 12 20c-7 0-11-8-11-8a18.45 18.45 0 0 1 5.06-5.94M9.9 4.24A9.12 9.12 0 0 1 12 4c7 0 11 8 11 8a18.5 18.5 0 0 1-2.16 3.19m-6.72-1.07a3 3 0 1 1-4.24-4.24"></path><line x1="1" y1="1" x2="23" y2="23"></line></svg>';
        });

        toggleConfirmPassword.addEventListener('click', () => {
            const type = confirmPasswordInput.getAttribute('type') === 'password' ? 'text' : 'password';
            confirmPasswordInput.setAttribute('type', type);
            toggleConfirmPassword.innerHTML = type === 'password' ?
                '<svg class="eye-icon" xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"></path><circle cx="12" cy="12" r="3"></circle></svg>' :
                '<svg class="eye-icon" xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M17.94 17.94A10.07 10.07 0 0 1 12 20c-7 0-11-8-11-8a18.45 18.45 0 0 1 5.06-5.94M9.9 4.24A9.12 9.12 0 0 1 12 4c7 0 11 8 11 8a18.5 18.5 0 0 1-2.16 3.19m-6.72-1.07a3 3 0 1 1-4.24-4.24"></path><line x1="1" y1="1" x2="23" y2="23"></line></svg>';
        });

        // Improved password strength checker with 5 levels
        function checkPasswordStrength(password) {
            if (!password) return 0;

            let score = 0;
            if (password.length >= 8) score += 1;
            if (password.length >= 12) score += 1;
            if (/[A-Z]/.test(password)) score += 1;
            if (/[a-z]/.test(password)) score += 1;
            if (/[0-9]/.test(password)) score += 1;
            if (/[^A-Za-z0-9]/.test(password)) score += 1;

            if (score <= 1) return 1;
            if (score <= 2) return 2;
            if (score <= 3) return 3;
            if (score <= 4) return 4;
            return 5;
        }

        // Update password strength display
        passwordInput.addEventListener('input', (e) => {
            const password = e.target.value;
            const strength = checkPasswordStrength(password);

            strengthBars.forEach((bar, index) => {
                bar.className = 'strength-bar';
                if (index < strength) {
                    if (strength === 1) bar.classList.add('very-weak');
                    else if (strength === 2) bar.classList.add('weak');
                    else if (strength === 3) bar.classList.add('fair');
                    else if (strength === 4) bar.classList.add('good');
                    else if (strength === 5) bar.classList.add('strong');
                }
            });

            const texts = ['', 'Very weak', 'Weak', 'Fair', 'Good', 'Strong'];
            strengthText.textContent = texts[strength] || 'Password strength';
            checkPasswordMatch();
        });

        // Check if passwords match
        function checkPasswordMatch() {
            const password = passwordInput.value;
            const confirmPassword = confirmPasswordInput.value;

            if (!password || !confirmPassword) {
                matchIcon.classList.add('hidden');
                matchText.textContent = 'Passwords must match';
                matchText.style.color = '#718096';
                return false;
            }

            if (password === confirmPassword) {
                matchIcon.classList.remove('hidden');
                matchText.textContent = 'Passwords match';
                matchText.style.color = '#48bb78';
                return true;
            } else {
                matchIcon.classList.add('hidden');
                matchText.textContent = 'Passwords do not match';
                matchText.style.color = '#e53e3e';
                return false;
            }
        }

        confirmPasswordInput.addEventListener('input', checkPasswordMatch);

        // Form validation
        function validateForm() {
            const name = document.getElementById('name').value.trim();
            const email = document.getElementById('email').value.trim();
            const password = passwordInput.value;

            const isNameValid = name.length >= 2;
            const isEmailValid = /^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(email);
            const isPhoneValid = validatePhoneNumber();
            const isPasswordNotVeryWeak = checkPasswordStrength(password) >= 2;
            const isPasswordMatch = checkPasswordMatch();
            const isTermsAccepted = termsCheckbox.checked;

            const isValid = isNameValid && isEmailValid && isPhoneValid &&
                isPasswordNotVeryWeak && isPasswordMatch && isTermsAccepted;

            registerButton.disabled = !isValid;
            return isValid;
        }

        // Add validation listeners
        document.getElementById('name').addEventListener('input', validateForm);
        document.getElementById('email').addEventListener('input', validateForm);
        phoneInput.addEventListener('input', validateForm);
        passwordInput.addEventListener('input', validateForm);
        confirmPasswordInput.addEventListener('input', validateForm);
        termsCheckbox.addEventListener('change', validateForm);

        // Also validate when country code changes
        countryCodeSelector.addEventListener('click', function() {
            setTimeout(validatePhoneNumber, 100);
        });

        // Initialize form validation
        validateForm();

        /* ============================================================
           UPDATED FORM SUBMIT HANDLER WITH TOAST MESSAGES
        ============================================================ */

        const form = document.querySelector('.premium-register-form');
        const recaptchaInput = document.getElementById('g-recaptcha-response');
        let isSubmitting = false;

        // Function to clear all errors (keep as is)
        function clearAllErrors() {
            document.querySelectorAll('.premium-error').forEach(el => {
                el.textContent = '';
                el.classList.remove('show');
            });
            document.querySelectorAll('.premium-text-input').forEach(input => {
                input.classList.remove('input-error');
            });
            if (emailSuggestion) {
                emailSuggestion.style.display = 'none';
            }
        }

        // Function to display error for a specific field (keep as is)
        function displayError(field, message) {
            const errorElement = document.getElementById(`${field}-error`)
                || (field === 'g-recaptcha-response' ? document.getElementById('g-recaptcha-response-error') : null);
            const inputElement = document.getElementById(field) || document.querySelector(`[name="${field}"]`);

            if (errorElement) {
                errorElement.textContent = message;
                errorElement.classList.add('show');
            }

            if (inputElement) {
                inputElement.classList.add('input-error');

                inputElement.addEventListener('input', function() {
                    this.classList.remove('input-error');
                    const errorEl = document.getElementById(`${field}-error`);
                    if (errorEl) errorEl.classList.remove('show');

                    if (field === 'email' && emailSuggestion) {
                        emailSuggestion.style.display = 'none';
                    }
                }, { once: true });
            }

            if (field === 'email' && message.includes('already been taken')) {
                if (emailSuggestion) {
                    emailSuggestion.style.display = 'block';
                }
            }
        }

        // In your form submit handler, update the structure:

        form.addEventListener('submit', async function (e) {
            e.preventDefault();

            if (isSubmitting) return;

            // Client-side validation
            if (typeof validateForm === 'function' && !validateForm()) {
                const firstError = document.querySelector('.premium-error.show');
                if (firstError) {
                    firstError.scrollIntoView({ behavior: 'smooth', block: 'center' });
                }
                return;
            }

            if (RECAPTCHA_ENABLED && typeof grecaptcha === 'undefined') {
                Toast.error('Security service not loaded. Please refresh the page or disable ad blockers.', 'Security Error');
                return;
            }

            isSubmitting = true;
            registerButton.disabled = true;

            // Change button text to show loading state
            const originalButtonText = registerButton.innerHTML;
            registerButton.innerHTML = 'Creating Account... <svg class="button-icon" xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 12a9 9 0 1 1-6.219-8.56"></path></svg>';

            const statusEl = document.getElementById('recaptcha-status');
            if (!statusEl) {
                const statusDiv = document.createElement('div');
                statusDiv.id = 'recaptcha-status';
                statusDiv.style.fontSize = '0.75rem';
                statusDiv.style.marginTop = '0.5rem';
                recaptchaInput.parentNode.appendChild(statusDiv);
            }

            const currentStatusEl = document.getElementById('recaptcha-status');
            if (currentStatusEl) {
                currentStatusEl.textContent = '⏳ Verifying security check...';
                currentStatusEl.style.color = '#718096';
            }

            try {
                if (RECAPTCHA_ENABLED) {
                    const token = await new Promise((resolve, reject) => {
                        grecaptcha.ready(() => {
                            grecaptcha.execute(RECAPTCHA_SITE_KEY, { action: 'register' })
                                .then(resolve)
                                .catch(reject);
                        });
                    });

                    if (!token) {
                        throw new Error('Empty reCAPTCHA token');
                    }

                    recaptchaInput.value = token;
                } else {
                    recaptchaInput.value = '';
                }

                // Prepare form data
                const formData = new FormData(form);

                // Add CSRF token
                const csrfToken = document.querySelector('meta[name="csrf-token"]');
                if (csrfToken) {
                    formData.append('_token', csrfToken.content);
                }

                // Combine phone number with country code
                const phoneInput = document.getElementById('phone');
                const countryCode = document.getElementById('countryCode').value;
                const phoneDigits = phoneInput.value.replace(/\D/g, '');
                const fullPhoneNumber = countryCode + phoneDigits;

                // Update form data with full phone number
                formData.set('phone', fullPhoneNumber);

                // Update status
                if (currentStatusEl) {
                    currentStatusEl.textContent = '✓ Security check passed, submitting...';
                    currentStatusEl.style.color = '#48bb78';
                }

                // Submit via fetch
                const response = await fetch(form.action, {
                    method: 'POST',
                    headers: {
                        'X-Requested-With': 'XMLHttpRequest',
                        'Accept': 'application/json',
                        'X-CSRF-TOKEN': csrfToken ? csrfToken.content : ''
                    },
                    body: formData
                });

                // Check if response is OK
                if (!response.ok) {
                    const errorText = await response.text();

                    try {
                        const errorData = JSON.parse(errorText);

                        // Clear previous errors
                        clearAllErrors();

                        // Display new errors
                        if (errorData.errors) {
                            Object.keys(errorData.errors).forEach(field => {
                                const errorMessage = errorData.errors[field][0];
                                displayError(field, errorMessage);
                            });

                            // Scroll to first error
                            const firstError = document.querySelector('.premium-error.show');
                            if (firstError) {
                                firstError.scrollIntoView({ behavior: 'smooth', block: 'center' });
                            }
                        }

                        // Show ONE toast for validation errors
                        if (errorData.errors) {
                            const firstError = Object.values(errorData.errors)[0][0];
                            Toast.error(firstError, 'Please fix the errors');
                        } else if (errorData.message) {
                            Toast.error(errorData.message, 'Validation Error');
                        } else {
                            Toast.error('Registration failed. Please check your information.', 'Error');
                        }

                        // Throw error WITHOUT message to prevent duplicate toast in catch block
                        throw new Error('VALIDATION_ERROR');

                    } catch (e) {
                        // If JSON parsing fails, show a generic error
                        console.error('Failed to parse error response:', errorText);

                        // Try to extract error message
                        let errorMessage = 'Registration failed. Please try again.';
                        if (errorText.includes('email') && errorText.includes('already')) {
                            errorMessage = 'This email is already registered.';
                            displayError('email', 'The email has already been taken.');
                        }

                        // Show ONE toast
                        Toast.error(errorMessage, 'Error');

                        // Throw error WITHOUT message to prevent duplicate toast
                        throw new Error('PARSE_ERROR');
                    }
                }

                const result = await response.json();

                if (result.success) {
                    if (currentStatusEl) {
                        currentStatusEl.textContent = '✓ Registration successful!';
                        currentStatusEl.style.color = '#48bb78';
                    }

                    // Show success toast
                    Toast.success(
                        'Registration successful! Please check your email to verify your account.',
                        'Success!',
                        3000
                    );

                    // Show success message briefly, then redirect
                    setTimeout(() => {
                        if (result.redirect) {
                            window.location.href = result.redirect;
                        } else {
                            window.location.href = '{{ route("dashboard") }}';
                        }
                    }, 1500);

                } else {
                    // Show ONE toast for non-validation server errors
                    Toast.error(result.message || 'Registration failed', 'Error');
                    throw new Error('SERVER_ERROR');
                }

            } catch (error) {
                console.error('Submission error:', error);

                // Reset button state
                isSubmitting = false;
                registerButton.disabled = false;
                registerButton.innerHTML = originalButtonText;

                // Update status element
                if (currentStatusEl) {
                    // Only show status for errors that don't already have a toast
                    if (error.message === 'VALIDATION_ERROR' ||
                        error.message === 'PARSE_ERROR' ||
                        error.message === 'SERVER_ERROR') {
                        // Already showed toast, so show generic status
                        currentStatusEl.textContent = '❌ Registration failed';
                    } else if (error.message.includes('Empty reCAPTCHA token')) {
                        currentStatusEl.textContent = '❌ Security check failed';
                        Toast.error('Security check failed. Refresh the page and try again.', 'reCAPTCHA');
                        displayError('g-recaptcha-response', 'Security check failed. Please refresh and try again.');
                    } else if (error.message && error.message !== 'VALIDATION_ERROR' && error.message !== 'PARSE_ERROR' && error.message !== 'SERVER_ERROR') {
                        Toast.error(error.message, 'Error');
                        // For other errors, show the message
                        currentStatusEl.textContent = '❌ ' + (error.message || 'Submission failed');
                    }
                    currentStatusEl.style.color = '#e53e3e';
                }

                // DO NOT show toast here - already shown above
                // The catch block is only for resetting UI state
            }
        });

        /* Safety: re-enable button on back navigation */
        window.addEventListener('pageshow', () => {
            isSubmitting = false;
            registerButton.disabled = false;
            const originalButtonText = '{{ __("Create Account") }} <svg class="button-icon" xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M16 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"></path><circle cx="8.5" cy="7" r="4"></circle><line x1="20" y1="8" x2="20" y2="14"></line><line x1="23" y1="11" x2="17" y2="11"></line></svg>';
            registerButton.innerHTML = originalButtonText;

            // Clear errors on page refresh
            clearAllErrors();
        });

    </script>
</x-guest-layout>
