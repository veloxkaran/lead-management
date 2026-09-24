<?php

namespace App\Policies;

use App\Models\Requirement;
use App\Models\User;
use Illuminate\Auth\Access\Response;

class RequirementPolicy
{
    public function viewAny(User $user): bool
    {
        return true;
    }

    public function view(User $user, Requirement $requirement): bool
    {
        return true;
    }

    public function create(User $user): bool
    {
        return true;
    }

    public function update(User $user, Requirement $requirement): Response|bool
    {
        if ($requirement->isLockedFor($user)) {
            return $this->lockedResponse();
        }

        return $user->isSuperAdmin()
            || $requirement->created_by === $user->id
            || $requirement->assigned_to === $user->id;
    }

    /**
     * The list page's quick "Status" popup (status + a required note) is
     * open to any user, unlike update() — which stays restricted to the
     * creator/assignee/super admin and gates the full edit form and
     * destructive actions.
     */
    public function changeStatus(User $user, Requirement $requirement): Response|bool
    {
        return $requirement->isLockedFor($user) ? $this->lockedResponse() : true;
    }

    public function delete(User $user, Requirement $requirement): Response|bool
    {
        return $this->update($user, $requirement);
    }

    private function lockedResponse(): Response
    {
        return Response::deny('This requirement was completed more than '.Requirement::EDIT_WINDOW_HOURS.' hours ago and is locked. Ask a Super Admin to reopen it.');
    }
}
