<?php

namespace App\Models;

use App\Enums\GoalType;
use App\Models\Concerns\BelongsToCompany;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Goal extends Model
{
    use BelongsToCompany, HasFactory;

    protected $fillable = [
        'company_id', 'title', 'target', 'achieved', 'goal_type', 'user_id',
        'start_date', 'end_date', 'bs_year', 'bs_month', 'created_by',
    ];

    protected function casts(): array
    {
        return [
            'goal_type' => GoalType::class,
            'target' => 'decimal:2',
            'achieved' => 'decimal:2',
            'start_date' => 'date',
            'end_date' => 'date',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function achievementPercentage(): float
    {
        if ((float) $this->target <= 0) {
            return 0.0;
        }

        return round(min(100, ((float) $this->achieved / (float) $this->target) * 100), 1);
    }
}
