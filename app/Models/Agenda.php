<?php

namespace App\Models;

use App\Enums\AgendaStatus;
use App\Models\Concerns\BelongsToCompany;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Agenda extends Model
{
    use BelongsToCompany, HasFactory;

    protected $fillable = [
        'company_id', 'title', 'description', 'status',
        'created_by', 'last_updated_by', 'finalized_by', 'finalized_at',
    ];

    protected function casts(): array
    {
        return [
            'status' => AgendaStatus::class,
            'finalized_at' => 'datetime',
        ];
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function lastUpdatedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'last_updated_by');
    }

    public function finalizedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'finalized_by');
    }

    /**
     * Every comment, top-level or reply — used for the "Total Discussion
     * Count" and the "Most Discussed" sort. Chronological (oldest first)
     * since the thread reads top-to-bottom like a discussion, unlike
     * TaskComment::comments()'s newest-first activity log style.
     */
    public function comments(): HasMany
    {
        return $this->hasMany(AgendaComment::class)->oldest();
    }

    public function topLevelComments(): HasMany
    {
        return $this->comments()->whereNull('parent_id');
    }

    public function isPending(): bool
    {
        return $this->status === AgendaStatus::Pending;
    }
}
