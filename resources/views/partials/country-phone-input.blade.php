@php
    $idPrefix = $idPrefix ?? 'phone';
    $countryCodeName = $countryCodeName ?? 'country_code';
    $phoneName = $phoneName ?? 'phone';
    $countryCodeValue = old($countryCodeName, filled($countryCodeValue ?? null) ? $countryCodeValue : config('countries.default_code', '+966'));
    $phoneValue = old($phoneName, $phoneValue ?? '');
    $countries = config('countries.dial_codes', []);
    $matched = collect($countries)->first(fn ($c) => $c['code'] === $countryCodeValue)
        ?? collect($countries)->first(fn ($c) => $c['code'] === config('countries.default_code'));
    $initialFlag = $matched['flag'] ?? '🇸🇦';
    $initialCode = $matched['code'] ?? $countryCodeValue;
    $inGrid = ! empty($grid);
@endphp

@once
    @push('styles')
        <link rel="stylesheet" href="{{ asset('css/country-code-selector.css') }}">
    @endpush
    @push('scripts')
        <script>window.COUNTRIES_DIAL_CODES = @json($countries);</script>
        <script src="{{ protected_js('country-code-selector.js') }}"></script>
    @endpush
@endonce

@if(! $inGrid)
<div class="mb-3">
@endif
    <label class="admin-label" for="{{ $idPrefix }}-number">{{ __('app.forms.phone') }}</label>
    <div class="phone-input-container"
         data-country-phone-row
         data-default-country-code="{{ $countryCodeValue }}"
         data-country-field="{{ $countryCodeName }}">
        <div class="country-code-selector" data-country-selector>
            <div class="selected-country">
                <span class="country-flag" data-country-flag>{{ $initialFlag }}</span>
                <span class="country-code" data-country-code-display>{{ $initialCode }}</span>
                <svg class="dropdown-arrow" xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true">
                    <polyline points="6 9 12 15 18 9"></polyline>
                </svg>
            </div>
            <div class="country-dropdown" data-country-dropdown>
                <div class="country-search">
                    <svg class="search-icon" xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true">
                        <circle cx="11" cy="11" r="8"></circle>
                        <line x1="21" y1="21" x2="16.65" y2="16.65"></line>
                    </svg>
                    <input type="text" class="country-search-input" data-country-search placeholder="{{ __('app.forms.search_country') }}" autocomplete="off">
                </div>
                <div class="country-list" data-country-list></div>
            </div>
        </div>
        <input type="hidden" name="{{ $countryCodeName }}" value="{{ $countryCodeValue }}" data-country-code-input>
        <div class="phone-number-wrapper">
            <input type="tel"
                   name="{{ $phoneName }}"
                   id="{{ $idPrefix }}-number"
                   value="{{ $phoneValue }}"
                   class="form-control form-control-sm phone-number-input admin-ltr"
                   dir="ltr"
                   maxlength="20"
                   placeholder="{{ __('app.forms.phone') }}"
                   autocomplete="tel">
        </div>
    </div>
    @error($countryCodeName) <p class="admin-field__error text-danger">{{ $message }}</p> @enderror
    @error($phoneName) <p class="admin-field__error text-danger">{{ $message }}</p> @enderror
@if(! $inGrid)
</div>
@endif
