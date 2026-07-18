<?php

namespace App\Services;

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

    public function update(Lead $lead, array $attributes): Lead
    {
        return $this->leads->update($lead, $attributes);
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
