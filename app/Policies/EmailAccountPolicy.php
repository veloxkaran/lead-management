<?php

namespace App\Policies;

use App\Models\EmailAccount;
use App\Models\User;

class EmailAccountPolicy
{
    /**
     * The list itself is scoped by user_id in EmailAccountRepository::filter()
     * — no org-wide visibility exists in this phase (Super Admin oversight is
     * a later phase), so viewAny only gates whether the page is reachable.
     */
    public function viewAny(User $user): bool
    {
        return true;
    }

    public function view(User $user, EmailAccount $account): bool
    {
        return $account->user_id === $user->id;
    }

    public function create(User $user): bool
    {
        return true;
    }

    public function update(User $user, EmailAccount $account): bool
    {
        return $account->user_id === $user->id;
    }

    public function delete(User $user, EmailAccount $account): bool
    {
        return $account->user_id === $user->id;
    }
}
