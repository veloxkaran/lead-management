<?php

namespace App\Services;

use App\Models\LeadStatus;
use App\Repositories\LeadStatusRepository;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class LeadStatusService
{
    public function __construct(
        protected LeadStatusRepository $leadStatuses,
        protected GoalAchievementService $goalAchievements,
    ) {
    }

    public function list(): \Illuminate\Support\Collection
    {
        return $this->leadStatuses->ordered();
    }

    public function create(array $attributes): LeadStatus
    {
        $attributes['slug'] = Str::slug($attributes['name']);
        $attributes['is_default'] = (bool) ($attributes['is_default'] ?? false);
        $attributes['is_closed_won'] = (bool) ($attributes['is_closed_won'] ?? false);
        $attributes['is_closed_lost'] = (bool) ($attributes['is_closed_lost'] ?? false);
        $attributes['is_achievement'] = (bool) ($attributes['is_achievement'] ?? false);
        $attributes['order'] = $this->leadStatuses->nextOrder();

        return DB::transaction(function () use ($attributes) {
            if (! empty($attributes['is_default'])) {
                $this->leadStatuses->query()->where('is_default', true)->update(['is_default' => false]);
            }

            return $this->leadStatuses->create($attributes);
        });
    }

    public function update(LeadStatus $leadStatus, array $attributes): LeadStatus
    {
        unset($attributes['slug']);
        $attributes['is_default'] = (bool) ($attributes['is_default'] ?? false);
        $attributes['is_closed_won'] = (bool) ($attributes['is_closed_won'] ?? false);
        $attributes['is_closed_lost'] = (bool) ($attributes['is_closed_lost'] ?? false);
        $attributes['is_achievement'] = (bool) ($attributes['is_achievement'] ?? false);

        return DB::transaction(function () use ($leadStatus, $attributes) {
            if (! empty($attributes['is_default'])) {
                $this->leadStatuses->query()->where('id', '!=', $leadStatus->id)->where('is_default', true)->update(['is_default' => false]);
            }

            $achievementFlagChanged = $attributes['is_achievement'] !== $leadStatus->is_achievement;

            $leadStatus = $this->leadStatuses->update($leadStatus, $attributes);

            if ($achievementFlagChanged) {
                $this->resyncLeadsInStatus($leadStatus);
            }

            return $leadStatus;
        });
    }

    /**
     * When a status's "counts as achievement" flag is flipped, every lead
     * currently sitting in that status must retroactively pick up (or lose)
     * its achieved_at and have affected goals recalculated — otherwise
     * pre-existing leads would silently keep the old behavior.
     */
    protected function resyncLeadsInStatus(LeadStatus $leadStatus): void
    {
        $leadStatus->leads()->each(fn ($lead) => $this->goalAchievements->applyStatusToLead($lead, $leadStatus));
    }

    public function delete(LeadStatus $leadStatus): void
    {
        if ($leadStatus->is_default) {
            throw ValidationException::withMessages([
                'lead_status' => 'The default status cannot be deleted.',
            ]);
        }

        if ($leadStatus->leads()->exists()) {
            throw ValidationException::withMessages([
                'lead_status' => 'This status has leads attached and cannot be deleted.',
            ]);
        }

        $this->leadStatuses->delete($leadStatus);
    }

    public function reorder(array $orderMap): void
    {
        DB::transaction(function () use ($orderMap) {
            foreach ($orderMap as $id => $order) {
                $this->leadStatuses->query()->whereKey($id)->update(['order' => (int) $order]);
            }
        });
    }
}
