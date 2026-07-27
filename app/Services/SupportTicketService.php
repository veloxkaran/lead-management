<?php

namespace App\Services;

use App\Enums\RequirementStatus;
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
            $attributes['raised_by'] = $raiser->id;

            /** @var SupportTicket $ticket */
            $ticket = $this->tickets->create($attributes);

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
    public function update(SupportTicket $ticket, array $attributes, array $files = []): SupportTicket
    {
        if (($attributes['status'] ?? null) === RequirementStatus::Completed->value && ! $ticket->resolved_at) {
            $attributes['resolved_at'] = now();
        }

        $ticket = $this->tickets->update($ticket, $attributes);

        $this->storeAttachments($ticket, $files);

        return $ticket;
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
