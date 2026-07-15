<?php

namespace App\Models;

use App\Models\Concerns\BelongsToCompany;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DailySummary extends Model
{
    use BelongsToCompany, HasFactory;

    protected $fillable = ['company_id', 'user_id', 'summary_date', 'achieved_today', 'planned_tomorrow', 'blockers'];

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
