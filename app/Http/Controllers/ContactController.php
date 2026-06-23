<?php
// app/Http/Controllers/ContactController.php

namespace App\Http\Controllers;

use App\Http\Requests\ContactFormRequest;
use App\Models\ContactMessage;
use App\Mail\ContactConfirmationMail;
use App\Mail\ContactNotificationMail;
use App\Services\BotProtectionService;
use App\Services\RecaptchaService;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Http\Request;

class ContactController extends Controller
{
    public function show()
    {
        return view('frontend.contact', [
            'recaptchaEnabled' => RecaptchaService::isEnabled(),
            'recaptchaSiteKey' => config('captcha.sitekey'),
        ]);
    }

    public function submit(ContactFormRequest $request, BotProtectionService $bots)
    {
        $guard = $bots->inspect($request, 'contact', 'contact');

        if (! $guard['ok']) {
            if (! empty($guard['fake_success'])) {
                return response()->json([
                    'success' => true,
                    'message' => 'Thank you for your message!',
                ]);
            }

            return response()->json([
                'success' => false,
                'message' => $guard['message'] ?? 'Unable to submit your message. Please try again.',
            ], $guard['status'] ?? 422);
        }

        $bots->recordAttempt($request, 'contact', $request->email);

        if ($this->isSpam($request)) {
            Log::warning('Contact form spam detected', [
                'ip' => $request->ip(),
                'email' => $request->email,
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Your message appears to be spam. Please contact us directly if this is an error.',
            ], 422);
        }

        try {
            $recaptchaScore = $guard['recaptcha']['score'] ?? null;

            $contactMessage = DB::transaction(function () use ($request, $recaptchaScore) {
                return ContactMessage::create([
                    'name' => $request->name,
                    'email' => $request->email,
                    'phone' => $request->phone,
                    'company' => $request->company,
                    'subject' => $request->subject,
                    'message' => $request->message,
                    'ip_address' => $request->ip(),
                    'user_agent' => $request->userAgent(),
                    'metadata' => [
                        'form_source' => 'contact_page',
                        'referrer' => $request->headers->get('referer'),
                        'timezone' => $request->header('X-Timezone', 'UTC'),
                        'submission_time' => now()->toIso8601String(),
                        'recaptcha_score' => $recaptchaScore,
                    ],
                ]);
            });

            $this->sendContactEmails($contactMessage);

            Log::info('New contact message submitted', [
                'id' => $contactMessage->id,
                'email' => $contactMessage->email,
                'subject' => $contactMessage->subject,
                'ip' => $contactMessage->ip_address,
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Thank you for your message! We\'ll get back to you within 24 hours.',
                'ticket_number' => 'TP-'.str_pad((string) $contactMessage->id, 6, '0', STR_PAD_LEFT),
            ]);
        } catch (\Throwable $e) {
            Log::error('Contact form submission failed', [
                'error' => $e->getMessage(),
                'ip' => $request->ip(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Something went wrong. Please try again or contact us directly.',
                'error' => config('app.debug') ? $e->getMessage() : null,
            ], 500);
        }
    }

    /**
     * @return list<string>
     */
    private function adminRecipients(): array
    {
        $emails = collect(config('mail.admin_emails', []))
            ->filter(fn ($email) => is_string($email) && filter_var(trim($email), FILTER_VALIDATE_EMAIL))
            ->map(fn ($email) => trim($email))
            ->unique()
            ->values()
            ->all();

        if ($emails !== []) {
            return $emails;
        }

        $fallback = config('contact.email');

        return is_string($fallback) && filter_var($fallback, FILTER_VALIDATE_EMAIL)
            ? [$fallback]
            : [];
    }

    private function sendContactEmails(ContactMessage $contactMessage): void
    {
        try {
            Mail::to($contactMessage->email)->send(new ContactConfirmationMail($contactMessage));
        } catch (\Throwable $e) {
            Log::warning('Contact confirmation email failed', [
                'message_id' => $contactMessage->id,
                'error' => $e->getMessage(),
            ]);
        }

        foreach ($this->adminRecipients() as $adminEmail) {
            try {
                Mail::to($adminEmail)->send(new ContactNotificationMail($contactMessage));
            } catch (\Throwable $e) {
                Log::warning('Contact admin notification email failed', [
                    'message_id' => $contactMessage->id,
                    'admin_email' => $adminEmail,
                    'error' => $e->getMessage(),
                ]);
            }
        }
    }

    /**
     * Basic spam detection
     */
    private function isSpam($request): bool
    {
        $spamPatterns = [
            '/viagra/i',
            '/casino/i',
            '/lottery/i',
            '/xxx/i',
            '/porn/i',
        ];

        $fieldsToCheck = [
            'name' => $request->name,
            'email' => $request->email,
            'subject' => $request->subject,
            'message' => $request->message,
        ];

        foreach ($fieldsToCheck as $value) {
            foreach ($spamPatterns as $pattern) {
                if (preg_match($pattern, (string) $value)) {
                    return true;
                }
            }
        }

        return false;
    }

    public function checkRateLimit(Request $request, BotProtectionService $bots)
    {
        $cfg = config('bot_protection.contact', []);
        $ipKey = $bots->ipRateKey('contact', $request->ip());
        $ipMax = (int) ($cfg['ip_max_attempts'] ?? 3);
        $remaining = RateLimiter::remaining($ipKey, $ipMax);
        $availableIn = RateLimiter::availableIn($ipKey);

        return response()->json([
            'remaining' => $remaining,
            'available_in_seconds' => $availableIn,
            'too_many_attempts' => RateLimiter::tooManyAttempts($ipKey, $ipMax),
        ]);
    }
}
