<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Storage;

class SupportTicketAttachment extends Model
{
    protected $fillable = ['support_ticket_id', 'disk_path', 'original_name', 'mime_type', 'size'];

    public function supportTicket(): BelongsTo
    {
        return $this->belongsTo(SupportTicket::class);
    }

    public function url(): string
    {
        return Storage::disk('public')->url($this->disk_path);
    }
}
