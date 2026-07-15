<?php

namespace App\Repositories;

use App\Enums\PolicyDocumentType;
use App\Models\PolicyDocument;
use App\Models\User;
use Illuminate\Support\Collection;

class PolicyDocumentRepository extends BaseRepository
{
    public function __construct(PolicyDocument $model)
    {
        parent::__construct($model);
    }

    /**
     * Active documents of $type assigned to $user, with the current version
     * and $user's own acknowledgment of it eager-loaded — the query shape
     * shared by the acknowledgment resolver and the "My SOPs & JDs"
     * dashboard, which differ only in what they do with the rows afterward.
     *
     * @return Collection<int, PolicyDocument>
     */
    public function assignedToUserOfType(User $user, PolicyDocumentType $type, array $with = []): Collection
    {
        return $this->query()
            ->active()
            ->ofType($type)
            ->assignedTo($user, $type)
            ->with(array_merge([
                'department', 'user',
                'currentVersion.acknowledgments' => fn ($query) => $query->where('user_id', $user->id),
            ], $with))
            ->get()
            ->filter(fn (PolicyDocument $document) => $document->currentVersion !== null)
            ->values();
    }
}
