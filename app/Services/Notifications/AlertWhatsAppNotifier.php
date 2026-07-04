<?php

namespace App\Services\Notifications;

use App\Models\User;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class AlertWhatsAppNotifier
{
    public function isConfigured(): bool
    {
        $sid = trim((string) config('notifications.whatsapp.twilio_account_sid'));
        $token = trim((string) config('notifications.whatsapp.twilio_auth_token'));
        $from = trim((string) config('notifications.whatsapp.twilio_from'));

        if ($sid === '' || $token === '' || $from === '') {
            return false;
        }

        // Ignore placeholder values from .env.example so alerts keep working without Twilio.
        if (str_contains($sid, 'xxxx') || str_contains($sid, 'XXXXXXXX')) {
            return false;
        }

        $tokenLower = strtolower($token);
        if (str_contains($tokenLower, 'your_auth')
            || str_contains($tokenLower, 'placeholder')
            || $tokenLower === 'changeme') {
            return false;
        }

        return true;
    }

    /**
     * @param  array<string, mixed>  $context
     */
    public function send(User $user, string $title, string $body, array $context = []): bool
    {
        if (! config('notifications.whatsapp_alerts_enabled', false)) {
            return false;
        }

        if (! $this->isConfigured()) {
            return false;
        }

        $to = $this->resolveWhatsAppNumber($user);
        if ($to === null) {
            return false;
        }

        $message = trim($title . "\n\n" . $body);
        if (! empty($context['time_display'])) {
            $message .= "\n" . $context['time_display'];
        }

        return $this->sendViaTwilio($to, $message);
    }

    private function resolveWhatsAppNumber(User $user): ?string
    {
        $code = preg_replace('/\D/', '', (string) ($user->country_code ?? ''));
        $phone = preg_replace('/\D/', '', (string) ($user->phone ?? ''));

        if ($phone === '') {
            return null;
        }

        $full = $code !== '' ? $code . $phone : $phone;

        return 'whatsapp:+' . ltrim($full, '+');
    }

    private function sendViaTwilio(string $to, string $message): bool
    {
        $sid = trim((string) config('notifications.whatsapp.twilio_account_sid'));
        $token = trim((string) config('notifications.whatsapp.twilio_auth_token'));
        $from = trim((string) config('notifications.whatsapp.twilio_from'));

        try {
            $response = Http::withBasicAuth($sid, $token)
                ->asForm()
                ->timeout(20)
                ->post("https://api.twilio.com/2010-04-01/Accounts/{$sid}/Messages.json", [
                    'From' => $from,
                    'To' => $to,
                    'Body' => mb_substr($message, 0, 1500),
                ]);

            if ($response->successful()) {
                return true;
            }

            Log::warning('WhatsApp alert failed', [
                'status' => $response->status(),
                'body' => $response->body(),
            ]);
        } catch (\Throwable $e) {
            report($e);
        }

        return false;
    }
}
