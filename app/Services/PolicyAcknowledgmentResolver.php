<?php

namespace App\Services;

use App\Enums\PolicyDocumentType;
use App\Models\PolicyDocument;
use App\Models\User;
use App\Repositories\PolicyDocumentRepository;
use Illuminate\Support\Collection;

class PolicyAcknowledgmentResolver
{
    public function __construct(protected PolicyDocumentRepository $repository)
    {
    }

    /**
     * Sop → DepartmentJd → IndividualJd, in that order, excluding any
     * document whose current version the user has already acknowledged.
     */
    public function pendingFor(User $user): Collection
    {
        return collect([PolicyDocumentType::Sop, PolicyDocumentType::DepartmentJd, PolicyDocumentType::IndividualJd])
            ->flatMap(fn (PolicyDocumentType $type) => $this->pendingOfType($user, $type))
            ->values();
    }

    /**
     * @return array<int> the current-version ids of everything pending —
     *                     used as the throttle fingerprint (see
     *                     ResolvePendingPolicyAcknowledgments).
     */
    public function pendingVersionIds(User $user): array
    {
        return $this->pendingFor($user)
            ->map(fn (PolicyDocument $document) => $document->currentVersion->id)
            ->all();
    }

    private function pendingOfType(User $user, PolicyDocumentType $type): Collection
    {
        return $this->repository->assignedToUserOfType($user, $type)
            ->reject(function (PolicyDocument $document) use ($user) {
                $acknowledgment = $document->currentVersion->acknowledgmentFor($user);

                return $acknowledgment?->acknowledged_at !== null;
            })
            ->values();
    }
}
