<?php

namespace App\Models;

use App\Models\Concerns\BelongsToCompany;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class LeadStatus extends Model
{
    use BelongsToCompany, HasFactory;

    protected $fillable = [
        'company_id', 'name', 'slug', 'color', 'order', 'is_default', 'is_closed_won', 'is_closed_lost', 'is_achievement',
    ];

    protected function casts(): array
    {
        return [
            'is_default' => 'boolean',
            'is_closed_won' => 'boolean',
            'is_closed_lost' => 'boolean',
            'is_achievement' => 'boolean',
        ];
    }

    public function leads(): HasMany
    {
        return $this->hasMany(Lead::class);
    }

    public function scopeOrdered($query)
    {
        return $query->orderBy('order');
    }
}
