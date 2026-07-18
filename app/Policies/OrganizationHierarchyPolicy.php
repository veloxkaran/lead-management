<?php

namespace App\Policies;

use App\Models\User;
use App\Services\OrganizationHierarchyService;

/**
 * Governs hierarchy-based data visibility (Team page, Org Tree, and any
 * future module wired into OrganizationHierarchyService) — deliberately
 * separate from UserPolicy, which governs SuperAdmin-only account CRUD, a
 * different concern from "can this viewer see this team's data."
 */
class OrganizationHierarchyPolicy
{
    public function __construct(protected OrganizationHierarchyService $hierarchy) {}

    /**
     * Always true: a user with zero direct reports still sees the Team page
     * with an empty state rather than a 403, so the feature stays
     * discoverable once an admin does assign them a report.
     */
    public function viewTeamPage(User $user): bool
    {
        return true;
    }

    public function viewOrgTree(User $user): bool
    {
        return $user->isOverseer() || $this->hierarchy->getDirectReports($user)->isNotEmpty();
    }

    public function viewTeamMember(User $viewer, User $target): bool
    {
        return $this->hierarchy->canView($viewer, $target);
    }
}
