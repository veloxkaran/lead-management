<?php

namespace App\Services;

use App\Enums\ActivityType;
use App\Models\LeadActivity;
use App\Models\Subscription;
use App\Models\User;
use App\Repositories\SubscriptionRepository;
use Illuminate\Pagination\LengthAwarePaginator;

class SubscriptionService
{
    public function __construct(protected SubscriptionRepository $subscriptions)
    {
    }

    public function list(array $filters, int $perPage = 20): LengthAwarePaginator
    {
        return $this->subscriptions->filter($filters, $perPage);
    }

    public function create(array $attributes, User $actor): Subscription
    {
        $attributes['created_by'] = $actor->id;

        /** @var Subscription $subscription */
        $subscription = $this->subscriptions->create($attributes);

        $this->logActivity($subscription, $actor, "Subscription \"{$subscription->plan_name}\" created by {$actor->name}.");

        return $subscription;
    }

    public function update(Subscription $subscription, array $attributes, User $actor): Subscription
    {
        $subscription = $this->subscriptions->update($subscription, $attributes);

        $this->logActivity($subscription, $actor, "Subscription updated by {$actor->name} — status: {$subscription->status->label()}.");

        return $subscription;
    }

    public function delete(Subscription $subscription): void
    {
        $this->subscriptions->delete($subscription);
    }

    private function logActivity(Subscription $subscription, User $actor, string $description): void
    {
        LeadActivity::create([
            'lead_id' => $subscription->lead_id,
            'activity_type' => ActivityType::SubscriptionUpdate,
            'activity_date' => now()->toDateString(),
            'activity_time' => now()->toTimeString(),
            'description' => $description,
            'created_by' => $actor->id,
        ]);
    }
}
