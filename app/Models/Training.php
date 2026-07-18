<?php

namespace App\Models;

use App\Enums\TrainingStatus;
use App\Models\Concerns\BelongsToCompany;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Training extends Model
{
    use BelongsToCompany, HasFactory;

    protected $fillable = [
        'company_id', 'lead_id', 'status', 'training_date', 'trainer_name', 'attendees_count',
        'department_id', 'completion_percentage', 'feedback', 'conducted_by',
    ];

    protected function casts(): array
    {
        return [
            'status' => TrainingStatus::class,
            'training_date' => 'date',
            'attendees_count' => 'integer',
            'completion_percentage' => 'integer',
        ];
    }

    public function lead(): BelongsTo
    {
        return $this->belongsTo(Lead::class);
    }

    public function department(): BelongsTo
    {
        return $this->belongsTo(Department::class);
    }

    public function conductor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'conducted_by');
    }
}
