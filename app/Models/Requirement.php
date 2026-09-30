<?php

namespace App\Models;

use App\Enums\RequirementPriority;
use App\Enums\RequirementStatus;
use App\Models\Concerns\BelongsToCompany;
use App\Models\Concerns\LocksAfterCompletion;
use App\Models\Concerns\TracksResolutionTime;
use App\Support\HtmlToText;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;
use Mews\Purifier\Facades\Purifier;

class Requirement extends Model
{
    use BelongsToCompany, HasFactory, LocksAfterCompletion, TracksResolutionTime;

    protected $fillable = [
        'company_id', 'lead_id', 'title', 'requirement', 'priority', 'status', 'due_date',
        'client_acknowledged_at', 'assigned_to', 'created_by', 'completed_at',
    ];

    /**
     * completed_at records when the requirement was solved, however it got
     * there (created as completed, edited, or a status change). Reopening
     * clears it — otherwise it keeps counting as "closed" (and skewing the
     * average solving time) on the dashboard's Performance Snapshot even
     * though it's active again.
     */
    protected static function booted(): void
    {
        static::saving(function (Requirement $requirement) {
            $isCompleted = $requirement->status === RequirementStatus::Completed;

            if ($isCompleted && $requirement->completed_at === null) {
                $requirement->completed_at = now();
            } elseif (! $isCompleted && $requirement->completed_at !== null) {
                $requirement->completed_at = null;
            }
        });
    }

    protected function casts(): array
    {
        return [
            'priority' => RequirementPriority::class,
            'status' => RequirementStatus::class,
            'due_date' => 'date',
            'client_acknowledged_at' => 'datetime',
            'completed_at' => 'datetime',
        ];
    }

    /**
     * Past its due date and not yet completed.
     */
    public function isOverdue(): bool
    {
        return $this->due_date !== null
            && $this->due_date->isPast()
            && $this->status !== RequirementStatus::Completed;
    }

    public function isCompleted(): bool
    {
        return $this->status === RequirementStatus::Completed;
    }

    /**
     * How long it took to solve (created → completed), e.g. "1 days, 2 hour
     * and 30 min". Null while the requirement is still open.
     */
    public function solvedInFormatted(): ?string
    {
        return $this->isCompleted() && $this->completed_at ? $this->elapsedFormatted() : null;
    }

    public function isAcknowledgedByClient(): bool
    {
        return $this->client_acknowledged_at !== null;
    }

    /**
     * `requirement` is saved already-sanitized by RequirementService, but
     * this re-cleans on read too so older rows written before the rich-text
     * editor existed (plain text, possibly with stray "<"/">") still render
     * safely as HTML instead of being interpreted as broken markup.
     */
    public function requirementHtml(): string
    {
        return Purifier::clean((string) $this->requirement);
    }

    /**
     * The requirement as readable plain text (structure kept, no tags or
     * entities) — for client emails, Slack, activity and exports.
     */
    public function plainText(): string
    {
        return HtmlToText::convert($this->requirement);
    }

    /**
     * One-line label: the title if set, otherwise the start of the text.
     */
    public function summary(int $limit = 60): string
    {
        return $this->title ?: Str::limit(preg_replace('/\s+/u', ' ', $this->plainText()), $limit);
    }

    public function lead(): BelongsTo
    {
        return $this->belongsTo(Lead::class);
    }

    public function assignee(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_to');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function comments(): HasMany
    {
        return $this->hasMany(RequirementComment::class)->oldest();
    }

    public function attachments(): HasMany
    {
        return $this->hasMany(RequirementAttachment::class);
    }

    protected function resolvedAtColumn(): string
    {
        return 'completed_at';
    }

    protected static function noResolvedRecordsMessage(): string
    {
        return 'No completed requirements yet';
    }
}
