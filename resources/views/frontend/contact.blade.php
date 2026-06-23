<!-- resources/views/frontend/contact.blade.php -->
@extends('frontend.layout')

@section('title', __('seo.pages.contact.title'))
@section('description', __('seo.pages.contact.description'))

@section('content')
@php
    $contactWhatsapp = config('contact.whatsapp');
    $contactWhatsappDigits = preg_replace('/\D/', '', $contactWhatsapp);
    $contactEmail = config('contact.email');
    $androidApkRelative = config('contact.android_apk');
    $androidApkUrl = asset($androidApkRelative);
    $androidApkAvailable = file_exists(public_path($androidApkRelative));
@endphp

    <!-- Contact Hero Section -->
    <section class="relative min-h-[60vh] flex items-center overflow-hidden">
        <!-- Background Elements -->
        <div class="absolute inset-0">
            <div class="absolute inset-0 bg-gradient-to-br from-slate-50 via-blue-50 to-sky-100 dark:from-slate-950 dark:via-slate-900 dark:to-sky-950"></div>
            <div class="absolute top-1/4 left-1/4 w-96 h-96 bg-gradient-to-r from-sky-300/20 to-blue-400/10 rounded-full blur-3xl"></div>
            <div class="absolute bottom-1/4 right-1/4 w-96 h-96 bg-gradient-to-r from-emerald-300/10 to-teal-400/5 rounded-full blur-3xl"></div>
        </div>

        <div class="relative max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-24">
            @include('frontend.partials.page-breadcrumbs')
            <div class="text-center">
            <span class="inline-block px-4 py-1.5 bg-gradient-to-r from-sky-500/10 to-blue-500/10 rounded-full text-sky-600 dark:text-sky-400 font-semibold text-sm mb-4">
                {{ __('frontend.contact.badge') }}
            </span>
                <h1 class="text-5xl lg:text-6xl font-bold mb-6">
                    {{ __('frontend.contact.heading') }} <span class="bg-gradient-to-r from-sky-500 to-blue-600 bg-clip-text text-transparent">{{ __('frontend.contact.heading_highlight') }}</span>
                </h1>
                <p class="text-xl text-slate-600 dark:text-slate-300 max-w-3xl mx-auto">
                    {{ __('frontend.contact.subtitle') }}
                </p>
            </div>
        </div>
    </section>

    <!-- Contact Form & Info -->
    <section class="py-20 relative">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="grid lg:grid-cols-2 gap-12">
                <!-- Contact Information -->
                <div class="space-y-8">
                    <div>
                        <h2 class="text-3xl font-bold mb-6">{{ __('frontend.contact.support_title') }}</h2>
                        <p class="text-slate-600 dark:text-slate-400">
                            {{ __('frontend.contact.support_desc') }}
                        </p>
                    </div>

                    <!-- Contact Cards -->
                    <div class="space-y-6">
                        <!-- WhatsApp -->
                        <a href="https://wa.me/{{ $contactWhatsappDigits }}"
                           target="_blank"
                           rel="noopener noreferrer"
                           class="block p-6 rounded-2xl glass border border-slate-200 dark:border-slate-800 hover:border-emerald-400 dark:hover:border-emerald-600 transition-all duration-300 group">
                            <div class="flex items-start gap-4">
                                <div class="w-12 h-12 rounded-xl bg-gradient-to-br from-emerald-500 to-green-600 flex items-center justify-center flex-shrink-0 group-hover:scale-105 transition-transform">
                                    <i class="fa-brands fa-whatsapp text-white text-2xl"></i>
                                </div>
                                <div>
                                    <h3 class="font-bold text-lg mb-2">WhatsApp</h3>
                                    <p class="text-slate-600 dark:text-slate-400 mb-3">
                                        Chat with us on WhatsApp for quick support and sales inquiries.
                                    </p>
                                    <div class="flex items-center gap-2 text-emerald-600 dark:text-emerald-400 font-medium">
                                        <i class="fa-brands fa-whatsapp"></i>
                                        <span>{{ $contactWhatsapp }}</span>
                                    </div>
                                </div>
                            </div>
                        </a>

                        <!-- Email -->
                        <a href="mailto:{{ $contactEmail }}"
                           class="block p-6 rounded-2xl glass border border-slate-200 dark:border-slate-800 hover:border-sky-300 dark:hover:border-sky-700 transition-all duration-300 group">
                            <div class="flex items-start gap-4">
                                <div class="w-12 h-12 rounded-xl bg-gradient-to-br from-sky-500 to-blue-600 flex items-center justify-center flex-shrink-0 group-hover:scale-105 transition-transform">
                                    <i class="fa-solid fa-envelope text-white text-xl"></i>
                                </div>
                                <div>
                                    <h3 class="font-bold text-lg mb-2">Email</h3>
                                    <p class="text-slate-600 dark:text-slate-400 mb-3">
                                        Send us an email and we will respond within 24 hours.
                                    </p>
                                    <div class="flex items-center gap-2 text-sky-600 dark:text-sky-400 font-medium">
                                        <i class="fa-solid fa-envelope"></i>
                                        <span>{{ $contactEmail }}</span>
                                    </div>
                                </div>
                            </div>
                        </a>

                        <!-- Android App -->
                        @if($androidApkAvailable)
                        <a href="{{ route('android-app') }}"
                           class="block p-6 rounded-2xl glass border border-slate-200 dark:border-slate-800 hover:border-green-400 dark:hover:border-green-600 transition-all duration-300 group">
                            <div class="flex items-start gap-4">
                                <div class="w-12 h-12 rounded-xl bg-gradient-to-br from-green-500 to-lime-600 flex items-center justify-center flex-shrink-0 group-hover:scale-105 transition-transform">
                                    <i class="fa-brands fa-android text-white text-2xl"></i>
                                </div>
                                <div>
                                    <h3 class="font-bold text-lg mb-2">Android App</h3>
                                    <p class="text-slate-600 dark:text-slate-400 mb-3">
                                        Download the FalconEyeGPS mobile app with our step-by-step installation guide.
                                    </p>
                                    <div class="flex items-center gap-2 text-green-600 dark:text-green-400 font-medium">
                                        <i class="fa-brands fa-android"></i>
                                        <span>Download &amp; install guide</span>
                                        <i class="fa-solid fa-arrow-right text-sm"></i>
                                    </div>
                                </div>
                            </div>
                        </a>
                        @endif
                    </div>

                    <!-- Stats -->
                    <div class="grid grid-cols-2 gap-4">
                        <div class="text-center p-4 rounded-xl glass">
                            <div class="text-2xl font-bold text-sky-600 dark:text-sky-400">24/7</div>
                            <div class="text-sm text-slate-600 dark:text-slate-400">Support</div>
                        </div>
                        <div class="text-center p-4 rounded-xl glass">
                            <div class="text-2xl font-bold text-emerald-600 dark:text-emerald-400">15min</div>
                            <div class="text-sm text-slate-600 dark:text-slate-400">Avg. Response</div>
                        </div>
                        <div class="text-center p-4 rounded-xl glass">
                            <div class="text-2xl font-bold text-amber-600 dark:text-amber-400">98%</div>
                            <div class="text-sm text-slate-600 dark:text-slate-400">Satisfaction</div>
                        </div>
                        <div class="text-center p-4 rounded-xl glass">
                            <div class="text-2xl font-bold text-purple-600 dark:text-purple-400">10K+</div>
                            <div class="text-sm text-slate-600 dark:text-slate-400">Clients</div>
                        </div>
                    </div>
                </div>

                <!-- Contact Form -->
                <div class="p-8 rounded-2xl glass border border-slate-200 dark:border-slate-800">
                    <h2 class="text-2xl font-bold mb-2">Send us a message</h2>
                    <p class="text-slate-600 dark:text-slate-400 mb-8">
                        Fill out the form below and we'll get back to you within 24 hours.
                    </p>

                    <form id="contactForm" method="POST" action="{{ route('contact.submit') }}">
                        @csrf

                        <div class="space-y-6">
                            <!-- Name & Email -->
                            <div class="grid md:grid-cols-2 gap-6">
                                <div>
                                    <label for="name" class="block text-sm font-medium mb-2">
                                        Full Name <span class="text-red-500">*</span>
                                    </label>
                                    <div class="relative">
                                        <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                                            <i class="fa-solid fa-user text-slate-400"></i>
                                        </div>
                                        <input type="text"
                                               id="name"
                                               name="name"
                                               required
                                               class="pl-10 w-full px-4 py-3 rounded-xl border border-slate-300 dark:border-slate-700 bg-white dark:bg-slate-900 focus:outline-none focus:ring-2 focus:ring-sky-500 focus:border-transparent transition-all"
                                               placeholder="John Doe">
                                    </div>
                                    <div class="text-xs text-red-500 mt-1" id="name-error"></div>
                                </div>

                                <div>
                                    <label for="email" class="block text-sm font-medium mb-2">
                                        Email Address <span class="text-red-500">*</span>
                                    </label>
                                    <div class="relative">
                                        <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                                            <i class="fa-solid fa-envelope text-slate-400"></i>
                                        </div>
                                        <input type="email"
                                               id="email"
                                               name="email"
                                               required
                                               class="pl-10 w-full px-4 py-3 rounded-xl border border-slate-300 dark:border-slate-700 bg-white dark:bg-slate-900 focus:outline-none focus:ring-2 focus:ring-sky-500 focus:border-transparent transition-all"
                                               placeholder="john@company.com">
                                    </div>
                                    <div class="text-xs text-red-500 mt-1" id="email-error"></div>
                                </div>
                            </div>

                            <!-- Phone & Company -->
                            <div class="grid md:grid-cols-2 gap-6">
                                <div>
                                    <label for="phone" class="block text-sm font-medium mb-2">
                                        Phone Number
                                    </label>
                                    <div class="relative">
                                        <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                                            <i class="fa-solid fa-phone text-slate-400"></i>
                                        </div>
                                        <input type="tel"
                                               id="phone"
                                               name="phone"
                                               class="pl-10 w-full px-4 py-3 rounded-xl border border-slate-300 dark:border-slate-700 bg-white dark:bg-slate-900 focus:outline-none focus:ring-2 focus:ring-sky-500 focus:border-transparent transition-all"
                                               placeholder="+923003026824">
                                    </div>
                                </div>

                                <div>
                                    <label for="company" class="block text-sm font-medium mb-2">
                                        Company
                                    </label>
                                    <div class="relative">
                                        <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                                            <i class="fa-solid fa-building text-slate-400"></i>
                                        </div>
                                        <input type="text"
                                               id="company"
                                               name="company"
                                               class="pl-10 w-full px-4 py-3 rounded-xl border border-slate-300 dark:border-slate-700 bg-white dark:bg-slate-900 focus:outline-none focus:ring-2 focus:ring-sky-500 focus:border-transparent transition-all"
                                               placeholder="Your Company">
                                    </div>
                                </div>
                            </div>

                            <!-- Subject -->
                            <div>
                                <label for="subject" class="block text-sm font-medium mb-2">
                                    Subject <span class="text-red-500">*</span>
                                </label>
                                <div class="relative">
                                    <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                                        <i class="fa-solid fa-tag text-slate-400"></i>
                                    </div>
                                    <input type="text"
                                           id="subject"
                                           name="subject"
                                           required
                                           class="pl-10 w-full px-4 py-3 rounded-xl border border-slate-300 dark:border-slate-700 bg-white dark:bg-slate-900 focus:outline-none focus:ring-2 focus:ring-sky-500 focus:border-transparent transition-all"
                                           placeholder="How can we help you?">
                                </div>
                                <div class="text-xs text-red-500 mt-1" id="subject-error"></div>
                            </div>

                            <!-- Message -->
                            <div>
                                <label for="message" class="block text-sm font-medium mb-2">
                                    Message <span class="text-red-500">*</span>
                                </label>
                                <div class="relative">
                                <textarea id="message"
                                          name="message"
                                          required
                                          rows="6"
                                          class="w-full px-4 py-3 rounded-xl border border-slate-300 dark:border-slate-700 bg-white dark:bg-slate-900 focus:outline-none focus:ring-2 focus:ring-sky-500 focus:border-transparent transition-all resize-y"
                                          placeholder="Tell us about your GPS tracking needs..."></textarea>
                                </div>
                                <div class="text-xs text-red-500 mt-1" id="message-error"></div>
                            </div>

                            <!-- Rate limit message -->
                            <div id="rateLimitMessage"
                                 class="hidden p-3 rounded-lg bg-amber-50 dark:bg-amber-900/20 border border-amber-200 dark:border-amber-800 text-amber-800 dark:text-amber-300 text-sm">
                            </div>

                            <!-- Submit Button -->
                            <div>
                                <button type="submit"
                                        id="submitBtn"
                                        class="w-full py-4 px-6 bg-gradient-to-r from-sky-600 to-blue-700 text-white font-semibold rounded-xl shadow-lg hover:shadow-xl hover:scale-[1.02] transition-all duration-300 flex items-center justify-center gap-2 disabled:opacity-50 disabled:cursor-not-allowed">
                                    <span id="submitText">Send Message</span>
                                    <i class="fa-solid fa-paper-plane"></i>
                                    <div id="spinner" class="hidden">
                                        <i class="fa-solid fa-spinner fa-spin"></i>
                                    </div>
                                </button>
                            </div>

                            @include('partials.bot-shield-fields', [
                                'recaptchaEnabled' => $recaptchaEnabled ?? false,
                                'includeHoneypots' => false,
                                'includeRecaptchaInput' => false,
                                'includeFormTiming' => false,
                                'includeRecaptchaNotice' => true,
                                'recaptchaNoticeStyle' => 'footer',
                            ])

                            <!-- Success Message -->
                            <div id="successMessage"
                                 class="hidden p-4 rounded-xl bg-emerald-50 dark:bg-emerald-900/20 border border-emerald-200 dark:border-emerald-800">
                                <div class="flex items-start gap-3">
                                    <div class="w-8 h-8 rounded-full bg-emerald-500 flex items-center justify-center flex-shrink-0">
                                        <i class="fa-solid fa-check text-white"></i>
                                    </div>
                                    <div>
                                        <h4 class="font-semibold text-emerald-900 dark:text-emerald-400">Message sent successfully!</h4>
                                        <p class="text-sm text-emerald-800 dark:text-emerald-300 mt-1">
                                            Thank you for contacting us. We've received your message and will get back to you within 24 hours.
                                        </p>
                                        <p class="text-xs text-emerald-700 dark:text-emerald-400 mt-2" id="ticketNumber"></p>
                                    </div>
                                </div>
                            </div>

                            <!-- Error Message -->
                            <div id="errorMessage"
                                 class="hidden p-4 rounded-xl bg-red-50 dark:bg-red-900/20 border border-red-200 dark:border-red-800">
                                <div class="flex items-start gap-3">
                                    <div class="w-8 h-8 rounded-full bg-red-500 flex items-center justify-center flex-shrink-0">
                                        <i class="fa-solid fa-exclamation text-white"></i>
                                    </div>
                                    <div>
                                        <h4 class="font-semibold text-red-900 dark:text-red-400">Something went wrong!</h4>
                                        <p class="text-sm text-red-800 dark:text-red-300 mt-1" id="errorMessageText">
                                            Please try again or contact us directly.
                                        </p>
                                    </div>
                                </div>
                            </div>
                        </div>

                        @include('partials.bot-shield-fields', [
                            'recaptchaEnabled' => $recaptchaEnabled ?? false,
                            'includeRecaptchaNotice' => false,
                        ])
                    </form>

                    <div class="mt-8 pt-8 border-t border-slate-200 dark:border-slate-800">
                        <p class="text-sm text-slate-600 dark:text-slate-400 text-center">
                            By submitting this form, you agree to our
                            <a href="{{ url('/privacy') }}" class="text-sky-600 dark:text-sky-400 hover:underline">Privacy Policy</a>
                            and
                            <a href="{{ url('/terms') }}" class="text-sky-600 dark:text-sky-400 hover:underline">Terms of Service</a>.
                        </p>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- FAQ Section -->
    <section class="py-20 bg-slate-50 dark:bg-slate-900/50">
        <div class="max-w-4xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="text-center mb-12">
                <h2 class="text-3xl font-bold mb-4">Frequently Asked Questions</h2>
                <p class="text-slate-600 dark:text-slate-400">Common questions about contacting FalconEyeGPS</p>
            </div>

            <div class="space-y-4">
                @php
                    $faqs = [
                        [
                            'q' => 'How long does it take to get a response?',
                            'a' => 'We typically respond within 24 hours for general inquiries and within 2 hours for urgent technical support requests.'
                        ],
                        [
                            'q' => 'Do you offer 24/7 support?',
                            'a' => 'Yes, we offer 24/7 technical support for all enterprise customers. Sales and general inquiries are handled during business hours.'
                        ],
                        [
                            'q' => 'What information should I include in my message?',
                            'a' => 'Please include your company name, number of vehicles, current GPS system (if any), and specific requirements or challenges.'
                        ],
                        [
                            'q' => 'Do you offer custom enterprise solutions?',
                            'a' => 'Yes, we specialize in custom enterprise GPS tracking solutions. Contact our sales team for a tailored proposal.'
                        ],
                        [
                            'q' => 'Can I schedule a product demo?',
                            'a' => 'Absolutely! Use the contact form to request a personalized demo of our GPS tracking platform.'
                        ],
                    ];
                @endphp

                @include('frontend.partials.faq-accordion', ['faqs' => $faqs])
            </div>
        </div>
    </section>

@endsection

@push('head')
@endpush

@push('scripts')
    @include('partials.bot-shield-script', [
        'recaptchaEnabled' => $recaptchaEnabled ?? false,
        'recaptchaSiteKey' => $recaptchaSiteKey ?? '',
        'recaptchaAction' => 'contact',
    ])
    <script>
        document.addEventListener('DOMContentLoaded', function () {

            const form = document.getElementById('contactForm');
            const submitBtn = document.getElementById('submitBtn');
            const submitText = document.getElementById('submitText');
            const spinner = document.getElementById('spinner');

            const successMessage = document.getElementById('successMessage');
            const errorMessage = document.getElementById('errorMessage');
            const errorMessageText = document.getElementById('errorMessageText');
            const ticketNumber = document.getElementById('ticketNumber');

            // 🔹 Rate limit UI
            const rateLimitMessage = document.getElementById('rateLimitMessage');

            /* ----------------------------------
               Rate limit check (UX only)
            ---------------------------------- */
            async function checkRateLimit() {
                try {
                    const res = await fetch("{{ route('contact.rate-limit') }}", {
                        headers: {
                            'X-Requested-With': 'XMLHttpRequest',
                            'Accept': 'application/json'
                        }
                    });

                    const data = await res.json();

                    if (data.too_many_attempts) {
                        submitBtn.disabled = true;

                        const minutes = Math.ceil(data.available_in_seconds / 60);
                        rateLimitMessage.textContent =
                            `Too many submissions. Please try again in ${minutes} minute${minutes > 1 ? 's' : ''}.`;

                        rateLimitMessage.classList.remove('hidden');
                        return false;
                    }

                    submitBtn.disabled = false;
                    rateLimitMessage.classList.add('hidden');
                    return true;

                } catch (e) {
                    // Fail open — don't block user if API fails
                    return true;
                }
            }

            // Initial rate limit check on page load
            checkRateLimit();

            function lockFormForMinutes(minutes) {
                submitBtn.disabled = true;

                rateLimitMessage.textContent =
                    `Thank you! You can submit another message after ${minutes} minutes.`;

                rateLimitMessage.classList.remove('hidden');
            }

            /* ----------------------------------
               Form submit
            ---------------------------------- */
            form.addEventListener('submit', async function (e) {
                e.preventDefault();

                clearErrors();

                // Check rate limit BEFORE submit
                const allowed = await checkRateLimit();
                if (!allowed) return;

                // Loading state
                submitBtn.disabled = true;
                submitText.classList.add('hidden');
                spinner.classList.remove('hidden');

                try {
                    await FalconEyeBotShield.acquireToken();
                } catch (securityError) {
                    errorMessageText.textContent = securityError.message || 'Security check failed. Please refresh and try again.';
                    errorMessage.classList.remove('hidden');
                    successMessage.classList.add('hidden');
                    submitBtn.disabled = false;
                    submitText.classList.remove('hidden');
                    spinner.classList.add('hidden');
                    return;
                }

                try {
                    const formData = new FormData(form);

                    const response = await fetch(form.action, {
                        method: 'POST',
                        body: formData,
                        headers: {
                            'X-Requested-With': 'XMLHttpRequest',
                            'Accept': 'application/json'
                        }
                    });

                    const data = await response.json();

                    if (response.ok && data.success) {
                        successMessage.classList.remove('hidden');
                        errorMessage.classList.add('hidden');

                        ticketNumber.textContent = `Ticket Number: ${data.ticket_number}`;

                        form.reset();
                        FalconEyeBotShield.initFormTiming();

                        // Lock form after successful submit
                        lockFormForMinutes(60);
                        successMessage.scrollIntoView({
                            behavior: 'smooth',
                            block: 'center'
                        });

                    } else {
                        errorMessageText.textContent =
                            data.message || 'Something went wrong. Please try again or contact us directly.';
                        errorMessage.classList.remove('hidden');
                        successMessage.classList.add('hidden');

                        if (data.errors) {
                            Object.keys(data.errors).forEach(field => {
                                const el = document.getElementById(`${field}-error`);
                                if (el) el.textContent = data.errors[field][0];
                            });
                        }

                        if (!response.ok && response.status !== 422) {
                            submitBtn.disabled = false;
                        }
                    }

                } catch (error) {
                    errorMessageText.textContent = 'Network error. Please try again.';
                    errorMessage.classList.remove('hidden');
                    successMessage.classList.add('hidden');

                } finally {
                    //submitBtn.disabled = false;
                    submitText.classList.remove('hidden');
                    spinner.classList.add('hidden');

                    // Re-check rate limit after submit
                    //checkRateLimit();
                }
            });

            /* ----------------------------------
               Helpers
            ---------------------------------- */
            function clearErrors() {
                document.querySelectorAll('[id$="-error"]').forEach(el => {
                    el.textContent = '';
                });
                errorMessage.classList.add('hidden');
            }

        });
    </script>
@endpush

