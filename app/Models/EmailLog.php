<?php

namespace App\Models;

use App\Enums\EmailLogStatus;
use App\Support\EmailFailureReason;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Support\Str;

class EmailLog extends Model
{
    protected $fillable = [
        'to_email', 'subject', 'body', 'template_key', 'related_type', 'related_id',
        'status', 'error', 'sent_at', 'delivered_at', 'tracking_token',
    ];

    /** Minutes in the queue after which a still-pending email is flagged. */
    public const STUCK_AFTER_MINUTES = 15;

    protected static function booted(): void
    {
        // Every email gets its own open-tracking token (see SendClientNotificationEmail).
        static::creating(function (EmailLog $log) {
            $log->tracking_token ??= Str::random(40);
        });
    }

    protected function casts(): array
    {
        return [
            'status' => EmailLogStatus::class,
            'sent_at' => 'datetime',
            'delivered_at' => 'datetime',
        ];
    }

    /**
     * What the status means for this email, in plain words — the Remarks
     * column. Failures get a reason someone can act on when the error is
     * recognisable (EmailFailureReason); the raw error is shown alongside.
     */
    public function remarks(): string
    {
        return match ($this->status) {
            EmailLogStatus::Pending => $this->created_at?->lt(now()->subMinutes(self::STUCK_AFTER_MINUTES))
                ? 'Still waiting in the queue after '.$this->created_at->diffForHumans(null, true).' — the queue worker (cron) may not be running.'
                : 'Waiting in the queue to be sent.',
            EmailLogStatus::Sent => 'Accepted by the mail server. Not opened yet — or opened with images blocked, which can\'t be detected.',
            EmailLogStatus::Delivered => 'Opened by the recipient'.($this->delivered_at ? ' on '.$this->delivered_at->format('M d, Y g:i A') : '').'.',
            EmailLogStatus::Failed => EmailFailureReason::explain($this->error) ?? 'Couldn\'t be sent — see the error below.',
        };
    }

    public function related(): MorphTo
    {
        return $this->morphTo();
    }
}
