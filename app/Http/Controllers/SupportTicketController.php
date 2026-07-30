<?php

namespace App\Http\Controllers;

use App\Enums\RequirementPriority;
use App\Enums\RequirementStatus;
use App\Http\Requests\SupportTicket\StoreSupportTicketRequest;
use App\Http\Requests\SupportTicket\UpdateSupportTicketRequest;
use App\Models\Lead;
use App\Models\SupportTicket;
use App\Models\User;
use App\Services\SupportTicketService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class SupportTicketController extends Controller
{
    public function __construct(protected SupportTicketService $supportTickets)
    {
    }

    public function index(Request $request): View
    {
        $this->authorize('viewAny', SupportTicket::class);

        $filters = $request->only(['search', 'status', 'priority']);

        return view('support-tickets.index', [
            'tickets' => $this->supportTickets->list($filters, 20),
            'statuses' => RequirementStatus::cases(),
            'priorities' => RequirementPriority::cases(),
            'filters' => $filters,
        ]);
    }

    public function create(): View
    {
        $this->authorize('create', SupportTicket::class);

        return view('support-tickets.create', [
            'leads' => Lead::active()->orderBy('company_name')->get(),
            'priorities' => RequirementPriority::cases(),
            'users' => User::orderBy('name')->get(),
        ]);
    }

    public function store(StoreSupportTicketRequest $request): RedirectResponse
    {
        $this->supportTickets->create(
            $request->safe()->except('attachments'),
            $request->user(),
            $request->file('attachments', [])
        );

        return redirect()->route('support-tickets.index')->with('success', 'Support ticket raised.');
    }

    public function storeForLead(StoreSupportTicketRequest $request, Lead $lead): RedirectResponse
    {
        $this->supportTickets->createForLead(
            $lead,
            $request->safe()->except('attachments'),
            $request->user(),
            $request->file('attachments', [])
        );

        return back()->with('success', 'Support ticket raised.');
    }

    public function show(SupportTicket $supportTicket): View
    {
        $this->authorize('view', $supportTicket);

        $supportTicket->load('lead', 'raiser', 'assignee', 'comments.author', 'attachments');

        return view('support-tickets.show', [
            'supportTicket' => $supportTicket,
        ]);
    }

    public function edit(SupportTicket $supportTicket): View
    {
        $this->authorize('update', $supportTicket);

        $supportTicket->load('lead', 'raiser', 'assignee', 'attachments');

        return view('support-tickets.edit', [
            'supportTicket' => $supportTicket,
            'statuses' => RequirementStatus::cases(),
            'priorities' => RequirementPriority::cases(),
            'users' => User::orderBy('name')->get(),
        ]);
    }

    public function update(UpdateSupportTicketRequest $request, SupportTicket $supportTicket): RedirectResponse
    {
        $this->supportTickets->update(
            $supportTicket,
            $request->safe()->except('attachments'),
            $request->file('attachments', [])
        );

        return redirect()->route('support-tickets.index')->with('success', 'Support ticket updated.');
    }

    public function destroy(SupportTicket $supportTicket): RedirectResponse
    {
        $this->authorize('delete', $supportTicket);

        $this->supportTickets->delete($supportTicket);

        return back()->with('success', 'Support ticket deleted.');
    }
}
