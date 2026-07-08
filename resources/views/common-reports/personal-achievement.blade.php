@extends('layouts.app')

@section('title', 'Personal Achievement')

@section('content')
    <x-page-header title="Personal Achievement" icon="bi-person-check" :subtitle="$user->name" />

    <div class="card border-0 shadow-sm">
        <div class="card-body">
            @forelse ($goals as $goal)
                <div class="mb-3">
                    <div class="d-flex justify-content-between small mb-1"><span>{{ $goal->title }}</span><span><x-currency :amount="$goal->achieved" /> / <x-currency :amount="$goal->target" /> ({{ $goal->achievementPercentage() }}%)</span></div>
                    <div class="progress" style="height: 8px;"><div class="progress-bar" style="width: {{ $goal->achievementPercentage() }}%"></div></div>
                </div>
            @empty
                <x-empty-state icon="bi-person-check" title="No individual goals assigned yet" />
            @endforelse
        </div>
    </div>
@endsection
