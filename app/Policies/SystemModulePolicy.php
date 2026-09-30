<?php

namespace App\Policies;

use App\Models\SystemModule;
use App\Models\User;

class SystemModulePolicy
{
    public function viewAny(User $user): bool
    {
        return $user->isSuperAdmin();
    }

    public function view(User $user, SystemModule $systemModule): bool
    {
        return $user->isSuperAdmin();
    }

    public function create(User $user): bool
    {
        return $user->isSuperAdmin();
    }

    public function update(User $user, SystemModule $systemModule): bool
    {
        return $user->isSuperAdmin();
    }

    public function delete(User $user, SystemModule $systemModule): bool
    {
        return $user->isSuperAdmin();
    }
}
