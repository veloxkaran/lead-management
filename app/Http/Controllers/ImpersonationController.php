<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Services\ImpersonationService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class ImpersonationController extends Controller
{
    public function __construct(protected ImpersonationService $impersonation)
    {
    }

    public function start(Request $request, User $user): RedirectResponse
    {
        $this->authorize('impersonate', $user);

        if ($this->impersonation->isImpersonating($request)) {
            return back()->with('error', 'You are already viewing as another user. Return to your account first.');
        }

        $this->impersonation->start($request, $request->user(), $user);

        return redirect()->route('dashboard')->with('success', "You are now viewing as {$user->name}.");
    }

    public function stop(Request $request): RedirectResponse
    {
        $admin = $this->impersonation->stop($request);

        if (! $admin) {
            return redirect()->route('dashboard');
        }

        return redirect()->route('users.index')->with('success', 'You are back on your own account.');
    }
}
