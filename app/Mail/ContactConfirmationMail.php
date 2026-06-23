<?php
// app/Mail/ContactConfirmationMail.php

namespace App\Mail;

use App\Models\ContactMessage;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class ContactConfirmationMail extends Mailable
{
    use Queueable, SerializesModels;

    public $contactMessage;
    public $supportEmail;

    public function __construct(ContactMessage $contactMessage)
    {
        $this->contactMessage = $contactMessage;
        $this->supportEmail = config('contact.email', config('mail.support_email', 'support@falconeyegps.com'));
    }

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'Thank You for Contacting FalconEyeGPS',
            replyTo: [config('contact.email', 'support@falconeyegps.com')]
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.contact.confirmation',
            with: [
                'ticketNumber' => 'TP-' . str_pad($this->contactMessage->id, 6, '0', STR_PAD_LEFT),
                'estimatedResponseTime' => '24 hours',
                'supportPhone' => config('contact.whatsapp', '+923003026824'),
                'supportEmail' => config('contact.email', 'zhg786@gmail.com'),
            ]
        );
    }

    public function attachments(): array
    {
        return [];
    }
}
