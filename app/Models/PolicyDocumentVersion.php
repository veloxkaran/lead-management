<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PolicyDocumentVersion extends Model
{
    use HasFactory;

    protected $fillable = [
        'policy_document_id', 'version', 'content', 'effective_date', 'published_at', 'created_by',
    ];

    /**
     * A newly published version changes who's "pending" — invalidate the
     * cached pending set (see PolicyDocument::booted()) for everyone
     * assigned to the parent document so this shows up immediately instead
     * of waiting out the 5-minute cache.
     */
    protected static function booted(): void
    {
        static::created(function (self $version) {
            $document = $version->policyDocument;

            if ($document) {
                PolicyDocument::forgetPendingCacheForUserIds($document->assignedUserIds());
            }
        });
    }

    protected function casts(): array
    {
        return [
            'effective_date' => 'date',
            'published_at' => 'datetime',
        ];
    }

    public function policyDocument(): BelongsTo
    {
        return $this->belongsTo(PolicyDocument::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function acknowledgments(): HasMany
    {
        return $this->hasMany(PolicyDocumentAcknowledgment::class);
    }

    public function acknowledgmentFor(User $user): ?PolicyDocumentAcknowledgment
    {
        return $this->relationLoaded('acknowledgments')
            ? $this->acknowledgments->firstWhere('user_id', $user->id)
            : $this->acknowledgments()->where('user_id', $user->id)->first();
    }

    /**
     * ~200 words per minute, rounded up to the nearest whole minute so a
     * one-paragraph document still reads as "1 min" rather than "0 min".
     */
    public function estimatedReadingMinutes(): int
    {
        $wordCount = str_word_count(strip_tags($this->content));

        return max(1, (int) ceil($wordCount / 200));
    }
}
