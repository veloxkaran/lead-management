<?php

namespace App\Models;

use App\Models\Concerns\BelongsToCompany;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Admin-managed list of industries a lead can be filed under. Leads store
 * the chosen name as text in leads.industry (the column predates this
 * list), so the relation joins on name rather than an id, and renaming an
 * industry is propagated to its leads by IndustryService::update().
 */
class Industry extends Model
{
    use BelongsToCompany, HasFactory;

    protected $fillable = ['company_id', 'name', 'slug'];

    public function leads(): HasMany
    {
        return $this->hasMany(Lead::class, 'industry', 'name');
    }

    public function scopeOrdered($query)
    {
        return $query->orderBy('name');
    }
}
