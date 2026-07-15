<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;

/**
 * Decides, per request, whether a user should actually be prompted with the
 * SOP/JD acknowledgment modal: nothing to show unless something is pending,
 * and even then only on a genuinely new/updated pending set (fingerprint
 * changed) or once every 12 hours otherwise. Extracted from
 * ResolvePendingPolicyAcknowledgments so the throttle/persist logic is
 * unit-testable without a full HTTP request cycle.
 */
class PolicyAcknowledgmentThrottleService
{
    public function __construct(protected PolicyAcknowledgmentResolver $resolver)
    {
    }

    /**
     * @return Collection<int, \App\Models\PolicyDocument> the documents to
     *                                                      prompt with now, empty if nothing should be shown
     */
    public function resolveForRequest(User $user): Collection
    {
        $pending = Cache::remember(
            "policy_ack_pending:{$user->id}",
            now()->addMinutes(5),
            fn () => $this->resolver->pendingFor($user)
        );

        if ($pending->isEmpty()) {
            return collect();
        }

        $fingerprint = $pending->map(fn ($document) => $document->currentVersion->id)->implode(',');

        if (! $this->shouldPromptNow($user, $fingerprint)) {
            return collect();
        }

        $user->forceFill([
            'policy_ack_last_prompted_at' => now(),
            'policy_ack_last_prompted_fingerprint' => $fingerprint,
        ])->save();

        return $pending;
    }

    private function shouldPromptNow(User $user, string $fingerprint): bool
    {
        if ($fingerprint !== $user->policy_ack_last_prompted_fingerprint) {
            return true;
        }

        return ! $user->policy_ack_last_prompted_at
            || $user->policy_ack_last_prompted_at->lt(now()->subHours(12));
    }
}
