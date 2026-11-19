<?php

namespace App\Mail;

use App\Support\EmailImage;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Attachment;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Mail\Mailables\Headers;
use Illuminate\Queue\SerializesModels;

/**
 * Client notifications (requirement / support ticket updates) and
 * announcements. Sent from SendClientNotificationEmail; the Mailable itself
 * isn't queued (no ShouldQueue) since that job already is.
 *
 * Spam-filter basics: an HTML part *and* a plain-text part (HTML-only mail
 * scores as spam), and — for announcements, which are bulk mail — an
 * unsubscribe link, the one-click List-Unsubscribe headers Gmail and Yahoo
 * require, and the company footer (address) from Campaign Setup.
 */
class ClientNotificationMail extends Mailable
{
    use Queueable, SerializesModels;

    /**
     * @param  array<int, string>  $imagePaths  absolute paths, embedded inline (cid:) below the body
     * @param  array<int, array{path: string, name: string, mime: ?string}>  $documents  sent as regular attachments
     */
    public function __construct(
        public string $renderedSubject,
        public string $renderedBody,
        public array $imagePaths = [],
        public array $documents = [],
        // 1×1 image whose loading marks the email log "Delivered" (opened).
        public ?string $trackingUrl = null,
        // Announcements only — transactional notifications have neither.
        public ?string $unsubscribeUrl = null,
        public ?string $footer = null,
    ) {
    }

    /**
     * @return array<int, Attachment>
     */
    public function attachments(): array
    {
        return array_map(function (array $document) {
            $attachment = Attachment::fromPath($document['path'])->as($document['name']);

            return $document['mime'] ? $attachment->withMime($document['mime']) : $attachment;
        }, $this->documents);
    }

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: $this->renderedSubject,
        );
    }

    public function headers(): Headers
    {
        return new Headers(text: $this->unsubscribeUrl ? [
            'List-Unsubscribe' => "<{$this->unsubscribeUrl}>",
            'List-Unsubscribe-Post' => 'List-Unsubscribe=One-Click',
        ] : []);
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.client-notification',
            text: 'emails.client-notification-text',
            with: [
                'body' => $this->renderedBody,
                'unsubscribeUrl' => $this->unsubscribeUrl,
                'footer' => $this->footer,
                'documentNames' => array_column($this->documents, 'name'),
                'images' => array_map(fn (string $path) => [
                    'path' => $path,
                    'width' => EmailImage::displayWidth($path),
                ], $this->imagePaths),
                'trackingUrl' => $this->trackingUrl,
            ],
        );
    }
}
