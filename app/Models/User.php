<?php

namespace App\Models;

use App\Enums\PermissionAction;
use App\Enums\PermissionModule;
use App\Enums\UiTheme;
use App\Enums\UserRole;
use App\Enums\UserStatus;
use App\Services\OrganizationHierarchyService;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Facades\Cache;
use Laravel\Sanctum\HasApiTokens;

class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasApiTokens, HasFactory, Notifiable, SoftDeletes;

    protected $fillable = [
        'name',
        'email',
        'phone',
        'designation',
        'role',
        'status',
        'reporting_manager_id',
        'company_id',
        'password',
        'suspended_at',
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'suspended_at' => 'datetime',
            'password' => 'hashed',
            'role' => UserRole::class,
            'status' => UserStatus::class,
            'theme' => UiTheme::class,
            'permissions' => 'array',
        ];
    }

    /**
     * Component permissions, set per member by a Super Admin. Stored as
     * module => list of allowed actions; a null column or a missing module
     * key means full access, so everyone has everything until restricted
     * (and components added later default to open). Super Admins can't be
     * restricted — they'd be able to lock themselves out of the screen that
     * grants access back. Without View, no other action on that component
     * is allowed; actions a component doesn't have fall back to View.
     * Deliberately not fillable — only UserPermissionController writes it.
     */
    public function hasPermission(PermissionModule|string $module, PermissionAction|string $action = PermissionAction::View): bool
    {
        $module = $module instanceof PermissionModule ? $module : PermissionModule::from($module);
        $action = $action instanceof PermissionAction ? $action : PermissionAction::from($action);

        if ($this->isSuperAdmin() || ! isset($this->permissions[$module->value])) {
            return true;
        }

        $allowed = $this->permissions[$module->value];

        if (! in_array(PermissionAction::View->value, $allowed, true)) {
            return false;
        }

        return ! $module->supports($action) || in_array($action->value, $allowed, true);
    }

    public function hasRestrictedPermissions(): bool
    {
        return ! $this->isSuperAdmin() && ! empty($this->permissions);
    }

    /**
     * The theme to render the app in — the user's pick, or the default.
     */
    public function uiTheme(): UiTheme
    {
        return $this->theme ?? UiTheme::DEFAULT;
    }

    public function isSuperAdmin(): bool
    {
        return $this->role === UserRole::SuperAdmin;
    }

    public function isManager(): bool
    {
        return $this->role === UserRole::Manager;
    }

    public function isBusinessDevelopment(): bool
    {
        return $this->role === UserRole::BusinessDevelopment;
    }

    public function isCustomerSuccess(): bool
    {
        return $this->role === UserRole::CustomerSuccess;
    }

    public function isFinance(): bool
    {
        return $this->role === UserRole::Finance;
    }

    /**
     * "Sees everything" (reports, tracking lists, dashboards) rather than
     * "can configure anything" (Super Admin keeps exclusive control of
     * configuration — users, lead statuses, settings). Hierarchy-derived,
     * not role-derived: a user becomes an overseer simply by having any
     * direct or indirect report, per OrganizationHierarchyService — no
     * separate "Manager" role is checked here.
     */
    public function isOverseer(): bool
    {
        return $this->isSuperAdmin() || app(OrganizationHierarchyService::class)->hasSubordinates($this);
    }

    public function isActive(): bool
    {
        return $this->status === UserStatus::Active;
    }

    /**
     * Cache invalidation only, not authorization: moving a user invalidates
     * every ancestor's cached subordinate-id list, not just the moved
     * user's own (which is unaffected by their own reassignment). Walks
     * both the old and new manager chains via reportingManager() — see
     * OrganizationHierarchyService::getManagerChain() for the same
     * upward-walk logic reused for authorization/tree-building.
     */
    protected static function booted(): void
    {
        static::updated(function (self $user) {
            if (! $user->wasChanged('reporting_manager_id')) {
                return;
            }

            self::forgetAncestorHierarchyCaches($user->getOriginal('reporting_manager_id'));
            self::forgetAncestorHierarchyCaches($user->reporting_manager_id);
        });

        static::deleted(function (self $user) {
            self::forgetAncestorHierarchyCaches($user->reporting_manager_id);
        });
    }

    private static function forgetAncestorHierarchyCaches(?int $startingManagerId): void
    {
        $seen = [];
        $current = $startingManagerId ? self::find($startingManagerId) : null;

        while ($current && ! in_array($current->id, $seen, true)) {
            Cache::forget("org_hierarchy:subordinate_ids:{$current->id}");
            $seen[] = $current->id;
            $current = $current->reportingManager;
        }
    }

    public function reportingManager(): BelongsTo
    {
        return $this->belongsTo(self::class, 'reporting_manager_id');
    }

    public function directReports(): HasMany
    {
        return $this->hasMany(self::class, 'reporting_manager_id');
    }

    /**
     * No BelongsToCompany trait here deliberately: a global scope on the
     * users table would call Auth::user() while the guard is still
     * resolving the authenticated user from that same table, recursing.
     */
    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function assignedLeads(): HasMany
    {
        return $this->hasMany(Lead::class, 'assigned_user_id');
    }

    public function createdLeads(): HasMany
    {
        return $this->hasMany(Lead::class, 'created_by');
    }

    public function leadActivities(): HasMany
    {
        return $this->hasMany(LeadActivity::class, 'created_by');
    }

    public function leadNotes(): HasMany
    {
        return $this->hasMany(LeadNote::class, 'author_id');
    }

    public function assignedRequirements(): HasMany
    {
        return $this->hasMany(Requirement::class, 'assigned_to');
    }

    public function dailySummaries(): HasMany
    {
        return $this->hasMany(DailySummary::class);
    }
}
