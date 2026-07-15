<?php

namespace Tests\Feature;

use App\Models\Lead;
use App\Models\Setting;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class WhatsappWebhookTest extends TestCase
{
    use RefreshDatabase;

    public function test_verify_succeeds_with_the_correct_token(): void
    {
        Setting::set('whatsapp_webhook_verify_token', 'secret-token');

        $this->get(route('whatsapp.webhook.verify', [
            'hub_verify_token' => 'secret-token',
            'hub_challenge' => 'challenge-123',
        ]))->assertOk()->assertSee('challenge-123');
    }

    public function test_verify_fails_with_the_wrong_token(): void
    {
        Setting::set('whatsapp_webhook_verify_token', 'secret-token');

        $this->get(route('whatsapp.webhook.verify', [
            'hub_verify_token' => 'wrong-token',
            'hub_challenge' => 'challenge-123',
        ]))->assertForbidden();
    }

    public function test_inbound_message_is_stored_and_matched_to_the_right_lead(): void
    {
        $lead = Lead::factory()->create(['whatsapp_number' => '15551234567']);

        $payload = [
            'entry' => [
                [
                    'changes' => [
                        [
                            'value' => [
                                'messages' => [
                                    [
                                        'id' => 'wamid.abc123',
                                        'from' => '15551234567',
                                        'type' => 'text',
                                        'timestamp' => (string) now()->timestamp,
                                        'text' => ['body' => 'Hello from the lead'],
                                    ],
                                ],
                            ],
                        ],
                    ],
                ],
            ],
        ];

        $this->postJson(route('whatsapp.webhook.handle'), $payload)->assertOk();

        $this->assertDatabaseHas('whatsapp_messages', [
            'lead_id' => $lead->id,
            'wa_message_id' => 'wamid.abc123',
            'body' => 'Hello from the lead',
            'direction' => 'inbound',
        ]);
    }

    public function test_webhook_never_errors_even_with_an_unrecognized_number(): void
    {
        $payload = [
            'entry' => [
                [
                    'changes' => [
                        [
                            'value' => [
                                'messages' => [
                                    ['id' => 'wamid.unknown', 'from' => '10000000000', 'type' => 'text', 'text' => ['body' => 'Hi']],
                                ],
                            ],
                        ],
                    ],
                ],
            ],
        ];

        $this->postJson(route('whatsapp.webhook.handle'), $payload)->assertOk();

        $this->assertDatabaseMissing('whatsapp_messages', ['wa_message_id' => 'wamid.unknown']);
    }
}
