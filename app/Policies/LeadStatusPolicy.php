<?php

namespace App\Policies;

use App\Models\LeadStatus;
use App\Models\User;

class LeadStatusPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->isSuperAdmin();
    }

    public function view(User $user, LeadStatus $leadStatus): bool
    {
        return $user->isSuperAdmin();
    }

    public function create(User $user): bool
    {
        return $user->isSuperAdmin();
    }

    public function update(User $user, LeadStatus $leadStatus): bool
    {
        return $user->isSuperAdmin();
    }

    public function delete(User $user, LeadStatus $leadStatus): bool
    {
        return $user->isSuperAdmin();
    }

    public function reorder(User $user): bool
    {
        return $user->isSuperAdmin();
    }
}
