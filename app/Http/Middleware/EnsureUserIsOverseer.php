<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureUserIsOverseer
{
    public function handle(Request $request, Closure $next): Response
    {
        abort_unless($request->user()?->isOverseer(), 403, 'This action is restricted to Managers and Super Admins.');

        return $next($request);
    }
}
