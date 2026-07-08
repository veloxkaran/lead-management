<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DailySummary extends Model
{
    use HasFactory;

    protected $fillable = ['user_id', 'summary_date', 'achieved_today', 'planned_tomorrow', 'blockers'];

    protected function casts(): array
    {
        return [
            'summary_date' => 'date',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
