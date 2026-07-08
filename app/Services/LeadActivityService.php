<?php

namespace App\Services;

use App\Models\Lead;
use App\Models\LeadActivity;
use App\Models\User;
use App\Repositories\LeadActivityRepository;
use Illuminate\Pagination\LengthAwarePaginator;

class LeadActivityService
{
    public function __construct(protected LeadActivityRepository $activities)
    {
    }

    public function list(array $filters, int $perPage = 20): LengthAwarePaginator
    {
        return $this->activities->filter($filters, $perPage);
    }

    public function logForLead(Lead $lead, array $attributes, User $creator): LeadActivity
    {
        $attributes['lead_id'] = $lead->id;
        $attributes['created_by'] = $creator->id;

        return $this->activities->create($attributes);
    }
}
