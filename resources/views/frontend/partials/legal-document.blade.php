{{-- Renders structured legal content from lang/legal.php --}}
@php
    $doc = __('legal.'.$document);
@endphp

<section class="relative min-h-[50vh] flex items-center overflow-hidden">
    <div class="absolute inset-0 bg-gradient-to-br from-slate-50 via-blue-50 to-sky-100 dark:from-slate-950 dark:via-blue-950 dark:to-sky-950"></div>
    <div class="relative max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-20 text-center">
        <span class="inline-block px-4 py-1.5 rounded-full font-semibold text-sm mb-4 {{ $doc['badge_class'] ?? 'bg-blue-500/10 text-blue-600 dark:text-blue-400' }}">
            {{ $doc['badge'] }}
        </span>
        <h1 class="text-4xl lg:text-5xl font-bold mb-4">
            {{ $doc['title'] }}
            @if(!empty($doc['title_highlight']))
                <span class="bg-gradient-to-r {{ $doc['title_gradient'] ?? 'from-blue-500 to-indigo-600' }} bg-clip-text text-transparent">{{ $doc['title_highlight'] }}</span>
            @endif
        </h1>
        @if(!empty($doc['subtitle']))
            <p class="text-lg text-slate-600 dark:text-slate-300 max-w-3xl mx-auto">{{ $doc['subtitle'] }}</p>
        @endif
        <p class="text-sm text-slate-500 dark:text-slate-400 mt-4">{{ __('legal.last_updated', ['date' => now()->translatedFormat(app()->getLocale() === 'ar' ? 'j F Y' : 'F j, Y')]) }}</p>
    </div>
</section>

<section class="py-16">
    <div class="max-w-4xl mx-auto px-4 sm:px-6 lg:px-8">
        <div class="prose prose-lg dark:prose-invert max-w-none">
            @foreach($doc['sections'] ?? [] as $section)
                <div class="mb-10">
                    @if(!empty($section['heading']))
                        <h2 class="text-2xl font-bold mb-4 text-slate-900 dark:text-white">{{ $section['heading'] }}</h2>
                    @endif
                    @foreach($section['paragraphs'] ?? [] as $paragraph)
                        <p class="text-slate-600 dark:text-slate-400 mb-4 leading-relaxed">{{ $paragraph }}</p>
                    @endforeach
                    @if(!empty($section['list']))
                        <ul class="list-disc ps-6 space-y-2 text-slate-600 dark:text-slate-400 mb-4">
                            @foreach($section['list'] as $item)
                                <li>{{ $item }}</li>
                            @endforeach
                        </ul>
                    @endif
                    @if(!empty($section['cards']))
                        <div class="grid md:grid-cols-2 gap-4 not-prose">
                            @foreach($section['cards'] as $card)
                                <div class="p-4 rounded-xl border border-slate-200 dark:border-slate-800 bg-slate-50 dark:bg-slate-900/50">
                                    @if(!empty($card['title']))
                                        <h4 class="font-bold mb-2 text-slate-900 dark:text-white">{{ $card['title'] }}</h4>
                                    @endif
                                    <p class="text-sm text-slate-600 dark:text-slate-400">{{ $card['text'] }}</p>
                                </div>
                            @endforeach
                        </div>
                    @endif
                </div>
            @endforeach
        </div>

        @if(!empty($doc['contact']))
            <div class="mt-12 p-6 rounded-xl bg-blue-50 dark:bg-blue-900/20 border border-blue-200 dark:border-blue-800">
                <h3 class="font-bold mb-2">{{ $doc['contact']['title'] }}</h3>
                <p class="text-slate-600 dark:text-slate-400 mb-3">{{ $doc['contact']['text'] }}</p>
                <p class="text-slate-700 dark:text-slate-300">
                    <strong>{{ __('legal.email') }}:</strong>
                    <a href="mailto:{{ $doc['contact']['email'] }}" class="text-blue-600 dark:text-blue-400">{{ $doc['contact']['email'] }}</a>
                </p>
            </div>
        @endif
    </div>
</section>
