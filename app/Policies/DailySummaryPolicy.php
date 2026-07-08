<?php

namespace App\Policies;

use App\Models\DailySummary;
use App\Models\User;

class DailySummaryPolicy
{
    public function viewAny(User $user): bool
    {
        return true;
    }

    public function view(User $user, DailySummary $dailySummary): bool
    {
        return $dailySummary->user_id === $user->id || $user->isSuperAdmin();
    }

    public function create(User $user): bool
    {
        return true;
    }

    public function update(User $user, DailySummary $dailySummary): bool
    {
        return $dailySummary->user_id === $user->id || $user->isSuperAdmin();
    }
}
