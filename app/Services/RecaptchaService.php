<?php

namespace App\Services;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class RecaptchaService
{
    public static function isEnabled(): bool
    {
        if (! config('captcha.enabled', true)) {
            return false;
        }

        $secret = config('captcha.secret');
        $sitekey = config('captcha.sitekey');

        return filled($secret) && filled($sitekey);
    }

    /**
     * @return array{ok: bool, message?: string, score?: float, action?: string, error_codes?: array}
     */
    public static function verify(?string $token, ?string $remoteIp = null, ?string $action = null): array
    {
        if (! self::isEnabled()) {
            Log::debug('reCAPTCHA skipped (not configured)');

            return ['ok' => true, 'skipped' => true];
        }

        if (! filled($token)) {
            return [
                'ok' => false,
                'message' => 'Security token missing. Please refresh the page and try again.',
            ];
        }

        $secret = config('captcha.secret');
        $expectedAction = $action ?? config('captcha.v3.action', 'register');
        $scoreThreshold = (float) config('captcha.options.score_threshold', 0.3);

        try {
            $response = Http::asForm()
                ->timeout((int) config('captcha.options.timeout', 30))
                ->post('https://www.google.com/recaptcha/api/siteverify', [
                    'secret' => $secret,
                    'response' => $token,
                    'remoteip' => $remoteIp,
                ]);
        } catch (ConnectionException $e) {
            Log::error('reCAPTCHA siteverify connection failed', ['error' => $e->getMessage()]);

            return [
                'ok' => false,
                'message' => 'Unable to verify security check. Please try again in a moment.',
            ];
        }

        if (! $response->successful()) {
            Log::warning('reCAPTCHA siteverify HTTP error', ['status' => $response->status()]);

            return [
                'ok' => false,
                'message' => 'Security verification failed. Please try again.',
            ];
        }

        $data = $response->json();

        if (empty($data['success'])) {
            $codes = $data['error-codes'] ?? [];

            Log::warning('reCAPTCHA rejected token', ['error_codes' => $codes]);

            $message = 'Security check failed. Please try again.';
            if (in_array('invalid-input-secret', $codes, true) || in_array('missing-input-secret', $codes, true)) {
                $message = 'reCAPTCHA is misconfigured on the server (invalid secret key). Contact support.';
            } elseif (in_array('invalid-input-response', $codes, true) || in_array('timeout-or-duplicate', $codes, true)) {
                $message = 'Security token expired. Please refresh the page and try again.';
            } elseif (in_array('browser-error', $codes, true)) {
                $message = 'Browser could not complete the security check. Disable ad blockers or try another browser.';
            }

            return [
                'ok' => false,
                'message' => $message,
                'error_codes' => $codes,
            ];
        }

        $score = isset($data['score']) ? (float) $data['score'] : null;
        $returnedAction = $data['action'] ?? null;

        if ($returnedAction !== null && $returnedAction !== '' && $returnedAction !== $expectedAction) {
            Log::warning('reCAPTCHA action mismatch', [
                'expected' => $expectedAction,
                'got' => $returnedAction,
            ]);

            return [
                'ok' => false,
                'message' => 'Security check failed. Please refresh and try again.',
                'score' => $score,
                'action' => $returnedAction,
            ];
        }

        if ($score !== null && $score < $scoreThreshold) {
            Log::warning('reCAPTCHA score below threshold', [
                'score' => $score,
                'threshold' => $scoreThreshold,
            ]);

            return [
                'ok' => false,
                'message' => 'Security check flagged this attempt. Please try again.',
                'score' => $score,
                'action' => $returnedAction,
            ];
        }

        return [
            'ok' => true,
            'score' => $score,
            'action' => $returnedAction,
        ];
    }
}
