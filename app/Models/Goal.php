<?php

namespace App\Models;

use App\Enums\GoalCategory;
use App\Enums\GoalStatus;
use App\Models\Concerns\BelongsToCompany;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Goal extends Model
{
    use BelongsToCompany, HasFactory;

    protected $fillable = [
        'company_id', 'title', 'category', 'description', 'target', 'achieved',
        'start_date', 'end_date', 'bs_year', 'bs_month', 'created_by',
    ];

    protected function casts(): array
    {
        return [
            'category' => GoalCategory::class,
            'target' => 'decimal:2',
            'achieved' => 'decimal:2',
            'start_date' => 'date',
            'end_date' => 'date',
        ];
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function contributions(): HasMany
    {
        return $this->hasMany(GoalContribution::class);
    }

    public function achievementPercentage(): float
    {
        if ((float) $this->target <= 0) {
            return 0.0;
        }

        return round(min(100, ((float) $this->achieved / (float) $this->target) * 100), 1);
    }

    /**
     * Completed if the target's been met regardless of dates (a goal can be
     * hit early); otherwise derived purely from today vs. the date range —
     * computed, not stored, so it's never stale.
     */
    public function status(): GoalStatus
    {
        if ((float) $this->target > 0 && (float) $this->achieved >= (float) $this->target) {
            return GoalStatus::Completed;
        }

        $today = now()->startOfDay();

        if ($today->lt($this->start_date)) {
            return GoalStatus::Upcoming;
        }

        if ($today->gt($this->end_date)) {
            return GoalStatus::Expired;
        }

        return GoalStatus::Active;
    }
}
