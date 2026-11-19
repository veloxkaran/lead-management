<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

/**
 * "Karan mentioned you on lead “Acme Traders”" — the bell notification for
 * an @mention in a comment (MentionNotifier). Opening it goes straight to
 * the record. type = "mention" powers the bell's "Mentions" filter.
 */
class MentionedNotification extends Notification
{
    use Queueable;

    public function __construct(
        protected string $authorName,
        protected string $where,
        protected string $url,
        protected string $excerpt,
    ) {
    }

    public function via(object $notifiable): array
    {
        return ['database'];
    }

    public function toArray(object $notifiable): array
    {
        return [
            'type' => 'mention',
            'message' => "{$this->authorName} mentioned you on {$this->where}: “{$this->excerpt}”",
            'url' => $this->url,
        ];
    }
}
