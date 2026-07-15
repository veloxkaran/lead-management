<?php

namespace App\Policies;

use App\Models\AccountRequest;
use App\Models\User;

class AccountRequestPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->isSuperAdmin() || $user->isManager() || $user->isBusinessDevelopment() || $user->isFinance();
    }

    public function view(User $user, AccountRequest $request): bool
    {
        return $user->isSuperAdmin() || $user->isManager() || $user->isFinance() || $request->requested_by === $user->id;
    }

    public function create(User $user): bool
    {
        return $user->isSuperAdmin() || $user->isManager() || $user->isBusinessDevelopment();
    }

    public function update(User $user, AccountRequest $request): bool
    {
        return $user->isSuperAdmin() || $user->isManager() || $user->isFinance() || $request->requested_by === $user->id;
    }

    public function delete(User $user, AccountRequest $request): bool
    {
        return $user->isSuperAdmin() || $user->isManager() || $request->requested_by === $user->id;
    }
}
