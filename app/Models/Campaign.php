<?php

namespace App\Models;

use App\Enums\CampaignAudience;
use App\Enums\CampaignChannel;
use App\Enums\CampaignRecipientStatus;
use App\Enums\CampaignStatus;
use App\Models\Concerns\BelongsToCompany;
use App\Support\CampaignSettings;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

class Campaign extends Model
{
    use BelongsToCompany, HasFactory;

    protected $fillable = [
        'company_id', 'name', 'channel', 'subject', 'message', 'audience', 'audience_filter', 'lead_ids', 'all_contacts', 'contact_ids',
        'status', 'scheduled_at', 'next_batch_at', 'started_at', 'paused_at', 'completed_at',
        'recipient_count', 'batch_count', 'current_batch', 'skipped', 'send_options', 'created_by',
        'reviewed_by', 'reviewed_at', 'review_note',
    ];

    protected function casts(): array
    {
        return [
            'channel' => CampaignChannel::class,
            'audience' => CampaignAudience::class,
            'status' => CampaignStatus::class,
            'audience_filter' => 'array',
            'lead_ids' => 'array',
            'all_contacts' => 'boolean',
            'contact_ids' => 'array',
            'skipped' => 'array',
            'send_options' => 'array',
            'scheduled_at' => 'datetime',
            'next_batch_at' => 'datetime',
            'started_at' => 'datetime',
            'paused_at' => 'datetime',
            'completed_at' => 'datetime',
            'reviewed_at' => 'datetime',
        ];
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }

    /**
     * Only Super Admins send straight away; everyone else's campaigns wait
     * for a Super Admin to approve them.
     */
    public static function requiresApproval(User $creator): bool
    {
        return ! $creator->isSuperAdmin();
    }

    public function isAwaitingApproval(): bool
    {
        return $this->status === CampaignStatus::AwaitingApproval;
    }

    public function recipients(): HasMany
    {
        return $this->hasMany(CampaignRecipient::class);
    }

    public function isEmail(): bool
    {
        return $this->channel === CampaignChannel::Email;
    }

    /**
     * Awaiting approval, pending (not started yet), mid-send or paused —
     * either way there are messages left that cancelling would stop.
     */
    public function isCancellable(): bool
    {
        return in_array($this->status, [CampaignStatus::AwaitingApproval, CampaignStatus::Pending, CampaignStatus::Sending, CampaignStatus::Paused], true);
    }

    /**
     * Minutes the whole send takes from start to finish at its batch
     * settings — shown to the Super Admin reviewing it.
     */
    public function estimatedDurationMinutes(): int
    {
        $size = max(1, (int) $this->sendOption('batch_size'));
        $perMinute = max(1, (int) $this->sendOption('per_minute'));
        $batches = max(1, (int) ceil($this->recipient_count / $size));
        $last = $this->recipient_count - ($batches - 1) * $size;

        return ($batches - 1) * $this->batchIntervalMinutes() + (int) ceil(max(1, $last) / $perMinute);
    }

    /**
     * The sending options snapshotted at creation, falling back to today's
     * Campaign Setup values for campaigns created before they existed.
     */
    public function sendOption(string $key): mixed
    {
        return $this->send_options[$key] ?? app(CampaignSettings::class)->sendOptions($this->channel)[$key] ?? null;
    }

    /**
     * Minutes from one batch being queued to the next: the time the batch
     * takes at its per-minute rate, plus the pause after it.
     */
    public function batchIntervalMinutes(): int
    {
        $size = max(1, (int) $this->sendOption('batch_size'));
        $perMinute = max(1, (int) $this->sendOption('per_minute'));

        return (int) ceil($size / $perMinute) + max(0, (int) $this->sendOption('pause_minutes'));
    }

    /**
     * Rough time the last batch finishes, for a campaign still going.
     */
    public function estimatedFinishAt(): ?Carbon
    {
        if (! in_array($this->status, [CampaignStatus::Pending, CampaignStatus::Sending], true)) {
            return null;
        }

        $spread = (int) ceil(max(1, (int) $this->sendOption('batch_size')) / max(1, (int) $this->sendOption('per_minute')));
        $waiting = $this->recipients()->where('status', CampaignRecipientStatus::Pending)->whereNull('queued_at')->distinct()->count('batch');

        if ($waiting === 0) {
            return now()->addMinutes($spread);
        }

        $start = $this->status === CampaignStatus::Pending ? ($this->scheduled_at ?? now()) : ($this->next_batch_at ?? now());

        return $start->copy()->max(now())->addMinutes(($waiting - 1) * $this->batchIntervalMinutes() + $spread);
    }

    public function isPausable(): bool
    {
        return $this->status === CampaignStatus::Sending;
    }

    public function isResumable(): bool
    {
        return $this->status === CampaignStatus::Paused;
    }

    /**
     * Failed messages can be put back in line unless the campaign was
     * cancelled or hasn't started.
     */
    public function canRetryFailed(): bool
    {
        return in_array($this->status, [CampaignStatus::Sending, CampaignStatus::Paused, CampaignStatus::Completed], true);
    }

    /**
     * Called after every send attempt. Marks the campaign completed once no
     * recipient is still waiting; when the last message of a batch is done
     * (it may have run long), makes sure the full pause still follows it.
     */
    public function refreshProgress(): void
    {
        $this->refresh();

        if ($this->status !== CampaignStatus::Sending) {
            return;
        }

        $pending = $this->recipients()->where('status', CampaignRecipientStatus::Pending);

        if (! (clone $pending)->exists()) {
            $this->update(['status' => CampaignStatus::Completed, 'completed_at' => now(), 'next_batch_at' => null]);

            return;
        }

        if ($this->next_batch_at && ! (clone $pending)->whereNotNull('queued_at')->exists()) {
            $earliest = now()->addMinutes(max(0, (int) $this->sendOption('pause_minutes')));

            if ($this->next_batch_at->lt($earliest)) {
                $this->update(['next_batch_at' => $earliest]);
            }
        }
    }

    /**
     * @return array<string, \Closure> withCount() definitions, one "{status}_count" per recipient status
     */
    public static function statusCounts(): array
    {
        return collect(CampaignRecipientStatus::cases())->mapWithKeys(fn (CampaignRecipientStatus $status) => [
            "recipients as {$status->value}_count" => fn ($q) => $q->where('status', $status),
        ])->all();
    }
}
