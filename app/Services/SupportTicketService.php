<?php

namespace App\Services;

use App\Enums\RequirementPriority;
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
    public function __construct(
        protected SupportTicketRepository $tickets,
        protected ClientNotifier $notifier,
    ) {
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
        $ticket = DB::transaction(function () use ($attributes, $raiser, $files) {
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

        // Sent after the transaction commits, not inside it — a rollback
        // must never leave an already-sent email describing a ticket that
        // no longer exists.
        $this->notifier->notify('support_ticket_created', $ticket->lead?->email, [
            ...$this->clientVariables($ticket),
        ], $ticket);

        return $ticket;
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
     * Raised anonymously through the public client self-service portal
     * (ClientSupportController), not by an authenticated staff member — so
     * there's no real User to attribute it to. support_tickets.raised_by is
     * NOT NULL and this app has no system/bot-user account, so it's set to
     * the lead's assigned rep (falling back to whoever created the lead,
     * always present) purely for FK integrity; `is_client_submitted` is
     * what views actually key off to label these "Client (self-service
     * portal)" instead of that staff member's name. Reuses createForLead()
     * as-is (same transaction/attachment handling), just with a synthetic
     * raiser and no attachments.
     *
     * `company_id` is set explicitly from the lead rather than left to
     * BelongsToCompany's auto-fill-on-create, which only fires when
     * Auth::check() is true — there's no authenticated user on this public
     * route at all.
     */
    public function createFromClientPortal(Lead $lead, array $attributes): SupportTicket
    {
        $raiser = $lead->assignedUser ?? $lead->creator;

        return $this->createForLead($lead, [
            ...$attributes,
            'company_id' => $lead->company_id,
            'priority' => RequirementPriority::Medium->value,
            'is_client_submitted' => true,
        ], $raiser);
    }

    /**
     * @param  array<int, UploadedFile|null>  $files
     */
    public function update(SupportTicket $ticket, array $attributes, User $actor, array $files = []): SupportTicket
    {
        if (array_key_exists('status', $attributes)) {
            if ($attributes['status'] === RequirementStatus::Completed->value && ! $ticket->resolved_at) {
                $attributes['resolved_at'] = now();
            } elseif ($attributes['status'] !== RequirementStatus::Completed->value && $ticket->resolved_at) {
                // Reopening a resolved ticket clears resolved_at —
                // otherwise it keeps counting as "solved" (and skewing the
                // average solving time) on the dashboard's Performance
                // Snapshot even though it's active again.
                $attributes['resolved_at'] = null;
            }
        }

        if (array_key_exists('assigned_to', $attributes)) {
            $assignedTo = $attributes['assigned_to'];
            unset($attributes['assigned_to']);
            $this->assign($ticket, $assignedTo !== null ? (int) $assignedTo : null, $actor);
        }

        $previousStatus = $ticket->status;

        $ticket = $this->tickets->update($ticket, $attributes);

        $this->logStatusChange($ticket, $previousStatus, $actor);

        $this->storeAttachments($ticket, $files);

        return $ticket;
    }

    /**
     * Every actual status transition gets its own row — mirrors assign()'s
     * assignmentLogs() reasoning, just for status instead of assignee.
     * Resubmitting the same status is a no-op (no log entry).
     */
    private function logStatusChange(SupportTicket $ticket, RequirementStatus $previousStatus, User $actor): void
    {
        if ($ticket->status === $previousStatus) {
            return;
        }

        $ticket->statusLogs()->create([
            'from_status' => $previousStatus,
            'to_status' => $ticket->status,
            'changed_by' => $actor->id,
        ]);

        $this->notifier->notify('support_ticket_status_changed', $ticket->lead?->email, [
            ...$this->clientVariables($ticket),
            'old_status' => $previousStatus->label(),
            'new_status' => $ticket->status->label(),
        ], $ticket);
    }

    /**
     * @return array<string, string|null>
     */
    private function clientVariables(SupportTicket $ticket): array
    {
        return [
            'company_name' => $ticket->lead?->company_name,
            'contact_person' => $ticket->lead?->contact_person,
            'subject' => $ticket->subject,
            'priority' => $ticket->priority->label(),
        ];
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
