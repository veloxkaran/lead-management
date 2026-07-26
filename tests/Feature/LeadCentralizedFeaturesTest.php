<?php

namespace Tests\Feature;

use App\Models\Lead;
use App\Models\SupportTicket;
use App\Models\Task;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LeadCentralizedFeaturesTest extends TestCase
{
    use RefreshDatabase;

    public function test_lead_show_page_lists_its_support_tickets_and_tasks(): void
    {
        $user = User::factory()->create();
        $lead = Lead::factory()->create(['assigned_user_id' => $user->id, 'created_by' => $user->id]);
        SupportTicket::factory()->create(['lead_id' => $lead->id, 'subject' => 'Cannot log in']);
        Task::factory()->create(['lead_id' => $lead->id, 'title' => 'Follow up on onboarding']);

        $response = $this->actingAs($user)->get(route('leads.show', $lead));

        $response->assertOk();
        $response->assertSee('Support Tickets');
        $response->assertSee('Cannot log in');
        $response->assertSee('Tasks');
        $response->assertSee('Follow up on onboarding');
    }

    public function test_support_ticket_can_be_raised_from_the_lead_page_and_returns_to_it(): void
    {
        $user = User::factory()->create();
        $lead = Lead::factory()->create(['assigned_user_id' => $user->id, 'created_by' => $user->id]);

        $this->actingAs($user)->from(route('leads.show', $lead))->post(route('leads.support-tickets.store', $lead), [
            'subject' => 'Client cannot access dashboard',
            'priority' => 'high',
        ])->assertRedirect(route('leads.show', $lead));

        $this->assertDatabaseHas('support_tickets', [
            'lead_id' => $lead->id,
            'subject' => 'Client cannot access dashboard',
            'raised_by' => $user->id,
        ]);
    }

    public function test_task_can_be_created_from_the_lead_page_and_returns_to_it(): void
    {
        $user = User::factory()->create();
        $lead = Lead::factory()->create(['assigned_user_id' => $user->id, 'created_by' => $user->id]);

        $this->actingAs($user)->from(route('leads.show', $lead))->post(route('leads.tasks.store', $lead), [
            'title' => 'Send renewal quote',
            'module' => 'lead',
            'priority' => 'medium',
        ])->assertRedirect(route('leads.show', $lead));

        $this->assertDatabaseHas('tasks', [
            'lead_id' => $lead->id,
            'title' => 'Send renewal quote',
            'module' => 'lead',
            'created_by' => $user->id,
        ]);
    }
}
