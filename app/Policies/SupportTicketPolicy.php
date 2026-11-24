<?php

namespace App\Policies;

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
            return Response::deny('This support ticket was closed more than '.SupportTicket::EDIT_WINDOW_HOURS.' hours ago and is locked. Ask a Super Admin to reopen it.');
        }

        return true;
    }

    /**
     * "Assign to me" on the details page — anyone may pick up an open,
     * unassigned ticket for themselves. Completed/cancelled ones aren't claimable.
     */
    public function assignToSelf(User $user, SupportTicket $ticket): bool
    {
        return $ticket->assigned_to === null && ! $ticket->isClosed();
    }

    /**
     * "Cancel" (with a reason) on a ticket that's still open — anyone who
     * can edit it, since the status dropdown on the edit form allows it too.
     */
    public function cancel(User $user, SupportTicket $ticket): bool
    {
        return ! $ticket->isClosed() && $this->update($user, $ticket) === true;
    }

    /**
     * "Reopen" on a completed or cancelled ticket — even one past the
     * edit-window lock. The people involved can: whoever raised it, whoever
     * it's assigned to, managers (anyone with people reporting to them) and
     * Super Admin.
     */
    public function reopen(User $user, SupportTicket $ticket): bool
    {
        return $ticket->isClosed()
            && ($user->id === $ticket->raised_by || $user->id === $ticket->assigned_to || $user->isOverseer());
    }

    public function delete(User $user, SupportTicket $ticket): bool
    {
        return $user->isSuperAdmin();
    }
}
