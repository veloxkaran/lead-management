<?php

namespace App\Policies;

use App\Models\Industry;
use App\Models\User;

class IndustryPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->isSuperAdmin();
    }

    public function view(User $user, Industry $industry): bool
    {
        return $user->isSuperAdmin();
    }

    public function create(User $user): bool
    {
        return $user->isSuperAdmin();
    }

    public function update(User $user, Industry $industry): bool
    {
        return $user->isSuperAdmin();
    }

    public function delete(User $user, Industry $industry): bool
    {
        return $user->isSuperAdmin();
    }
}
