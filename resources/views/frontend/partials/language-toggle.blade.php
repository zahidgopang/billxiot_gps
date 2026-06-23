@php
    $current = app()->getLocale();
@endphp
<div class="frontend-lang-toggle inline-flex items-center rounded-xl overflow-hidden border border-slate-200 dark:border-slate-700 bg-slate-100/80 dark:bg-slate-800/80" role="group" aria-label="{{ __('app.language.label') }}" dir="ltr">
    <a href="{{ route('locale.switch', 'en') }}"
       class="inline-flex items-center justify-center min-w-[2.25rem] h-9 px-2.5 text-xs font-bold no-underline transition-colors {{ $current === 'en' ? 'bg-sky-600 text-white' : 'text-slate-600 dark:text-slate-400 hover:bg-slate-200 dark:hover:bg-slate-700' }}"
       title="{{ __('app.language.english') }}"
       @if($current === 'en') aria-current="true" @endif>EN</a>
    <a href="{{ route('locale.switch', 'ar') }}"
       class="inline-flex items-center justify-center min-w-[2.25rem] h-9 px-2.5 text-xs font-bold no-underline transition-colors {{ $current === 'ar' ? 'bg-sky-600 text-white' : 'text-slate-600 dark:text-slate-400 hover:bg-slate-200 dark:hover:bg-slate-700' }}"
       title="{{ __('app.language.arabic') }}"
       @if($current === 'ar') aria-current="true" @endif>ع</a>
</div>
