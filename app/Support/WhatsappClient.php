<?php

namespace App\Support;

use App\Settings\WhatsappSettings;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

class WhatsappClient
{
    public function __construct(protected WhatsappSettings $settings)
    {
    }

    /**
     * @return array{messages?: array<int, array{id: string}>}
     */
    public function sendText(string $to, string $body): array
    {
        return $this->post([
            'messaging_product' => 'whatsapp',
            'to' => $to,
            'type' => 'text',
            'text' => ['body' => $body],
        ]);
    }

    /**
     * @param  array<int, array<string, mixed>>  $components
     * @return array{messages?: array<int, array{id: string}>}
     */
    public function sendTemplate(string $to, string $name, string $language, array $components = []): array
    {
        return $this->post([
            'messaging_product' => 'whatsapp',
            'to' => $to,
            'type' => 'template',
            'template' => [
                'name' => $name,
                'language' => ['code' => $language],
                'components' => $components,
            ],
        ]);
    }

    /**
     * Approved templates live in Meta Business Manager, not this app — fetch
     * and cache them briefly rather than maintaining a local copy that can
     * drift out of sync with what's actually approved.
     *
     * @return array<int, array<string, mixed>>
     */
    public function fetchTemplates(): array
    {
        if (blank($this->settings->businessAccountId()) || blank($this->settings->accessToken())) {
            return [];
        }

        return Cache::remember('whatsapp.templates', now()->addMinutes(10), function () {
            $response = Http::withToken($this->settings->accessToken())
                ->timeout(10)
                ->get($this->settings->businessAccountUrl('message_templates'), [
                    'fields' => 'name,language,category,components,status',
                    'limit' => 100,
                ]);

            return $response->successful() ? ($response->json('data') ?? []) : [];
        });
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    protected function post(array $payload): array
    {
        $response = Http::withToken($this->settings->accessToken())
            ->timeout(10)
            ->post($this->settings->graphApiUrl('messages'), $payload);

        return $response->throw()->json() ?? [];
    }
}
