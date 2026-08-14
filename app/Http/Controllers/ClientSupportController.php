<?php

namespace App\Http\Controllers;

use App\Http\Requests\ClientSupport\StoreClientSupportTicketRequest;
use App\Http\Requests\ClientSupport\VerifySupportAccessRequest;
use App\Models\Lead;
use App\Services\SupportTicketService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Public, unauthenticated portal a lead's client contact uses to raise
 * support tickets with the Support ID + PIN a Super Admin issued them from
 * the lead's details page (LeadController::generateSupportAccess()) — no
 * staff login involved. See routes/web.php's "support-access" group.
 */
class ClientSupportController extends Controller
{
    public function __construct(protected SupportTicketService $supportTickets) {}

    public function showVerify(Request $request): View|RedirectResponse
    {
        if ($request->session()->has('client_support_lead_id')) {
            return redirect()->route('client-support.ticket.create');
        }

        return view('client-support.verify');
    }

    public function verify(VerifySupportAccessRequest $request): RedirectResponse
    {
        $lead = $request->attempt();

        $request->session()->regenerate();
        $request->session()->put('client_support_lead_id', $lead->id);

        return redirect()->route('client-support.ticket.create');
    }

    public function showTicketForm(Request $request): View|RedirectResponse
    {
        $lead = $this->verifiedLead($request);

        if (! $lead) {
            return $this->redirectToVerify();
        }

        return view('client-support.ticket-create', ['lead' => $lead]);
    }

    public function storeTicket(StoreClientSupportTicketRequest $request): RedirectResponse
    {
        $lead = $this->verifiedLead($request);

        if (! $lead) {
            return $this->redirectToVerify();
        }

        $ticket = $this->supportTickets->createFromClientPortal($lead, $request->validated());

        return redirect()->route('client-support.ticket.submitted')->with('submitted_ticket_id', $ticket->id);
    }

    public function submitted(Request $request): View|RedirectResponse
    {
        $lead = $this->verifiedLead($request);

        if (! $lead || ! $request->session()->has('submitted_ticket_id')) {
            return $this->redirectToVerify();
        }

        return view('client-support.ticket-submitted', [
            'lead' => $lead,
            'ticketId' => $request->session()->get('submitted_ticket_id'),
        ]);
    }

    public function logout(Request $request): RedirectResponse
    {
        $request->session()->forget('client_support_lead_id');
        $request->session()->regenerate();

        return redirect()->route('client-support.show');
    }

    private function verifiedLead(Request $request): ?Lead
    {
        $leadId = $request->session()->get('client_support_lead_id');

        return $leadId ? Lead::find($leadId) : null;
    }

    private function redirectToVerify(): RedirectResponse
    {
        return redirect()->route('client-support.show')
            ->withErrors(['pin' => 'Please verify your Support ID and PIN again.']);
    }
}
