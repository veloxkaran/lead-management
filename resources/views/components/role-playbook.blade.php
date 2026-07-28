@props(['user', 'playbook', 'quote' => null])

@php
    $hour = now()->hour;
    $greeting = $hour < 12 ? 'Good morning' : ($hour < 17 ? 'Good afternoon' : 'Good evening');
@endphp

<div class="card border-0 shadow-sm mb-3">
    <div class="card-body">
        <div class="d-flex flex-wrap justify-content-between align-items-start gap-2 mb-3">
            <div>
                <h1 class="h5 fw-semibold mb-0">{{ $greeting }}, {{ explode(' ', $user->name)[0] }}.</h1>
                <span class="badge bg-primary-subtle text-primary">{{ $user->role->label() }}</span>
            </div>
        </div>

        @if ($playbook)
            <div class="row g-3">
                <div class="col-12">
                    <div class="rounded p-3 text-white" style="background: linear-gradient(135deg,#2456a6,#1b2430);">
                        <div class="small fw-semibold text-uppercase mb-2 opacity-75"><i class="bi bi-stars me-1"></i> Motivation</div>
                        <p class="small mb-2">{{ $playbook->motivation }}</p>
                        @if ($quote)
                            <hr class="opacity-25">
                            <p class="small mb-1 fst-italic opacity-75">{{ $quote['text'] }}</p>
                            <p class="small mb-0 opacity-50">— {{ $quote['author'] }}</p>
                        @endif
                    </div>
                </div>
            </div>
        @endif
    </div>
</div>
