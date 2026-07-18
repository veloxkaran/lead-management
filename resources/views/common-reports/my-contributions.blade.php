@extends('layouts.app')

@section('title', 'My Contributions')

@section('content')
    <x-page-header title="My Contributions" icon="bi-person-check" :subtitle="$user->name" />

    <div class="card border-0 shadow-sm mb-3">
        <div class="card-body">
            <div class="text-muted small">Total Contributed</div>
            <div class="fs-4 fw-semibold"><x-currency :amount="$total" /></div>
        </div>
    </div>

    <div class="card border-0 shadow-sm">
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead class="table-light">
                    <tr>
                        <th>Goal</th>
                        <th>Contribution Type</th>
                        <th>Client Name</th>
                        <th>Value</th>
                        <th>Date</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($contributions as $contribution)
                        <tr>
                            <td><a href="{{ route('goals.show', $contribution->goal) }}" class="text-decoration-none">{{ $contribution->goal->title }}</a></td>
                            <td class="small">{{ $contribution->contribution_type->label() }}</td>
                            <td class="small">{{ $contribution->source?->lead?->company_name ?? '—' }}</td>
                            <td><x-currency :amount="$contribution->amount" /></td>
                            <td class="small text-muted">{{ $contribution->contributed_at->format('M d, Y') }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="5"><x-empty-state icon="bi-person-check" title="No contributions yet" description="Close a deal to start contributing toward organizational goals." /></td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        @if ($contributions->hasPages())
            <div class="card-footer bg-white">{{ $contributions->links() }}</div>
        @endif
    </div>
@endsection
