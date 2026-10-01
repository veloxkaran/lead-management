<?php

namespace App\Support;

use App\Enums\CampaignChannel;
use App\Models\Setting;
use Illuminate\Contracts\Encryption\DecryptException;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Str;

/**
 * Campaign Setup values (Administration → Campaign Setup), stored in the
 * settings table — except the email sender, which comes from .env
 * (CAMPAIGN_MAIL_*). The SMS API key is kept encrypted with APP_KEY;
 * rotating APP_KEY makes it unreadable, and it then has to be entered again.
 */
class CampaignSettings
{
    /** Plain-text email settings, in form order. The signature is saved separately (sanitized HTML). The sender comes from .env (see envMail()). */
    public const EMAIL_KEYS = [
        'campaign_footer', 'campaign_track_opens',
        'campaign_batch_size', 'campaign_emails_per_minute', 'campaign_batch_pause_minutes',
    ];

    /** Plain-text SMS gateway settings, in form order. */
    public const SMS_KEYS = [
        'sms_endpoint', 'sms_method', 'sms_format', 'sms_auth_mode', 'sms_auth_name',
        'sms_to_param', 'sms_message_param', 'sms_sender_param', 'sms_sender_id',
        'sms_extra_params', 'sms_country_prefix', 'sms_success_path', 'sms_success_value',
        'sms_message_id_path', 'sms_dlr_id_param', 'sms_dlr_status_param',
        'sms_dlr_delivered_values', 'sms_dlr_failed_values',
    ];

    /**
     * Starting points for gateways commonly used in Nepal. Parameter names
     * follow each provider's public API docs — check them against your
     * account's docs, then adjust any field.
     */
    public const SMS_PRESETS = [
        'sparrow' => [
            'label' => 'Sparrow SMS',
            'sms_endpoint' => 'https://api.sparrowsms.com/v2/sms/',
            'sms_method' => 'POST', 'sms_format' => 'form',
            'sms_auth_mode' => 'param', 'sms_auth_name' => 'token',
            'sms_to_param' => 'to', 'sms_message_param' => 'text', 'sms_sender_param' => 'from',
            'sms_success_path' => 'response_code', 'sms_success_value' => '200',
        ],
        'aakash' => [
            'label' => 'Aakash SMS',
            'sms_endpoint' => 'https://sms.aakashsms.com/sms/v3/send',
            'sms_method' => 'POST', 'sms_format' => 'form',
            'sms_auth_mode' => 'param', 'sms_auth_name' => 'auth_token',
            'sms_to_param' => 'to', 'sms_message_param' => 'text', 'sms_sender_param' => '',
            'sms_success_path' => 'error', 'sms_success_value' => 'false',
        ],
    ];

    public function get(string $key, ?string $default = null): ?string
    {
        $value = Setting::get($key);

        return $value === null || $value === '' ? $default : (string) $value;
    }

    public function set(string $key, ?string $value): void
    {
        Setting::set($key, $value);
    }

    public function smsApiKey(): ?string
    {
        return $this->decrypt('sms_api_key');
    }

    public function setSmsApiKey(?string $key): void
    {
        $this->set('sms_api_key', $key === null || $key === '' ? null : Crypt::encryptString($key));
    }

    public function hasSmsApiKey(): bool
    {
        return $this->smsApiKey() !== null;
    }

    public function smsConfigured(): bool
    {
        return $this->get('sms_endpoint') !== null && $this->get('sms_to_param') !== null && $this->get('sms_message_param') !== null;
    }

    /**
     * Secret path segment of the delivery-report URL, generated on first use.
     */
    public function dlrSecret(): string
    {
        $secret = $this->get('sms_dlr_secret');

        if ($secret === null) {
            $secret = Str::random(40);
            $this->set('sms_dlr_secret', $secret);
        }

        return $secret;
    }

    /**
     * The campaign login from .env (config campaigns.mail), or null when
     * CAMPAIGN_MAIL_HOST isn't set.
     *
     * @return array{host: string, port: int, username: ?string, password: ?string, encryption: ?string, from_address: ?string, from_name: ?string, reply_to: ?string}|null
     */
    public function envMail(): ?array
    {
        $mail = (array) config('campaigns.mail');

        return filled($mail['host'] ?? null) ? $mail : null;
    }

    /**
     * Sanitized signature HTML (from the rich-text editor), or null when
     * there's nothing in it. An image on its own (just a logo) counts.
     */
    public function signature(): ?string
    {
        $html = $this->get('campaign_signature');

        if ($html === null) {
            return null;
        }

        return trim(HtmlToText::convert($html)) !== '' || preg_match('#<img\b#i', $html) ? $html : null;
    }

    public function tracksOpens(): bool
    {
        return $this->get('campaign_track_opens', '1') === '1';
    }

    /**
     * How a new campaign on this channel will be paced, snapshotted onto
     * the campaign when it's created. SMS gateways take far more per
     * minute and don't need a pause; they still go in batches so progress
     * reads the same way.
     *
     * @return array{batch_size: int, per_minute: int, pause_minutes: int, include_signature: bool, track_opens: bool}
     */
    public function sendOptions(CampaignChannel $channel): array
    {
        if ($channel === CampaignChannel::Sms) {
            return [
                'batch_size' => 500,
                'per_minute' => (int) config('campaigns.sms_per_minute'),
                'pause_minutes' => 0,
                'include_signature' => false,
                'track_opens' => false,
            ];
        }

        return [
            'batch_size' => max(1, (int) $this->get('campaign_batch_size', (string) config('campaigns.batch_size'))),
            'per_minute' => max(1, (int) $this->get('campaign_emails_per_minute', (string) config('campaigns.emails_per_minute'))),
            'pause_minutes' => max(0, (int) $this->get('campaign_batch_pause_minutes', (string) config('campaigns.batch_pause_minutes'))),
            'include_signature' => $this->signature() !== null,
            'track_opens' => $this->tracksOpens(),
        ];
    }

    /**
     * "key=value" lines → array, for fixed parameters every request sends.
     *
     * @return array<string, string>
     */
    public function extraParams(): array
    {
        $params = [];

        foreach (preg_split('/\r\n|\r|\n/', (string) $this->get('sms_extra_params')) as $line) {
            if (str_contains($line, '=')) {
                [$key, $value] = array_map('trim', explode('=', $line, 2));
                if ($key !== '') {
                    $params[$key] = $value;
                }
            }
        }

        return $params;
    }

    /**
     * @return array<int, string> comma-separated setting → lowercase list
     */
    public function list(string $key): array
    {
        return array_values(array_filter(array_map(fn ($v) => mb_strtolower(trim($v)), explode(',', (string) $this->get($key)))));
    }

    private function decrypt(string $key): ?string
    {
        $encrypted = $this->get($key);

        if ($encrypted === null) {
            return null;
        }

        try {
            return Crypt::decryptString($encrypted);
        } catch (DecryptException) {
            return null;
        }
    }
}
