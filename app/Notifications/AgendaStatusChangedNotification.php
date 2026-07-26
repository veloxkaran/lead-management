<?php

namespace App\Notifications;

use App\Models\Agenda;
use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

class AgendaStatusChangedNotification extends Notification
{
    use Queueable;

    public function __construct(protected Agenda $agenda, protected User $actor)
    {
    }

    public function via(object $notifiable): array
    {
        return ['database'];
    }

    public function toArray(object $notifiable): array
    {
        $verb = $this->agenda->status->label();

        return [
            'message' => "{$this->actor->name} marked \"{$this->agenda->title}\" as {$verb}.",
            'agenda_id' => $this->agenda->id,
            'url' => route('meeting-room.index', ['agenda' => $this->agenda->id]),
        ];
    }
}
