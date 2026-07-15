<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PolicyDocumentAcknowledgment extends Model
{
    protected $fillable = [
        'policy_document_version_id', 'user_id', 'viewed_at', 'acknowledged_at',
        'ip_address', 'user_agent', 'device', 'browser',
    ];

    protected function casts(): array
    {
        return [
            'viewed_at' => 'datetime',
            'acknowledged_at' => 'datetime',
        ];
    }

    public function version(): BelongsTo
    {
        return $this->belongsTo(PolicyDocumentVersion::class, 'policy_document_version_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
