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
     * The employee-facing ability to read/acknowledge a document — every
     * active user for a company-wide Sop, or the specific assignee for an
     * IndividualJd. Super Admin can always view, for troubleshooting.
     */
    public function view(User $user, PolicyDocument $policyDocument): bool
    {
        return $user->isSuperAdmin()
            || $policyDocument->user_id === $user->id
            || $policyDocument->type->isCompanyWide();
    }
}
