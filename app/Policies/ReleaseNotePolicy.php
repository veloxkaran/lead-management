<?php

namespace App\Policies;

use App\Models\ReleaseNote;
use App\Models\User;

class ReleaseNotePolicy
{
    public function viewAny(User $user): bool
    {
        return true;
    }

    public function view(User $user, ReleaseNote $release_note): bool
    {
        return true;
    }

    public function create(User $user): bool
    {
        return $user->isSuperAdmin();
    }

    public function update(User $user, ReleaseNote $release_note): bool
    {
        return $user->isSuperAdmin();
    }

    public function delete(User $user, ReleaseNote $release_note): bool
    {
        return $user->isSuperAdmin();
    }
}
