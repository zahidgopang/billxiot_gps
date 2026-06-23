<?php

namespace App\Services;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\RateLimiter;

class BotProtectionService
{
    /**
     * @return array{ok: bool, fake_success?: bool, message?: string, status?: int, log_reason?: string}
     */
    public function inspect(Request $request, string $context, ?string $recaptchaAction = null): array
    {
        if ($this->honeypotTripped($request)) {
            Log::warning('Bot honeypot triggered', [
                'context' => $context,
                'ip' => $request->ip(),
            ]);

            return ['ok' => false, 'fake_success' => true, 'log_reason' => 'honeypot'];
        }

        if ($this->suspiciousUserAgent($request)) {
            Log::warning('Blocked suspicious user agent', [
                'context' => $context,
                'ip' => $request->ip(),
                'user_agent' => $request->userAgent(),
            ]);

            return ['ok' => false, 'fake_success' => true, 'log_reason' => 'user_agent'];
        }

        if ($this->timingInvalid($request)) {
            Log::warning('Bot timing check failed', [
                'context' => $context,
                'ip' => $request->ip(),
                'started_at' => $request->input('form_started_at'),
            ]);

            return [
                'ok' => false,
                'message' => 'Please wait a moment before submitting the form.',
                'status' => 422,
                'log_reason' => 'timing',
            ];
        }

        $rateLimitMessage = $this->rateLimitMessage($request, $context);
        if ($rateLimitMessage !== null) {
            return [
                'ok' => false,
                'message' => $rateLimitMessage,
                'status' => 429,
                'log_reason' => 'rate_limit',
            ];
        }

        $action = $recaptchaAction ?? $context;
        $recaptcha = RecaptchaService::verify(
            $request->input('g-recaptcha-response'),
            $request->ip(),
            $action
        );

        if (! $recaptcha['ok']) {
            Log::warning('reCAPTCHA blocked submission', [
                'context' => $context,
                'ip' => $request->ip(),
                'score' => $recaptcha['score'] ?? null,
            ]);

            return [
                'ok' => false,
                'message' => $recaptcha['message'] ?? 'Security check failed. Please try again.',
                'status' => 422,
                'log_reason' => 'recaptcha',
            ];
        }

        return ['ok' => true, 'recaptcha' => $recaptcha];
    }

    public function recordAttempt(Request $request, string $context, ?string $email = null): void
    {
        $ipKey = $this->ipRateKey($context, $request->ip());
        $cfg = config("bot_protection.{$context}", []);
        RateLimiter::hit($ipKey, (int) ($cfg['ip_decay_seconds'] ?? 3600));

        if ($email !== null && $context === 'contact') {
            $emailKey = 'contact-email:'.hash('sha256', strtolower(trim($email)));
            RateLimiter::hit($emailKey, (int) ($cfg['email_decay_seconds'] ?? 3600));
        }
    }

    public function honeypotTripped(Request $request): bool
    {
        foreach (config('bot_protection.honeypot_fields', []) as $field) {
            if (filled($request->input($field))) {
                return true;
            }
        }

        return false;
    }

    public function suspiciousUserAgent(Request $request): bool
    {
        $ua = (string) $request->userAgent();
        if ($ua === '') {
            return true;
        }

        foreach (config('bot_protection.blocked_user_agent_patterns', []) as $pattern) {
            if (preg_match($pattern, $ua)) {
                return true;
            }
        }

        return false;
    }

    public function timingInvalid(Request $request): bool
    {
        $startedAt = $request->input('form_started_at');
        if (! is_numeric($startedAt)) {
            return true;
        }

        $elapsed = time() - (int) $startedAt;
        $min = (int) config('bot_protection.min_form_seconds', 3);
        $max = (int) config('bot_protection.max_form_seconds', 7200);

        return $elapsed < $min || $elapsed > $max;
    }

    public function rateLimitMessage(Request $request, string $context): ?string
    {
        $cfg = config("bot_protection.{$context}", []);
        $ipKey = $this->ipRateKey($context, $request->ip());
        $ipMax = (int) ($cfg['ip_max_attempts'] ?? 5);

        if (RateLimiter::tooManyAttempts($ipKey, $ipMax)) {
            $minutes = (int) ceil(RateLimiter::availableIn($ipKey) / 60);

            return "Too many attempts. Please try again in {$minutes} minute(s).";
        }

        if ($context === 'contact') {
            $email = strtolower(trim((string) $request->input('email', '')));
            if ($email !== '') {
                $emailKey = 'contact-email:'.hash('sha256', $email);
                $emailMax = (int) ($cfg['email_max_attempts'] ?? 2);
                if (RateLimiter::tooManyAttempts($emailKey, $emailMax)) {
                    $minutes = (int) ceil(RateLimiter::availableIn($emailKey) / 60);

                    return "Too many messages from this email. Please try again in {$minutes} minute(s).";
                }
            }
        }

        return null;
    }

    public function ipRateKey(string $context, string $ip): string
    {
        return "{$context}-form:{$ip}";
    }
}
