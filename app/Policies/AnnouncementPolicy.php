<?php

namespace App\Policies;

use App\Models\Announcement;
use App\Models\User;

/**
 * Every staff member can read announcements (dashboard + Announcements
 * page); only Super Admin can send them or see who they went to —
 * recipient addresses are client contact details.
 */
class AnnouncementPolicy
{
    public function viewAny(User $user): bool
    {
        return true;
    }

    public function view(User $user, Announcement $announcement): bool
    {
        return true;
    }

    public function create(User $user): bool
    {
        return $user->isSuperAdmin();
    }

    public function viewDelivery(User $user): bool
    {
        return $user->isSuperAdmin();
    }
}
