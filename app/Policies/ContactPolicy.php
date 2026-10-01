<?php

namespace App\Policies;

use App\Enums\UserRole;
use App\Models\Contact;
use App\Models\User;

/**
 * Contacts are a shared address book for the roles that do outreach —
 * the same ones that can send campaigns: Super Admin, Manager and
 * Business Development. Anyone of them can add contacts and see them all;
 * editing or deleting one is for whoever added it, or a Manager / Super
 * Admin. Per-member permissions (Users → Permissions → Contacts) can
 * narrow this further.
 */
class ContactPolicy
{
    private const ROLES = [UserRole::SuperAdmin, UserRole::Manager, UserRole::BusinessDevelopment];

    public function viewAny(User $user): bool
    {
        return in_array($user->role, self::ROLES, true);
    }

    public function view(User $user, Contact $contact): bool
    {
        return $this->viewAny($user);
    }

    public function create(User $user): bool
    {
        return $this->viewAny($user);
    }

    public function update(User $user, Contact $contact): bool
    {
        return $this->viewAny($user)
            && ($user->isSuperAdmin() || $user->isManager() || $contact->created_by === $user->id);
    }

    public function delete(User $user, Contact $contact): bool
    {
        return $this->update($user, $contact);
    }
}
