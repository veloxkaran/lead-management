<?php

namespace App\Models;

use App\Enums\RequirementPriority;
use App\Enums\RequirementStatus;
use App\Models\Concerns\BelongsToCompany;
use App\Models\Concerns\TracksResolutionTime;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Requirement extends Model
{
    use BelongsToCompany, HasFactory, TracksResolutionTime;

    public const MIN_SPRINT = 35;

    public const MAX_SPRINT = 50;

    protected $fillable = [
        'company_id', 'lead_id', 'requirement', 'priority', 'status', 'due_date',
        'client_acknowledged_at', 'assigned_to', 'sprint', 'created_by', 'completed_at',
    ];

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
     * @return array<int, string>
     */
    public static function sprintOptions(): array
    {
        return array_map(fn (int $n) => "Sprint {$n}", range(self::MIN_SPRINT, self::MAX_SPRINT));
    }

    public function isAcknowledgedByClient(): bool
    {
        return $this->client_acknowledged_at !== null;
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

    protected function resolvedAtColumn(): string
    {
        return 'completed_at';
    }

    protected static function noResolvedRecordsMessage(): string
    {
        return 'No completed requirements yet';
    }
}
