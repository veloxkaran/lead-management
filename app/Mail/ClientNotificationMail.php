<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/**
 * Sent synchronously (no ShouldQueue) — QUEUE_CONNECTION is "database" in
 * this app, which needs a running `queue:work` worker to ever process a
 * queued job. Client-facing notifications must go out reliably without
 * depending on that worker being up, so this sends inline instead.
 */
class ClientNotificationMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public string $renderedSubject,
        public string $renderedBody,
    ) {
    }

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: $this->renderedSubject,
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.client-notification',
            with: ['body' => $this->renderedBody],
        );
    }
}
