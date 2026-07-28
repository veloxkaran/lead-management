<?php

namespace App\Rules;

use App\Models\RawData;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

/**
 * Rejects a Raw Data submission whose phone (or contact person, per a
 * separate instance of this rule on that field) exactly matches an
 * existing Raw Data row — case-insensitive, unlike NotDuplicateLeadName's
 * fuzzy similarity match, since a phone number either is or isn't the same
 * number. One instance per field (each reports against its own column)
 * rather than one rule checking both, so the error attaches to whichever
 * field actually matched.
 */
class NotDuplicateRawContact implements ValidationRule
{
    public function __construct(protected string $column, protected ?int $ignoreId = null)
    {
    }

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (! is_scalar($value) || trim((string) $value) === '') {
            return;
        }

        $existing = RawData::query()
            ->whereRaw('lower('.$this->column.') = ?', [mb_strtolower(trim((string) $value))])
            ->when($this->ignoreId, fn ($q) => $q->where('id', '!=', $this->ignoreId))
            ->first();

        if ($existing) {
            $label = $this->column === 'phone' ? 'phone number' : 'contact person';

            $fail("This {$label} already exists in Raw Data (entry #{$existing->id}, {$existing->contact_person} · {$existing->phone}).");
        }
    }
}
