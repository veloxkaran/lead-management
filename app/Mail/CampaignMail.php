<?php

namespace App\Mail;

use App\Support\HtmlToText;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Attachment;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Mail\Mailables\Headers;

/**
 * A campaign email, built to stay out of spam folders: a plain-text part
 * alongside the HTML, a light layout, the sender's signature and footer
 * (company address), and an unsubscribe link backed by the one-click
 * List-Unsubscribe headers Gmail and Yahoo require from bulk senders.
 *
 * Images are embedded inline (cid:) below the message, so they show
 * without "load images"; PDFs go as regular attachments.
 *
 * The optional 1×1 tracking image marks the recipient "Delivered" when
 * loaded (CampaignTrackingController::open) — plain SMTP gives no
 * delivery receipt, so an opened email is the confirmation.
 */
class CampaignMail extends Mailable
{
    use Queueable;

    /**
     * @param  array<int, array{path: string, width: int, name: string, mime: string}>  $images  shown inside the email
     * @param  array<int, array{path: string, width: int, name: string, mime: string}>  $documents  attached (PDFs)
     */
    public function __construct(
        public string $renderedSubject,
        public string $renderedBody,
        public ?string $trackingUrl = null,
        public ?string $unsubscribeUrl = null,
        public ?string $signatureHtml = null,
        public ?string $footer = null,
        public array $images = [],
        public array $documents = [],
        // The rich-text body (already personalized and safe); without it the
        // plain renderedBody is shown with its line breaks. renderedBody is
        // always the plain-text version, for the text part.
        public ?string $bodyHtml = null,
    ) {
    }

    /**
     * @return array<int, Attachment>
     */
    public function attachments(): array
    {
        return array_map(
            fn (array $document) => Attachment::fromPath($document['path'])->as($document['name'])->withMime($document['mime']),
            $this->documents,
        );
    }

    public function envelope(): Envelope
    {
        return new Envelope(subject: $this->renderedSubject);
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
            view: 'emails.campaign',
            text: 'emails.campaign-text',
            with: [
                'body' => $this->renderedBody,
                'bodyHtml' => $this->bodyHtml ?? nl2br(e($this->renderedBody)),
                'signatureHtml' => $this->signatureHtml,
                'signatureText' => $this->signatureHtml ? HtmlToText::convert($this->signatureHtml) : null,
                'footer' => $this->footer,
                'trackingUrl' => $this->trackingUrl,
                'unsubscribeUrl' => $this->unsubscribeUrl,
                'images' => $this->images,
                'documents' => $this->documents,
            ],
        );
    }
}
