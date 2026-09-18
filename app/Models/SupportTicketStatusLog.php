<?php

namespace App\Models;

use App\Enums\RequirementStatus;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SupportTicketStatusLog extends Model
{
    use HasFactory;

    protected $fillable = ['support_ticket_id', 'from_status', 'to_status', 'changed_by'];

    protected function casts(): array
    {
        return [
            'from_status' => RequirementStatus::class,
            'to_status' => RequirementStatus::class,
        ];
    }

    public function supportTicket(): BelongsTo
    {
        return $this->belongsTo(SupportTicket::class);
    }

    public function changedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'changed_by');
    }
}
