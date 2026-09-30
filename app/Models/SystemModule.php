<?php

namespace App\Models;

use App\Models\Concerns\BelongsToCompany;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Admin-managed list of product/system modules a requirement belongs to.
 * Named SystemModule to stay clear of the unrelated PermissionModule,
 * TaskModule and ActivityModule enums.
 */
class SystemModule extends Model
{
    use BelongsToCompany, HasFactory;

    protected $fillable = ['company_id', 'name', 'slug'];

    public function requirements(): HasMany
    {
        return $this->hasMany(Requirement::class);
    }

    public function scopeOrdered($query)
    {
        return $query->orderBy('name');
    }
}
