<?php

namespace App\Http\Middleware;

use App\Enums\PermissionAction;
use App\Enums\PermissionModule;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * `permission:{module}` on a route group infers the action from each
 * route's controller method (PermissionAction::forRouteMethod()).
 * `permission:{module},{action}` on a single route names it explicitly and
 * wins over the group's inferred one — e.g. posting a comment is an edit of
 * the parent record, not a create.
 */
class EnsureUserHasPermission
{
    public function handle(Request $request, Closure $next, string $module, ?string $action = null): Response
    {
        $route = $request->route();

        if ($action === null && $this->routeNamesExplicitAction($route->gatherMiddleware(), $module)) {
            return $next($request);
        }

        $module = PermissionModule::from($module);
        $action = $action !== null
            ? PermissionAction::from($action)
            : PermissionAction::forRouteMethod($route->getActionMethod(), $request->method());

        abort_unless(
            $request->user()?->hasPermission($module, $action),
            403,
            "You don't have permission to ".strtolower($action->label())." {$module->label()}."
        );

        return $next($request);
    }

    private function routeNamesExplicitAction(array $middleware, string $module): bool
    {
        foreach ($middleware as $entry) {
            if (is_string($entry) && str_starts_with($entry, "permission:{$module},")) {
                return true;
            }
        }

        return false;
    }
}
