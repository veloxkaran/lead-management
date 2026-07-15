<?php

namespace Tests\Feature;

use App\Enums\ActivityType;
use App\Enums\RequirementPriority;
use App\Enums\RequirementStatus;
use App\Models\DealClosure;
use App\Models\Lead;
use App\Models\LeadActivity;
use App\Models\LeadNote;
use App\Models\LeadStatus;
use App\Models\LeadStatusHistory;
use App\Models\Requirement;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LeadWalkthroughTest extends TestCase
{
    use RefreshDatabase;

    public function test_walkthrough_renders_every_kind_of_step_for_a_closed_won_lead(): void
    {
        $user = User::factory()->create();
        $statusA = LeadStatus::factory()->create(['name' => 'New']);
        $statusB = LeadStatus::factory()->create(['name' => 'Closed Won', 'is_closed_won' => true]);

        $lead = Lead::factory()->create([
            'assigned_user_id' => $user->id,
            'created_by' => $user->id,
            'lead_status_id' => $statusB->id,
        ]);

        LeadStatusHistory::factory()->create([
            'lead_id' => $lead->id,
            'from_status_id' => null,
            'to_status_id' => $statusA->id,
            'changed_by' => $user->id,
            'changed_at' => now()->subDays(5),
        ]);
        LeadStatusHistory::factory()->create([
            'lead_id' => $lead->id,
            'from_status_id' => $statusA->id,
            'to_status_id' => $statusB->id,
            'changed_by' => $user->id,
            'changed_at' => now()->subDay(),
            'seconds_in_previous_status' => 4 * 86400,
        ]);

        LeadActivity::factory()->create([
            'lead_id' => $lead->id,
            'created_by' => $user->id,
            'activity_type' => ActivityType::PhoneCall,
        ]);

        LeadNote::factory()->create([
            'lead_id' => $lead->id,
            'author_id' => $user->id,
            'comment' => 'Client confirmed budget.',
        ]);

        Requirement::factory()->create([
            'lead_id' => $lead->id,
            'created_by' => $user->id,
            'priority' => RequirementPriority::High,
            'status' => RequirementStatus::Completed,
        ]);

        DealClosure::factory()->create([
            'lead_id' => $lead->id,
            'closed_by' => $user->id,
            'deal_value' => 5000,
        ]);

        $response = $this->actingAs($user)->get(route('leads.walkthrough', $lead));

        $response->assertOk();
        $response->assertSeeText("Let's walk through {$lead->company_name}");
        $response->assertSeeText('Deal closed! 🎉');
        $response->assertSeeText('Client confirmed budget.');
    }

    public function test_walkthrough_handles_a_lead_with_no_history_yet(): void
    {
        $user = User::factory()->create();
        $lead = Lead::factory()->create(['assigned_user_id' => $user->id, 'created_by' => $user->id]);

        $this->actingAs($user)->get(route('leads.walkthrough', $lead))->assertOk();
    }

    public function test_a_user_cannot_walk_through_someone_elses_lead(): void
    {
        $owner = User::factory()->create();
        $other = User::factory()->create();
        $lead = Lead::factory()->create(['assigned_user_id' => $owner->id, 'created_by' => $owner->id]);

        $this->actingAs($other)->get(route('leads.walkthrough', $lead))->assertForbidden();
    }
}
