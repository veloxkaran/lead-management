<?php

namespace App\Support\ActivityModules;

use App\Enums\ActivityModule;
use App\Models\User;
use Closure;
use Illuminate\Database\Eloquent\Model;

/**
 * Everything the Activity Feed needs to know about one module in order to
 * display it and resolve a viewer-specific permission/link for a logged
 * entry. See ActivityModuleRegistry for how these are assembled — adding a
 * new module means adding one of these, not editing ActivityLinkResolver.
 */
final class ActivityModuleDefinition
{
    /**
     * @param  Closure(Model, User): array{can_view: bool, url: string|null}  $resolveLink
     */
    public function __construct(
        public readonly ActivityModule $module,
        public readonly string $label,
        public readonly string $icon,
        public readonly Closure $resolveLink,
    ) {
    }

    /**
     * @return array{can_view: bool, url: string|null}
     */
    public function resolveLinkFor(Model $subject, User $viewer): array
    {
        return ($this->resolveLink)($subject, $viewer);
    }
}
