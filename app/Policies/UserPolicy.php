<?php

namespace App\Policies;

use App\Models\User;

class UserPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->isSuperAdmin();
    }

    public function view(User $user, User $model): bool
    {
        return $user->isSuperAdmin();
    }

    public function create(User $user): bool
    {
        return $user->isSuperAdmin();
    }

    public function update(User $user, User $model): bool
    {
        return $user->isSuperAdmin();
    }

    public function delete(User $user, User $model): bool
    {
        return $user->isSuperAdmin() && $user->id !== $model->id;
    }

    public function suspend(User $user, User $model): bool
    {
        return $user->isSuperAdmin() && $user->id !== $model->id;
    }

    public function resetPassword(User $user, User $model): bool
    {
        return $user->isSuperAdmin();
    }

    /**
     * Super Admins always have full access (see User::hasPermission()), so
     * there's nothing to manage on their accounts.
     */
    public function managePermissions(User $user, User $model): bool
    {
        return $user->isSuperAdmin() && ! $model->isSuperAdmin();
    }

    public function impersonate(User $user, User $model): bool
    {
        return $user->isSuperAdmin()
            && $user->id !== $model->id
            && ! $model->isSuperAdmin()
            && $model->isActive();
    }
}
