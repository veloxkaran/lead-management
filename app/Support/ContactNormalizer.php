<?php

namespace App\Support;

/**
 * Canonical forms used to decide whether two contacts are the same, so
 * "Ram@Example.com " and "ram@example.com", or "+977-980-0000000" and
 * "9800000000", are recognised as duplicates.
 */
class ContactNormalizer
{
    public static function email(mixed $value): ?string
    {
        $email = mb_strtolower(trim((string) $value));

        return $email !== '' && filter_var($email, FILTER_VALIDATE_EMAIL) !== false ? $email : null;
    }

    /**
     * Digits only, with a leading "00" and the default country code removed
     * (only when what's left is still a full number, so a local number that
     * happens to start with those digits isn't cut short).
     */
    public static function phone(mixed $value): ?string
    {
        $digits = preg_replace('/\D+/', '', (string) $value);

        if (str_starts_with($digits, '00')) {
            $digits = substr($digits, 2);
        }

        $code = (string) config('campaigns.default_country_code');

        if ($code !== '' && str_starts_with($digits, $code) && strlen($digits) - strlen($code) >= 10) {
            $digits = substr($digits, strlen($code));
        }

        $length = strlen($digits);

        return $length >= 7 && $length <= 15 ? $digits : null;
    }
}
