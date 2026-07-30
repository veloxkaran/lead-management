<?php

namespace App\Repositories;

use App\Enums\RequirementPriority;
use App\Enums\RequirementStatus;
use App\Models\SupportTicket;
use Illuminate\Pagination\LengthAwarePaginator;

class SupportTicketRepository extends BaseRepository
{
    public function __construct(SupportTicket $model)
    {
        parent::__construct($model);
    }

    public function filter(array $filters, int $perPage = 20): LengthAwarePaginator
    {
        $query = $this->query()->with(['lead', 'raiser', 'assignee']);

        if (! empty($filters['status'])) {
            $query->where('status', $filters['status']);
        }

        if (! empty($filters['priority'])) {
            $query->where('priority', $filters['priority']);
        }

        return $query
            ->orderByRaw(
                'CASE status WHEN ? THEN 1 WHEN ? THEN 2 WHEN ? THEN 3 WHEN ? THEN 4 ELSE 5 END',
                [
                    RequirementStatus::Pending->value,
                    RequirementStatus::InProgress->value,
                    RequirementStatus::OnHold->value,
                    RequirementStatus::Completed->value,
                ]
            )
            ->orderByRaw(
                'CASE priority WHEN ? THEN 1 WHEN ? THEN 2 WHEN ? THEN 3 WHEN ? THEN 4 ELSE 5 END',
                [
                    RequirementPriority::Urgent->value,
                    RequirementPriority::High->value,
                    RequirementPriority::Medium->value,
                    RequirementPriority::Low->value,
                ]
            )
            ->latest()
            ->paginate($perPage)
            ->withQueryString();
    }
}
