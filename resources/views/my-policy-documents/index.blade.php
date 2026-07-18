@extends('layouts.app')

@section('title', 'My SOPs & Job Descriptions')

@section('content')
    <x-page-header title="My SOPs & Job Descriptions" icon="bi-journal-check" subtitle="Everything assigned to you — reopen and review anytime." />

    <div class="card border-0 shadow-sm mb-3">
        <div class="card-header bg-white fw-semibold">SOPs</div>
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead class="table-light">
                    <tr>
                        <th>Title</th>
                        <th>Status</th>
                        <th>Version</th>
                        <th>Last Viewed</th>
                        <th>Last Acknowledged</th>
                        <th class="text-end">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($sops as $row)
                        <tr>
                            <td class="fw-semibold">{{ $row->document->title }}</td>
                            <td>
                                @if ($row->is_read)
                                    <span class="badge bg-success-subtle text-success-emphasis">Read</span>
                                @else
                                    <span class="badge bg-danger-subtle text-danger-emphasis">Unread</span>
                                @endif
                            </td>
                            <td>{{ $row->version->version }}</td>
                            <td>{{ $row->last_viewed?->format('M d, Y H:i') ?? '—' }}</td>
                            <td>{{ $row->last_acknowledged?->format('M d, Y H:i') ?? '—' }}</td>
                            <td class="text-end">
                                <a href="{{ route('my-policy-documents.show', $row->version) }}" class="btn btn-sm btn-outline-secondary"><i class="bi bi-eye"></i> Review</a>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="6"><x-empty-state icon="bi-journal-check" title="No SOPs assigned" description="Nothing has been published yet." /></td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    <div class="card border-0 shadow-sm">
        <div class="card-header bg-white fw-semibold">Individual Job Descriptions</div>
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead class="table-light">
                    <tr>
                        <th>Title</th>
                        <th>Status</th>
                        <th>Assigned By</th>
                        <th>Version</th>
                        <th>Last Updated</th>
                        <th class="text-end">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($individualJds as $row)
                        <tr>
                            <td class="fw-semibold">{{ $row->document->title }}</td>
                            <td>
                                @if ($row->is_read)
                                    <span class="badge bg-success-subtle text-success-emphasis">Read</span>
                                @else
                                    <span class="badge bg-danger-subtle text-danger-emphasis">Unread</span>
                                @endif
                            </td>
                            <td>{{ $row->document->creator?->name ?? '—' }}</td>
                            <td>{{ $row->version->version }}</td>
                            <td>{{ $row->version->published_at->format('M d, Y') }}</td>
                            <td class="text-end">
                                <a href="{{ route('my-policy-documents.show', $row->version) }}" class="btn btn-sm btn-outline-secondary"><i class="bi bi-eye"></i> Review</a>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="6"><x-empty-state icon="bi-person-badge" title="No job description assigned" description="Nothing has been assigned to you individually yet." /></td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
@endsection
