<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Enums\WhatsappMessageDirection;
use App\Jobs\SendWhatsappMessage;
use App\Models\Lead;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Bus;
use Tests\TestCase;

class WhatsappChatTest extends TestCase
{
    use RefreshDatabase;

    public function test_unassigned_user_cannot_view_the_whatsapp_thread(): void
    {
        $user = User::factory()->create();
        $lead = Lead::factory()->create(['whatsapp_number' => '15551234567']);

        $this->actingAs($user)->get(route('whatsapp.show', $lead))->assertForbidden();
    }

    public function test_assigned_user_can_view_the_whatsapp_thread(): void
    {
        $user = User::factory()->create();
        $lead = Lead::factory()->create(['whatsapp_number' => '15551234567']);
        $lead->whatsappUsers()->attach($user);

        $this->actingAs($user)->get(route('whatsapp.show', $lead))->assertOk();
    }

    public function test_super_admin_can_view_any_whatsapp_thread_regardless_of_assignment(): void
    {
        $superAdmin = User::factory()->create(['role' => UserRole::SuperAdmin]);
        $lead = Lead::factory()->create(['whatsapp_number' => '15551234567']);

        $this->actingAs($superAdmin)->get(route('whatsapp.show', $lead))->assertOk();
    }

    public function test_sending_a_free_text_message_is_rejected_when_the_24_hour_window_is_closed(): void
    {
        $user = User::factory()->create();
        $lead = Lead::factory()->create(['whatsapp_number' => '15551234567']);
        $lead->whatsappUsers()->attach($user);

        $this->actingAs($user)
            ->postJson(route('whatsapp.messages.store', $lead), ['body' => 'Hello'])
            ->assertStatus(422);

        $this->assertDatabaseMissing('whatsapp_messages', ['lead_id' => $lead->id]);
    }

    public function test_sending_a_free_text_message_dispatches_a_job_when_the_window_is_open(): void
    {
        Bus::fake();

        $user = User::factory()->create();
        $lead = Lead::factory()->create(['whatsapp_number' => '15551234567']);
        $lead->whatsappUsers()->attach($user);
        $lead->whatsappMessages()->create([
            'direction' => WhatsappMessageDirection::Inbound,
            'from_number' => '15551234567',
            'to_number' => '15557654321',
            'type' => 'text',
            'body' => 'Hi there',
            'status' => 'received',
            'wa_timestamp' => now(),
        ]);

        $this->actingAs($user)
            ->postJson(route('whatsapp.messages.store', $lead), ['body' => 'Hello back'])
            ->assertOk();

        $this->assertDatabaseHas('whatsapp_messages', [
            'lead_id' => $lead->id,
            'body' => 'Hello back',
            'direction' => 'outbound',
        ]);

        Bus::assertDispatched(SendWhatsappMessage::class);
    }

    public function test_only_super_admin_can_manage_whatsapp_user_assignments(): void
    {
        $manager = User::factory()->create(['role' => UserRole::Manager]);
        $lead = Lead::factory()->create();
        $assignee = User::factory()->create();

        $this->actingAs($manager)
            ->put(route('leads.whatsapp-users.update', $lead), ['user_ids' => [$assignee->id]])
            ->assertForbidden();
    }

    public function test_super_admin_can_assign_whatsapp_users_to_a_lead(): void
    {
        $superAdmin = User::factory()->create(['role' => UserRole::SuperAdmin]);
        $lead = Lead::factory()->create();
        $assignee = User::factory()->create();

        $this->actingAs($superAdmin)
            ->put(route('leads.whatsapp-users.update', $lead), ['user_ids' => [$assignee->id]])
            ->assertRedirect();

        $this->assertTrue($lead->whatsappUsers()->where('user_id', $assignee->id)->exists());
    }
}
