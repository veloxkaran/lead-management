<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

/**
 * Lets a Super Admin temporarily authenticate as another user ("login as")
 * and return to their own account afterward. State is tracked entirely in
 * the session (impersonator_id) — no DB table needed.
 */
class ImpersonationService
{
    protected const SESSION_KEY = 'impersonator_id';

    public function isImpersonating(Request $request): bool
    {
        return $request->session()->has(self::SESSION_KEY);
    }

    public function start(Request $request, User $admin, User $target): void
    {
        $request->session()->put(self::SESSION_KEY, $admin->id);

        Auth::login($target);

        $request->session()->regenerate();
    }

    /**
     * Ends impersonation and restores the original admin's session.
     * Returns the admin that was restored, or null if the session had no
     * impersonation in progress (e.g. a stale/duplicate request).
     */
    public function stop(Request $request): ?User
    {
        $adminId = $request->session()->pull(self::SESSION_KEY);

        if (! $adminId) {
            return null;
        }

        $admin = User::find($adminId);

        if ($admin) {
            Auth::login($admin);
        }

        $request->session()->regenerate();

        return $admin;
    }
}
