<?php

namespace App\Models\Concerns;

use App\Models\Company;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Auth;

/**
 * Scopes a model to the authenticated user's company and auto-fills
 * company_id on create. Deliberately not applied to the User model itself:
 * resolving auth()->user() from inside a global scope on the users table
 * would re-enter user resolution while the guard is still resolving it.
 *
 * Fails open (no scoping) outside an authenticated web request — console
 * commands, queued jobs, and Super Admin all bypass it — so this has no
 * effect on today's single-tenant behavior.
 */
trait BelongsToCompany
{
    public static function bootBelongsToCompany(): void
    {
        static::creating(function ($model) {
            if (empty($model->company_id) && Auth::check()) {
                $model->company_id = Auth::user()->company_id;
            }
        });

        static::addGlobalScope('company', function (Builder $builder) {
            if (! Auth::check()) {
                return;
            }

            $user = Auth::user();

            if ($user->isSuperAdmin() || empty($user->company_id)) {
                return;
            }

            $builder->where($builder->getModel()->getTable().'.company_id', $user->company_id);
        });
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }
}
