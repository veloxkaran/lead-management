<?php

namespace App\Services;

use App\Support\CampaignSettings;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use RuntimeException;

/**
 * Sends one SMS through whatever HTTP gateway is configured in Campaign
 * Setup — endpoint, method, parameter names, how the API key is passed and
 * how success is recognised are all settings, so any provider with a
 * simple HTTP API works without code changes.
 */
class SmsGateway
{
    public function __construct(protected CampaignSettings $settings)
    {
    }

    /**
     * @return array{message_id: ?string, response: string}
     *
     * @throws RuntimeException when not configured, unreachable, or the gateway reports failure
     */
    public function send(string $to, string $message): array
    {
        $s = $this->settings;

        if (! $s->smsConfigured()) {
            throw new RuntimeException('SMS gateway is not set up yet (Administration → Campaign Setup).');
        }

        $number = ($s->get('sms_country_prefix') ?? '').$to;

        $params = [
            ...$s->extraParams(),
            $s->get('sms_to_param') => $number,
            $s->get('sms_message_param') => $message,
        ];

        if (($sender = $s->get('sms_sender_param')) && ($senderId = $s->get('sms_sender_id'))) {
            $params[$sender] = $senderId;
        }

        $request = Http::timeout(20)->acceptJson();
        $key = $s->smsApiKey();

        $authMode = $s->get('sms_auth_mode', 'param');

        if ($key !== null && $authMode === 'header') {
            $request->withHeaders([$s->get('sms_auth_name', 'Authorization') => $key]);
        } elseif ($key !== null && $authMode === 'bearer') {
            $request->withToken($key);
        } elseif ($key !== null) {
            $params[$s->get('sms_auth_name', 'token')] = $key;
        }

        try {
            $method = strtoupper($s->get('sms_method', 'POST'));

            $response = match (true) {
                $method === 'GET' => $request->get($s->get('sms_endpoint'), $params),
                $s->get('sms_format') === 'json' => $request->asJson()->post($s->get('sms_endpoint'), $params),
                default => $request->asForm()->post($s->get('sms_endpoint'), $params),
            };
        } catch (ConnectionException $e) {
            throw new RuntimeException('Could not reach the SMS gateway: '.$e->getMessage());
        }

        $body = Str::limit(trim($response->body()), 500);

        if (! $response->successful()) {
            throw new RuntimeException("SMS gateway returned HTTP {$response->status()}: {$body}");
        }

        if ($path = $s->get('sms_success_path')) {
            $actual = data_get($response->json(), $path);
            $actual = is_bool($actual) ? ($actual ? 'true' : 'false') : (string) $actual;

            if (mb_strtolower($actual) !== mb_strtolower((string) $s->get('sms_success_value'))) {
                throw new RuntimeException("SMS gateway rejected the message: {$body}");
            }
        }

        $messageId = ($idPath = $s->get('sms_message_id_path')) ? data_get($response->json(), $idPath) : null;

        return [
            'message_id' => is_scalar($messageId) && $messageId !== '' ? (string) $messageId : null,
            'response' => $body,
        ];
    }
}
