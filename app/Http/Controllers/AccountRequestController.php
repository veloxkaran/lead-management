<?php

namespace App\Http\Controllers;

use App\Enums\AccountRequestType;
use App\Enums\RequirementStatus;
use App\Enums\UserRole;
use App\Http\Requests\AccountRequest\StoreAccountRequestRequest;
use App\Http\Requests\AccountRequest\UpdateAccountRequestRequest;
use App\Models\AccountRequest;
use App\Models\Lead;
use App\Models\User;
use App\Services\AccountRequestService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class AccountRequestController extends Controller
{
    public function __construct(protected AccountRequestService $accountRequests)
    {
    }

    public function index(Request $request): View
    {
        $this->authorize('viewAny', AccountRequest::class);

        $filters = $request->only(['status']);

        if ($request->user()->isBusinessDevelopment()) {
            $filters['requested_by'] = $request->user()->id;
        }

        return view('account-requests.index', [
            'requests' => $this->accountRequests->list($filters, 20),
            'statuses' => RequirementStatus::cases(),
            'filters' => $request->only(['status']),
        ]);
    }

    public function create(): View
    {
        $this->authorize('create', AccountRequest::class);

        return view('account-requests.create', [
            'leads' => Lead::active()->orderBy('company_name')->get(),
            'types' => AccountRequestType::cases(),
        ]);
    }

    public function store(StoreAccountRequestRequest $request): RedirectResponse
    {
        $this->accountRequests->create($request->validated(), $request->user());

        return redirect()->route('account-requests.index')->with('success', 'Account request sent to Finance.');
    }

    public function edit(AccountRequest $accountRequest): View
    {
        $this->authorize('update', $accountRequest);

        $accountRequest->load('lead', 'requester', 'processor');

        return view('account-requests.edit', [
            'accountRequest' => $accountRequest,
            'statuses' => RequirementStatus::cases(),
            'types' => AccountRequestType::cases(),
            'users' => User::where('role', UserRole::Finance)->orderBy('name')->get(),
        ]);
    }

    public function update(UpdateAccountRequestRequest $request, AccountRequest $accountRequest): RedirectResponse
    {
        $this->accountRequests->update($accountRequest, $request->validated());

        return redirect()->route('account-requests.index')->with('success', 'Account request updated.');
    }

    public function destroy(AccountRequest $accountRequest): RedirectResponse
    {
        $this->authorize('delete', $accountRequest);

        $this->accountRequests->delete($accountRequest);

        return back()->with('success', 'Account request deleted.');
    }
}
