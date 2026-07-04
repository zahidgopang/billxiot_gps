<?php

namespace App\Services\Notifications;

use App\Mail\FleetAlertMail;
use App\Models\User;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

class AlertEmailNotifier
{
    /**
     * @param  array<string, mixed>  $context
     */
    public function send(User $user, string $title, string $body, array $context = []): bool
    {
        if (! config('notifications.email_alerts_enabled', true)) {
            return false;
        }

        $email = trim((string) $user->email);
        if ($email === '' || ! filter_var($email, FILTER_VALIDATE_EMAIL)) {
            return false;
        }

        try {
            Mail::to($email)->send(new FleetAlertMail($title, $body, $context));

            return true;
        } catch (\Throwable $e) {
            report($e);
            Log::warning('Fleet alert email failed', [
                'user_id' => $user->id,
                'error' => $e->getMessage(),
            ]);

            return false;
        }
    }
}
