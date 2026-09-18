<?php

namespace App\Support;

use Anuzpandey\LaravelNepaliDate\LaravelNepaliDate;
use Illuminate\Support\Carbon;

/**
 * Central place for Bikram Sambat (Nepali calendar) <-> Gregorian (AD)
 * conversion, backed by anuzpandey/laravel-nepali-date. BS months don't map
 * onto AD months at fixed offsets (month lengths vary year to year), so any
 * "BS month" filter has to be resolved through this conversion rather than
 * approximated with date arithmetic.
 */
class BsDate
{
    public const MONTHS = [
        1 => 'Baisakh',
        2 => 'Jestha',
        3 => 'Asar',
        4 => 'Shrawan',
        5 => 'Bhadra',
        6 => 'Aswin',
        7 => 'Kartik',
        8 => 'Mangsir',
        9 => 'Poush',
        10 => 'Magh',
        11 => 'Falgun',
        12 => 'Chaitra',
    ];

    /**
     * The current BS year, for centering "which years to offer" pickers.
     */
    public static function currentYear(): int
    {
        return (int) LaravelNepaliDate::from(Carbon::now())->toNepaliDateArray()->year;
    }

    /**
     * The [start, end] AD Carbon instants spanning the given BS year/month,
     * suitable for a whereBetween/>=,<= filter against a datetime column.
     *
     * @return array{0: Carbon, 1: Carbon}
     */
    public static function monthToAdRange(int $bsYear, int $bsMonth): array
    {
        $daysInMonth = LaravelNepaliDate::daysInMonth($bsMonth, $bsYear);

        $start = Carbon::parse(
            LaravelNepaliDate::from(sprintf('%04d-%02d-01', $bsYear, $bsMonth))->toEnglishDate()
        )->startOfDay();

        $end = Carbon::parse(
            LaravelNepaliDate::from(sprintf('%04d-%02d-%02d', $bsYear, $bsMonth, $daysInMonth))->toEnglishDate()
        )->endOfDay();

        return [$start, $end];
    }
}
