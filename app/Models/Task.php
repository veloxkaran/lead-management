<?php

namespace App\Models;

use App\Enums\TaskModule;
use App\Enums\TaskPriority;
use App\Enums\TaskStatus;
use App\Models\Concerns\BelongsToCompany;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class Task extends Model
{
    use BelongsToCompany, HasFactory, SoftDeletes;

    protected $fillable = [
        'company_id', 'module', 'taskable_type', 'taskable_id', 'lead_id', 'title', 'description',
        'priority', 'status', 'assigned_by', 'assigned_to', 'created_by', 'due_date',
        'estimated_hours', 'actual_hours', 'completion_percentage', 'completed_at',
    ];

    protected function casts(): array
    {
        return [
            'module' => TaskModule::class,
            'priority' => TaskPriority::class,
            'status' => TaskStatus::class,
            'due_date' => 'date',
            'estimated_hours' => 'decimal:2',
            'actual_hours' => 'decimal:2',
            'completion_percentage' => 'integer',
            'completed_at' => 'datetime',
        ];
    }

    public function lead(): BelongsTo
    {
        return $this->belongsTo(Lead::class);
    }

    public function taskable(): MorphTo
    {
        return $this->morphTo();
    }

    public function assignedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_by');
    }

    public function assignee(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_to');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function checklistItems(): HasMany
    {
        return $this->hasMany(TaskChecklistItem::class)->orderBy('position');
    }

    public function comments(): HasMany
    {
        return $this->hasMany(TaskComment::class)->latest();
    }

    /**
     * Derived live from the assignee's current reporting_manager_id rather
     * than stored on the task — see the Phase 1 plan's rationale: hierarchy
     * access control elsewhere in this app is always computed live, and
     * snapshotting here would let a re-org silently desync Task visibility
     * from the rest of the system.
     */
    public function assigneeManager(): ?User
    {
        return $this->assignee?->reportingManager;
    }

    public function isOverdue(): bool
    {
        return $this->due_date !== null
            && $this->due_date->isPast()
            && ! in_array($this->status, [TaskStatus::Completed, TaskStatus::Cancelled], true);
    }
}
