<?php

namespace App\Services;

use App\Enums\ActivityModule;
use App\Models\ActivityLogEntry;
use App\Models\Lead;
use App\Models\LeadStatus;
use App\Models\User;
use App\Repositories\Contracts\LeadRepositoryInterface;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;

class LeadService
{
    public function __construct(
        protected LeadRepositoryInterface $leads,
        protected LeadAchievementService $leadAchievements,
        protected GoalContributionService $goalContributions,
    ) {}

    public function list(array $filters, int $perPage = 15): LengthAwarePaginator
    {
        return $this->leads->filter($filters, $perPage);
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
}
