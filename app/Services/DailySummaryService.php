<?php

namespace App\Services;

use App\Models\DailySummary;
use App\Models\User;
use App\Repositories\DailySummaryRepository;
use Illuminate\Pagination\LengthAwarePaginator;

class DailySummaryService
{
    public function __construct(protected DailySummaryRepository $dailySummaries)
    {
    }

    public function list(array $filters, User $viewer, int $perPage = 15): LengthAwarePaginator
    {
        return $this->dailySummaries->search($filters, $viewer, $perPage);
    }

    /**
     * Submit a new daily summary, or signal that one already exists for this
     * user/date so the caller can redirect to edit it instead.
     *
     * @return array{summary: DailySummary, created: bool}
     */
    public function submitOrRedirectToEdit(array $attributes, User $user): array
    {
        $existing = $this->dailySummaries->findForUserAndDate($user->id, $attributes['summary_date']);

        if ($existing) {
            return ['summary' => $existing, 'created' => false];
        }

        $attributes['user_id'] = $user->id;
        $summary = $this->dailySummaries->create($attributes);

        return ['summary' => $summary, 'created' => true];
    }

    public function update(DailySummary $dailySummary, array $attributes): DailySummary
    {
        return $this->dailySummaries->update($dailySummary, $attributes);
    }
}
