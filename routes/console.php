<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Schedule::command('follow-ups:send-reminders')->everyFiveMinutes();
Schedule::command('goals:reset-monthly')->dailyAt('00:10');
Schedule::command('slack:daily-summary')->dailyAt('18:00');
Schedule::command('db:backup')->hourly();

// Shared hosting (cPanel) has no long-running worker, so drain the queue from
// cron each minute. Must include "emails" — SendClientNotificationEmail uses it.
// Runs in-process (call, not command) so it still works where the host has
// disabled proc_open — Schedule::command() silently does nothing there. The
// overlap lock expires after 5 minutes instead of the default 24 hours, so a
// worker killed mid-run can't block emails for a whole day.
Schedule::call(function () {
    Artisan::call('queue:work', [
        '--queue' => 'emails,default',
        '--stop-when-empty' => true,
        '--tries' => 3,
        '--max-time' => 55,
    ]);

    if ($output = trim(Artisan::output())) {
        Log::channel('single')->info("Scheduled queue worker:\n".$output);
    }
})
    ->name('queue-worker')
    ->everyMinute()
    ->withoutOverlapping(5);
