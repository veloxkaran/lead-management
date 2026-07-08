<?php

namespace App\Services;

use App\Models\Lead;
use App\Models\LeadNote;
use App\Models\User;
use App\Repositories\LeadNoteRepository;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

class LeadNoteService
{
    public function __construct(protected LeadNoteRepository $notes)
    {
    }

    public function createForLead(Lead $lead, array $attributes, array $files, User $author): LeadNote
    {
        return DB::transaction(function () use ($lead, $attributes, $files, $author) {
            /** @var LeadNote $note */
            $note = $this->notes->create([
                'lead_id' => $lead->id,
                'comment' => $attributes['comment'],
                'author_id' => $author->id,
            ]);

            foreach ($files as $file) {
                if (! $file) {
                    continue;
                }

                $path = $file->store("lead-notes/{$lead->id}", 'public');

                $note->attachments()->create([
                    'disk_path' => $path,
                    'original_name' => $file->getClientOriginalName(),
                    'mime_type' => $file->getClientMimeType(),
                    'size' => $file->getSize(),
                ]);
            }

            return $note->load('attachments');
        });
    }

    public function delete(LeadNote $note): void
    {
        DB::transaction(function () use ($note) {
            foreach ($note->attachments as $attachment) {
                Storage::disk('public')->delete($attachment->disk_path);
                $attachment->delete();
            }

            $this->notes->delete($note);
        });
    }
}
