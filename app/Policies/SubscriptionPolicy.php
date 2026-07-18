<?php

namespace App\Policies;

use App\Models\Subscription;
use App\Models\User;

class SubscriptionPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->isSuperAdmin() || $user->isManager() || $user->isCustomerSuccess();
    }

    public function view(User $user, Subscription $subscription): bool
    {
        return $this->viewAny($user);
    }

    public function create(User $user): bool
    {
        return $user->isSuperAdmin() || $user->isManager() || $user->isCustomerSuccess();
    }

    public function update(User $user, Subscription $subscription): bool
    {
        return $this->create($user);
    }

    public function delete(User $user, Subscription $subscription): bool
    {
        return $user->isSuperAdmin() || $user->isManager();
    }
}
