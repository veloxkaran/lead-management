<?php

namespace App\Policies;

use App\Models\SupportTicket;
use App\Models\User;

class SupportTicketPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->isSuperAdmin() || $user->isManager() || $user->isCustomerSuccess();
    }

    public function view(User $user, SupportTicket $ticket): bool
    {
        return $user->isSuperAdmin() || $user->isManager() || $user->isCustomerSuccess() || $ticket->raised_by === $user->id;
    }

    public function create(User $user): bool
    {
        return $user->isSuperAdmin() || $user->isManager();
    }

    public function update(User $user, SupportTicket $ticket): bool
    {
        return $user->isSuperAdmin() || $user->isManager() || $user->isCustomerSuccess() || $ticket->raised_by === $user->id;
    }

    public function delete(User $user, SupportTicket $ticket): bool
    {
        return $user->isSuperAdmin() || $user->isManager() || $ticket->raised_by === $user->id;
    }
}
