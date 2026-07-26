<?php

namespace App\Notifications;

use App\Models\AgendaComment;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

class AgendaCommentNotification extends Notification
{
    use Queueable;

    /**
     * @param  array<int, int>  $mentionedUserIds
     */
    public function __construct(protected AgendaComment $comment, protected array $mentionedUserIds = [])
    {
    }

    public function via(object $notifiable): array
    {
        return ['database'];
    }

    public function toArray(object $notifiable): array
    {
        $agenda = $this->comment->agenda;
        $wasMentioned = in_array($notifiable->id, $this->mentionedUserIds, true);

        $message = $wasMentioned
            ? "{$this->comment->author->name} mentioned you in \"{$agenda->title}\""
            : "{$this->comment->author->name} commented on \"{$agenda->title}\"";

        return [
            'message' => $message,
            'agenda_id' => $agenda->id,
            'comment_id' => $this->comment->id,
            'url' => route('meeting-room.index', ['agenda' => $agenda->id]),
        ];
    }
}
