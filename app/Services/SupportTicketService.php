<?php

namespace App\Services;

use App\Enums\RequirementStatus;
use App\Enums\SupportTicketAssignmentAction;
use App\Models\Lead;
use App\Models\SupportTicket;
use App\Models\SupportTicketComment;
use App\Models\User;
use App\Repositories\SupportTicketRepository;
use Illuminate\Http\UploadedFile;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;

class SupportTicketService
{
    public function __construct(protected SupportTicketRepository $tickets)
    {
    }

    public function list(array $filters, int $perPage = 20): LengthAwarePaginator
    {
        return $this->tickets->filter($filters, $perPage);
    }

    /**
     * @param  array<int, UploadedFile|null>  $files
     */
    public function create(array $attributes, User $raiser, array $files = []): SupportTicket
    {
        return DB::transaction(function () use ($attributes, $raiser, $files) {
            $assignedTo = $attributes['assigned_to'] ?? null;
            unset($attributes['assigned_to']);
            $attributes['raised_by'] = $raiser->id;

            /** @var SupportTicket $ticket */
            $ticket = $this->tickets->create($attributes);

            if ($assignedTo !== null) {
                $this->assign($ticket, (int) $assignedTo, $raiser);
            }

            $this->storeAttachments($ticket, $files);

            return $ticket->load('attachments');
        });
    }

    /**
     * @param  array<int, UploadedFile|null>  $files
     */
    public function createForLead(Lead $lead, array $attributes, User $raiser, array $files = []): SupportTicket
    {
        $attributes['lead_id'] = $lead->id;

        return $this->create($attributes, $raiser, $files);
    }

    /**
     * @param  array<int, UploadedFile|null>  $files
     */
    public function update(SupportTicket $ticket, array $attributes, User $actor, array $files = []): SupportTicket
    {
        if (($attributes['status'] ?? null) === RequirementStatus::Completed->value && ! $ticket->resolved_at) {
            $attributes['resolved_at'] = now();
        }

        if (array_key_exists('assigned_to', $attributes)) {
            $assignedTo = $attributes['assigned_to'];
            unset($attributes['assigned_to']);
            $this->assign($ticket, $assignedTo !== null ? (int) $assignedTo : null, $actor);
        }

        $ticket = $this->tickets->update($ticket, $attributes);

        $this->storeAttachments($ticket, $files);

        return $ticket;
    }

    public function delete(SupportTicket $ticket): void
    {
        $this->tickets->delete($ticket);
    }

    /**
     * assigned_by/assigned_at always move together with assigned_to rather
     * than being independently settable — they log who performed *this*
     * assignment and when, so clearing assigned_to (unassigning) clears
     * them too instead of leaving a stale "assigned by/at" behind.
     *
     * Every actual transition also appends to assignmentLogs(): a change
     * away from a previous assignee logs an Unassigned entry for them, and
     * a change onto a new assignee logs an Assigned entry — a direct
     * reassignment (A to B) logs both, so both event types are always
     * individually visible in the history rather than only the latest
     * assigned_to value. Resubmitting the same assignee is a no-op (no
     * update, no log entry). Mirrors RawDataService::assign().
     */
    public function assign(SupportTicket $ticket, ?int $assignedTo, User $actor): SupportTicket
    {
        $previousAssignedTo = $ticket->assigned_to;

        if ($assignedTo === $previousAssignedTo) {
            return $ticket;
        }

        $ticket->update([
            'assigned_to' => $assignedTo,
            'assigned_by' => $assignedTo ? $actor->id : null,
            'assigned_at' => $assignedTo ? now() : null,
        ]);

        if ($previousAssignedTo !== null) {
            $ticket->assignmentLogs()->create([
                'action' => SupportTicketAssignmentAction::Unassigned,
                'user_id' => $previousAssignedTo,
                'performed_by' => $actor->id,
            ]);
        }

        if ($assignedTo !== null) {
            $ticket->assignmentLogs()->create([
                'action' => SupportTicketAssignmentAction::Assigned,
                'user_id' => $assignedTo,
                'performed_by' => $actor->id,
            ]);
        }

        return $ticket;
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

    /**
     * Shared by create() and update() — documents can be added when a
     * ticket is raised and appended any time afterward, mirroring
     * ReleaseNoteService's storeAttachments() pattern.
     *
     * @param  array<int, UploadedFile|null>  $files
     */
    private function storeAttachments(SupportTicket $ticket, array $files): void
    {
        foreach ($files as $file) {
            if (! $file instanceof UploadedFile) {
                continue;
            }

            $path = $file->store("support-tickets/{$ticket->id}", 'public');

            $ticket->attachments()->create([
                'disk_path' => $path,
                'original_name' => $file->getClientOriginalName(),
                'mime_type' => $file->getClientMimeType(),
                'size' => $file->getSize(),
            ]);
        }
    }
}
