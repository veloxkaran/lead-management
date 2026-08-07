<?php

namespace App\Models;

use App\Enums\SupportTicketAssignmentAction;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SupportTicketAssignmentLog extends Model
{
    use HasFactory;

    protected $fillable = ['support_ticket_id', 'action', 'user_id', 'performed_by'];

    protected function casts(): array
    {
        return [
            'action' => SupportTicketAssignmentAction::class,
        ];
    }

    public function supportTicket(): BelongsTo
    {
        return $this->belongsTo(SupportTicket::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function performedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'performed_by');
    }
}
