<?php

namespace App\Services;

use App\Enums\CampaignAudience;
use App\Enums\CampaignChannel;
use App\Enums\CampaignRecipientStatus;
use App\Enums\CampaignStatus;
use App\Jobs\SendCampaignMessage;
use App\Models\Campaign;
use App\Models\CampaignRecipient;
use App\Models\User;
use App\Support\CampaignRecipientBuilder;
use App\Support\CampaignRecipientList;
use App\Support\CampaignSettings;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class CampaignService
{
    public function __construct(protected CampaignRecipientBuilder $builder, protected CampaignSettings $settings)
    {
    }

    /**
     * @param  array<string, mixed>  $input  validated StoreCampaignRequest data
     */
    public function recipientList(array $input): CampaignRecipientList
    {
        $audience = CampaignAudience::from($input['audience'] ?? CampaignAudience::None->value);

        return $this->builder->build(
            CampaignChannel::from($input['channel']),
            $audience,
            $this->audienceFilter($audience, $input) ?? [],
            $input['lead_ids'] ?? [],
            $input['extra_contacts'] ?? null,
            (bool) ($input['all_contacts'] ?? false),
            $input['contact_ids'] ?? [],
        );
    }

    /**
     * Saves the campaign with one pending row per recipient, each assigned
     * to a batch, then either starts sending or (scheduled for later)
     * leaves it Pending for `campaigns:dispatch-due` to pick up.
     *
     * @param  array<string, mixed>  $input
     *
     * @throws ValidationException when nobody is left to send to
     */
    public function create(array $input, User $actor): Campaign
    {
        $list = $this->recipientList($input);
        $channel = CampaignChannel::from($input['channel']);

        if ($list->count() === 0) {
            throw ValidationException::withMessages([
                'recipients' => 'No one to send to — choose leads or add contacts with a valid '.($channel === CampaignChannel::Email ? 'email address.' : 'phone number.'),
            ]);
        }

        $audience = CampaignAudience::from($input['audience'] ?? CampaignAudience::None->value);
        $scheduledAt = ! empty($input['scheduled_at']) ? Carbon::parse($input['scheduled_at']) : null;

        $options = $this->settings->sendOptions($channel);
        // The signature is on by default whenever one is set up; the composer can leave it off.
        $options['include_signature'] = $options['include_signature'] && (bool) ($input['include_signature'] ?? true);

        $campaign = DB::transaction(function () use ($input, $actor, $list, $audience, $scheduledAt, $options) {
            $campaign = Campaign::create([
                'name' => $input['name'],
                'channel' => $input['channel'],
                'subject' => $input['channel'] === CampaignChannel::Email->value ? $input['subject'] : null,
                'message' => $input['message'],
                'audience' => $audience,
                'audience_filter' => $this->audienceFilter($audience, $input),
                'lead_ids' => array_values(array_map('intval', $input['lead_ids'] ?? [])) ?: null,
                'all_contacts' => (bool) ($input['all_contacts'] ?? false),
                'contact_ids' => array_values(array_map('intval', $input['contact_ids'] ?? [])) ?: null,
                'status' => CampaignStatus::Pending,
                'scheduled_at' => $scheduledAt?->isFuture() ? $scheduledAt : null,
                'recipient_count' => $list->count(),
                'batch_count' => (int) ceil($list->count() / $options['batch_size']),
                'skipped' => $list->skipped ?: null,
                'send_options' => $options,
                'created_by' => $actor->id,
            ]);

            $now = now();
            foreach (array_chunk($list->recipients, 500, true) as $chunk) {
                CampaignRecipient::insert(array_map(fn (array $recipient, int $index) => [
                    ...$recipient,
                    'campaign_id' => $campaign->id,
                    'batch' => intdiv($index, $options['batch_size']) + 1,
                    'status' => CampaignRecipientStatus::Pending->value,
                    'tracking_token' => $campaign->isEmail() ? Str::random(40) : null,
                    'created_at' => $now,
                    'updated_at' => $now,
                ], $chunk, array_keys($chunk)));
            }

            return $campaign;
        });

        if ($campaign->scheduled_at === null) {
            $this->dispatch($campaign);
        }

        return $campaign;
    }

    /**
     * Starts a Pending campaign and queues its first batch. Only moves a
     * Pending campaign to Sending once, so a double dispatch (scheduler
     * overlap) can't queue anyone twice.
     */
    public function dispatch(Campaign $campaign): void
    {
        $claimed = Campaign::whereKey($campaign->id)
            ->where('status', CampaignStatus::Pending)
            ->update(['status' => CampaignStatus::Sending, 'started_at' => now(), 'next_batch_at' => now()]);

        if (! $claimed) {
            return;
        }

        $this->assignMissingBatches($campaign->refresh());
        $this->queueNextBatch($campaign);
    }

    /**
     * Queues the next batch once it's due: its time has come (the previous
     * batch's sending time plus the pause) and nothing from the previous
     * batch is still waiting in the queue — so a slow mail server can never
     * make batches pile up on each other. Messages inside the batch are
     * spread out at the campaign's per-minute rate.
     *
     * @return bool whether a batch was queued
     */
    public function queueNextBatch(Campaign $campaign): bool
    {
        return DB::transaction(function () use ($campaign) {
            $stillQueued = $campaign->recipients()->where('status', CampaignRecipientStatus::Pending)->whereNotNull('queued_at')->exists();

            if ($stillQueued) {
                return false;
            }

            // Claiming next_batch_at means an overlapping scheduler run can't queue the same batch.
            $claimed = Campaign::whereKey($campaign->id)
                ->where('status', CampaignStatus::Sending)
                ->whereNotNull('next_batch_at')
                ->where('next_batch_at', '<=', now())
                ->update(['next_batch_at' => null]);

            if (! $claimed) {
                return false;
            }

            $campaign->refresh();
            $waiting = fn () => $campaign->recipients()->where('status', CampaignRecipientStatus::Pending)->whereNull('queued_at');
            $batch = $waiting()->min('batch');

            if ($batch === null) {
                $campaign->refreshProgress();

                return false;
            }

            $ids = $waiting()->where('batch', $batch)->orderBy('id')->pluck('id');
            $perMinute = max(1, (int) $campaign->sendOption('per_minute'));
            $now = now();

            foreach ($ids->chunk(500) as $chunk) {
                CampaignRecipient::whereIn('id', $chunk->all())->update(['queued_at' => $now]);
            }

            foreach ($ids->values() as $i => $id) {
                SendCampaignMessage::dispatch($id)->delay($now->copy()->addSeconds(intdiv($i * 60, $perMinute)));
            }

            // A sync queue (tests) has already sent the batch, and maybe finished the campaign.
            if ($campaign->fresh()->status === CampaignStatus::Sending) {
                $campaign->update([
                    'current_batch' => $batch,
                    'next_batch_at' => $waiting()->exists() ? $now->copy()->addMinutes($campaign->batchIntervalMinutes()) : null,
                ]);
            } else {
                $campaign->update(['current_batch' => $batch]);
            }

            return true;
        });
    }

    /**
     * Run every minute by the scheduler: starts scheduled campaigns whose
     * time has come, and queues the next batch of any campaign mid-send.
     *
     * @return int campaigns started
     */
    public function dispatchDue(): int
    {
        $due = Campaign::withoutGlobalScopes()
            ->where('status', CampaignStatus::Pending)
            ->where(fn ($q) => $q->whereNull('scheduled_at')->orWhere('scheduled_at', '<=', now()))
            ->get();

        $due->each(fn (Campaign $campaign) => $this->dispatch($campaign));

        Campaign::withoutGlobalScopes()
            ->where('status', CampaignStatus::Sending)
            ->whereNotNull('next_batch_at')
            ->where('next_batch_at', '<=', now())
            ->get()
            ->each(fn (Campaign $campaign) => $this->queueNextBatch($campaign));

        return $due->count();
    }

    /**
     * Stops after whatever is already on its way. Messages queued for the
     * current batch are put back (see SendCampaignMessage) and go out again
     * on resume.
     */
    public function pause(Campaign $campaign): void
    {
        Campaign::whereKey($campaign->id)
            ->where('status', CampaignStatus::Sending)
            ->update(['status' => CampaignStatus::Paused, 'paused_at' => now(), 'next_batch_at' => null]);
    }

    public function resume(Campaign $campaign): void
    {
        $resumed = Campaign::whereKey($campaign->id)
            ->where('status', CampaignStatus::Paused)
            ->update(['status' => CampaignStatus::Sending, 'paused_at' => null, 'next_batch_at' => now()]);

        if ($resumed) {
            $this->queueNextBatch($campaign->refresh());
            $campaign->refresh()->refreshProgress();
        }
    }

    /**
     * Puts failed messages that never left (the mail server or gateway
     * refused them) back in line. Ones that were sent and then reported
     * undelivered aren't retried — that would be a second copy.
     *
     * @return int messages put back
     */
    public function retryFailed(Campaign $campaign): int
    {
        if (! $campaign->canRetryFailed()) {
            return 0;
        }

        $count = DB::transaction(function () use ($campaign) {
            $count = $campaign->recipients()
                ->where('status', CampaignRecipientStatus::Failed)
                ->whereNull('sent_at')
                ->update([
                    'status' => CampaignRecipientStatus::Pending->value,
                    'error' => null,
                    'queued_at' => null,
                    'updated_at' => now(),
                ]);

            if ($count && in_array($campaign->status, [CampaignStatus::Completed, CampaignStatus::Sending], true)) {
                $campaign->update([
                    'status' => CampaignStatus::Sending,
                    'completed_at' => null,
                    'next_batch_at' => $campaign->next_batch_at ?? now(),
                ]);
            }

            return $count;
        });

        if ($count && $campaign->refresh()->status === CampaignStatus::Sending) {
            $this->queueNextBatch($campaign);
        }

        return $count;
    }

    /**
     * Stops whatever hasn't gone out yet; already-sent messages can't be recalled.
     */
    public function cancel(Campaign $campaign): void
    {
        DB::transaction(function () use ($campaign) {
            $campaign->recipients()->where('status', CampaignRecipientStatus::Pending)->update([
                'status' => CampaignRecipientStatus::Cancelled->value,
                'updated_at' => now(),
            ]);

            $campaign->update(['status' => CampaignStatus::Cancelled, 'completed_at' => now(), 'next_batch_at' => null]);
        });
    }

    /**
     * Recipients saved before batches existed get numbered in id order.
     */
    private function assignMissingBatches(Campaign $campaign): void
    {
        if (! $campaign->recipients()->whereNull('batch')->exists()) {
            return;
        }

        $size = max(1, (int) $campaign->sendOption('batch_size'));

        $campaign->recipients()->whereNull('batch')->orderBy('id')->pluck('id')->chunk($size)
            ->each(function ($ids, int $i) use ($campaign) {
                CampaignRecipient::whereIn('id', $ids->all())->update(['batch' => $i + 1]);
            });

        $campaign->update(['batch_count' => (int) $campaign->recipients()->max('batch')]);
    }

    /**
     * {{name}} and {{company_name}} merged per recipient; unknown tags are dropped.
     */
    public static function personalize(string $text, CampaignRecipient|array|null $recipient): string
    {
        return EmailTemplateService::merge($text, [
            'name' => data_get($recipient, 'name'),
            'company_name' => data_get($recipient, 'company_name'),
        ]);
    }

    /**
     * @param  array<string, mixed>  $input
     * @return array<int, int|string>|null
     */
    private function audienceFilter(CampaignAudience $audience, array $input): ?array
    {
        return match ($audience) {
            CampaignAudience::LeadStatuses => array_values(array_map('intval', $input['lead_status_ids'] ?? [])),
            CampaignAudience::Industries => array_values(array_map('strval', $input['industries'] ?? [])),
            default => null,
        };
    }
}
