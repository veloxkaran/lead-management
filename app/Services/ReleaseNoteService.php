<?php

namespace App\Services;

use App\Models\ReleaseNote;
use App\Repositories\ReleaseNoteRepository;
use Illuminate\Http\UploadedFile;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

class ReleaseNoteService
{
    public function __construct(protected ReleaseNoteRepository $releaseNotes)
    {
    }

    public function list(int $perPage = 15): LengthAwarePaginator
    {
        return $this->releaseNotes->query()
            ->with('creator')
            ->withCount('attachments')
            ->orderByDesc('release_date')
            ->orderByDesc('id')
            ->paginate($perPage)
            ->withQueryString();
    }

    public function create(array $attributes, array $attachments, int $creatorId): ReleaseNote
    {
        return DB::transaction(function () use ($attributes, $attachments, $creatorId) {
            $attributes['created_by'] = $creatorId;

            /** @var ReleaseNote $releaseNote */
            $releaseNote = $this->releaseNotes->create($attributes);

            $this->storeAttachments($releaseNote, $attachments);

            return $releaseNote->refresh();
        });
    }

    public function update(ReleaseNote $releaseNote, array $attributes, array $attachments): ReleaseNote
    {
        return DB::transaction(function () use ($releaseNote, $attributes, $attachments) {
            $releaseNote = $this->releaseNotes->update($releaseNote, $attributes);

            $this->storeAttachments($releaseNote, $attachments);

            return $releaseNote;
        });
    }

    public function delete(ReleaseNote $releaseNote): bool
    {
        foreach ($releaseNote->attachments as $attachment) {
            Storage::disk('public')->delete($attachment->disk_path);
        }

        return $this->releaseNotes->delete($releaseNote);
    }

    /**
     * @param array<int, UploadedFile|null> $attachments
     */
    protected function storeAttachments(ReleaseNote $releaseNote, array $attachments): void
    {
        foreach ($attachments as $file) {
            if (! $file instanceof UploadedFile) {
                continue;
            }

            $path = $file->store("release-notes/{$releaseNote->id}", 'public');

            $releaseNote->attachments()->create([
                'disk_path' => $path,
                'original_name' => $file->getClientOriginalName(),
            ]);
        }
    }
}
