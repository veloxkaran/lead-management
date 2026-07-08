<?php

namespace App\Support;

class Currency
{
    public const SYMBOL = 'NPR';

    public static function format(int|float|string|null $amount, int $decimals = 2): string
    {
        return self::SYMBOL.' '.number_format((float) ($amount ?? 0), $decimals);
    }
}
