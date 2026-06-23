<?php
// app/Mail/ContactNotificationMail.php

namespace App\Mail;

use App\Models\ContactMessage;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class ContactNotificationMail extends Mailable
{
    use Queueable, SerializesModels;

    public $contactMessage;

    public function __construct(ContactMessage $contactMessage)
    {
        $this->contactMessage = $contactMessage;
    }

    public function envelope(): Envelope
    {
        $priority = strtoupper($this->contactMessage->priority);
        return new Envelope(
            subject: "[{$priority}] New Contact Message: {$this->contactMessage->subject}",
            tags: ['contact', 'new-message'],
            metadata: [
                'message_id' => $this->contactMessage->id,
            ],
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.contact.notification',
            with: [
                'ticketNumber' => 'TP-' . str_pad($this->contactMessage->id, 6, '0', STR_PAD_LEFT),
                'dashboardUrl' => route('admin.contact-messages.show', $this->contactMessage),
            ]
        );
    }

    public function attachments(): array
    {
        return [];
    }
}
