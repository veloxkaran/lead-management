<?php

namespace App\Support;

use Illuminate\Support\Carbon;

/**
 * Resolves a "period" preset (today/week/month/custom) plus optional
 * date_from/date_to into a concrete [from, to] Carbon pair. Originally lived
 * only in RawDataRepository; pulled out here once the dashboard's
 * "What's New Today" widget needed the exact same today/week/month/custom
 * vocabulary and date_from/date_to field names.
 */
class PeriodRange
{
    /**
     * @return array{0: ?Carbon, 1: ?Carbon}
     */
    public static function resolve(array $filters, ?string $defaultPeriod = null): array
    {
        return match ($filters['period'] ?? $defaultPeriod) {
            'today' => [now()->startOfDay(), now()->endOfDay()],
            'week' => [now()->startOfWeek(), now()->endOfWeek()],
            'month' => [now()->startOfMonth(), now()->endOfMonth()],
            'custom' => [
                ! empty($filters['date_from']) ? Carbon::parse($filters['date_from'])->startOfDay() : null,
                ! empty($filters['date_to']) ? Carbon::parse($filters['date_to'])->endOfDay() : null,
            ],
            default => [null, null],
        };
    }
}
