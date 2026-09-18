@extends('layouts.app')

@section('title', 'Email Templates')

@section('content')
    <x-page-header title="Email Templates" icon="bi-file-earmark-text" subtitle="Client-facing emails sent automatically when a requirement or support ticket is created or changes status." />

    <div class="card border-0 shadow-sm">
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead class="table-light">
                    <tr>
                        <th>Template</th>
                        <th>Subject</th>
                        <th>Last Updated</th>
                        <th class="text-end">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($templates as $template)
                        <tr>
                            <td class="fw-semibold small">{{ $template->name }}</td>
                            <td class="small text-muted">{{ $template->subject }}</td>
                            <td class="small text-muted">
                                @if ($template->updatedBy)
                                    {{ $template->updated_at->format('M d, Y g:i A') }} by {{ $template->updatedBy->name }}
                                @else
                                    &mdash;
                                @endif
                            </td>
                            <td class="text-end">
                                <a href="{{ route('email-templates.preview', $template) }}" target="_blank" rel="noopener" class="btn btn-sm btn-outline-secondary"><i class="bi bi-eye"></i> Preview</a>
                                <a href="{{ route('email-templates.edit', $template) }}" class="btn btn-sm btn-outline-secondary"><i class="bi bi-pencil"></i> Edit</a>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="4">
                                <x-empty-state icon="bi-file-earmark-text" title="No email templates found" />
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
@endsection
