<?php

namespace App\Http\Controllers;

use App\Enums\EmailProvider;
use App\Http\Requests\EmailAccount\StoreEmailAccountRequest;
use App\Http\Requests\EmailAccount\UpdateEmailAccountRequest;
use App\Models\EmailAccount;
use App\Services\EmailAccountService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class EmailAccountController extends Controller
{
    public function __construct(protected EmailAccountService $emailAccounts)
    {
    }

    public function index(Request $request): View
    {
        $this->authorize('viewAny', EmailAccount::class);

        $filters = $request->only(['provider', 'is_active']);

        return view('email-accounts.index', [
            'accounts' => $this->emailAccounts->list($filters, $request->user(), 20),
            'providers' => EmailProvider::cases(),
            'filters' => $filters,
        ]);
    }

    public function create(): View
    {
        $this->authorize('create', EmailAccount::class);

        return view('email-accounts.create', [
            'providers' => EmailProvider::cases(),
        ]);
    }

    public function store(StoreEmailAccountRequest $request): RedirectResponse
    {
        $account = $this->emailAccounts->create($request->validated(), $request->user());

        return redirect()->route('email-accounts.index')->with('success', "Email account \"{$account->email_address}\" added.");
    }

    public function edit(EmailAccount $emailAccount): View
    {
        $this->authorize('update', $emailAccount);

        return view('email-accounts.edit', [
            'account' => $emailAccount,
        ]);
    }

    public function update(UpdateEmailAccountRequest $request, EmailAccount $emailAccount): RedirectResponse
    {
        $attributes = $request->validated();

        // Leaving the password field blank keeps the existing credential —
        // it must never be overwritten with an empty string.
        if (empty($attributes['password'])) {
            unset($attributes['password']);
        }

        $this->emailAccounts->update($emailAccount, $attributes, $request->user(), $request->ip(), $request->userAgent());

        return redirect()->route('email-accounts.index')->with('success', 'Email account updated.');
    }

    public function destroy(Request $request, EmailAccount $emailAccount): RedirectResponse
    {
        $this->authorize('delete', $emailAccount);

        $this->emailAccounts->delete($emailAccount, $request->user(), $request->ip(), $request->userAgent());

        return redirect()->route('email-accounts.index')->with('success', 'Email account deleted.');
    }

    public function testConnection(Request $request, EmailAccount $emailAccount): RedirectResponse
    {
        $this->authorize('update', $emailAccount);

        $account = $this->emailAccounts->testConnection($emailAccount, $request->user(), $request->ip(), $request->userAgent());

        return back()->with(
            $account->connection_status->value === 'connected' ? 'success' : 'error',
            "Connection {$account->connection_status->label()}".($account->connection_error ? ": {$account->connection_error}" : '.')
        );
    }

    public function setDefault(Request $request, EmailAccount $emailAccount): RedirectResponse
    {
        $this->authorize('update', $emailAccount);

        $this->emailAccounts->setDefault($emailAccount, $request->user());

        return back()->with('success', "\"{$emailAccount->email_address}\" is now your default account.");
    }

    public function toggleActive(Request $request, EmailAccount $emailAccount): RedirectResponse
    {
        $this->authorize('update', $emailAccount);

        $this->emailAccounts->toggleActive($emailAccount, $request->user(), $request->ip(), $request->userAgent());

        return back()->with('success', 'Email account status updated.');
    }
}
