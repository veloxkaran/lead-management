@extends('layouts.app')

@section('title', 'Goal vs Achievement')

@section('content')
    <x-page-header title="Goal vs Achievement" icon="bi-bullseye" />

    @if ($goals->isNotEmpty())
        <div class="card border-0 shadow-sm mb-3">
            <div class="card-header bg-white fw-semibold">Target vs Achieved</div>
            <div class="card-body">
                <canvas id="orgGoalChart" height="90"></canvas>
            </div>
        </div>
    @endif

    <div class="card border-0 shadow-sm">
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead class="table-light"><tr><th>Title</th><th>Category</th><th>Target</th><th>Achieved</th><th>Progress</th></tr></thead>
                <tbody>
                    @forelse ($goals as $goal)
                        <tr>
                            <td><a href="{{ route('goals.show', $goal) }}" class="text-decoration-none">{{ $goal->title }}</a></td>
                            <td class="small">{{ $goal->category->label() }}</td>
                            <td><x-currency :amount="$goal->target" /></td>
                            <td><x-currency :amount="$goal->achieved" /></td>
                            <td style="min-width: 160px;">
                                <div class="progress" style="height: 8px;"><div class="progress-bar" style="width: {{ $goal->achievementPercentage() }}%"></div></div>
                                <span class="small text-muted">{{ $goal->achievementPercentage() }}%</span>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="5"><x-empty-state icon="bi-bullseye" title="No goals to display" /></td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    @if ($goals->isNotEmpty())
        @push('scripts')
            <script>
                document.addEventListener('DOMContentLoaded', () => {
                    new Chart(document.getElementById('orgGoalChart'), {
                        type: 'bar',
                        data: {
                            labels: @json($goals->pluck('title')->values()),
                            datasets: [
                                {
                                    label: 'Target ({{ \App\Support\Currency::SYMBOL }})',
                                    data: @json($goals->pluck('target')->values()),
                                    backgroundColor: '#c9d6e8',
                                },
                                {
                                    label: 'Achieved ({{ \App\Support\Currency::SYMBOL }})',
                                    data: @json($goals->pluck('achieved')->values()),
                                    backgroundColor: '#2456a6',
                                },
                            ],
                        },
                        options: {
                            scales: { y: { beginAtZero: true } },
                            plugins: { legend: { position: 'bottom' } },
                        },
                    });
                });
            </script>
        @endpush
    @endif
@endsection
