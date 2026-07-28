<?php

namespace App\Policies;

use App\Models\RawData;
use App\Models\User;

class RawDataPolicy
{
    public function viewAny(User $user): bool
    {
        return true;
    }

    public function view(User $user, RawData $rawData): bool
    {
        return true;
    }

    public function create(User $user): bool
    {
        return true;
    }

    /**
     * Marking Not Valid and converting to a lead both go through this —
     * once finalized (see RawDataStatus::isFinalized()), the service layer
     * itself refuses any further transition regardless of this ability.
     */
    public function update(User $user, RawData $rawData): bool
    {
        return true;
    }

    public function delete(User $user, RawData $rawData): bool
    {
        return $user->isSuperAdmin() || $rawData->created_by === $user->id;
    }
}
