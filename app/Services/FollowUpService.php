<?php

namespace App\Services;

use App\Models\FollowUp;
use App\Models\Lead;
use App\Models\User;
use App\Repositories\FollowUpRepository;
use Illuminate\Pagination\LengthAwarePaginator;

class FollowUpService
{
    public function __construct(protected FollowUpRepository $followUps)
    {
    }

    public function list(array $filters, int $perPage = 20): LengthAwarePaginator
    {
        return $this->followUps->filter($filters, $perPage);
    }

    public function createForLead(Lead $lead, array $attributes, User $creator): FollowUp
    {
        $attributes['lead_id'] = $lead->id;
        $attributes['created_by'] = $creator->id;

        return $this->followUps->create($attributes);
    }

    public function update(FollowUp $followUp, array $attributes): FollowUp
    {
        return $this->followUps->update($followUp, $attributes);
    }

    public function delete(FollowUp $followUp): void
    {
        $this->followUps->delete($followUp);
    }
}
