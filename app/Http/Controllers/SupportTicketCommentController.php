<?php

namespace App\Http\Controllers;

use App\Http\Requests\SupportTicket\StoreSupportTicketCommentRequest;
use App\Http\Requests\SupportTicket\UpdateSupportTicketCommentRequest;
use App\Models\SupportTicket;
use App\Models\SupportTicketComment;
use App\Services\SupportTicketService;
use Illuminate\Http\RedirectResponse;

class SupportTicketCommentController extends Controller
{
    public function __construct(protected SupportTicketService $supportTickets)
    {
    }

    public function store(StoreSupportTicketCommentRequest $request, SupportTicket $supportTicket): RedirectResponse
    {
        $this->supportTickets->addComment($supportTicket, $request->validated(), $request->user());

        return back()->with('success', 'Comment added.');
    }

    public function update(UpdateSupportTicketCommentRequest $request, SupportTicket $supportTicket, SupportTicketComment $comment): RedirectResponse
    {
        $this->supportTickets->updateComment($comment, $request->validated());

        return back()->with('success', 'Comment updated.');
    }
}
