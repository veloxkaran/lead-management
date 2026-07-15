<?php

namespace App\Models;

use App\Enums\UserRole;
use Illuminate\Database\Eloquent\Model;

class RolePlaybook extends Model
{
    protected $fillable = ['role', 'responsibilities', 'sops', 'success_metrics', 'motivation'];

    protected function casts(): array
    {
        return [
            'responsibilities' => 'array',
            'sops' => 'array',
            'success_metrics' => 'array',
        ];
    }

    public static function forRole(UserRole $role): ?self
    {
        return static::where('role', $role->value)->first();
    }
}
