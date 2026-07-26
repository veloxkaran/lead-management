<?php

namespace App\Repositories;

use App\Models\Agenda;
use App\Models\User;
use Illuminate\Pagination\LengthAwarePaginator;

class AgendaRepository extends BaseRepository
{
    public function __construct(Agenda $model)
    {
        parent::__construct($model);
    }

    /**
     * The Team Meeting Room has no visibility scoping (every user sees
     * every agenda) — unlike TaskRepository::filter(), there's no
     * hierarchy/assignment `where` gating the base query, only the
     * search/status/sort filters below.
     */
    public function filter(array $filters, User $viewer, int $perPage = 20): LengthAwarePaginator
    {
        $query = $this->query()->with('creator')->withCount('comments');

        if (! empty($filters['search'])) {
            $term = '%'.$filters['search'].'%';

            $query->where(function ($q) use ($term) {
                $q->where('title', 'like', $term)
                    ->orWhere('description', 'like', $term)
                    ->orWhereHas('creator', fn ($c) => $c->where('name', 'like', $term));
            });
        }

        if (! empty($filters['status']) && $filters['status'] !== 'all') {
            if ($filters['status'] === 'mine') {
                $query->where('created_by', $viewer->id);
            } else {
                $query->where('status', $filters['status']);
            }
        }

        return match ($filters['sort'] ?? 'newest') {
            'oldest' => $query->oldest()->paginate($perPage)->withQueryString(),
            'recently_updated' => $query->orderByDesc('updated_at')->paginate($perPage)->withQueryString(),
            'most_discussed' => $query->orderByDesc('comments_count')->paginate($perPage)->withQueryString(),
            default => $query->latest()->paginate($perPage)->withQueryString(),
        };
    }
}
