<?php

namespace App\Http\Controllers;

use App\Http\Requests\SupportTicket\StoreSupportTicketCommentRequest;
use App\Http\Requests\SupportTicket\UpdateSupportTicketCommentRequest;
use App\Models\SupportTicket;
use App\Models\SupportTicketComment;
use App\Services\SupportTicketService;
use Illuminate\Http\RedirectResponse;
use App\Services\MentionNotifier;

class SupportTicketCommentController extends Controller
{
    public function __construct(protected SupportTicketService $supportTickets)
    {
    }

    public function store(StoreSupportTicketCommentRequest $request, SupportTicket $supportTicket, MentionNotifier $mentions): RedirectResponse
    {
        $this->supportTickets->addComment($supportTicket, $request->validated(), $request->user());
        $mentions->notify($request->validated('comment'), $request->user(), $supportTicket);

        return back()->with('success', 'Comment added.');
    }

    public function update(UpdateSupportTicketCommentRequest $request, SupportTicket $supportTicket, SupportTicketComment $comment, MentionNotifier $mentions): RedirectResponse
    {
        $before = $comment->comment;
        $this->supportTickets->updateComment($comment, $request->validated());

        // Only people newly mentioned in the edit are notified.
        $mentions->notify($request->validated('comment'), $request->user(), $supportTicket, previousText: $before);

        return back()->with('success', 'Comment updated.');
    }
}
