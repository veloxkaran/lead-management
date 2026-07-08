@extends('layouts.app')

@section('title', 'Dashboard')

@section('content')
    <x-page-header title="My Dashboard" icon="bi-speedometer2" />

    <div class="row g-3 mb-3">
        <div class="col-lg-8">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-header bg-white fw-semibold d-flex justify-content-between">
                    <span><i class="bi bi-diagram-3 me-1"></i> My Leads</span>
                    <a href="{{ route('leads.index') }}" class="small">View all</a>
                </div>
                <div class="table-responsive">
                    <table class="table table-sm mb-0">
                        <thead class="table-light"><tr><th>Company</th><th>Status</th><th>Created</th></tr></thead>
                        <tbody>
                            @forelse ($personalLeads as $lead)
                                <tr>
                                    <td><a href="{{ route('leads.show', $lead) }}" class="text-decoration-none">{{ $lead->company_name }}</a></td>
                                    <td>@if($lead->status)<span class="badge" style="background-color: {{ $lead->status->color }}">{{ $lead->status->name }}</span>@endif</td>
                                    <td class="small text-muted">{{ $lead->created_at->diffForHumans() }}</td>
                                </tr>
                            @empty
                                <tr><td colspan="3"><x-empty-state icon="bi-diagram-3" title="No leads assigned yet" /></td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
        <div class="col-lg-4">
            <div class="card border-0 shadow-sm text-white h-100" style="background: linear-gradient(135deg,#2456a6,#1b2430);">
                <div class="card-body">
                    <i class="bi bi-quote fs-2 opacity-75"></i>
                    <p class="mb-1">{{ $quote['text'] }}</p>
                    <p class="small opacity-75 mb-0">— {{ $quote['author'] }}</p>
                </div>
            </div>
        </div>
    </div>

    <div class="row g-3 mb-3">
        <div class="col-lg-4">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-header bg-white fw-semibold"><i class="bi bi-bell me-1"></i> Today's Reminders</div>
                <ul class="list-group list-group-flush">
                    @forelse ($todaysReminders as $reminder)
                        <li class="list-group-item small d-flex justify-content-between">
                            <a href="{{ route('leads.show', $reminder->lead) }}" class="text-decoration-none">{{ $reminder->lead->company_name }}</a>
                            <span class="text-muted">{{ \Illuminate\Support\Carbon::parse($reminder->follow_up_time)->format('g:i A') }}</span>
                        </li>
                    @empty
                        <li class="list-group-item"><x-empty-state icon="bi-bell" title="Nothing due today" /></li>
                    @endforelse
                </ul>
            </div>
        </div>
        <div class="col-lg-4">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-header bg-white fw-semibold"><i class="bi bi-calendar-week me-1"></i> Upcoming Follow-ups</div>
                <ul class="list-group list-group-flush">
                    @forelse ($upcomingFollowUps as $followUp)
                        <li class="list-group-item small d-flex justify-content-between">
                            <a href="{{ route('leads.show', $followUp->lead) }}" class="text-decoration-none">{{ $followUp->lead->company_name }}</a>
                            <span class="text-muted">{{ $followUp->follow_up_date->format('M d') }}</span>
                        </li>
                    @empty
                        <li class="list-group-item"><x-empty-state icon="bi-calendar-week" title="No upcoming follow-ups" /></li>
                    @endforelse
                </ul>
            </div>
        </div>
        <div class="col-lg-4">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-header bg-white fw-semibold"><i class="bi bi-camera-video me-1"></i> Google Meet Schedule</div>
                <ul class="list-group list-group-flush">
                    @forelse ($meetings as $meeting)
                        <li class="list-group-item small">
                            <a href="{{ $meeting->meeting_link }}" target="_blank" class="text-decoration-none fw-semibold">{{ $meeting->title }}</a>
                            <div class="text-muted">{{ $meeting->meeting_date->format('M d') }} · {{ \Illuminate\Support\Carbon::parse($meeting->meeting_time)->format('g:i A') }}</div>
                        </li>
                    @empty
                        <li class="list-group-item"><x-empty-state icon="bi-camera-video" title="No meetings scheduled" /></li>
                    @endforelse
                </ul>
            </div>
        </div>
    </div>

    <div class="row g-3 mb-3">
        <div class="col-lg-6">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-header bg-white fw-semibold d-flex justify-content-between">
                    <span><i class="bi bi-bullseye me-1"></i> Goal vs Achievement</span>
                    <a href="{{ route('goals.index') }}" class="small">View all</a>
                </div>
                <div class="card-body">
                    @forelse ($individualGoals->concat($teamGoals) as $goal)
                        <div class="mb-3">
                            <div class="d-flex justify-content-between small mb-1">
                                <span>{{ $goal->title }}</span>
                                <span>{{ $goal->achievementPercentage() }}%</span>
                            </div>
                            <div class="progress" style="height: 8px;">
                                <div class="progress-bar" style="width: {{ $goal->achievementPercentage() }}%"></div>
                            </div>
                            <div class="small text-muted mt-1"><x-currency :amount="$goal->achieved" /> / <x-currency :amount="$goal->target" /></div>
                        </div>
                    @empty
                        <x-empty-state icon="bi-bullseye" title="No goals assigned yet" />
                    @endforelse
                </div>
            </div>
        </div>
        <div class="col-lg-6">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-header bg-white fw-semibold"><i class="bi bi-chat-left-text me-1"></i> Unread Comments</div>
                <ul class="list-group list-group-flush">
                    @forelse ($recentNotes as $note)
                        <li class="list-group-item small">
                            <a href="{{ route('leads.show', $note->lead) }}" class="text-decoration-none fw-semibold">{{ $note->lead->company_name }}</a>
                            <div class="text-truncate">{{ $note->comment }}</div>
                            <div class="text-muted" style="font-size:0.72rem;">{{ $note->author?->name }} · {{ $note->created_at->diffForHumans() }}</div>
                        </li>
                    @empty
                        <li class="list-group-item"><x-empty-state icon="bi-chat-left-text" title="No new comments" /></li>
                    @endforelse
                </ul>
            </div>
        </div>
    </div>

    <div class="card border-0 shadow-sm">
        <div class="card-header bg-white fw-semibold d-flex justify-content-between">
            <span><i class="bi bi-journal-text me-1"></i> Daily Productivity Summary</span>
            @if (!$todaysSummarySubmitted)
                <a href="{{ route('daily-summaries.create') }}" class="btn btn-sm btn-primary">Submit Today's Summary</a>
            @else
                <span class="badge bg-success">Submitted today</span>
            @endif
        </div>
        <ul class="list-group list-group-flush">
            @forelse ($recentSummaries as $summary)
                <li class="list-group-item small">
                    <div class="fw-semibold">{{ $summary->summary_date->format('M d, Y') }}</div>
                    <div>{{ \Illuminate\Support\Str::limit($summary->achieved_today, 140) }}</div>
                </li>
            @empty
                <li class="list-group-item"><x-empty-state icon="bi-journal-text" title="No summaries submitted yet" /></li>
            @endforelse
        </ul>
    </div>
@endsection
