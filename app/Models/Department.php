<?php

namespace App\Models;

use App\Models\Concerns\BelongsToCompany;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Department extends Model
{
    use BelongsToCompany, HasFactory;

    protected $fillable = ['company_id', 'name', 'description'];

    public function users(): HasMany
    {
        return $this->hasMany(User::class);
    }

    public function policyDocuments(): HasMany
    {
        return $this->hasMany(PolicyDocument::class);
    }
}
