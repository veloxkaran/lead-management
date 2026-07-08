<?php

namespace App\Services;

use App\Events\RequirementSaved;
use App\Models\Lead;
use App\Models\Requirement;
use App\Models\User;
use App\Repositories\RequirementRepository;
use Illuminate\Pagination\LengthAwarePaginator;

class RequirementService
{
    public function __construct(protected RequirementRepository $requirements)
    {
    }

    public function list(array $filters, int $perPage = 20): LengthAwarePaginator
    {
        return $this->requirements->filter($filters, $perPage);
    }

    public function create(array $attributes, User $creator): Requirement
    {
        $attributes['created_by'] = $creator->id;

        /** @var Requirement $requirement */
        $requirement = $this->requirements->create($attributes);

        event(new RequirementSaved($requirement, true));

        return $requirement;
    }

    public function createForLead(Lead $lead, array $attributes, User $creator): Requirement
    {
        $attributes['lead_id'] = $lead->id;

        return $this->create($attributes, $creator);
    }

    public function update(Requirement $requirement, array $attributes): Requirement
    {
        $requirement = $this->requirements->update($requirement, $attributes);

        event(new RequirementSaved($requirement, false));

        return $requirement;
    }

    public function delete(Requirement $requirement): void
    {
        $this->requirements->delete($requirement);
    }
}
