<?php

namespace App\Enums;

enum PermissionAction: string
{
    case View = 'view';
    case Create = 'create';
    case Update = 'update';
    case Delete = 'delete';

    public function label(): string
    {
        return match ($this) {
            self::View => 'View',
            self::Create => 'Create',
            self::Update => 'Edit',
            self::Delete => 'Delete',
        };
    }

    /**
     * Maps a policy ability name onto the permission it needs, so the
     * Gate::before hook (see AppServiceProvider) can gate every existing
     * policy check without each policy knowing permissions exist. Anything
     * that isn't obviously a read, a create or a delete — changeStatus,
     * close, archive, manageSupportAccess, … — counts as an edit.
     */
    public static function forAbility(string $ability): self
    {
        return match (true) {
            str_starts_with($ability, 'view'), $ability === 'exportPdf' => self::View,
            $ability === 'create' => self::Create,
            str_starts_with($ability, 'delete') => self::Delete,
            default => self::Update,
        };
    }

    /**
     * Infers the permission a route needs from its controller method, for
     * EnsureUserHasPermission when the route doesn't name one explicitly.
     * Only `destroy` counts as a delete — other DELETE-verb routes (revoking
     * support access, removing a checklist item) are edits of the parent.
     */
    public static function forRouteMethod(string $method, string $httpVerb): self
    {
        return match (true) {
            $method === 'destroy' => self::Delete,
            $method === 'create', str_starts_with($method, 'store') => self::Create,
            $method === 'edit', $method === 'update' => self::Update,
            in_array($httpVerb, ['GET', 'HEAD'], true) => self::View,
            default => self::Update,
        };
    }
}
