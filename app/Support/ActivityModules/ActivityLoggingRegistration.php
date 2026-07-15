<?php

namespace App\Support\ActivityModules;

use App\Enums\ActivityModule;
use Closure;
use Illuminate\Database\Eloquent\Model;

/**
 * One "when a model of this class is created, log this module event" rule.
 * A module can have several of these (e.g. the Lead module logs on Lead
 * creation, on a status change, and on a deal closing — three different
 * model classes, one module). AppServiceProvider iterates these instead of
 * hardcoding each Model::created() call.
 */
final class ActivityLoggingRegistration
{
    /**
     * @param  class-string<Model>  $modelClass
     * @param  Closure(Model): string  $describe
     * @param  Closure(Model): (int|null)  $actorId
     */
    public function __construct(
        public readonly string $modelClass,
        public readonly ActivityModule $module,
        public readonly Closure $describe,
        public readonly Closure $actorId,
    ) {
    }
}
