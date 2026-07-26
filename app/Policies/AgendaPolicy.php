<?php

namespace App\Policies;

use App\Models\Agenda;
use App\Models\User;

/**
 * The Team Meeting Room is shared by every user — visibility is never
 * restricted, so viewAny/view/create all stay wide open. Only finalizing
 * (closing/dismissing) an agenda is creator-restricted; that action reuses
 * update() rather than a bespoke ability name, matching TaskPolicy's
 * delegation pattern.
 */
class AgendaPolicy
{
    public function viewAny(User $user): bool
    {
        return true;
    }

    public function view(User $user, Agenda $agenda): bool
    {
        return true;
    }

    public function create(User $user): bool
    {
        return true;
    }

    public function update(User $user, Agenda $agenda): bool
    {
        return $agenda->created_by === $user->id;
    }
}
