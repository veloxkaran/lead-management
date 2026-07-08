<?php

namespace App\Models;

use App\Enums\UserRole;
use App\Enums\UserStatus;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
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
        'designation',
        'role',
        'status',
        'team_id',
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
        ];
    }

    public function isSuperAdmin(): bool
    {
        return $this->role === UserRole::SuperAdmin;
    }

    public function isActive(): bool
    {
        return $this->status === UserStatus::Active;
    }

    public function team(): BelongsTo
    {
        return $this->belongsTo(Team::class);
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

    public function dailySummaries(): HasMany
    {
        return $this->hasMany(DailySummary::class);
    }
}
