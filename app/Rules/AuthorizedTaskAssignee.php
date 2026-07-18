<?php

namespace App\Rules;

use App\Models\User;
use App\Services\OrganizationHierarchyService;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

/**
 * Rejects assigning a task to anyone outside the assigner's own reporting
 * chain — mirrors ProhibitsHierarchyCycles's shape (a hierarchy constraint
 * checked against one input field at submission time), not a
 * TaskPolicy concern (which answers "can user X act on persisted Task Z").
 */
class AuthorizedTaskAssignee implements ValidationRule
{
    public function __construct(protected User $assigner, protected OrganizationHierarchyService $hierarchy)
    {
    }

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (! $value) {
            return;
        }

        $targetId = (int) $value;

        if ($this->assigner->isOverseer() || $targetId === $this->assigner->id) {
            return;
        }

        if (! $this->hierarchy->getAllSubordinateIds($this->assigner)->contains($targetId)) {
            $fail('You can only assign tasks to yourself or someone in your reporting chain.');
        }
    }
}
