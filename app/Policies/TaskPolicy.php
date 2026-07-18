<?php

namespace App\Policies;

use App\Models\Task;
use App\Models\User;
use App\Services\OrganizationHierarchyService;

class TaskPolicy
{
    public function __construct(protected OrganizationHierarchyService $hierarchy)
    {
    }

    /**
     * Visibility is enforced by the hierarchy-scoped index query
     * (TaskRepository::filter()), not here — viewAny only gates whether the
     * list page itself is reachable.
     */
    public function viewAny(User $user): bool
    {
        return true;
    }

    public function view(User $user, Task $task): bool
    {
        if ($user->isOverseer() || $task->assigned_by === $user->id || $task->created_by === $user->id) {
            return true;
        }

        // canView()'s self-inclusion is exactly right here (unlike
        // LeadPolicy::viewProgressStatus): the assignee must see their own task.
        return $task->assignee && $this->hierarchy->canView($user, $task->assignee);
    }

    /**
     * Who they may assign the task to is validated separately, at the
     * input-field level — see App\Rules\AuthorizedTaskAssignee.
     */
    public function create(User $user): bool
    {
        return true;
    }

    public function update(User $user, Task $task): bool
    {
        return $this->view($user, $task);
    }

    /**
     * Narrower than update(): an assignee can work their own task but not
     * delete an assigner's record out from under them.
     */
    public function delete(User $user, Task $task): bool
    {
        return $user->isOverseer() || $task->assigned_by === $user->id || $task->created_by === $user->id;
    }
}
