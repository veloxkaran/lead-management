<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>{{ $lead->company_name }} - Full History - {{ config('app.name') }}</title>
    <style>
        body { font-family: sans-serif; font-size: 11px; color: #212529; }
        .brand { font-size: 11px; color: #6c757d; text-transform: uppercase; letter-spacing: 0.05em; margin: 0; }
        h1 { font-size: 20px; margin: 2px 0 0; }
        h2 { font-size: 14px; margin: 18px 0 6px; padding-bottom: 3px; border-bottom: 2px solid #33475b; }
        table { width: 100%; border-collapse: collapse; margin-bottom: 4px; }
        th, td { border: 1px solid #ccc; padding: 5px 7px; text-align: left; vertical-align: top; }
        th { background: #f4f6f9; }
        .meta-table td { border: none; padding: 2px 7px 2px 0; }
        .meta-table td.label { color: #6c757d; width: 140px; }
        .muted { color: #6c757d; }
        .reply { padding-left: 18px; }
        .empty { color: #6c757d; font-style: italic; }
    </style>
</head>
<body>
    <p class="brand">{{ config('app.name') }} &middot; Lead Full History</p>
    <h1>{{ $lead->company_name }}</h1>
    <p class="muted">Generated {{ now()->format('M d, Y g:i A') }}</p>

    <h2>Lead Details</h2>
    <table class="meta-table">
        <tr>
            <td class="label">Contact Person</td><td>{{ $lead->contact_person }}</td>
            <td class="label">Email</td><td>{{ $lead->email ?: '—' }}</td>
        </tr>
        <tr>
            <td class="label">Phone</td><td>{{ $lead->phone ?: '—' }}</td>
            <td class="label">Industry</td><td>{{ $lead->industry ?: '—' }}</td>
        </tr>
        <tr>
            <td class="label">Source</td><td>{{ $lead->source ?: '—' }}</td>
            <td class="label">Employees</td><td>{{ $lead->number_of_employees ?: '—' }}</td>
        </tr>
        <tr>
            <td class="label">Status</td><td>{{ $lead->status?->name ?? '—' }}</td>
            <td class="label">Assigned To</td><td>{{ $lead->assignedUser?->name ?? '—' }}</td>
        </tr>
        <tr>
            <td class="label">Opportunity Cost</td><td>{{ $lead->opportunity_cost !== null ? \App\Support\Currency::format($lead->opportunity_cost) : '—' }}</td>
            <td class="label">Achieved Cost</td><td>{{ \App\Support\Currency::format($lead->achieved_cost) }}</td>
        </tr>
        <tr>
            <td class="label">Client Since</td><td>{{ $lead->created_at->format('M d, Y') }}</td>
            <td class="label">Created By</td><td>{{ $lead->creator?->name ?? '—' }}</td>
        </tr>
    </table>
    @if ($lead->business_details)
        <p><strong>Business Details:</strong> {{ $lead->business_details }}</p>
    @endif
    @if ($lead->about_client_business)
        <p><strong>About Client:</strong> {{ $lead->about_client_business }}</p>
    @endif

    @if ($lead->dealClosure)
        <h2>Deal Closure</h2>
        <table class="meta-table">
            <tr>
                <td class="label">Value</td><td>{{ \App\Support\Currency::format($lead->dealClosure->deal_value) }}</td>
                <td class="label">Closed By</td><td>{{ $lead->dealClosure->closedBy?->name ?? '—' }}</td>
            </tr>
            <tr>
                <td class="label">Closed Date</td><td>{{ $lead->dealClosure->closed_date->format('M d, Y') }}</td>
                <td class="label">Comment</td><td>{{ $lead->dealClosure->closing_comment ?: '—' }}</td>
            </tr>
        </table>
    @endif

    <h2>Status History</h2>
    <table>
        <thead><tr><th>Date</th><th>From</th><th>To</th><th>Changed By</th></tr></thead>
        <tbody>
            @forelse ($lead->statusHistories as $history)
                <tr>
                    <td>{{ $history->created_at->format('M d, Y g:i A') }}</td>
                    <td>{{ $history->fromStatus?->name ?? '—' }}</td>
                    <td>{{ $history->toStatus?->name ?? '—' }}</td>
                    <td>{{ $history->changedBy?->name ?? '—' }}</td>
                </tr>
            @empty
                <tr><td colspan="4" class="empty">No status changes recorded.</td></tr>
            @endforelse
        </tbody>
    </table>

    <h2>Activities</h2>
    <table>
        <thead><tr><th>Date</th><th>Type</th><th>Description</th><th>By</th></tr></thead>
        <tbody>
            @forelse ($lead->activities as $activity)
                <tr>
                    <td>{{ $activity->activity_date->format('M d, Y') }} {{ $activity->activity_time }}</td>
                    <td>{{ $activity->activity_type->label() }}</td>
                    <td>{{ $activity->description }}</td>
                    <td>{{ $activity->creator?->name ?? '—' }}</td>
                </tr>
            @empty
                <tr><td colspan="4" class="empty">No activities recorded.</td></tr>
            @endforelse
        </tbody>
    </table>

    <h2>Notes</h2>
    <table>
        <thead><tr><th>Date</th><th>Author</th><th>Note</th></tr></thead>
        <tbody>
            @forelse ($lead->notes as $note)
                <tr>
                    <td>{{ $note->created_at->format('M d, Y g:i A') }}</td>
                    <td>{{ $note->author?->name ?? '—' }}</td>
                    <td>{{ $note->comment }}</td>
                </tr>
            @empty
                <tr><td colspan="3" class="empty">No notes recorded.</td></tr>
            @endforelse
        </tbody>
    </table>

    <h2>Follow Ups</h2>
    <table>
        <thead><tr><th>Date</th><th>Status</th><th>Reminder</th><th>Created By</th></tr></thead>
        <tbody>
            @forelse ($lead->followUps as $followUp)
                <tr>
                    <td>{{ $followUp->follow_up_date->format('M d, Y') }} {{ $followUp->follow_up_time }}</td>
                    <td>{{ $followUp->status->label() }}</td>
                    <td>{{ $followUp->reminder_type->label() }}</td>
                    <td>{{ $followUp->creator?->name ?? '—' }}</td>
                </tr>
            @empty
                <tr><td colspan="4" class="empty">No follow-ups recorded.</td></tr>
            @endforelse
        </tbody>
    </table>

    <h2>Requirements</h2>
    @forelse ($lead->requirements as $requirement)
        <table>
            <thead><tr><th>Requirement</th><th>Priority</th><th>Status</th><th>Assigned</th><th>Raised By</th></tr></thead>
            <tbody>
                <tr>
                    <td>{{ $requirement->requirement }}</td>
                    <td>{{ $requirement->priority->label() }}</td>
                    <td>{{ $requirement->status->label() }}</td>
                    <td>{{ $requirement->assignee?->name ?? '—' }}</td>
                    <td>{{ $requirement->creator?->name ?? '—' }} on {{ $requirement->created_at->format('M d, Y g:i A') }}</td>
                </tr>
                <tr>
                    <td colspan="5">
                        <strong>Comments:</strong>
                        @forelse ($requirement->comments as $comment)
                            <div>
                                <span class="muted">{{ $comment->created_at->format('M d, Y g:i A') }} &middot; {{ $comment->author?->name ?? '—' }}:</span>
                                {{ $comment->comment }}
                            </div>
                        @empty
                            <span class="empty">No comments.</span>
                        @endforelse
                    </td>
                </tr>
            </tbody>
        </table>
    @empty
        <p class="empty">No requirements recorded.</p>
    @endforelse

    <h2>Support Tickets</h2>
    @forelse ($lead->supportTickets as $ticket)
        <table>
            <thead><tr><th>Subject</th><th>Priority</th><th>Status</th><th>Raised By</th><th>Assigned</th></tr></thead>
            <tbody>
                <tr>
                    <td>{{ $ticket->subject }}</td>
                    <td>{{ $ticket->priority->label() }}</td>
                    <td>{{ $ticket->status->label() }}</td>
                    <td>{{ $ticket->raiser?->name ?? '—' }}</td>
                    <td>{{ $ticket->assignee?->name ?? '—' }}</td>
                </tr>
                @if ($ticket->details)
                    <tr><td colspan="5"><strong>Details:</strong> {{ $ticket->details }}</td></tr>
                @endif
                <tr>
                    <td colspan="5">
                        <strong>Documents:</strong>
                        {{ $ticket->attachments->isNotEmpty() ? $ticket->attachments->pluck('original_name')->implode(', ') : 'None' }}
                    </td>
                </tr>
                <tr>
                    <td colspan="5">
                        <strong>Comments:</strong>
                        @forelse ($ticket->comments as $comment)
                            <div class="{{ $comment->parent_id ? 'reply' : '' }}">
                                <span class="muted">{{ $comment->created_at->format('M d, Y g:i A') }} &middot; {{ $comment->author?->name ?? '—' }}:</span>
                                {{ $comment->comment }}
                            </div>
                        @empty
                            <span class="empty">No comments.</span>
                        @endforelse
                    </td>
                </tr>
            </tbody>
        </table>
    @empty
        <p class="empty">No support tickets recorded.</p>
    @endforelse

    <h2>Tasks</h2>
    @forelse ($lead->tasks as $task)
        <table>
            <thead><tr><th>Title</th><th>Priority</th><th>Status</th><th>Assigned</th><th>Due</th></tr></thead>
            <tbody>
                <tr>
                    <td>{{ $task->title }}</td>
                    <td>{{ $task->priority->label() }}</td>
                    <td>{{ $task->status->label() }}</td>
                    <td>{{ $task->assignee?->name ?? '—' }}</td>
                    <td>{{ $task->due_date?->format('M d, Y') ?? '—' }}</td>
                </tr>
                @if ($task->description)
                    <tr><td colspan="5"><strong>Description:</strong> {{ $task->description }}</td></tr>
                @endif
                <tr>
                    <td colspan="5">
                        <strong>Comments:</strong>
                        @forelse ($task->comments as $comment)
                            <div>
                                <span class="muted">{{ $comment->created_at->format('M d, Y g:i A') }} &middot; {{ $comment->author?->name ?? '—' }}:</span>
                                {{ $comment->comment }}
                            </div>
                        @empty
                            <span class="empty">No comments.</span>
                        @endforelse
                    </td>
                </tr>
            </tbody>
        </table>
    @empty
        <p class="empty">No tasks recorded.</p>
    @endforelse

    <h2>Implementation Requests</h2>
    <table>
        <thead><tr><th>Title</th><th>Status</th><th>Phase</th><th>Progress</th><th>Requested By</th><th>Assigned</th></tr></thead>
        <tbody>
            @forelse ($lead->implementationRequests as $request)
                <tr>
                    <td>{{ $request->title }}</td>
                    <td>{{ $request->status->label() }}</td>
                    <td>{{ $request->phase ?: '—' }}</td>
                    <td>{{ $request->completion_percentage }}%</td>
                    <td>{{ $request->requester?->name ?? '—' }}</td>
                    <td>{{ $request->assignee?->name ?? '—' }}</td>
                </tr>
            @empty
                <tr><td colspan="6" class="empty">No implementation requests recorded.</td></tr>
            @endforelse
        </tbody>
    </table>

    <h2>Trainings</h2>
    <table>
        <thead><tr><th>Date</th><th>Status</th><th>Trainer</th><th>Attendees</th><th>Progress</th></tr></thead>
        <tbody>
            @forelse ($lead->trainings as $training)
                <tr>
                    <td>{{ $training->training_date?->format('M d, Y') ?? '—' }}</td>
                    <td>{{ $training->status->label() }}</td>
                    <td>{{ $training->trainer_name ?? $training->conductor?->name ?? '—' }}</td>
                    <td>{{ $training->attendees_count ?? '—' }}</td>
                    <td>{{ $training->completion_percentage }}%</td>
                </tr>
            @empty
                <tr><td colspan="5" class="empty">No trainings recorded.</td></tr>
            @endforelse
        </tbody>
    </table>

    <h2>Subscriptions</h2>
    <table>
        <thead><tr><th>Plan</th><th>Status</th><th>Start</th><th>Expiry</th><th>Billing</th><th>Renewal</th></tr></thead>
        <tbody>
            @forelse ($lead->subscriptions as $subscription)
                <tr>
                    <td>{{ $subscription->plan_name }}</td>
                    <td>{{ $subscription->status->label() }}</td>
                    <td>{{ $subscription->contract_start_date?->format('M d, Y') ?? '—' }}</td>
                    <td>{{ $subscription->expiry_date?->format('M d, Y') ?? '—' }}</td>
                    <td>{{ $subscription->billing_cycle?->label() ?? '—' }}</td>
                    <td>{{ \App\Support\Currency::format($subscription->renewal_amount) }}</td>
                </tr>
            @empty
                <tr><td colspan="6" class="empty">No subscriptions recorded.</td></tr>
            @endforelse
        </tbody>
    </table>

    <h2>Account Requests</h2>
    <table>
        <thead><tr><th>Type</th><th>Amount</th><th>Status</th><th>Requested By</th><th>Processed By</th></tr></thead>
        <tbody>
            @forelse ($lead->accountRequests as $request)
                <tr>
                    <td>{{ $request->request_type->label() }}</td>
                    <td>{{ \App\Support\Currency::format($request->amount) }}</td>
                    <td>{{ $request->status->label() }}</td>
                    <td>{{ $request->requester?->name ?? '—' }}</td>
                    <td>{{ $request->processor?->name ?? '—' }}</td>
                </tr>
            @empty
                <tr><td colspan="5" class="empty">No account requests recorded.</td></tr>
            @endforelse
        </tbody>
    </table>
</body>
</html>
