<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Models\SupportTicket;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RoleHandoffTest extends TestCase
{
    use RefreshDatabase;

    public function test_business_development_can_raise_a_support_ticket(): void
    {
        $bde = User::factory()->create(['role' => UserRole::BusinessDevelopment]);

        $this->actingAs($bde)->get(route('support-tickets.create'))->assertOk();
    }

    public function test_manager_can_raise_a_support_ticket_customer_success_sees_it(): void
    {
        $manager = User::factory()->create(['role' => UserRole::Manager]);
        $cs = User::factory()->create(['role' => UserRole::CustomerSuccess]);

        $this->actingAs($manager)->post(route('support-tickets.store'), [
            'subject' => 'Client cannot log in',
            'priority' => 'high',
        ])->assertRedirect(route('support-tickets.index'));

        $ticket = SupportTicket::firstWhere('subject', 'Client cannot log in');

        $this->assertDatabaseHas('support_tickets', ['id' => $ticket->id, 'raised_by' => $manager->id]);
        $this->actingAs($cs)->get(route('support-tickets.index'))->assertOk();
    }
}
