<?php

namespace App\Models;

use App\Enums\AnnouncementAudience;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;

class Announcement extends Model
{
    protected $fillable = [
        'title', 'content', 'audience', 'lead_status_ids', 'recipient_count', 'created_by',
    ];

    protected function casts(): array
    {
        return [
            'audience' => AnnouncementAudience::class,
            'lead_status_ids' => 'array',
        ];
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function images(): HasMany
    {
        return $this->hasMany(AnnouncementImage::class);
    }

    public function documents(): HasMany
    {
        return $this->hasMany(AnnouncementDocument::class);
    }

    public function emailLogs(): MorphMany
    {
        return $this->morphMany(EmailLog::class, 'related');
    }
}
