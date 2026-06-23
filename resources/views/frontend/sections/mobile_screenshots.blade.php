@php
    $screensDir = public_path('images/mobile-app');
    $screensMeta = __('frontend.mobile_screens.items');
    $screens = [];
    $appIcon = asset(config('branding.app_icon'));
    $appIconVersion = @filemtime(public_path(config('branding.app_icon'))) ?: time();

    foreach ($screensMeta as $key => $meta) {
        $webp = $screensDir . DIRECTORY_SEPARATOR . $key . '.webp';
        $png = $screensDir . DIRECTORY_SEPARATOR . $key . '.png';
        $path = file_exists($webp) ? $webp : (file_exists($png) ? $png : null);
        if ($path) {
            $relative = 'images/mobile-app/' . basename($path);
            $version = @filemtime($path) ?: time();
            $screens[] = array_merge($meta, [
                'key' => $key,
                'src' => asset($relative) . '?v=' . $version,
                'file' => basename($path),
            ]);
        }
    }
@endphp

@if(count($screens) > 0)
<style>
    #mobile-screenshots {
        overflow: hidden;
        max-width: 100vw;
    }

    #mobile-screenshots .mobile-screens-gallery,
    #mobile-screenshots .mobile-screens-gallery > * {
        min-width: 0;
        max-width: 100%;
    }

    #mobile-screenshots .phone-mockup {
        width: min(100%, 260px);
        max-width: calc(100vw - 2.5rem);
        margin-inline: auto;
        box-sizing: border-box;
    }

    @media (min-width: 640px) {
        #mobile-screenshots .phone-mockup {
            width: min(100%, 280px);
        }
    }

    @media (min-width: 1024px) {
        #mobile-screenshots .phone-mockup {
            width: min(100%, 300px);
        }
    }

    #mobile-screenshots .phone-frame {
        width: 100%;
        box-sizing: border-box;
    }

    #mobile-screenshots .phone-screen {
        position: relative;
        --screen-h: min(52vh, 520px);
        height: var(--screen-h);
        width: min(100%, calc(var(--screen-h) * 9 / 19.5));
        margin-inline: auto;
        overflow: hidden;
        isolation: isolate;
    }

    @media (min-width: 1024px) {
        #mobile-screenshots .phone-screen {
            --screen-h: min(580px, 56vh);
        }
    }

    #mobile-screenshots .mobile-screenshot-slide {
        position: absolute;
        inset: 0;
        width: 100%;
        height: 100%;
        max-width: 100%;
        max-height: 100%;
        object-fit: cover;
        object-position: top center;
        display: block;
    }

    #mobile-screenshots .phone-notch {
        background: #000;
        box-shadow:
            0 0 0 1px rgba(255, 255, 255, 0.22),
            0 2px 8px rgba(0, 0, 0, 0.5);
    }

    #mobile-screenshots .mobile-screens-thumbs {
        scrollbar-width: thin;
        scrollbar-color: rgba(255,255,255,0.2) transparent;
        -webkit-overflow-scrolling: touch;
        overscroll-behavior-x: contain;
    }

    #mobile-screenshots .mobile-screens-thumbs::-webkit-scrollbar { height: 6px; }

    #mobile-screenshots .mobile-screens-thumbs::-webkit-scrollbar-thumb {
        background: rgba(255,255,255,0.2);
        border-radius: 999px;
    }
    #mobile-screenshots .phone-app-icon {
        width: 2.75rem;
        height: 2.75rem;
        border-radius: 0.85rem;
        box-shadow: 0 8px 24px rgba(0, 0, 0, 0.35);
        border: 1px solid rgba(255, 255, 255, 0.12);
    }

    @media (min-width: 640px) {
        #mobile-screenshots .phone-app-icon {
            width: 3rem;
            height: 3rem;
        }
    }
</style>

<section id="mobile-screenshots" class="py-20 sm:py-28 lg:py-32 relative scroll-mt-24 w-full max-w-[100vw]">
    <div class="absolute inset-0 bg-gradient-to-b from-slate-950 via-slate-900 to-slate-950"></div>
    <div class="pointer-events-none absolute top-0 left-0 w-48 h-48 sm:w-72 sm:h-72 bg-sky-500/10 rounded-full blur-3xl opacity-80"></div>
    <div class="pointer-events-none absolute bottom-0 right-0 w-56 h-56 sm:w-80 sm:h-80 bg-indigo-500/10 rounded-full blur-3xl opacity-80"></div>

    <div class="relative max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 w-full box-border">
        <div class="text-center max-w-3xl mx-auto mb-10 sm:mb-14 lg:mb-16 px-1">
            <span class="inline-flex items-center gap-2 px-4 py-1.5 rounded-full bg-sky-500/10 border border-sky-500/20 text-sky-300 font-semibold text-sm mb-5">
                <img src="{{ $appIcon }}?v={{ $appIconVersion }}" alt="" class="w-5 h-5 rounded-md" width="20" height="20" loading="lazy">
                {{ __('frontend.mobile_screens.badge') }}
            </span>
            <h2 class="text-2xl sm:text-4xl lg:text-5xl font-bold text-white mb-4 sm:mb-5 leading-tight">
                {{ __('frontend.mobile_screens.title') }}
                <span class="block bg-gradient-to-r from-sky-400 to-blue-500 bg-clip-text text-transparent">
                    {{ __('frontend.mobile_screens.title_highlight') }}
                </span>
            </h2>
            <p class="text-base sm:text-lg text-slate-400 leading-relaxed">
                {{ __('frontend.mobile_screens.subtitle') }}
            </p>
        </div>

        <div class="mobile-screens-gallery w-full box-border" data-screens-count="{{ count($screens) }}">
            <div class="flex flex-col lg:flex-row items-stretch lg:items-start gap-8 sm:gap-10 lg:gap-14 w-full">
                {{-- Phone mockup --}}
                <div class="w-full lg:w-[42%] lg:max-w-[380px] flex flex-col items-center shrink-0 mx-auto lg:mx-0 gap-4">
                    <img
                        src="{{ $appIcon }}?v={{ $appIconVersion }}"
                        alt="{{ config('branding.name') }}"
                        class="phone-app-icon"
                        width="48"
                        height="48"
                        loading="lazy"
                    >
                    <div class="phone-mockup w-full">
                        <div class="phone-frame rounded-[2rem] sm:rounded-[2.5rem] p-2 sm:p-3 bg-gradient-to-b from-slate-700 to-slate-900 shadow-xl sm:shadow-2xl ring-1 ring-white/10">
                            <div class="phone-screen rounded-[1.35rem] sm:rounded-[1.75rem] bg-slate-950">
                                @foreach($screens as $index => $screen)
                                    <img
                                        src="{{ $screen['src'] }}"
                                        alt="{{ $screen['title'] }} — FalconEyeGPS mobile app screenshot"
                                        class="mobile-screenshot-slide bg-slate-950 transition-opacity duration-500 {{ $index === 0 ? 'opacity-100 z-10' : 'opacity-0 z-0 pointer-events-none' }}"
                                        data-slide-index="{{ $index }}"
                                        loading="{{ $index === 0 ? 'eager' : 'lazy' }}"
                                        decoding="async"
                                    >
                                @endforeach
                                <div class="phone-notch pointer-events-none absolute top-2 left-1/2 z-20 h-5 sm:h-6 w-24 sm:w-28 -translate-x-1/2 rounded-full" aria-hidden="true"></div>
                            </div>
                            <div class="phone-home-indicator mx-auto mt-2 h-1 w-20 sm:w-24 rounded-full bg-white/50" aria-hidden="true"></div>
                        </div>
                    </div>
                </div>
                <div class="w-full lg:flex-1 min-w-0 box-border">
                    <div class="grid grid-cols-[2.5rem_1fr_2.5rem] sm:grid-cols-[3rem_1fr_3rem] gap-2 sm:gap-4 items-start mb-5 sm:mb-6">
                        <button type="button" class="mobile-screens-prev group w-10 h-10 sm:w-12 sm:h-12 rounded-full bg-white/5 border border-white/10 text-white hover:bg-white/10 transition-colors flex items-center justify-center" aria-label="{{ __('frontend.mobile_screens.prev') }}">
                            <i class="fa-solid fa-chevron-left text-sm sm:text-base"></i>
                        </button>
                        <div class="text-center min-w-0 px-0.5">
                            <p class="mobile-screens-counter text-xs sm:text-sm text-sky-400 font-medium mb-1">1 / {{ count($screens) }}</p>
                            <h3 class="mobile-screens-title text-lg sm:text-2xl font-bold text-white leading-snug">{{ $screens[0]['title'] }}</h3>
                            <p class="mobile-screens-desc text-slate-400 text-sm sm:text-base mt-1.5 sm:mt-2 leading-relaxed">{{ $screens[0]['description'] }}</p>
                        </div>
                        <button type="button" class="mobile-screens-next group w-10 h-10 sm:w-12 sm:h-12 rounded-full bg-white/5 border border-white/10 text-white hover:bg-white/10 transition-colors flex items-center justify-center" aria-label="{{ __('frontend.mobile_screens.next') }}">
                            <i class="fa-solid fa-chevron-right text-sm sm:text-base"></i>
                        </button>
                    </div>

                    <div class="w-full min-w-0 overflow-hidden -mx-1 px-1">
                        <div class="mobile-screens-thumbs flex gap-2.5 sm:gap-3 overflow-x-auto pb-2 snap-x snap-mandatory">
                            @foreach($screens as $index => $screen)
                                <button
                                    type="button"
                                    class="mobile-screens-thumb snap-start shrink-0 w-[3.25rem] sm:w-16 md:w-20 rounded-lg sm:rounded-xl overflow-hidden border-2 transition-all {{ $index === 0 ? 'border-sky-500 ring-2 ring-sky-500/30' : 'border-white/10 opacity-70 hover:opacity-100' }}"
                                    data-thumb-index="{{ $index }}"
                                    aria-label="{{ $screen['title'] }}"
                                >
                                    <img src="{{ $screen['src'] }}" alt="" class="block w-full aspect-[9/16] object-cover object-top bg-slate-900" loading="lazy">
                                </button>
                            @endforeach
                        </div>
                    </div>

                    <div class="mt-6 sm:mt-8 flex flex-col sm:flex-row flex-wrap gap-3">
                        <a href="{{ route('android-app') }}"
                           class="inline-flex items-center justify-center gap-2 px-5 sm:px-6 py-3 rounded-xl bg-gradient-to-r from-sky-600 to-blue-700 text-white font-semibold shadow-lg hover:shadow-xl transition-all text-sm sm:text-base">
                            <i class="fa-brands fa-android"></i>
                            {{ __('frontend.mobile_screens.cta_download') }}
                        </a>
                        <a href="{{ url('/contact') }}"
                           class="inline-flex items-center justify-center gap-2 px-5 sm:px-6 py-3 rounded-xl border border-white/20 text-white font-semibold hover:bg-white/5 transition-all text-sm sm:text-base">
                            {{ __('frontend.mobile_screens.cta_signup') }}
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </div>
</section>

@push('scripts')
<script>
(function () {
    const gallery = document.querySelector('.mobile-screens-gallery');
    if (!gallery) return;

    const slides = gallery.querySelectorAll('.mobile-screenshot-slide');
    const thumbs = gallery.querySelectorAll('.mobile-screens-thumb');
    const titleEl = gallery.querySelector('.mobile-screens-title');
    const descEl = gallery.querySelector('.mobile-screens-desc');
    const counterEl = gallery.querySelector('.mobile-screens-counter');
    const prevBtn = gallery.querySelector('.mobile-screens-prev');
    const nextBtn = gallery.querySelector('.mobile-screens-next');
    const thumbsTrack = gallery.querySelector('.mobile-screens-thumbs');

    const meta = @json(array_values(array_map(fn ($s) => ['title' => $s['title'], 'description' => $s['description']], $screens)));
    let current = 0;
    let timer;

    function show(index) {
        current = (index + slides.length) % slides.length;
        slides.forEach((slide, i) => {
            slide.classList.toggle('opacity-100', i === current);
            slide.classList.toggle('z-10', i === current);
            slide.classList.toggle('opacity-0', i !== current);
            slide.classList.toggle('z-0', i !== current);
            slide.classList.toggle('pointer-events-none', i !== current);
        });
        thumbs.forEach((thumb, i) => {
            thumb.classList.toggle('border-sky-500', i === current);
            thumb.classList.toggle('ring-2', i === current);
            thumb.classList.toggle('ring-sky-500/30', i === current);
            thumb.classList.toggle('border-white/10', i !== current);
            thumb.classList.toggle('opacity-70', i !== current);
        });
        if (titleEl) titleEl.textContent = meta[current].title;
        if (descEl) descEl.textContent = meta[current].description;
        if (counterEl) counterEl.textContent = (current + 1) + ' / ' + slides.length;

        const activeThumb = thumbs[current];
        if (activeThumb && thumbsTrack) {
            const targetLeft = activeThumb.offsetLeft - (thumbsTrack.clientWidth / 2) + (activeThumb.clientWidth / 2);
            thumbsTrack.scrollTo({ left: Math.max(0, targetLeft), behavior: 'smooth' });
        }
    }

    function next() { show(current + 1); resetTimer(); }
    function prev() { show(current - 1); resetTimer(); }

    function resetTimer() {
        clearInterval(timer);
        timer = setInterval(next, 6000);
    }

    prevBtn?.addEventListener('click', prev);
    nextBtn?.addEventListener('click', next);
    thumbs.forEach((thumb, i) => thumb.addEventListener('click', () => { show(i); resetTimer(); }));

    let touchStartX = 0;
    gallery.addEventListener('touchstart', (e) => { touchStartX = e.changedTouches[0].screenX; }, { passive: true });
    gallery.addEventListener('touchend', (e) => {
        const dx = e.changedTouches[0].screenX - touchStartX;
        if (Math.abs(dx) > 50) dx < 0 ? next() : prev();
    }, { passive: true });

    resetTimer();
})();
</script>
@endpush
@endif
