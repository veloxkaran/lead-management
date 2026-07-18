<?php

namespace App\Models;

use App\Enums\ContributionType;
use App\Models\Concerns\BelongsToCompany;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class GoalContribution extends Model
{
    use BelongsToCompany, HasFactory;

    protected $fillable = [
        'goal_id', 'user_id', 'company_id', 'source_type', 'source_id',
        'contribution_type', 'amount', 'contributed_at',
    ];

    protected function casts(): array
    {
        return [
            'contribution_type' => ContributionType::class,
            'amount' => 'decimal:2',
            'contributed_at' => 'date',
        ];
    }

    public function goal(): BelongsTo
    {
        return $this->belongsTo(Goal::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function source(): MorphTo
    {
        return $this->morphTo();
    }
}
