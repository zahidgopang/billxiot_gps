@extends('frontend.layout')

@section('title', __('seo.pages.android-app.title'))
@section('description', __('seo.pages.android-app.description'))

@section('content')
@php
    $steps = [
        [
            'number' => 1,
            'title' => 'Download the FalconEyeGPS APK',
            'body' => 'Tap the download button below. Your browser will save FalconEyeGPS.apk to your Downloads folder.',
            'icon' => 'fa-download',
        ],
        [
            'number' => 2,
            'title' => 'Open the downloaded file',
            'body' => 'Open your Downloads folder or notification shade and tap FalconEyeGPS.apk to start installation.',
            'icon' => 'fa-folder-open',
        ],
        [
            'number' => 3,
            'title' => 'If Android shows a security prompt',
            'body' => 'If you see “This file may be harmful”, tap Open file. This warning appears for APKs downloaded outside Google Play.',
            'icon' => 'fa-triangle-exclamation',
        ],
        [
            'number' => 4,
            'title' => 'If Google Play Protect appears',
            'body' => 'If you see “App blocked to protect your device”, tap More details, then Install anyway.',
            'icon' => 'fa-shield-halved',
        ],
        [
            'number' => 5,
            'title' => 'Complete installation and open the app',
            'body' => 'Follow the on-screen prompts, then tap Open or find FalconEyeGPS on your home screen and sign in.',
            'icon' => 'fa-circle-check',
        ],
    ];

    $faqs = [
        [
            'q' => 'Why do I see a Play Protect warning?',
            'a' => 'Because the application is distributed directly from our website and has not yet built reputation through Google Play Store.',
        ],
        [
            'q' => 'Is FalconEyeGPS malware?',
            'a' => 'No. FalconEyeGPS is our official GPS tracking application distributed through our official website.',
        ],
        [
            'q' => 'Why is the app not on Google Play yet?',
            'a' => 'We are preparing our Google Play Store listing. Until then, the official APK is available only from this page. An iOS version for iPhone is also coming soon.',
        ],
        [
            'q' => 'Can I install on any Android phone?',
            'a' => 'Yes, on Android 7.0 and newer with enough storage for the APK (about 62 MB). Enable “Install unknown apps” for your browser if prompted.',
        ],
    ];
@endphp

    {{-- Hero --}}
    <section class="relative overflow-hidden">
        <div class="absolute inset-0 bg-gradient-to-br from-slate-50 via-blue-50 to-sky-100 dark:from-slate-950 dark:via-slate-900 dark:to-sky-950"></div>
        <div class="absolute top-1/4 left-1/4 w-96 h-96 bg-gradient-to-r from-sky-300/20 to-blue-400/10 rounded-full blur-3xl"></div>

        <div class="relative max-w-4xl mx-auto px-4 sm:px-6 lg:px-8 py-20 text-center">
            <div class="text-left mb-8">
                @include('frontend.partials.page-breadcrumbs')
            </div>
            <span class="inline-flex items-center gap-2 px-4 py-1.5 bg-gradient-to-r from-green-500/10 to-emerald-500/10 rounded-full text-green-700 dark:text-green-400 font-semibold text-sm mb-4">
                <i class="fa-brands fa-android"></i>
                Official Android App
            </span>
            <h1 class="text-4xl sm:text-5xl font-bold mb-4">
                Download <span class="bg-gradient-to-r from-sky-500 to-blue-600 bg-clip-text text-transparent">FalconEyeGPS</span>
            </h1>
            <p class="text-lg text-slate-600 dark:text-slate-300 max-w-2xl mx-auto">
                Fleet tracking on your phone — live map, alerts, geofences, and profile sync with the web dashboard.
            </p>
            <div class="mt-5 flex flex-col sm:flex-row items-center justify-center gap-3">
                <span class="inline-flex items-center gap-2 text-sm text-amber-700 dark:text-amber-300 bg-amber-50 dark:bg-amber-900/20 border border-amber-200 dark:border-amber-800 rounded-full px-4 py-2">
                    <i class="fa-brands fa-google-play"></i>
                    Google Play Store version coming soon
                </span>
                <span class="inline-flex items-center gap-2 text-sm text-slate-700 dark:text-slate-300 bg-slate-100 dark:bg-slate-800/60 border border-slate-200 dark:border-slate-700 rounded-full px-4 py-2">
                    <i class="fa-brands fa-apple"></i>
                    iOS version coming soon
                </span>
            </div>
        </div>
    </section>

    <section class="py-12 sm:py-16">
        <div class="max-w-4xl mx-auto px-4 sm:px-6 lg:px-8 space-y-10">

            {{-- Why warnings appear --}}
            <div class="p-6 sm:p-8 rounded-2xl glass border border-sky-200 dark:border-sky-800 bg-sky-50/50 dark:bg-sky-950/20">
                <div class="flex items-start gap-4">
                    <div class="w-12 h-12 rounded-xl bg-gradient-to-br from-sky-500 to-blue-600 flex items-center justify-center flex-shrink-0">
                        <i class="fa-solid fa-circle-info text-white text-xl"></i>
                    </div>
                    <div>
                        <h2 class="text-xl font-bold mb-3">Why Android may show security warnings</h2>
                        <p class="text-slate-600 dark:text-slate-400 leading-relaxed">
                            FalconEyeGPS is currently distributed directly from our official website and is not yet published on Google Play Store. Because of this, Android may display a Play Protect or Unknown App warning. This is expected for direct APK installations.
                        </p>
                    </div>
                </div>
            </div>

            {{-- How to Install --}}
            <div>
                <div class="flex items-center gap-3 mb-8">
                    <div class="w-10 h-10 rounded-xl bg-gradient-to-br from-green-500 to-emerald-600 flex items-center justify-center">
                        <i class="fa-solid fa-list-ol text-white"></i>
                    </div>
                    <div>
                        <h2 class="text-2xl sm:text-3xl font-bold">How to Install</h2>
                        <p class="text-slate-600 dark:text-slate-400 text-sm">Follow these steps on your Android phone</p>
                    </div>
                </div>

                <div class="space-y-8">
                    @foreach($steps as $step)
                        <article class="rounded-2xl glass border border-slate-200 dark:border-slate-800 overflow-hidden">
                            <div class="p-6 sm:p-8">
                                <div class="flex items-start gap-4 mb-4">
                                    <div class="flex-shrink-0 w-10 h-10 rounded-full bg-gradient-to-br from-sky-500 to-blue-600 text-white font-bold flex items-center justify-center text-lg">
                                        {{ $step['number'] }}
                                    </div>
                                    <div class="flex-1 min-w-0">
                                        <div class="flex items-center gap-2 mb-2">
                                            <i class="fa-solid {{ $step['icon'] }} text-sky-600 dark:text-sky-400"></i>
                                            <h3 class="text-lg font-bold">Step {{ $step['number'] }}: {{ $step['title'] }}</h3>
                                        </div>
                                        <p class="text-slate-600 dark:text-slate-400">{{ $step['body'] }}</p>
                                    </div>
                                </div>
                            </div>
                            <div class="bg-slate-100 dark:bg-slate-900/50 px-4 sm:px-8 py-6 flex justify-center border-t border-slate-200 dark:border-slate-800">
                                @include('frontend.partials.android-install-step-visual', ['step' => $step['number']])
                            </div>
                        </article>
                    @endforeach
                </div>
            </div>

            {{-- Security notice --}}
            <div class="p-6 sm:p-8 rounded-2xl border-2 border-emerald-300 dark:border-emerald-700 bg-emerald-50 dark:bg-emerald-950/20">
                <div class="flex items-start gap-4">
                    <div class="w-12 h-12 rounded-xl bg-emerald-600 flex items-center justify-center flex-shrink-0">
                        <i class="fa-solid fa-lock text-white text-xl"></i>
                    </div>
                    <div>
                        <h2 class="text-xl font-bold text-emerald-900 dark:text-emerald-300 mb-2">Security notice</h2>
                        <p class="text-emerald-900/80 dark:text-emerald-200/90 leading-relaxed">
                            Please download FalconEyeGPS only from the official website:
                            <a href="{{ $officialSite }}" class="font-semibold underline hover:no-underline break-all">{{ $officialSite }}</a>
                        </p>
                        <p class="mt-2 text-emerald-800 dark:text-emerald-300/90 font-medium">
                            Do not install APKs from unofficial sources.
                        </p>
                    </div>
                </div>
            </div>

            {{-- Download button --}}
            <div id="download" class="p-8 sm:p-10 rounded-2xl glass border border-slate-200 dark:border-slate-800 text-center">
                <div class="w-16 h-16 mx-auto mb-4 rounded-2xl bg-gradient-to-br from-green-500 to-lime-600 flex items-center justify-center">
                    <i class="fa-brands fa-android text-white text-3xl"></i>
                </div>
                <h2 class="text-2xl font-bold mb-2">Download FalconEyeGPS APK</h2>
                <p class="text-slate-600 dark:text-slate-400 mb-6 max-w-lg mx-auto">
                    Official release for Android. Requires Android 7.0 or newer.
                    @if($apkSizeMb)
                        <span class="block mt-1 text-sm">File size: ~{{ $apkSizeMb }} MB</span>
                    @endif
                    <span class="block mt-2 text-sm text-slate-500 dark:text-slate-400">
                        <i class="fa-brands fa-apple mr-1"></i> iPhone / iOS app — coming soon
                    </span>
                </p>

                @if($apkAvailable)
                    <a href="{{ $apkUrl }}"
                       download="FalconEyeGPS.apk"
                       class="inline-flex items-center justify-center gap-3 px-8 py-4 bg-gradient-to-r from-green-600 to-emerald-700 text-white font-semibold rounded-xl shadow-lg hover:shadow-xl hover:scale-[1.02] transition-all duration-300 w-full sm:w-auto">
                        <i class="fa-solid fa-download text-lg"></i>
                        Download APK
                    </a>
                    <p class="mt-4 text-xs text-slate-500 dark:text-slate-400">
                        After downloading, follow the steps above if Android shows any warnings.
                    </p>
                @else
                    <div class="inline-flex items-center gap-2 px-6 py-3 rounded-xl bg-amber-50 dark:bg-amber-900/20 text-amber-800 dark:text-amber-300 border border-amber-200 dark:border-amber-800">
                        <i class="fa-solid fa-clock"></i>
                        APK is being updated. Please check back shortly or contact support.
                    </div>
                @endif
            </div>

            {{-- FAQ --}}
            <div>
                <div class="flex items-center gap-3 mb-6">
                    <div class="w-10 h-10 rounded-xl bg-gradient-to-br from-purple-500 to-indigo-600 flex items-center justify-center">
                        <i class="fa-solid fa-circle-question text-white"></i>
                    </div>
                    <h2 class="text-2xl sm:text-3xl font-bold">Frequently Asked Questions</h2>
                </div>

                <div class="space-y-3">
                    @foreach($faqs as $faq)
                        <details class="group rounded-xl glass border border-slate-200 dark:border-slate-800 overflow-hidden">
                            <summary class="flex items-center justify-between gap-4 p-5 sm:p-6 cursor-pointer list-none hover:border-sky-300 dark:hover:border-sky-700 transition-all [&::-webkit-details-marker]:hidden">
                                <h3 class="font-semibold text-base sm:text-lg pr-2">{{ $faq['q'] }}</h3>
                                <i class="fa-solid fa-chevron-down text-slate-500 flex-shrink-0 transition-transform group-open:rotate-180"></i>
                            </summary>
                            <div class="px-5 sm:px-6 pb-5 sm:pb-6 text-slate-600 dark:text-slate-400 leading-relaxed border-t border-slate-200 dark:border-slate-800 pt-4">
                                {{ $faq['a'] }}
                            </div>
                        </details>
                    @endforeach
                </div>
            </div>

            {{-- Help --}}
            <div class="text-center pb-8">
                <p class="text-slate-600 dark:text-slate-400 mb-4">Need help installing the app?</p>
                <a href="{{ route('contact') }}"
                   class="inline-flex items-center gap-2 text-sky-600 dark:text-sky-400 font-semibold hover:underline">
                    <i class="fa-solid fa-headset"></i>
                    Contact support
                </a>
            </div>
        </div>
    </section>
@endsection
