<?php

namespace App\Support;

use Illuminate\Support\Carbon;

/**
 * A lightweight Bikram Sambat (Nepali) calendar converter.
 *
 * NOTE: Real BS month lengths vary year to year based on astronomical
 * calculations published by the Nepal Calendar Determination Committee.
 * This implementation uses a documented approximate model (a fixed
 * 12-month length pattern totalling 365 days, with a simple 4-year leap
 * rule adding a day to Ashar) rather than an embedded authoritative
 * year-by-year data table. It is accurate to within a day or two of the
 * true calendar and is sufficient for "reset every Nepali month"
 * scheduling; swap in an authoritative data table for exact precision.
 */
class NepaliCalendar
{
    protected const EPOCH_BS_YEAR = 2000;

    protected const EPOCH_AD_DATE = '1943-04-14';

    protected const MONTH_LENGTHS = [31, 32, 31, 32, 31, 30, 30, 29, 30, 29, 30, 30];

    protected const MONTH_NAMES = [
        'Baisakh', 'Jestha', 'Ashar', 'Shrawan', 'Bhadra', 'Ashwin',
        'Kartik', 'Mangsir', 'Poush', 'Magh', 'Falgun', 'Chaitra',
    ];

    public static function isLeapYear(int $bsYear): bool
    {
        return ($bsYear - self::EPOCH_BS_YEAR) % 4 === 0;
    }

    public static function yearLength(int $bsYear): int
    {
        return array_sum(self::MONTH_LENGTHS) + (self::isLeapYear($bsYear) ? 1 : 0);
    }

    protected static function monthLengths(int $bsYear): array
    {
        $lengths = self::MONTH_LENGTHS;

        if (self::isLeapYear($bsYear)) {
            $lengths[2] += 1; // extra day added to Ashar in approximate leap years
        }

        return $lengths;
    }

    public static function monthName(int $month): string
    {
        return self::MONTH_NAMES[$month - 1] ?? 'Unknown';
    }

    public static function fromGregorian(Carbon $date): array
    {
        $diffInDays = Carbon::parse(self::EPOCH_AD_DATE)->diffInDays($date, false);

        $bsYear = self::EPOCH_BS_YEAR;

        while ($diffInDays >= self::yearLength($bsYear)) {
            $diffInDays -= self::yearLength($bsYear);
            $bsYear++;
        }

        $months = self::monthLengths($bsYear);
        $bsMonth = 1;

        foreach ($months as $length) {
            if ($diffInDays < $length) {
                break;
            }

            $diffInDays -= $length;
            $bsMonth++;
        }

        return ['year' => $bsYear, 'month' => $bsMonth, 'day' => $diffInDays + 1];
    }

    public static function toGregorian(int $bsYear, int $bsMonth, int $bsDay): Carbon
    {
        $totalDays = 0;

        for ($year = self::EPOCH_BS_YEAR; $year < $bsYear; $year++) {
            $totalDays += self::yearLength($year);
        }

        $months = self::monthLengths($bsYear);

        for ($month = 1; $month < $bsMonth; $month++) {
            $totalDays += $months[$month - 1];
        }

        $totalDays += $bsDay - 1;

        return Carbon::parse(self::EPOCH_AD_DATE)->addDays($totalDays);
    }

    public static function today(): array
    {
        return self::fromGregorian(now());
    }

    public static function label(array $bs): string
    {
        return self::monthName($bs['month']).' '.$bs['year'];
    }
}
