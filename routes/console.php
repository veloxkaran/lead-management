<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Schedule::command('follow-ups:send-reminders')->everyFiveMinutes();
Schedule::command('goals:reset-monthly')->dailyAt('00:10');
Schedule::command('slack:daily-summary')->dailyAt('18:00');
