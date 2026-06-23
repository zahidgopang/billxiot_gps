@if($recaptchaEnabled ?? false)
<script src="https://www.google.com/recaptcha/api.js?render={{ $recaptchaSiteKey }}"></script>
<script>
(function () {
    const RECAPTCHA_ENABLED = @json($recaptchaEnabled ?? false);
    const RECAPTCHA_SITE_KEY = @json($recaptchaSiteKey ?? '');
    const RECAPTCHA_ACTION = @json($recaptchaAction ?? 'submit');

    window.FalconEyeBotShield = {
        initFormTiming() {
            const el = document.getElementById('form_started_at');
            if (el) {
                el.value = Math.floor(Date.now() / 1000);
            }
        },

        async acquireToken() {
            const input = document.getElementById('g-recaptcha-response');
            if (!RECAPTCHA_ENABLED) {
                if (input) input.value = '';
                return true;
            }
            if (typeof grecaptcha === 'undefined') {
                throw new Error('Security check unavailable. Refresh the page.');
            }
            return new Promise((resolve, reject) => {
                grecaptcha.ready(function () {
                    grecaptcha.execute(RECAPTCHA_SITE_KEY, { action: RECAPTCHA_ACTION })
                        .then(function (token) {
                            if (!token) {
                                reject(new Error('Empty security token'));
                                return;
                            }
                            if (input) input.value = token;
                            resolve(true);
                        })
                        .catch(reject);
                });
            });
        }
    };

    document.addEventListener('DOMContentLoaded', function () {
        window.FalconEyeBotShield.initFormTiming();
    });
})();
</script>
@else
<script>
(function () {
    window.FalconEyeBotShield = {
        initFormTiming() {
            const el = document.getElementById('form_started_at');
            if (el) el.value = Math.floor(Date.now() / 1000);
        },
        async acquireToken() { return true; }
    };
    document.addEventListener('DOMContentLoaded', function () {
        window.FalconEyeBotShield.initFormTiming();
    });
})();
</script>
@endif
