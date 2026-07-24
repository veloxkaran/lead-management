<?php

namespace App\Settings;

use App\Models\Setting;

/**
 * Single source of truth for the WhatsApp integration's config keys — every
 * consumer (client, settings controller, webhook controller) depends on this
 * typed object instead of hand-typing `Setting::get('whatsapp_...')` string
 * literals, which previously had to stay in sync across three files by hand.
 */
class WhatsappSettings
{
    public const KEYS = [
        'whatsapp_enabled',
        'whatsapp_phone_number_id',
        'whatsapp_business_account_id',
        'whatsapp_access_token',
        'whatsapp_webhook_verify_token',
        'whatsapp_app_secret',
        'whatsapp_app_id',
    ];

    public function enabled(): bool
    {
        return Setting::get('whatsapp_enabled') === '1';
    }

    public function phoneNumberId(): ?string
    {
        return Setting::get('whatsapp_phone_number_id');
    }

    public function businessAccountId(): ?string
    {
        return Setting::get('whatsapp_business_account_id');
    }

    public function accessToken(): ?string
    {
        return Setting::get('whatsapp_access_token');
    }

    public function webhookVerifyToken(): ?string
    {
        return Setting::get('whatsapp_webhook_verify_token');
    }

    public function appSecret(): ?string
    {
        return Setting::get('whatsapp_app_secret');
    }

    /**
     * The Meta App ID (distinct from the WABA ID) — needed for app-level
     * Graph API calls, e.g. subscribing the WABA to webhook events via
     * `POST /{waba_id}/subscribed_apps`, or embedded signup.
     */
    public function appId(): ?string
    {
        return Setting::get('whatsapp_app_id');
    }

    public function apiVersion(): string
    {
        return config('services.whatsapp.api_version', 'v20.0');
    }

    /**
     * Centralizes Graph API URL construction so WhatsappClient doesn't
     * hand-assemble `https://graph.facebook.com/{version}/{phone_number_id}/...`
     * itself.
     */
    public function graphApiUrl(string $path): string
    {
        return "https://graph.facebook.com/{$this->apiVersion()}/{$this->phoneNumberId()}/{$path}";
    }

    public function businessAccountUrl(string $path): string
    {
        return "https://graph.facebook.com/{$this->apiVersion()}/{$this->businessAccountId()}/{$path}";
    }

    /**
     * @return array<string, string|null> raw key/value pairs, for the edit form.
     */
    public function all(): array
    {
        return collect(self::KEYS)->mapWithKeys(fn (string $key) => [$key => Setting::get($key)])->all();
    }

    /**
     * @param  array<string, mixed>  $credentials  validated non-boolean fields, keyed by setting name
     */
    public function save(array $credentials, bool $enabled): void
    {
        foreach ($credentials as $key => $value) {
            Setting::set($key, $value);
        }

        Setting::set('whatsapp_enabled', $enabled ? '1' : '0');
    }
}
