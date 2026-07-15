<?php

namespace App\Repositories;

use App\Models\DailySummary;
use App\Models\User;
use Illuminate\Pagination\LengthAwarePaginator;

class DailySummaryRepository extends BaseRepository
{
    public function __construct(DailySummary $model)
    {
        parent::__construct($model);
    }

    public function search(array $filters, User $viewer, int $perPage = 15): LengthAwarePaginator
    {
        $query = $this->query()->with('user');

        if (! $viewer->isOverseer()) {
            $query->where('user_id', $viewer->id);
        } elseif (! empty($filters['user_id'])) {
            $query->where('user_id', $filters['user_id']);
        }

        if (! empty($filters['q'])) {
            $search = $filters['q'];
            $query->where(function ($q) use ($search) {
                $q->where('achieved_today', 'like', "%{$search}%")
                    ->orWhere('planned_tomorrow', 'like', "%{$search}%")
                    ->orWhere('blockers', 'like', "%{$search}%");
            });
        }

        if (! empty($filters['from'])) {
            $query->whereDate('summary_date', '>=', $filters['from']);
        }

        if (! empty($filters['to'])) {
            $query->whereDate('summary_date', '<=', $filters['to']);
        }

        return $query->orderByDesc('summary_date')->paginate($perPage)->withQueryString();
    }

    public function findForUserAndDate(int $userId, string $date): ?DailySummary
    {
        return $this->query()->where('user_id', $userId)->whereDate('summary_date', $date)->first();
    }
}
