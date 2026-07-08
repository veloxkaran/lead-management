@extends('layouts.app')

@section('title', 'Team Achievement')

@section('content')
    <x-page-header title="Team Achievement" icon="bi-people" />

    @forelse ($groupedGoals as $teamName => $goals)
        <div class="card border-0 shadow-sm mb-3">
            <div class="card-header bg-white fw-semibold">{{ $teamName }}</div>
            <div class="card-body">
                @foreach ($goals as $goal)
                    <div class="mb-3">
                        <div class="d-flex justify-content-between small mb-1"><span>{{ $goal->title }}</span><span>{{ $goal->achievementPercentage() }}%</span></div>
                        <div class="progress" style="height: 8px;"><div class="progress-bar bg-info" style="width: {{ $goal->achievementPercentage() }}%"></div></div>
                    </div>
                @endforeach
            </div>
        </div>
    @empty
        <x-empty-state icon="bi-people" title="No team goals to display" />
    @endforelse
@endsection
