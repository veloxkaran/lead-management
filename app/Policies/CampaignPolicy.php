<?php

namespace App\Policies;

use App\Enums\UserRole;
use App\Models\Campaign;
use App\Models\User;

/**
 * Campaigns reach clients directly, so they're limited to the roles that
 * do outreach: Super Admin, Manager and Business Development. Business
 * Development members only see (and can only pause, resume, retry or
 * cancel) campaigns they
 * created — recipient lists are client contact details. Per-member
 * permissions (Users → Permissions → Campaigns) can narrow this further.
 * Campaign Setup (gateway keys, sender) is Super Admin only, enforced on
 * its routes.
 */
class CampaignPolicy
{
    private const ROLES = [UserRole::SuperAdmin, UserRole::Manager, UserRole::BusinessDevelopment];

    public static function seesAllCampaigns(User $user): bool
    {
        return in_array($user->role, [UserRole::SuperAdmin, UserRole::Manager], true);
    }

    public function viewAny(User $user): bool
    {
        return in_array($user->role, self::ROLES, true);
    }

    public function view(User $user, Campaign $campaign): bool
    {
        return self::seesAllCampaigns($user)
            || ($this->viewAny($user) && $campaign->created_by === $user->id);
    }

    public function create(User $user): bool
    {
        return $this->viewAny($user);
    }

    public function cancel(User $user, Campaign $campaign): bool
    {
        return $campaign->isCancellable() && $this->view($user, $campaign);
    }

    public function pause(User $user, Campaign $campaign): bool
    {
        return $campaign->isPausable() && $this->view($user, $campaign);
    }

    public function resume(User $user, Campaign $campaign): bool
    {
        return $campaign->isResumable() && $this->view($user, $campaign);
    }

    public function retryFailed(User $user, Campaign $campaign): bool
    {
        return $campaign->canRetryFailed() && $this->view($user, $campaign);
    }
}
