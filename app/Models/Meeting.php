<?php

namespace App\Models;

use App\Models\Concerns\BelongsToCompany;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Meeting extends Model
{
    use BelongsToCompany, HasFactory;

    protected $fillable = [
        'company_id', 'title', 'meeting_date', 'meeting_time', 'meeting_link', 'participants', 'team_id', 'created_by',
    ];

    protected function casts(): array
    {
        return [
            'meeting_date' => 'date',
            'participants' => 'array',
        ];
    }

    public function team(): BelongsTo
    {
        return $this->belongsTo(Team::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
