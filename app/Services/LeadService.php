<?php

namespace App\Services;

use App\Enums\ActivityModule;
use App\Models\ActivityLogEntry;
use App\Models\Lead;
use App\Models\LeadStatus;
use App\Models\User;
use App\Repositories\Contracts\LeadRepositoryInterface;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

class LeadService
{
    public function __construct(
        protected LeadRepositoryInterface $leads,
        protected LeadAchievementService $leadAchievements,
        protected GoalContributionService $goalContributions,
    ) {}

    public function list(array $filters, int $perPage = 15, ?int $currentUserId = null): LengthAwarePaginator
    {
        return $this->leads->filter($filters, $perPage, $currentUserId);
    }

    public function create(array $attributes, User $creator): Lead
    {
        return DB::transaction(function () use ($attributes, $creator) {
            $attributes['created_by'] = $creator->id;
            $attributes['lead_status_id'] ??= LeadStatus::where('is_default', true)->value('id');

            /** @var Lead $lead */
            $lead = $this->leads->create($attributes);

            if ($lead->lead_status_id) {
                $lead->statusHistories()->create([
                    'from_status_id' => null,
                    'to_status_id' => $lead->lead_status_id,
                    'changed_by' => $creator->id,
                    'changed_at' => now(),
                ]);

                $this->leadAchievements->applyStatusToLead($lead, LeadStatus::find($lead->lead_status_id));
            }

            return $lead;
        });
    }

    /**
     * Now open to every user (see LeadPolicy::update()), so every field
     * change is diff-logged with who/when — same getRawOriginal()-before
     * getDirty()-after pattern as TaskService/RequirementService::update(),
     * so the log stays correct regardless of anything downstream touching
     * the model afterward.
     */
    public function update(Lead $lead, array $attributes, User $actor, ?string $ip, ?string $userAgent): Lead
    {
        $originalRaw = collect(array_keys($attributes))
            ->mapWithKeys(fn ($key) => [$key => $lead->getRawOriginal($key)])
            ->all();

        $lead->fill($attributes);
        $changed = $lead->getDirty();
        unset($changed['updated_at']);

        $lead->save();

        if (! empty($changed)) {
            ActivityLogEntry::create([
                'company_id' => $lead->company_id ?? $actor->company_id,
                'user_id' => $actor->id,
                'module' => ActivityModule::Lead,
                'description' => "updated lead \"{$lead->company_name}\"",
                'subject_type' => $lead->getMorphClass(),
                'subject_id' => $lead->getKey(),
                'old_values' => array_intersect_key($originalRaw, $changed),
                'new_values' => $changed,
                'ip_address' => $ip,
                'user_agent' => $userAgent,
            ]);
        }

        return $lead;
    }

    public function changeStatus(Lead $lead, int $newStatusId, User $changedBy): Lead
    {
        return DB::transaction(function () use ($lead, $newStatusId, $changedBy) {
            $previousStatusId = $lead->lead_status_id;

            if ($previousStatusId === $newStatusId) {
                return $lead;
            }

            $lastChange = $lead->statusHistories()->latest('changed_at')->first();
            $secondsInPrevious = $lastChange ? now()->diffInSeconds($lastChange->changed_at) : null;

            $lead->statusHistories()->create([
                'from_status_id' => $previousStatusId,
                'to_status_id' => $newStatusId,
                'changed_by' => $changedBy->id,
                'changed_at' => now(),
                'seconds_in_previous_status' => $secondsInPrevious,
            ]);

            $lead->update(['lead_status_id' => $newStatusId]);

            $this->leadAchievements->applyStatusToLead($lead, LeadStatus::find($newStatusId));

            return $lead->refresh();
        });
    }

    public function archive(Lead $lead): Lead
    {
        return $this->leads->update($lead, ['archived_at' => now()]);
    }

    public function restore(Lead $lead): Lead
    {
        return $this->leads->update($lead, ['archived_at' => null]);
    }

    public function close(Lead $lead, array $attributes, User $closedBy): Lead
    {
        return DB::transaction(function () use ($lead, $attributes, $closedBy) {
            $dealClosure = $lead->dealClosure()->updateOrCreate([], [
                'closed_by' => $closedBy->id,
                'closed_date' => $attributes['closed_date'],
                'deal_value' => $attributes['deal_value'],
                'closing_comment' => $attributes['closing_comment'] ?? null,
            ]);

            $this->goalContributions->recordForDealClosure($dealClosure);

            $convertedStatusId = LeadStatus::where('slug', 'converted-to-customer')->value('id');

            if ($convertedStatusId) {
                $this->changeStatus($lead, $convertedStatusId, $closedBy);
            }

            return $lead->refresh();
        });
    }

    /**
     * Issues (or rotates) the Support ID + PIN a client contact uses on the
     * public self-service portal (ClientSupportController) to raise support
     * tickets for this lead without a staff login. Support ID stays stable
     * once assigned — only the PIN rotates on subsequent calls. Returns the
     * plaintext PIN so the caller can flash it exactly once; only its hash
     * is persisted.
     *
     * Deliberately sets attributes directly and save()s rather than going
     * through update()/the repository's update() — both would either sweep
     * the hash into the everyone-can-view change log (see update()'s
     * docblock) or silently drop it, since these columns aren't in
     * Lead::$fillable on purpose.
     */
    public function generateSupportAccess(Lead $lead, User $actor): string
    {
        $pin = str_pad((string) random_int(0, 9999), 4, '0', STR_PAD_LEFT);

        if (! $lead->support_id) {
            $lead->support_id = $this->generateUniqueSupportId();
        }

        $lead->support_pin_hash = Hash::make($pin);
        $lead->support_pin_generated_at = now();
        $lead->support_pin_generated_by = $actor->id;
        $lead->save();

        return $pin;
    }

    /**
     * Revokes a lead's client-portal access entirely (e.g. the PIN was
     * shared with the wrong person) — clears the Support ID too, so
     * generateSupportAccess() issues a brand new one next time rather than
     * reusing a potentially-compromised one.
     */
    public function revokeSupportAccess(Lead $lead): void
    {
        $lead->support_id = null;
        $lead->support_pin_hash = null;
        $lead->support_pin_generated_at = null;
        $lead->support_pin_generated_by = null;
        $lead->save();
    }

    private function generateUniqueSupportId(): string
    {
        // Unambiguous charset (no 0/O/1/I) — this code gets read aloud/typed
        // by a client, not just copy-pasted.
        $alphabet = str_split('ABCDEFGHJKLMNPQRSTUVWXYZ23456789');

        do {
            $candidate = 'SPT-'.implode('', Arr::random($alphabet, 6));
        } while (Lead::where('support_id', $candidate)->exists());

        return $candidate;
    }
}
