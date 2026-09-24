@extends('layouts.app')

@section('title', $announcement->title)

@section('content')
    <x-page-header :title="$announcement->title" icon="bi-broadcast">
        <x-slot:actions>
            <a href="{{ route('announcements.index') }}" class="btn btn-outline-secondary btn-sm">
                <i class="bi bi-arrow-left"></i> Back to Announcements
            </a>
        </x-slot:actions>
    </x-page-header>

    @can('viewDelivery', App\Models\Announcement::class)
        <div class="row g-3 mb-3">
            @foreach ([
                ['label' => 'Recipients', 'value' => $announcement->recipient_count, 'class' => 'text-body', 'status' => null],
                ['label' => 'Sent', 'value' => $announcement->sent_count, 'class' => 'text-success', 'status' => 'sent'],
                ['label' => 'Pending', 'value' => $announcement->pending_count, 'class' => 'text-warning', 'status' => 'pending'],
                ['label' => 'Failed', 'value' => $announcement->failed_count, 'class' => 'text-danger', 'status' => 'failed'],
            ] as $stat)
                <div class="col-6 col-md-3">
                    <a href="{{ route('announcements.show', [$announcement, 'status' => $stat['status']]) }}" class="card border-0 shadow-sm text-decoration-none h-100">
                        <div class="card-body">
                            <div class="small text-muted">{{ $stat['label'] }}</div>
                            <div class="fs-4 fw-semibold {{ $stat['class'] }}">{{ $stat['value'] }}</div>
                        </div>
                    </a>
                </div>
            @endforeach
        </div>
    @endcan

    <div class="row g-3">
        <div class="{{ $logs ? 'col-lg-7' : 'col-lg-9' }}">
            <div class="card border-0 shadow-sm mb-3">
                <div class="card-body">
                    <div class="small text-muted mb-3">
                        <i class="bi bi-person"></i> {{ $announcement->creator?->name ?? 'Team' }}
                        · <i class="bi bi-calendar3"></i> {{ $announcement->created_at->format('M d, Y g:i A') }}
                    </div>
                    <div>{!! nl2br(e($announcement->content)) !!}</div>

                    <div x-data="attachmentPreview()">
                        @foreach ($announcement->images as $image)
                            <a href="#" class="d-block mt-3" title="Click to enlarge"
                               @click.prevent="open(@js($image->url()), @js($image->original_name), 'image/*')">
                                <img src="{{ $image->url() }}" alt="{{ $image->original_name }}" class="img-fluid rounded">
                            </a>
                        @endforeach

                        @if ($announcement->documents->isNotEmpty())
                            <div class="mt-4">
                                <div class="small text-muted mb-2"><i class="bi bi-paperclip"></i> Attachments</div>
                                <ul class="list-group">
                                    @foreach ($announcement->documents as $document)
                                        <li class="list-group-item d-flex align-items-center gap-2 py-2">
                                            <i class="bi {{ $document->icon() }} fs-5"></i>
                                            <a href="#" class="flex-grow-1 small text-decoration-none text-truncate" style="min-width: 0;"
                                               @click.prevent="open(@js(route('announcement-documents.preview', $document)), @js($document->original_name), @js($document->mime_type))">
                                                {{ $document->original_name }}
                                            </a>
                                            <span class="small text-muted">{{ $document->humanSize() }}</span>
                                            <button type="button" class="btn btn-sm btn-outline-secondary" title="Preview"
                                                    @click="open(@js(route('announcement-documents.preview', $document)), @js($document->original_name), @js($document->mime_type))">
                                                <i class="bi bi-eye"></i>
                                            </button>
                                            <a href="{{ route('announcement-documents.download', $document) }}" class="btn btn-sm btn-outline-secondary" title="Download">
                                                <i class="bi bi-download"></i>
                                            </a>
                                        </li>
                                    @endforeach
                                </ul>
                            </div>
                        @endif

                        @include('announcements._preview_modal')
                    </div>
                </div>
            </div>

            @can('viewDelivery', App\Models\Announcement::class)
                <div class="card border-0 shadow-sm mb-3">
                    <div class="card-header bg-white fw-semibold">Email as Sent</div>
                    <div class="card-body">
                        <div class="small text-muted mb-2">Subject: <span class="text-body">{{ $preview['subject'] }}</span></div>
                        <div class="border rounded p-3 small">{!! nl2br(e($preview['body'])) !!}</div>
                        <div class="form-text">Shown with a blank contact name; each recipient's email is personalized.</div>
                    </div>
                </div>
            @endcan
        </div>

        @if ($logs)
            <div class="col-lg-5">
                <div class="card border-0 shadow-sm mb-3">
                    <div class="card-body">
                        <dl class="row small mb-0">
                            <dt class="col-4 text-muted">Audience</dt>
                            <dd class="col-8">
                                {{ $announcement->audience->label() }}
                                @if ($statusNames->isNotEmpty())
                                    <div class="text-muted">{{ $statusNames->join(', ') }}</div>
                                @endif
                            </dd>
                            <dt class="col-4 text-muted">Sent By</dt><dd class="col-8">{{ $announcement->creator?->name ?? '—' }}</dd>
                            <dt class="col-4 text-muted">Created</dt><dd class="col-8">{{ $announcement->created_at->format('M d, Y g:i A') }}</dd>
                        </dl>
                    </div>
                </div>

                <div class="card border-0 shadow-sm">
                    <div class="card-header bg-white fw-semibold">Recipients</div>
                    <div class="table-responsive">
                        <table class="table table-sm align-middle mb-0">
                            <tbody>
                                @forelse ($logs as $log)
                                    <tr>
                                        <td class="small"><a href="{{ route('email-logs.show', $log) }}">{{ $log->to_email }}</a></td>
                                        <td class="text-end"><x-status-badge :status="$log->status" /></td>
                                    </tr>
                                @empty
                                    <tr><td class="small text-muted text-center py-3">No recipients in this view.</td></tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                    @if ($logs->hasPages())
                        <div class="card-footer bg-white">{{ $logs->links() }}</div>
                    @endif
                </div>
            </div>
        @endif
    </div>
@endsection
