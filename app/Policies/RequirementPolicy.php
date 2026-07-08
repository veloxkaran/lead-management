<?php

namespace App\Policies;

use App\Models\Requirement;
use App\Models\User;

class RequirementPolicy
{
    public function viewAny(User $user): bool
    {
        return true;
    }

    public function view(User $user, Requirement $requirement): bool
    {
        return true;
    }

    public function create(User $user): bool
    {
        return true;
    }

    public function update(User $user, Requirement $requirement): bool
    {
        return $user->isSuperAdmin()
            || $requirement->created_by === $user->id
            || $requirement->assigned_to === $user->id;
    }

    public function delete(User $user, Requirement $requirement): bool
    {
        return $this->update($user, $requirement);
    }
}
