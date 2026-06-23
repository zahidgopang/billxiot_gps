@php
    $current = app()->getLocale();
@endphp
<div class="lang-toggle" role="group" aria-label="{{ __('app.language.label') }}" dir="ltr">
    <a href="{{ route('locale.switch', 'en') }}"
       class="lang-toggle__btn {{ $current === 'en' ? 'lang-toggle__btn--active' : '' }}"
       title="{{ __('app.language.english') }}"
       @if($current === 'en') aria-current="true" @endif>EN</a>
    <a href="{{ route('locale.switch', 'ar') }}"
       class="lang-toggle__btn {{ $current === 'ar' ? 'lang-toggle__btn--active' : '' }}"
       title="{{ __('app.language.arabic') }}"
       @if($current === 'ar') aria-current="true" @endif>ع</a>
</div>
