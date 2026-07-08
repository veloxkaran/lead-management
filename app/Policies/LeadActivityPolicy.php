<?php

namespace App\Policies;

use App\Models\LeadActivity;
use App\Models\User;

class LeadActivityPolicy
{
    public function viewAny(User $user): bool
    {
        return true;
    }

    public function view(User $user, LeadActivity $activity): bool
    {
        return true;
    }

    public function create(User $user): bool
    {
        return true;
    }

    public function update(User $user, LeadActivity $activity): bool
    {
        return $user->isSuperAdmin() || $activity->created_by === $user->id;
    }

    public function delete(User $user, LeadActivity $activity): bool
    {
        return $user->isSuperAdmin() || $activity->created_by === $user->id;
    }
}
