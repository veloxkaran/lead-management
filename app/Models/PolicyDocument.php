<?php

namespace App\Models;

use App\Enums\PolicyDocumentType;
use App\Enums\UserStatus;
use App\Models\Concerns\BelongsToCompany;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;

class PolicyDocument extends Model
{
    use BelongsToCompany, HasFactory, SoftDeletes;

    protected $fillable = [
        'company_id', 'type', 'title', 'user_id',
        'allow_skip', 'is_active', 'created_by',
    ];

    /**
     * ResolvePendingPolicyAcknowledgments caches each user's pending set for
     * 5 minutes — without invalidating on every change that could affect who
     * sees this document, a new/updated/reassigned document wouldn't be
     * reflected for up to 5 minutes, breaking the "shows immediately"
     * requirement. Covers reassignment (old AND new assignees) and
     * enable/disable; version publish is invalidated by
     * PolicyDocumentVersion's own boot hook below.
     */
    protected static function booted(): void
    {
        static::updated(function (self $document) {
            if (! $document->wasChanged(['user_id', 'is_active'])) {
                return;
            }

            self::forgetPendingCacheForUserIds($document->assignedUserIds());

            self::forgetPendingCacheForUserIds(self::assignedUserIdsFor(
                $document->type,
                $document->company_id,
                $document->getOriginal('user_id'),
            ));
        });

        static::deleted(fn (self $document) => self::forgetPendingCacheForUserIds($document->assignedUserIds()));
    }

    /**
     * @return Collection<int, int>
     */
    public function assignedUserIds(): Collection
    {
        return self::assignedUserIdsFor($this->type, $this->company_id, $this->user_id);
    }

    /**
     * @return Collection<int, int>
     */
    private static function assignedUserIdsFor(PolicyDocumentType $type, ?int $companyId, ?int $userId): Collection
    {
        if ($type->isCompanyWide()) {
            return User::where('company_id', $companyId)->where('status', UserStatus::Active)->pluck('id');
        }

        return $userId ? collect([$userId]) : collect();
    }

    /**
     * @param  \Illuminate\Support\Collection<int, int>  $userIds
     */
    public static function forgetPendingCacheForUserIds($userIds): void
    {
        foreach ($userIds as $userId) {
            Cache::forget("policy_ack_pending:{$userId}");
        }
    }

    /**
     * The users this document applies to: every active user in the company
     * (for Sop), or its single assignee (for IndividualJd).
     *
     * @return Collection<int, User>
     */
    public function assignedUsers(): Collection
    {
        if ($this->type->isCompanyWide()) {
            return User::where('company_id', $this->company_id)->where('status', UserStatus::Active)->get();
        }

        return collect([$this->user])->filter();
    }

    protected function casts(): array
    {
        return [
            'type' => PolicyDocumentType::class,
            'allow_skip' => 'boolean',
            'is_active' => 'boolean',
        ];
    }

    /**
     * The assignee for an Individual JD. Not applicable to Sop.
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function versions(): HasMany
    {
        return $this->hasMany(PolicyDocumentVersion::class)->latest('effective_date');
    }

    /**
     * The version currently in effect — excludes versions scheduled for a
     * future effective_date, which is what makes "schedule effective dates"
     * work without any extra state: a future version just isn't current yet.
     */
    public function currentVersion(): HasOne
    {
        // Two versions can share the same calendar-day effective_date (e.g. a
        // same-day correction) — tiebreak on id so the most recently
        // published one always wins, not whichever the join happens to
        // return first for the tie.
        //
        // Compared against a full datetime string, not toDateString(): the
        // `date` cast persists effective_date with a trailing "00:00:00",
        // so comparing it lexicographically against a bare "Y-m-d" string
        // would always evaluate false (the longer string sorts "greater").
        return $this->hasOne(PolicyDocumentVersion::class)
            ->where('effective_date', '<=', now()->toDateTimeString())
            ->latestOfMany(['effective_date', 'id']);
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function scopeOfType($query, PolicyDocumentType $type)
    {
        return $query->where('type', $type->value);
    }

    /**
     * Documents of $type that apply to $user: every company-wide one (Sop —
     * the model's own BelongsToCompany scope already restricts the query to
     * the viewer's company, so no extra where is needed here), or individual
     * ones assigned to $user directly. Matches $type->isCompanyWide() with
     * $type itself rather than each document's own `type` column, since
     * callers already filter to a single type via scopeOfType and pass it in.
     */
    public function scopeAssignedTo($query, User $user, PolicyDocumentType $type)
    {
        if ($type->isCompanyWide()) {
            return $query;
        }

        return $query->where('user_id', $user->id);
    }
}
