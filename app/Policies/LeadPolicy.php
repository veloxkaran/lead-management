<?php

namespace App\Policies;

use App\Models\Lead;
use App\Models\User;

class LeadPolicy
{
    public function viewAny(User $user): bool
    {
        return true;
    }

    public function view(User $user, Lead $lead): bool
    {
        return $user->isSuperAdmin() || $lead->assigned_user_id === $user->id || $lead->created_by === $user->id;
    }

    public function create(User $user): bool
    {
        return true;
    }

    public function update(User $user, Lead $lead): bool
    {
        return $user->isSuperAdmin() || $lead->assigned_user_id === $user->id || $lead->created_by === $user->id;
    }

    public function delete(User $user, Lead $lead): bool
    {
        return $user->isSuperAdmin();
    }

    public function archive(User $user, Lead $lead): bool
    {
        return $this->update($user, $lead);
    }

    public function changeStatus(User $user, Lead $lead): bool
    {
        return $this->update($user, $lead);
    }

    public function close(User $user, Lead $lead): bool
    {
        return $this->update($user, $lead);
    }
}
