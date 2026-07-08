<?php

namespace App\Services;

use App\Models\KnowledgeBaseCategory;
use App\Repositories\KnowledgeBaseCategoryRepository;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

class KnowledgeBaseCategoryService
{
    public function __construct(protected KnowledgeBaseCategoryRepository $categories)
    {
    }

    public function list(): Collection
    {
        return $this->categories->query()->withCount('items')->orderBy('name')->get();
    }

    public function create(array $attributes): KnowledgeBaseCategory
    {
        $attributes['slug'] = Str::slug($attributes['name']);

        return $this->categories->create($attributes);
    }

    public function update(KnowledgeBaseCategory $category, array $attributes): KnowledgeBaseCategory
    {
        $attributes['slug'] = Str::slug($attributes['name']);

        return $this->categories->update($category, $attributes);
    }

    public function delete(KnowledgeBaseCategory $category): bool
    {
        return $this->categories->delete($category);
    }
}
