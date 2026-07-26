<?php

namespace App\Policies;

use App\Models\SupportTicketComment;
use App\Models\User;

/**
 * Posting is open to anyone who can view the ticket (checked in
 * StoreSupportTicketCommentRequest against the SupportTicket itself, not
 * here) — only editing is restricted, and only here: the comment's own
 * author, and only inside its 4-hour edit window. No delete/viewAny
 * methods since comments can't be removed and visibility is never
 * restricted.
 */
class SupportTicketCommentPolicy
{
    public function update(User $user, SupportTicketComment $comment): bool
    {
        return $comment->author_id === $user->id && $comment->isEditable();
    }
}
