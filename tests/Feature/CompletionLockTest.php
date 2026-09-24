<?php

namespace Tests\Feature;

use App\Enums\RequirementStatus;
use App\Models\Lead;
use App\Models\Requirement;
use App\Models\SupportTicket;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CompletionLockTest extends TestCase
{
    use RefreshDatabase;

    private function completedRequirement(User $owner, $completedAt): Requirement
    {
        return Requirement::factory()->create([
            'lead_id' => Lead::factory()->create()->id,
            'created_by' => $owner->id,
            'status' => RequirementStatus::Completed,
            'completed_at' => $completedAt,
        ]);
    }

    private function requirementPayload(Requirement $requirement, RequirementStatus $status): array
    {
        return [
            'requirement' => $requirement->requirement,
            'priority' => $requirement->priority->value,
            'status' => $status->value,
        ];
    }

    private function ticketPayload(SupportTicket $ticket, RequirementStatus $status): array
    {
        return [
            'subject' => $ticket->subject,
            'details' => $ticket->details,
            'priority' => $ticket->priority->value,
            'status' => $status->value,
        ];
    }

    public function test_completed_requirement_is_editable_within_four_hours(): void
    {
        $owner = User::factory()->create();
        $requirement = $this->completedRequirement($owner, now()->subHours(3)->subMinutes(59));

        $this->actingAs($owner)->get(route('requirements.edit', $requirement))->assertOk();
        $this->actingAs($owner)->get(route('requirements.show', $requirement))
            ->assertSee('it can still be edited until');

        $this->actingAs($owner)->put(route('requirements.update', $requirement), $this->requirementPayload($requirement, RequirementStatus::InProgress))
            ->assertRedirect();

        $this->assertSame(RequirementStatus::InProgress, $requirement->fresh()->status);
    }

    public function test_completed_requirement_locks_after_four_hours_for_non_admins(): void
    {
        $owner = User::factory()->create();
        $requirement = $this->completedRequirement($owner, now()->subHours(4)->subMinute());

        $this->actingAs($owner)->get(route('requirements.edit', $requirement))->assertForbidden();
        $this->actingAs($owner)->put(route('requirements.update', $requirement), $this->requirementPayload($requirement, RequirementStatus::InProgress))
            ->assertForbidden();
        $this->actingAs($owner)->patch(route('requirements.status.update', $requirement), ['status' => 'in_progress', 'note' => 'Reopen'])
            ->assertForbidden();
        $this->actingAs($owner)->delete(route('requirements.destroy', $requirement))->assertForbidden();

        $this->actingAs($owner)->get(route('requirements.show', $requirement))
            ->assertOk()
            ->assertSee('is now locked')
            ->assertDontSee(route('requirements.edit', $requirement));

        $this->assertSame(RequirementStatus::Completed, $requirement->fresh()->status);
    }

    public function test_super_admin_can_reopen_a_locked_requirement_which_lifts_the_lock(): void
    {
        $superAdmin = User::factory()->superAdmin()->create();
        $owner = User::factory()->create();
        $requirement = $this->completedRequirement($owner, now()->subDays(2));

        $this->actingAs($superAdmin)->get(route('requirements.show', $requirement))
            ->assertSee('As Super Admin you can still edit or reopen it.');

        $this->actingAs($superAdmin)->patch(route('requirements.status.update', $requirement), ['status' => 'in_progress', 'note' => 'Client came back'])
            ->assertRedirect();

        $requirement->refresh();
        $this->assertNull($requirement->completed_at);
        $this->assertFalse($requirement->isLocked());
        $this->actingAs($owner)->get(route('requirements.edit', $requirement))->assertOk();
    }

    public function test_legacy_completed_requirement_without_timestamp_locks_from_updated_at(): void
    {
        $owner = User::factory()->create();
        $requirement = $this->completedRequirement($owner, null);
        $requirement->forceFill(['updated_at' => now()->subHours(5)])->saveQuietly();

        $this->assertTrue($requirement->fresh()->isLocked());
    }

    public function test_open_requirement_never_locks(): void
    {
        $requirement = Requirement::factory()->create(['status' => RequirementStatus::InProgress]);
        $requirement->forceFill(['updated_at' => now()->subYear()])->saveQuietly();

        $this->assertNull($requirement->fresh()->locksAt());
        $this->assertFalse($requirement->fresh()->isLocked());
    }

    public function test_completed_support_ticket_locks_after_four_hours_except_for_super_admin(): void
    {
        $user = User::factory()->create();
        $superAdmin = User::factory()->superAdmin()->create();
        $ticket = SupportTicket::factory()->create([
            'status' => RequirementStatus::Completed,
            'resolved_at' => now()->subHours(3),
        ]);

        // Still inside the window.
        $this->actingAs($user)->get(route('support-tickets.edit', $ticket))->assertOk();

        $this->travel(61)->minutes();

        $this->actingAs($user)->get(route('support-tickets.edit', $ticket))->assertForbidden();
        $this->actingAs($user)->put(route('support-tickets.update', $ticket), $this->ticketPayload($ticket, RequirementStatus::InProgress))
            ->assertForbidden();
        $this->actingAs($user)->get(route('support-tickets.show', $ticket))
            ->assertOk()
            ->assertSee('is now locked')
            ->assertDontSee(route('support-tickets.edit', $ticket));

        $this->actingAs($superAdmin)->put(route('support-tickets.update', $ticket), $this->ticketPayload($ticket, RequirementStatus::InProgress))
            ->assertRedirect();

        $ticket->refresh();
        $this->assertSame(RequirementStatus::InProgress, $ticket->status);
        $this->assertNull($ticket->resolved_at);
    }
}
