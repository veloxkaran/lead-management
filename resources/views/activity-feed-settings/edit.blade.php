@extends('layouts.app')

@section('title', 'Activity Feed Settings')

@section('content')
    <x-page-header title="Activity Feed Settings" icon="bi-activity" subtitle="Configure the cross-department Activity Feed widget shown on every dashboard." />

    <form method="POST" action="{{ route('activity-feed-settings.update') }}">
        @csrf
        @method('PUT')

        <x-settings-card title="General" icon="bi-activity">
            <div class="row g-3">
                <div class="col-12">
                    <x-settings-toggle name="activity_feed_enabled" label="Activity Feed widget enabled on dashboards" :checked="$settings['activity_feed_enabled'] === '1'" />
                </div>
                <div class="col-md-6">
                    <label class="form-label small fw-semibold">Auto-Refresh Interval (seconds)</label>
                    <input type="number" min="5" max="300" name="activity_feed_refresh_seconds" value="{{ old('activity_feed_refresh_seconds', $settings['activity_feed_refresh_seconds'] ?: 10) }}" class="form-control" required>
                </div>
                <div class="col-md-6">
                    <label class="form-label small fw-semibold">Activities Per Page</label>
                    <input type="number" min="1" max="50" name="activity_feed_per_page" value="{{ old('activity_feed_per_page', $settings['activity_feed_per_page'] ?: 10) }}" class="form-control" required>
                </div>
            </div>
        </x-settings-card>

        <x-settings-card title="Modules Shown in the Feed" icon="bi-toggles">
            <div class="row g-2">
                @foreach ($modules as $definition)
                    <div class="col-md-4 form-check">
                        <input class="form-check-input" type="checkbox" name="modules[]" value="{{ $definition->module->value }}" id="module-{{ $definition->module->value }}" @checked(in_array($definition->module->value, $enabledModules, true))>
                        <label class="form-check-label small" for="module-{{ $definition->module->value }}">
                            <i class="bi {{ $definition->icon }}"></i> {{ $definition->label }}
                        </label>
                    </div>
                @endforeach
            </div>
        </x-settings-card>

        <button class="btn btn-primary"><i class="bi bi-check-lg"></i> Save Settings</button>
    </form>
@endsection
