<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Facades\URL;

class SendVerificationWithWelcome extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct()
    {
        //
    }

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $verificationUrl = URL::temporarySignedRoute(
            'verification.verify',
            now()->addDays(config('auth.verification.expire', 7)),
            [
                'id' => $notifiable->getKey(),
                'hash' => sha1($notifiable->getEmailForVerification()),
            ]
        );

        return (new MailMessage)
            ->subject('Welcome to ' . config('app.name') . ' - Verify Your Email')
            ->greeting('Hello ' . $notifiable->name . '!')
            ->line('Welcome to ' . config('app.name') . '! We\'re excited to have you on board.')
            ->line('Here are your account details:')
            ->line('- **Name:** ' . $notifiable->name)
            ->line('- **Email:** ' . $notifiable->email)
            ->line('- **Phone:** ' . ($notifiable->country_code . ' ' . $notifiable->phone))
            ->line('- **Registered:** ' . ($notifiable->created_at?->format('F d, Y \a\t h:i A') ?? now()->format('F d, Y \a\t h:i A')))
            ->line('Please verify your email address by clicking the button below:')
            ->action('Verify Email Address', $verificationUrl)
            ->line('The verification link will expire in ' . config('auth.verification.expire', 7) . ' days.')
            ->line('If you did not create an account, no further action is required.')
            ->salutation('Best regards,<br>' . config('app.name') . ' Team');
    }

    public function toArray(object $notifiable): array
    {
        return [];
    }
}
