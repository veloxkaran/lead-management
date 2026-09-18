@extends('layouts.app')

@section('title', 'Edit '.$template->name)

@section('content')
    <x-page-header title="Edit Email Template" icon="bi-file-earmark-text" :subtitle="$template->name">
        <x-slot:actions>
            <a href="{{ route('email-templates.preview', $template) }}" target="_blank" rel="noopener" class="btn btn-outline-secondary btn-sm">
                <i class="bi bi-eye"></i> Preview
            </a>
            <a href="{{ route('email-templates.index') }}" class="btn btn-outline-secondary btn-sm">
                <i class="bi bi-arrow-left"></i> Back to Templates
            </a>
        </x-slot:actions>
    </x-page-header>

    <div class="row g-3">
        <div class="col-lg-8">
            <div class="card border-0 shadow-sm">
                <div class="card-body">
                    <form method="POST" action="{{ route('email-templates.update', $template) }}">
                        @csrf
                        @method('PUT')
                        <div class="mb-3">
                            <label class="form-label small fw-semibold">Subject</label>
                            <input type="text" name="subject" value="{{ old('subject', $template->subject) }}" class="form-control" required>
                        </div>
                        <div class="mb-3">
                            <label class="form-label small fw-semibold">Body</label>
                            <textarea name="body" rows="14" class="form-control" required>{{ old('body', $template->body) }}</textarea>
                            <div class="form-text">Plain text — line breaks are preserved. Use the merge fields on the right; anything left blank on send is simply removed.</div>
                        </div>
                        <button type="submit" class="btn btn-primary"><i class="bi bi-check-lg"></i> Save Template</button>
                    </form>
                </div>
            </div>
        </div>
        <div class="col-lg-4">
            <div class="card border-0 shadow-sm">
                <div class="card-header bg-white fw-semibold">Available Merge Fields</div>
                <ul class="list-group list-group-flush">
                    @foreach ($variables as $variable)
                        <li class="list-group-item small"><code>{{ $variable }}</code></li>
                    @endforeach
                </ul>
            </div>
        </div>
    </div>
@endsection
