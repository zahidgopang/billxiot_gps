@php
    $current = app()->getLocale();
@endphp
<div class="lang-toggle" role="group" aria-label="{{ __('app.language.label') }}" dir="ltr">
    <a href="{{ route('locale.switch', 'en') }}"
       class="lang-toggle__btn {{ $current === 'en' ? 'lang-toggle__btn--active' : '' }}"
       title="{{ __('app.language.english') }}"
       data-lang-switch
       @if($current === 'en') aria-current="true" @endif>EN</a>
    <a href="{{ route('locale.switch', 'ar') }}"
       class="lang-toggle__btn lang-toggle__btn--ar {{ $current === 'ar' ? 'lang-toggle__btn--active' : '' }}"
       title="{{ __('app.language.arabic') }}"
       lang="ar"
       data-lang-switch
       @if($current === 'ar') aria-current="true" @endif>AR</a>
</div>
<script>
    document.querySelectorAll('[data-lang-switch]').forEach(function (link) {
        link.addEventListener('click', function () {
            var group = link.closest('.lang-toggle');
            if (group) group.classList.add('lang-toggle--busy');
            document.documentElement.classList.add('lang-switch-pending');
        });
    });
</script>
