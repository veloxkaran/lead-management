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

    /**
     * Editing is decided by the member's permissions: anyone with the
     * Requirements "update" permission can edit any requirement (checked by
     * the Gate::before hook in AppServiceProvider before this runs). The
     * only extra rule is the completion lock.
     */
    public function update(User $user, Requirement $requirement): Response|bool
    {
        return $requirement->isLockedFor($user) ? $this->lockedResponse() : true;
    }

    /**
     * The list page's quick "Status" popup (status + a required note) is
     * open to any user who can view requirements, even without the
     * "update" permission that the full edit form needs.
     */
    public function changeStatus(User $user, Requirement $requirement): Response|bool
    {
        return $requirement->isLockedFor($user) ? $this->lockedResponse() : true;
    }

    /**
     * "Cancel" (with a reason) on a requirement that's still open — open to
     * the same people as the quick status popup, which can also cancel.
     */
    public function cancel(User $user, Requirement $requirement): bool
    {
        return ! $requirement->isClosed() && $this->changeStatus($user, $requirement) === true;
    }

    /**
     * "Assign to me" on the details page — anyone may pick up an open,
     * unassigned requirement for themselves. Completed/cancelled ones aren't claimable.
     */
    public function assignToSelf(User $user, Requirement $requirement): bool
    {
        return $requirement->assigned_to === null && ! $requirement->isClosed();
    }

    /**
     * Deleting stays with the people who own it — its creator, its assignee
     * or Super Admin (plus the "delete" permission and the completion lock).
     */
    public function delete(User $user, Requirement $requirement): Response|bool
    {
        if ($requirement->isLockedFor($user)) {
            return $this->lockedResponse();
        }

        return $user->isSuperAdmin()
            || $requirement->created_by === $user->id
            || $requirement->assigned_to === $user->id;
    }

    private function lockedResponse(): Response
    {
        return Response::deny('This requirement was closed more than '.Requirement::EDIT_WINDOW_HOURS.' hours ago and is locked. Ask a Super Admin to reopen it.');
    }
}
