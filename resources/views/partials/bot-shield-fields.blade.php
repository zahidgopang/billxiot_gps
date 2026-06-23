{{-- Anti-bot honeypots + timing (sr-only — no layout or scroll side effects). --}}
@if($includeHoneypots ?? true)
    <div class="sr-only" aria-hidden="true">
        <input type="text" name="website" id="hp_website" tabindex="-1" autocomplete="off" value="">
        <input type="text" name="url" id="hp_url" tabindex="-1" autocomplete="off" value="">
        @if(empty($singleHoneypotOnly))
            <input type="text" name="honeypot" id="honeypot" tabindex="-1" autocomplete="off" value="">
        @endif
    </div>
@endif

@if($includeRecaptchaInput ?? true)
    <input type="hidden" name="g-recaptcha-response" id="g-recaptcha-response" value="">
@endif

@if($includeFormTiming ?? true)
    <input type="hidden" name="form_started_at" id="form_started_at" value="">
@endif

@if(($includeRecaptchaNotice ?? true) && ($recaptchaEnabled ?? false))
    <p @class([
        'text-xs text-slate-500 dark:text-slate-400 leading-relaxed',
        'text-center mt-3 px-2' => ($recaptchaNoticeStyle ?? '') === 'footer',
        'mt-2' => ($recaptchaNoticeStyle ?? '') !== 'footer',
    ])>
        Protected by reCAPTCHA —
        <a href="https://policies.google.com/privacy" class="underline hover:text-sky-600 dark:hover:text-sky-400" target="_blank" rel="noopener">Privacy</a>
        &
        <a href="https://policies.google.com/terms" class="underline hover:text-sky-600 dark:hover:text-sky-400" target="_blank" rel="noopener">Terms</a>
    </p>
@endif
