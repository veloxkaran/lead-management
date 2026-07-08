<?php

namespace App\Repositories;

use App\Models\KnowledgeBaseItem;
use Illuminate\Pagination\LengthAwarePaginator;

class KnowledgeBaseItemRepository extends BaseRepository
{
    public function __construct(KnowledgeBaseItem $model)
    {
        parent::__construct($model);
    }

    public function filter(array $filters, int $perPage = 12): LengthAwarePaginator
    {
        $query = $this->query()->with(['category', 'uploader', 'tags']);

        if (! empty($filters['category_id'])) {
            $query->where('category_id', $filters['category_id']);
        }

        if (! empty($filters['type'])) {
            $query->where('type', $filters['type']);
        }

        if (! empty($filters['q'])) {
            $search = $filters['q'];
            $query->where(function ($q) use ($search) {
                $q->where('title', 'like', "%{$search}%")
                    ->orWhere('description', 'like', "%{$search}%")
                    ->orWhereHas('tags', fn ($tagQuery) => $tagQuery->where('name', 'like', "%{$search}%"));
            });
        }

        return $query->latest()->paginate($perPage)->withQueryString();
    }
}
