<?php

namespace App\Http\Controllers;

use App\Enums\BillingCycle;
use App\Enums\SubscriptionStatus;
use App\Http\Requests\Subscription\StoreSubscriptionRequest;
use App\Http\Requests\Subscription\UpdateSubscriptionRequest;
use App\Models\Lead;
use App\Models\Subscription;
use App\Services\SubscriptionService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class SubscriptionController extends Controller
{
    public function __construct(protected SubscriptionService $subscriptions)
    {
    }

    public function index(Request $request): View
    {
        $this->authorize('viewAny', Subscription::class);

        return view('subscriptions.index', [
            'subscriptions' => $this->subscriptions->list($request->only(['status']), 20),
            'statuses' => SubscriptionStatus::cases(),
            'filters' => $request->only(['status']),
        ]);
    }

    public function forLead(Request $request, Lead $lead): View
    {
        $this->authorize('viewProgressStatus', $lead);

        return view('subscriptions.for-lead', [
            'lead' => $lead,
            'subscriptions' => $this->subscriptions->list(['lead_id' => $lead->id], 20),
            'statuses' => SubscriptionStatus::cases(),
        ]);
    }

    public function create(): View
    {
        $this->authorize('create', Subscription::class);

        return view('subscriptions.create', [
            'leads' => Lead::active()->orderBy('company_name')->get(),
            'billingCycles' => BillingCycle::cases(),
        ]);
    }

    public function store(StoreSubscriptionRequest $request): RedirectResponse
    {
        $subscription = $this->subscriptions->create($request->validated(), $request->user());

        return redirect()->route('leads.show', $subscription->lead_id)->with('success', 'Subscription created.');
    }

    public function edit(Subscription $subscription): View
    {
        $this->authorize('update', $subscription);

        $subscription->load('lead', 'creator');

        return view('subscriptions.edit', [
            'subscription' => $subscription,
            'statuses' => SubscriptionStatus::cases(),
            'billingCycles' => BillingCycle::cases(),
        ]);
    }

    public function update(UpdateSubscriptionRequest $request, Subscription $subscription): RedirectResponse
    {
        $this->subscriptions->update($subscription, $request->validated(), $request->user());

        return redirect()->route('leads.show', $subscription->lead_id)->with('success', 'Subscription updated.');
    }

    public function destroy(Subscription $subscription): RedirectResponse
    {
        $this->authorize('delete', $subscription);

        $this->subscriptions->delete($subscription);

        return back()->with('success', 'Subscription deleted.');
    }
}
