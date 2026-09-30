<?php

namespace App\Services;

use App\Models\SystemModule;
use App\Repositories\SystemModuleRepository;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

class SystemModuleService
{
    public function __construct(protected SystemModuleRepository $modules)
    {
    }

    public function list(): Collection
    {
        return $this->modules->query()->withCount('requirements')->ordered()->get();
    }

    public function create(array $attributes): SystemModule
    {
        $attributes['slug'] = Str::slug($attributes['name']);

        return $this->modules->create($attributes);
    }

    public function update(SystemModule $module, array $attributes): SystemModule
    {
        $attributes['slug'] = Str::slug($attributes['name']);

        return $this->modules->update($module, $attributes);
    }

    public function delete(SystemModule $module): bool
    {
        return $this->modules->delete($module);
    }
}
