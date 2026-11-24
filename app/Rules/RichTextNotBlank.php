<?php

namespace App\Rules;

use App\Support\RichText;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

/**
 * `required` alone lets an editor holding only empty lines or spaces
 * (<p><br></p><p> </p>) through, which then saves as a blank description.
 */
class RichTextNotBlank implements ValidationRule
{
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (is_string($value) && RichText::isBlank($value)) {
            $fail('The :attribute field is required.');
        }
    }
}
