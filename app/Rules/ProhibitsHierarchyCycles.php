<?php

namespace App\Rules;

use App\Models\User;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

/**
 * Rejects a reporting_manager_id assignment that would make $userId its own
 * (in)direct manager. A self-referencing FK can't prevent this at the DB
 * level, and an undetected cycle would make
 * UserHierarchyRepository::getAllSubordinateIds()'s WITH RECURSIVE query
 * loop until its depth cap — this stops the cycle from being created at all.
 */
class ProhibitsHierarchyCycles implements ValidationRule
{
    public function __construct(protected ?int $userId) {}

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (! $value || ! $this->userId) {
            return;
        }

        if ((int) $value === $this->userId) {
            $fail('A user cannot report to themselves.');

            return;
        }

        $currentId = (int) $value;
        $seen = [];

        while ($currentId && ! in_array($currentId, $seen, true)) {
            if ($currentId === $this->userId) {
                $fail('This assignment would create a circular reporting relationship.');

                return;
            }

            $seen[] = $currentId;
            $currentId = User::whereKey($currentId)->value('reporting_manager_id');
        }
    }
}
