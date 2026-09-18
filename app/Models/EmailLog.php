<?php

namespace App\Models;

use App\Enums\EmailLogStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class EmailLog extends Model
{
    protected $fillable = [
        'to_email', 'subject', 'body', 'template_key', 'related_type', 'related_id',
        'status', 'error', 'sent_at',
    ];

    protected function casts(): array
    {
        return [
            'status' => EmailLogStatus::class,
            'sent_at' => 'datetime',
        ];
    }

    public function related(): MorphTo
    {
        return $this->morphTo();
    }
}
