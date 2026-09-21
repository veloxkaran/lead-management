<?php

namespace Tests\Feature;

use App\Models\SupportTicket;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SupportTicketDeletePermissionTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_regular_user_cannot_delete_a_support_ticket(): void
    {
        $user = User::factory()->create();
        $ticket = SupportTicket::factory()->create();

        $this->actingAs($user)->delete(route('support-tickets.destroy', $ticket))->assertForbidden();

        $this->assertModelExists($ticket);
    }

    public function test_a_super_admin_can_delete_a_support_ticket(): void
    {
        $superAdmin = User::factory()->superAdmin()->create();
        $ticket = SupportTicket::factory()->create();

        $this->actingAs($superAdmin)->delete(route('support-tickets.destroy', $ticket))->assertRedirect();

        $this->assertModelMissing($ticket);
    }

    public function test_the_delete_button_is_hidden_from_a_regular_user_on_the_index_page(): void
    {
        $user = User::factory()->create();
        SupportTicket::factory()->create();

        $response = $this->actingAs($user)->get(route('support-tickets.index'));

        $response->assertOk();
        $response->assertDontSee('data-confirm-delete', false);
    }

    public function test_the_delete_button_is_visible_to_a_super_admin_on_the_index_page(): void
    {
        $superAdmin = User::factory()->superAdmin()->create();
        SupportTicket::factory()->create();

        $response = $this->actingAs($superAdmin)->get(route('support-tickets.index'));

        $response->assertOk();
        $response->assertSee('data-confirm-delete', false);
    }
}
