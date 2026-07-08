<?php

namespace App\Rules;

use App\Models\Lead;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

/**
 * Rejects a lead company name that's a near-duplicate (>= threshold%
 * character similarity via similar_text) of an existing lead's name.
 * Catches typo'd re-entries of the same company rather than exact
 * duplicates, which a plain unique rule wouldn't.
 */
class NotDuplicateLeadName implements ValidationRule
{
    public function __construct(protected ?int $ignoreLeadId = null, protected float $threshold = 70.0)
    {
    }

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (! is_string($value) || trim($value) === '') {
            return;
        }

        $existingNames = Lead::query()
            ->when($this->ignoreLeadId, fn ($q) => $q->where('id', '!=', $this->ignoreLeadId))
            ->pluck('company_name');

        foreach ($existingNames as $existingName) {
            similar_text(mb_strtolower($value), mb_strtolower($existingName), $percent);

            if ($percent >= $this->threshold) {
                $fail(sprintf(
                    'This name is too similar (%.0f%% match) to an existing lead, "%s". Please use a more distinct name.',
                    $percent,
                    $existingName
                ));

                return;
            }
        }
    }
}
