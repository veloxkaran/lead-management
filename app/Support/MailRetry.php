<?php

namespace App\Support;

use Illuminate\Contracts\Queue\Job;
use Illuminate\Queue\Jobs\SyncJob;
use Throwable;

/**
 * Which send failures are safe to try again. Only ones where the mail
 * server certainly did NOT take the message — so a retry can never deliver
 * a second copy:
 *
 *   • it couldn't be reached at all (DNS hiccup, connection refused/timed out)
 *   • it answered with a temporary 4xx code (busy, rate limiting, greylisting)
 *
 * Anything else (a 5xx rejection, a connection dropped mid-message) is
 * final: a 5xx won't change on retry, and a dropped connection might have
 * happened after the message was already accepted.
 */
class MailRetry
{
    /** Wait before each retry: 1 min, 5 min, 15 min. */
    public const DELAYS = [60, 300, 900];

    public static function isRetryable(Throwable $e): bool
    {
        $message = $e->getMessage();

        return (bool) preg_match('/Connection could not be established|getaddrinfo|php_network_getaddresses|Connection refused|Network is unreachable/i', $message)
            || (bool) preg_match('/got code "4\d\d"/', $message);
    }

    /**
     * Seconds to wait before retrying this failed send, or null when it
     * shouldn't be retried: not a safe-to-retry error, retries used up, or
     * not running on a real queue (the sync driver can't delay a job, so a
     * "retry later" would leave the email stuck as pending).
     */
    public static function delay(?Job $job, Throwable $e): ?int
    {
        if (! $job || $job instanceof SyncJob || ! self::isRetryable($e)) {
            return null;
        }

        return self::delayFor($job->attempts());
    }

    /**
     * Seconds to wait before retry number $attempt (1-based), or null once
     * the retries are used up.
     */
    public static function delayFor(int $attempt): ?int
    {
        return self::DELAYS[$attempt - 1] ?? null;
    }

    public static function maxAttempts(): int
    {
        return count(self::DELAYS) + 1;
    }
}
