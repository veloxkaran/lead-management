<?php

namespace App\Models;

use App\Enums\UserRole;
use App\Enums\UserStatus;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasApiTokens, HasFactory, Notifiable, SoftDeletes;

    protected $fillable = [
        'name',
        'email',
        'phone',
        'department',
        'department_id',
        'designation',
        'role',
        'status',
        'team_id',
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
            'policy_ack_last_prompted_at' => 'datetime',
            'password' => 'hashed',
            'role' => UserRole::class,
            'status' => UserStatus::class,
        ];
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
     * Super Admin keeps exclusive control of configuration (users, roles,
     * lead statuses, settings); Manager gets the same company-wide view
     * everywhere else — reports, tracking lists, dashboards — so this is
     * the check for "sees everything" rather than "can configure anything".
     */
    public function isOverseer(): bool
    {
        return $this->isSuperAdmin() || $this->isManager();
    }

    public function isActive(): bool
    {
        return $this->status === UserStatus::Active;
    }

    public function team(): BelongsTo
    {
        return $this->belongsTo(Team::class);
    }

    /**
     * Named to avoid colliding with the legacy `department` string column
     * (still present, still used by the profile self-edit form) — Eloquent
     * always resolves a raw attribute before a same-named relationship
     * method, so `department()` here would be permanently unreachable.
     */
    public function assignedDepartment(): BelongsTo
    {
        return $this->belongsTo(Department::class, 'department_id');
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

    public function goals(): HasMany
    {
        return $this->hasMany(Goal::class);
    }

    public function whatsappLeads(): BelongsToMany
    {
        return $this->belongsToMany(Lead::class, 'lead_whatsapp_user');
    }

    public function dailySummaries(): HasMany
    {
        return $this->hasMany(DailySummary::class);
    }

    /**
     * Individual Job Descriptions assigned directly to this user (as opposed
     * to Sops/Department JDs, which are scoped via department()).
     */
    public function individualPolicyDocuments(): HasMany
    {
        return $this->hasMany(PolicyDocument::class);
    }

    public function policyDocumentAcknowledgments(): HasMany
    {
        return $this->hasMany(PolicyDocumentAcknowledgment::class);
    }
}
