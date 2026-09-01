<?php

namespace App\Mail;

use App\Models\PhishingTarget;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class PhishingSimMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public PhishingTarget $target,
        public string $senderName,
        public string $emailSubject,
        public string $bodyTemplate
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            from: new \Illuminate\Mail\Mailables\Address(
                config('mail.from.address'),
                $this->senderName
            ),
            subject: $this->emailSubject,
        );
    }

    public function content(): Content
    {
        $link = url("/phish/{$this->target->token}");
        $body = str_replace('{{link}}', $link, $this->bodyTemplate);

        return new Content(
            text: 'emails.phishing-sim',
            with: [
                'body' => $body,
                'link' => $link,
            ],
        );
    }

    public function attachments(): array
    {
        return [];
    }
}
