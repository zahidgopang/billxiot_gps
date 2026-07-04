<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class FleetAlertMail extends Mailable
{
    use Queueable, SerializesModels;

    /**
     * @param  array<string, mixed>  $context
     */
    public function __construct(
        public string $alertTitle,
        public string $alertBody,
        public array $context = [],
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: $this->alertTitle,
        );
    }

    public function content(): Content
    {
        return new Content(
            markdown: 'emails.fleet-alert',
            with: [
                'alertTitle' => $this->alertTitle,
                'alertBody' => $this->alertBody,
                'context' => $this->context,
            ],
        );
    }
}
