<!-- frontend/sections/android_app.blade.php -->
@php
    $apkRelative = config('contact.android_apk', 'android_apk/app-release.apk');
    $apkPath = public_path($apkRelative);
    $apkAvailable = file_exists($apkPath);
    $apkUrl = asset($apkRelative);
    $apkSizeMb = $apkAvailable ? round(filesize($apkPath) / 1024 / 1024, 1) : null;
    $officialSite = rtrim((string) config('app.url', 'https://falconeyegps.com'), '/');
@endphp

<section id="android-app" class="py-32 relative overflow-hidden scroll-mt-24">
    <div class="absolute inset-0 bg-gradient-to-b from-white via-green-50/40 to-slate-50 dark:from-slate-950 dark:via-slate-900 dark:to-slate-950"></div>
    <div class="absolute top-1/3 right-0 w-96 h-96 bg-gradient-to-r from-green-300/15 to-emerald-400/10 rounded-full blur-3xl"></div>
    <div class="absolute bottom-0 left-1/4 w-72 h-72 bg-gradient-to-r from-sky-300/10 to-blue-400/5 rounded-full blur-3xl"></div>

    <div class="relative max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
        <div class="grid lg:grid-cols-2 gap-12 lg:gap-16 items-center">
            {{-- Content --}}
            <div>
                <span class="inline-flex items-center gap-2 px-4 py-1.5 bg-gradient-to-r from-green-500/10 to-emerald-500/10 rounded-full text-green-700 dark:text-green-400 font-semibold text-sm mb-4">
                    <i class="fa-brands fa-android"></i>
                    {{ __('frontend.android_app.badge') }}
                </span>
                <h2 class="text-4xl lg:text-5xl font-bold mb-6">
                    {{ __('frontend.android_app.title') }}
                    <span class="bg-gradient-to-r from-green-500 to-emerald-600 bg-clip-text text-transparent">{{ __('frontend.android_app.title_highlight') }}</span>
                </h2>
                <p class="text-lg text-slate-600 dark:text-slate-300 mb-6 leading-relaxed">
                    {{ __('frontend.android_app.subtitle') }}
                </p>

                <ul class="space-y-3 mb-8">
                    @foreach(__('frontend.android_app.features') as $feature)
                        <li class="flex items-start gap-3 text-slate-600 dark:text-slate-400">
                            <span class="mt-1 flex-shrink-0 w-5 h-5 rounded-full bg-green-500/15 text-green-600 dark:text-green-400 flex items-center justify-center">
                                <i class="fa-solid fa-check text-[10px]"></i>
                            </span>
                            <span>{{ $feature }}</span>
                        </li>
                    @endforeach
                </ul>

                <div class="flex flex-wrap gap-2 mb-8">
                    <span class="inline-flex items-center gap-1.5 text-xs font-medium text-amber-700 dark:text-amber-300 bg-amber-50 dark:bg-amber-900/20 border border-amber-200 dark:border-amber-800 rounded-full px-3 py-1.5">
                        <i class="fa-brands fa-google-play"></i>
                        {{ __('frontend.android_app.play_soon') }}
                    </span>
                    <span class="inline-flex items-center gap-1.5 text-xs font-medium text-slate-600 dark:text-slate-300 bg-slate-100 dark:bg-slate-800 border border-slate-200 dark:border-slate-700 rounded-full px-3 py-1.5">
                        <i class="fa-brands fa-apple"></i>
                        {{ __('frontend.android_app.ios_soon') }}
                    </span>
                </div>

                @if($apkAvailable)
                    <div class="flex flex-col sm:flex-row gap-4">
                        <a href="{{ route('android-app') }}"
                           class="inline-flex items-center justify-center gap-2 px-8 py-4 bg-gradient-to-r from-green-600 to-emerald-700 text-white font-semibold rounded-xl shadow-lg hover:shadow-xl hover:scale-[1.02] transition-all">
                            <i class="fa-solid fa-book-open"></i>
                            {{ __('frontend.android_app.install_guide') }}
                        </a>
                        <a href="{{ $apkUrl }}"
                           download="FalconEyeGPS.apk"
                           class="inline-flex items-center justify-center gap-2 px-8 py-4 rounded-xl font-semibold border-2 border-green-600 text-green-700 dark:text-green-400 hover:bg-green-50 dark:hover:bg-green-950/30 transition-all">
                            <i class="fa-solid fa-download"></i>
                            {{ __('frontend.android_app.download_apk') }}
                        </a>
                    </div>
                    @if($apkSizeMb)
                        <p class="mt-4 text-sm text-slate-500 dark:text-slate-400">
                            {{ __('frontend.android_app.file_size', ['size' => $apkSizeMb]) }}
                        </p>
                    @endif
                    <p class="mt-3 text-xs text-slate-500 dark:text-slate-400">
                        <i class="fa-solid fa-lock text-emerald-600 mr-1"></i>
                        {{ __('frontend.android_app.security_note', ['url' => $officialSite]) }}
                    </p>
                @else
                    <a href="{{ route('android-app') }}"
                       class="inline-flex items-center justify-center gap-2 px-8 py-4 bg-gradient-to-r from-green-600 to-emerald-700 text-white font-semibold rounded-xl shadow-lg hover:shadow-xl transition-all">
                        <i class="fa-brands fa-android"></i>
                        {{ __('frontend.android_app.learn_more') }}
                    </a>
                @endif
            </div>

            {{-- Visual --}}
            <div class="flex justify-center lg:justify-end">
                <div class="relative w-full max-w-sm">
                    <div class="absolute -inset-4 bg-gradient-to-br from-green-400/20 to-emerald-600/10 rounded-[2.5rem] blur-2xl"></div>
                    <div class="relative rounded-[2rem] border-[6px] border-slate-800 bg-slate-900 shadow-2xl overflow-hidden ring-1 ring-slate-700">
                        <div class="h-6 bg-slate-900 flex items-center justify-center">
                            <div class="w-16 h-1.5 rounded-full bg-slate-700"></div>
                        </div>
                        <div class="bg-gradient-to-b from-[#0A0F2D] to-[#121a3a] p-6 min-h-[380px]">
                            <div class="flex items-center gap-3 mb-6">
                                <img src="{{ asset(config('branding.app_icon')) }}?v={{ @filemtime(public_path(config('branding.app_icon'))) ?: time() }}"
                                     alt="{{ config('branding.name') }}"
                                     class="w-12 h-12 rounded-2xl shadow-lg"
                                     width="48"
                                     height="48"
                                     loading="lazy">
                                <div>
                                    <p class="text-white font-bold">FalconEyeGPS</p>
                                    <p class="text-slate-400 text-xs">{{ __('frontend.meta.tagline') }}</p>
                                </div>
                            </div>
                            <div class="rounded-2xl bg-slate-800/80 border border-slate-700 p-4 mb-4">
                                <div class="flex items-center justify-between mb-3">
                                    <span class="text-slate-400 text-xs">{{ __('frontend.android_app.mock_live_map') }}</span>
                                    <span class="text-emerald-400 text-xs font-semibold">● Live</span>
                                </div>
                                <div class="h-28 rounded-xl bg-slate-900/80 border border-slate-700 flex items-center justify-center">
                                    <i class="fa-solid fa-map-location-dot text-4xl text-sky-500/40"></i>
                                </div>
                            </div>
                            <div class="grid grid-cols-2 gap-3">
                                <div class="rounded-xl bg-slate-800/60 border border-slate-700 p-3 text-center">
                                    <i class="fa-solid fa-bell text-amber-400 mb-1"></i>
                                    <p class="text-white text-xs font-semibold">{{ __('frontend.android_app.mock_alerts') }}</p>
                                </div>
                                <div class="rounded-xl bg-slate-800/60 border border-slate-700 p-3 text-center">
                                    <i class="fa-solid fa-draw-polygon text-purple-400 mb-1"></i>
                                    <p class="text-white text-xs font-semibold">{{ __('frontend.android_app.mock_geofences') }}</p>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</section>
