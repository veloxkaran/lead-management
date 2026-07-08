@extends('layouts.app')

@section('title', 'Settings')

@section('content')
    <x-page-header title="Settings" icon="bi-gear" subtitle="System-wide configuration for integrations and notifications." />

    <form method="POST" action="{{ route('settings.update') }}">
        @csrf
        @method('PUT')

        <div class="card border-0 shadow-sm mb-3">
            <div class="card-header bg-white fw-semibold"><i class="bi bi-slack me-1"></i> Slack Webhook</div>
            <div class="card-body">
                <label class="form-label small fw-semibold">Slack Incoming Webhook URL</label>
                <input type="url" name="slack_webhook_url" value="{{ old('slack_webhook_url', $settings['slack_webhook_url']) }}" class="form-control" placeholder="https://hooks.slack.com/services/...">
                <div class="form-text">Used to notify Slack when requirements are created or updated, and for the daily target-vs-achievement summary.</div>
            </div>
        </div>

        <div class="card border-0 shadow-sm mb-3">
            <div class="card-header bg-white fw-semibold"><i class="bi bi-google me-1"></i> Google Drive & Meet</div>
            <div class="card-body row g-3">
                <div class="col-md-6">
                    <label class="form-label small fw-semibold">Google Drive Folder Link</label>
                    <input type="url" name="google_drive_folder_link" value="{{ old('google_drive_folder_link', $settings['google_drive_folder_link']) }}" class="form-control">
                </div>
                <div class="col-md-6">
                    <label class="form-label small fw-semibold">Default Google Meet Link</label>
                    <input type="url" name="google_meet_default_link" value="{{ old('google_meet_default_link', $settings['google_meet_default_link']) }}" class="form-control">
                </div>
            </div>
        </div>

        <div class="card border-0 shadow-sm mb-3">
            <div class="card-header bg-white fw-semibold"><i class="bi bi-building me-1"></i> Company Information</div>
            <div class="card-body row g-3">
                <div class="col-md-6">
                    <label class="form-label small fw-semibold">Company Name</label>
                    <input type="text" name="company_name" value="{{ old('company_name', $settings['company_name']) }}" class="form-control">
                </div>
                <div class="col-md-6">
                    <label class="form-label small fw-semibold">Company Email</label>
                    <input type="email" name="company_email" value="{{ old('company_email', $settings['company_email']) }}" class="form-control">
                </div>
                <div class="col-md-6">
                    <label class="form-label small fw-semibold">Company Phone</label>
                    <input type="text" name="company_phone" value="{{ old('company_phone', $settings['company_phone']) }}" class="form-control">
                </div>
                <div class="col-md-6">
                    <label class="form-label small fw-semibold">Company Address</label>
                    <input type="text" name="company_address" value="{{ old('company_address', $settings['company_address']) }}" class="form-control">
                </div>
            </div>
        </div>

        <div class="card border-0 shadow-sm mb-3">
            <div class="card-header bg-white fw-semibold"><i class="bi bi-bell me-1"></i> Notifications & Dashboard</div>
            <div class="card-body row g-3">
                <div class="col-md-4 form-check form-switch">
                    <input class="form-check-input" type="checkbox" role="switch" name="notifications_email_enabled" value="1" @checked($settings['notifications_email_enabled'] === '1')>
                    <label class="form-check-label small">Email notifications enabled</label>
                </div>
                <div class="col-md-4 form-check form-switch">
                    <input class="form-check-input" type="checkbox" role="switch" name="notifications_web_enabled" value="1" @checked($settings['notifications_web_enabled'] === '1')>
                    <label class="form-check-label small">Web notifications enabled</label>
                </div>
                <div class="col-md-4 form-check form-switch">
                    <input class="form-check-input" type="checkbox" role="switch" name="dashboard_show_motivation_quote" value="1" @checked($settings['dashboard_show_motivation_quote'] !== '0')>
                    <label class="form-check-label small">Show motivation quote widget</label>
                </div>
                <div class="col-md-6">
                    <label class="form-label small fw-semibold">Motivation Quote API URL</label>
                    <input type="url" name="motivation_quote_api_url" value="{{ old('motivation_quote_api_url', $settings['motivation_quote_api_url']) }}" class="form-control" placeholder="https://zenquotes.io/api/random">
                </div>
            </div>
        </div>

        <button class="btn btn-primary"><i class="bi bi-check-lg"></i> Save Settings</button>
    </form>
@endsection
