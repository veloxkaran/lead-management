<?php

namespace App\Policies;

use App\Enums\UserRole;
use App\Models\Campaign;
use App\Models\User;

/**
 * Viewing: every campaign is visible to anyone allowed to view Campaigns
 * — that's decided per member under Users → Permissions → Campaigns (the
 * Gate::before hook), not by role.
 *
 * Creating is for the roles that do outreach: Super Admin, Manager and
 * Business Development. Pausing, resuming, retrying and cancelling a
 * campaign is for whoever created it, a Manager or a Super Admin.
 * Campaigns from anyone but a Super Admin wait for a Super Admin's
 * approval before anything is sent (review()). Campaign Setup is Super
 * Admin only, enforced on its routes.
 */
class CampaignPolicy
{
    private const CREATOR_ROLES = [UserRole::SuperAdmin, UserRole::Manager, UserRole::BusinessDevelopment];

    public function viewAny(User $user): bool
    {
        return true;
    }

    public function view(User $user, Campaign $campaign): bool
    {
        return true;
    }

    public function create(User $user): bool
    {
        return in_array($user->role, self::CREATOR_ROLES, true);
    }

    public function cancel(User $user, Campaign $campaign): bool
    {
        return $campaign->isCancellable() && $this->manages($user, $campaign);
    }

    public function pause(User $user, Campaign $campaign): bool
    {
        return $campaign->isPausable() && $this->manages($user, $campaign);
    }

    public function resume(User $user, Campaign $campaign): bool
    {
        return $campaign->isResumable() && $this->manages($user, $campaign);
    }

    public function retryFailed(User $user, Campaign $campaign): bool
    {
        return $campaign->canRetryFailed() && $this->manages($user, $campaign);
    }

    /**
     * Approving or rejecting a campaign that's waiting — Super Admin only.
     */
    public function review(User $user, Campaign $campaign): bool
    {
        return $user->isSuperAdmin() && $campaign->isAwaitingApproval();
    }

    private function manages(User $user, Campaign $campaign): bool
    {
        return $user->isSuperAdmin() || $user->isManager() || $campaign->created_by === $user->id;
    }
}
