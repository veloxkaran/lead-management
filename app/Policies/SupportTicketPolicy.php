<?php

namespace App\Policies;

use App\Enums\RequirementStatus;
use App\Models\SupportTicket;
use App\Models\User;
use Illuminate\Auth\Access\Response;

class SupportTicketPolicy
{
    public function viewAny(User $user): bool
    {
        return true;
    }

    public function view(User $user, SupportTicket $ticket): bool
    {
        return true;
    }

    public function create(User $user): bool
    {
        return true;
    }

    /**
     * Open to everyone — until the ticket has been completed for more than
     * the edit window, after which only Super Admin can change or reopen it.
     */
    public function update(User $user, SupportTicket $ticket): Response|bool
    {
        if ($ticket->isLockedFor($user)) {
            return Response::deny('This support ticket was completed more than '.SupportTicket::EDIT_WINDOW_HOURS.' hours ago and is locked. Ask a Super Admin to reopen it.');
        }

        return true;
    }

    /**
     * "Assign to me" on the details page — anyone may pick up an open,
     * unassigned ticket for themselves. Completed ones aren't claimable.
     */
    public function assignToSelf(User $user, SupportTicket $ticket): bool
    {
        return $ticket->assigned_to === null && $ticket->status !== RequirementStatus::Completed;
    }

    public function delete(User $user, SupportTicket $ticket): bool
    {
        return $user->isSuperAdmin();
    }
}
