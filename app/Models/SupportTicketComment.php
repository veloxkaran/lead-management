<?php

namespace App\Models;

use App\Models\Concerns\BelongsToCompany;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SupportTicketComment extends Model
{
    use BelongsToCompany, HasFactory;

    protected $fillable = ['company_id', 'support_ticket_id', 'author_id', 'comment'];

    public function supportTicket(): BelongsTo
    {
        return $this->belongsTo(SupportTicket::class);
    }

    public function author(): BelongsTo
    {
        return $this->belongsTo(User::class, 'author_id');
    }

    /**
     * The 4-hour edit window is measured from the original post time, not
     * from any prior edit — one edit doesn't reset the clock.
     */
    public function isEditable(): bool
    {
        return $this->created_at->addHours(4)->isFuture();
    }

    public function wasEdited(): bool
    {
        return $this->updated_at->gt($this->created_at->addSecond());
    }
}
