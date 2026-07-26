<?php

namespace App\Services;

use App\Enums\RequirementStatus;
use App\Models\SupportTicket;
use App\Models\SupportTicketComment;
use App\Models\User;
use App\Repositories\SupportTicketRepository;
use Illuminate\Pagination\LengthAwarePaginator;

class SupportTicketService
{
    public function __construct(protected SupportTicketRepository $tickets)
    {
    }

    public function list(array $filters, int $perPage = 20): LengthAwarePaginator
    {
        return $this->tickets->filter($filters, $perPage);
    }

    public function create(array $attributes, User $raiser): SupportTicket
    {
        $attributes['raised_by'] = $raiser->id;

        return $this->tickets->create($attributes);
    }

    public function update(SupportTicket $ticket, array $attributes): SupportTicket
    {
        if (($attributes['status'] ?? null) === RequirementStatus::Completed->value && ! $ticket->resolved_at) {
            $attributes['resolved_at'] = now();
        }

        return $this->tickets->update($ticket, $attributes);
    }

    public function delete(SupportTicket $ticket): void
    {
        $this->tickets->delete($ticket);
    }

    public function addComment(SupportTicket $ticket, array $attributes, User $author): SupportTicketComment
    {
        return $ticket->comments()->create([
            ...$attributes,
            'author_id' => $author->id,
        ]);
    }

    public function updateComment(SupportTicketComment $comment, array $attributes): SupportTicketComment
    {
        $comment->update($attributes);

        return $comment;
    }
}
