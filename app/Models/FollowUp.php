<?php

namespace App\Models;

use App\Enums\FollowUpStatus;
use App\Enums\ReminderType;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class FollowUp extends Model
{
    use HasFactory;

    protected $fillable = [
        'lead_id', 'follow_up_date', 'follow_up_time', 'reminder_minutes_before',
        'reminder_type', 'status', 'notified_at', 'created_by',
    ];

    protected function casts(): array
    {
        return [
            'follow_up_date' => 'date',
            'reminder_type' => ReminderType::class,
            'status' => FollowUpStatus::class,
            'notified_at' => 'datetime',
        ];
    }

    public function lead(): BelongsTo
    {
        return $this->belongsTo(Lead::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function scopeDue($query)
    {
        return $query->where('status', FollowUpStatus::Pending)
            ->where(function ($q) {
                $q->where('follow_up_date', '<', now()->toDateString())
                    ->orWhere(function ($q2) {
                        $q2->where('follow_up_date', now()->toDateString())
                            ->where('follow_up_time', '<=', now()->toTimeString());
                    });
            });
    }
}
