<?php

namespace App\Notifications;

use App\Models\Agenda;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

class AgendaCreatedNotification extends Notification
{
    use Queueable;

    public function __construct(protected Agenda $agenda)
    {
    }

    public function via(object $notifiable): array
    {
        return ['database'];
    }

    public function toArray(object $notifiable): array
    {
        return [
            'message' => "{$this->agenda->creator?->name} raised a new agenda: \"{$this->agenda->title}\"",
            'agenda_id' => $this->agenda->id,
            'url' => route('meeting-room.index', ['agenda' => $this->agenda->id]),
        ];
    }
}
