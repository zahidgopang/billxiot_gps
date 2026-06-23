@php
    $faqItems = $faqs ?? [];
@endphp

@if(count($faqItems) > 0)
    @push('json-ld')
        <script type="application/ld+json">{!! json_encode(\App\Support\Seo::faqSchema($faqItems), JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) !!}</script>
    @endpush
@endif

<div class="space-y-6 faq-accordion" data-faq-accordion>
    @foreach($faqItems as $index => $faq)
        <div class="faq-item rounded-xl glass border border-slate-200 dark:border-slate-800 overflow-hidden">
            <button
                type="button"
                class="faq-trigger w-full p-6 text-left hover:border-sky-300 dark:hover:border-sky-700 transition-all"
                aria-expanded="false"
                aria-controls="faq-panel-{{ $index }}"
                id="faq-trigger-{{ $index }}"
            >
                <div class="flex items-center justify-between gap-4">
                    <h4 class="font-semibold text-lg">{{ $faq['q'] }}</h4>
                    <svg class="faq-chevron w-5 h-5 text-slate-500 shrink-0 transform transition-transform duration-300"
                         fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/>
                    </svg>
                </div>
            </button>
            <div
                id="faq-panel-{{ $index }}"
                class="faq-panel hidden px-6 pb-6 text-slate-600 dark:text-slate-400 leading-relaxed"
                role="region"
                aria-labelledby="faq-trigger-{{ $index }}"
            >
                {{ $faq['a'] }}
            </div>
        </div>
    @endforeach
</div>

@once
    @push('scripts')
        <script>
            (function () {
                if (window.__faqAccordionBound) return;
                window.__faqAccordionBound = true;

                document.addEventListener('click', function (event) {
                    const trigger = event.target.closest('.faq-trigger');
                    if (!trigger) return;

                    const item = trigger.closest('.faq-item');
                    if (!item) return;

                    const panel = item.querySelector('.faq-panel');
                    const chevron = item.querySelector('.faq-chevron');
                    const isOpen = trigger.getAttribute('aria-expanded') === 'true';

                    trigger.setAttribute('aria-expanded', isOpen ? 'false' : 'true');
                    panel?.classList.toggle('hidden', isOpen);
                    chevron?.classList.toggle('rotate-180', !isOpen);
                });
            })();
        </script>
    @endpush
@endonce
