<?php

namespace App\Services;

use App\Enums\RequirementStatus;
use App\Models\AccountRequest;
use App\Models\User;
use App\Repositories\AccountRequestRepository;
use Illuminate\Pagination\LengthAwarePaginator;

class AccountRequestService
{
    public function __construct(protected AccountRequestRepository $requests)
    {
    }

    public function list(array $filters, int $perPage = 20): LengthAwarePaginator
    {
        return $this->requests->filter($filters, $perPage);
    }

    public function create(array $attributes, User $requester): AccountRequest
    {
        $attributes['requested_by'] = $requester->id;

        return $this->requests->create($attributes);
    }

    public function update(AccountRequest $request, array $attributes): AccountRequest
    {
        if (($attributes['status'] ?? null) === RequirementStatus::Completed->value && ! $request->processed_at) {
            $attributes['processed_at'] = now();
        }

        return $this->requests->update($request, $attributes);
    }

    public function delete(AccountRequest $request): void
    {
        $this->requests->delete($request);
    }
}
