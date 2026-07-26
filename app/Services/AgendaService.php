<?php

namespace App\Services;

use App\Enums\AgendaStatus;
use App\Enums\UserStatus;
use App\Models\Agenda;
use App\Models\AgendaComment;
use App\Models\User;
use App\Notifications\AgendaCommentNotification;
use App\Notifications\AgendaCreatedNotification;
use App\Notifications\AgendaStatusChangedNotification;
use App\Repositories\AgendaRepository;
use App\Support\MentionParser;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Notification;
use Illuminate\Validation\ValidationException;

class AgendaService
{
    public function __construct(protected AgendaRepository $agendas)
    {
    }

    public function list(array $filters, User $viewer, int $perPage = 20): LengthAwarePaginator
    {
        return $this->agendas->filter($filters, $viewer, $perPage);
    }

    public function create(array $attributes, User $actor): Agenda
    {
        $attributes['created_by'] = $actor->id;
        $attributes['last_updated_by'] = $actor->id;
        $attributes['status'] = AgendaStatus::Pending->value;

        /** @var Agenda $agenda */
        $agenda = $this->agendas->create($attributes);

        $this->notifyOthers($agenda, $actor, new AgendaCreatedNotification($agenda));

        return $agenda;
    }

    /**
     * Enforces the transition matrix (Pending -> Closed/Dismissed only,
     * permanent thereafter) as a business rule here, separate from
     * AgendaPolicy::update()'s "are you the creator" authorization check —
     * same split TaskPolicy/TaskService use for who vs. what's valid.
     */
    public function changeStatus(Agenda $agenda, AgendaStatus $target, User $actor): Agenda
    {
        if (! $agenda->status->canTransitionTo($target)) {
            throw ValidationException::withMessages([
                'status' => "An agenda that is {$agenda->status->label()} can no longer be changed.",
            ]);
        }

        $agenda->update([
            'status' => $target->value,
            'finalized_by' => $actor->id,
            'finalized_at' => now(),
            'last_updated_by' => $actor->id,
        ]);

        $this->notifyOthers($agenda, $actor, new AgendaStatusChangedNotification($agenda, $actor));

        return $agenda;
    }

    public function addComment(Agenda $agenda, array $attributes, User $actor): AgendaComment
    {
        /** @var AgendaComment $comment */
        $comment = $agenda->comments()->create([
            'parent_id' => $attributes['parent_id'] ?? null,
            'comment' => $attributes['comment'],
            'author_id' => $actor->id,
        ]);

        $agenda->update(['last_updated_by' => $actor->id]);

        $mentionedIds = MentionParser::extractUsers($attributes['comment'])
            ->reject(fn (User $user) => $user->id === $actor->id)
            ->pluck('id')
            ->all();

        $this->notifyOthers($agenda, $actor, new AgendaCommentNotification($comment->load('author'), $mentionedIds));

        return $comment->load('author');
    }

    /**
     * "Notify all users" per the spec — every active user except whoever
     * just performed the action. No hierarchy/assignment scoping here,
     * unlike Task/Lead notifications, since the Team Meeting Room is a
     * single shared space every user participates in.
     */
    private function notifyOthers(Agenda $agenda, User $actor, $notification): void
    {
        $recipients = User::where('status', UserStatus::Active)
            ->where('id', '!=', $actor->id)
            ->get();

        if ($recipients->isNotEmpty()) {
            Notification::send($recipients, $notification);
        }
    }
}
