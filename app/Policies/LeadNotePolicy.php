<?php

namespace App\Policies;

use App\Models\LeadNote;
use App\Models\User;

class LeadNotePolicy
{
    public function viewAny(User $user): bool
    {
        return true;
    }

    public function view(User $user, LeadNote $note): bool
    {
        return true;
    }

    public function create(User $user): bool
    {
        return true;
    }

    public function update(User $user, LeadNote $note): bool
    {
        return $user->isSuperAdmin() || $note->author_id === $user->id;
    }

    public function delete(User $user, LeadNote $note): bool
    {
        return $user->isSuperAdmin() || $note->author_id === $user->id;
    }
}
