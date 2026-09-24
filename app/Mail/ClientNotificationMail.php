<?php

namespace App\Mail;

use App\Support\EmailImage;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Attachment;
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

    /**
     * @param  array<int, string>  $imagePaths  absolute paths, embedded inline (cid:) below the body
     * @param  array<int, array{path: string, name: string, mime: ?string}>  $documents  sent as regular attachments
     */
    public function __construct(
        public string $renderedSubject,
        public string $renderedBody,
        public array $imagePaths = [],
        public array $documents = [],
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

    public function content(): Content
    {
        return new Content(
            view: 'emails.client-notification',
            with: [
                'body' => $this->renderedBody,
                'images' => array_map(fn (string $path) => [
                    'path' => $path,
                    'width' => EmailImage::displayWidth($path),
                ], $this->imagePaths),
            ],
        );
    }
}
