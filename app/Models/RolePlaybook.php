<?php

namespace App\Models;

use App\Enums\UserRole;
use Illuminate\Database\Eloquent\Model;

class RolePlaybook extends Model
{
    protected $fillable = ['role', 'motivation'];

    public static function forRole(UserRole $role): ?self
    {
        return static::where('role', $role->value)->first();
    }
}
