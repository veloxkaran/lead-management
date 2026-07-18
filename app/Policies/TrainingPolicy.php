<?php

namespace App\Policies;

use App\Models\Training;
use App\Models\User;

class TrainingPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->isSuperAdmin() || $user->isManager() || $user->isCustomerSuccess();
    }

    public function view(User $user, Training $training): bool
    {
        return $this->viewAny($user);
    }

    public function create(User $user): bool
    {
        return $user->isSuperAdmin() || $user->isManager() || $user->isCustomerSuccess();
    }

    public function update(User $user, Training $training): bool
    {
        return $this->create($user);
    }

    public function delete(User $user, Training $training): bool
    {
        return $user->isSuperAdmin() || $user->isManager();
    }
}
