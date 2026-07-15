<?php

namespace App\Http\Middleware;

use App\Services\PolicyAcknowledgmentThrottleService;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\View;
use Symfony\Component\HttpFoundation\Response;

/**
 * Decides, on every full-page GET request, whether to show the SOP/JD
 * acknowledgment modal (the actual decision lives in
 * PolicyAcknowledgmentThrottleService). Skips AJAX/JSON requests entirely
 * (including this feature's own view/acknowledge endpoints, and unrelated
 * polling like the WhatsApp inbox) since those aren't full page loads and
 * axios.defaults sets X-Requested-With globally (resources/js/bootstrap.js),
 * which Request::ajax() picks up.
 */
class ResolvePendingPolicyAcknowledgments
{
    public function __construct(protected PolicyAcknowledgmentThrottleService $throttle)
    {
    }

    public function handle(Request $request, Closure $next): Response
    {
        View::share('pendingPolicyDocuments', collect());

        $user = $request->user();

        if ($user && $request->isMethod('GET') && ! $request->ajax() && ! $request->expectsJson()) {
            View::share('pendingPolicyDocuments', $this->throttle->resolveForRequest($user));
        }

        return $next($request);
    }
}
