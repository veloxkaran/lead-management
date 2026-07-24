@extends('layouts.app')

@section('title', 'WhatsApp Settings')

@section('content')
    <x-page-header title="WhatsApp Settings" icon="bi-whatsapp" subtitle="Configure the Meta WhatsApp Cloud API integration used by the WhatsApp inbox." />

    <form method="POST" action="{{ route('whatsapp-settings.update') }}">
        @csrf
        @method('PUT')

        <x-settings-card title="Meta Cloud API Credentials" icon="bi-whatsapp">
            <div class="row g-3">
                <div class="col-12">
                    <x-settings-toggle name="whatsapp_enabled" label="WhatsApp integration enabled" :checked="$settings['whatsapp_enabled'] === '1'" />
                </div>
                <div class="col-md-6">
                    <label class="form-label small fw-semibold">Phone Number ID</label>
                    <input type="text" name="whatsapp_phone_number_id" value="{{ old('whatsapp_phone_number_id', $settings['whatsapp_phone_number_id']) }}" class="form-control">
                </div>
                <div class="col-md-6">
                    <label class="form-label small fw-semibold">WhatsApp Business Account ID</label>
                    <input type="text" name="whatsapp_business_account_id" value="{{ old('whatsapp_business_account_id', $settings['whatsapp_business_account_id']) }}" class="form-control">
                </div>
                <div class="col-md-6">
                    <label class="form-label small fw-semibold">Meta App ID</label>
                    <input type="text" name="whatsapp_app_id" value="{{ old('whatsapp_app_id', $settings['whatsapp_app_id']) }}" class="form-control">
                    <div class="form-text">The Facebook App ID from the Meta App Dashboard (distinct from the WABA ID above).</div>
                </div>
                <div class="col-12">
                    <label class="form-label small fw-semibold">Access Token</label>
                    <input type="password" name="whatsapp_access_token" value="{{ old('whatsapp_access_token', $settings['whatsapp_access_token']) }}" class="form-control" autocomplete="new-password">
                    <div class="form-text">A permanent (System User) access token from the Meta App Dashboard.</div>
                </div>
                <div class="col-md-6">
                    <label class="form-label small fw-semibold">Webhook Verify Token</label>
                    <input type="text" name="whatsapp_webhook_verify_token" value="{{ old('whatsapp_webhook_verify_token', $settings['whatsapp_webhook_verify_token']) }}" class="form-control">
                </div>
                <div class="col-md-6">
                    <label class="form-label small fw-semibold">App Secret</label>
                    <input type="password" name="whatsapp_app_secret" value="{{ old('whatsapp_app_secret', $settings['whatsapp_app_secret']) }}" class="form-control" autocomplete="new-password">
                    <div class="form-text">Used to verify the signature of incoming webhook requests.</div>
                </div>
            </div>
        </x-settings-card>

        <x-settings-card title="Webhook Setup" icon="bi-link-45deg">
            <p class="small text-muted">Enter these into your Meta App Dashboard under WhatsApp &rsaquo; Configuration &rsaquo; Webhooks.</p>
            <label class="form-label small fw-semibold">Callback URL</label>
            <input type="text" class="form-control form-control-sm mb-3" value="{{ route('whatsapp.webhook.handle') }}" readonly onclick="this.select()">
            <label class="form-label small fw-semibold">Verify Token</label>
            <input type="text" class="form-control form-control-sm" value="{{ $settings['whatsapp_webhook_verify_token'] }}" readonly onclick="this.select()">
        </x-settings-card>

        <button class="btn btn-primary"><i class="bi bi-check-lg"></i> Save Settings</button>
    </form>

    <x-settings-card title="Send Test Message" icon="bi-send" class="mt-3">
        <form method="POST" action="{{ route('whatsapp-settings.test') }}" class="d-flex gap-2">
            @csrf
            <input type="text" name="test_number" class="form-control" placeholder="e.g. 15551234567" required>
            <button class="btn btn-outline-primary text-nowrap"><i class="bi bi-send"></i> Send Test</button>
        </form>
    </x-settings-card>
@endsection
