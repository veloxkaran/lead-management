<?php

namespace App\Models;

use App\Enums\PolicyDocumentType;
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
        'company_id', 'type', 'title', 'department_id', 'user_id',
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
            if (! $document->wasChanged(['department_id', 'user_id', 'is_active'])) {
                return;
            }

            self::forgetPendingCacheForUserIds($document->assignedUserIds());

            self::forgetPendingCacheForUserIds(self::assignedUserIdsFor(
                $document->type,
                $document->getOriginal('department_id'),
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
        return self::assignedUserIdsFor($this->type, $this->department_id, $this->user_id);
    }

    /**
     * @return Collection<int, int>
     */
    private static function assignedUserIdsFor(PolicyDocumentType $type, ?int $departmentId, ?int $userId): Collection
    {
        if ($type->isDepartmentAssigned()) {
            return $departmentId ? User::where('department_id', $departmentId)->pluck('id') : collect();
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
     * The users this document applies to: everyone in its department (for
     * Sop/DepartmentJd), or its single assignee (for IndividualJd). Uses the
     * `department`/`user` relations, so eager-loading `department.users`
     * before calling this avoids an extra query per document.
     *
     * @return Collection<int, User>
     */
    public function assignedUsers(): Collection
    {
        if ($this->type->isDepartmentAssigned()) {
            return $this->department?->users ?? collect();
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

    public function department(): BelongsTo
    {
        return $this->belongsTo(Department::class);
    }

    /**
     * The assignee for an Individual JD. Not applicable to Sop/DepartmentJd.
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
     * Documents of $type that apply to $user: department-scoped ones where
     * $user belongs to that department, or individual ones assigned to
     * $user directly. Matches $type->isDepartmentAssigned() with $type
     * itself rather than each document's own `type` column, since callers
     * already filter to a single type via scopeOfType and pass it in.
     */
    public function scopeAssignedTo($query, User $user, PolicyDocumentType $type)
    {
        if ($type->isDepartmentAssigned()) {
            return $user->department_id
                ? $query->where('department_id', $user->department_id)
                : $query->whereRaw('0 = 1');
        }

        return $query->where('user_id', $user->id);
    }
}
