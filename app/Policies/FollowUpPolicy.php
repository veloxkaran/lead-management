<?php

namespace App\Policies;

use App\Models\FollowUp;
use App\Models\User;

class FollowUpPolicy
{
    public function viewAny(User $user): bool
    {
        return true;
    }

    public function view(User $user, FollowUp $followUp): bool
    {
        return true;
    }

    public function create(User $user): bool
    {
        return true;
    }

    public function update(User $user, FollowUp $followUp): bool
    {
        return $user->isSuperAdmin()
            || $followUp->created_by === $user->id
            || $followUp->lead?->assigned_user_id === $user->id;
    }

    public function delete(User $user, FollowUp $followUp): bool
    {
        return $this->update($user, $followUp);
    }
}
