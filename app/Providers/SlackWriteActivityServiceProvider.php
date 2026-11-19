<?php

namespace App\Providers;

use App\Support\SlackWriteActivity;
use Illuminate\Queue\Events\JobProcessing;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\ServiceProvider;

/**
 * Wires SlackWriteActivity: listens to every App\Models created/updated/
 * deleted event, and flushes the buffered lines as one Slack message at the
 * end of each web request / artisan command (terminating) and after each
 * queue job — the scheduled worker is one long artisan process, so without
 * the per-job flush a minute of campaign work would arrive as one blob.
 */
class SlackWriteActivityServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(SlackWriteActivity::class);
    }

    public function boot(): void
    {
        foreach (['created', 'updated', 'deleted'] as $action) {
            Event::listen("eloquent.{$action}: App\\Models\\*", function (string $event, array $payload) use ($action) {
                $this->app->make(SlackWriteActivity::class)->record($action, $payload[0]);
            });
        }

        Queue::before(fn (JobProcessing $event) => $this->app->make(SlackWriteActivity::class)->startJob($event->job->resolveName()));
        Queue::after(fn () => $this->app->make(SlackWriteActivity::class)->flush());
        Queue::exceptionOccurred(fn () => $this->app->make(SlackWriteActivity::class)->flush());

        $this->app->terminating(fn () => $this->app->make(SlackWriteActivity::class)->flush());
    }
}
