<?php

namespace App\Support;

use App\Models\ActivityLogEntry;
use App\Models\User;
use App\Support\ActivityModules\ActivityModuleRegistry;

/**
 * Thin backward-compatible facade: the actual per-module permission/link
 * logic lives in ActivityModuleRegistry (one definition per module, so a new
 * module doesn't require editing this class). Kept as its own class because
 * "resolve a viewer-specific link for a logged entry" reads more clearly at
 * call sites than reaching into the registry directly, and because whether
 * an activity row is clickable is decided fresh per viewer on every render
 * — never precomputed/stored — which is worth stating once here.
 */
class ActivityLinkResolver
{
    /**
     * @return array{can_view: bool, url: string|null}
     */
    public static function resolve(ActivityLogEntry $entry, User $viewer): array
    {
        $subject = $entry->subject;

        if (! $subject) {
            return ['can_view' => false, 'url' => null];
        }

        return ActivityModuleRegistry::definition($entry->module)->resolveLinkFor($subject, $viewer);
    }
}
