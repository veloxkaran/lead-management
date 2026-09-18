<?php

namespace App\Models\Concerns;

use Illuminate\Support\Carbon;

/**
 * Shared "how long did this take to resolve" arithmetic for models that
 * track a start (created_at) and an optional completion timestamp — e.g.
 * SupportTicket::resolved_at, Requirement::completed_at. Per-record elapsed
 * time freezes once the completion timestamp is set instead of continuing
 * to climb; the org-wide average only considers records that have one.
 */
trait TracksResolutionTime
{
    /**
     * The column holding when this record was resolved/completed — null
     * while still open. Implemented per model since the column name isn't
     * shared (resolved_at vs completed_at).
     */
    abstract protected function resolvedAtColumn(): string;

    /**
     * Shown by averageResolutionFormatted() when nothing has been resolved
     * yet. Override per model for wording that matches its domain noun.
     */
    protected static function noResolvedRecordsMessage(): string
    {
        return 'No resolved records yet';
    }

    /**
     * Minutes since this record was created — up to now while still open,
     * or up to the completion timestamp once resolved (so the value
     * freezes at resolution instead of continuing to climb).
     */
    public function elapsedMinutes(): int
    {
        $resolvedAt = $this->{$this->resolvedAtColumn()};

        return $this->created_at->diffInMinutes($resolvedAt ?? now());
    }

    /**
     * elapsedMinutes() broken into "N days, N hour and N min", e.g.
     * "1 days, 10 hour and 5 min".
     */
    public function elapsedFormatted(): string
    {
        return static::formatMinutesAsDaysHoursMinutes($this->elapsedMinutes());
    }

    /**
     * Average resolution time in minutes, across every resolved record by
     * default or — when $from/$to are given — only those resolved within
     * that window (e.g. the dashboard's Daily/Monthly performance snapshot).
     * Null when nothing matching has been resolved yet, so callers can tell
     * "no data" apart from a genuine zero.
     */
    public static function averageResolutionMinutes(?Carbon $from = null, ?Carbon $to = null): ?float
    {
        $column = (new static)->resolvedAtColumn();

        $query = static::query()->whereNotNull($column);

        if ($from) {
            $query->where($column, '>=', $from);
        }

        if ($to) {
            $query->where($column, '<=', $to);
        }

        $resolved = $query->get(['created_at', $column]);

        if ($resolved->isEmpty()) {
            return null;
        }

        return $resolved->avg(fn (self $record) => $record->created_at->diffInMinutes($record->{$column}));
    }

    /**
     * averageResolutionMinutes() broken into "N days, N hour and N min",
     * for display on a dashboard stat. Accepts the same optional $from/$to
     * window.
     */
    public static function averageResolutionFormatted(?Carbon $from = null, ?Carbon $to = null): string
    {
        $avgMinutes = static::averageResolutionMinutes($from, $to);

        if ($avgMinutes === null) {
            return static::noResolvedRecordsMessage();
        }

        return static::formatMinutesAsDaysHoursMinutes((int) round($avgMinutes));
    }

    /**
     * Shared by elapsedFormatted() and averageResolutionFormatted() so both
     * render the same "N days, N hour and N min" shape.
     */
    protected static function formatMinutesAsDaysHoursMinutes(int $totalMinutes): string
    {
        $days = intdiv($totalMinutes, 1440);
        $hours = intdiv($totalMinutes % 1440, 60);
        $minutes = $totalMinutes % 60;

        return sprintf('%d days, %d hour and %d min', $days, $hours, $minutes);
    }
}
