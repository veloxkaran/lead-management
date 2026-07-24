<?php

namespace App\Services;

use App\Enums\ActivityType;
use App\Enums\ImplementationStatus;
use App\Models\ImplementationRequest;
use App\Models\LeadActivity;
use App\Models\User;
use App\Repositories\ImplementationRequestRepository;
use Illuminate\Pagination\LengthAwarePaginator;

class ImplementationRequestService
{
    public function __construct(protected ImplementationRequestRepository $requests)
    {
    }

    public function list(array $filters, int $perPage = 20): LengthAwarePaginator
    {
        return $this->requests->filter($filters, $perPage);
    }

    public function create(array $attributes, User $requester): ImplementationRequest
    {
        $attributes['requested_by'] = $requester->id;
        $attributes['status'] = $attributes['status'] ?? ImplementationStatus::NotStarted->value;

        /** @var ImplementationRequest $request */
        $request = $this->requests->create($attributes);

        $this->logActivity($request, $requester, "Implementation request \"{$request->title}\" raised by {$requester->name}.");

        return $request;
    }

    public function update(ImplementationRequest $request, array $attributes, User $actor): ImplementationRequest
    {
        if (($attributes['status'] ?? null) === ImplementationStatus::Completed->value && ! $request->completed_at) {
            $attributes['completed_at'] = now();
        }

        $request = $this->requests->update($request, $attributes);

        $summary = "Implementation request \"{$request->title}\" updated by {$actor->name} — status: {$request->status->label()}";
        $summary .= $request->assignee ? ", assigned to {$request->assignee->name}." : '.';

        $this->logActivity($request, $actor, $summary);

        return $request;
    }

    public function delete(ImplementationRequest $request): void
    {
        $this->requests->delete($request);
    }

    private function logActivity(ImplementationRequest $request, User $actor, string $description): void
    {
        LeadActivity::create([
            'lead_id' => $request->lead_id,
            'activity_type' => ActivityType::ImplementationRequest,
            'activity_date' => now()->toDateString(),
            'activity_time' => now()->toTimeString(),
            'description' => $description,
            'created_by' => $actor->id,
        ]);
    }
}
