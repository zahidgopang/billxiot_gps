<!-- resources/views/frontend/pages/help.blade.php -->
@extends('frontend.layout')

@section('title', __('seo.pages.help.title'))
@section('description', __('seo.pages.help.description'))

@section('content')

    <!-- Hero -->
    <section class="relative min-h-[60vh] flex items-center overflow-hidden">
        <div class="absolute inset-0 bg-gradient-to-br from-slate-50 via-sky-50 to-blue-100 dark:from-slate-950 dark:via-sky-950 dark:to-blue-950"></div>

        <div class="relative max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-24">
            @include('frontend.partials.page-breadcrumbs')
            <div class="grid lg:grid-cols-2 gap-12 items-center">
                <div>
                <span class="inline-block px-4 py-1.5 bg-gradient-to-r from-sky-500/10 to-blue-500/10 rounded-full text-sky-600 dark:text-sky-400 font-semibold text-sm mb-4">
                    HELP CENTER
                </span>
                    <h1 class="text-5xl lg:text-6xl font-bold mb-6">
                        How can we <span class="bg-gradient-to-r from-sky-500 to-blue-600 bg-clip-text text-transparent">help?</span>
                    </h1>
                    <p class="text-xl text-slate-600 dark:text-slate-300 mb-8">
                        Find answers to common questions, tutorials, and support resources for FalconEyeGPS.
                    </p>

                    <!-- Search -->
                    <div class="relative max-w-xl">
                        <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                            <i class="fa-solid fa-search text-slate-400"></i>
                        </div>
                        <input type="text"
                               placeholder="Search for answers..."
                               class="pl-10 w-full px-4 py-3 rounded-xl border border-slate-300 dark:border-slate-700 bg-white dark:bg-slate-900 focus:outline-none focus:ring-2 focus:ring-sky-500 focus:border-transparent">
                    </div>
                </div>

                <div class="relative">
                    <div class="grid grid-cols-2 gap-4">
                        <div class="p-6 rounded-2xl glass border border-slate-200 dark:border-slate-800">
                            <div class="w-12 h-12 rounded-xl bg-gradient-to-br from-sky-500 to-blue-600 flex items-center justify-center mb-4">
                                <i class="fa-solid fa-book text-white text-xl"></i>
                            </div>
                            <h3 class="font-bold text-lg mb-2">Documentation</h3>
                            <p class="text-slate-600 dark:text-slate-400 text-sm">Detailed guides and manuals</p>
                        </div>

                        <div class="p-6 rounded-2xl glass border border-slate-200 dark:border-slate-800">
                            <div class="w-12 h-12 rounded-xl bg-gradient-to-br from-emerald-500 to-teal-600 flex items-center justify-center mb-4">
                                <i class="fa-solid fa-video text-white text-xl"></i>
                            </div>
                            <h3 class="font-bold text-lg mb-2">Video Tutorials</h3>
                            <p class="text-slate-600 dark:text-slate-400 text-sm">Step-by-step video guides</p>
                        </div>

                        <div class="p-6 rounded-2xl glass border border-slate-200 dark:border-slate-800">
                            <div class="w-12 h-12 rounded-xl bg-gradient-to-br from-amber-500 to-orange-600 flex items-center justify-center mb-4">
                                <i class="fa-solid fa-question-circle text-white text-xl"></i>
                            </div>
                            <h3 class="font-bold text-lg mb-2">FAQ</h3>
                            <p class="text-slate-600 dark:text-slate-400 text-sm">Frequently asked questions</p>
                        </div>

                        <div class="p-6 rounded-2xl glass border border-slate-200 dark:border-slate-800">
                            <div class="w-12 h-12 rounded-xl bg-gradient-to-br from-purple-500 to-pink-600 flex items-center justify-center mb-4">
                                <i class="fa-solid fa-comments text-white text-xl"></i>
                            </div>
                            <h3 class="font-bold text-lg mb-2">Community</h3>
                            <p class="text-slate-600 dark:text-slate-400 text-sm">Connect with other users</p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- Popular Articles -->
    <section class="py-20">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="text-center mb-16">
                <h2 class="text-3xl font-bold mb-4">Popular Help Articles</h2>
                <p class="text-slate-600 dark:text-slate-400">Most frequently viewed support articles</p>
            </div>

            <div class="grid md:grid-cols-3 gap-8">
                @php
                    $articles = [
                        [
                            'title' => 'Getting Started with FalconEyeGPS',
                            'excerpt' => 'Complete setup guide for new users',
                            'category' => 'Setup',
                            'views' => '12,548',
                            'icon' => 'fa-solid fa-rocket',
                            'color' => 'from-sky-500 to-blue-600'
                        ],
                        [
                            'title' => 'Device Installation & Setup',
                            'excerpt' => 'How to install and configure GPS devices',
                            'category' => 'Hardware',
                            'views' => '8,932',
                            'icon' => 'fa-solid fa-satellite-dish',
                            'color' => 'from-emerald-500 to-teal-600'
                        ],
                        [
                            'title' => 'Real-Time Tracking Features',
                            'excerpt' => 'Understanding live tracking capabilities',
                            'category' => 'Features',
                            'views' => '7,415',
                            'icon' => 'fa-solid fa-location-dot',
                            'color' => 'from-amber-500 to-orange-600'
                        ],
                        [
                            'title' => 'Generating Reports',
                            'excerpt' => 'Create and export detailed reports',
                            'category' => 'Reporting',
                            'views' => '6,892',
                            'icon' => 'fa-solid fa-chart-bar',
                            'color' => 'from-purple-500 to-pink-600'
                        ],
                        [
                            'title' => 'Setting Up Geofences',
                            'excerpt' => 'Create virtual boundaries and alerts',
                            'category' => 'Alerts',
                            'views' => '5,743',
                            'icon' => 'fa-solid fa-draw-polygon',
                            'color' => 'from-red-500 to-rose-600'
                        ],
                        [
                            'title' => 'Mobile App Guide',
                            'excerpt' => 'Using FalconEyeGPS on iOS and Android',
                            'category' => 'Mobile',
                            'views' => '4,986',
                            'icon' => 'fa-solid fa-mobile-screen',
                            'color' => 'from-indigo-500 to-violet-600'
                        ],
                    ];
                @endphp

                @foreach($articles as $article)
                    <a href="#" class="group">
                        <div class="h-full p-6 rounded-2xl glass border border-slate-200 dark:border-slate-800 hover:border-slate-300 dark:hover:border-slate-700 transition-all">
                            <div class="flex items-start gap-4 mb-4">
                                <div class="w-12 h-12 rounded-xl bg-gradient-to-br {{ $article['color'] }} flex items-center justify-center flex-shrink-0">
                                    <i class="{{ $article['icon'] }} text-white text-xl"></i>
                                </div>
                                <div>
                                <span class="inline-block px-2 py-1 rounded bg-slate-100 dark:bg-slate-800 text-slate-600 dark:text-slate-400 text-xs font-medium mb-2">
                                    {{ $article['category'] }}
                                </span>
                                    <h3 class="font-bold text-lg group-hover:text-sky-600 dark:group-hover:text-sky-400 transition-colors">
                                        {{ $article['title'] }}
                                    </h3>
                                </div>
                            </div>

                            <p class="text-slate-600 dark:text-slate-400 text-sm mb-4">{{ $article['excerpt'] }}</p>

                            <div class="flex items-center justify-between pt-4 border-t border-slate-200 dark:border-slate-800">
                                <div class="flex items-center gap-1 text-slate-500 dark:text-slate-400 text-sm">
                                    <i class="fa-solid fa-eye"></i>
                                    <span>{{ $article['views'] }} views</span>
                                </div>
                                <div class="text-sky-600 dark:text-sky-400">
                                    <i class="fa-solid fa-arrow-right group-hover:translate-x-1 transition-transform"></i>
                                </div>
                            </div>
                        </div>
                    </a>
                @endforeach
            </div>
        </div>
    </section>

    <!-- FAQ Section -->
    <section class="py-20 bg-slate-50 dark:bg-slate-900/50">
        <div class="max-w-4xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="text-center mb-16">
                <h2 class="text-3xl font-bold mb-4">Frequently Asked Questions</h2>
                <p class="text-slate-600 dark:text-slate-400">Quick answers to common questions</p>
            </div>

            <div class="space-y-4">
                @php
                    $faqs = [
                        [
                            'q' => 'What GPS devices are compatible with FalconEyeGPS?',
                            'a' => 'We support common protocols such as GT06 and many LTE trackers. Our team helps configure your device model during onboarding.'
                        ],
                        [
                            'q' => 'How often is location updated?',
                            'a' => 'Update frequency depends on device settings and cellular coverage. Most fleets see near real-time positions when devices are online.'
                        ],
                        [
                            'q' => 'Can I track vehicles in real-time?',
                            'a' => 'Yes. The web panel and Android app show live positions, speed, and status for authorized devices on your account.'
                        ],
                        [
                            'q' => 'Is there a mobile app available?',
                            'a' => 'Yes. The FalconEyeGPS Android app is available for download from our website and uses the same login as the web panel.'
                        ],
                        [
                            'q' => 'How secure is my data?',
                            'a' => 'We use HTTPS for web and API traffic, password authentication, and role-based access. See our Security page for details.'
                        ],
                        [
                            'q' => 'What kind of support do you offer?',
                            'a' => 'We provide onboarding assistance, device configuration help, and support by email and contact form for active customers.'
                        ],
                    ];
                @endphp

                @include('frontend.partials.faq-accordion', ['faqs' => $faqs])
            </div>

            <div class="text-center mt-12">
                <a href="#" class="inline-flex items-center gap-2 px-6 py-3 border-2 border-slate-300 dark:border-slate-700 rounded-xl font-medium hover:bg-slate-50 dark:hover:bg-slate-800 transition-colors">
                    View All FAQ
                    <i class="fa-solid fa-arrow-right"></i>
                </a>
            </div>
        </div>
    </section>

    <!-- Contact Support -->
    <section class="py-20">
        <div class="max-w-4xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="p-8 rounded-2xl glass border border-slate-200 dark:border-slate-800 text-center">
                <div class="w-16 h-16 rounded-xl bg-gradient-to-br from-sky-500 to-blue-600 flex items-center justify-center mx-auto mb-6">
                    <i class="fa-solid fa-headset text-white text-2xl"></i>
                </div>
                <h2 class="text-3xl font-bold mb-4">Still Need Help?</h2>
                <p class="text-xl text-slate-600 dark:text-slate-400 mb-8">
                    Our support team is available 24/7 to assist you via WhatsApp.
                </p>

                <div class="grid md:grid-cols-3 gap-6 mb-8">
                    <div class="p-6 rounded-xl bg-slate-50 dark:bg-slate-800/50">
                        <i class="fa-solid fa-phone text-sky-500 text-2xl mb-4"></i>
                        <div class="font-bold mb-2">Phone Support</div>
                        <div class="text-slate-600 dark:text-slate-400">+92 300 3026824</div>
                    </div>

                    <div class="p-6 rounded-xl bg-slate-50 dark:bg-slate-800/50">
                        <i class="fa-solid fa-envelope text-sky-500 text-2xl mb-4"></i>
                        <div class="font-bold mb-2">Email Support</div>
                        <div class="text-slate-600 dark:text-slate-400">support@falconeyegps.com</div>
                    </div>

                    <div onclick="openWhatsAppSupport()"
                         class="p-6 rounded-xl bg-gradient-to-br from-emerald-50 to-green-50 dark:from-emerald-900/20 dark:to-green-900/20 border border-emerald-200 dark:border-emerald-700/30 cursor-pointer hover:shadow-lg transition-all duration-300 group">
                        <div class="relative">
                            <div class="w-12 h-12 rounded-xl bg-gradient-to-br from-emerald-500 to-green-500 flex items-center justify-center mb-4 mx-auto">
                                <i class="fa-brands fa-whatsapp text-white text-2xl"></i>
                            </div>
                            <div class="absolute -top-1 -right-1 w-6 h-6 bg-emerald-500 rounded-full flex items-center justify-center">
                                <div class="w-2 h-2 bg-white rounded-full animate-pulse"></div>
                            </div>
                        </div>
                        <div class="font-bold mb-2 text-emerald-700 dark:text-emerald-300">WhatsApp Support</div>
                        <div class="text-emerald-600 dark:text-emerald-400 mb-1">Instant Live Chat</div>
                        <div class="text-sm text-emerald-500 dark:text-emerald-400/80">Click to Chat Now</div>

                        <!-- Hover Effect -->
                        <div class="mt-3 flex items-center justify-center gap-2 text-sm text-emerald-600 dark:text-emerald-400 opacity-0 group-hover:opacity-100 transition-opacity duration-300">
                            <span>Tap to open WhatsApp</span>
                            <i class="fas fa-arrow-right group-hover:translate-x-1 transition-transform"></i>
                        </div>
                    </div>
                </div>

                <!-- WhatsApp CTA Button -->
                <button onclick="openWhatsAppSupport()"
                        class="inline-flex items-center gap-3 px-8 py-4 bg-gradient-to-r from-emerald-500 to-green-600 text-white font-semibold rounded-xl hover:shadow-xl hover:shadow-emerald-500/30 transition-all duration-300 mb-4 group">
                    <i class="fa-brands fa-whatsapp text-xl"></i>
                    Chat on WhatsApp Now
                    <i class="fa-solid fa-arrow-right group-hover:translate-x-1 transition-transform"></i>
                </button>

                <p class="text-sm text-slate-500 dark:text-slate-400">
                    Average response time: <span class="font-semibold text-emerald-600 dark:text-emerald-400">2 minutes</span>
                </p>
            </div>
        </div>
    </section>

    <!-- Floating WhatsApp Button -->
    <div class="fixed bottom-6 right-6 z-40 hidden lg:block">
        <button onclick="openWhatsAppSupport()"
                class="group relative w-16 h-16 rounded-full bg-gradient-to-r from-emerald-500 to-green-500 shadow-2xl shadow-emerald-500/30 hover:shadow-emerald-500/50 transition-all duration-300 hover:scale-110">
            <i class="fa-brands fa-whatsapp text-white text-2xl absolute inset-0 flex items-center justify-center"></i>

            <!-- Notification Badge -->
            <div class="absolute -top-1 -right-1 w-6 h-6 bg-red-500 rounded-full flex items-center justify-center animate-pulse">
                <span class="text-xs text-white font-bold">1</span>
            </div>

            <!-- Pulse Animation -->
            <div class="absolute inset-0 rounded-full border-4 border-emerald-400 opacity-0 group-hover:opacity-100 animate-ping"></div>

            <!-- Tooltip -->
            <div class="absolute right-full mr-3 top-1/2 -translate-y-1/2 px-3 py-2 bg-slate-900 text-white text-sm rounded-lg whitespace-nowrap opacity-0 group-hover:opacity-100 transition-opacity duration-300">
                Chat with us on WhatsApp
                <div class="absolute top-1/2 -right-1 -translate-y-1/2 w-2 h-2 bg-slate-900 rotate-45"></div>
            </div>
        </button>
    </div>

@endsection

@push('scripts')
    <script>
        // WhatsApp Configuration
        const whatsappConfig = {
            phoneNumber: '+923003026824', // Your WhatsApp number
            defaultMessage: 'Hello! I need help with FalconEyeGPS. I was on your help center page.',
            businessHours: {
                start: 9, // 9 AM
                end: 18,  // 6 PM
                timezone: 'GMT+5'
            }
        };

        // Open WhatsApp Support Chat
        function openWhatsAppSupport() {
            // Check business hours
            const now = new Date();
            const currentHour = now.getHours();
            const isBusinessHours = currentHour >= whatsappConfig.businessHours.start &&
                currentHour < whatsappConfig.businessHours.end;

            // Format phone number (remove any non-digits)
            const phone = whatsappConfig.phoneNumber.replace(/\D/g, '');

            // Create message
            let message = whatsappConfig.defaultMessage;

            if (!isBusinessHours) {
                message += '\n\n[Message sent outside business hours (9 AM - 6 PM GMT+5). We\'ll respond when we\'re back.]';
            }

            // Encode message for URL
            const encodedMessage = encodeURIComponent(message);

            // Create WhatsApp URL
            const whatsappURL = `https://wa.me/${phone}?text=${encodedMessage}`;

            // Open in new tab
            window.open(whatsappURL, '_blank', 'noopener,noreferrer');

            // Track the event
            trackSupportRequest('whatsapp');

            // Show notification
            showNotification('Opening WhatsApp...', 'success');
        }

        // Track support requests
        function trackSupportRequest(channel) {
            // Google Analytics
            if (typeof gtag !== 'undefined') {
                gtag('event', 'support_request', {
                    'event_category': 'help_center',
                    'event_label': channel,
                    'value': 1
                });
            }

            // Log to console (for debugging)
            console.log(`Support requested via ${channel}`);
        }

        // Show notification
        function showNotification(message, type = 'info') {
            // Create notification element
            const notification = document.createElement('div');
            notification.className = `fixed top-4 right-4 z-50 px-6 py-4 rounded-xl shadow-2xl backdrop-blur-sm transform translate-x-full opacity-0 transition-all duration-300 ${
                type === 'success' ? 'bg-emerald-500/90 text-white' : 'bg-sky-500/90 text-white'
            }`;

            notification.innerHTML = `
            <div class="flex items-center gap-3">
                <i class="fas fa-${type === 'success' ? 'check-circle' : 'info-circle'} text-xl"></i>
                <span>${message}</span>
            </div>
        `;

            document.body.appendChild(notification);

            // Animate in
            setTimeout(() => {
                notification.classList.remove('translate-x-full', 'opacity-0');
                notification.classList.add('translate-x-0', 'opacity-100');
            }, 10);

            // Remove after 3 seconds
            setTimeout(() => {
                notification.classList.remove('translate-x-0', 'opacity-100');
                notification.classList.add('translate-x-full', 'opacity-0');
                setTimeout(() => notification.remove(), 300);
            }, 3000);
        }

        // Update business hours status
        function updateBusinessHoursStatus() {
            const now = new Date();
            const currentHour = now.getHours();
            const isBusinessHours = currentHour >= whatsappConfig.businessHours.start &&
                currentHour < whatsappConfig.businessHours.end;

            // Update status text if needed
            const statusElements = document.querySelectorAll('.whatsapp-status');
            statusElements.forEach(el => {
                if (isBusinessHours) {
                    el.innerHTML = '<span class="text-emerald-600 dark:text-emerald-400">Online Now • 2 min avg response</span>';
                } else {
                    const hoursUntilOpen = 24 - currentHour + whatsappConfig.businessHours.start;
                    el.innerHTML = `<span class="text-amber-600 dark:text-amber-400">Back in ${hoursUntilOpen}h • Leave a message</span>`;
                }
            });
        }

        // Initialize on page load
        document.addEventListener('DOMContentLoaded', () => {
            // Update business hours status
            updateBusinessHoursStatus();

            // Update every minute
            setInterval(updateBusinessHoursStatus, 60000);

            // Keyboard shortcut (Alt + W for WhatsApp)
            document.addEventListener('keydown', (e) => {
                if (e.altKey && e.key === 'w') {
                    e.preventDefault();
                    openWhatsAppSupport();
                }
            });

            // Add WhatsApp icon to browser tab title when needed
            const originalTitle = document.title;
            let hasNotification = false;

            // Simulate new message notification (remove this in production)
            setTimeout(() => {
                if (!hasNotification) {
                    document.title = '💬 ' + originalTitle;
                    hasNotification = true;

                    // Reset after 5 seconds
                    setTimeout(() => {
                        document.title = originalTitle;
                        hasNotification = false;
                    }, 5000);
                }
            }, 10000);
        });
    </script>
@endpush

@push('styles')
    <style>
        /* WhatsApp-specific animations */
        @keyframes ping-slow {
            0% {
                transform: scale(1);
                opacity: 0.8;
            }
            100% {
                transform: scale(1.5);
                opacity: 0;
            }
        }

        .animate-ping {
            animation: ping-slow 2s cubic-bezier(0, 0, 0.2, 1) infinite;
        }

        /* WhatsApp card hover effect */
        [onclick*="openWhatsAppSupport"] {
            transition: all 0.3s ease;
        }

        [onclick*="openWhatsAppSupport"]:hover {
            transform: translateY(-2px);
            box-shadow: 0 10px 25px rgba(34, 197, 94, 0.2);
        }

        /* Responsive WhatsApp button */
        @media (max-width: 768px) {
            .fixed.bottom-6.right-6 {
                bottom: 5rem;
                right: 1rem;
            }
        }
    </style>
@endpush
