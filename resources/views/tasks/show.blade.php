@extends('layouts.app')

@section('title', $task->title)

@section('content')
    <x-page-header :title="$task->title" icon="bi-list-task" :subtitle="$task->module->label()">
        <x-slot:actions>
            @can('update', $task)
                <a href="{{ route('tasks.edit', $task) }}" class="btn btn-outline-secondary btn-sm"><i class="bi bi-pencil"></i> Edit</a>
            @endcan
            @can('delete', $task)
                <form method="POST" action="{{ route('tasks.destroy', $task) }}" data-confirm-delete data-confirm-title="Delete this task?">
                    @csrf @method('DELETE')
                    <button class="btn btn-outline-danger btn-sm"><i class="bi bi-trash"></i> Delete</button>
                </form>
            @endcan
        </x-slot:actions>
    </x-page-header>

    <div class="row g-3">
        <div class="col-lg-4">
            <div class="card border-0 shadow-sm mb-3">
                <div class="card-body">
                    <div class="d-flex gap-2 mb-3">
                        <x-status-badge :status="$task->status" />
                        <x-status-badge :status="$task->priority" />
                        @if ($task->isOverdue())
                            <span class="badge bg-danger">Overdue</span>
                        @endif
                    </div>
                    <div class="progress mb-3" style="height: 6px;">
                        <div class="progress-bar" role="progressbar" style="width: {{ $task->completion_percentage }}%" aria-valuenow="{{ $task->completion_percentage }}" aria-valuemin="0" aria-valuemax="100"></div>
                    </div>
                    <dl class="row small mb-0">
                        <dt class="col-5 text-muted">Client</dt>
                        <dd class="col-7">
                            @if ($task->lead)
                                <a href="{{ route('leads.show', $task->lead) }}">{{ $task->lead->company_name }}</a>
                            @else
                                —
                            @endif
                        </dd>
                        <dt class="col-5 text-muted">Created By</dt><dd class="col-7">{{ $task->creator?->name ?? '—' }}</dd>
                        <dt class="col-5 text-muted">Assigned By</dt><dd class="col-7">{{ $task->assignedBy?->name ?? '—' }}</dd>
                        <dt class="col-5 text-muted">Assigned To</dt><dd class="col-7">{{ $task->assignee?->name ?? '—' }}</dd>
                        <dt class="col-5 text-muted">Reporting Manager</dt><dd class="col-7">{{ $task->assigneeManager()?->name ?? '—' }}</dd>
                        <dt class="col-5 text-muted">Due Date</dt><dd class="col-7">{{ $task->due_date?->format('M d, Y') ?? '—' }}</dd>
                        <dt class="col-5 text-muted">Estimated Hours</dt><dd class="col-7">{{ $task->estimated_hours ?? '—' }}</dd>
                        <dt class="col-5 text-muted">Actual Hours</dt><dd class="col-7">{{ $task->actual_hours ?? '—' }}</dd>
                        <dt class="col-5 text-muted">Completed On</dt><dd class="col-7">{{ $task->completed_at?->format('M d, Y') ?? '—' }}</dd>
                    </dl>
                    @if ($task->description)
                        <hr>
                        <h6 class="fw-semibold small">Description</h6>
                        <p class="small text-muted mb-0">{{ $task->description }}</p>
                    @endif
                </div>
            </div>
        </div>

        <div class="col-lg-8">
            <ul class="nav nav-tabs" id="taskTabs" role="tablist">
                <li class="nav-item"><button class="nav-link active" data-bs-toggle="tab" data-bs-target="#checklist" type="button">Checklist</button></li>
                <li class="nav-item"><button class="nav-link" data-bs-toggle="tab" data-bs-target="#comments" type="button">Comments</button></li>
                <li class="nav-item"><button class="nav-link" data-bs-toggle="tab" data-bs-target="#activity" type="button">Activity</button></li>
            </ul>
            <div class="tab-content bg-white border border-top-0 rounded-bottom p-3 shadow-sm">
                <div class="tab-pane fade show active" id="checklist">
                    @include('tasks.partials._checklist')
                </div>
                <div class="tab-pane fade" id="comments">
                    @include('tasks.partials._comments')
                </div>
                <div class="tab-pane fade" id="activity">
                    @include('tasks.partials._activity')
                </div>
            </div>
        </div>
    </div>
@endsection
