<?php

namespace App\Repositories\Contracts;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Pagination\LengthAwarePaginator;

interface RepositoryInterface
{
    public function query(): Builder;

    public function all(array $with = []): \Illuminate\Support\Collection;

    public function paginate(int $perPage = 15, array $with = []): LengthAwarePaginator;

    public function find(int $id, array $with = []): ?Model;

    public function findOrFail(int $id, array $with = []): Model;

    public function create(array $attributes): Model;

    public function update(Model $model, array $attributes): Model;

    public function delete(Model $model): bool;
}
