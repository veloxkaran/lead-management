<?php

namespace App\Support;

use App\Models\Lead;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * Turns a lead's scattered history (status changes, activities, notes,
 * requirements, follow-ups, deal closure) into one ordered, presentable
 * sequence of "steps" for the story-style walkthrough view.
 */
class LeadWalkthrough
{
    public static function build(Lead $lead): array
    {
        $events = collect()
            ->concat(static::statusEvents($lead))
            ->concat(static::activityEvents($lead))
            ->concat(static::noteEvents($lead))
            ->concat(static::requirementEvents($lead))
            ->concat(static::followUpEvents($lead))
            ->concat(static::dealClosureEvent($lead))
            ->sortBy('at')
            ->values();

        return collect([static::greeting($lead)])
            ->concat($events)
            ->push(static::closure($lead))
            ->map(fn (array $step, int $i) => $step + ['index' => $i])
            ->values()
            ->all();
    }

    protected static function greeting(Lead $lead): array
    {
        return [
            'type' => 'greeting',
            'icon' => 'bi-stars',
            'accent' => 'primary',
            'title' => "Let's walk through {$lead->company_name}",
            'subtitle' => 'Every step, from first contact to where things stand today.',
            'body' => collect([
                $lead->source ? "Sourced via {$lead->source}." : null,
                $lead->assignedUser ? "Owned by {$lead->assignedUser->name}." : null,
                'Started '.$lead->created_at->diffForHumans(),
            ])->filter()->implode(' '),
            'actor' => null,
            'meta' => null,
        ];
    }

    protected static function closure(Lead $lead): array
    {
        if ($lead->dealClosure) {
            return [
                'type' => 'closure',
                'outcome' => 'won',
                'icon' => 'bi-trophy-fill',
                'accent' => 'success',
                'title' => 'Deal closed! 🎉',
                'subtitle' => $lead->company_name.' — '.Currency::format($lead->dealClosure->deal_value),
                'body' => $lead->dealClosure->closing_comment,
                'actor' => $lead->dealClosure->closedBy?->name,
                'meta' => $lead->dealClosure->closed_date?->format('M d, Y'),
            ];
        }

        if ($lead->status?->is_closed_lost) {
            return [
                'type' => 'closure',
                'outcome' => 'lost',
                'icon' => 'bi-emoji-neutral',
                'accent' => 'secondary',
                'title' => 'This one didn\'t close',
                'subtitle' => "Currently marked {$lead->status->name}.",
                'body' => 'On to the next one — every lead teaches you something about the pipeline.',
                'actor' => null,
                'meta' => null,
            ];
        }

        return [
            'type' => 'closure',
            'outcome' => 'open',
            'icon' => 'bi-hourglass-split',
            'accent' => $lead->status?->color ?? 'primary',
            'title' => 'Still in motion',
            'subtitle' => $lead->status ? "Currently at {$lead->status->name}." : 'No status set yet.',
            'body' => $lead->currentStatusAge().' in this status so far.',
            'actor' => null,
            'meta' => null,
        ];
    }

    protected static function statusEvents(Lead $lead): Collection
    {
        return $lead->statusHistories->map(function ($history) {
            $spent = $history->seconds_in_previous_status
                ? \Carbon\CarbonInterval::seconds($history->seconds_in_previous_status)->cascade()->forHumans(['short' => true])
                : null;

            return [
                'type' => 'status_change',
                'icon' => 'bi-arrow-left-right',
                'accent' => $history->toStatus?->color ?? 'primary',
                'title' => $history->fromStatus
                    ? "{$history->fromStatus->name} → {$history->toStatus?->name}"
                    : "Set to {$history->toStatus?->name}",
                'subtitle' => $spent ? "Spent {$spent} in {$history->fromStatus?->name}" : 'First status on this lead',
                'body' => null,
                'actor' => $history->changedBy?->name,
                'meta' => $history->changed_at?->format('M d, Y g:i A'),
                'at' => $history->changed_at ?? $history->created_at,
            ];
        });
    }

    protected static function activityEvents(Lead $lead): Collection
    {
        return $lead->activities->map(function ($activity) {
            $at = Carbon::parse($activity->activity_date->format('Y-m-d').' '.$activity->activity_time);

            return [
                'type' => 'activity',
                'icon' => $activity->activity_type->icon(),
                'accent' => 'info',
                'title' => $activity->activity_type->label().' logged',
                'subtitle' => null,
                'body' => $activity->description,
                'actor' => $activity->creator?->name,
                'meta' => $at->format('M d, Y g:i A'),
                'at' => $at,
            ];
        });
    }

    protected static function noteEvents(Lead $lead): Collection
    {
        return $lead->notes->map(fn ($note) => [
            'type' => 'note',
            'icon' => 'bi-chat-left-text',
            'accent' => 'secondary',
            'title' => 'Comment added',
            'subtitle' => $note->attachments->count() ? $note->attachments->count().' attachment(s)' : null,
            'body' => $note->comment,
            'actor' => $note->author?->name,
            'meta' => $note->created_at->format('M d, Y g:i A'),
            'at' => $note->created_at,
        ]);
    }

    protected static function requirementEvents(Lead $lead): Collection
    {
        return $lead->requirements->map(fn ($requirement) => [
            'type' => 'requirement',
            'icon' => 'bi-list-check',
            'accent' => 'warning',
            'title' => 'Requirement raised',
            'subtitle' => $requirement->priority->label().' priority · '.$requirement->status->label(),
            'body' => $requirement->requirement,
            'actor' => $requirement->creator?->name,
            'meta' => $requirement->created_at->format('M d, Y g:i A'),
            'at' => $requirement->created_at,
        ]);
    }

    protected static function followUpEvents(Lead $lead): Collection
    {
        return $lead->followUps->map(fn ($followUp) => [
            'type' => 'follow_up',
            'icon' => 'bi-bell',
            'accent' => 'warning',
            'title' => 'Follow-up scheduled',
            'subtitle' => 'For '.$followUp->follow_up_date->format('M d, Y').' at '.Carbon::parse($followUp->follow_up_time)->format('g:i A'),
            'body' => null,
            'actor' => $followUp->creator?->name,
            'meta' => $followUp->status->label(),
            'at' => $followUp->created_at,
        ]);
    }

    protected static function dealClosureEvent(Lead $lead): Collection
    {
        if (! $lead->dealClosure) {
            return collect();
        }

        return collect([[
            'type' => 'deal_closure',
            'icon' => 'bi-trophy',
            'accent' => 'success',
            'title' => 'Deal marked closed-won',
            'subtitle' => Currency::format($lead->dealClosure->deal_value),
            'body' => $lead->dealClosure->closing_comment,
            'actor' => $lead->dealClosure->closedBy?->name,
            'meta' => $lead->dealClosure->closed_date?->format('M d, Y'),
            'at' => $lead->dealClosure->created_at,
        ]]);
    }
}
