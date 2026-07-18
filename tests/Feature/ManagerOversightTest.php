<?php

namespace Tests\Feature;

use App\Enums\ActivityType;
use App\Enums\FollowUpStatus;
use App\Enums\UserRole;
use App\Models\FollowUp;
use App\Models\Lead;
use App\Models\LeadActivity;
use App\Models\Meeting;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ManagerOversightTest extends TestCase
{
    use RefreshDatabase;

    public function test_manager_can_access_the_full_reports_suite(): void
    {
        $manager = User::factory()->create(['role' => UserRole::Manager]);

        $this->actingAs($manager)->get(route('reports.index'))->assertOk();
        $this->actingAs($manager)->get(route('reports.master'))->assertOk();
        $this->actingAs($manager)->get(route('reports.conversion'))->assertOk();
    }

    public function test_business_development_cannot_access_the_full_reports_suite(): void
    {
        $bde = User::factory()->create(['role' => UserRole::BusinessDevelopment]);

        $this->actingAs($bde)->get(route('reports.index'))->assertForbidden();
    }

    public function test_manager_sees_every_users_activities_not_just_their_own(): void
    {
        $manager = User::factory()->create(['role' => UserRole::Manager]);
        $other = User::factory()->create();
        $lead = Lead::factory()->create(['assigned_user_id' => $other->id, 'created_by' => $other->id]);
        LeadActivity::factory()->create([
            'lead_id' => $lead->id,
            'created_by' => $other->id,
            'activity_type' => ActivityType::PhoneCall,
        ]);

        $response = $this->actingAs($manager)->get(route('activities.index'));

        $response->assertOk();
        $response->assertViewHas('activities', fn ($activities) => $activities->total() === 1);
    }

    public function test_manager_sees_every_users_follow_ups(): void
    {
        $manager = User::factory()->create(['role' => UserRole::Manager]);
        $other = User::factory()->create();
        $lead = Lead::factory()->create(['assigned_user_id' => $other->id, 'created_by' => $other->id]);
        FollowUp::factory()->create([
            'lead_id' => $lead->id,
            'created_by' => $other->id,
            'status' => FollowUpStatus::Pending,
        ]);

        $response = $this->actingAs($manager)->get(route('follow-ups.index'));

        $response->assertOk();
        $response->assertViewHas('followUps', fn ($followUps) => $followUps->total() === 1);
    }

    public function test_manager_can_use_the_all_meetings_scope(): void
    {
        $manager = User::factory()->create(['role' => UserRole::Manager]);
        $other = User::factory()->create();
        Meeting::factory()->create(['created_by' => $other->id]);

        $response = $this->actingAs($manager)->get(route('meetings.index', ['scope' => 'all']));

        $response->assertOk();
        $response->assertViewHas('meetings', fn ($meetings) => $meetings->total() === 1);
    }
}
