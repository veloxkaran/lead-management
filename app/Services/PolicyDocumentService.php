<?php

namespace App\Services;

use App\Enums\PolicyDocumentType;
use App\Models\PolicyDocument;
use App\Models\PolicyDocumentVersion;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Mews\Purifier\Facades\Purifier;

class PolicyDocumentService
{
    public function list(PolicyDocumentType $type)
    {
        return PolicyDocument::ofType($type)
            ->with(['department', 'user', 'currentVersion'])
            ->latest()
            ->get();
    }

    public function create(PolicyDocumentType $type, array $attributes, User $creator): PolicyDocument
    {
        return DB::transaction(function () use ($type, $attributes, $creator) {
            $document = PolicyDocument::create([
                'type' => $type,
                'title' => $attributes['title'],
                'department_id' => $attributes['department_id'] ?? null,
                'user_id' => $attributes['user_id'] ?? null,
                'allow_skip' => $attributes['allow_skip'] ?? false,
                'is_active' => $attributes['is_active'] ?? true,
                'created_by' => $creator->id,
            ]);

            $this->publishVersion($document, [
                'version' => $attributes['version'] ?? '1.0',
                'content' => $attributes['content'],
                'effective_date' => $attributes['effective_date'],
            ], $creator);

            return $document;
        });
    }

    public function update(PolicyDocument $document, array $attributes): PolicyDocument
    {
        $document->update([
            'title' => $attributes['title'],
            'department_id' => $attributes['department_id'] ?? null,
            'user_id' => $attributes['user_id'] ?? null,
            'allow_skip' => $attributes['allow_skip'] ?? false,
            'is_active' => $attributes['is_active'] ?? true,
        ]);

        return $document;
    }

    public function delete(PolicyDocument $document): bool
    {
        return $document->delete();
    }

    /**
     * Append-only — never touches an existing version row. Because
     * acknowledgments are keyed to a specific version id, this alone makes
     * everyone assigned to the document "pending" again — no separate
     * force-re-acknowledge mechanism is needed. Cache invalidation for the
     * throttle middleware happens in PolicyDocumentVersion::booted().
     */
    public function publishVersion(PolicyDocument $document, array $attributes, User $creator): PolicyDocumentVersion
    {
        return $document->versions()->create([
            'version' => $attributes['version'],
            'content' => Purifier::clean($attributes['content'], 'policy_document'),
            'effective_date' => $attributes['effective_date'],
            'published_at' => now(),
            'created_by' => $creator->id,
        ]);
    }
}
