<?php

namespace Tests\Feature;

use App\Models\SupportTicket;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SupportTicketDetailsLockTest extends TestCase
{
    use RefreshDatabase;

    public function test_subject_and_details_can_be_edited_within_twelve_hours(): void
    {
        $user = User::factory()->create();
        $ticket = SupportTicket::factory()->create(['subject' => 'Original subject', 'details' => 'Original details']);

        $this->actingAs($user)->put(route('support-tickets.update', $ticket), [
            'subject' => 'Updated subject',
            'details' => 'Updated details',
            'priority' => $ticket->priority->value,
            'status' => $ticket->status->value,
        ])->assertRedirect();

        $ticket->refresh();
        $this->assertSame('Updated subject', $ticket->subject);
        $this->assertSame('Updated details', $ticket->details);
    }

    public function test_subject_and_details_cannot_be_edited_after_twelve_hours(): void
    {
        $user = User::factory()->create();
        $ticket = SupportTicket::factory()->create(['subject' => 'Original subject', 'details' => 'Original details']);
        $ticket->forceFill(['created_at' => now()->subHours(13)])->save();

        $response = $this->actingAs($user)->put(route('support-tickets.update', $ticket), [
            'subject' => 'Hacked subject',
            'details' => 'Hacked details',
            'priority' => $ticket->priority->value,
            'status' => $ticket->status->value,
        ]);

        $response->assertSessionHasErrors(['subject', 'details']);

        $ticket->refresh();
        $this->assertSame('Original subject', $ticket->subject);
        $this->assertSame('Original details', $ticket->details);
    }

    public function test_status_and_priority_stay_editable_after_twelve_hours(): void
    {
        $user = User::factory()->create();
        $ticket = SupportTicket::factory()->create(['subject' => 'Original subject', 'details' => 'Original details', 'status' => 'pending']);
        $ticket->forceFill(['created_at' => now()->subHours(13)])->save();

        $this->actingAs($user)->put(route('support-tickets.update', $ticket), [
            'subject' => $ticket->subject,
            'details' => $ticket->details,
            'priority' => 'high',
            'status' => 'completed',
        ])->assertRedirect();

        $ticket->refresh();
        $this->assertSame('completed', $ticket->status->value);
        $this->assertSame('high', $ticket->priority->value);
    }

    public function test_edit_page_locks_subject_and_details_fields_after_twelve_hours(): void
    {
        $user = User::factory()->create();
        $ticket = SupportTicket::factory()->create();
        $ticket->forceFill(['created_at' => now()->subHours(13)])->save();

        $response = $this->actingAs($user)->get(route('support-tickets.edit', $ticket));

        $response->assertOk();
        $response->assertSee('locked');
    }
}
