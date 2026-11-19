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
        6 => 'Asoj',
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

    /**
     * @return array{year: int, month: int, day: int}
     */
    public static function toBsParts(Carbon $date): array
    {
        $parts = LaravelNepaliDate::from($date)->toNepaliDateArray();

        return [
            'year' => (int) $parts->year,
            'month' => (int) $parts->month,
            'day' => (int) $parts->day,
        ];
    }

    /**
     * "Asoj 2" — a single day, for the dashboard's Daily snapshot label.
     */
    public static function dayLabel(Carbon $date): string
    {
        $bs = self::toBsParts($date);

        return self::MONTHS[$bs['month']].' '.$bs['day'];
    }

    /**
     * "Asoj" — a whole BS month with no day, for the Monthly snapshot label.
     */
    public static function monthLabel(Carbon $date): string
    {
        $bs = self::toBsParts($date);

        return self::MONTHS[$bs['month']];
    }

    /**
     * "Asoj 2083" — a BS month with its year, for the Monthly snapshot label
     * (the year matters once you step back past Baisakh).
     */
    public static function monthYearLabel(int $bsYear, int $bsMonth): string
    {
        return self::MONTHS[$bsMonth].' '.$bsYear;
    }

    /**
     * The BS year/month `$offset` months away from the given BS year/month
     * (e.g. -1 from Baisakh 2083 is Chaitra 2082).
     *
     * @return array{year: int, month: int}
     */
    public static function shiftMonth(int $bsYear, int $bsMonth, int $offset): array
    {
        $index = $bsYear * 12 + ($bsMonth - 1) + $offset;

        return ['year' => intdiv($index, 12), 'month' => $index % 12 + 1];
    }

    /**
     * "since 2082, Asoj" — for the Lifetime snapshot label, anchored to
     * whichever date it's tracking since (e.g. the earliest record).
     */
    public static function sinceLabel(Carbon $date): string
    {
        $bs = self::toBsParts($date);

        return "since {$bs['year']}, ".self::MONTHS[$bs['month']];
    }
}
