<?php

namespace App\Policies;

use App\Models\PolicyDocument;
use App\Models\User;

class PolicyDocumentPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->isSuperAdmin();
    }

    public function create(User $user): bool
    {
        return $user->isSuperAdmin();
    }

    public function update(User $user, PolicyDocument $policyDocument): bool
    {
        return $user->isSuperAdmin();
    }

    public function delete(User $user, PolicyDocument $policyDocument): bool
    {
        return $user->isSuperAdmin();
    }

    /**
     * The employee-facing ability to read/acknowledge a document — assigned
     * to the user's department (Sop/DepartmentJd) or directly to the user
     * (IndividualJd). Super Admin can always view, for troubleshooting.
     */
    public function view(User $user, PolicyDocument $policyDocument): bool
    {
        return $user->isSuperAdmin()
            || $policyDocument->user_id === $user->id
            || ($user->department_id !== null && $policyDocument->department_id === $user->department_id);
    }
}
