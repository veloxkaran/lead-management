<?php

namespace App\Policies;

use App\Models\ImplementationRequest;
use App\Models\User;

class ImplementationRequestPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->isSuperAdmin() || $user->isManager() || $user->isBusinessDevelopment() || $user->isCustomerSuccess();
    }

    public function view(User $user, ImplementationRequest $request): bool
    {
        return $user->isSuperAdmin() || $user->isManager() || $user->isCustomerSuccess() || $request->requested_by === $user->id;
    }

    public function create(User $user): bool
    {
        return true;
    }

    public function update(User $user, ImplementationRequest $request): bool
    {
        return true;
    }

    public function delete(User $user, ImplementationRequest $request): bool
    {
        return $user->isSuperAdmin() || $user->isManager() || $request->requested_by === $user->id;
    }
}
