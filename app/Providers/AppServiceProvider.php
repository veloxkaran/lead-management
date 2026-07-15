<?php

namespace App\Providers;

use App\Models\ActivityLogEntry;
use App\Support\ActivityModules\ActivityLoggingRegistration;
use App\Support\ActivityModules\ActivityModuleRegistry;
use Illuminate\Pagination\Paginator;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Paginator::useBootstrapFive();

        $this->registerActivityLogging();
    }

    /**
     * Iterates ActivityModuleRegistry::loggingRegistrations() instead of
     * hardcoding one Model::created() call per module here — adding a new
     * logging hook now means adding one registry entry, not editing this
     * method. Plain closures (not an Observer class): Eloquent's
     * Model::observe() only remembers the observer's class name and
     * re-resolves a fresh instance via the container whenever the event
     * fires — it can't carry constructor arguments like these per-module
     * closures, so a parameterized Observer class doesn't work here.
     */
    private function registerActivityLogging(): void
    {
        foreach (ActivityModuleRegistry::loggingRegistrations() as $registration) {
            $registration->modelClass::created(
                fn ($model) => $this->logActivity($registration, $model)
            );
        }
    }

    /**
     * Writes are unconditional — the Super Admin per-module toggle
     * (ActivityFeedSettings::enabledModules()) only filters what the feed
     * query returns (ActivityFeedController), not what gets recorded, so
     * history survives a module being switched off and back on.
     */
    private function logActivity(ActivityLoggingRegistration $registration, $model): void
    {
        $actorId = ($registration->actorId)($model);

        if (! $actorId) {
            return;
        }

        ActivityLogEntry::create([
            'company_id' => $model->company_id ?? auth()->user()?->company_id,
            'user_id' => $actorId,
            'module' => $registration->module,
            'description' => ($registration->describe)($model),
            'subject_type' => $model->getMorphClass(),
            'subject_id' => $model->getKey(),
        ]);
    }
}
