<?php

namespace Tests\Feature;

use App\Enums\RequirementPriority;
use App\Enums\RequirementStatus;
use App\Models\SupportTicket;
use App\Models\User;
use App\Support\SupportTicketSummary;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SupportTicketSummaryTest extends TestCase
{
    use RefreshDatabase;

    private function ticket(string $subject, RequirementPriority $priority, RequirementStatus $status, int $ageHours, ?int $resolvedAfterHours = null): SupportTicket
    {
        $ticket = SupportTicket::factory()->create(['subject' => $subject, 'priority' => $priority->value, 'status' => $status->value]);
        $created = now()->subHours($ageHours);
        $ticket->forceFill([
            'created_at' => $created,
            'resolved_at' => $resolvedAfterHours !== null ? $created->copy()->addHours($resolvedAfterHours) : null,
        ])->save();

        return $ticket;
    }

    private function seedTickets(): void
    {
        // Urgent allows 24h, low 336h (config/support_tickets.php).
        $this->ticket('Urgent and late', RequirementPriority::Urgent, RequirementStatus::InProgress, 30);
        $this->ticket('Low but recent', RequirementPriority::Low, RequirementStatus::Pending, 30);
        $this->ticket('Fixed quickly', RequirementPriority::High, RequirementStatus::Completed, 100, resolvedAfterHours: 50);
    }

    public function test_overdue_depends_on_how_long_the_priority_allows(): void
    {
        $this->seedTickets();

        $this->assertTrue(SupportTicket::where('subject', 'Urgent and late')->first()->isOverdue());
        $this->assertFalse(SupportTicket::where('subject', 'Low but recent')->first()->isOverdue());
        $this->assertFalse(SupportTicket::where('subject', 'Fixed quickly')->first()->isOverdue(), 'completed tickets are never overdue');

        $summary = SupportTicketSummary::of(SupportTicket::all());
        $this->assertSame([3, 2, 1, 1, 33], [$summary->total, $summary->open, $summary->overdue, $summary->completed, $summary->completionPercent()]);
        $this->assertSame('2 days, 2 hour and 0 min', $summary->avgResolutionTime);
    }

    public function test_the_list_shows_the_counts_strip_and_quick_views(): void
    {
        $user = User::factory()->create();
        $this->seedTickets();

        $this->actingAs($user)->get(route('support-tickets.index'))->assertOk()
            ->assertSeeInOrder(['All', '3', 'Open', '2', 'Overdue', '1', 'Completed', '1'])
            ->assertSee('33% done')
            ->assertSee('2 days, 2 hour and 0 min')
            ->assertSee('Open longer than its priority allows', false);

        $this->actingAs($user)->get(route('support-tickets.index', ['view' => 'overdue']))->assertOk()
            ->assertSee('Urgent and late')->assertDontSee('Low but recent')->assertDontSee('Fixed quickly');
        $this->actingAs($user)->get(route('support-tickets.index', ['view' => 'open']))->assertOk()
            ->assertSee('Urgent and late')->assertSee('Low but recent')->assertDontSee('Fixed quickly');
        $this->actingAs($user)->get(route('support-tickets.index', ['view' => 'completed']))->assertOk()
            ->assertSee('Fixed quickly')->assertDontSee('Urgent and late');
    }

    public function test_the_counts_follow_the_other_filters(): void
    {
        $user = User::factory()->create();
        $this->seedTickets();

        $this->actingAs($user)->get(route('support-tickets.index', ['priority' => 'low']))->assertOk()
            ->assertSee('1 filter active')
            ->assertSee('0% done')
            ->assertSee('None resolved yet')
            ->assertSeeInOrder(['All', '1', 'Open', '1', 'Overdue', '0', 'Completed', '0']);
    }

    public function test_the_overdue_threshold_is_configurable(): void
    {
        config(['support_tickets.overdue_after_hours.low' => 12]);
        $this->seedTickets();

        $this->assertTrue(SupportTicket::where('subject', 'Low but recent')->first()->isOverdue());
        $this->assertSame(2, SupportTicketSummary::of(SupportTicket::all())->overdue);
    }
}
