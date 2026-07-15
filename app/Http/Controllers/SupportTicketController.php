<?php

namespace App\Http\Controllers;

use App\Enums\RequirementPriority;
use App\Enums\RequirementStatus;
use App\Enums\UserRole;
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

        $filters = $request->only(['status', 'priority']);

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
        ]);
    }

    public function store(StoreSupportTicketRequest $request): RedirectResponse
    {
        $this->supportTickets->create($request->validated(), $request->user());

        return redirect()->route('support-tickets.index')->with('success', 'Support ticket raised.');
    }

    public function edit(SupportTicket $supportTicket): View
    {
        $this->authorize('update', $supportTicket);

        $supportTicket->load('lead', 'raiser', 'assignee');

        return view('support-tickets.edit', [
            'supportTicket' => $supportTicket,
            'statuses' => RequirementStatus::cases(),
            'priorities' => RequirementPriority::cases(),
            'users' => User::where('role', UserRole::CustomerSuccess)->orderBy('name')->get(),
        ]);
    }

    public function update(UpdateSupportTicketRequest $request, SupportTicket $supportTicket): RedirectResponse
    {
        $this->supportTickets->update($supportTicket, $request->validated());

        return redirect()->route('support-tickets.index')->with('success', 'Support ticket updated.');
    }

    public function destroy(SupportTicket $supportTicket): RedirectResponse
    {
        $this->authorize('delete', $supportTicket);

        $this->supportTickets->delete($supportTicket);

        return back()->with('success', 'Support ticket deleted.');
    }
}
