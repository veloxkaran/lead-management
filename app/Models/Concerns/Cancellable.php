<?php

namespace App\Models\Concerns;

use App\Enums\RequirementStatus;
use App\Models\User;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Requirements and support tickets can be Cancelled with a reason.
 * cancelled_at is stamped on the way in and the reason/who/when are all
 * cleared on the way out (reopening), so a record never shows a stale
 * cancellation — the services set cancellation_reason/cancelled_by, and
 * history survives in the status log / activity log / comments.
 */
trait Cancellable
{
    protected static function bootCancellable(): void
    {
        static::saving(function ($record) {
            if ($record->status === RequirementStatus::Cancelled) {
                $record->cancelled_at ??= now();

                return;
            }

            $record->cancelled_at = null;
            $record->cancelled_by = null;
            $record->cancellation_reason = null;
        });
    }

    public function initializeCancellable(): void
    {
        $this->mergeFillable(['cancellation_reason', 'cancelled_at', 'cancelled_by']);
        $this->mergeCasts(['cancelled_at' => 'datetime']);
    }

    public function isCancelled(): bool
    {
        return $this->status === RequirementStatus::Cancelled;
    }

    /**
     * Completed or Cancelled — no longer open work.
     */
    public function isClosed(): bool
    {
        return $this->status?->isClosed() ?? false;
    }

    public function canceller(): BelongsTo
    {
        return $this->belongsTo(User::class, 'cancelled_by');
    }
}
