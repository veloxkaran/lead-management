<?php

namespace App\Services;

use App\Models\Industry;
use App\Models\Lead;
use App\Repositories\IndustryRepository;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class IndustryService
{
    public function __construct(protected IndustryRepository $industries)
    {
    }

    public function list(): Collection
    {
        return $this->industries->query()->withCount('leads')->ordered()->get();
    }

    public function create(array $attributes): Industry
    {
        $attributes['slug'] = Str::slug($attributes['name']);

        return $this->industries->create($attributes);
    }

    /**
     * Leads hold the industry by name, so a rename is carried over to every
     * lead filed under the old name (archived and soft-deleted ones too) —
     * otherwise they'd silently fall off the list and fail validation on
     * their next edit.
     */
    public function update(Industry $industry, array $attributes): Industry
    {
        $attributes['slug'] = Str::slug($attributes['name']);
        $oldName = $industry->name;

        return DB::transaction(function () use ($industry, $attributes, $oldName) {
            $industry = $this->industries->update($industry, $attributes);

            if ($industry->name !== $oldName) {
                Lead::withTrashed()->where('industry', $oldName)->update(['industry' => $industry->name]);
            }

            return $industry;
        });
    }

    public function delete(Industry $industry): bool
    {
        return $this->industries->delete($industry);
    }
}
