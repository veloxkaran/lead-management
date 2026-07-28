@extends('layouts.app')

@section('title', 'Raw Data — '.$rawData->contact_person)

@section('content')
    <x-page-header title="{{ $rawData->contact_person }}" icon="bi-inbox" :subtitle="$rawData->phone">
        <x-slot:actions>
            @can('update', $rawData)
                @if ($rawData->isNew())
                    <button type="button" class="btn btn-success btn-sm" data-bs-toggle="modal" data-bs-target="#convertToLeadModal">
                        <i class="bi bi-arrow-right-circle"></i> Convert to Lead
                    </button>
                    <form method="POST" action="{{ route('raw-data.mark-not-valid', $rawData) }}" class="d-inline" data-confirm-delete data-confirm-title="Mark as not valid?" data-confirm-text="This cannot be undone." data-confirm-button-text="Mark Not Valid">
                        @csrf
                        <button type="submit" class="btn btn-outline-danger btn-sm"><i class="bi bi-x-circle"></i> Mark Not Valid</button>
                    </form>
                @endif
            @endcan
            <a href="{{ route('raw-data.index') }}" class="btn btn-outline-secondary btn-sm">
                <i class="bi bi-arrow-left"></i> Back
            </a>
        </x-slot:actions>
    </x-page-header>

    <div class="card border-0 shadow-sm">
        <div class="card-body">
            <div class="row g-3">
                <div class="col-md-3">
                    <div class="small text-muted">Contact Person</div>
                    <div class="small fw-semibold">{{ $rawData->contact_person }}</div>
                </div>
                <div class="col-md-3">
                    <div class="small text-muted">Phone</div>
                    <div class="small fw-semibold">{{ $rawData->phone }}</div>
                </div>
                <div class="col-md-3">
                    <div class="small text-muted">Status</div>
                    <x-status-badge :status="$rawData->status" />
                </div>
                <div class="col-md-3">
                    <div class="small text-muted">Added By</div>
                    <div class="small fw-semibold">{{ $rawData->creator?->name ?? 'Unknown' }}</div>
                </div>
                @if ($rawData->convertedLead)
                    <div class="col-12">
                        <div class="small text-muted">Converted Lead</div>
                        <a href="{{ route('leads.show', $rawData->convertedLead) }}" class="small fw-semibold">{{ $rawData->convertedLead->company_name }}</a>
                    </div>
                @endif
                <div class="col-12">
                    <div class="small text-muted">Added on {{ $rawData->created_at->format('M d, Y g:i A') }}</div>
                </div>
            </div>
        </div>
    </div>

    <div class="card border-0 shadow-sm mt-3">
        <div class="card-header bg-white fw-semibold">
            <i class="bi bi-chat-left-text"></i> Comments ({{ $rawData->comments->count() }})
        </div>
        <div class="card-body">
            <form method="POST" action="{{ route('raw-data.comments.store', $rawData) }}" class="mb-3">
                @csrf
                <textarea name="comment" rows="2" class="form-control form-control-sm" placeholder="Add a comment..." required></textarea>
                @error('comment')<div class="text-danger small mt-1">{{ $message }}</div>@enderror
                <button type="submit" class="btn btn-sm btn-primary mt-2"><i class="bi bi-send"></i> Post Comment</button>
            </form>

            @forelse ($rawData->comments as $comment)
                <div class="border-bottom pb-2 mb-2">
                    <div>
                        <span class="fw-semibold small">{{ $comment->author?->name ?? 'Unknown' }}</span>
                        <span class="text-muted small">{{ $comment->created_at->format('M d, Y g:i A') }}</span>
                    </div>
                    <p class="small mb-0">{{ $comment->comment }}</p>
                </div>
            @empty
                <p class="text-muted small mb-0">No comments yet.</p>
            @endforelse
        </div>
    </div>

    @can('update', $rawData)
        @if ($rawData->isNew())
            <div class="modal fade" id="convertToLeadModal" tabindex="-1">
                <div class="modal-dialog">
                    <form method="POST" action="{{ route('raw-data.convert', $rawData) }}" class="modal-content">
                        @csrf
                        <div class="modal-header">
                            <h5 class="modal-title">Convert to Lead</h5>
                            <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                        </div>
                        <div class="modal-body">
                            <p class="small text-muted">Contact Person and Phone carry over from this entry — fill in the rest to create the Lead.</p>
                            <div class="mb-3">
                                <label class="form-label small fw-semibold">Company Name</label>
                                <input type="text" name="company_name" class="form-control" value="{{ old('company_name') }}" required>
                                @error('company_name')<div class="text-danger small mt-1">{{ $message }}</div>@enderror
                            </div>
                            <div class="mb-3">
                                <label class="form-label small fw-semibold">Contact Person</label>
                                <input type="text" name="contact_person" class="form-control" value="{{ old('contact_person', $rawData->contact_person) }}" required>
                                @error('contact_person')<div class="text-danger small mt-1">{{ $message }}</div>@enderror
                            </div>
                            <div class="mb-3">
                                <label class="form-label small fw-semibold">Phone</label>
                                <input type="text" name="phone" class="form-control" value="{{ old('phone', $rawData->phone) }}">
                                @error('phone')<div class="text-danger small mt-1">{{ $message }}</div>@enderror
                            </div>
                            <div class="mb-3">
                                <label class="form-label small fw-semibold">Email (optional)</label>
                                <input type="email" name="email" class="form-control" value="{{ old('email') }}">
                                @error('email')<div class="text-danger small mt-1">{{ $message }}</div>@enderror
                            </div>
                        </div>
                        <div class="modal-footer">
                            <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
                            <button type="submit" class="btn btn-success">Convert to Lead</button>
                        </div>
                    </form>
                </div>
            </div>
        @endif
    @endcan
@endsection
