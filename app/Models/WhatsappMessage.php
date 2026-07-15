<?php

namespace App\Models;

use App\Enums\WhatsappMessageDirection;
use App\Enums\WhatsappMessageStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class WhatsappMessage extends Model
{
    protected $fillable = [
        'lead_id', 'direction', 'wa_message_id', 'from_number', 'to_number', 'type', 'body',
        'template_name', 'template_payload', 'media_id', 'media_url', 'status', 'status_error',
        'sent_by', 'wa_timestamp',
    ];

    protected function casts(): array
    {
        return [
            'direction' => WhatsappMessageDirection::class,
            'status' => WhatsappMessageStatus::class,
            'template_payload' => 'array',
            'wa_timestamp' => 'datetime',
        ];
    }

    public function lead(): BelongsTo
    {
        return $this->belongsTo(Lead::class);
    }

    public function sentBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'sent_by');
    }
}
